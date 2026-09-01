<?php

namespace App\Jobs;

use App\Models\UploadBatch;
use App\Services\UploadBatchProcessor;
use App\UploadBatchStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessUploadBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

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
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        UploadBatch::query()->whereKey($this->uploadBatchId)->update(['processing_status' => UploadBatchStatus::Failed, 'failure_message' => $exception?->getMessage() ?? 'Unknown processing error.', 'completed_at' => now()]);
        Log::error('Lead upload batch failed.', ['upload_batch_id' => $this->uploadBatchId, 'error' => $exception?->getMessage()]);
    }
}
