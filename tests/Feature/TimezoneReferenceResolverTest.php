<?php

use App\Models\TimezoneReference;
use App\Services\TimezoneReferenceResolver;

it('resolves a cleaning reference case insensitively by country code', function () {
    $reference = TimezoneReference::query()->updateOrCreate(['original_country_code' => 'DZ'], [
        'country' => 'Algeria',
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

it('uses the bundled reference when production data migrations have not run', function () {
    TimezoneReference::query()->delete();

    $resolved = app(TimezoneReferenceResolver::class)->resolveByCountryCode('US');

    expect($resolved?->original_country_code)->toBe('US')
        ->and($resolved?->reference_country_code)->toBe('US')
        ->and($resolved?->reference_capital)->toBe('New York');
});
