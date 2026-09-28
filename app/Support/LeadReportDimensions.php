<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

/**
 * How the lead reports group records (canonical source, resolved country,
 * company, industry, raw data source), shared by the report queries and the
 * lead list so a clicked report bar lists exactly the leads it counted.
 */
class LeadReportDimensions
{
    /** Canonical data source groups, in report order. */
    public const SOURCE_GROUPS = ['Tendata', 'Lusha', 'Manual', 'Email', 'Other', 'Unknown'];

    /** Drill-down query keys the lead list accepts from a report, beyond its own status and agent filters. */
    public const DRILLDOWN_KEYS = ['created_from', 'created_to', 'source_group', 'data_source', 'country_group', 'company', 'industry', 'region', 'city', 'agent'];

    public const COMPANY = "COALESCE(NULLIF(LOWER(TRIM(leads.normalized_company_name)), ''), NULLIF(LOWER(TRIM(leads.company_name)), ''))";

    /** Stored country code, otherwise the lowercased country name. */
    public const COUNTRY = "COALESCE(NULLIF(UPPER(TRIM(leads.country_code)), ''), NULLIF(LOWER(TRIM(leads.country)), ''))";

    /**
     * Some imported sources spell a country name differently than the
     * canonical name on file (e.g. "United States" vs. our "United States
     * of America", or "Republic of Ireland" vs. our "Ireland"). Map the
     * common variants to their ISO2 code so they group with that country.
     *
     * @var array<string, string>
     */
    public const COUNTRY_NAME_ALIASES = [
        'united states' => 'US',
        'usa' => 'US',
        'u.s.a.' => 'US',
        'u.s.' => 'US',
        'america' => 'US',
        'uk' => 'GB',
        'u.k.' => 'GB',
        'great britain' => 'GB',
        'england' => 'GB',
        'republic of ireland' => 'IE',
        'south korea' => 'KR',
        'north korea' => 'KP',
        'vietnam' => 'VN',
        'ivory coast' => 'CI',
        'czech republic' => 'CZ',
        'russia' => 'RU',
    ];

    /**
     * SQL that folds a free-text source column into one of SOURCE_GROUPS.
     *
     * @param  literal-string  $column
     * @param  literal-string|null  $entryMethod
     * @return literal-string
     */
    public static function sourceExpression(string $column, ?string $entryMethod = null): string
    {
        $manual = $entryMethod === null ? '' : "WHEN NULLIF(TRIM({$column}), '') IS NULL AND {$entryMethod} = 'manual' THEN 'Manual'";

        return "CASE WHEN LOWER(TRIM({$column})) = 'tendata' THEN 'Tendata'
            WHEN LOWER(TRIM({$column})) = 'lusha' THEN 'Lusha'
            WHEN LOWER(TRIM({$column})) IN ('manual', 'manual entry') THEN 'Manual'
            WHEN LOWER(TRIM({$column})) IN ('email', 'email reply', 'email outreach') THEN 'Email'
            {$manual} WHEN NULLIF(TRIM({$column}), '') IS NULL OR LOWER(TRIM({$column})) IN ('unknown', 'n/a') THEN 'Unknown' ELSE 'Other' END";
    }

