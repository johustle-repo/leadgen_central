<?php

namespace App\Services;

use App\Models\DuplicateLog;
use App\Models\DuplicateMatch;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\UploadBatchStatus;
use App\UploadRowStatus;
use Illuminate\Support\Facades\DB;

class UploadBatchReanalyzer
{
    public function prepare(UploadBatch $uploadBatch): int
    {
        return DB::transaction(function () use ($uploadBatch): int {
            $batch = UploadBatch::query()->lockForUpdate()->findOrFail($uploadBatch->id);
            $rows = $batch->rows()
                ->where(function ($query): void {
                    $query->where('processing_status', UploadRowStatus::Duplicate)
                        ->orWhereIn('error_category', ['exact_duplicate', 'possible_duplicate']);
                })
                ->get();

            if ($rows->isEmpty()) {
                return 0;
            }

            $rowIds = $rows->modelKeys();
            $leadIds = $rows->where('error_category', 'possible_duplicate')->pluck('lead_id')->filter()->all();

            DuplicateLog::query()->whereIn('upload_row_id', $rowIds)->delete();
            DuplicateMatch::query()->whereIn('upload_row_id', $rowIds)->delete();
            Lead::query()->where('upload_batch_id', $batch->id)->whereKey($leadIds)->delete();
            $batch->rows()->whereKey($rowIds)->update([
                'processed_data' => null,
                'processing_status' => UploadRowStatus::Pending,
                'error_category' => null,
                'error_message' => null,
                'lead_id' => null,
                'duplicate_match_id' => null,
            ]);
            $batch->update([
                'duplicate_rows' => 0,
                'exact_duplicate_rows' => 0,
                'possible_duplicate_rows' => 0,
                'processing_status' => UploadBatchStatus::Pending,
                'failure_message' => null,
                'completed_at' => null,
            ]);

            return $rows->count();
        });
    }
}
