<?php

use App\Services\CsvHeaderMapper;

it('maps the established cleaned lead file headings', function () {
    $mapping = (new CsvHeaderMapper)->map([
        'Company',
        'Date',
        'First Name',
        'Country',
        'Import Trades',
        'Sources of Data',
        'Link',
        'Unnamed: 0',
    ]);

    expect($mapping)->toBe([
        'Company' => 'company_name',
        'Date' => 'lead_date',
        'First Name' => 'contact_person',
        'Country' => 'country',
        'Import Trades' => 'import_trades',
        'Sources of Data' => 'data_source',
        'Link' => 'source_url',
        'Unnamed: 0' => null,
    ]);
});

it('maps every column of the default lead upload template', function () {
    // This is the exact header row shipped in public/templates/lead-upload-template.csv,
    // linked from the Upload Leads page as the recommended starting format.
    $mapping = (new CsvHeaderMapper)->map([
        'Date', 'Company', 'Website', 'First Name', 'Email', 'Country',
        'City', 'Import Trades', 'LinkedIn', 'Sources of Data', 'Source Link',
    ]);

    expect($mapping)->toBe([
        'Date' => 'lead_date',
        'Company' => 'company_name',
        'Website' => 'website',
        'First Name' => 'contact_person',
        'Email' => 'email',
        'Country' => 'country',
        'City' => 'city',
        'Import Trades' => 'import_trades',
        'LinkedIn' => 'linkedin_url',
        'Sources of Data' => 'data_source',
        'Source Link' => 'source_url',
    ]);
});
