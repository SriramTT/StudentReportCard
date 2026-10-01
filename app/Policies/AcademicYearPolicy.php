<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    /**
     * Determine whether the user can view any academic years.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can view the specific academic year.
     */
    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can create academic years.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can update the academic year.
     */
    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can close the academic year.
     */
    public function close(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can reopen the academic year.
     */
    public function reopen(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can delete the academic year.
     */
    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the academic year's academic contents/setup can be configured.
     */
    public function configure(User $user, AcademicYear $academicYear): bool
    {
        if (! ($user->isAdmin() || $user->isOfficeStaff())) {
            return false;
        }

        return $academicYear->isConfigurationWindowOpen();
    }
}
