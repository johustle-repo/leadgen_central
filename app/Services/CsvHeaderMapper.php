<?php

namespace App\Services;

class CsvHeaderMapper
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'lead_date' => ['date', 'lead date'],
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
        foreach ($headers as $header) {
            $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $header) ?? $header));
            $mapping[$header] = null;
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $mapping[$header] = $field;
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
