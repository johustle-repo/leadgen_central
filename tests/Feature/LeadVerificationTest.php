<?php

use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use App\Services\LeadVerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('preserves contact_person when verify() is called without re-passing it', function () {
    // Regression test: normalize() unconditionally recomputes contact_person
    // (defaulting to null when absent from the merged data) rather than
    // leaving it untouched, so verify() must always reseed it from the
    // lead's current value - a caller that verifies a lead without
    // explicitly re-passing contact_person must not have it wiped out.
    $lead = Lead::factory()->create(['status' => 'raw', 'contact_person' => 'Ada Lovelace']);
    $actor = User::factory()->administrator()->create();

    app(LeadVerificationService::class)->verify($lead, ['status' => 'possible_lead'], $actor);

    expect($lead->refresh()->contact_person)->toBe('Ada Lovelace');
});

it('preserves secondary_email when verify() is called without re-passing it', function () {
    $lead = Lead::factory()->create(['status' => 'raw', 'secondary_email' => 'second@example.com']);
    $actor = User::factory()->administrator()->create();

    app(LeadVerificationService::class)->verify($lead, ['status' => 'possible_lead'], $actor);

    expect($lead->refresh()->secondary_email)->toBe('second@example.com');
});

it('lets a reviewer set and update the secondary email through the verify form', function () {
    $lead = Lead::factory()->create(['status' => 'raw', 'company_name' => 'Acme']);
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)->put(route('verification.update', $lead), [
        'company_name' => 'Acme',
        'status' => 'possible_lead',
        'secondary_email' => 'Second@Example.com',
    ])->assertRedirect();

    expect($lead->refresh()->secondary_email)->toBe('second@example.com');
});

it('exposes canDelete on the Lead Review queue and a single lead only to administrators', function (string $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);
    $lead = Lead::factory()->create();

    $this->actingAs($user)->get(route('verification.index'))->assertInertia(fn (Assert $page) => $page
        ->where('canDelete', $expected));

    if ($user->canViewAllLeads()) {
        $this->actingAs($user)->get(route('verification.show', $lead))->assertInertia(fn (Assert $page) => $page
            ->where('canDelete', $expected));
    }
})->with([
    'sub-administrator' => ['sub_administrator', false],
    'administrator' => ['administrator', true],
    'super administrator' => ['super_administrator', true],
]);

it('lets a sub-administrator classify a lead and records status history', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'raw']);

    $response = $this->actingAs($reviewer)->put(route('verification.update', $lead), ['status' => 'qualified_lead', 'company_name' => $lead->company_name, 'remarks' => 'Verified contact and company.']);

    $response->assertRedirect();
    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'qualified_lead', 'validation_status' => 'verified', 'verified_by' => $reviewer->id]);
    $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'old_status' => 'raw', 'new_status' => 'qualified_lead', 'changed_by' => $reviewer->id]);
});

it('lets a reviewer record the date a possible lead replied in Reply.io', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'possible_lead', 'replied_at' => null]);

    $this->actingAs($reviewer)->put(route('verification.update', $lead), [
        'status' => 'possible_lead',
        'company_name' => $lead->company_name,
        'replied_at' => '2026-09-14',
    ])->assertRedirect();

    expect($lead->refresh()->replied_at?->toDateString())->toBe('2026-09-14');
});

it('lets a reviewer reassign a lead to a different agent while verifying it', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $originalOwner = User::factory()->create();
    $newOwner = User::factory()->create();
    $lead = Lead::factory()->for($originalOwner, 'agent')->create(['status' => 'raw']);

    $this->actingAs($reviewer)->get(route('verification.show', $lead))
        ->assertInertia(fn (Assert $page) => $page->has('agents', 2));

    $this->actingAs($reviewer)->put(route('verification.update', $lead), [
        'status' => 'possible_lead',
        'company_name' => $lead->company_name,
        'agent_id' => $newOwner->id,
    ])->assertRedirect();

    expect($lead->refresh()->agent_id)->toBe($newOwner->id);
});

