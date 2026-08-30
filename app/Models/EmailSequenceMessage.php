<?php

namespace App\Models;

use Database\Factories\EmailSequenceMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSequenceMessage extends Model
{
    /** @use HasFactory<EmailSequenceMessageFactory> */
    use HasFactory;

    protected $fillable = ['email_sequence_enrollment_id', 'step_number', 'gmail_message_id', 'gmail_thread_id', 'subject', 'body', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<EmailSequenceEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(EmailSequenceEnrollment::class, 'email_sequence_enrollment_id');
    }
}
