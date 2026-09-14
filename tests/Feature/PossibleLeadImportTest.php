<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('updates an existing lead matched by email and marks it a possible lead', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'status' => 'raw',
        'email' => 'ada@acme.test',
        'company_name' => 'Acme Corp',
        'product_requested' => 'Ringlock',
        'notes' => null,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'possible-leads.csv',
        "Company,Email,Product Request,Notes\nAcme Corp,ada@acme.test,Cuplock,Verification: https://tendata.example/acme\n",
    );

    $response = $this->actingAs($reviewer)->post(route('verification.possible-leads.import.store'), [
        'agent_id' => $agent->id,
        'file' => $file,
    ]);

    $response->assertRedirect(route('verification.index', ['status' => 'possible_lead']));
    $response->assertSessionHas('toast.message', '1 existing lead(s) updated, 0 new possible lead(s) created.');
    $lead->refresh();
    expect($lead->status->value)->toBe('possible_lead')
        ->and($lead->agent_id)->toBe($agent->id)
        ->and($lead->product_requested)->toBe('Ringlock, Cuplock')
        ->and($lead->notes)->toContain('Verification: https://tendata.example/acme');
});

it('updates an existing lead matched by company name when no email is given', function () {
    $reviewer = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'status' => 'needs_review',
        'company_name' => 'Acme Corp',
        'normalized_company_name' => 'acme corp',
        'city' => null,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'possible-leads.csv',
        "Company,City\nAcme Corp,Austin\n",
    );

    $this->actingAs($reviewer)->post(route('verification.possible-leads.import.store'), [
        'agent_id' => $agent->id,
        'file' => $file,
    ])->assertSessionHas('toast.message', '1 existing lead(s) updated, 0 new possible lead(s) created.');

    expect($lead->refresh()->status->value)->toBe('possible_lead')
        ->and($lead->city)->toBe('Austin');
});

it('creates a fresh possible lead owned by the chosen agent when nothing matches', function () {
    // Regression test: LeadVerificationService::verify() is called right after
    // creation to mark the lead Possible Lead. verify() used to reseed only
    // company_name/website/email/phone from the lead before normalizing, so
    // contact_person - which normalize() always recomputes, defaulting to
    // null when absent - got wiped back out immediately after being set.
    $reviewer = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'possible-leads.csv',
        "Company,Contact,Email,Product Request\nBrand New Co,Ada Lovelace,new@brandnew.test,Ringlock\n",
    );

    $this->actingAs($reviewer)->post(route('verification.possible-leads.import.store'), [
        'agent_id' => $agent->id,
        'file' => $file,
    ])->assertSessionHas('toast.message', '0 existing lead(s) updated, 1 new possible lead(s) created.');

    $lead = Lead::query()->where('email', 'new@brandnew.test')->firstOrFail();
    expect($lead->status->value)->toBe('possible_lead')
        ->and($lead->agent_id)->toBe($agent->id)
        ->and($lead->contact_person)->toBe('Ada Lovelace')
        ->and($lead->product_requested)->toBe('Ringlock');
});

it('merges a continuation row with no company name into whichever lead the row above it touched', function () {
    $reviewer = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create([
        'status' => 'raw',
        'company_name' => 'Acme Corp',
        'normalized_company_name' => 'acme corp',
        'product_requested' => null,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'possible-leads.csv',
        "Company,Product Request\nAcme Corp,Ringlock\n,Cuplock\n,Leadgers\n",
    );

    $this->actingAs($reviewer)->post(route('verification.possible-leads.import.store'), [
        'agent_id' => $agent->id,
        'file' => $file,
    ])->assertSessionHas('toast.message', '1 existing lead(s) updated, 0 new possible lead(s) created.');

    expect($lead->refresh()->status->value)->toBe('possible_lead')
        ->and($lead->product_requested)->toBe('Ringlock, Cuplock, Leadgers');
});

it('skips a company name that matches more than one existing lead and reports it', function () {
    $reviewer = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Acme Corp', 'normalized_company_name' => 'acme corp', 'email' => 'one@acme.test']);
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Acme Corp', 'normalized_company_name' => 'acme corp', 'email' => 'two@acme.test']);
    $file = UploadedFile::fake()->createWithContent('possible-leads.csv', "Company\nAcme Corp\n");

    $this->actingAs($reviewer)->post(route('verification.possible-leads.import.store'), [
        'agent_id' => $agent->id,
        'file' => $file,
    ])->assertSessionHas('toast.message', function (string $message) {
        return str_contains($message, 'Matched more than one existing lead') && str_contains($message, 'Acme Corp');
    });

    expect(Lead::query()->where('normalized_company_name', 'acme corp')->count())->toBe(2);
});

it('prevents agents from importing possible leads', function () {
    $agent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('possible-leads.csv', "Company\nAcme Corp\n");

    $this->actingAs($agent)->get(route('verification.possible-leads.import'))->assertForbidden();
    $this->actingAs($agent)->post(route('verification.possible-leads.import.store'), [
        'agent_id' => $agent->id,
        'file' => $file,
    ])->assertForbidden();
});

it('lets a sub-administrator use the possible-leads import tool', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $agent = User::factory()->create(['name' => 'Assigned Agent']);

    $this->actingAs($reviewer)->get(route('verification.possible-leads.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('verification/possible-leads/import')->where('agents.0.id', $agent->id));
});
