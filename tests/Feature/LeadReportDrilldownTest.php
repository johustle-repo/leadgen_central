<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\DashboardReport;
use App\Services\DatabaseIntelligenceReport;
use App\Support\CountryRegions;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return list<string>
 */
function drilldownCompanies(User $user, array $query): array
{
    $companies = [];
    test()->actingAs($user)->get(route('leads.index', [...$query, 'per_page' => 100]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$companies) {
            $companies = collect($page->toArray()['props']['leads']['data'])->pluck('company_name')->sort()->values()->all();
        });

    return $companies;
}

beforeEach(function () {
    $this->travelTo('2026-09-28 12:00:00');
});

it('merges differently stored spellings of a country in the report', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    foreach ([['US', null], [null, 'United States'], [null, 'united states of america'], ['TT', null], [null, 'Trinidad and Tobago'], [null, null]] as [$code, $name]) {
        Lead::factory()->for($agent, 'agent')->create(['country_code' => $code, 'country' => $name]);
    }

    $report = app(DashboardReport::class)->for($administrator, ['date_from' => '2026-09-01', 'date_to' => '2026-09-28'])['databaseAnalytics'];

    expect(array_column($report['distributions']['countries'], 'value', 'label'))->toBe(['US' => 3, 'TT' => 2, 'Unknown' => 1])
        ->and($report['overview']['countries'])->toBe(2);
});

it('lists the leads behind a data source bar, limited to the report period', function () {
    $administrator = User::factory()->administrator()->create();
    Lead::factory()->create(['company_name' => 'Blank Source', 'data_source' => null, 'source' => 'csv']);
    Lead::factory()->create(['company_name' => 'Unknown Source', 'data_source' => 'n/a', 'source' => 'csv']);
    Lead::factory()->create(['company_name' => 'Tendata Source', 'data_source' => 'Tendata']);
    Lead::factory()->create(['company_name' => 'Combined Source', 'data_source' => 'Tendata/Lusha']);
    Lead::factory()->create(['company_name' => 'Old Blank Source', 'data_source' => null, 'source' => 'csv', 'created_at' => '2026-07-01 10:00:00']);

    expect(drilldownCompanies($administrator, ['created_from' => '2026-09-01', 'created_to' => '2026-09-28', 'source_group' => 'Unknown']))
        ->toBe(['Blank Source', 'Unknown Source'])
        ->and(drilldownCompanies($administrator, ['created_from' => '2026-09-01', 'created_to' => '2026-09-28', 'source_group' => 'Tendata']))
        ->toBe(['Tendata Source'])
        ->and(drilldownCompanies($administrator, ['created_from' => '2026-09-01', 'created_to' => '2026-09-28', 'source_group' => 'Tendata & Lusha']))
        ->toBe(['Combined Source']);
});

it('lists every spelling of a country and the leads with no country', function () {
    $administrator = User::factory()->administrator()->create();
    Lead::factory()->create(['company_name' => 'Coded', 'country_code' => 'US', 'country' => null]);
    Lead::factory()->create(['company_name' => 'Named', 'country_code' => null, 'country' => 'United States']);
    Lead::factory()->create(['company_name' => 'Canadian', 'country_code' => 'CA', 'country' => null]);
    Lead::factory()->create(['company_name' => 'Nowhere', 'country_code' => '', 'country' => null]);

    expect(drilldownCompanies($administrator, ['country_group' => 'US']))->toBe(['Coded', 'Named'])
        ->and(drilldownCompanies($administrator, ['country_group' => 'Unknown']))->toBe(['Nowhere']);
});

it('lists the leads behind company, industry and raw data source bars', function () {
    $administrator = User::factory()->administrator()->create();
    Lead::factory()->create(['company_name' => 'Kiewit', 'normalized_company_name' => 'kiewit', 'industry' => 'Construction', 'data_source' => 'Lusha/Tendata']);
    Lead::factory()->create(['company_name' => 'Globex', 'normalized_company_name' => 'globex', 'industry' => null, 'data_source' => 'Lusha']);

    expect(drilldownCompanies($administrator, ['company' => 'kiewit']))->toBe(['Kiewit'])
        ->and(drilldownCompanies($administrator, ['industry' => 'Unknown']))->toBe(['Globex'])
        ->and(drilldownCompanies($administrator, ['data_source' => 'lusha/tendata']))->toBe(['Kiewit']);
});

it('lists the same leads the demographics tab counts for each region, country and city', function () {
    $administrator = User::factory()->administrator()->create();
    foreach ([
        ['US', null, 'Texas'], [null, 'United States', 'texas'], ['CA', null, 'Ontario'], [null, 'Trinidad and Tobago', null],
        ['GB', null, 'London'], ['XX', null, 'Nowhere City'], [null, 'Atlantis', 'Poseidonia'], [null, null, null],
    ] as $index => [$code, $country, $city]) {
        Lead::factory()->create(['company_name' => "Lead {$index}", 'country_code' => $code, 'country' => $country, 'city' => $city]);
    }
    $intelligence = app(DatabaseIntelligenceReport::class);
    $report = $intelligence->for($administrator, ['date_from' => '2026-09-01', 'date_to' => '2026-09-28']);

    foreach (['region' => 'region', 'country' => 'country_group'] as $dimension => $key) {
        foreach ($intelligence->demographicBreakdown($report, $dimension) as $row) {
            $criterion = $dimension === 'country' ? (CountryRegions::codeForName($row['label']) ?? $row['label']) : $row['label'];

            expect(drilldownCompanies($administrator, [$key => $criterion]))->toHaveCount($row['records'], "{$dimension} {$row['label']}");
        }
    }
    expect(drilldownCompanies($administrator, ['region' => 'Unassigned']))->toBe(['Lead 5', 'Lead 6', 'Lead 7'])
        ->and(drilldownCompanies($administrator, ['country_group' => 'US', 'city' => 'Texas']))->toBe(['Lead 0', 'Lead 1'])
        ->and(drilldownCompanies($administrator, ['city' => 'Unknown']))->toBe(['Lead 3', 'Lead 7']);
});

it('lists the leads of the owner picked on the demographics tab', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Owned']);
    Lead::factory()->create(['company_name' => 'Colleague']);

    expect(drilldownCompanies($administrator, ['agent' => (string) $agent->id]))->toBe(['Owned']);
});

it('keeps an agent drill-down to their own leads and clears the default lead date', function () {
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['company_name' => 'Mine', 'data_source' => null, 'source' => 'csv']);
    Lead::factory()->create(['company_name' => 'Colleague', 'data_source' => null, 'source' => 'csv']);

    $this->actingAs($agent)->get(route('leads.index', ['source_group' => 'Unknown']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.company_name', 'Mine')
            ->where('filters.source_group', 'Unknown')
            ->where('filters.date', ''));
});

it('rejects a malformed report period', function () {
    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('leads.index', ['created_from' => 'not-a-date']))
        ->assertSessionHasErrors('created_from');
});
