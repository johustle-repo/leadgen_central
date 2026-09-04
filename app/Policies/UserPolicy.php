<?php

namespace App\Policies;

use App\AccountStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can update the model.
     *
     * A Super Administrator can update anyone. A regular Administrator can
     * update their own account and anyone below the administrator tier, but
     * not another Administrator or Super Administrator.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->isSuperAdministrator()) {
            return true;
        }

        return $user->isAdministrator() && ($user->is($model) || ! $model->isAdministrator());
    }

    /**
     * Determine whether the user can delete the model.
     *
     * A Super Administrator can delete anyone but themselves. A regular
     * Administrator can only delete accounts below the administrator tier.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        if ($user->isSuperAdministrator()) {
            return true;
        }

        return $user->isAdministrator() && ! $model->isAdministrator();
    }

    /**
     * Determine whether the user can log in as the model.
     *
     * Exclusive to Super Administrators. They cannot impersonate themselves,
     * another Super Administrator, or an inactive account.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->isSuperAdministrator()
            && ! $user->is($model)
            && ! $model->isSuperAdministrator()
            && $model->status === AccountStatus::Active;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
