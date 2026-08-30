<?php

namespace App\Models;

use App\UploadBatchStatus;
use Database\Factories\UploadBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property UploadBatchStatus $processing_status
 * @property array<string, string|null>|null $column_mapping
 * @property list<string>|null $headers
 * @property User $user
 */
class UploadBatch extends Model
{
    /** @use HasFactory<UploadBatchFactory> */
    use HasFactory;

    protected $fillable = ['batch_code', 'user_id', 'original_filename', 'stored_filename', 'file_size', 'total_rows', 'new_leads', 'valid_leads', 'accepted_rows', 'rejected_rows', 'invalid_rows', 'location_error_rows', 'duplicate_rows', 'exact_duplicate_rows', 'possible_duplicate_rows', 'error_rows', 'processing_status', 'headers', 'column_mapping', 'failure_message', 'started_at', 'completed_at'];

    protected $attributes = ['processing_status' => 'pending', 'total_rows' => 0, 'new_leads' => 0, 'valid_leads' => 0, 'accepted_rows' => 0, 'rejected_rows' => 0, 'invalid_rows' => 0, 'location_error_rows' => 0, 'duplicate_rows' => 0, 'exact_duplicate_rows' => 0, 'possible_duplicate_rows' => 0, 'error_rows' => 0];

    protected static function booted(): void
    {
        static::created(function (UploadBatch $batch): void {
            if ($batch->batch_code === null) {
                $batch->forceFill(['batch_code' => sprintf('UPL-%s-%05d', $batch->created_at->format('Ymd'), $batch->id)])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return ['processing_status' => UploadBatchStatus::class, 'headers' => 'array', 'column_mapping' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<UploadRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(UploadRow::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
