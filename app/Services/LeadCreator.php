<?php

namespace App\Services;

use App\LeadSource;
use App\LeadStatus;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadCreator
{
    public const MAX_CONTACTS_PER_COMPANY = 10;

    public function __construct(
        private LeadNormalizationService $normalizer,
        private LocationMatchingService $locations,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $owner, User $actor, ?UploadBatch $batch = null): Lead
    {
        $normalized = $this->normalizer->normalize($data);
        $location = $this->locations->match(
            isset($data['country_code']) ? (string) $data['country_code'] : ($data['country'] ?? null),
            $data['city'] ?? null,
        );

        return DB::transaction(function () use ($normalized, $location, $data, $owner, $actor, $batch): Lead {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if ($batch === null && $normalized['email'] !== null) {
                $existing = Lead::query()->where('email', $normalized['email'])->first(['id', 'lead_code', 'agent_id']);

                if ($existing !== null) {
                    throw ValidationException::withMessages([
                        'email' => "This email is already saved as lead {$existing->lead_code}.",
                    ]);
                }
            }

            $companyContactCount = Lead::query()
                ->whereBelongsTo($owner, 'agent')
                ->where('normalized_company_name', $normalized['normalized_company_name'])
                ->count();

            if ($companyContactCount >= self::MAX_CONTACTS_PER_COMPANY) {
                throw ValidationException::withMessages([
                    'company_name' => 'An agent can have a maximum of 10 contacts for the same company.',
                ]);
            }

            return Lead::create([...$normalized, ...$location->leadAttributes($data['city'] ?? null, $data['country'] ?? ($data['country_code'] ?? null)), 'agent_id' => $owner->id, 'upload_batch_id' => $batch?->id, 'source' => $batch ? LeadSource::Csv : LeadSource::Manual, 'status' => $batch ? LeadStatus::Validated : LeadStatus::Raw, 'created_by' => $actor->id]);
        });
    }
}
