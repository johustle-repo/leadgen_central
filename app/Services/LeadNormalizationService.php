<?php

namespace App\Services;

use Illuminate\Support\Str;

class LeadNormalizationService
{
    public function __construct(private DomainNormalizer $domains) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        $website = filled($data['website'] ?? null) ? trim((string) $data['website']) : null;
        $company = Str::of((string) ($data['company_name'] ?? ''))->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return [...$data,
            'company_name' => Str::squish((string) ($data['company_name'] ?? '')),
            'normalized_company_name' => $company,
            'original_website' => $website,
            'website' => $website,
            'website_domain' => $this->domains->domain($website),
            'contact_person' => filled($data['contact_person'] ?? null) ? Str::squish((string) $data['contact_person']) : null,
            'email' => filled($data['email'] ?? null) ? Str::lower(trim((string) $data['email'])) : null,
            'phone' => filled($data['phone'] ?? null) ? preg_replace('/[^0-9+]/', '', (string) $data['phone']) : null,
        ];
    }

    public function normalizeLocation(?string $value): string
    {
        return Str::of((string) $value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
