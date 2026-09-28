<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadStateResolver;

it('reads the state from how agents write the City field', function (string $country, string $city, ?string $state) {
    expect(app(LeadStateResolver::class)->stateFor($country, $city, null))->toBe($state);
})->with([
    'city, state code' => ['US', 'Houston, TX', 'Texas'],
    'city, lowercase code' => ['US', 'Portland, me', 'Maine'],
    'city, full state' => ['US', 'Wichita, Kansas', 'Kansas'],
    'state only' => ['US', 'South Dakota', 'South Dakota'],
    'state code only' => ['US', 'TX', 'Texas'],
    'no comma' => ['US', 'Dallas TX', 'Texas'],
    'misspelt state' => ['US', 'Davenport, Lowa', 'Iowa'],
    'dotted city' => ['US', 'St. Louis, MO', 'Missouri'],
    'CA means California in the US' => ['US', 'Concord, CA', 'California'],
    'canadian province code' => ['CA', 'Toronto, ON', 'Ontario'],
    'canadian province without accent' => ['CA', 'Quebec', 'Québec'],
    'city only, never seen with a state' => ['US', 'Springfield', null],
    'trailing comma' => ['US', 'Springfield,', null],
    'unknown' => ['US', 'Unknown', null],
]);

it('saves a US lead\'s City as its full state and keeps what was entered', function () {
    $lead = Lead::factory()->create(['country_code' => 'US', 'country' => 'United States', 'city' => 'Houston, TX', 'raw_city' => null]);

    expect($lead->refresh()->only(['city', 'state_province', 'raw_city']))
        ->toBe(['city' => 'Texas', 'state_province' => 'Texas', 'raw_city' => 'Houston, TX']);
});

it('places a city entered without its state from the overrides, then from other leads', function () {
    config(['leadgen.city_states.US' => ['Louisville' => 'Colorado']]);
    Lead::factory()->create(['country_code' => 'US', 'city' => 'Aurora, CO']);

    $louisville = Lead::factory()->create(['country_code' => 'US', 'city' => 'Louisville']);
    $aurora = Lead::factory()->create(['country_code' => 'US', 'city' => 'Aurora']);

    expect($louisville->refresh()->city)->toBe('Colorado')
        ->and($aurora->refresh()->city)->toBe('Colorado');
});

it('uses the lead\'s own State/Province when the City has none', function () {
    $lead = Lead::factory()->create(['country_code' => 'US', 'city' => 'Springfield', 'state_province' => 'IL']);

    expect($lead->refresh()->only(['city', 'state_province', 'raw_city']))
        ->toBe(['city' => 'Illinois', 'state_province' => 'Illinois', 'raw_city' => 'Springfield']);
});

it('leaves leads outside the US and Canada, and unplaced cities, as entered', function () {
    $london = Lead::factory()->create(['country_code' => 'GB', 'city' => 'London, UK']);
    $springfield = Lead::factory()->create(['country_code' => 'US', 'city' => 'Springfield']);

    expect($london->refresh()->city)->toBe('London, UK')
        ->and($springfield->refresh()->city)->toBe('Springfield');
});

it('rewrites the City when an agent edits it on the lead form', function () {
    $agent = User::factory()->create();
    $lead = Lead::factory()->for($agent, 'agent')->create(['country_code' => 'US', 'city' => 'Texas']);

    $this->actingAs($agent)->put(route('leads.update', $lead), ['company_name' => $lead->company_name, 'country_code' => 'US', 'city' => 'Farmingdale, NY'])
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->only(['city', 'raw_city']))->toBe(['city' => 'New York', 'raw_city' => 'Farmingdale, NY']);
});

it('backfills existing leads and lists the cities it could not place', function () {
    $houston = Lead::factory()->create(['country_code' => 'US', 'city' => 'Texas']);
    $springfield = Lead::factory()->create(['country_code' => 'US', 'city' => 'Texas']);
    Lead::query()->whereKey($houston->id)->update(['city' => 'Houston, TX', 'state_province' => null, 'raw_city' => null]);
    Lead::query()->whereKey($springfield->id)->update(['city' => 'Springfield', 'state_province' => null]);

    $this->artisan('leads:normalize-states', ['--dry-run' => true])
        ->expectsOutputToContain('Would update 1 US/Canadian leads.')
        ->expectsOutputToContain('Springfield')
        ->assertSuccessful();
    expect($houston->refresh()->city)->toBe('Houston, TX');

    $this->artisan('leads:normalize-states')->expectsOutputToContain('Updated 1 US/Canadian leads.')->assertSuccessful();

    expect($houston->refresh()->only(['city', 'state_province', 'raw_city']))->toBe(['city' => 'Texas', 'state_province' => 'Texas', 'raw_city' => 'Houston, TX'])
        ->and($springfield->refresh()->city)->toBe('Springfield');
});
