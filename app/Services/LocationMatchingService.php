<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\LocationAlias;

class LocationMatchingService
{
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
