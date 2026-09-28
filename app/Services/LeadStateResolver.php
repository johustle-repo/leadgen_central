<?php

namespace App\Services;

use App\Models\Lead;
use App\Support\CountryRegions;
use App\Support\LeadReportDimensions;
use App\Support\NorthAmericanStates;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps US and Canadian leads' City field uniform: it holds the full state or
 * province name ("Houston, TX" becomes "Texas"), with the text as entered
 * kept in raw_city.
 */
class LeadStateResolver
{
    /** Countries whose leads are grouped by state/province. */
    public const COUNTRIES = ['US', 'CA'];

    /** @var array<string, array<string, string>> */
    private array $learned = [];

    /**
     * Rewrites the lead's City (and State/Province) to its full state name
     * when the state can be determined; otherwise leaves the lead untouched.
     *
     * @return bool Whether the lead's state was determined.
     */
    public function normalize(Lead $lead): bool
    {
        $country = self::countryOf($lead->country_code, $lead->country);
        $state = $country === null ? null : $this->stateFor($country, $lead->city, $lead->state_province);
        if ($state === null) {
            return false;
        }

        if ($lead->city !== $state) {
            if ($lead->isDirty('city') || blank($lead->raw_city)) {
                $lead->raw_city = $lead->city;
            }
            $lead->city = $state;
        }
        $lead->state_province = $state;

        return true;
    }

    /**
     * The state/province a City value belongs in: one written in the value
     * itself, then the lead's own State/Province, then a configured override,
     * then how other leads wrote the same city with its state.
     */
    public function stateFor(string $country, ?string $city, ?string $stateProvince): ?string
    {
        $parsed = NorthAmericanStates::parse(self::isPlaceholder($city) ? '' : (string) $city, $country);
        if ($parsed['state'] !== null) {
            return $parsed['state'];
        }

        $ownState = self::isPlaceholder($stateProvince) ? null : NorthAmericanStates::resolve((string) $stateProvince, $country);
        if ($ownState !== null) {
            return $ownState;
        }

        if ($parsed['city'] === null) {
            return null;
        }
        $key = NorthAmericanStates::fold($parsed['city']);

        return $this->overrides($country)[$key] ?? $this->learnedStates($country)[$key] ?? null;
    }

    /**
     * US or CA for a lead's stored country code or name, otherwise null.
     */
    public static function countryOf(?string $code, ?string $name): ?string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            $name = trim((string) $name);
            $code = preg_match('/^[A-Za-z]{2}$/', $name) === 1
                ? strtoupper($name)
                : (LeadReportDimensions::COUNTRY_NAME_ALIASES[mb_strtolower($name)] ?? CountryRegions::codeForName($name) ?? '');
        }

        return in_array($code, self::COUNTRIES, true) ? $code : null;
    }

    /**
     * City => state learned from leads that wrote the city together with its
     * state ("Louisville, CO"), kept only where one state accounts for most
     * of those mentions. Cached briefly: it changes slowly and is read on
     * every save of a city-only lead.
     *
     * @return array<string, string>
     */
    public function learnedStates(string $country): array
    {
        return $this->learned[$country] ??= Cache::remember("lead-city-states:{$country}", now()->addMinutes(10), function () use ($country): array {
            $counts = [];
            $rows = Lead::query()
                ->where(fn ($query) => $query->where('raw_city', 'like', '%,%')->orWhere('city', 'like', '%,%'))
                ->toBase()
                ->select(['raw_city', 'city', 'country_code', 'country'])
                ->cursor();
            foreach ($rows as $row) {
                if (self::countryOf($row->country_code, $row->country) !== $country) {
                    continue;
                }
                foreach ([$row->raw_city, $row->city] as $value) {
                    $parsed = NorthAmericanStates::parse((string) $value, $country);
                    if ($parsed['city'] !== null && $parsed['state'] !== null) {
                        $key = NorthAmericanStates::fold($parsed['city']);
                        $counts[$key][$parsed['state']] = ($counts[$key][$parsed['state']] ?? 0) + 1;
                    }
                }
            }

            $learned = [];
            foreach ($counts as $city => $states) {
                arsort($states);
                if (reset($states) > array_sum($states) / 2) {
                    $learned[$city] = (string) key($states);
                }
            }

            return $learned;
        });
    }

    /** @return array<string, string> */
    private function overrides(string $country): array
    {
        $overrides = [];
        foreach ((array) config("leadgen.city_states.{$country}", []) as $city => $state) {
            $resolved = NorthAmericanStates::resolve((string) $state, $country);
            if ($resolved !== null) {
                $overrides[NorthAmericanStates::fold((string) $city)] = $resolved;
            }
        }

        return $overrides;
    }

    private static function isPlaceholder(?string $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['', 'n/a', 'na', 'none', 'null', '-', 'unknown'], true);
    }
}
