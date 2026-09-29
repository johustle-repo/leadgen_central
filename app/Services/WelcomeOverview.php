<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\Support\ReportCache;
use App\UploadBatchStatus;
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
     * @return array<string, mixed>
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

        return collect(range(self::GROWTH_WEEKS, 1))->map(function (int $weeksAgo) use ($leads, $end): array {
            $from = $end->subWeeks($weeksAgo);

            return ['week_start' => $from->toDateString(), 'leads' => (clone $leads)->where('created_at', '>=', $from)->where('created_at', '<', $from->addWeek())->count()];
        })->values()->all();
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
        $batchEvents = (clone $batches)->latest('updated_at')->limit(self::ACTIVITY_LIMIT)->get(['id', 'processing_status', 'created_at', 'completed_at', 'updated_at'])
            ->map(fn (UploadBatch $batch): array => match ($batch->processing_status) {
                UploadBatchStatus::Completed => ['type' => 'batch', 'label' => 'CSV batch accepted', 'occurred_at' => ($batch->completed_at ?? $batch->updated_at)->toIso8601String()],
                UploadBatchStatus::Failed => ['type' => 'batch_failed', 'label' => 'CSV batch failed', 'occurred_at' => $batch->updated_at->toIso8601String()],
                default => ['type' => 'batch', 'label' => 'CSV batch uploaded', 'occurred_at' => $batch->created_at->toIso8601String()],
            });
        $leadEvents = (clone $leads)->latest()->limit(self::ACTIVITY_LIMIT)->pluck('created_at')
            ->map(fn ($createdAt): array => ['type' => 'lead', 'label' => 'New lead added', 'occurred_at' => $createdAt->toIso8601String()]);
        $verifiedEvents = (clone $leads)->whereNotNull('verified_at')->latest('verified_at')->limit(self::ACTIVITY_LIMIT)->pluck('verified_at')
            ->map(fn ($verifiedAt): array => ['type' => 'verified', 'label' => 'Lead verified', 'occurred_at' => $verifiedAt->toIso8601String()]);

        return $batchEvents->concat($leadEvents)->concat($verifiedEvents)
            ->sortByDesc('occurred_at')->take(self::ACTIVITY_LIMIT)->values()->all();
    }

    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(100 * ($current - $previous) / $previous, 1) : null;
    }
}
