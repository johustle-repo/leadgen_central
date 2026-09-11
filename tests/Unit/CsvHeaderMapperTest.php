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

it('falls back to any header containing the word date when no exact alias matches', function (string $header) {
    $mapping = (new CsvHeaderMapper)->map(['Company', $header, 'Email']);

    expect($mapping[$header])->toBe('lead_date');
})->with([
    'Date (MM/DD/YYYY)',
    'Record Date - US',
    'Date of Contact',
]);

it('does not let a fuzzy date header override an exact alias match found earlier', function () {
    $mapping = (new CsvHeaderMapper)->map(['Company', 'Date', 'Notes about update date']);

    expect($mapping)->toBe([
        'Company' => 'company_name',
        'Date' => 'lead_date',
        'Notes about update date' => null,
    ]);
});

it('maps every column of the default lead upload template', function () {
    // This is the recommended standard header row for lead upload CSVs.
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
