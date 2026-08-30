<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;

class LocationMatchResult
{
    public function __construct(
        public readonly ?Country $country,
        public readonly ?City $city,
        public readonly string $matchType,
    ) {}

    /** @return array<string, mixed> */
    public function leadAttributes(?string $rawCity, ?string $rawCountry): array
    {
        return [
            'raw_city' => $rawCity,
            'raw_country' => $rawCountry,
            'canonical_city_id' => $this->city?->id,
            'canonical_country_id' => $this->country?->id,
            'city' => $this->city === null ? $rawCity : $this->city->name,
            'country' => $this->country === null ? $rawCountry : $this->country->name,
            'country_code' => $this->country === null ? (preg_match('/^[A-Za-z]{2}$/', trim((string) $rawCountry)) === 1 ? strtoupper(trim((string) $rawCountry)) : null) : $this->country->iso2,
            'timezone' => $this->city === null ? $this->country?->default_timezone : $this->city->timezone,
            'location_match_type' => $this->matchType,
            'validation_status' => in_array($this->matchType, ['exact', 'alias', 'country'], true) ? 'validated' : 'needs_review',
        ];
    }
}
