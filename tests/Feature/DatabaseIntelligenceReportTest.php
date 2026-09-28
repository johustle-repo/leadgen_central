<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use App\Services\DatabaseIntelligenceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('database report extends the dashboard analytics with company, source, and geography intelligence', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00'));
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->count(2)->create(['normalized_company_name' => 'acme corp']);
    Lead::factory()->for($user, 'agent')->create(['normalized_company_name' => 'solo company']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('analytics/index')
        ->where('databaseReport.overview.records', 3)
        ->where('databaseReport.overview.companies', 2)
        ->has('databaseReport.company_analysis')
        ->has('databaseReport.source_quality')
        ->has('databaseReport.quality_trend')
        ->has('databaseReport.geographic_detail'));
});

test('company analysis separates single from multiple contact companies and averages correctly', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->count(3)->create(['normalized_company_name' => 'acme corp']);
    Lead::factory()->for($user, 'agent')->create(['normalized_company_name' => 'solo company']);
    Lead::factory()->for($user, 'agent')->create(['normalized_company_name' => null, 'company_name' => '']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.company_analysis.single_contact', 1)
        ->where('databaseReport.company_analysis.multiple_contacts', 1)
        ->where('databaseReport.company_analysis.average_contacts', 2)
        ->where('databaseReport.company_analysis.unnamed_records', 1));
});

test('lead-level source distribution buckets known vendors case-insensitively and omits zero-count buckets', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['data_source' => 'Tendata']);
    Lead::factory()->for($user, 'agent')->create(['data_source' => 'tendata']);
    Lead::factory()->for($user, 'agent')->create(['data_source' => 'Apollo']);
    Lead::factory()->for($user, 'agent')->create(['data_source' => 'Tendata/Lusha']);
    Lead::factory()->for($user, 'agent')->create(['data_source' => 'lusha/tendata']);
    // A blank data_source on a *manually entered* lead is bucketed as "Manual" (the entry
    // method itself), so this one must come from a non-manual source to land in "Unknown".
    Lead::factory()->for($user, 'agent')->create(['data_source' => '', 'source' => 'csv']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.distributions.sources', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata')['value'] === 2)
        ->where('databaseReport.distributions.sources', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata & Lusha')['value'] === 2)
        ->where('databaseReport.distributions.sources', fn ($rows) => collect($rows)->firstWhere('label', 'Other')['value'] === 1)
        ->where('databaseReport.distributions.sources', fn ($rows) => collect($rows)->firstWhere('label', 'Unknown')['value'] === 1)
        // Lusha never appears among this agent's leads, so the distribution (unlike the
        // reference source_quality table) must not list it at all.
        ->where('databaseReport.distributions.sources', fn ($rows) => collect($rows)->firstWhere('label', 'Lusha') === null));
});

test('source quality always lists every known category as a reference row even with zero records', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['data_source' => 'Tendata']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('databaseReport.source_quality', 7)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata')['records'] === 1)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Lusha')['records'] === 0)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata & Lusha')['records'] === 0));
});

test('source quality buckets upload row outcomes from the processed-data snapshot, separate from the current lead source', function () {
    $user = User::factory()->create();
    $batch = UploadBatch::factory()->for($user)->create();
    UploadRow::factory()->for($batch)->create(['row_number' => 1, 'processing_status' => 'accepted', 'processed_data' => ['data_source' => 'Tendata']]);
    UploadRow::factory()->for($batch)->create(['row_number' => 2, 'processing_status' => 'rejected', 'processed_data' => ['data_source' => 'Tendata']]);
    UploadRow::factory()->for($batch)->create(['row_number' => 3, 'processing_status' => 'accepted', 'processed_data' => ['data_source' => 'Lusha']]);
    UploadRow::factory()->for($batch)->create(['row_number' => 4, 'processing_status' => 'pending', 'processed_data' => null]);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata')['processed'] === 2)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata')['accepted'] === 1)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Tendata')['rejected'] === 1)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Lusha')['processed'] === 1)
        ->where('databaseReport.source_quality', fn ($rows) => collect($rows)->firstWhere('label', 'Lusha')['accepted'] === 1));
});