it('lets administrators search the verification contact workspace', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $owner = User::factory()->create(['name' => 'North Team Agent']);
    $matching = Lead::factory()->for($owner, 'agent')->create(['status' => 'possible_lead', 'company_name' => 'Atlas Scaffolding', 'contact_person' => 'Maria Santos', 'email' => 'maria@atlas.test']);
    Lead::factory()->create(['status' => 'possible_lead', 'company_name' => 'Unrelated Company', 'contact_person' => 'Other Contact']);

    $this->actingAs($reviewer)->get(route('verification.index', ['search' => 'Maria Santos']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('verification/index')
            ->where('filters.search', 'Maria Santos')
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $matching->id));

    $this->actingAs($reviewer)->get(route('verification.index', ['search' => 'North Team Agent']))
        ->assertInertia(fn (Assert $page) => $page->where('leads.data.0.id', $matching->id));
});

it('searches every lead regardless of status, ignoring the default Possible Leads view', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $rawLead = Lead::factory()->create(['status' => 'raw', 'company_name' => 'Raw Prospect Inc', 'contact_person' => 'Searchable Contact']);
    Lead::factory()->create(['status' => 'possible_lead', 'company_name' => 'Unrelated Possible Lead']);

    // With no search term, the default view is Possible Leads only.
    $this->actingAs($reviewer)->get(route('verification.index'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'possible_lead')
            ->has('leads.data', 1));

    // Searching finds a raw-status lead too, even though it's outside the
    // default Possible Leads view - search reaches the whole leads table.
    $this->actingAs($reviewer)->get(route('verification.index', ['search' => 'Searchable Contact']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', '')
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $rawLead->id));
});

it('lets a reviewer filter possible leads down to a single agent', function () {
    $reviewer = User::factory()->administrator()->create();
    $agentOne = User::factory()->create(['name' => 'Agent One']);
    $agentTwo = User::factory()->create(['name' => 'Agent Two']);
    $ownedByOne = Lead::factory()->for($agentOne, 'agent')->create(['status' => 'possible_lead']);
    Lead::factory()->for($agentTwo, 'agent')->create(['status' => 'possible_lead']);

    $this->actingAs($reviewer)->get(route('verification.index', ['agent_id' => $agentOne->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $ownedByOne->id)
            ->where('filters.agent_id', (string) $agentOne->id));
});

it('only lists agents, not admin-tier accounts, in the Lead Review agent filter', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $agent = User::factory()->create(['name' => 'Field Agent']);
    User::factory()->administrator()->create(['name' => 'An Administrator']);

    $this->actingAs($reviewer)->get(route('verification.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('agents', 1)
            ->where('agents.0.id', $agent->id));
});

it('lets a sub-administrator save a contact to the possible leads list', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'needs_review']);

    $this->actingAs($reviewer)->put(route('verification.possible', $lead), ['remarks' => 'Potential buyer with active projects.'])
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Contact saved to Possible Leads.');

    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'possible_lead', 'verified_by' => $reviewer->id]);
    $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'new_status' => 'possible_lead', 'changed_by' => $reviewer->id, 'remarks' => 'Potential buyer with active projects.']);
});

it('lets administrators add a possible lead and assign it to an active agent', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $owner = User::factory()->create(['name' => 'Assigned Agent']);

    $this->actingAs($reviewer)->get(route('verification.possible-leads.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('verification/possible-create')
            ->where('agents.0.id', $owner->id)
            ->where('defaults.lead_date', today()->toDateString()));

    $response = $this->actingAs($reviewer)->post(route('verification.possible-leads.store'), [
        'agent_id' => $owner->id,
        'lead_date' => '2026-09-02',
        'company_name' => 'Atlas Scaffolding',
        'contact_person' => 'MARIA SANTOS',
        'email' => 'maria@atlas.test',
        'secondary_email' => 'Ops@Atlas.test',
        'country_code' => 'us',
        'city' => 'Texas',
        'data_source' => 'Manual',
        'notes' => 'Requested pricing for an upcoming project.',
    ]);

    $lead = Lead::query()->where('email', 'maria@atlas.test')->firstOrFail();
    $response->assertRedirect(route('verification.show', $lead))->assertSessionHas('toast.message', 'Possible lead added successfully.');
    expect($lead->status->value)->toBe('possible_lead')
        ->and($lead->agent_id)->toBe($owner->id)
        ->and($lead->contact_person)->toBe('Maria Santos')
        ->and($lead->country_code)->toBe('US')
        ->and($lead->secondary_email)->toBe('ops@atlas.test')
        ->and($lead->created_by)->toBe($reviewer->id);
    $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'old_status' => 'raw', 'new_status' => 'possible_lead', 'changed_by' => $reviewer->id]);
});

