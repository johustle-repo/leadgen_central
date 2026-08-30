<?php

namespace App\Models;

use Database\Factories\DuplicateLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateLog extends Model
{
    /** @use HasFactory<DuplicateLogFactory> */
    use HasFactory;

    protected $fillable = ['uploading_agent_id', 'original_lead_id', 'original_owner_id', 'upload_batch_id', 'upload_row_id', 'duplicate_match_id', 'detection_reason'];

    /** @return BelongsTo<User, $this> */
    public function uploadingAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploading_agent_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function originalLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'original_lead_id');
    }
}
