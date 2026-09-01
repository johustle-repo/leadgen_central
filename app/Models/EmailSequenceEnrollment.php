<?php

namespace App\Models;

use Database\Factories\EmailSequenceEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $email_sequence_id
 * @property int $lead_id
 * @property int $agent_id
 * @property string $status
 * @property int $current_step
 * @property Carbon $started_at
 * @property Carbon|null $next_send_at
 * @property Carbon|null $stopped_at
 * @property-read EmailSequence $sequence
 * @property-read Lead $lead
 */
class EmailSequenceEnrollment extends Model
{
    /** @use HasFactory<EmailSequenceEnrollmentFactory> */
    use HasFactory;

    protected $fillable = ['email_sequence_id', 'lead_id', 'agent_id', 'status', 'current_step', 'started_at', 'next_send_at', 'stopped_at', 'stop_reason', 'last_error'];

    protected $attributes = ['status' => 'active', 'current_step' => 0];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'next_send_at' => 'datetime', 'stopped_at' => 'datetime'];
    }

    /** @return BelongsTo<EmailSequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'email_sequence_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return HasMany<EmailSequenceMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailSequenceMessage::class);
    }
}