it('prevents agents and inactive owners from adding possible leads', function () {
    $agent = User::factory()->create();
    $reviewer = User::factory()->administrator()->create();
    $inactiveOwner = User::factory()->create(['status' => 'inactive']);
    $payload = ['agent_id' => $inactiveOwner->id, 'lead_date' => today()->toDateString(), 'company_name' => 'Blocked Company'];

    $this->actingAs($agent)->get(route('verification.possible-leads.create'))->assertForbidden();
    $this->actingAs($agent)->post(route('verification.possible-leads.store'), $payload)->assertForbidden();
    $this->actingAs($reviewer)->post(route('verification.possible-leads.store'), $payload)->assertSessionHasErrors('agent_id');
    $this->assertDatabaseMissing('leads', ['company_name' => 'Blocked Company']);
});

it('exports only matching possible leads as a safe CSV and records the export', function () {
    $reviewer = User::factory()->administrator()->create();
    Lead::factory()->create(['status' => 'possible_lead', 'company_name' => '=Potential Buyer', 'contact_person' => 'Maria Santos', 'email' => 'maria@example.test']);
    Lead::factory()->create(['status' => 'possible_lead', 'company_name' => 'Different Buyer', 'contact_person' => 'John Other']);
    Lead::factory()->create(['status' => 'qualified_lead', 'company_name' => 'Qualified Buyer', 'contact_person' => 'Maria Santos']);

    $response = $this->actingAs($reviewer)->get(route('verification.possible-leads.export', ['search' => 'Maria']));

    $response->assertOk()->assertDownload('Possible-Leads-'.today()->format('m-d-Y').'.csv');
    expect($response->streamedContent())
        ->toContain("'=Potential Buyer")
        ->toContain('maria@example.test')
        ->not->toContain('Different Buyer')
        ->not->toContain('Qualified Buyer');
    $this->assertDatabaseHas('audit_logs', ['user_id' => $reviewer->id, 'action' => 'possible_leads.exported']);
});

it('keeps possible lead documents private and allows reviewers to manage them', function () {
    Storage::fake('local');
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'possible_lead']);
    $file = UploadedFile::fake()->create('company-profile.pdf', 100, 'application/pdf');

    $this->actingAs($reviewer)->post(route('leads.attachments.store', $lead), ['attachment' => $file, 'label' => 'Company profile'])
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Contact document uploaded successfully.');

    $attachment = LeadAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    $this->assertDatabaseHas('lead_attachments', ['lead_id' => $lead->id, 'uploaded_by' => $reviewer->id, 'label' => 'Company profile']);

    $this->actingAs($reviewer)->get(route('leads.attachments.download', [$lead, $attachment]))
        ->assertOk()
        ->assertDownload('company-profile.pdf');

    $this->actingAs($reviewer)->delete(route('leads.attachments.destroy', [$lead, $attachment]))
        ->assertRedirect();
    Storage::disk('local')->assertMissing($attachment->path);
    $this->assertDatabaseMissing('lead_attachments', ['id' => $attachment->id]);
});

