<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users (Administrator and Office Staff).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create users (Administrator and Office Staff).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can update the user.
     * Office Staff cannot edit an Administrator.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->isOfficeStaff() && $model->isAdmin()) {
            return false;
        }

        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can activate another user.
     * Office Staff cannot activate an Administrator.
     */
    public function activate(User $user, User $model): bool
    {
        if ($user->isOfficeStaff() && $model->isAdmin()) {
            return false;
        }

        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can deactivate another user.
     * Prevents self-deactivation, prevents Office Staff deactivating Admin,
     * and prevents deactivating the last active Administrator.
     */
    public function deactivate(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->isOfficeStaff() && $model->isAdmin()) {
            return false;
        }

        if (! ($user->isAdmin() || $user->isOfficeStaff())) {
            return false;
        }

        // Prevent deactivating the last active Administrator
        if ($model->isAdmin()) {
            $activeAdminCount = User::query()
                ->where('role_id', $model->role_id)
                ->where('is_active', true)
                ->count();

            if ($activeAdminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can change another user's password.
     * Office Staff cannot change password of an Administrator.
     */
    public function changePassword(User $user, User $model): bool
    {
        if ($user->isOfficeStaff() && $model->isAdmin()) {
            return false;
        }

        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Users are never hard-deleted.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
