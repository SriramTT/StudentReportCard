<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->isAdmin();
    }
}
