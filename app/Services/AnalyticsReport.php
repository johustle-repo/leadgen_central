<?php

namespace App\Services;

use App\Models\EmailReply;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class AnalyticsReport
{
    /**
     * @param  array{period?: string, date_from?: string, date_to?: string}  $filters
     * @return array<string, mixed>
     */
    public function for(User $user, array $filters): array
    {
        [$from, $to, $period] = $this->dateRange($filters);
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();
        $leads = $this->leadQuery($user, $from, $to);
        $replies = $this->replyQuery($user, $from, $to);
        $current = $this->summary($leads, $replies, $user, $from, $to);
        $previous = $this->summary($this->leadQuery($user, $previousFrom, $previousTo), $this->replyQuery($user, $previousFrom, $previousTo), $user, $previousFrom, $previousTo);

        return [
            'period' => $period,
            'filters' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'summary' => [...$current, 'lead_change' => $this->change($current['total_leads'], $previous['total_leads']), 'reply_change' => $this->change($current['replies'], $previous['replies'])],
            'dailyActivity' => $this->dailyActivity($leads, $replies, $from, $to),
            'leadStatuses' => $this->distribution($leads, 'status'),
            'sources' => $this->distribution($leads, 'data_source'),
            'countries' => $this->distribution($leads, 'country_code', 8),
            'replyClassifications' => $this->distribution($replies, 'classification'),
            'agentPerformance' => $user->isAdministrator() ? $this->agentPerformance($from, $to) : [],
        ];
    }

    /**
     * @param  array{period?: string, date_from?: string, date_to?: string}  $filters
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function dateRange(array $filters): array
    {
        $period = $filters['period'] ?? '30_days';
        $to = CarbonImmutable::today()->endOfDay();
        $from = match ($period) {
            '7_days' => $to->subDays(6)->startOfDay(),
            '90_days' => $to->subDays(89)->startOfDay(),
            'custom' => CarbonImmutable::parse($filters['date_from'] ?? throw new LogicException('A custom analytics start date is required.'))->startOfDay(),
            default => $to->subDays(29)->startOfDay(),
        };
        if ($period === 'custom') {
            $to = CarbonImmutable::parse($filters['date_to'] ?? throw new LogicException('A custom analytics end date is required.'))->endOfDay();
        }

        return [$from, $to, $period];
    }

    /** @return Builder<Lead> */
    private function leadQuery(User $user, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Lead::query()
            ->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user, 'agent'))
            ->whereBetween('created_at', [$from, $to]);
    }

    /** @return Builder<EmailReply> */
    private function replyQuery(User $user, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return EmailReply::query()
            ->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user, 'agent'))
            ->whereBetween('received_at', [$from, $to]);
    }

    /**
     * @param  Builder<Lead>  $leads
     * @param  Builder<EmailReply>  $replies
     * @return array{total_leads: int, qualified_leads: int, qualification_rate: float, replies: int, replied_leads: int, reply_rate: float, interested_replies: int, duplicates: int}
     */
    private function summary(Builder $leads, Builder $replies, User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $total = (clone $leads)->count();
        $qualified = (clone $leads)->where('status', 'qualified_lead')->count();
        $replyCount = (clone $replies)->count();
        $repliedLeads = (clone $replies)->whereNotNull('lead_id')->distinct()->count('lead_id');

        return [
            'total_leads' => $total,
            'qualified_leads' => $qualified,
            'qualification_rate' => $total > 0 ? round(($qualified / $total) * 100, 1) : 0.0,
            'replies' => $replyCount,
            'replied_leads' => $repliedLeads,
            'reply_rate' => $total > 0 ? round(($repliedLeads / $total) * 100, 1) : 0.0,
            'interested_replies' => (clone $replies)->whereIn('classification', ['interested', 'possible_lead'])->count(),
            'duplicates' => (int) UploadBatch::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user))->whereBetween('created_at', [$from, $to])->sum('duplicate_rows'),
        ];
    }

    /**
     * @param  Builder<Lead>  $leads
     * @param  Builder<EmailReply>  $replies
     * @return array<int, array{date: string, label: string, leads: int, replies: int}>
     */
    private function dailyActivity(Builder $leads, Builder $replies, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leadCounts = (clone $leads)->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')->groupBy('activity_date')->pluck('aggregate', 'activity_date');
        $replyCounts = (clone $replies)->selectRaw('DATE(received_at) as activity_date, COUNT(*) as aggregate')->groupBy('activity_date')->pluck('aggregate', 'activity_date');
        $activity = [];

        for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $activity[] = ['date' => $key, 'label' => $date->format('M j'), 'leads' => (int) ($leadCounts[$key] ?? 0), 'replies' => (int) ($replyCounts[$key] ?? 0)];
        }

        return $activity;
    }

    /**
     * @param  Builder<Lead>|Builder<EmailReply>  $query
     * @return array<int, array{label: string, value: int}>
     */
    private function distribution(Builder $query, string $column, int $limit = 10): array
    {
        $expression = match ($column) {
            'status' => "COALESCE(NULLIF(status, ''), 'Unknown') as label, COUNT(*) as aggregate",
            'data_source' => "COALESCE(NULLIF(data_source, ''), 'Unknown') as label, COUNT(*) as aggregate",
            'country_code' => "COALESCE(NULLIF(country_code, ''), 'Unknown') as label, COUNT(*) as aggregate",
            'classification' => "COALESCE(NULLIF(classification, ''), 'Unknown') as label, COUNT(*) as aggregate",
            default => throw new LogicException('Unsupported analytics distribution.'),
        };

        return (clone $query)
            ->selectRaw($expression)
            ->groupBy('label')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->getAttribute('label'), 'value' => (int) $row->getAttribute('aggregate')])
            ->all();
    }

    /** @return array<int, array<string, int|string|float>> */
    private function agentPerformance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return User::query()
            ->where('role', UserRole::Agent)
            ->withCount([
                'leads as leads_count' => fn (Builder $query) => $query->whereBetween('created_at', [$from, $to]),
                'leads as qualified_count' => fn (Builder $query) => $query->whereBetween('created_at', [$from, $to])->where('status', 'qualified_lead'),
                'emailReplies as replies_count' => fn (Builder $query) => $query->whereBetween('received_at', [$from, $to]),
                'emailReplies as interested_count' => fn (Builder $query) => $query->whereBetween('received_at', [$from, $to])->whereIn('classification', ['interested', 'possible_lead']),
            ])
            ->orderByDesc('leads_count')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(function (User $agent): array {
                $leads = (int) $agent->getAttribute('leads_count');
                $qualified = (int) $agent->getAttribute('qualified_count');

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'leads' => $leads,
                    'qualified' => $qualified,
                    'replies' => (int) $agent->getAttribute('replies_count'),
                    'interested' => (int) $agent->getAttribute('interested_count'),
                    'qualification_rate' => $leads > 0 ? round(($qualified / $leads) * 100, 1) : 0.0,
                ];
            })->all();
    }

    private function change(int $current, int $previous): float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100.0 : 0.0);
    }
}
