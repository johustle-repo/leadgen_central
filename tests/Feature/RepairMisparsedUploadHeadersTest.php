<?php

use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('repairs a batch whose header was misread as a single tab-separated column', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lead-imports/tab-leads.csv', "Company\tEmail\nAcme\thello@acme.test\n");
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create([
        'stored_filename' => 'lead-imports/tab-leads.csv',
        'headers' => ["Company\tEmail"],
        'column_mapping' => ["Company\tEmail" => null],
        'processing_status' => 'pending',
    ]);

    $this->artisan('uploads:repair-headers')->assertSuccessful();

    $batch->refresh();
    expect($batch->headers)->toBe(['Company', 'Email'])
        ->and($batch->column_mapping)->toBe(['Company' => 'company_name', 'Email' => 'email'])
        ->and($batch->processing_status->value)->toBe('completed')
        ->and($batch->accepted_rows)->toBe(1);
    $this->assertDatabaseHas('leads', ['company_name' => 'Acme', 'email' => 'hello@acme.test']);
});

it('leaves batches alone that already have a Company Name column mapped', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lead-imports/already-mapped.csv', "Company,Email\nAcme,hello@acme.test\n");
    $agent = User::factory()->create();
    $mappedBatch = UploadBatch::factory()->for($agent)->create([
        'stored_filename' => 'lead-imports/already-mapped.csv',
        'headers' => ['Company', 'Email'],
        'column_mapping' => ['Company' => 'company_name', 'Email' => 'email'],
        'processing_status' => 'pending',
    ]);

    $this->artisan('uploads:repair-headers')
        ->expectsOutput('No batches needed repair.')
        ->assertSuccessful();

    expect($mappedBatch->refresh()->processing_status->value)->toBe('pending');
});

it('skips a batch when even the correctly-delimited header has no Company Name column', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lead-imports/no-company.csv', "Email\tPhone\nhello@acme.test\t123\n");
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create([
        'stored_filename' => 'lead-imports/no-company.csv',
        'headers' => ["Email\tPhone"],
        'column_mapping' => ["Email\tPhone" => null],
        'processing_status' => 'pending',
    ]);

    $this->artisan('uploads:repair-headers')->assertSuccessful();

    expect($batch->refresh()->processing_status->value)->toBe('pending')
        ->and($batch->column_mapping)->toBe(["Email\tPhone" => null]);
});

it('repairs only the explicit batch IDs given instead of scanning for unmapped uploads', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lead-imports/tab-leads.csv', "Company\tEmail\nAcme\thello@acme.test\n");
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create([
        'stored_filename' => 'lead-imports/tab-leads.csv',
        'headers' => ["Company\tEmail"],
        'column_mapping' => ["Company\tEmail" => null],
        'processing_status' => 'pending',
    ]);

    $this->artisan('uploads:repair-headers', ['ids' => [$batch->id]])->assertSuccessful();

    expect($batch->refresh()->column_mapping)->toBe(['Company' => 'company_name', 'Email' => 'email']);
});

it('skips a batch whose stored file no longer exists instead of crashing', function () {
    Storage::fake('local');
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create([
        'stored_filename' => 'lead-imports/missing.csv',
        'headers' => ["Company\tEmail"],
        'column_mapping' => ["Company\tEmail" => null],
        'processing_status' => 'pending',
    ]);

    $this->artisan('uploads:repair-headers')->assertSuccessful();

    expect($batch->refresh()->processing_status->value)->toBe('pending');
});
