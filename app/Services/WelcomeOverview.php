<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\Support\ReportCache;
use App\UploadBatchStatus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The live "Lead intelligence overview" card on the welcome page, scoped the
 * same way as the dashboard: administrators see every lead, agents only their own.
 */
class WelcomeOverview
{
    private const GROWTH_WEEKS = 8;

    private const ACTIVITY_LIMIT = 3;

    /**
     * @return array{
     *     stats: array{total: int, total_change: float|null, validated: int, validated_rate: float|null, this_month: int, this_month_change: float|null},
     *     growth: list<array{week_start: string, leads: int}>,
     *     activity: list<array{type: string, label: string, occurred_at: string}>,
     * }
     */
    public function for(User $user): array
    {
        $scope = $user->canViewAllLeads() ? 'all' : "agent:{$user->id}";

        return Cache::remember(ReportCache::key('welcome-overview', $scope, []), ReportCache::ttl(), fn (): array => $this->build($user));
    }

    /**
     * @return array{
     *     stats: array{total: int, total_change: float|null, validated: int, validated_rate: float|null, this_month: int, this_month_change: float|null},
     *     growth: list<array{week_start: string, leads: int}>,
     *     activity: list<array{type: string, label: string, occurred_at: string}>,
     * }
     */
    private function build(User $user): array
    {
        $now = CarbonImmutable::now();
        $leads = Lead::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user, 'agent'));
        $batches = UploadBatch::query()->when(! $user->canViewAllLeads(), fn (Builder $query) => $query->whereBelongsTo($user));

        $total = (clone $leads)->count();
        $lastThirtyDays = (clone $leads)->where('created_at', '>=', $now->subDays(30))->count();
        $validated = (clone $leads)->whereIn('validation_status', ['validated', 'verified'])->count();
        $monthStart = $now->startOfMonth();
        $thisMonth = (clone $leads)->where('created_at', '>=', $monthStart)->count();
        $previousMonth = (clone $leads)->where('created_at', '>=', $monthStart->subMonth())->where('created_at', '<', $monthStart)->count();

        return [
            'stats' => [
                'total' => $total,
                'total_change' => $this->change($total, $total - $lastThirtyDays),
                'validated' => $validated,
                'validated_rate' => $total > 0 ? round(100 * $validated / $total, 1) : null,
                'this_month' => $thisMonth,
                'this_month_change' => $this->change($thisMonth, $previousMonth),
            ],
            'growth' => $this->growth($leads, $now),
            'activity' => $this->activity($leads, $batches),
        ];
    }

    /**
     * Leads created in each of the last eight 7-day windows, oldest first.
     *
     * @param  Builder<Lead>  $leads
     * @return list<array{week_start: string, leads: int}>
     */
    private function growth(Builder $leads, CarbonImmutable $now): array
    {
        $end = $now->addDay()->startOfDay();
        $weeks = [];
        for ($weeksAgo = self::GROWTH_WEEKS; $weeksAgo >= 1; $weeksAgo--) {
            $from = $end->subWeeks($weeksAgo);
            $weeks[] = ['week_start' => $from->toDateString(), 'leads' => (clone $leads)->where('created_at', '>=', $from)->where('created_at', '<', $from->addWeek())->count()];
        }

        return $weeks;
    }

    /**
     * The most recent import and lead events, without names or lead details.
     *
     * @param  Builder<Lead>  $leads
     * @param  Builder<UploadBatch>  $batches
     * @return list<array{type: string, label: string, occurred_at: string}>
     */
    private function activity(Builder $leads, Builder $batches): array
    {
        $events = [];
        foreach ((clone $batches)->latest('updated_at')->limit(self::ACTIVITY_LIMIT)->get(['id', 'processing_status', 'created_at', 'completed_at', 'updated_at']) as $batch) {
            $events[] = match ($batch->processing_status) {
                UploadBatchStatus::Completed => $this->event('batch', 'CSV batch accepted', $batch->completed_at ?? $batch->updated_at),
                UploadBatchStatus::Failed => $this->event('batch_failed', 'CSV batch failed', $batch->updated_at),
                default => $this->event('batch', 'CSV batch uploaded', $batch->created_at),
            };
        }
        foreach ((clone $leads)->latest()->limit(self::ACTIVITY_LIMIT)->get(['id', 'created_at']) as $lead) {
            $events[] = $this->event('lead', 'New lead added', $lead->created_at);
        }
        foreach ((clone $leads)->whereNotNull('verified_at')->latest('verified_at')->limit(self::ACTIVITY_LIMIT)->get(['id', 'verified_at']) as $lead) {
            $events[] = $this->event('verified', 'Lead verified', $lead->verified_at);
        }

        usort($events, fn (array $first, array $second): int => strcmp($second['occurred_at'], $first['occurred_at']));

        return array_slice($events, 0, self::ACTIVITY_LIMIT);
    }

    /**
     * @return array{type: string, label: string, occurred_at: string}
     */
    private function event(string $type, string $label, mixed $occurredAt): array
    {
        return ['type' => $type, 'label' => $label, 'occurred_at' => Carbon::parse($occurredAt)->toIso8601String()];
    }

    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(100 * ($current - $previous) / $previous, 1) : null;
    }
}
