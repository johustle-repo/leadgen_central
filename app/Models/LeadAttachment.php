<?php

namespace App\Models;

use Database\Factories\LeadAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAttachment extends Model
{
    /** @use HasFactory<LeadAttachmentFactory> */
    use HasFactory;

    protected $fillable = ['lead_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'file_size', 'label'];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
