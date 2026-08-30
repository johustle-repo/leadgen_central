<?php

namespace App\Models;

use App\EmailReplyClassification;
use Database\Factories\EmailReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReply extends Model
{
    /** @use HasFactory<EmailReplyFactory> */
    use HasFactory;

    protected $fillable = ['gmail_connection_id', 'agent_id', 'lead_id', 'gmail_message_id', 'gmail_thread_id', 'sender_name', 'sender_email', 'subject', 'body_preview', 'body_text', 'classification', 'classification_reason', 'is_read', 'received_at'];

    protected $attributes = ['classification' => 'needs_review', 'is_read' => false];

    protected function casts(): array
    {
        return ['classification' => EmailReplyClassification::class, 'is_read' => 'boolean', 'received_at' => 'datetime'];
    }

    /** @return BelongsTo<GmailConnection, $this> */
    public function gmailConnection(): BelongsTo
    {
        return $this->belongsTo(GmailConnection::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
