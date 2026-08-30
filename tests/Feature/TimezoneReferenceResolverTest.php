<?php

use App\Models\TimezoneReference;
use App\Services\TimezoneReferenceResolver;

it('resolves a cleaning reference case insensitively by country code', function () {
    $reference = TimezoneReference::factory()->create([
        'country' => 'Algeria',
        'original_country_code' => 'DZ',
        'reference_country_code' => 'FR',
        'reference_capital' => 'Paris',
    ]);

    $resolved = app(TimezoneReferenceResolver::class)->resolveByCountryCode(' dz ');

    expect($resolved?->is($reference))->toBeTrue();
});

it('returns null when the country code has no cleaning reference', function () {
    $resolved = app(TimezoneReferenceResolver::class)->resolveByCountryCode('ZZ');

    expect($resolved)->toBeNull();
});
