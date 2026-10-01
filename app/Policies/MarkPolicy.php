<?php

namespace App\Policies;

use App\Models\Mark;
use App\Models\User;
use App\Services\TeacherAuthorizationService;

class MarkPolicy
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth
    ) {}

    /**
     * Determine whether the user can view any marks.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->isClassTeacher() || $user->isSubjectTeacher();
    }

    /**
     * Determine whether the user can view the specific mark.
     */
    public function view(User $user, Mark $mark): bool
    {
        return $this->teacherAuth->userCanViewMark($user, $mark);
    }

    /**
     * Determine whether the user can create marks.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->isClassTeacher() || $user->isSubjectTeacher();
    }

    /**
     * Determine whether the user can update the mark.
     * Enforces contextual teacher assignment scope and closed-year restrictions.
     */
    public function update(User $user, Mark $mark): bool
    {
        return $this->teacherAuth->userCanEditMark($user, $mark);
    }

    /**
     * Determine whether the user can delete the mark.
     */
    public function delete(User $user, Mark $mark): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    /**
     * Determine whether the user can batch save marks in a specific context.
     */
    public function batchSave(User $user, int $academicYearId, int $classId, int $sectionId, int $subjectId): bool
    {
        return $this->teacherAuth->userCanEditMarksInContext($user, $academicYearId, $classId, $sectionId, $subjectId);
    }
}
