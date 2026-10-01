<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

class TeacherAssignmentPolicy
{
    /**
     * Determine whether the user can view any teacher assignments.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can view the specific teacher assignment.
     */
    public function view(User $user, TeacherAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can create teacher assignments.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can update the teacher assignment.
     */
    public function update(User $user, TeacherAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can activate the teacher assignment.
     */
    public function activate(User $user, TeacherAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can deactivate the teacher assignment.
     */
    public function deactivate(User $user, TeacherAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can permanently delete an unused teacher assignment.
     * Deletion is restricted to Admin and Office Staff, and blocked server-side if historical records exist.
     */
    public function delete(User $user, TeacherAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