test('quality trend reports both counts and rates together at day week and month granularity', function (string $granularity, string $firstBucket) {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00'));
    $user = User::factory()->create();
    $batch = UploadBatch::factory()->for($user)->create(['created_at' => '2026-08-31 10:00:00']);
    UploadRow::factory()->for($batch)->create(['row_number' => 1, 'processing_status' => 'accepted']);
    UploadRow::factory()->for($batch)->create(['row_number' => 2, 'processing_status' => 'rejected']);

    $this->actingAs($user)->get(route('report.index', ['period' => 'custom', 'date_from' => '2026-08-31', 'date_to' => '2026-09-01', 'granularity' => $granularity]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.quality_trend.0.date', $firstBucket)
        ->where('databaseReport.quality_trend.0.processed', 2)
        ->where('databaseReport.quality_trend.0.rejected', 1)
        ->where('databaseReport.quality_trend.0.rejected_rate', 50));
})->with([
    ['day', '2026-08-31'],
    ['week', '2026-08-31'],
    ['month', '2026-08-01'],
]);

test('industries panel is hidden below twenty percent coverage and shown once past it', function () {
    $sparse = User::factory()->create();
    Lead::factory()->for($sparse, 'agent')->create(['industry' => 'Manufacturing']);
    Lead::factory()->for($sparse, 'agent')->count(9)->create(['industry' => null]);

    $this->actingAs($sparse)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.show_industries', false));

    $covered = User::factory()->create();
    Lead::factory()->for($covered, 'agent')->count(3)->create(['industry' => 'Manufacturing']);
    Lead::factory()->for($covered, 'agent')->count(2)->create(['industry' => null]);

    $this->actingAs($covered)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.show_industries', true));
});

test('geographic analysis groups leads by country and totals records', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'PH', 'country' => 'Philippines', 'state_province' => 'Metro Manila', 'city' => 'Manila']);
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'PH', 'country' => 'Philippines', 'state_province' => 'Cebu', 'city' => 'Cebu City']);
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'US', 'country' => 'United States', 'state_province' => 'California', 'city' => 'Los Angeles']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.geographic_detail.records', 3)
        ->has('databaseReport.geographic_detail.rows', 2)
        ->where('databaseReport.geographic_detail.rows', fn ($rows) => collect($rows)->firstWhere('country', 'PH')['records'] === 2
            && collect($rows)->firstWhere('country', 'US')['records'] === 1));
});

test('a country name that does not exactly match the canonical spelling still folds into the right country total', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'US', 'country' => 'United States']);
    Lead::factory()->for($user, 'agent')->create(['country_code' => null, 'country' => 'United States']);
    Lead::factory()->for($user, 'agent')->create(['country_code' => null, 'country' => 'Republic of Ireland']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.geographic_detail.rows', fn ($rows) => collect($rows)->firstWhere('country', 'US')['records'] === 2
            && collect($rows)->firstWhere('country', 'IE')['records'] === 1));
});

test('contribution by agent is visible only to administrators and super administrators', function (string $role, bool $visible) {
    $user = User::factory()->create(['role' => $role]);
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create();

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.contribution', fn ($rows) => $visible ? count($rows) > 0 : count($rows) === 0));
})->with([
    'agent' => ['agent', false],
    'sub-admin' => ['sub_administrator', false],
    'administrator' => ['administrator', true],
    'super administrator' => ['super_administrator', true],
]);

test('demographics count leads and possible leads per region, country, city and agent', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['name' => 'Ana Agent']);
    Lead::factory()->for($agent, 'agent')->create(['country_code' => 'PH', 'country' => 'Philippines', 'city' => 'Manila', 'status' => 'possible_lead']);
    Lead::factory()->for($agent, 'agent')->create(['country_code' => 'PH', 'country' => 'Philippines', 'city' => ' manila ', 'status' => 'raw']);
    Lead::factory()->for($agent, 'agent')->create(['country_code' => null, 'country' => 'United Arab Emirates', 'city' => 'Dubai', 'status' => 'qualified_lead']);
    Lead::factory()->for($agent, 'agent')->create(['country_code' => null, 'country' => 'Atlantis', 'city' => null, 'status' => 'possible_lead']);

    $this->actingAs($administrator)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.demographics.truncated', false)
        ->where("databaseReport.demographics.agents.{$agent->id}", 'Ana Agent')
        ->where('databaseReport.demographics.rows', function ($rows) use ($agent) {
            $rows = collect($rows);
            $manila = $rows->firstWhere('country', 'PH');
            $dubai = $rows->firstWhere('country', 'AE');
            $unknown = $rows->firstWhere('country', 'atlantis');

            return $rows->count() === 3
                && $manila['region'] === 'Asia' && $manila['records'] === 2 && $manila['possible'] === 1 && $manila['agent_id'] === $agent->id
                && $dubai['region'] === 'Middle East' && $dubai['qualified'] === 1 && $dubai['possible'] === 0
                && $unknown['region'] === 'Unassigned' && $unknown['city'] === 'Unknown' && $unknown['possible'] === 1;
        }));
});

