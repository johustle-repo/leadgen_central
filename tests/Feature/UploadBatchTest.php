<?php

use App\Jobs\ProcessUploadBatch;
use App\Models\Country;
use App\Models\Lead;
use App\Models\SystemSetting;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('allows upload processing to finish without a queue timeout', function () {
    $job = new ProcessUploadBatch(123);

    expect($job->timeout)->toBe(0)
        ->and($job->failOnTimeout)->toBeTrue();
});

it('shows upload history for a soft deleted owner without crashing', function () {
    $administrator = User::factory()->create(['role' => 'administrator']);
    $formerAgent = User::factory()->create(['name' => 'Former Agent']);
    $batch = UploadBatch::factory()->for($formerAgent)->create();
    $formerAgent->delete();

    $response = $this->actingAs($administrator)->get(route('uploads.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('uploads/index')
        ->where('batches.data.0.id', $batch->id)
        ->where('batches.data.0.user.name', 'Former Agent'));
});

it('sorts upload history by filename', function () {
    $administrator = User::factory()->administrator()->create();
    $zuluBatch = UploadBatch::factory()->create(['original_filename' => 'zulu.csv']);
    $alphaBatch = UploadBatch::factory()->create(['original_filename' => 'alpha.csv']);

    $this->actingAs($administrator)->get(route('uploads.index', ['sort' => 'filename_asc']))->assertInertia(fn (Assert $page) => $page
        ->component('uploads/index')
        ->where('sort', 'filename_asc')
        ->where('batches.data.0.id', $alphaBatch->id)
        ->where('batches.data.1.id', $zuluBatch->id));
});

it('falls back to newest sorting for unsupported input', function () {
    $administrator = User::factory()->administrator()->create();
    $olderBatch = UploadBatch::factory()->create(['created_at' => '2026-08-01 09:00:00']);
    $newerBatch = UploadBatch::factory()->create(['created_at' => '2026-08-31 09:00:00']);

    $this->actingAs($administrator)->get(route('uploads.index', ['sort' => 'created_at desc; drop table']))->assertInertia(fn (Assert $page) => $page
        ->component('uploads/index')
        ->where('sort', 'newest')
        ->where('batches.data.0.id', $newerBatch->id)
        ->where('batches.data.1.id', $olderBatch->id));
});

it('uploads maps and processes valid and invalid CSV rows', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('leads.csv', "Company Name,Email,Website\nAcme,hello@acme.test,acme.test\n,invalid-email,invalid\n");
    $upload = $this->actingAs($agent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::firstOrFail();
    $upload->assertRedirect(route('uploads.mapping', $batch));
    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => ['Company Name' => 'company_name', 'Email' => 'email', 'Website' => 'website']])->assertRedirect(route('uploads.show', $batch));
    $batch->refresh();
    expect($batch->total_rows)->toBe(2)->and($batch->accepted_rows)->toBe(1)->and($batch->rejected_rows)->toBe(1);
    $this->assertDatabaseHas('leads', ['agent_id' => $agent->id, 'company_name' => 'Acme', 'source' => 'csv']);
    $this->assertDatabaseHas('upload_rows', ['upload_batch_id' => $batch->id, 'row_number' => 3, 'processing_status' => 'rejected']);
});

it('uploads and processes multiple compatible raw files together', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $firstFile = UploadedFile::fake()->createWithContent('03-23-2026-Lead-1-Raw.csv', "Company,Email\nAcme One,one@acme.test\n");
    $secondFile = UploadedFile::fake()->createWithContent('03-24-2026-Lead-2-Raw.csv', "Company,Email\nAcme Two,two@acme.test\n");

    $response = $this->actingAs($agent)->post(route('uploads.store'), ['files' => [$firstFile, $secondFile]]);

    $response->assertRedirect(route('uploads.index'))->assertSessionHas('toast', [
        'type' => 'success',
        'message' => '2 raw files uploaded and queued for cleaning.',
    ]);
    $this->assertDatabaseCount('upload_batches', 2);
    $this->assertDatabaseHas('upload_batches', ['user_id' => $agent->id, 'original_filename' => '03-23-2026-Lead-1-Raw.csv', 'processing_status' => 'completed']);
    $this->assertDatabaseHas('upload_batches', ['user_id' => $agent->id, 'original_filename' => '03-24-2026-Lead-2-Raw.csv', 'processing_status' => 'completed']);
    $this->assertDatabaseHas('leads', ['agent_id' => $agent->id, 'company_name' => 'Acme One', 'email' => 'one@acme.test', 'lead_date' => '2026-03-23 00:00:00']);
    $this->assertDatabaseHas('leads', ['agent_id' => $agent->id, 'company_name' => 'Acme Two', 'email' => 'two@acme.test', 'lead_date' => '2026-03-24 00:00:00']);
    expect(Storage::disk('local')->allFiles('lead-imports'))->toHaveCount(2);
});

