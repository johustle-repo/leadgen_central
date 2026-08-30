<?php

namespace App\Models;

use App\AccountStatus;
use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $company_alias
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property AccountStatus $status
 * @property string|null $team
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'company_alias', 'email', 'password', 'role', 'team', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected $attributes = ['role' => 'agent', 'status' => 'active'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function canViewAllLeads(): bool
    {
        return in_array($this->role, [UserRole::Administrator, UserRole::SubAdministrator], true);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'agent_id');
    }

    /** @return HasMany<UploadBatch, $this> */
    public function uploadBatches(): HasMany
    {
        return $this->hasMany(UploadBatch::class);
    }

    /** @return HasMany<DuplicateLog, $this> */
    public function duplicateLogs(): HasMany
    {
        return $this->hasMany(DuplicateLog::class, 'uploading_agent_id');
    }

    /** @return HasMany<GmailConnection, $this> */
    public function gmailConnections(): HasMany
    {
        return $this->hasMany(GmailConnection::class);
    }

    /** @return HasMany<EmailReply, $this> */
    public function emailReplies(): HasMany
    {
        return $this->hasMany(EmailReply::class, 'agent_id');
    }

    /** @return HasMany<EmailSequence, $this> */
    public function emailSequences(): HasMany
    {
        return $this->hasMany(EmailSequence::class);
    }

    /** @return HasMany<EmailSequenceEnrollment, $this> */
    public function emailSequenceEnrollments(): HasMany
    {
        return $this->hasMany(EmailSequenceEnrollment::class, 'agent_id');
    }
}
