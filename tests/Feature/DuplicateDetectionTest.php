<?php

use App\Models\City;
use App\Models\Country;
use App\Models\DuplicateLog;
use App\Models\DuplicateMatch;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('retains original ownership and logs a second agents exact duplicate upload', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $original = Lead::factory()->for($owner, 'agent')->create(['company_name' => 'ABC Construction', 'normalized_company_name' => 'abc construction', 'website' => 'https://example.com', 'website_domain' => 'example.com', 'contact_person' => 'Jane Smith', 'email' => 'jane@example.com', 'created_by' => $owner->id]);
    $uploadingAgent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('leads.csv', "Company,Website,Name,Email\nABC Construction,https://www.example.com/about,Jane Smith,jane@example.com\n");
    $this->actingAs($uploadingAgent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::query()->whereBelongsTo($uploadingAgent)->firstOrFail();

    $this->actingAs($uploadingAgent)->post(route('uploads.process', $batch), ['mapping' => ['Company' => 'company_name', 'Website' => 'website', 'Name' => 'contact_person', 'Email' => 'email']])->assertRedirect(route('uploads.show', $batch));

    expect(Lead::count())->toBe(1)->and($original->refresh()->agent_id)->toBe($owner->id)->and($batch->refresh()->exact_duplicate_rows)->toBe(1);
    $this->assertDatabaseHas((new DuplicateLog)->getTable(), ['uploading_agent_id' => $uploadingAgent->id, 'original_lead_id' => $original->id, 'original_owner_id' => $owner->id, 'upload_batch_id' => $batch->id]);
});

it('cleans an agents exported manual lead by updating the original instead of marking it duplicate', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $country = Country::factory()->create(['name' => 'United States', 'normalized_name' => 'united states', 'iso2' => 'US']);
    City::factory()->for($country)->create(['name' => 'Austin', 'normalized_name' => 'austin', 'timezone' => 'America/Chicago']);
    $original = Lead::factory()->for($agent, 'agent')->create([
        'source' => 'manual',
        'status' => 'raw',
        'company_name' => 'Acme Ventures',
        'normalized_company_name' => 'acme ventures',
        'website' => 'acme.test',
        'contact_person' => 'Ada',
        'email' => 'ada@acme.test',
        'created_by' => $agent->id,
    ]);
    $file = UploadedFile::fake()->createWithContent('08-25-2026-Leads-Raw.csv', "Date,Company,Website,First Name,Email,Country,City,Import Trades,LinkedIn,Sources of Data,Link\n08/25/2026,Acme Ventures,acme.test,Ada,ada@acme.test,US,Austin,Machinery,https://linkedin.com/in/ada,Manual,https://example.com/acme\n");
    $this->actingAs($agent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::query()->whereBelongsTo($agent)->firstOrFail();

    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => [
        'Date' => 'lead_date',
        'Company' => 'company_name',
        'Website' => 'website',
        'First Name' => 'contact_person',
        'Email' => 'email',
        'Country' => 'country',
        'City' => 'city',
        'Import Trades' => 'import_trades',
        'LinkedIn' => 'linkedin_url',
        'Sources of Data' => 'data_source',
        'Link' => 'source_url',
    ]]);

    expect(Lead::count())->toBe(1)
        ->and($batch->refresh()->duplicate_rows)->toBe(0)
        ->and($batch->accepted_rows)->toBe(1);
    $this->assertDatabaseHas('leads', [
        'id' => $original->id,
        'source' => 'manual',
        'status' => 'validated',
        'upload_batch_id' => null,
        'country_code' => 'US',
        'city' => 'Austin',
        'timezone' => 'America/Chicago',
        'import_trades' => 'Machinery',
        'updated_by' => $agent->id,
    ]);
    $this->assertDatabaseHas('upload_rows', [
        'upload_batch_id' => $batch->id,
        'processing_status' => 'accepted',
        'lead_id' => $original->id,
        'duplicate_match_id' => null,
    ]);
});