it('shows and enforces the administrator file count limit', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    SystemSetting::factory()->create(['key' => 'csv_max_files', 'value' => '2']);

    $this->actingAs($agent)->get(route('uploads.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('uploads/create')
            ->where('maximumFiles', 2));

    $files = collect(range(1, 3))
        ->map(fn (int $number): UploadedFile => UploadedFile::fake()->createWithContent("leads-{$number}.csv", "Company,Email\nAcme {$number},lead{$number}@acme.test\n"))
        ->all();

    $this->actingAs($agent)->post(route('uploads.store'), ['files' => $files])
        ->assertSessionHasErrors('files');

    $this->assertDatabaseCount('upload_batches', 0);
    expect(Storage::disk('local')->allFiles('lead-imports'))->toBeEmpty();
});

it('rejects all selected files when one cannot map a company column', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $validFile = UploadedFile::fake()->createWithContent('valid.csv', "Company,Email\nAcme,hello@acme.test\n");
    $invalidFile = UploadedFile::fake()->createWithContent('invalid.csv', "Email,Phone\nhello@example.com,123\n");

    $response = $this->actingAs($agent)->post(route('uploads.store'), ['files' => [$validFile, $invalidFile]]);

    $response->assertSessionHasErrors('files.1');
    $this->assertDatabaseCount('upload_batches', 0);
    expect(Storage::disk('local')->allFiles('lead-imports'))->toBeEmpty();
});

it('rejects an empty CSV file', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $response = $this->actingAs($agent)->post(route('uploads.store'), ['file' => UploadedFile::fake()->createWithContent('empty.csv', '')]);
    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('upload_batches', 0);
});

it('preserves the raw sample lead columns during import', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('raw-leads.csv', "Date,Company,Website,First Name,Email,Country,City,Import Trades,LinkedIn,Sources of Data,Link\n08/25/2026,Acme,acme.test,Ada,ada@acme.test,US,Austin,Machinery,https://linkedin.com/company/acme,Apollo,https://example.com/acme\n");
    $this->actingAs($agent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::firstOrFail();

    expect($batch->column_mapping['Date'])->toBe('lead_date');

    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => ['Date' => 'lead_date', 'Company' => 'company_name', 'Website' => 'website', 'First Name' => 'contact_person', 'Email' => 'email', 'Country' => 'country', 'City' => 'city', 'Import Trades' => 'import_trades', 'LinkedIn' => 'linkedin_url', 'Sources of Data' => 'data_source', 'Link' => 'source_url']])->assertRedirect(route('uploads.show', $batch));

    $this->assertDatabaseHas('leads', ['agent_id' => $agent->id, 'lead_date' => '2026-08-25 00:00:00', 'company_name' => 'Acme', 'contact_person' => 'Ada', 'country_code' => 'US', 'city' => 'Austin', 'import_trades' => 'Machinery', 'data_source' => 'Apollo', 'source_url' => 'https://example.com/acme']);
});

it('accepts a row and assigns the country timezone when the city value is a state', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    Country::factory()->create(['name' => 'United States', 'normalized_name' => 'united states', 'iso2' => 'US', 'default_timezone' => 'America/New_York']);
    $file = UploadedFile::fake()->createWithContent('us-leads.csv', "Company,Country,City\nCF Evans Construction,US,South Carolina\n");
    $this->actingAs($agent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::firstOrFail();

    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => ['Company' => 'company_name', 'Country' => 'country', 'City' => 'city']]);

    $this->assertDatabaseHas('leads', ['company_name' => 'CF Evans Construction', 'country_code' => 'US', 'city' => 'South Carolina', 'timezone' => 'America/New_York', 'validation_status' => 'validated']);
    $this->assertDatabaseHas('upload_rows', ['upload_batch_id' => $batch->id, 'row_number' => 2, 'processing_status' => 'accepted', 'error_category' => null, 'error_message' => null]);
});

