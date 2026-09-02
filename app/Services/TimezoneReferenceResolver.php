<?php

namespace App\Services;

use App\Models\TimezoneReference;
use App\Support\TimezoneCleaningReferences;

class TimezoneReferenceResolver
{
    public function resolveByCountryCode(?string $countryCode): ?TimezoneReference
    {
        if (blank($countryCode)) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));
        $reference = TimezoneReference::query()
            ->where('original_country_code', $countryCode)
            ->first();

        if ($reference !== null) {
            return $reference;
        }

        $fallback = TimezoneCleaningReferences::find($countryCode);

        return $fallback === null ? null : new TimezoneReference([
            ...$fallback,
            'original_country_code' => $countryCode,
        ]);
    }
}