test('mexico belongs to the americas region even though the regions reference omits it', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'MX', 'country' => 'Mexico']);
    Lead::factory()->for($user, 'agent')->create(['country_code' => null, 'country' => 'Mexico']);

    $this->actingAs($user)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseReport.demographics.rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['country'] === 'MX' && $row['region'] === 'The Americas')));
});

test('an agent only sees their own leads in the demographics', function () {
    $agent = User::factory()->create();
    $other = User::factory()->create(['name' => 'Other Agent']);
    Lead::factory()->for($agent, 'agent')->create(['country_code' => 'US']);
    Lead::factory()->for($other, 'agent')->create(['country_code' => 'CA']);

    $this->actingAs($agent)->get(route('report.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('databaseReport.demographics.rows', 1)
        ->where('databaseReport.demographics.rows.0.country', 'US')
        ->where('databaseReport.demographics.agents', fn ($agents) => collect($agents)->keys()->all() === [$agent->id]));
});

test('csv export breaks leads down by region, country, city and agent with possible lead counts', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create(['name' => 'Ana Agent']);
    Lead::factory()->for($agent, 'agent')->count(2)->create(['country_code' => 'AU', 'city' => 'Canberra', 'status' => 'possible_lead']);

    $csv = (string) $this->actingAs($administrator)->get(route('report.export'))->assertOk()->streamedContent();

    expect($csv)
        ->toContain("\"Leads by region - selected period\"\nRegion,Leads,\"Possible leads\",\"Qualified leads\",Forwarded,\"Possible lead rate\"\nOceania,2,2,0,0,100%")
        ->toContain('Australia,2,2,0,0,100%')
        ->toContain('"Canberra, Australia",2,2,0,0,100%')
        ->toContain('"Ana Agent",2,2,0,0,100%');
});

test('csv and pdf exports include the new database intelligence sections without dropping legacy ones', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['data_source' => 'Tendata']);

    $csv = (string) $this->actingAs($administrator)->get(route('report.export'))->assertOk()->streamedContent();
    expect($csv)
        ->toContain('Database summary - selected period')
        ->toContain('Database totals - all time')
        ->toContain('Company and contact analysis')
        ->toContain('Source quality - selected period')
        ->toContain('Geographic analysis')
        ->toContain('Database contribution by agent - selected period')
        ->toContain('Summary')
        ->toContain('Lead status');

    $pdf = $this->actingAs($administrator)->get(route('report.export-pdf'));
    $pdf->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');
});

test('database report query count does not grow with lead or upload row volume', function () {
    $administrator = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create(['normalized_company_name' => 'acme']);
    $batch = UploadBatch::factory()->for($agent)->create();
    UploadRow::factory()->for($batch)->create(['processing_status' => 'accepted']);
    DB::enableQueryLog();
    app(DatabaseIntelligenceReport::class)->for($administrator, ['date_from' => '2026-08-01', 'date_to' => '2026-09-10']);
    $initialCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    Lead::factory()->count(15)->for($agent, 'agent')->create(['normalized_company_name' => 'other co']);
    UploadBatch::factory()->for($agent)->count(15)->create()->each(
        fn (UploadBatch $extraBatch) => UploadRow::factory()->for($extraBatch)->create(['processing_status' => 'accepted']),
    );
    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(DatabaseIntelligenceReport::class)->for($administrator, ['date_from' => '2026-08-01', 'date_to' => '2026-09-10']);

    expect(count(DB::getQueryLog()))->toBe($initialCount);
    DB::disableQueryLog();
});
