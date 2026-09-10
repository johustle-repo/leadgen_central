<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class DatabaseIntelligenceReport
{
    private const COMPANY = "COALESCE(NULLIF(LOWER(TRIM(normalized_company_name)), ''), NULLIF(LOWER(TRIM(company_name)), ''))";

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function for(User $user, array $filters): array
    {
        $dashboard = app(DashboardReport::class)->for($user, $filters);
        $data = $dashboard['databaseAnalytics'];
        $from = CarbonImmutable::parse($dashboard['filters']['date_from']);
        $until = CarbonImmutable::parse($dashboard['filters']['date_to'])->addDay();
        $leads = Lead::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user, 'agent'))
            ->where('leads.created_at', '>=', $from)->where('leads.created_at', '<', $until);
        $batches = UploadBatch::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user))
            ->where('created_at', '>=', $from)->where('created_at', '<', $until);
        $rows = UploadRow::query()->whereIn('upload_batch_id', (clone $batches)->select('id'))->toBase();
        $companyGroups = (clone $leads)->whereRaw(self::COMPANY.' IS NOT NULL')
            ->selectRaw(self::COMPANY.' as company, COUNT(*) as contacts')->groupBy('company')->toBase();
        $companySummary = DB::query()->fromSub($companyGroups, 'companies')->selectRaw('COUNT(*) as companies, COALESCE(SUM(contacts), 0) as contacts, COUNT(CASE WHEN contacts = 1 THEN 1 END) as single_contact, COUNT(CASE WHEN contacts > 1 THEN 1 END) as multiple_contacts')->first();
        $data['company_analysis'] = [
            'single_contact' => (int) $companySummary->single_contact,
            'multiple_contacts' => (int) $companySummary->multiple_contacts,
            'average_contacts' => $companySummary->companies > 0 ? round($companySummary->contacts / $companySummary->companies, 2) : null,
            'unnamed_records' => $data['overview']['records'] - (int) $companySummary->contacts,
        ];
        $source = $this->sourceExpression('data_source', 'source');
        $sourceCounts = (clone $leads)->selectRaw("{$source} as label, COUNT(*) as records")->groupBy('label')->toBase()->get()->keyBy('label');
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $snapshot = $sqlite ? "json_extract(processed_data, '$.data_source')" : "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(processed_data, '$.data_source')), 'null')";
        $rowSource = $this->sourceExpression($snapshot);
        $sourceQuality = $this->rowMetrics($rows)->selectRaw("{$rowSource} as label")->groupBy('label')->get()->keyBy('label');
        $data['source_quality'] = [];
        $data['distributions']['sources'] = [];
        foreach (['Tendata', 'Lusha', 'Manual', 'Email', 'Other', 'Unknown'] as $label) {
            $records = (int) ($sourceCounts->get($label)->records ?? 0);
            $metrics = $this->metrics($sourceQuality->get($label));
            $data['source_quality'][] = ['label' => $label, 'records' => $records, ...$metrics];
            if ($records > 0) {
                $data['distributions']['sources'][] = ['label' => $label, 'value' => $records, 'percent' => $this->rate($records, $data['overview']['records'])];
            }
        }
        $data['quality'] = [...$data['quality'], ...$this->metrics($this->rowMetrics($rows)->first())];
        $data['quality']['submitted_rows'] = (int) (clone $batches)->sum('total_rows');
        $data['quality']['average_batch_size'] = $data['overview']['uploads'] > 0 ? round($data['quality']['submitted_rows'] / $data['overview']['uploads'], 1) : null;
        $data['quality_trend'] = $this->qualityTrend($rows, $from, $until, $filters['granularity'] ?? 'day');
        $knownIndustry = (clone $leads)->whereNotNull('industry')->whereRaw("LOWER(TRIM(industry)) NOT IN ('', 'unknown', 'n/a')")->count();
        $data['industry_coverage'] = $this->rate($knownIndustry, $data['overview']['records']);
        $data['show_industries'] = $knownIndustry > 0 && $data['industry_coverage'] >= 20;
        $data['geographic_detail'] = $this->geography($leads, $filters);
        $data['contribution'] = $user->isAdministrator() ? $this->contribution($leads, $batches, $rows) : [];

        return $data;
    }

    /** @param array<string, mixed> $data
     * @return array<string, array<int, array<mixed>>>
     */
    public function exportSections(array $data): array
    {
        $quality = $data['quality'];
        $rate = fn (?float $value): string => $value === null ? 'N/A' : $value.'%';
        $sections = [
            'Database summary - selected period' => [['Metric', 'Value'], ['Records added', $data['overview']['records']], ['Unique companies', $data['overview']['companies']], ['Unique emails', $data['overview']['emails']], ['Duplicates detected', $quality['duplicates']], ['Countries represented', $data['overview']['countries']], ['Raw data sources', $data['overview']['sources']], ['Upload batches', $data['overview']['uploads']], ['Import rows with issues', $quality['issues']]],
            'Database totals - all time' => [['Metric', 'Value'], ['Total records', $data['all_time']['records']], ['Unique companies', $data['all_time']['companies']], ['Unique emails', $data['all_time']['emails']]],
            'Database growth - selected period' => [['Period start', 'Records added', 'First-seen companies', 'First-seen emails'], ...array_map(fn (array $point): array => [$point['date'], $point['records'], $point['companies'], $point['emails']], $data['growth']['points'])],
            'Database classification distribution' => [['Classification', 'Records', 'Percent'], ...array_map(fn (array $row): array => [$row['label'], $row['value'], $rate($row['percent'])], $data['distributions']['statuses'])],
            'Company and contact analysis' => [['Metric', 'Value'], ['Average contact records per company', $data['company_analysis']['average_contacts'] ?? 'N/A'], ['Companies with one contact', $data['company_analysis']['single_contact']], ['Companies with multiple contacts', $data['company_analysis']['multiple_contacts']]],
            'Top companies by contact records' => [['Company', 'Contact records', 'Unique emails'], ...array_map(fn (array $row): array => [$row['label'], $row['contacts'], $row['emails']], $data['companies'])],
            'Upload quality - selected-period batches' => [['Metric', 'Value'], ['Submitted rows (batch totals)', $quality['submitted_rows']], ['Observed rows', $quality['observed_rows']], ['Processed rows', $quality['processed']], ['Accepted including review', $quality['accepted']], ['Needs review', $quality['needs_review']], ['Duplicates', $quality['duplicates']], ['Rejected', $quality['rejected']], ['Processing errors', $quality['errors']], ['Location issues', $quality['location_issues']], ['Average batch size', $quality['average_batch_size'] ?? 'N/A'], ['Acceptance rate', $rate($quality['accepted_rate'])], ['Duplicate rate', $rate($quality['duplicates_rate'])], ['Rejection rate', $rate($quality['rejected_rate'])], ['Error rate', $rate($quality['errors_rate'])]],
            'Source quality - selected period' => [['Source', 'Records', 'Processed rows', 'Accepted rows', 'Duplicate rate', 'Rejection rate', 'Error rate'], ...array_map(fn (array $row): array => [$row['label'], $row['records'], $row['processed'], $row['accepted'], $rate($row['duplicates_rate']), $rate($row['rejected_rate']), $rate($row['errors_rate'])], $data['source_quality'])],
            'Geographic analysis - selected period and location filters' => [['Country code or label', 'State / province', 'City as stored', 'Timezone', 'Records'], ...array_map(fn (array $row): array => [$row['country'], $row['province'], $row['city'], $row['timezone'], $row['records']], $data['geographic_detail']['rows'])],
            'Metric definitions' => [['Definition'], ['Period metrics exclude soft-deleted leads. Emails are addresses, not verified people. Repeated company names are not automatically duplicates.'], ['Quality categories overlap. Rates exclude pending rows. Source outcomes use import snapshots; missing snapshots are Unknown. Current lead sources and import outcomes are distinct populations.'], ['Data quality rate is accepted rows without recorded issues divided by processed rows. Location flags may be incomplete. Geography preserves historical labels.']],
        ];
        if ($data['can_compare_agents']) {
            $sections['Database contribution by agent - selected period'] = [['Agent', 'Records', 'Companies', 'Uploads', 'Avg batch', 'Duplicate rate', 'Rejection rate', 'Error rate', 'Quality rate'], ...array_map(fn (array $row): array => [$row['name'], $row['records'], $row['companies'], $row['uploads'], $row['average_batch_size'] ?? 'N/A', $rate($row['duplicates_rate']), $rate($row['rejected_rate']), $rate($row['errors_rate']), $rate($row['clean_rate'])], $data['contribution'])];
        }

        return $sections;
    }

    /**
     * @param  literal-string  $column
     * @param  literal-string|null  $entryMethod
     * @return literal-string
     */
    private function sourceExpression(string $column, ?string $entryMethod = null): string
    {
        $manual = $entryMethod === null ? '' : "WHEN NULLIF(TRIM({$column}), '') IS NULL AND {$entryMethod} = 'manual' THEN 'Manual'";

        return "CASE WHEN LOWER(TRIM({$column})) = 'tendata' THEN 'Tendata'
            WHEN LOWER(TRIM({$column})) = 'lusha' THEN 'Lusha'
            WHEN LOWER(TRIM({$column})) IN ('manual', 'manual entry') THEN 'Manual'
            WHEN LOWER(TRIM({$column})) IN ('email', 'email reply', 'email outreach') THEN 'Email'
            {$manual} WHEN NULLIF(TRIM({$column}), '') IS NULL OR LOWER(TRIM({$column})) IN ('unknown', 'n/a') THEN 'Unknown' ELSE 'Other' END";
    }

    private function rowMetrics(QueryBuilder $rows): QueryBuilder
    {
        return (clone $rows)->selectRaw("COUNT(*) as observed_rows,
            COUNT(CASE WHEN processing_status != 'pending' THEN 1 END) as processed,
            COUNT(CASE WHEN processing_status IN ('accepted', 'needs_review') THEN 1 END) as accepted,
            COUNT(CASE WHEN processing_status = 'needs_review' THEN 1 END) as needs_review,
            COUNT(CASE WHEN processing_status != 'pending' AND (processing_status = 'duplicate' OR error_category = 'possible_duplicate') THEN 1 END) as duplicates,
            COUNT(CASE WHEN processing_status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN processing_status = 'error' THEN 1 END) as errors,
            COUNT(CASE WHEN processing_status != 'pending' AND error_category = 'location' THEN 1 END) as location_issues,
            COUNT(CASE WHEN processing_status != 'pending' AND (processing_status IN ('needs_review', 'duplicate', 'rejected', 'error') OR error_category IS NOT NULL) THEN 1 END) as issues,
            COUNT(CASE WHEN processing_status = 'accepted' AND error_category IS NULL THEN 1 END) as clean");
    }

    /** @return array<string, int|float|null> */
    private function metrics(?object $row): array
    {
        $result = [];
        foreach (['observed_rows', 'processed', 'accepted', 'needs_review', 'duplicates', 'rejected', 'errors', 'location_issues', 'issues', 'clean'] as $key) {
            $result[$key] = (int) ($row->{$key} ?? 0);
        }
        foreach (['accepted', 'duplicates', 'rejected', 'errors', 'location_issues', 'clean'] as $key) {
            $result[$key.'_rate'] = $this->rate($result[$key], $result['processed']);
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function qualityTrend(QueryBuilder $rows, CarbonImmutable $from, CarbonImmutable $until, string $granularity): array
    {
        $events = (clone $rows)->join('upload_batches as batches', 'batches.id', '=', 'upload_rows.upload_batch_id')
            ->select('upload_rows.processing_status', 'upload_rows.error_category')->selectRaw('batches.created_at as event_at');
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $bucket = match ($granularity) {
            'week' => $sqlite ? "DATE(event_at, '-' || ((CAST(strftime('%w', event_at) AS INTEGER) + 6) % 7) || ' days')" : 'DATE_SUB(DATE(event_at), INTERVAL WEEKDAY(event_at) DAY)',
            'month' => $sqlite ? "strftime('%Y-%m-01', event_at)" : "DATE_FORMAT(event_at, '%Y-%m-01')",
            default => 'DATE(event_at)',
        };
        $counts = $this->rowMetrics(DB::query()->fromSub($events, 'events'))->selectRaw("{$bucket} as bucket")->groupBy('bucket')->get()->keyBy('bucket');
        $start = match ($granularity) { 'week' => $from->startOfWeek(), 'month' => $from->startOfMonth(), default => $from };
        $step = match ($granularity) { 'week' => 'addWeek', 'month' => 'addMonth', default => 'addDay' };
        $points = [];
        for ($date = $start; $date->lt($until); $date = $date->{$step}()) {
            $points[] = ['date' => $date->toDateString(), ...$this->metrics($counts->get($date->toDateString()))];
        }

        return $points;
    }

    /** @param Builder<Lead> $leads
     * @param Builder<UploadBatch> $batches
     * @return array<int, array<string, mixed>>
     */
    private function contribution(Builder $leads, Builder $batches, QueryBuilder $rows): array
    {
        $leadCounts = (clone $leads)->selectRaw('agent_id, COUNT(*) as records, COUNT(DISTINCT '.self::COMPANY.') as companies')->groupBy('agent_id');
        $batchCounts = (clone $batches)->selectRaw('user_id, COUNT(*) as uploads, SUM(total_rows) as submitted_rows')->groupBy('user_id');
        $events = (clone $rows)->join('upload_batches as batches', 'batches.id', '=', 'upload_rows.upload_batch_id')
            ->select('upload_rows.processing_status', 'upload_rows.error_category', 'batches.user_id');
        $rowCounts = $this->rowMetrics(DB::query()->fromSub($events, 'events'))->addSelect('user_id')->groupBy('user_id');
        $query = User::withTrashed()->where('role', UserRole::Agent)->select('users.id', 'users.name')
            ->leftJoinSub($leadCounts, 'lead_counts', 'lead_counts.agent_id', '=', 'users.id')
            ->leftJoinSub($batchCounts, 'batch_counts', 'batch_counts.user_id', '=', 'users.id')
            ->leftJoinSub($rowCounts, 'row_counts', 'row_counts.user_id', '=', 'users.id');
        foreach (['records', 'companies'] as $key) {
            $query->selectRaw("COALESCE(lead_counts.{$key}, 0) as {$key}");
        }
        foreach (['uploads', 'submitted_rows'] as $key) {
            $query->selectRaw("COALESCE(batch_counts.{$key}, 0) as {$key}");
        }
        foreach (['observed_rows', 'processed', 'accepted', 'needs_review', 'duplicates', 'rejected', 'errors', 'location_issues', 'issues', 'clean'] as $key) {
            $query->selectRaw("COALESCE(row_counts.{$key}, 0) as {$key}");
        }

        return $query->orderByDesc('records')->orderBy('users.id')->limit(20)->toBase()->get()->map(fn (object $row): array => [
            'id' => (int) $row->id, 'name' => $row->name, 'records' => (int) $row->records, 'companies' => (int) $row->companies, 'uploads' => (int) $row->uploads,
            'average_batch_size' => $row->uploads > 0 ? round($row->submitted_rows / $row->uploads, 1) : null, ...$this->metrics($row),
        ])->all();
    }

    /** @param Builder<Lead> $leads
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function geography(Builder $leads, array $filters): array
    {
        $projection = (clone $leads)->selectRaw("COALESCE(NULLIF(UPPER(TRIM(country_code)), ''), NULLIF(LOWER(TRIM(country)), ''), 'Unknown') as country,
            COALESCE(NULLIF(TRIM(state_province), ''), 'Unknown') as province,
            COALESCE(NULLIF(TRIM(city), ''), 'Unknown') as city,
            COALESCE(NULLIF(TRIM(timezone), ''), 'Unknown') as timezone")->toBase();
        $query = DB::query()->fromSub($projection, 'locations');
        $selection = [];
        foreach (['country', 'province', 'city'] as $key) {
            $selection['geo_'.$key] = $filters['geo_'.$key] ?? '';
            if ($selection['geo_'.$key] !== '') {
                $query->where('locations.'.$key, $selection['geo_'.$key]);
            }
        }
        $total = (clone $query)->count();
        $groups = $query->select('locations.country', 'locations.province', 'locations.city', 'locations.timezone')
            ->selectRaw('COUNT(*) as records')->groupBy('locations.country', 'locations.province', 'locations.city', 'locations.timezone')
            ->orderByDesc('records')->orderBy('country')->orderBy('province')->orderBy('city')->orderBy('timezone')->limit(50)->get()
            ->map(fn (object $row): array => [...(array) $row, 'records' => (int) $row->records])->all();

        return ['filters' => $selection, 'records' => $total, 'rows' => $groups];
    }

    private function rate(int $value, int $total): ?float
    {
        return $total > 0 ? round(100 * $value / $total, 1) : null;
    }
}
