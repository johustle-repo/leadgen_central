<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * US states and Canadian provinces/territories, and parsing of the free-text
 * "City, ST" / "State" / "City" values agents enter in a lead's City field.
 */
class NorthAmericanStates
{
    /** @var array<string, array<string, string>> country => [code => name] */
    public const STATES = [
        'US' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
            'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
            'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
            'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
            'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
            'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
            'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
            'WY' => 'Wyoming', 'PR' => 'Puerto Rico',
        ],
        'CA' => [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
            'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut',
            'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Québec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
        ],
    ];

    /** Common alternative spellings, folded (lowercase ASCII) => code. */
    private const ALIASES = [
        'US' => ['washington dc' => 'DC', 'washington d c' => 'DC', 'd c' => 'DC'],
        'CA' => ['newfoundland' => 'NL', 'labrador' => 'NL', 'pei' => 'PE', 'yukon territory' => 'YT'],
    ];

    /**
     * Full state/province name for a code, name or near-miss spelling within
     * the country (e.g. "TX", "texas", "Lowa" for Iowa), or null.
     */
    public static function resolve(string $value, string $country): ?string
    {
        $states = self::STATES[$country] ?? null;
        $folded = self::fold($value);
        if ($states === null || $folded === '') {
            return null;
        }

        $code = strtoupper($folded);
        if (isset($states[$code])) {
            return $states[$code];
        }
        if (isset(self::ALIASES[$country][$folded])) {
            return $states[self::ALIASES[$country][$folded]] ?? null;
        }

        foreach ($states as $name) {
            if (self::fold($name) === $folded) {
                return $name;
            }
        }

        // One slipped letter in a name ("Lowa", "Tennesee"). Only reached where
        // a state is expected: parse() never applies it to a bare city.
        if (mb_strlen($folded) >= 4) {
            foreach ($states as $name) {
                if (levenshtein(self::fold($name), $folded) === 1) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Splits a City field value into its city and state parts:
     * "Houston, TX" => [Houston, Texas]; "South Dakota" => [null, South Dakota];
     * "Vernal" => [Vernal, null].
     *
     * @return array{city: string|null, state: string|null}
     */
    public static function parse(string $value, string $country): array
    {
        $value = trim($value, " \t\n\r\0\x0B,.;");
        if ($value === '') {
            return ['city' => null, 'state' => null];
        }

        // A bare word is a state only on an exact code/name match; the
        // near-miss spelling rule is kept for the part after a comma, where a
        // state is expected, so a city like "Vernal" is never read as a state.
        if (self::isExactName($value, $country)) {
            return ['city' => null, 'state' => self::resolve($value, $country)];
        }

        if (str_contains($value, ',')) {
            $city = trim(Str::beforeLast($value, ','));
            $state = self::resolve(Str::afterLast($value, ','), $country);
            if ($state !== null) {
                return ['city' => $city !== '' ? $city : null, 'state' => $state];
            }
        } elseif (preg_match('/^(.+?)\s+([A-Za-z]{2})$/', $value, $matches) === 1 && ($state = self::resolve($matches[2], $country)) !== null) {
            // "Dallas TX" without the comma.
            return ['city' => $matches[1], 'state' => $state];
        }

        return ['city' => $value, 'state' => null];
    }

    /** Lowercase ASCII with punctuation collapsed: "St. Louis" => "st louis". */
    public static function fold(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private static function isExactName(string $value, string $country): bool
    {
        $folded = self::fold($value);

        return isset(self::STATES[$country][strtoupper($folded)]) || isset(self::ALIASES[$country][$folded])
            || collect(self::STATES[$country])->contains(fn (string $name): bool => self::fold($name) === $folded);
    }
}
