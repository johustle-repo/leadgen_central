<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadBulkDeletion
{
    /** @param list<int> $leadIds */
    public function delete(array $leadIds, User $actor, ?string $ipAddress, ?string $userAgent): int
    {
        return DB::transaction(function () use ($leadIds, $actor, $ipAddress, $userAgent): int {
            $leads = Lead::query()->whereKey($leadIds)->orderBy('id')->get();
            $leadCodes = $leads->pluck('lead_code')->all();

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'lead.bulk_deleted',
                'auditable_type' => 'lead',
                'auditable_id' => $leads->first()?->id,
                'description' => 'Deleted '.$leads->count().' selected lead(s).',
                'metadata' => [
                    'lead_ids' => $leads->modelKeys(),
                    'lead_codes' => $leadCodes,
                    'count' => $leads->count(),
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            $leads->each->delete();

            return $leads->count();
        });
    }
}
