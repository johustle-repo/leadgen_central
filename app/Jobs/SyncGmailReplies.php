<?php

namespace App\Jobs;

use App\Models\GmailConnection;
use App\Services\GmailReplySynchronizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncGmailReplies implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $gmailConnectionId) {}

    /**
     * Execute the job.
     */
    public function handle(GmailReplySynchronizer $synchronizer): void
    {
        $connection = GmailConnection::query()->where('status', 'active')->find($this->gmailConnectionId);
        if ($connection !== null) {
            $synchronizer->sync($connection);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->gmailConnectionId;
    }

    public function failed(?Throwable $exception): void
    {
        GmailConnection::query()->whereKey($this->gmailConnectionId)->update([
            'status' => 'error',
            'last_error' => $exception?->getMessage() ?? 'Gmail synchronization failed.',
        ]);
    }
}
