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
     * Determine whether the user can create academic years (Administrator only).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the academic year (Administrator only).
     */
    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can close the academic year (Administrator only).
     */
    public function close(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reopen the academic year (Administrator only).
     */
    public function reopen(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the academic year (Administrator only).
     */
    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin();
    }
}
