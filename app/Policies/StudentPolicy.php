<?php

namespace App\Policies;

use App\Enums\StudentPlacementStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\TeacherAuthorizationService;

class StudentPolicy
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth
    ) {}

    /**
     * Determine whether the user can view any students.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->isClassTeacher() || $user->isSubjectTeacher();
    }

    /**
     * Determine whether the user can view the specific student.
     * Enforces active placement within the teacher's assigned classroom.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        // For teachers: verify student has active academic record in an assigned classroom
        $activeRecords = $student->academicRecords()
            ->where('status', StudentPlacementStatus::ACTIVE)
            ->get();

        foreach ($activeRecords as $record) {
            if ($this->teacherAuth->userCanAccessClassSection($user, $record->academic_year_id, $record->class_id, $record->section_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create students.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can update the student.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can transfer the student internally or change placement.
     */
    public function transfer(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can import students from CSV.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can manage student subject allocations.
     */
    public function updateAllocation(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can permanently delete an unused student.
     * Deletion is restricted to Admin and Office Staff, and blocked server-side if historical records exist.
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
