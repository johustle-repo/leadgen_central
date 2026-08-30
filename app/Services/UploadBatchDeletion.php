<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadBatchDeletion
{
    public function delete(UploadBatch $uploadBatch, User $actor, ?string $ipAddress, ?string $userAgent): void
    {
        $storedFilename = $uploadBatch->stored_filename;

        DB::transaction(function () use ($uploadBatch, $actor, $ipAddress, $userAgent): void {
            $uploadBatch->loadMissing('user:id,name,email');
            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'upload_batch.deleted',
                'auditable_type' => 'upload_batch',
                'auditable_id' => $uploadBatch->id,
                'description' => "Deleted upload {$uploadBatch->batch_code} ({$uploadBatch->original_filename}).",
                'metadata' => [
                    'batch_code' => $uploadBatch->batch_code,
                    'original_filename' => $uploadBatch->original_filename,
                    'owner_id' => $uploadBatch->user_id,
                    'owner_name' => $uploadBatch->user->name,
                    'owner_email' => $uploadBatch->user->email,
                    'total_rows' => $uploadBatch->total_rows,
                    'accepted_rows' => $uploadBatch->accepted_rows,
                    'rejected_rows' => $uploadBatch->rejected_rows,
                    'duplicate_rows' => $uploadBatch->duplicate_rows,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
            $uploadBatch->delete();
        });

        Storage::disk('local')->delete($storedFilename);
    }
}
