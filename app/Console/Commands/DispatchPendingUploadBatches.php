<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUploadBatch;
use App\Models\UploadBatch;
use App\UploadBatchStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('uploads:dispatch-pending')]
#[Description('Restore missing queue jobs for pending lead uploads')]
class DispatchPendingUploadBatches extends Command
{
    public function handle(): int
    {
        $batches = UploadBatch::query()
            ->where('processing_status', UploadBatchStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->oldest()
            ->limit(100)
            ->get()
            ->filter(fn (UploadBatch $batch): bool => in_array('company_name', $batch->column_mapping ?? [], true));

        $batches->each(fn (UploadBatch $batch) => ProcessUploadBatch::dispatch($batch->id));

        $this->info("Dispatched {$batches->count()} pending upload batch(es).");

        return self::SUCCESS;
    }
}
