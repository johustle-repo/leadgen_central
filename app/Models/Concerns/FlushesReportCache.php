<?php

namespace App\Models\Concerns;

use App\Support\ReportCache;

/**
 * Keeps the lead reports current: saving, deleting or restoring a record the
 * reports read marks their cached results stale.
 */
trait FlushesReportCache
{
    protected static function bootFlushesReportCache(): void
    {
        foreach (['saved', 'deleted', 'restored', 'forceDeleted'] as $event) {
            static::registerModelEvent($event, fn () => ReportCache::flush());
        }
    }
}
