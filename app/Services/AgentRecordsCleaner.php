<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Models\User;
use App\UploadBatchStatus;
use Illuminate\Support\Facades\DB;

class AgentRecordsCleaner
{
    public function __construct(private UploadBatchDeletion $uploadBatchDeletion) {}

    /**
     * Wipes a user's leads (soft-deleted, same as the existing single/bulk lead
     * delete) and their completed/failed upload history (hard-deleted, files
     * included, same as the existing upload delete) - everything else about
     * the account is left untouched.
     *
     * @return array{leads: int, uploads: int}
     */
    public function clear(User $agent, User $actor, ?string $ipAddress, ?string $userAgent): array
    {
        $uploadBatches = UploadBatch::query()
            ->where('user_id', $agent->id)
            ->whereIn('processing_status', [UploadBatchStatus::Completed, UploadBatchStatus::Failed])
            ->get();

        foreach ($uploadBatches as $batch) {
            $this->uploadBatchDeletion->delete($batch, $actor, $ipAddress, $userAgent);
        }

        $leadCount = DB::transaction(function () use ($agent, $actor, $ipAddress, $userAgent): int {
            $leads = Lead::query()->where('agent_id', $agent->id)->get();

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'agent.records_cleared',
                'auditable_type' => 'user',
                'auditable_id' => $agent->id,
                'description' => "Cleared {$leads->count()} lead(s) for {$agent->name}.",
                'metadata' => ['agent_id' => $agent->id, 'agent_name' => $agent->name, 'lead_count' => $leads->count()],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            $leads->each->delete();

            return $leads->count();
        });

        return ['leads' => $leadCount, 'uploads' => $uploadBatches->count()];
    }
}
