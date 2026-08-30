<?php

namespace App\Models;

use Database\Factories\GmailConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $access_token
 * @property string $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_synced_at
 */
class GmailConnection extends Model
{
    /** @use HasFactory<GmailConnectionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'gmail_address', 'access_token', 'refresh_token', 'token_expires_at', 'history_id', 'last_synced_at', 'status', 'last_error'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'token_expires_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EmailReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(EmailReply::class);
    }
}
