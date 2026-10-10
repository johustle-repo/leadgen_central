<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Runs an on-demand backup requested from the Backups page.
 */
class CreateDatabaseBackup implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string PENDING_CACHE_KEY = 'database-backup:pending';

    public int $tries = 1;

    /** Large databases must be allowed to finish dumping. */
    public int $timeout = 0;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        try {
            Artisan::call('app:backup-database');
        } finally {
            Cache::forget(self::PENDING_CACHE_KEY);
        }
    }
}
