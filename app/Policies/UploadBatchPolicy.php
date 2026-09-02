<?php

namespace App\Policies;

use App\Models\UploadBatch;
use App\Models\User;
use App\UploadBatchStatus;

class UploadBatchPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UploadBatch $uploadBatch): bool
    {
        return $user->canViewAllLeads() || $uploadBatch->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UploadBatch $uploadBatch): bool
    {
        return $uploadBatch->user_id === $user->id && $uploadBatch->processing_status === UploadBatchStatus::Pending;
    }

    public function reanalyze(User $user, UploadBatch $uploadBatch): bool
    {
        return ($user->canViewAllLeads() || $uploadBatch->user_id === $user->id)
            && in_array($uploadBatch->processing_status, [UploadBatchStatus::Completed, UploadBatchStatus::Failed], true);
    }

    public function retry(User $user, UploadBatch $uploadBatch): bool
    {
        return ($user->canViewAllLeads() || $uploadBatch->user_id === $user->id)
            && $uploadBatch->processing_status === UploadBatchStatus::Pending;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UploadBatch $uploadBatch): bool
    {
        return $user->isAdministrator()
            && in_array($uploadBatch->processing_status, [UploadBatchStatus::Completed, UploadBatchStatus::Failed], true);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UploadBatch $uploadBatch): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UploadBatch $uploadBatch): bool
    {
        return false;
    }
}
