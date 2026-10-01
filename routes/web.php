<?php

use App\Http\Controllers\Academic\AcademicYearController;
use App\Http\Controllers\Academic\ClassSubjectController;
use App\Http\Controllers\Academic\SchoolClassController;
use App\Http\Controllers\Academic\SectionController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\TeacherAssignmentController;
use App\Http\Controllers\Academic\TermController;
use App\Http\Controllers\Assessments\AssessmentApplicabilityController;
use App\Http\Controllers\Assessments\AssessmentController;
use App\Http\Controllers\Assessments\AssessmentTypeController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\Calculations\CalculationSettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Marks\MarkController;
use App\Http\Controllers\Reports\GeneratedReportDownloadController;
use App\Http\Controllers\Reports\ReportAssessmentSelectionController;
use App\Http\Controllers\Reports\ReportConfigurationController;
use App\Http\Controllers\Reports\ReportGenerationController;
use App\Http\Controllers\Settings\SchoolSettingController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentSubjectAllocationController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Guest authentication & first-run setup routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.submit');

    // Two-Step Verification (Email OTP)
    Route::get('/login/otp', [LoginController::class, 'showOtpForm'])->name('login.otp');
    Route::post('/login/otp', [LoginController::class, 'verifyOtp'])
        ->middleware('throttle:login')
        ->name('login.otp.verify');
    Route::post('/login/otp/resend', [LoginController::class, 'resendOtp'])
        ->middleware('throttle:login')
        ->name('login.otp.resend');

    // First-run Administrator bootstrap (available strictly when zero users exist)
    Route::get('/setup', [SetupController::class, 'showSetupForm'])->name('setup');
    Route::post('/setup', [SetupController::class, 'setup'])->name('setup.submit');
});

