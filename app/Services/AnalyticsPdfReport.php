<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Shapes the analytics report into chart-ready values for the PDF export.
 * Dompdf has no scripting, so every chart is drawn from plain widths and
 * heights (percent of the largest value) computed here.
 */
class AnalyticsPdfReport
{
    /** Time-series charts merge consecutive points beyond this many columns. */
    public const MAX_COLUMNS = 31;

    /** Series colours, starting with the brand mint and navy. */
    public const PALETTE = ['#10b981', '#0ea5e9', '#6366f1', '#f59e0b', '#f43f5e', '#14b8a6', '#8b5cf6', '#64748b'];

    public function __construct(public DatabaseIntelligenceReport $intelligence) {}

    /**
     * @param  array<string, mixed>  $data  AnalyticsReport output with databaseReport.
     * @return array<string, mixed>
     */
    public function for(array $data): array
    {
        $report = $data['databaseReport'];
        $quality = $report['quality'];
        $records = (int) $report['overview']['records'];

        $classification = $this->segments(array_values(array_map(
            fn (array $row): array => ['label' => $this->labelOf((string) $row['label']), 'value' => (int) $row['value']],
            $report['distributions']['statuses'],
        )));
        $outcomes = $this->segments([
            ['label' => 'Accepted', 'value' => (int) $quality['accepted'] - (int) $quality['needs_review'], 'color' => '#10b981'],
            ['label' => 'Needs review', 'value' => (int) $quality['needs_review'], 'color' => '#0ea5e9'],
            ['label' => 'Skipped as duplicate', 'value' => $this->skippedDuplicates($quality), 'color' => '#f59e0b'],
            ['label' => 'Rejected', 'value' => (int) $quality['rejected'], 'color' => '#f43f5e'],
            ['label' => 'Errors', 'value' => (int) $quality['errors'], 'color' => '#64748b'],
        ]);
        $sources = $this->bars(array_map(
            fn (array $row): array => ['label' => $row['label'], 'value' => (int) $row['records']],
            array_values(array_filter($report['source_quality'], fn (array $row): bool => $row['records'] > 0)),
        ), $records);
        $countries = $this->bars(array_map(
            fn (array $row): array => ['label' => $row['label'], 'value' => $row['records']],
            $this->intelligence->demographicBreakdown($report, 'country', 10),
        ), $records);
        $regions = $this->bars(array_map(
            fn (array $row): array => ['label' => $row['label'], 'value' => $row['records']],
            $this->intelligence->demographicBreakdown($report, 'region'),
        ), $records);
        $cities = $this->bars(array_map(
            fn (array $row): array => ['label' => $row['label'], 'value' => $row['records']],
            $this->intelligence->demographicBreakdown($report, 'city', 15),
        ), $records);
        $companies = $this->bars(array_values(array_map(
            fn (array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['contacts']],
            $report['companies'],
        )), $records);
        $agents = $this->bars(array_values(array_map(
            fn (array $row): array => ['label' => (string) $row['name'], 'value' => (int) $row['records']],
            $report['contribution'],
        )), $records);
        $granularity = $report['growth']['granularity'] ?? 'day';
        $growth = $this->columns(array_values(array_map(
            fn (array $point): array => ['date' => (string) $point['date'], 'value' => (int) $point['records']],
            $report['growth']['points'],
        )), $granularity);

        return [
            'kpis' => $this->kpis($report),
            'highlights' => $this->highlights($report, $growth, $classification, $sources, $countries),
            'growth' => $growth,
            'qualityTrend' => $this->qualityColumns($report['quality_trend'] ?? [], $granularity),
            'classification' => $classification,
            'outcomes' => $outcomes,
            'companySplit' => $this->segments([
                ['label' => 'Multiple contacts', 'value' => (int) $report['company_analysis']['multiple_contacts'], 'color' => '#10b981'],
                ['label' => 'One contact', 'value' => (int) $report['company_analysis']['single_contact'], 'color' => '#0ea5e9'],
            ]),
            'sources' => $sources,
            'countries' => $countries,
            'regions' => $regions,
            'cities' => $cities,
            'companies' => $companies,
            'agents' => $agents,
        ];
    }

