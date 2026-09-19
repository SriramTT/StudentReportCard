<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Services\TeacherAuthorizationService;

class AttendancePolicy
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth
    ) {}

    /**
     * Determine whether the user can view any attendance records.
     * Subject teachers have no attendance authority.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->isClassTeacher();
    }

    /**
     * Determine whether the user can view the specific attendance record.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        if (! $user->isClassTeacher()) {
            return false;
        }

        if (! $attendance->relationLoaded('studentAcademicRecord')) {
            $attendance->load('studentAcademicRecord');
        }

        $sar = $attendance->studentAcademicRecord;
        if ($sar === null) {
            return false;
        }

        return $this->teacherAuth->isClassTeacherFor(
            $user,
            $sar->academic_year_id,
            $sar->class_id,
            $sar->section_id
        );
    }

    /**
     * Determine whether the user can create attendance records.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->isClassTeacher();
    }

    /**
     * Determine whether the user can update the attendance record.
     * Subject teachers have zero attendance authority.
     * Class teachers are authorized only for assigned classrooms during open academic years.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $this->teacherAuth->userCanEditAttendance($user, $attendance);
    }

    /**
     * Determine whether the user can delete the attendance record.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
