<?php

namespace App\Models;

use App\LeadSource;
use App\LeadStatus;
use App\Models\Concerns\FlushesReportCache;
use App\Services\LeadStateResolver;
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
    use FlushesReportCache, HasFactory, SoftDeletes;

    protected $fillable = ['lead_code', 'agent_id', 'upload_batch_id', 'source', 'lead_date', 'company_name', 'normalized_company_name', 'website', 'original_website', 'website_domain', 'address', 'city', 'raw_city', 'state_province', 'country', 'raw_country', 'country_code', 'canonical_city_id', 'canonical_country_id', 'timezone', 'industry', 'business_type', 'contact_person', 'position', 'email', 'secondary_email', 'phone', 'linkedin_url', 'import_trades', 'product_requested', 'data_source', 'source_url', 'status', 'validation_status', 'location_match_type', 'verified_at', 'verified_by', 'replied_at', 'notes', 'created_by', 'updated_by'];

    protected $attributes = ['source' => 'manual', 'status' => 'raw', 'validation_status' => 'pending'];

    protected static function booted(): void
    {
        static::saving(fn (Lead $lead) => $lead->fillDerivedFields());

        static::created(function (Lead $lead): void {
            if ($lead->lead_code === null) {
                $lead->forceFill(['lead_code' => sprintf('LD-%s-%06d', $lead->created_at->format('Y'), $lead->id)])->saveQuietly();
            }
        });
    }

    /** Data sources recognised from the site a lead's link points at. */
    public const LINK_SOURCES = ['tendata' => 'Tendata', 'lusha' => 'Lusha'];

    /**
     * The data source a link belongs to, judged by its domain (e.g.
     * bizr.tendata.cn is Tendata, dashboard.lusha.com is Lusha).
     */
    public static function sourceFromLink(?string $url): ?string
    {
        foreach (self::LINK_SOURCES as $domain => $source) {
            if ($url !== null && preg_match("~(^|[/.@]){$domain}\\.[a-z]{2,}~i", trim($url)) === 1) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Applied on every save, whatever created or edited the lead: a missing
     * LinkedIn stays blank instead of a placeholder, and a lead with no data
     * source takes it from its Tendata or Lusha link, or is Manual when it
     * has no link either. A US or Canadian lead's City holds its full state
     * or province name (see LeadStateResolver).
     *
     * Only fields loaded on this instance are considered, so saving a lead
     * fetched with a partial column list never blanks the columns it skipped.
     */
    private function fillDerivedFields(): void
    {
        $loaded = fn (string ...$keys): bool => ! $this->exists || array_diff($keys, array_keys($this->getAttributes())) === [];

        if ($loaded('linkedin_url') && $this->linkedin_url !== null && $this->isPlaceholder($this->linkedin_url)) {
            $this->linkedin_url = null;
        }

        $location = ['city', 'raw_city', 'state_province', 'country', 'country_code'];
        if ($loaded(...$location) && (! $this->exists || $this->isDirty($location))) {
            app(LeadStateResolver::class)->normalize($this);
        }

        if (! $loaded('data_source', 'source_url') || ! $this->isPlaceholder($this->data_source)) {
            return;
        }

        $derived = self::sourceFromLink($this->source_url);
        if ($derived === null && $this->isPlaceholder($this->source_url)) {
            $derived = 'Manual';
        }
        if ($derived !== null) {
            $this->data_source = $derived;
        }
    }

    private function isPlaceholder(?string $value): bool
    {
        return $value === null || in_array(mb_strtolower(trim($value)), ['', 'n/a', 'na', 'none', 'null', '-'], true);
    }

    protected function casts(): array
    {
        return ['source' => LeadSource::class, 'lead_date' => 'date', 'status' => LeadStatus::class, 'verified_at' => 'datetime', 'replied_at' => 'date'];
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
