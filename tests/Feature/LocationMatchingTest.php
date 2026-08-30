<?php

use App\Models\City;
use App\Models\Country;
use App\Models\LocationAlias;
use App\Services\LocationMatchingService;

it('matches exact and aliased locations and assigns an IANA timezone', function () {
    $country = Country::factory()->create(['name' => 'United States of America', 'normalized_name' => 'united states of america', 'iso2' => 'US', 'default_timezone' => 'America/New_York']);
    $city = City::factory()->for($country)->create(['name' => 'New York City', 'normalized_name' => 'new york city', 'timezone' => 'America/New_York']);
    LocationAlias::factory()->create(['alias' => 'USA', 'normalized_alias' => 'usa', 'country_id' => $country->id]);
    LocationAlias::factory()->create(['alias' => 'NYC', 'normalized_alias' => 'nyc', 'country_id' => $country->id, 'city_id' => $city->id]);

    $exact = app(LocationMatchingService::class)->match('US', 'New York City');
    $alias = app(LocationMatchingService::class)->match('USA', 'NYC');

    expect($exact->matchType)->toBe('exact')->and($exact->city?->timezone)->toBe('America/New_York')
        ->and($alias->matchType)->toBe('alias')->and($alias->country?->id)->toBe($country->id)->and($alias->city?->id)->toBe($city->id);
});

it('uses the country default timezone when the submitted location is not a canonical city', function () {
    Country::factory()->create(['name' => 'United States', 'normalized_name' => 'united states', 'iso2' => 'US', 'default_timezone' => 'America/New_York']);

    $result = app(LocationMatchingService::class)->match('US', 'South Carolina');
    $attributes = $result->leadAttributes('South Carolina', 'US');

    expect($result->matchType)->toBe('country')
        ->and($result->country?->iso2)->toBe('US')
        ->and($attributes['city'])->toBe('South Carolina')
        ->and($attributes['timezone'])->toBe('America/New_York')
        ->and($attributes['validation_status'])->toBe('validated');
});
