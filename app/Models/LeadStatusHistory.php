<?php

namespace App\Models;

use Database\Factories\LeadStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatusHistory extends Model
{
    /** @use HasFactory<LeadStatusHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['lead_id', 'old_status', 'new_status', 'changed_by', 'remarks', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
