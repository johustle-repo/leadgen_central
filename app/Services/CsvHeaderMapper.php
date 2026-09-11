<?php

namespace App\Services;

use Illuminate\Support\Str;

class CsvHeaderMapper
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'lead_date' => ['date', 'lead date', 'lead created date', 'created date', 'date created', 'date added', 'date received', 'contact date', 'date contacted', 'record date', 'entry date', 'date recorded', 'upload date'],
        'company_name' => ['company', 'company name', 'business name'],
        'website' => ['website', 'url', 'company website'],
        'address' => ['address', 'street address'], 'city' => ['city'],
        'state_province' => ['state', 'province', 'state/province', 'state province'],
        'country' => ['country', 'nation'], 'country_code' => ['country code', 'iso country code'], 'industry' => ['industry'],
        'business_type' => ['business type', 'company type'],
        'contact_person' => ['contact', 'contact person', 'name', 'first name'],
        'position' => ['position', 'job title', 'title'], 'email' => ['email', 'email address'],
        'phone' => ['phone', 'phone number', 'telephone'],
        'linkedin_url' => ['linkedin', 'linkedin url'],
        'import_trades' => ['import trades'],
        'data_source' => ['sources of data', 'data source'],
        'source_url' => ['link', 'source link'],
        'notes' => ['notes', 'comments'],
    ];

    /**
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public function map(array $headers): array
    {
        $mapping = [];
        $normalizedHeaders = [];
        foreach ($headers as $header) {
            $normalized = Str::of($header)
                ->replace("\xEF\xBB\xBF", '')
                ->lower()
                ->replace(['_', '-'], ' ')
                ->squish()
                ->toString();
            $normalizedHeaders[$header] = $normalized;
            $mapping[$header] = null;
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        // A real-world date column doesn't always match one of the exact
        // headings above (e.g. "Date (MM/DD/YYYY)", "Record Date - US").
        // When nothing has already claimed lead_date, fall back to the
        // first header that contains "date" as a whole word, so the row's
        // own date still wins over the upload day.
        if (! in_array('lead_date', $mapping, true)) {
            foreach ($normalizedHeaders as $header => $normalized) {
                if (preg_match('/\bdate\b/', $normalized) === 1) {
                    $mapping[$header] = 'lead_date';
                    break;
                }
            }
        }

        return $mapping;
    }

    /** @return list<string> */
    public function fields(): array
    {
        return array_keys(self::ALIASES);
    }
}
