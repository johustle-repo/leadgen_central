<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Versioned cache keys for the lead reports. Every report result is cached
 * under the current data version, and any change to the data they read bumps
 * the version, so the next report load rebuilds instead of serving figures
 * from before the change.
 */
class ReportCache
{
    private const VERSION_KEY = 'reports:data-version';

    /** Nesting depth of deferWhile() calls in this process. */
    private static int $deferred = 0;

    private static bool $pendingFlush = false;

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function key(string $report, string $scope, array $filters): string
    {
        ksort($filters);

        return "{$report}:".self::version().":{$scope}:".now()->toDateString().':'.md5(serialize($filters));
    }

    /**
     * How long a report stays cached. Any data change already invalidates it
     * and the key changes daily, so this only bounds how long an unused entry
     * lingers in the cache store.
     */
    public static function ttl(): CarbonInterface
    {
        return now()->addHours(12);
    }

    /**
     * The current report data version; open report pages compare it with the
     * one they were rendered with to know when to reload.
     */
    public static function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn (): string => (string) Str::uuid());
    }

    /**
     * Marks every cached report stale once the current transaction commits,
     * so a report built mid-transaction cannot be cached as the new version.
     */
    public static function flush(): void
    {
        if (self::$deferred > 0) {
            self::$pendingFlush = true;

            return;
        }

        DB::afterCommit(fn () => Cache::forever(self::VERSION_KEY, (string) Str::uuid()));
    }

    /**
     * Runs a bulk write (such as a CSV import) with a single flush at the end
     * instead of one per changed record.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public static function deferWhile(callable $callback): mixed
    {
        self::$deferred++;

        try {
            return $callback();
        } finally {
            self::$deferred--;
            if (self::$deferred === 0 && self::$pendingFlush) {
                self::$pendingFlush = false;
                self::flush();
            }
        }
    }
}
