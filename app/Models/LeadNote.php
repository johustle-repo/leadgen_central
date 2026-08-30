<?php

namespace App\Models;

use Database\Factories\LeadNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    /** @use HasFactory<LeadNoteFactory> */
    use HasFactory;

    protected $fillable = ['lead_id', 'user_id', 'note', 'note_type'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
