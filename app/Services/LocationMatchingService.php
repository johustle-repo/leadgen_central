<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\LocationAlias;
use App\Support\TimezoneCleaningReferences;

class LocationMatchingService
{
    /**
     * TimezoneCleaningReferences buckets every country into one of these
     * five reference countries; their timezones, used below as the last
     * resort when the countries table itself has no matching row.
     *
     * @var array<string, string>
     */
    private const ANCHOR_TIMEZONES = [
        'US' => 'America/New_York',
        'FR' => 'Europe/Paris',
        'PH' => 'Asia/Manila',
        'IN' => 'Asia/Kolkata',
        'AE' => 'Asia/Dubai',
    ];

    public function __construct(private LeadNormalizationService $normalizer) {}

    public function match(?string $countryValue, ?string $cityValue): LocationMatchResult
    {
        $countryNormalized = $this->normalizer->normalizeLocation($countryValue);
        $country = Country::query()->where('normalized_name', $countryNormalized)
            ->orWhere('iso2', strtoupper(trim((string) $countryValue)))->first();
        $countryMatch = $country ? 'exact' : null;

        if (! $country && $countryNormalized !== '') {
            $alias = LocationAlias::query()->with('country')->where('normalized_alias', $countryNormalized)->whereNotNull('country_id')->first();
            $country = $alias?->country;
            $countryMatch = $country ? 'alias' : null;
        }

        // The countries table is only ever populated by a manual, one-off
        // `locations:import` run against an external dataset file that
        // isn't part of the repo - easy to skip on a fresh environment,
        // which otherwise silently breaks country_code/timezone auto-set
        // even for a country the caller supplied an exact ISO2 code for
        // (e.g. the Add Possible Lead / Lead Review country dropdown,
        // which only ever submits a known-valid code). Fall back to the
        // same static reference data Geographic analysis already relies
        // on for exactly this situation, keyed by that code, rather than
        // giving up.
        if (! $country) {
            $code = strtoupper(trim((string) $countryValue));
            $reference = TimezoneCleaningReferences::find($code);
            if ($reference !== null) {
                $country = new Country([
                    'iso2' => $code,
                    'name' => $reference['country'],
                    'default_timezone' => self::ANCHOR_TIMEZONES[$reference['reference_country_code']] ?? null,
                ]);
                $countryMatch = 'reference';
            }
        }
        if (! $country) {
            return new LocationMatchResult(null, null, 'not_found');
        }

        $cityNormalized = $this->normalizer->normalizeLocation($cityValue);
        if ($cityNormalized === '') {
            return new LocationMatchResult($country, null, 'country');
        }
        $city = City::query()->whereBelongsTo($country)->where('normalized_name', $cityNormalized)->first();
        if ($city) {
            return new LocationMatchResult($country, $city, $countryMatch === 'alias' ? 'alias' : 'exact');
        }
        $alias = LocationAlias::query()->with('city')->where('normalized_alias', $cityNormalized)->where('country_id', $country->id)->whereNotNull('city_id')->first();

        return new LocationMatchResult($country, $alias?->city, $alias?->city ? 'alias' : 'country');
    }
}
