<?php

namespace App\Jobs;

use App\Exceptions\GmailReauthorizationRequiredException;
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

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $gmailConnectionId) {}

    /**
     * Execute the job.
     */
    public function handle(GmailReplySynchronizer $synchronizer): void
    {
        // Looked up by id alone (not scoped to status = active) so a manual
        // "Sync" click on a previously failed connection genuinely retries
        // instead of silently doing nothing.
        $connection = GmailConnection::query()->find($this->gmailConnectionId);
        if ($connection === null) {
            return;
        }

        try {
            $synchronizer->sync($connection);
        } catch (GmailReauthorizationRequiredException $exception) {
            // Retrying won't help until the mailbox is reconnected, so stop
            // burning retry attempts and report it right away.
            $this->fail($exception);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->gmailConnectionId;
    }

    public function failed(?Throwable $exception): void
    {
        $isReauthRequired = $exception instanceof GmailReauthorizationRequiredException;
        GmailConnection::query()->whereKey($this->gmailConnectionId)->update([
            'status' => $isReauthRequired ? 'expired' : 'error',
            'last_error' => $isReauthRequired
                ? $exception->getMessage()
                : 'Gmail synchronization failed. Try again, or reconnect this account if the problem continues.',
        ]);
    }
}
