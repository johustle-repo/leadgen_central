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