it('rejects agents and non-possible contacts from document management', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $reviewer = User::factory()->subAdministrator()->create();
    $possibleLead = Lead::factory()->create(['status' => 'possible_lead']);
    $reviewLead = Lead::factory()->create(['status' => 'needs_review']);
    $file = fn () => UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf');

    $this->actingAs($agent)->post(route('leads.attachments.store', $possibleLead), ['attachment' => $file()])->assertForbidden();
    $this->actingAs($reviewer)->post(route('leads.attachments.store', $reviewLead), ['attachment' => $file()])->assertForbidden();
    $this->assertDatabaseCount('lead_attachments', 0);
});

it('rejects unsafe document types and cross-contact attachment access', function () {
    Storage::fake('local');
    $reviewer = User::factory()->administrator()->create();
    $lead = Lead::factory()->create(['status' => 'possible_lead']);
    $otherLead = Lead::factory()->create(['status' => 'possible_lead']);

    $this->actingAs($reviewer)->post(route('leads.attachments.store', $lead), [
        'attachment' => UploadedFile::fake()->create('script.exe', 10, 'application/x-dosexec'),
    ])->assertSessionHasErrors('attachment');

    $safeFile = UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf');
    $this->actingAs($reviewer)->post(route('leads.attachments.store', $lead), ['attachment' => $safeFile]);
    $attachment = LeadAttachment::query()->firstOrFail();

    $this->actingAs($reviewer)->get(route('leads.attachments.download', [$otherLead, $attachment]))->assertForbidden();
    $this->actingAs($reviewer)->delete(route('leads.attachments.destroy', [$otherLead, $attachment]))->assertForbidden();
    Storage::disk('local')->assertExists($attachment->path);
});

it('prevents agents from using the verification workspace', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($agent)->get(route('verification.show', $lead))->assertForbidden();
    $this->actingAs($agent)->put(route('verification.update', $lead), ['status' => 'qualified_lead', 'company_name' => $lead->company_name])->assertForbidden();
});

it('forwards qualified leads and preserves forwarding and status history', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'qualified_lead']);

    $response = $this->actingAs($reviewer)->post(route('leads.forwardings.store', $lead), ['recipient_name' => 'Sales Team', 'recipient_email' => 'sales@example.com', 'remarks' => 'Ready for outreach.']);

    $response->assertRedirect();
    $this->assertDatabaseHas('lead_forwardings', ['lead_id' => $lead->id, 'forwarded_by' => $reviewer->id, 'recipient_email' => 'sales@example.com']);
    $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'old_status' => 'qualified_lead', 'new_status' => 'forwarded']);
});

it('stores structured notes with their author and timestamp', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['created_by' => $agent->id]);

    $response = $this->actingAs($agent)->post(route('leads.notes.store', $lead), ['note' => 'Confirmed the contact email.', 'note_type' => 'verification']);

    $response->assertRedirect();
    $this->assertDatabaseHas('lead_notes', ['lead_id' => $lead->id, 'user_id' => $agent->id, 'note' => 'Confirmed the contact email.', 'note_type' => 'verification']);
});

it('does not forward a lead before it is qualified', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $lead = Lead::factory()->create(['status' => 'possible_lead']);

    $response = $this->actingAs($reviewer)->post(route('leads.forwardings.store', $lead), ['recipient_email' => 'sales@example.com']);

    $response->assertSessionHasErrors('lead');
    $this->assertDatabaseCount('lead_forwardings', 0);
});

it('renders verification records after their owner account is deleted', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $formerAgent = User::factory()->create();
    $lead = Lead::factory()->for($formerAgent, 'agent')->create(['status' => 'needs_review']);
    $formerAgent->delete();

    $this->actingAs($reviewer)->get(route('verification.index', ['status' => 'needs_review']))->assertInertia(fn (Assert $page) => $page
        ->component('verification/index')
        ->where('leads.data.0.id', $lead->id)
        ->where('leads.data.0.agent', null));
    $this->actingAs($reviewer)->get(route('verification.show', $lead))->assertInertia(fn (Assert $page) => $page
        ->component('verification/show')
        ->where('lead.id', $lead->id)
        ->where('lead.agent', null));
});
