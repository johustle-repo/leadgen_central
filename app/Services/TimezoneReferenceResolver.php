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

    /**
     * Resolve many country codes at once without issuing a query per code.
     *
     * @param  iterable<string|null>  $countryCodes
     * @return array<string, TimezoneReference|null> keyed by the normalized (uppercased, trimmed) country code
     */
    public function resolveManyByCountryCode(iterable $countryCodes): array
    {
        $normalized = collect($countryCodes)
            ->filter(fn (?string $code) => filled($code))
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->unique()
            ->values();

        $found = TimezoneReference::query()
            ->whereIn('original_country_code', $normalized->all())
            ->get()
            ->keyBy('original_country_code');

        return $normalized->mapWithKeys(function (string $code) use ($found): array {
            if ($found->has($code)) {
                return [$code => $found->get($code)];
            }

            $fallback = TimezoneCleaningReferences::find($code);

            return [$code => $fallback === null ? null : new TimezoneReference([
                ...$fallback,
                'original_country_code' => $code,
            ])];
        })->all();
    }
}
