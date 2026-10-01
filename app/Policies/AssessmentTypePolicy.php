<?php

namespace App\Policies;

use App\Models\AssessmentType;
use App\Models\User;

class AssessmentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function view(User $user, AssessmentType $assessmentType): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function update(User $user, AssessmentType $assessmentType): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function delete(User $user, AssessmentType $assessmentType): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
