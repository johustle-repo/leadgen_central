<?php

namespace App\Services;

use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\UploadRow;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class DashboardReport
{
    private const COMPANY = "COALESCE(NULLIF(LOWER(TRIM(normalized_company_name)), ''), NULLIF(LOWER(TRIM(company_name)), ''))";

    private const EMAIL = "NULLIF(LOWER(TRIM(email)), '')";

    private const COUNTRY = "COALESCE(NULLIF(UPPER(TRIM(country_code)), ''), NULLIF(LOWER(TRIM(country)), ''))";

    /**
     * @param  array{period?: string|null, date_from?: string|null, date_to?: string|null, granularity?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function for(User $user, array $filters): array
    {
        [$from, $until, $period] = $this->period($filters);
        $previousFrom = $from->subDays((int) $from->diffInDays($until));
        $granularity = $filters['granularity'] ?? 'day';
        $allLeads = Lead::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user, 'agent'));
        $allBatches = UploadBatch::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user));
        $leads = $this->during($allLeads, $from, $until);
        $batches = $this->during($allBatches, $from, $until);
        $overview = $this->overview($leads);
        $previous = $this->overview($this->during($allLeads, $previousFrom, $from));
        $allTime = $this->overview($allLeads);
        $overview['uploads'] = (clone $batches)->count();
        $previous['uploads'] = $this->during($allBatches, $previousFrom, $from)->count();
        $allTime['uploads'] = (clone $allBatches)->count();
        $changes = [];
        foreach ($overview as $key => $value) {
            $changes[$key] = $this->change($value, $previous[$key]);
        }

        $rows = UploadRow::query()->whereIn('upload_batch_id', (clone $batches)->select('id'));
        $rowMetrics = $this->rowMetrics($rows)->toBase();
        $quality = $this->numericRow((clone $rowMetrics)->first());
        $quality['rows'] = $quality['observed_rows'];
        unset($quality['observed_rows']);
        $counts = $quality;
        $processed = $quality['processed'];
        foreach (['accepted', 'duplicates', 'rejected', 'errors'] as $key) {
            $quality[$key.'_rate'] = $this->rate($counts[$key], $processed);
        }
        $perBatch = (clone $rowMetrics)->addSelect('upload_batch_id')->groupBy('upload_batch_id');
        $averages = DB::query()->fromSub($perBatch, 'batch_quality')->where('processed', '>', 0)
            ->selectRaw('AVG(100.0 * accepted / processed) as acceptance, AVG(100.0 * duplicates / processed) as duplicates')->first();
        $quality['average_acceptance_rate'] = $averages->acceptance === null ? null : round((float) $averages->acceptance, 1);
        $quality['average_duplicate_rate'] = $averages->duplicates === null ? null : round((float) $averages->duplicates, 1);
        $quality['average_rows_per_upload'] = $overview['uploads'] > 0 ? round($quality['rows'] / $overview['uploads'], 1) : null;

        $distributions = [];
        foreach (['countries' => self::COUNTRY, 'industries' => "NULLIF(LOWER(TRIM(industry)), '')", 'sources' => "NULLIF(LOWER(TRIM(data_source)), '')", 'statuses' => 'status', 'entry_methods' => 'source',
            'provinces' => "NULLIF(LOWER(TRIM(state_province)), '')", 'cities' => "NULLIF(LOWER(TRIM(city)), '')", 'timezones' => "NULLIF(TRIM(timezone), '')"] as $key => $expression) {
            $distributions[$key] = $this->distribution($leads, $expression, $overview['records'], $key === 'statuses' ? 20 : 10);
        }
        $leadTable = (new Lead)->getTable();
        $userTable = (new User)->getTable();
        $owners = (clone $leads)->leftJoin($userTable.' as owners', 'owners.id', '=', $leadTable.'.agent_id');
        $distributions['owners'] = $this->distribution($owners, "COALESCE(owners.name, 'Deleted user')", $overview['records'], 10, $leadTable.'.agent_id');

        $companies = (clone $leads)->whereRaw(self::COMPANY.' IS NOT NULL')->selectRaw(self::COMPANY.' as company, COUNT(*) as contacts, COUNT(DISTINCT '.self::EMAIL.') as emails')
            ->groupBy('company')->orderByDesc('contacts')->orderBy('company')->limit(15)->toBase()->get();
        $missing = $this->missing($leads, $overview['records']);
        $geography = $this->geography($leads);
        $growth = $this->growth($allLeads, $from, $until, $previousFrom, $granularity);
        $contribution = $user->isAdministrator() ? $this->contribution($leads, $batches, $rows) : [];

        $legacyBatches = (clone $batches)->selectRaw('COALESCE(SUM(duplicate_rows), 0) as duplicates, COALESCE(SUM(invalid_rows + location_error_rows + error_rows), 0) as issues')->toBase()->first();
        $qualified = (int) (collect($distributions['statuses'])->firstWhere('label', 'qualified_lead')['value'] ?? 0);
        $stats = ['total_leads' => $overview['records'], 'unique_leads' => $overview['companies'], 'qualified_leads' => $qualified,
            'qualification_rate' => $this->rate($qualified, $overview['records']) ?? 0,
            'duplicates_flagged' => (int) $legacyBatches->duplicates, 'data_issues' => (int) $legacyBatches->issues];
        if ($user->isSuperAdministrator()) {
            $replyCounts = EmailReply::query()->selectRaw("COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread, COUNT(CASE WHEN classification IN ('interested', 'possible_lead') THEN 1 END) as possible")->toBase()->first();
            $stats['unread_replies'] = (int) $replyCounts->unread;
            $stats['possible_reply_leads'] = (int) $replyCounts->possible;
        }

        return [
            'stats' => $stats,
            'period' => $period,
            'filters' => ['date_from' => $from->toDateString(), 'date_to' => $until->subDay()->toDateString()],
            'databaseAnalytics' => [
                'overview' => $overview, 'previous' => $previous, 'changes' => $changes, 'all_time' => $allTime,
                'previous_period' => ['from' => $previousFrom->toDateString(), 'to' => $from->subDay()->toDateString()],
                'timezone' => config('app.timezone'), 'distributions' => $distributions, 'quality' => $quality,
                'missing' => $missing, 'growth' => $growth, 'geography' => $geography,
                'companies' => $companies->map(fn (object $row): array => ['label' => $row->company, 'contacts' => (int) $row->contacts, 'emails' => (int) $row->emails])->all(),
                'contribution' => $contribution, 'can_compare_agents' => $user->isAdministrator(),
            ],
            'recentBatches' => (clone $batches)->with(['user' => fn (Relation $query) => $query->getQuery()->withoutGlobalScope(SoftDeletingScope::class)->select('id', 'name')])->latest()->limit(5)
                ->get(['id', 'user_id', 'batch_code', 'original_filename', 'processing_status', 'created_at', 'total_rows']),
            'recentLeads' => (clone $leads)->with(['agent' => fn (Relation $query) => $query->getQuery()->withoutGlobalScope(SoftDeletingScope::class)->select('id', 'name')])->latest()->limit(5)
                ->get(['id', 'lead_code', 'agent_id', 'company_name', 'status', 'created_at']),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function period(array $filters): array
    {
        $today = CarbonImmutable::today();
        $period = $filters['period'] ?? 'month';
        [$from, $until] = match ($period) {
            'today' => [$today, $today->addDay()],
            'week' => [$today->startOfWeek(), $today->addDay()],
            'last_week' => [$today->startOfWeek()->subWeek(), $today->startOfWeek()],
            'last_month' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()],
            '30_days' => [$today->subDays(29), $today->addDay()],
            'quarter' => [$today->startOfQuarter(), $today->addDay()],
            'custom' => [CarbonImmutable::parse($filters['date_from']), CarbonImmutable::parse($filters['date_to'])->addDay()],
            default => [$today->startOfMonth(), $today->addDay()],
        };

        return [$from, $until, $period];
    }

    /** @template T of \Illuminate\Database\Eloquent\Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function during(Builder $query, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        $column = $query->getModel()->qualifyColumn('created_at');

        return (clone $query)->where($column, '>=', $from)->where($column, '<', $until);
    }

    /** @param Builder<Lead> $leads
     * @return array<string, int>
     */
    private function overview(Builder $leads): array
    {
        $query = (clone $leads)->selectRaw('COUNT(*) as records');
        foreach (['companies' => self::COMPANY, 'emails' => self::EMAIL, 'countries' => self::COUNTRY,
            'cities' => "NULLIF(LOWER(TRIM(city)), '')", 'provinces' => "NULLIF(LOWER(TRIM(state_province)), '')", 'sources' => "NULLIF(LOWER(TRIM(data_source)), '')"] as $key => $expression) {
            $query->selectRaw("COUNT(DISTINCT {$expression}) as {$key}");
        }

        return $this->numericRow($query->toBase()->first());
    }

    /** @param Builder<UploadRow> $rows
     * @return Builder<UploadRow>
     */
    private function rowMetrics(Builder $rows): Builder
    {
        $table = (new UploadRow)->getTable();

        return (clone $rows)->from($table.' as quality_rows')->selectRaw("COUNT(*) as observed_rows,
            COUNT(CASE WHEN quality_rows.processing_status != 'pending' THEN 1 END) as processed,
            COUNT(CASE WHEN quality_rows.processing_status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN quality_rows.processing_status IN ('accepted', 'needs_review') THEN 1 END) as accepted,
            COUNT(CASE WHEN quality_rows.processing_status = 'needs_review' THEN 1 END) as needs_review,
            COUNT(CASE WHEN quality_rows.processing_status = 'duplicate' OR quality_rows.error_category = 'possible_duplicate' THEN 1 END) as duplicates,
            COUNT(CASE WHEN quality_rows.processing_status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN quality_rows.processing_status = 'error' THEN 1 END) as errors,
            COUNT(CASE WHEN quality_rows.processing_status IN ('needs_review', 'rejected', 'error') THEN 1 END) as issues");
    }

    /** @param Builder<Lead> $query
     * @param  literal-string  $expression
     * @return list<array{label: string, value: int, percent: float|null}>
     */
    private function distribution(Builder $query, string $expression, int $total, int $limit = 10, ?string $identity = null): array
    {
        $groups = (clone $query)->selectRaw("COALESCE({$expression}, 'Unknown') as label, COUNT(*) as aggregate")
            ->groupBy('label')->when($identity, fn (Builder $builder) => $builder->groupBy($identity))
            ->orderByDesc('aggregate')->orderBy('label')->limit($limit)->toBase()->get()
            ->map(fn (object $row): array => ['label' => (string) $row->label, 'value' => (int) $row->aggregate, 'percent' => $this->rate((int) $row->aggregate, $total)])->all();
        $remaining = $total - array_sum(array_column($groups, 'value'));
        if ($remaining > 0) {
            $groups[] = ['label' => 'Other groups', 'value' => $remaining, 'percent' => $this->rate($remaining, $total)];
        }

        return array_values($groups);
    }

    /** @param Builder<Lead> $leads
     * @return list<array{label: string, value: int, percent: float|null}>
     */
    private function missing(Builder $leads, int $total): array
    {
        $fields = ['company_name', 'contact_person', 'email', 'phone', 'website', 'country', 'state_province', 'city', 'timezone', 'industry', 'data_source'];
        $query = clone $leads;
        foreach ($fields as $field) {
            $expression = $field === 'country' ? self::COUNTRY : "NULLIF(TRIM({$field}), '')";
            $query->selectRaw("COUNT(CASE WHEN {$expression} IS NULL THEN 1 END) as {$field}");
        }
        $counts = $this->numericRow($query->toBase()->first());

        return array_map(fn (string $field): array => ['label' => $field, 'value' => $counts[$field], 'percent' => $this->rate($counts[$field], $total)], $fields);
    }

    /** @param Builder<Lead> $leads
     * @return array<string, mixed>
     */
    private function geography(Builder $leads): array
    {
        $query = clone $leads;
        foreach (['country' => self::COUNTRY, 'province' => "NULLIF(TRIM(state_province), '')", 'city' => "NULLIF(TRIM(city), '')", 'timezone' => "NULLIF(TRIM(timezone), '')"] as $key => $expression) {
            $query->selectRaw("COALESCE({$expression}, 'Unknown') as {$key}");
        }
        $rows = DB::query()->fromSub($query->toBase(), 'locations')
            ->select('locations.country', 'locations.province', 'locations.city', 'locations.timezone')
            ->selectRaw('COUNT(*) as records')
            ->groupBy('locations.country', 'locations.province', 'locations.city', 'locations.timezone')
            ->orderByDesc('records')->orderBy('country')->orderBy('province')->orderBy('city')->orderBy('timezone')->limit(20)->get();
        $unverified = (clone $leads)->whereNotNull('city')->whereRaw("TRIM(city) != ''")->whereNull('canonical_city_id')->count();

        return ['rows' => $rows->map(fn (object $row): array => [...(array) $row, 'records' => (int) $row->records])->all(), 'unverified_city_records' => $unverified];
    }

    /** @param Builder<Lead> $allLeads
     * @return array<string, mixed>
     */
    private function growth(Builder $allLeads, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $previousFrom, string $granularity): array
    {
        $bucket = match ($granularity) {
            'week' => DB::connection()->getDriverName() === 'sqlite' ? "DATE(event_at, '-' || ((CAST(strftime('%w', event_at) AS INTEGER) + 6) % 7) || ' days')" : 'DATE_SUB(DATE(event_at), INTERVAL WEEKDAY(event_at) DAY)',
            'month' => DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m-01', event_at)" : "DATE_FORMAT(event_at, '%Y-%m-01')",
            default => 'DATE(event_at)',
        };
        $events = (clone $allLeads)->selectRaw('created_at as event_at')->toBase();
        $series = ['records' => $events];
        foreach (['companies' => self::COMPANY, 'emails' => self::EMAIL] as $key => $expression) {
            $series[$key] = (clone $allLeads)->whereRaw($expression.' IS NOT NULL')->selectRaw('MIN(created_at) as event_at')->groupByRaw($expression)->toBase();
        }
        $points = [];
        $step = match ($granularity) {
            'week' => 'addWeek', 'month' => 'addMonth', default => 'addDay'
        };
        $start = match ($granularity) {
            'week' => $from->startOfWeek(), 'month' => $from->startOfMonth(), default => $from
        };
        for ($date = $start; $date->lt($until); $date = $date->{$step}()) {
            $points[$date->toDateString()] = ['date' => $date->toDateString(), 'records' => 0, 'companies' => 0, 'emails' => 0];
        }
        $totals = [];
        foreach ($series as $key => $query) {
            $grouped = DB::query()->fromSub($query, 'events')->where('event_at', '>=', $from)->where('event_at', '<', $until)
                ->selectRaw($bucket.' as bucket, COUNT(*) as aggregate')->groupBy('bucket')->get();
            $totals[$key] = 0;
            foreach ($grouped as $row) {
                $points[$row->bucket][$key] = (int) $row->aggregate;
                $totals[$key] += (int) $row->aggregate;
            }
            $previous = DB::query()->fromSub($query, 'events')->where('event_at', '>=', $previousFrom)->where('event_at', '<', $from)->count();
            $totals[$key.'_change'] = $this->change($totals[$key], $previous);
        }

        return ['granularity' => $granularity, 'points' => array_values($points), 'totals' => $totals];
    }

    /** @param Builder<Lead> $leads
     * @param  Builder<UploadBatch>  $batches
     * @param  Builder<UploadRow>  $rows
     * @return list<array<string, int|string>>
     */
    private function contribution(Builder $leads, Builder $batches, Builder $rows): array
    {
        $leadCounts = (clone $leads)->selectRaw("agent_id, COUNT(*) as records, COUNT(CASE WHEN status = 'possible_lead' THEN 1 END) as possible, COUNT(CASE WHEN status = 'qualified_lead' THEN 1 END) as qualified, COUNT(CASE WHEN status = 'forwarded' THEN 1 END) as forwarded")->groupBy('agent_id');
        $batchCounts = (clone $batches)->selectRaw('user_id, COUNT(*) as uploads')->groupBy('user_id');
        $batchTable = (new UploadBatch)->getTable();
        $rowCounts = $this->rowMetrics($rows)->join($batchTable.' as batches', 'batches.id', '=', 'quality_rows.upload_batch_id')
            ->addSelect('batches.user_id')->groupBy('batches.user_id');
        $userTable = (new User)->getTable();
        $query = User::withTrashed()->where('role', UserRole::Agent)->select($userTable.'.id', $userTable.'.name')
            ->leftJoinSub($leadCounts, 'lead_counts', 'lead_counts.agent_id', '=', $userTable.'.id')
            ->leftJoinSub($batchCounts, 'batch_counts', 'batch_counts.user_id', '=', $userTable.'.id')
            ->leftJoinSub($rowCounts, 'row_counts', 'row_counts.user_id', '=', $userTable.'.id');
        foreach (['records', 'possible', 'qualified', 'forwarded'] as $field) {
            $query->selectRaw("COALESCE(lead_counts.{$field}, 0) as {$field}");
        }
        $query->selectRaw('COALESCE(batch_counts.uploads, 0) as uploads');
        foreach (['accepted', 'duplicates', 'rejected', 'errors', 'issues', 'processed'] as $field) {
            $query->selectRaw("COALESCE(row_counts.{$field}, 0) as {$field}");
        }

        return array_values($query->orderByDesc('records')->orderBy($userTable.'.id')->limit(20)->toBase()->get()->map(function (object $row): array {
            $values = $this->numericRow($row);
            $values['name'] = (string) $row->name;

            return $values;
        })->all());
    }

    /** @return array<string, int> */
    private function numericRow(object $row): array
    {
        return array_map(fn (mixed $value): int => (int) $value, (array) $row);
    }

    private function rate(int $value, int $total): ?float
    {
        return $total > 0 ? round(100 * $value / $total, 1) : null;
    }

    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(100 * ($current - $previous) / $previous, 1) : ($current === 0 ? 0.0 : null);
    }
}
