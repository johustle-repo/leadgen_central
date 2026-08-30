<?php

use App\Services\LeadNormalizationService;

it('preserves the submitted website while normalizing its domain and company name', function () {
    $normalized = app(LeadNormalizationService::class)->normalize(['company_name' => '  ABC Construction, LLC  ', 'website' => 'https://www.Example.com/about']);

    expect($normalized['company_name'])->toBe('ABC Construction, LLC')
        ->and($normalized['normalized_company_name'])->toBe('abc construction llc')
        ->and($normalized['original_website'])->toBe('https://www.Example.com/about')
        ->and($normalized['website_domain'])->toBe('example.com');
});