it('prevents agents from viewing another agents upload', function () {
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->create();
    $this->actingAs($agent)->get(route('uploads.show', $batch))->assertForbidden();
});

it('rejects uploaded contacts beyond an agents company limit', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $rows = collect(range(1, 11))
        ->map(fn (int $number): string => "Acme,Contact {$number},contact{$number}@acme.test")
        ->implode("\n");
    $file = UploadedFile::fake()->createWithContent('company-limit.csv', "Company,Name,Email\n{$rows}\n");
    $this->actingAs($agent)->post(route('uploads.store'), ['file' => $file]);
    $batch = UploadBatch::firstOrFail();

    $this->actingAs($agent)->post(route('uploads.process', $batch), ['mapping' => ['Company' => 'company_name', 'Name' => 'contact_person', 'Email' => 'email']]);

    expect(Lead::query()->whereBelongsTo($agent, 'agent')->count())->toBe(10);
    $this->assertDatabaseHas('upload_rows', ['upload_batch_id' => $batch->id, 'row_number' => 12, 'processing_status' => 'rejected', 'error_category' => 'company_contact_limit', 'error_message' => 'An agent can have a maximum of 10 contacts for the same company.']);
});

it('re-analyzes duplicate rows from the stored upload without uploading again', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    Storage::disk('local')->put('lead-imports/reanalyze.csv', "Company,Website,Name,Email\nAcme,https://acme.test,Jane Smith,jane@acme.test\nAcme,https://acme.test,John Jones,john@acme.test\n");
    $batch = UploadBatch::factory()->for($agent)->create([
        'stored_filename' => 'lead-imports/reanalyze.csv',
        'headers' => ['Company', 'Website', 'Name', 'Email'],
        'column_mapping' => ['Company' => 'company_name', 'Website' => 'website', 'Name' => 'contact_person', 'Email' => 'email'],
        'processing_status' => 'completed',
        'total_rows' => 2,
        'accepted_rows' => 1,
        'duplicate_rows' => 1,
        'exact_duplicate_rows' => 1,
    ]);
    $acceptedLead = Lead::factory()->for($agent, 'agent')->for($batch, 'uploadBatch')->create(['company_name' => 'Acme', 'contact_person' => 'Jane Smith', 'email' => 'jane@acme.test']);
    UploadRow::factory()->for($batch)->for($acceptedLead)->create(['row_number' => 2, 'processing_status' => 'accepted']);
    UploadRow::factory()->for($batch)->for($acceptedLead)->create(['row_number' => 3, 'processing_status' => 'duplicate', 'error_category' => 'exact_duplicate', 'error_message' => 'Previously matched by company website.']);

    $response = $this->actingAs($agent)->post(route('uploads.reanalyze', $batch));

    $response->assertRedirect()->assertSessionHas('toast');
    expect($batch->refresh()->processing_status->value)->toBe('completed')
        ->and($batch->accepted_rows)->toBe(2)
        ->and($batch->exact_duplicate_rows)->toBe(0)
        ->and($acceptedLead->refresh()->trashed())->toBeFalse();
    $this->assertDatabaseHas('leads', ['upload_batch_id' => $batch->id, 'contact_person' => 'John Jones', 'email' => 'john@acme.test']);
    $this->assertDatabaseHas('upload_rows', ['upload_batch_id' => $batch->id, 'row_number' => 3, 'processing_status' => 'needs_review', 'error_category' => 'location']);
});

it('prevents an agent from re-analyzing another agents upload', function () {
    $owner = User::factory()->create();
    $otherAgent = User::factory()->create();
    $batch = UploadBatch::factory()->for($owner)->create(['processing_status' => 'completed']);

    $this->actingAs($otherAgent)->post(route('uploads.reanalyze', $batch))->assertForbidden();
});
