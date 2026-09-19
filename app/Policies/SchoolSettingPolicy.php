<?php

namespace App\Policies;

use App\Models\SchoolSetting;
use App\Models\User;

class SchoolSettingPolicy
{
    /**
     * Determine whether the user can view school settings (Administrator only).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view a specific school setting (Administrator only).
     */
    public function view(User $user, SchoolSetting $setting): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create school settings (Administrator only).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update school settings (Administrator only).
     */
    public function update(User $user, SchoolSetting $setting): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete school settings (Administrator only).
     */
    public function delete(User $user, SchoolSetting $setting): bool
    {
        return $user->isAdmin();
    }
}
