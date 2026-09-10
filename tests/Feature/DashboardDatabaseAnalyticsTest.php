<?php

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use App\Services\DashboardReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('database analytics obey role scope even when another owner or global search is requested', function (string $role, int $records, bool $comparison) {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00'));
    $user = User::factory()->create(['role' => $role]);
    $other = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['normalized_company_name' => 'own company']);
    Lead::factory()->for($other, 'agent')->create(['normalized_company_name' => 'other company']);
    $ownBatch = UploadBatch::factory()->for($user)->create();
    $otherBatch = UploadBatch::factory()->for($other)->create();
    UploadRow::factory()->for($ownBatch)->create(['processing_status' => 'accepted']);
    UploadRow::factory()->for($otherBatch)->create(['processing_status' => 'accepted']);

    $this->actingAs($user)->get(route('dashboard', ['search' => 'other', 'agent_id' => $other->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.overview.records', $records)
        ->where('databaseAnalytics.all_time.records', $records)
        ->where('databaseAnalytics.overview.uploads', $records)
        ->where('databaseAnalytics.quality.accepted', $records)
        ->where('databaseAnalytics.growth.totals.records', $records)
        ->where('databaseAnalytics.can_compare_agents', $comparison)
        ->has('recentLeads', $records)->has('recentBatches', $records)
        ->where('databaseAnalytics.contribution', fn ($rows) => $comparison ? count($rows) > 0 : count($rows) === 0)
        ->missing('stats.unread_replies'));
})->with([
    'agent' => ['agent', 1, false],
    'sub-admin' => ['sub_administrator', 2, false],
    'administrator' => ['administrator', 2, true],
]);

test('overview separates contact records company groups email identities and first appearances', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00'));
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['company_name' => 'Acme', 'normalized_company_name' => 'acme', 'email' => 'repeat@example.com', 'created_at' => '2026-08-31 23:59:59']);
    Lead::factory()->for($user, 'agent')->create(['company_name' => 'ACME', 'normalized_company_name' => ' ACME ', 'email' => ' REPEAT@example.com ', 'created_at' => '2026-09-01 00:00:00']);
    Lead::factory()->for($user, 'agent')->create(['company_name' => 'Acme', 'normalized_company_name' => 'acme', 'email' => 'new@example.com', 'created_at' => '2026-09-02']);
    Lead::factory()->for($user, 'agent')->create(['company_name' => 'New company', 'normalized_company_name' => null, 'email' => '   ', 'created_at' => '2026-09-03']);
    Lead::factory()->for($user, 'agent')->create(['normalized_company_name' => 'deleted', 'created_at' => '2026-09-03', 'deleted_at' => now()]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.overview.records', 3)
        ->where('databaseAnalytics.overview.companies', 2)
        ->where('databaseAnalytics.overview.emails', 2)
        ->where('databaseAnalytics.all_time.records', 4)
        ->where('databaseAnalytics.growth.totals.records', 3)
        ->where('databaseAnalytics.growth.totals.companies', 1)
        ->where('databaseAnalytics.growth.totals.emails', 1)
        ->where('databaseAnalytics.companies.0.contacts', 2)
        ->where('databaseAnalytics.companies.0.emails', 2)
        ->where('databaseAnalytics.changes.records', 200)
        ->where('databaseAnalytics.missing', fn ($rows) => collect($rows)->firstWhere('label', 'email')['value'] === 1));
});

