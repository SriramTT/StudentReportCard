<?php

namespace App\Policies;

use App\Models\AssessmentApplicability;
use App\Models\User;

class AssessmentApplicabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function view(User $user, AssessmentApplicability $applicability): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function update(User $user, AssessmentApplicability $applicability): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function delete(User $user, AssessmentApplicability $applicability): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