// Protected routes: strict pipeline web -> auth -> active
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Phase 9: School Settings (Administrator-only)
    Route::get('/admin/school-settings', [SchoolSettingController::class, 'edit'])->name('admin.school_settings.edit');
    Route::put('/admin/school-settings', [SchoolSettingController::class, 'update'])->name('admin.school_settings.update');

    // Phase 9.5: User Management (Administrator-only)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/password', [UserController::class, 'changePassword'])->name('users.password');

    // Phase 9: Academic Years
    Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic_years.index');
    Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic_years.store');
    Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic_years.update');
    Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic_years.destroy');
    Route::post('/academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academic_years.close');
    Route::post('/academic-years/{academicYear}/reopen', [AcademicYearController::class, 'reopen'])->name('academic_years.reopen');

    // Phase 9: Terms (Dynamic N-Terms)
    Route::get('/terms', [TermController::class, 'index'])->name('terms.index');
    Route::post('/terms', [TermController::class, 'store'])->name('terms.store');
    Route::post('/terms/reorder', [TermController::class, 'reorder'])->name('terms.reorder');
    Route::put('/terms/{term}', [TermController::class, 'update'])->name('terms.update');
    Route::delete('/terms/{term}', [TermController::class, 'destroy'])->name('terms.destroy');

    // Phase 9: Classes
    Route::get('/classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::post('/classes', [SchoolClassController::class, 'store'])->name('classes.store');
    Route::put('/classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
    Route::delete('/classes/{schoolClass}', [SchoolClassController::class, 'destroy'])->name('classes.destroy');

    // Phase 9: Sections
    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

    // Phase 9: Subjects
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    // Phase 9: Class Subjects
    Route::get('/class-subjects', [ClassSubjectController::class, 'index'])->name('class_subjects.index');
    Route::post('/class-subjects', [ClassSubjectController::class, 'store'])->name('class_subjects.store');
    Route::post('/class-subjects/group-status', [ClassSubjectController::class, 'updateGroupStatus'])->name('class_subjects.group_status');
    Route::post('/class-subjects/group-sync', [ClassSubjectController::class, 'syncGroup'])->name('class_subjects.group_sync');
    Route::delete('/class-subjects/group-delete', [ClassSubjectController::class, 'destroyGroup'])->name('class_subjects.group_destroy');
    Route::put('/class-subjects/{classSubject}', [ClassSubjectController::class, 'update'])->name('class_subjects.update');
    Route::delete('/class-subjects/{classSubject}', [ClassSubjectController::class, 'destroy'])->name('class_subjects.destroy');

    // Phase 9.5: Teacher Assignments (Administrator + Office Staff)
    Route::get('/teacher-assignments', [TeacherAssignmentController::class, 'index'])->name('teacher_assignments.index');
    Route::post('/teacher-assignments', [TeacherAssignmentController::class, 'store'])->name('teacher_assignments.store');
    Route::put('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update'])->name('teacher_assignments.update');
    Route::delete('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy'])->name('teacher_assignments.destroy');
    Route::post('/teacher-assignments/{teacherAssignment}/activate', [TeacherAssignmentController::class, 'activate'])->name('teacher_assignments.activate');
    Route::post('/teacher-assignments/{teacherAssignment}/deactivate', [TeacherAssignmentController::class, 'deactivate'])->name('teacher_assignments.deactivate');

    // Phase 9: Assessment Types
    Route::get('/assessments/types', [AssessmentTypeController::class, 'index'])->name('assessments.types.index');
    Route::post('/assessments/types', [AssessmentTypeController::class, 'store'])->name('assessments.types.store');
    Route::put('/assessments/types/{assessmentType}', [AssessmentTypeController::class, 'update'])->name('assessments.types.update');
    Route::delete('/assessments/types/{assessmentType}', [AssessmentTypeController::class, 'destroy'])->name('assessments.types.destroy');

    // Phase 9: Assessments
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
    Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');

    // Phase 9: Assessment Applicability
    Route::get('/assessments/{assessment}/applicability', [AssessmentApplicabilityController::class, 'index'])->name('assessments.applicability.index');
    Route::post('/assessments/{assessment}/applicability', [AssessmentApplicabilityController::class, 'store'])->name('assessments.applicability.store');
    Route::put('/assessments/{assessment}/applicability/{applicability}', [AssessmentApplicabilityController::class, 'update'])->name('assessments.applicability.update');
    Route::delete('/assessments/{assessment}/applicability/{applicability}', [AssessmentApplicabilityController::class, 'destroy'])->name('assessments.applicability.destroy');

    // Phase 9: Calculation Settings
    Route::get('/calculations/settings', [CalculationSettingController::class, 'index'])->name('calculations.settings.index');
    Route::post('/calculations/settings', [CalculationSettingController::class, 'store'])->name('calculations.settings.store');
    Route::put('/calculations/settings/{calculationSetting}', [CalculationSettingController::class, 'update'])->name('calculations.settings.update');
    Route::delete('/calculations/settings/{calculationSetting}', [CalculationSettingController::class, 'destroy'])->name('calculations.settings.destroy');

    // Phase 9: Report Configuration & Assessment Selections
    Route::get('/reports/configurations', [ReportConfigurationController::class, 'index'])->name('reports.configurations.index');
    Route::post('/reports/configurations', [ReportConfigurationController::class, 'store'])->name('reports.configurations.store');
    Route::put('/reports/configurations/{reportConfiguration}', [ReportConfigurationController::class, 'update'])->name('reports.configurations.update');
    Route::delete('/reports/configurations/{reportConfiguration}', [ReportConfigurationController::class, 'destroy'])->name('reports.configurations.destroy');
    Route::post('/reports/configurations/{reportConfiguration}/selections', [ReportAssessmentSelectionController::class, 'store'])->name('reports.selections.store');
    Route::post('/reports/configurations/{reportConfiguration}/selections/reorder', [ReportAssessmentSelectionController::class, 'reorder'])->name('reports.selections.reorder');
    Route::put('/reports/configurations/{reportConfiguration}/selections/{selection}', [ReportAssessmentSelectionController::class, 'update'])->name('reports.selections.update');
    Route::delete('/reports/configurations/{reportConfiguration}/selections/{selection}', [ReportAssessmentSelectionController::class, 'destroy'])->name('reports.selections.destroy');

    // Phase 10: Student Management, Admission Number & Import
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/import', [StudentController::class, 'importForm'])->name('students.import.form');
    Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
    Route::get('/students/template', [StudentController::class, 'template'])->name('students.template');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::get('/students/{student}/transfer', [StudentController::class, 'transferForm'])->name('students.transfer.form');
    Route::post('/students/{student}/transfer', [StudentController::class, 'transfer'])->name('students.transfer');
    Route::get('/student-subject-allocations/{studentAcademicRecord}', [StudentSubjectAllocationController::class, 'edit'])->name('student-subject-allocations.edit');
    Route::patch('/student-subject-allocations/{studentAcademicRecord}', [StudentSubjectAllocationController::class, 'update'])->name('student-subject-allocations.update');

    // Phase 11: Marks Entry & Management
    Route::get('/marks', [MarkController::class, 'index'])->name('marks.index');
    Route::post('/marks/batch-save', [MarkController::class, 'batchSave'])->name('marks.batch_save');

    // Phase 12: Term Attendance Management
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/batch-save', [AttendanceController::class, 'batchSave'])->name('attendance.batch_save');

    // Phase 13: Report Card & PDF Generation
    Route::get('/reports', [ReportGenerationController::class, 'index'])->name('reports.index');
    Route::post('/reports/preview', [ReportGenerationController::class, 'preview'])->name('reports.preview');
    Route::post('/reports/generate', [ReportGenerationController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/download/{generatedReport}', [GeneratedReportDownloadController::class, 'download'])->name('reports.download');
});
