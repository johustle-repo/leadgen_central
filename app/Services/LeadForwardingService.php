<?php

namespace App\Services;

use App\LeadStatus;
use App\Models\Lead;
use App\Models\LeadForwarding;
use App\Models\LeadStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadForwardingService
{
    /** @param array<string, mixed> $data */
    public function forward(Lead $lead, array $data, User $actor): LeadForwarding
    {
        if ($lead->status !== LeadStatus::QualifiedLead) {
            throw ValidationException::withMessages(['lead' => 'Only qualified leads can be forwarded.']);
        }

        return DB::transaction(function () use ($lead, $data, $actor): LeadForwarding {
            $forwarding = LeadForwarding::query()->create([...$data, 'lead_id' => $lead->id, 'forwarded_by' => $actor->id, 'forwarded_at' => now()]);
            $lead->update(['status' => LeadStatus::Forwarded, 'updated_by' => $actor->id]);
            LeadStatusHistory::query()->create(['lead_id' => $lead->id, 'old_status' => LeadStatus::QualifiedLead->value, 'new_status' => LeadStatus::Forwarded->value, 'changed_by' => $actor->id, 'remarks' => $data['remarks'] ?? null]);

            return $forwarding;
        });
    }
}
