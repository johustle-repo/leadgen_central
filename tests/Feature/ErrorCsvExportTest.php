<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;

it('exports original problematic rows with their category and message', function () {
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create(['headers' => ['Company', 'Email']]);
    UploadRow::factory()->for($batch)->create(['row_number' => 2, 'raw_data' => ['Company' => '', 'Email' => 'invalid'], 'processing_status' => 'rejected', 'error_category' => 'validation', 'error_message' => 'The company name field is required.']);

    $response = $this->actingAs($agent)->get(route('uploads.errors', $batch));

    $response->assertOk()->assertDownload($batch->batch_code.'-problems.csv');
    expect($response->streamedContent())->toContain('"Row Number","Error Category","Error Message",Company,Email')->toContain('validation')->toContain('invalid');
});

it('exports only accepted leads in the cleaned file format', function () {
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create();
    $acceptedLead = Lead::factory()->for($agent, 'agent')->for($batch, 'uploadBatch')->create([
        'lead_date' => '2026-08-25',
        'company_name' => 'Clean Company',
        'email' => 'clean@example.com',
        'country_code' => 'US',
        'city' => 'New York',
        'data_source' => 'Tendata',
    ]);
    $rejectedLead = Lead::factory()->for($agent, 'agent')->for($batch, 'uploadBatch')->create(['company_name' => 'Rejected Company']);
    UploadRow::factory()->for($batch)->for($acceptedLead)->create(['row_number' => 2, 'processing_status' => 'accepted']);
    UploadRow::factory()->for($batch)->for($rejectedLead)->create(['row_number' => 3, 'processing_status' => 'rejected']);

    $response = $this->actingAs($agent)->get(route('uploads.cleaned', $batch));

    $response->assertOk()->assertDownload($batch->batch_code.'-cleaned.csv');
    expect($response->streamedContent())
        ->toContain('Date,Company,Website,"First Name",Email,Country,City,"Import Trades",LinkedIn,"Sources of Data",Link')
        ->toContain('08/25/2026,"Clean Company"')
        ->toContain('clean@example.com')
        ->not->toContain('Rejected Company');
});

it('prevents an agent from downloading another agents cleaned file', function () {
    $owner = User::factory()->create();
    $otherAgent = User::factory()->create();
    $batch = UploadBatch::factory()->for($owner)->create();

    $response = $this->actingAs($otherAgent)->get(route('uploads.cleaned', $batch));

    $response->assertForbidden();
});