    /**
     * Headline tiles with the change against the previous period of equal length.
     *
     * @param  array<string, mixed>  $report
     * @return list<array{label: string, value: string, change: float|null, previous: string|null, note: string|null}>
     */
    private function kpis(array $report): array
    {
        $quality = $report['quality'];
        $tile = fn (string $label, string $key): array => [
            'label' => $label,
            'value' => number_format((int) $report['overview'][$key]),
            'change' => $report['changes'][$key] ?? null,
            'previous' => number_format((int) ($report['previous'][$key] ?? 0)),
            'note' => null,
        ];

        return [
            $tile('Records added', 'records'),
            $tile('Unique companies', 'companies'),
            $tile('Unique emails', 'emails'),
            $tile('Countries', 'countries'),
            $tile('Upload batches', 'uploads'),
            ['label' => 'Acceptance rate', 'value' => $this->percent($quality['accepted_rate']), 'change' => null, 'previous' => null, 'note' => number_format((int) $quality['processed']).' rows processed'],
            ['label' => 'Duplicates', 'value' => number_format((int) $quality['duplicates']), 'change' => null, 'previous' => null, 'note' => $this->percent($quality['duplicates_rate']).' of processed rows'],
            ['label' => 'Rows with issues', 'value' => number_format((int) $quality['issues']), 'change' => null, 'previous' => null, 'note' => number_format((int) $quality['rejected']).' rejected'],
        ];
    }

    /**
     * Plain-language findings; each is skipped when its data is missing.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $growth
     * @param  list<array<string, mixed>>  $classification
     * @param  list<array<string, mixed>>  $sources
     * @param  list<array<string, mixed>>  $countries
     * @return list<string>
     */
    private function highlights(array $report, array $growth, array $classification, array $sources, array $countries): array
    {
        $records = (int) $report['overview']['records'];
        if ($records === 0) {
            return ['No records were added in this period.'];
        }

        $change = $report['changes']['records'] ?? null;
        $previous = $report['previous_period'];
        $highlights = [sprintf(
            '%s records added, %s the previous period (%s to %s).',
            number_format($records),
            $change === null ? 'with none in' : ($change >= 0 ? '+' : '').$change.'% vs',
            $previous['from'],
            $previous['to'],
        )];

        if ($growth['peak'] !== null) {
            $highlights[] = sprintf('Busiest %s was %s with %s records (%s of the period).', $growth['bucket'], $growth['peak']['label'], number_format($growth['peak']['value']), $this->percent($this->share($growth['peak']['value'], $records)));
        }
        if ($classification !== []) {
            $highlights[] = sprintf('%s of records are %s.', $this->percent($classification[0]['percent']), strtolower($classification[0]['label']));
        }

        $quality = $report['quality'];
        if ($quality['processed'] > 0) {
            $highlights[] = sprintf('%s of %s processed import rows were accepted; %s rejected and %s flagged as duplicates.', $this->percent($quality['accepted_rate']), number_format((int) $quality['processed']), number_format((int) $quality['rejected']), number_format((int) $quality['duplicates']));
        }
        if ($sources !== []) {
            $highlights[] = sprintf('%s supplied %s of records.', $sources[0]['label'], $this->percent($sources[0]['percent']));
        }
        if ($countries !== []) {
            $highlights[] = sprintf('%s accounts for %s of records across %s countries.', $countries[0]['label'], $this->percent($countries[0]['percent']), number_format((int) $report['overview']['countries']));
        }

        $companies = $report['company_analysis'];
        if ($companies['average_contacts'] !== null) {
            $highlights[] = sprintf('%s companies average %s contacts each; %s have more than one contact.', number_format((int) $report['overview']['companies']), $companies['average_contacts'], number_format($companies['multiple_contacts']));
        }

        return $highlights;
    }

    /**
     * Proportional segments for a 100% stacked bar, largest first.
     *
     * @param  list<array{label: string, value: int, color?: string}>  $items
     * @return list<array{label: string, value: int, percent: float, color: string}>
     */
    private function segments(array $items): array
    {
        $total = array_sum(array_column($items, 'value'));
        if ($total === 0) {
            return [];
        }

        $segments = [];
        foreach ($items as $index => $item) {
            if ($item['value'] > 0) {
                $segments[] = [
                    'label' => $item['label'],
                    'value' => $item['value'],
                    'percent' => round(100 * $item['value'] / $total, 1),
                    'color' => $item['color'] ?? self::PALETTE[$index % count(self::PALETTE)],
                ];
            }
        }

        return $segments;
    }

    /**
     * Ranked horizontal bars; width is relative to the largest bar and
     * percent is the share of the given total.
     *
     * @param  list<array{label: string, value: int}>  $items
     * @return list<array{label: string, value: int, percent: float, width: float}>
     */
    private function bars(array $items, int $total): array
    {
        $max = max([0, ...array_column($items, 'value')]);

        return array_map(fn (array $item): array => [
            'label' => $item['label'],
            'value' => $item['value'],
            'percent' => $this->share($item['value'], $total) ?? 0.0,
            'width' => $max > 0 ? round(100 * $item['value'] / $max, 1) : 0.0,
        ], $items);
    }

