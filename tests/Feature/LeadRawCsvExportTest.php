<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Lead;
use App\Models\TimezoneReference;
use App\Models\User;

it('downloads only the agents leads created within the selected date range as a raw CSV', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create([
        'lead_date' => '2026-08-25',
        'company_name' => 'Included Company',
        'contact_person' => 'Ada',
        'email' => 'ada@included.test',
        'country_code' => 'US',
        'created_by' => $agent->id,
        'created_at' => '2026-08-25 10:00:00',
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Outside Date Company',
        'created_by' => $agent->id,
        'created_at' => '2026-08-24 10:00:00',
    ]);
    Lead::factory()->for($otherAgent, 'agent')->create([
        'company_name' => 'Another Agents Company',
        'created_by' => $otherAgent->id,
        'created_at' => '2026-08-25 11:00:00',
    ]);

    $response = $this->actingAs($agent)->get(route('leads.download-raw', [
        'date_from' => '2026-08-25',
        'date_to' => '2026-08-25',
    ]));

    $response->assertOk()->assertDownload('08-25-2026-Leads-Raw.csv');
    expect($response->streamedContent())
        ->toContain('Date,Company,Website,"First Name",Email,Country,City,"Import Trades",LinkedIn,"Sources of Data",Link')
        ->toContain('08/25/2026,"Included Company"')
        ->toContain('ada@included.test')
        ->not->toContain('Outside Date Company')
        ->not->toContain('Another Agents Company');
});

it('rejects a raw CSV download when the end date precedes the start date', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('leads.download-raw', [
        'date_from' => '2026-08-26',
        'date_to' => '2026-08-25',
    ]));

    $response->assertSessionHasErrors(['date_to']);
});

it('downloads the agents cleaned leads including records that need location review', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $country = Country::factory()->create(['name' => 'United States', 'normalized_name' => 'united states', 'iso2' => 'US', 'default_timezone' => 'America/New_York']);
    City::factory()->for($country)->create(['name' => 'Austin', 'normalized_name' => 'austin', 'timezone' => 'America/Chicago']);
    TimezoneReference::factory()->create([
        'country' => 'Canada',
        'original_country_code' => 'CA',
        'reference_country_code' => 'US',
        'reference_capital' => 'Austin',
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'lead_date' => '2026-08-25',
        'company_name' => 'Clean Company',
        'email' => 'clean@example.test',
        'validation_status' => 'validated',
        'status' => 'validated',
        'country_code' => 'CA',
        'raw_country' => 'CA',
        'raw_city' => 'Ontario',
        'created_by' => $agent->id,
        'created_at' => '2026-08-25 10:00:00',
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Needs Review Company',
        'validation_status' => 'needs_review',
        'created_by' => $agent->id,
        'created_at' => '2026-08-25 11:00:00',
    ]);
    Lead::factory()->for($agent, 'agent')->create([
        'company_name' => 'Confirmed Duplicate Company',
        'status' => 'duplicate',
        'created_by' => $agent->id,
        'created_at' => '2026-08-25 11:30:00',
    ]);
    Lead::factory()->for($otherAgent, 'agent')->create([
        'company_name' => 'Other Agents Clean Company',
        'validation_status' => 'validated',
        'status' => 'validated',
        'created_by' => $otherAgent->id,
        'created_at' => '2026-08-25 12:00:00',
    ]);

    $response = $this->actingAs($agent)->get(route('leads.download-cleaned', [
        'date_from' => '2026-08-25',
        'date_to' => '2026-08-25',
    ]));

    $response->assertOk()->assertDownload('08-25-2026-Leads-Cleaned.csv');
    expect($response->streamedContent())
        ->toContain('LinkedIn,"Sources of Data",Link')
        ->not->toContain('Timezone')
        ->toContain('Clean Company')
        ->toContain('clean@example.test')
        ->toContain(',US,Austin,')
        ->not->toContain('Ontario')
        ->toContain('Needs Review Company')
        ->not->toContain('Confirmed Duplicate Company')
        ->not->toContain('Other Agents Clean Company');
});
