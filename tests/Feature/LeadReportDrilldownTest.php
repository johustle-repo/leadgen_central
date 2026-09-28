<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\DashboardReport;
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
    Lead::factory()->create(['company_name' => 'Old Blank Source', 'data_source' => null, 'source' => 'csv', 'created_at' => '2026-07-01 10:00:00']);

    expect(drilldownCompanies($administrator, ['created_from' => '2026-09-01', 'created_to' => '2026-09-28', 'source_group' => 'Unknown']))
        ->toBe(['Blank Source', 'Unknown Source'])
        ->and(drilldownCompanies($administrator, ['created_from' => '2026-09-01', 'created_to' => '2026-09-28', 'source_group' => 'Tendata']))
        ->toBe(['Tendata Source']);
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
