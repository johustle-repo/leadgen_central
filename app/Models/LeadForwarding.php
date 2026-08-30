<?php

namespace App\Models;

use Database\Factories\LeadForwardingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadForwarding extends Model
{
    /** @use HasFactory<LeadForwardingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['lead_id', 'forwarded_by', 'forwarded_to', 'recipient_name', 'recipient_email', 'team', 'remarks', 'forwarded_at', 'created_at'];

    protected function casts(): array
    {
        return ['forwarded_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by');
    }
}
