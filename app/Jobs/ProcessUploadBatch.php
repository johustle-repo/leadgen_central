<?php

namespace App\Jobs;

use App\Models\UploadBatch;
use App\Services\UploadBatchProcessor;
use App\UploadBatchStatus;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessUploadBatch implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    /** CSV imports may contain many files and must be allowed to finish. */
    public int $timeout = 0;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $uploadBatchId)
    {
        //
    }

    /**
     * Execute the job.
     */
    /** @var list<int> */
    public array $backoff = [1, 5, 15];

    public function uniqueId(): string
    {
        return (string) $this->uploadBatchId;
    }

    public function handle(UploadBatchProcessor $processor): void
    {
        $batch = UploadBatch::with('user')->findOrFail($this->uploadBatchId);
        if ($batch->processing_status === UploadBatchStatus::Completed) {
            return;
        }
        try {
            $processor->process($batch);
        } catch (Throwable $exception) {
            $batch->update(['processing_status' => UploadBatchStatus::Failed, 'failure_message' => $exception->getMessage(), 'completed_at' => now()]);
            report($exception);
            // Deliberately not rethrown: the failure is already recorded on the
            // batch above, so there's nothing left for Laravel's own retry/backoff
            // to accomplish - it would only repeat the same failure (e.g. a missing
            // stored file) up to `$tries` times. On the constrained cron-driven
            // worker this project runs on shared hosting (see DEPLOYMENT.md), those
            // extra attempts cost real time out of the once-a-minute processing
            // window. A user can still explicitly retry via Re-analyze.
        }
    }

    public function failed(?Throwable $exception): void
    {
        UploadBatch::query()->whereKey($this->uploadBatchId)->update(['processing_status' => UploadBatchStatus::Failed, 'failure_message' => $exception?->getMessage() ?? 'Unknown processing error.', 'completed_at' => now()]);
        Log::error('Lead upload batch failed.', ['upload_batch_id' => $this->uploadBatchId, 'error' => $exception?->getMessage()]);
    }
}
