<?php

namespace App\Models;

use App\UploadRowStatus;
use Database\Factories\UploadRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property UploadRowStatus $processing_status
 * @property array<string, mixed> $raw_data
 */
class UploadRow extends Model
{
    /** @use HasFactory<UploadRowFactory> */
    use HasFactory;

    protected $fillable = ['upload_batch_id', 'row_number', 'raw_data', 'processed_data', 'processing_status', 'error_category', 'error_message', 'lead_id', 'duplicate_match_id'];

    protected $attributes = ['processing_status' => 'pending'];

    protected function casts(): array
    {
        return ['raw_data' => 'array', 'processed_data' => 'array', 'processing_status' => UploadRowStatus::class];
    }

    /** @return BelongsTo<UploadBatch, $this> */
    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