it('updates a missing date on an agents matching uploaded lead when requested', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $original = Lead::factory()->for($agent, 'agent')->create([
        'source' => 'csv',
        'lead_date' => null,
        'company_name' => 'Acme Ventures',
        'contact_person' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
        'created_by' => $agent->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        '08-25-2026-Lead-1-Raw.csv',
        "Company,First Name,Email\nAcme Ventures,Ada Lovelace,ada@acme.test\n",
    );

    $this->actingAs($agent)->post(route('uploads.store'), [
        'file' => $file,
        'duplicate_handling' => 'update_missing',
    ]);
    $batch = UploadBatch::query()->whereBelongsTo($agent)->firstOrFail();
    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => [
        'Company' => 'company_name',
        'First Name' => 'contact_person',
        'Email' => 'email',
    ]]);

    expect(Lead::count())->toBe(1)
        ->and($original->refresh()->lead_date?->toDateString())->toBe('2026-08-25')
        ->and($batch->refresh()->duplicate_rows)->toBe(0)
        ->and($batch->accepted_rows)->toBe(1);
});

it('does not update another agents matching lead when requested', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $original = Lead::factory()->for($owner, 'agent')->create([
        'source' => 'csv',
        'lead_date' => null,
        'email' => 'shared@acme.test',
        'created_by' => $owner->id,
    ]);
    $uploadingAgent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        '08-25-2026-Lead-1-Raw.csv',
        "Company,Email\nAcme Ventures,shared@acme.test\n",
    );

    $this->actingAs($uploadingAgent)->post(route('uploads.store'), [
        'file' => $file,
        'duplicate_handling' => 'update_missing',
    ]);
    $batch = UploadBatch::query()->whereBelongsTo($uploadingAgent)->firstOrFail();
    $this->actingAs($uploadingAgent)->post(route('uploads.process', $batch), ['mapping' => [
        'Company' => 'company_name',
        'Email' => 'email',
    ]]);

    expect($original->refresh()->lead_date)->toBeNull()
        ->and($batch->refresh()->exact_duplicate_rows)->toBe(1);
});

it('allows different people from the same company and website', function () {
    Lead::factory()->create(['company_name' => 'Acme Industrial Supply', 'normalized_company_name' => 'acme industrial supply', 'website' => 'https://acme.example', 'website_domain' => 'acme.example', 'contact_person' => 'Jane Smith', 'email' => 'jane@acme.example']);

    $match = app(DuplicateDetectionService::class)->find(['normalized_company_name' => 'acme industrial supply', 'website_domain' => 'acme.example', 'contact_person' => 'John Jones', 'email' => 'john@acme.example']);

    expect($match)->toBeNull();
});

it('allows matching contact names when their emails are different', function () {
    Lead::factory()->create(['contact_person' => 'Alex Lee', 'email' => 'alex.one@example.com']);

    $match = app(DuplicateDetectionService::class)->find(['contact_person' => 'Alex Lee', 'email' => 'alex.two@example.com']);

    expect($match)->toBeNull();
});

it('uses an exact contact name as a fallback when neither lead has an email', function () {
    $existing = Lead::factory()->create(['contact_person' => 'Maria Santos', 'email' => null]);

    $match = app(DuplicateDetectionService::class)->find(['contact_person' => '  maria   santos ', 'email' => null]);

    expect($match)
        ->not->toBeNull()
        ->and($match['type'])->toBe('exact')
        ->and($match['lead']->id)->toBe($existing->id)
        ->and($match['fields'])->toBe(['contact_name']);
});

it('restricts duplicate review to administrators and sub-administrators', function () {
    $agent = User::factory()->create();
    $reviewer = User::factory()->subAdministrator()->create();

    $this->actingAs($agent)->get(route('duplicates.index'))->assertForbidden();
    $this->actingAs($reviewer)->get(route('duplicates.index'))->assertOk();
});

it('returns historical duplicate matches when a related lead was deleted', function () {
    $reviewer = User::factory()->subAdministrator()->create();
    $existingLead = Lead::factory()->create();
    $match = DuplicateMatch::factory()->for($existingLead, 'existingLead')->create();
    $existingLead->delete();

    $this->actingAs($reviewer)->get(route('duplicates.index'))->assertInertia(fn (Assert $page) => $page
        ->component('duplicates/index')
        ->where('matches.data.0.id', $match->id)
        ->where('matches.data.0.existing_lead', null));
});
