<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\GeneratedReport;
use App\Models\Mark;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TeacherAuthorizationService
{
    /**
     * Get all active teacher assignments for a user evaluated against live PostgreSQL data.
     *
     * @param  User  $user
     * @param  int|null  $academicYearId
     * @return Collection<int, TeacherAssignment>
     */
    public function getActiveAssignments(User $user, ?int $academicYearId = null): Collection
    {
        $today = now()->toDateString();

        $query = TeacherAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            });

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->get();
    }

    /**
     * Check if the user is authorized for the given academic year, class, section, and optional subject.
     * Evaluates live assignments with multi-assignment and class teacher all-subject inheritance rules.
     */
    public function isAuthorized(
        User $user,
        int $academicYearId,
        int $classId,
        int $sectionId,
        ?int $subjectId = null
    ): bool {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        $today = now()->toDateString();

        $query = TeacherAssignment::query()
            ->where('user_id', $user->id)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            });

        if ($subjectId !== null) {
            $query->where(function ($q) use ($subjectId) {
                // Class Teacher has scope over ALL subjects in assigned class/section
                $q->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
                  // Subject Teacher has scope ONLY over the specific assigned subject
                  ->orWhere(function ($sub) use ($subjectId) {
                      $sub->where('assignment_type', TeacherAssignmentType::SUBJECT_TEACHER)
                          ->where('subject_id', $subjectId);
                  });
            });
        }

        return $query->exists();
    }

    /**
     * Determine if a user has an active Class Teacher assignment for the specified class and section.
     */
    public function isClassTeacherFor(
        User $user,
        int $academicYearId,
        int $classId,
        int $sectionId
    ): bool {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        $today = now()->toDateString();

        return TeacherAssignment::query()
            ->where('user_id', $user->id)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
            ->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            })
            ->exists();
    }

    /**
     * Check if user can access the class and section context.
     */
    public function userCanAccessClassSection(
        User $user,
        int $academicYearId,
        int $classId,
        int $sectionId
    ): bool {
        return $this->isAuthorized($user, $academicYearId, $classId, $sectionId, null);
    }

    /**
     * Check if user can access a specific subject within the class and section context.
     */
    public function userCanAccessSubject(
        User $user,
        int $academicYearId,
        int $classId,
        int $sectionId,
        int $subjectId
    ): bool {
        return $this->isAuthorized($user, $academicYearId, $classId, $sectionId, $subjectId);
    }

    /**
     * Check if user can view a specific mark record.
     */
    public function userCanViewMark(User $user, Mark $mark): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        if (! $mark->relationLoaded('studentAcademicRecord')) {
            $mark->load('studentAcademicRecord');
        }
        if (! $mark->relationLoaded('studentSubjectAllocation.classSubject')) {
            $mark->load('studentSubjectAllocation.classSubject');
        }

        $sar = $mark->studentAcademicRecord;
        $allocation = $mark->studentSubjectAllocation;

        if ($sar === null || $allocation === null || $allocation->classSubject === null) {
            return false;
        }

        return $this->isAuthorized(
            $user,
            $sar->academic_year_id,
            $sar->class_id,
            $sar->section_id,
            $allocation->classSubject->subject_id
        );
    }

    /**
     * Check if user can edit a specific mark record.
     * Enforces closed-year read-only restriction for teachers.
     */
    public function userCanEditMark(User $user, Mark $mark): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        if (! $mark->relationLoaded('studentAcademicRecord')) {
            $mark->load('studentAcademicRecord');
        }
        if (! $mark->relationLoaded('studentSubjectAllocation.classSubject')) {
            $mark->load('studentSubjectAllocation.classSubject');
        }

        $sar = $mark->studentAcademicRecord;
        $allocation = $mark->studentSubjectAllocation;

        if ($sar === null || $allocation === null || $allocation->classSubject === null) {
            return false;
        }

        // Closed academic year rule: teachers are read-only
        $academicYear = AcademicYear::query()->find($sar->academic_year_id);
        if ($academicYear?->status === AcademicYearStatus::CLOSED) {
            return false;
        }

        return $this->isAuthorized(
            $user,
            $sar->academic_year_id,
            $sar->class_id,
            $sar->section_id,
            $allocation->classSubject->subject_id
        );
    }

    /**
     * Check if user can edit an attendance record.
     * Subject teachers have no attendance authority.
     * Class teachers have authority only within assigned classroom during open academic years.
     */
    public function userCanEditAttendance(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        if (! $attendance->relationLoaded('studentAcademicRecord')) {
            $attendance->load('studentAcademicRecord');
        }

        $sar = $attendance->studentAcademicRecord;
        if ($sar === null) {
            return false;
        }

        // Closed academic year rule: class teachers are read-only
        $academicYear = AcademicYear::query()->find($sar->academic_year_id);
        if ($academicYear?->status === AcademicYearStatus::CLOSED) {
            return false;
        }

        return $this->isClassTeacherFor($user, $sar->academic_year_id, $sar->class_id, $sar->section_id);
    }

    /**
     * Check if user can generate report cards for a classroom.
     * Administrator / Office Staff: allowed.
     * Class Teacher: allowed for assigned class/section in open academic years.
     * Subject Teacher: denied (not class teacher for section).
     */
    public function userCanGenerateReport(
        User $user,
        int $academicYearId,
        int $classId,
        int $sectionId
    ): bool {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        $academicYear = AcademicYear::query()->find($academicYearId);
        if ($academicYear?->status === AcademicYearStatus::CLOSED) {
            return false;
        }

        return $this->isClassTeacherFor($user, $academicYearId, $classId, $sectionId);
    }

    /**
     * Check if user can download an existing generated report card.
     */
    public function userCanDownloadReport(User $user, GeneratedReport $report): bool
    {
        if ($user->isAdmin() || $user->isOfficeStaff()) {
            return true;
        }

        if (! $report->relationLoaded('studentAcademicRecord')) {
            $report->load('studentAcademicRecord');
        }

        $sar = $report->studentAcademicRecord;
        if ($sar === null) {
            return false;
        }

        return $this->isClassTeacherFor($user, $sar->academic_year_id, $sar->class_id, $sar->section_id);
    }
}
