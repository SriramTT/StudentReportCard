<?php

namespace App\Policies;

use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\TeacherAuthorizationService;

class ReportPolicy
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth
    ) {}

    /**
     * Determine whether the user can view any reports.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff() || $user->isClassTeacher();
    }

    /**
     * Determine whether the user can view/download the specific generated report.
     */
    public function view(User $user, GeneratedReport $report): bool
    {
        return $this->teacherAuth->userCanDownloadReport($user, $report);
    }

    /**
     * Determine whether the user can generate report cards for a classroom.
     */
    public function generate(User $user, int $academicYearId, int $classId, int $sectionId): bool
    {
        return $this->teacherAuth->userCanGenerateReport($user, $academicYearId, $classId, $sectionId);
    }

    /**
     * Determine whether the user can download the generated report file.
     */
    public function download(User $user, GeneratedReport $report): bool
    {
        return $this->teacherAuth->userCanDownloadReport($user, $report);
    }
}