    /**
     * Column chart points, merging consecutive points so long ranges stay legible.
     *
     * @param  list<array{date: string, value: int}>  $points
     * @return array{columns: list<array{label: string, value: int, height: float, show_label: bool}>, total: int, max: int, average: float, peak: array{label: string, value: int}|null, bucket: string}
     */
    private function columns(array $points, string $granularity): array
    {
        $size = max(1, (int) ceil(count($points) / self::MAX_COLUMNS));
        $buckets = array_map(fn (array $chunk): array => [
            'label' => $this->dateLabel($chunk[0]['date'], $granularity),
            'value' => array_sum(array_column($chunk, 'value')),
        ], array_chunk($points, $size));

        $max = max([0, ...array_column($buckets, 'value')]);
        $every = (int) max(1, ceil(count($buckets) / 8));
        $peak = null;
        foreach ($buckets as $bucket) {
            if ($bucket['value'] > 0 && ($peak === null || $bucket['value'] > $peak['value'])) {
                $peak = $bucket;
            }
        }
        $total = array_sum(array_column($buckets, 'value'));

        return [
            'columns' => array_map(fn (array $bucket, int $index): array => [
                ...$bucket,
                'height' => $max > 0 ? round(100 * $bucket['value'] / $max, 1) : 0.0,
                'show_label' => $index % $every === 0,
            ], $buckets, array_keys($buckets)),
            'total' => $total,
            'max' => $max,
            'average' => $buckets === [] ? 0.0 : round($total / count($buckets), 1),
            'peak' => $peak,
            'bucket' => $size === 1 ? $granularity : "{$size}-{$granularity} span",
        ];
    }

    /**
     * Stacked import-outcome columns per period, scaled to the busiest one.
     *
     * @param  list<array<string, mixed>>  $points
     * @return array{columns: list<array{label: string, processed: int, show_label: bool, stack: list<array{color: string, height: float}>}>, max: int}
     */
    private function qualityColumns(array $points, string $granularity): array
    {
        $size = max(1, (int) ceil(count($points) / self::MAX_COLUMNS));
        $keys = ['errors' => '#64748b', 'rejected' => '#f43f5e', 'skipped' => '#f59e0b', 'accepted' => '#10b981'];
        $buckets = array_map(function (array $chunk) use ($granularity): array {
            $bucket = ['label' => $this->dateLabel($chunk[0]['date'], $granularity)];
            foreach (['processed', 'accepted', 'rejected', 'errors'] as $key) {
                $bucket[$key] = array_sum(array_column($chunk, $key));
            }
            $bucket['skipped'] = $this->skippedDuplicates($bucket);

            return $bucket;
        }, array_chunk($points, $size));

        $max = max([0, ...array_column($buckets, 'processed')]);
        $every = (int) max(1, ceil(count($buckets) / 8));

        return [
            'columns' => array_map(fn (array $bucket, int $index): array => [
                'label' => $bucket['label'],
                'processed' => $bucket['processed'],
                'show_label' => $index % $every === 0,
                'stack' => array_values(array_filter(array_map(
                    fn (string $key, string $color): array => ['color' => $color, 'height' => $max > 0 ? round(100 * $bucket[$key] / $max, 1) : 0.0],
                    array_keys($keys), $keys,
                ), fn (array $part): bool => $part['height'] > 0)),
            ], $buckets, array_keys($buckets)),
            'max' => $max,
        ];
    }

    /**
     * Rows whose status is duplicate. The duplicates metric also counts
     * accepted rows flagged as possible duplicates, so it overlaps accepted;
     * processing statuses are disjoint, which a stacked bar needs.
     *
     * @param  array<string, mixed>  $counts
     */
    private function skippedDuplicates(array $counts): int
    {
        return max(0, (int) $counts['processed'] - (int) $counts['accepted'] - (int) $counts['rejected'] - (int) $counts['errors']);
    }

    private function labelOf(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    private function dateLabel(string $date, string $granularity): string
    {
        return CarbonImmutable::parse($date)->format($granularity === 'month' ? 'M Y' : 'M j');
    }

    private function share(int $value, int $total): ?float
    {
        return $total > 0 ? round(100 * $value / $total, 1) : null;
    }

    private function percent(?float $value): string
    {
        return $value === null ? 'N/A' : $value.'%';
    }
}
