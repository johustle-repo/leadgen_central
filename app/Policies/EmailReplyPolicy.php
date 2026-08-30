<?php

namespace App\Policies;

use App\Models\EmailReply;
use App\Models\User;

class EmailReplyPolicy
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
    public function view(User $user, EmailReply $emailReply): bool
    {
        return $user->canViewAllLeads() || $emailReply->agent_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmailReply $emailReply): bool
    {
        return $this->view($user, $emailReply);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmailReply $emailReply): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmailReply $emailReply): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmailReply $emailReply): bool
    {
        return false;
    }
}