    /**
     * Resolves values of the COUNTRY expression to an ISO2 code where one is
     * known (alias, reference name or countries table), otherwise keeps the
     * lowercased name; empty values become 'Unknown'.
     *
     * @param  list<string|null>  $values
     * @return array<string, string> raw value => resolved country
     */
    public static function resolveCountries(array $values): array
    {
        $resolved = [];
        $unmatched = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                $resolved[''] = 'Unknown';

                continue;
            }
            $name = mb_strtolower($value);
            $code = preg_match('/^[A-Z]{2}$/', $value) === 1 ? $value : (self::COUNTRY_NAME_ALIASES[$name] ?? CountryRegions::codeForName($name));
            if ($code === null) {
                $unmatched[] = $name;
            }
            $resolved[$value] = $code ?? $name;
        }

        // Always one lookup, even when nothing is unmatched, so report query
        // counts stay the same whatever the data holds.
        $known = Country::query()->whereIn('normalized_name', array_values(array_unique($unmatched)))->pluck('iso2', 'normalized_name');
        foreach ($resolved as $value => $country) {
            if ($known->has($country)) {
                $resolved[$value] = strtoupper((string) $known->get($country));
            }
        }

        return $resolved;
    }

    /**
     * Narrows a lead query to one report category. Values are the labels the
     * reports produce, so 'Unknown' selects leads with the field left empty.
     *
     * @param  Builder<Lead>  $leads
     * @param  array<string, string|null>  $criteria
     */
    public static function applyDrilldown(Builder $leads, array $criteria): void
    {
        $criteria = array_filter($criteria, fn (?string $value): bool => $value !== null && $value !== '');

        if (isset($criteria['created_from'])) {
            $leads->where('leads.created_at', '>=', $criteria['created_from'].' 00:00:00');
        }
        if (isset($criteria['created_to'])) {
            $leads->where('leads.created_at', '<=', $criteria['created_to'].' 23:59:59');
        }
        if (isset($criteria['source_group'])) {
            $leads->whereRaw(self::sourceExpression('leads.data_source', 'leads.source').' = ?', [$criteria['source_group']]);
        }
        if (isset($criteria['data_source'])) {
            self::whereText($leads, 'leads.data_source', $criteria['data_source']);
        }
        if (isset($criteria['industry'])) {
            self::whereText($leads, 'leads.industry', $criteria['industry']);
        }
        if (isset($criteria['company'])) {
            $leads->whereRaw(self::COMPANY.' = ?', [mb_strtolower(trim($criteria['company']))]);
        }
        if (isset($criteria['country_group'])) {
            self::whereCountry($leads, $criteria['country_group']);
        }
        if (isset($criteria['region'])) {
            self::whereRegion($leads, $criteria['region']);
        }
        if (isset($criteria['city'])) {
            self::whereText($leads, 'leads.city', $criteria['city']);
        }
        if (isset($criteria['agent'])) {
            $criteria['agent'] === 'none' ? $leads->whereNull('leads.agent_id') : $leads->where('leads.agent_id', $criteria['agent']);
        }
    }

    /**
     * @param  Builder<Lead>  $leads
     * @param  literal-string  $column
     */
    private static function whereText(Builder $leads, string $column, string $value): void
    {
        if ($value === 'Unknown') {
            $leads->whereRaw("NULLIF(TRIM({$column}), '') IS NULL");

            return;
        }

        $leads->whereRaw("LOWER(TRIM({$column})) = ?", [mb_strtolower(trim($value))]);
    }

    /**
     * Matches every stored spelling that resolveCountries() folds into $country.
     *
     * @param  Builder<Lead>  $leads
     */
    private static function whereCountry(Builder $leads, string $country): void
    {
        $noCode = "NULLIF(TRIM(leads.country_code), '') IS NULL";

        if ($country === 'Unknown') {
            $leads->whereRaw($noCode)->whereRaw("NULLIF(TRIM(leads.country), '') IS NULL");

            return;
        }

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            $name = mb_strtolower(trim($country));
            $leads->where(fn (Builder $query) => $query->whereRaw('LOWER(TRIM(leads.country_code)) = ?', [$name])
                ->orWhere(fn (Builder $byName) => $byName->whereRaw($noCode)->whereRaw('LOWER(TRIM(leads.country)) = ?', [$name])));

            return;
        }

        [$sql, $bindings] = self::countriesCondition([$country]);
        $leads->whereRaw($sql, $bindings);
    }

    /**
     * Matches the countries of one reference region; Unassigned is every lead
     * whose country is outside the reference, including leads with none.
     *
     * @param  Builder<Lead>  $leads
     */
    private static function whereRegion(Builder $leads, string $region): void
    {
        $inRegion = fn (array $country): bool => $region === CountryRegions::UNASSIGNED || $country['region'] === $region;
        [$sql, $bindings] = self::countriesCondition(array_keys(array_filter(CountryRegions::COUNTRIES, $inRegion)));

        $leads->whereRaw($region === CountryRegions::UNASSIGNED ? "NOT {$sql}" : $sql, $bindings);
    }

    /**
     * Null-safe SQL matching leads stored under any of the given ISO2 codes,
     * by code or by any known name (alias, reference or countries table).
     *
     * @param  list<string>  $codes
     * @return array{0: literal-string, 1: list<string>}
     */
    private static function countriesCondition(array $codes): array
    {
        if ($codes === []) {
            return ['1 = 0', []];
        }

        $names = Country::query()->whereIn('iso2', $codes)->pluck('normalized_name')->map(fn (string $name): string => mb_strtolower($name))->all();
        foreach ($codes as $code) {
            array_push($names, ...array_keys(self::COUNTRY_NAME_ALIASES, $code, true));
            if (isset(CountryRegions::COUNTRIES[$code])) {
                $names[] = mb_strtolower(CountryRegions::COUNTRIES[$code]['name']);
            }
        }
        $names = array_values(array_unique($names));

        $sql = "COALESCE(UPPER(TRIM(leads.country_code)), '') IN (".self::placeholders($codes).')';
        if ($names !== []) {
            $sql .= " OR COALESCE(LOWER(TRIM(leads.country_code)), '') IN (".self::placeholders($names).')'
                ." OR (COALESCE(TRIM(leads.country_code), '') = '' AND COALESCE(LOWER(TRIM(leads.country)), '') IN (".self::placeholders($names).'))';
        }

        return ["({$sql})", [...$codes, ...$names, ...$names]];
    }

    /**
     * @param  list<string>  $values
     * @return literal-string
     */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