test('quality counts overlapping outcomes once per category and excludes pending rows from rates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00'));
    $admin = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    $batch = UploadBatch::factory()->for($agent)->create(['accepted_rows' => 999, 'total_rows' => 999]);
    foreach ([['accepted', null], ['needs_review', 'possible_duplicate'], ['duplicate', 'exact_duplicate'], ['rejected', 'validation'], ['error', 'processing'], ['pending', null]] as $index => [$status, $category]) {
        UploadRow::factory()->for($batch)->create(['row_number' => $index + 1, 'processing_status' => $status, 'error_category' => $category]);
    }
    $second = UploadBatch::factory()->for($agent)->create();
    UploadRow::factory()->for($second)->create(['processing_status' => 'accepted']);
    UploadBatch::factory()->for($agent)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.quality.rows', 7)
        ->where('databaseAnalytics.quality.processed', 6)
        ->where('databaseAnalytics.quality.pending', 1)
        ->where('databaseAnalytics.quality.accepted', 3)
        ->where('databaseAnalytics.quality.needs_review', 1)
        ->where('databaseAnalytics.quality.duplicates', 2)
        ->where('databaseAnalytics.quality.rejected', 1)
        ->where('databaseAnalytics.quality.errors', 1)
        ->where('databaseAnalytics.quality.accepted_rate', 50)
        ->where('databaseAnalytics.quality.duplicates_rate', 33.3)
        ->where('databaseAnalytics.quality.rejected_rate', 16.7)
        ->where('databaseAnalytics.quality.errors_rate', 16.7)
        ->where('databaseAnalytics.quality.average_acceptance_rate', 70)
        ->where('databaseAnalytics.quality.average_duplicate_rate', 20)
        ->where('databaseAnalytics.quality.average_rows_per_upload', 2.3)
        ->where('databaseAnalytics.contribution.0.accepted', 3)
        ->where('databaseAnalytics.contribution.0.issues', 3));
});

test('empty data has zero counts null rates and no invented percentage growth', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.overview.records', 0)
        ->where('databaseAnalytics.quality.accepted_rate', null)
        ->where('databaseAnalytics.quality.average_duplicate_rate', null)
        ->where('databaseAnalytics.changes.records', 0)
        ->has('databaseAnalytics.companies', 0));
});

test('reporting presets resolve exact dates and preceding equal length periods', function (string $period, string $from, string $to, string $previousFrom, string $previousTo) {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00'));
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard', ['period' => $period]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.date_from', $from)->where('filters.date_to', $to)
        ->where('databaseAnalytics.previous_period.from', $previousFrom)
        ->where('databaseAnalytics.previous_period.to', $previousTo));
})->with([
    ['today', '2026-09-10', '2026-09-10', '2026-09-09', '2026-09-09'],
    ['week', '2026-09-07', '2026-09-10', '2026-09-03', '2026-09-06'],
    ['last_week', '2026-08-31', '2026-09-06', '2026-08-24', '2026-08-30'],
    ['month', '2026-09-01', '2026-09-10', '2026-08-22', '2026-08-31'],
    ['last_month', '2026-08-01', '2026-08-31', '2026-07-01', '2026-07-31'],
    ['30_days', '2026-08-12', '2026-09-10', '2026-07-13', '2026-08-11'],
    ['quarter', '2026-07-01', '2026-09-10', '2026-04-20', '2026-06-30'],
]);

