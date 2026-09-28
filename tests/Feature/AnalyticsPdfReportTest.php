<?php

use App\Services\AnalyticsPdfReport;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pdfReportData(array $overrides = []): array
{
    $quality = ['processed' => 15, 'accepted' => 10, 'needs_review' => 2, 'duplicates' => 5, 'rejected' => 2, 'errors' => 1, 'issues' => 8,
        'accepted_rate' => 66.7, 'duplicates_rate' => 33.3, 'rejected_rate' => 13.3, 'errors_rate' => 6.7];

    return ['databaseReport' => array_replace([
        'overview' => ['records' => 100, 'companies' => 20, 'emails' => 90, 'countries' => 2, 'uploads' => 3],
        'previous' => ['records' => 50, 'companies' => 10, 'emails' => 45, 'countries' => 2, 'uploads' => 1],
        'changes' => ['records' => 100.0, 'companies' => 100.0, 'emails' => 100.0, 'countries' => 0.0, 'uploads' => 200.0],
        'previous_period' => ['from' => '2026-07-01', 'to' => '2026-07-31'],
        'quality' => $quality,
        'quality_trend' => [],
        'distributions' => ['statuses' => [['label' => 'validated', 'value' => 80], ['label' => 'possible_lead', 'value' => 20]]],
        'source_quality' => [['label' => 'Tendata', 'records' => 70], ['label' => 'Lusha', 'records' => 30], ['label' => 'Email', 'records' => 0]],
        'demographics' => ['rows' => [
            ['region' => 'US & Canada', 'country' => 'US', 'city' => 'Texas', 'agent_id' => null, 'records' => 60, 'possible' => 5, 'qualified' => 0, 'forwarded' => 0],
            ['region' => 'US & Canada', 'country' => 'CA', 'city' => 'Ontario', 'agent_id' => null, 'records' => 40, 'possible' => 15, 'qualified' => 0, 'forwarded' => 0],
        ], 'agents' => []],
        'companies' => [['label' => 'kiewit', 'contacts' => 8, 'emails' => 8]],
        'contribution' => [],
        'company_analysis' => ['average_contacts' => 5.0, 'single_contact' => 5, 'multiple_contacts' => 15],
        'growth' => ['granularity' => 'day', 'points' => [['date' => '2026-08-01', 'records' => 100]]],
    ], $overrides)];
}

it('merges long ranges into at most the column limit while keeping the total and peak', function () {
    $points = array_map(fn (int $day): array => [
        'date' => now()->setDate(2026, 1, 1)->addDays($day)->toDateString(),
        'records' => $day === 40 ? 500 : 1,
    ], range(0, 89));

    $growth = app(AnalyticsPdfReport::class)->for(pdfReportData(['growth' => ['granularity' => 'day', 'points' => $points]]))['growth'];

    expect($growth['columns'])->toHaveCount(30)
        ->and($growth['total'])->toBe(589)
        ->and($growth['peak']['value'])->toBe(502)
        ->and($growth['bucket'])->toBe('3-day span')
        ->and(max(array_column($growth['columns'], 'height')))->toBe(100.0);
});

it('splits import outcomes into disjoint statuses that add up to the processed rows', function () {
    $outcomes = app(AnalyticsPdfReport::class)->for(pdfReportData())['outcomes'];

    expect(array_column($outcomes, 'value', 'label'))->toBe([
        'Accepted' => 8,
        'Needs review' => 2,
        'Skipped as duplicate' => 2,
        'Rejected' => 2,
        'Errors' => 1,
    ])->and(array_sum(array_column($outcomes, 'value')))->toBe(15);
});

it('ranks bars by share of all records and summarizes the period in plain findings', function () {
    $report = app(AnalyticsPdfReport::class)->for(pdfReportData());

    expect(array_column($report['sources'], 'label'))->toBe(['Tendata', 'Lusha'])
        ->and($report['sources'][1])->toMatchArray(['percent' => 30.0, 'width' => 42.9])
        ->and($report['countries'][0])->toMatchArray(['label' => 'United States of America', 'percent' => 60.0])
        ->and($report['kpis'][0])->toMatchArray(['label' => 'Records added', 'value' => '100', 'change' => 100.0, 'previous' => '50'])
        ->and($report['highlights'][0])->toBe('100 records added, +100% vs the previous period (2026-07-01 to 2026-07-31).')
        ->and($report['highlights'])->toContain('Tendata supplied 70% of records.');
});

it('reports an empty period without charts or findings that divide by zero', function () {
    $report = app(AnalyticsPdfReport::class)->for(pdfReportData([
        'overview' => ['records' => 0, 'companies' => 0, 'emails' => 0, 'countries' => 0, 'uploads' => 0],
        'quality' => ['processed' => 0, 'accepted' => 0, 'needs_review' => 0, 'duplicates' => 0, 'rejected' => 0, 'errors' => 0, 'issues' => 0,
            'accepted_rate' => null, 'duplicates_rate' => null, 'rejected_rate' => null, 'errors_rate' => null],
        'distributions' => ['statuses' => []],
        'demographics' => ['rows' => [], 'agents' => []],
        'growth' => ['granularity' => 'day', 'points' => [['date' => '2026-08-01', 'records' => 0]]],
    ]));

    expect($report['highlights'])->toBe(['No records were added in this period.'])
        ->and($report['outcomes'])->toBe([])
        ->and($report['classification'])->toBe([])
        ->and($report['growth']['peak'])->toBeNull()
        ->and($report['kpis'][5]['value'])->toBe('N/A');
});
