<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadVerificationService
{
    public function __construct(private LeadNormalizationService $normalizer, private LocationMatchingService $locations) {}

    /** @param array<string, mixed> $data */
    public function verify(Lead $lead, array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $data, $actor): Lead {
            $oldStatus = $lead->status->value;
            $updates = $this->normalizer->normalize([...$lead->only(['company_name', 'website', 'email', 'phone']), ...$data]);
            $location = $this->locations->match($data['country_code'] ?? $data['country'] ?? $lead->country_code ?? $lead->country, $data['city'] ?? $lead->city);
            $newStatus = (string) $data['status'];
            $locationAttributes = $location->leadAttributes($data['city'] ?? $lead->raw_city ?? $lead->city, $data['country'] ?? $data['country_code'] ?? $lead->raw_country ?? $lead->country);
            $lead->update([...$updates, ...$locationAttributes, 'timezone' => $data['timezone'] ?? $locationAttributes['timezone'], 'status' => $newStatus, 'validation_status' => 'verified', 'verified_at' => now(), 'verified_by' => $actor->id, 'updated_by' => $actor->id]);
            if ($oldStatus !== $newStatus) {
                LeadStatusHistory::query()->create(['lead_id' => $lead->id, 'old_status' => $oldStatus, 'new_status' => $newStatus, 'changed_by' => $actor->id, 'remarks' => $data['remarks'] ?? null]);
            }

            return $lead->refresh();
        });
    }
}
