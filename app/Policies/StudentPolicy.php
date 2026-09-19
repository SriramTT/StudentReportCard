<?php

namespace App\Policies;

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
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        // For teachers: verify student has active academic record in an assigned classroom
        $academicRecords = $student->academicRecords;
        foreach ($academicRecords as $record) {
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
     * Hard deletion is strictly prohibited (TH-12, Section 27).
     */
    public function delete(User $user, Student $student): bool
    {
        return false;
    }
}