test('custom periods apply inclusive day boundaries to records batches and contribution', function () {
    $user = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    foreach (['2026-08-31 23:59:59', '2026-09-01 00:00:00', '2026-09-02 23:59:59', '2026-09-03 00:00:00'] as $date) {
        Lead::factory()->for($agent, 'agent')->create(['created_at' => $date, 'status' => 'qualified_lead']);
        UploadBatch::factory()->for($agent)->create(['created_at' => $date]);
    }

    $this->actingAs($user)->get(route('dashboard', ['period' => 'custom', 'date_from' => '2026-09-01', 'date_to' => '2026-09-02']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.overview.records', 2)
        ->where('databaseAnalytics.overview.uploads', 2)
        ->where('databaseAnalytics.contribution.0.qualified', 2)
        ->has('recentLeads', 2)->has('recentBatches', 2)
        ->where('databaseAnalytics.growth.points.0.records', 1)
        ->where('databaseAnalytics.growth.points.1.records', 1));
});

test('growth buckets do not recount an existing company or email across intervals', function (string $granularity, string $firstDate, int $firstCount) {
    $user = User::factory()->create();
    foreach (['2026-08-31', '2026-09-01', '2026-09-07'] as $date) {
        Lead::factory()->for($user, 'agent')->create(['created_at' => $date, 'normalized_company_name' => 'same company', 'email' => 'same@example.com']);
    }

    $this->actingAs($user)->get(route('dashboard', ['period' => 'custom', 'date_from' => '2026-08-31', 'date_to' => '2026-09-07', 'granularity' => $granularity]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.growth.points.0.date', $firstDate)
        ->where('databaseAnalytics.growth.points.0.records', $firstCount)
        ->where('databaseAnalytics.growth.totals.records', 3)
        ->where('databaseAnalytics.growth.totals.companies', 1)
        ->where('databaseAnalytics.growth.totals.emails', 1)
        ->where('databaseAnalytics.growth.totals.records_change', null));
})->with([['day', '2026-08-31', 1], ['week', '2026-08-31', 2], ['month', '2026-08-01', 1]]);

test('invalid report filters return validation errors', function (array $query, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard', $query))->assertSessionHasErrors($field);
})->with([
    [['period' => 'unbounded'], 'period'],
    [['period' => 'custom'], 'date_from'],
    [['period' => 'custom', 'date_from' => '2026-09-10'], 'date_to'],
    [['period' => 'custom', 'date_from' => '2026-09-10', 'date_to' => '2026-09-01'], 'date_to'],
    [['period' => 'custom', 'date_from' => 'not-a-date', 'date_to' => '2026-09-01'], 'date_from'],
    [['period' => 'custom', 'date_from' => '2000-01-01', 'date_to' => '2026-09-01'], 'date_to'],
    [['granularity' => 'year'], 'granularity'],
]);

test('geography preserves stored city values and distributions include the tail and missing values', function () {
    $user = User::factory()->create();
    foreach (range(1, 12) as $index) {
        Lead::factory()->for($user, 'agent')->create(['country' => 'Country '.$index, 'country_code' => null, 'city' => 'Province in city field', 'state_province' => null, 'timezone' => null]);
    }
    Lead::factory()->for($user, 'agent')->create(['country' => ' ', 'country_code' => null, 'city' => null]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('databaseAnalytics.geography.unverified_city_records', 12)
        ->where('databaseAnalytics.geography.rows', fn ($rows) => collect($rows)->where('city', 'Province in city field')->count() === 12)
        ->where('databaseAnalytics.geography.rows.0.province', 'Unknown')
        ->where('databaseAnalytics.distributions.countries.10.label', 'Other groups')
        ->where('databaseAnalytics.distributions.countries.10.value', 3)
        ->where('databaseAnalytics.distributions.countries', fn ($rows) => collect($rows)->sum('value') === 13)
        ->where('databaseAnalytics.missing', fn ($rows) => collect($rows)->firstWhere('label', 'country')['value'] === 1));
});

test('dashboard query count does not grow with lead or agent count', function () {
    $admin = User::factory()->administrator()->create();
    $agent = User::factory()->create();
    Lead::factory()->for($agent, 'agent')->create();
    UploadBatch::factory()->for($agent)->create();
    DB::enableQueryLog();
    app(DashboardReport::class)->for($admin, []);
    $initialCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    Lead::factory()->count(12)->create();
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(DashboardReport::class)->for($admin, []);

    expect(count(DB::getQueryLog()))->toBe($initialCount);
    DB::disableQueryLog();
});

test('geographic groups use projected country codes and trimmed location labels', function () {
    $user = User::factory()->create();
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'PH', 'country' => 'Philippines', 'state_province' => ' Metro Manila ', 'city' => ' Manila ', 'timezone' => 'Asia/Manila']);
    Lead::factory()->for($user, 'agent')->create(['country_code' => 'PH', 'country' => 'Different historical label', 'state_province' => 'Metro Manila', 'city' => 'Manila', 'timezone' => 'Asia/Manila']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('databaseAnalytics.geography.rows', 1)
        ->where('databaseAnalytics.geography.rows.0.country', 'PH')
        ->where('databaseAnalytics.geography.rows.0.province', 'Metro Manila')
        ->where('databaseAnalytics.geography.rows.0.city', 'Manila')
        ->where('databaseAnalytics.geography.rows.0.records', 2));
});
