<?php

namespace App\Services;

use App\Models\TimezoneReference;

class TimezoneReferenceResolver
{
    public function resolveByCountryCode(?string $countryCode): ?TimezoneReference
    {
        if (blank($countryCode)) {
            return null;
        }

        return TimezoneReference::query()
            ->where('original_country_code', strtoupper(trim($countryCode)))
            ->first();
    }
}
