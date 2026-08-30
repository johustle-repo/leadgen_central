<?php

namespace App\Models;

use Database\Factories\DuplicateMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateMatch extends Model
{
    /** @use HasFactory<DuplicateMatchFactory> */
    use HasFactory;

    protected $fillable = ['incoming_lead_id', 'upload_row_id', 'existing_lead_id', 'match_type', 'match_score', 'matched_fields', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['matched_fields' => 'array', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function existingLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'existing_lead_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function incomingLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'incoming_lead_id');
    }

    /** @return BelongsTo<UploadRow, $this> */
    public function uploadRow(): BelongsTo
    {
        return $this->belongsTo(UploadRow::class);
    }
}
