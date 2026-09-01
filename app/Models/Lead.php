<?php

namespace App\Models;

use App\LeadSource;
use App\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property LeadSource $source
 * @property LeadStatus $status
 * @property Carbon|null $lead_date
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['lead_code', 'agent_id', 'upload_batch_id', 'source', 'lead_date', 'company_name', 'normalized_company_name', 'website', 'original_website', 'website_domain', 'address', 'city', 'raw_city', 'state_province', 'country', 'raw_country', 'country_code', 'canonical_city_id', 'canonical_country_id', 'timezone', 'industry', 'business_type', 'contact_person', 'position', 'email', 'phone', 'linkedin_url', 'import_trades', 'data_source', 'source_url', 'status', 'validation_status', 'location_match_type', 'verified_at', 'verified_by', 'notes', 'created_by', 'updated_by'];

    protected $attributes = ['source' => 'manual', 'status' => 'raw', 'validation_status' => 'pending'];

    protected static function booted(): void
    {
        static::created(function (Lead $lead): void {
            if ($lead->lead_code === null) {
                $lead->forceFill(['lead_code' => sprintf('LD-%s-%06d', $lead->created_at->format('Y'), $lead->id)])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return ['source' => LeadSource::class, 'lead_date' => 'date', 'status' => LeadStatus::class, 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<UploadBatch, $this> */
    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<City, $this> */
    public function canonicalCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'canonical_city_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function canonicalCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'canonical_country_id');
    }

    /** @return HasMany<LeadNote, $this> */
    public function structuredNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    /** @return HasMany<LeadStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class);
    }

    /** @return HasMany<LeadForwarding, $this> */
    public function forwardings(): HasMany
    {
        return $this->hasMany(LeadForwarding::class);
    }

    /** @return HasMany<DuplicateMatch, $this> */
    public function duplicateMatchesAsIncoming(): HasMany
    {
        return $this->hasMany(DuplicateMatch::class, 'incoming_lead_id');
    }

    /** @return HasMany<DuplicateMatch, $this> */
    public function duplicateMatchesAsExisting(): HasMany
    {
        return $this->hasMany(DuplicateMatch::class, 'existing_lead_id');
    }

    /** @return HasMany<EmailReply, $this> */
    public function emailReplies(): HasMany
    {
        return $this->hasMany(EmailReply::class);
    }

    /** @return HasMany<EmailSequenceEnrollment, $this> */
    public function emailSequenceEnrollments(): HasMany
    {
        return $this->hasMany(EmailSequenceEnrollment::class);
    }

    /** @return HasMany<LeadAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class);
    }
}
