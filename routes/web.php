<?php

use App\Http\Controllers\Academic\AcademicYearController;
use App\Http\Controllers\Academic\ClassSubjectController;
use App\Http\Controllers\Academic\SchoolClassController;
use App\Http\Controllers\Academic\SectionController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\TermController;
use App\Http\Controllers\Assessments\AssessmentApplicabilityController;
use App\Http\Controllers\Assessments\AssessmentController;
use App\Http\Controllers\Assessments\AssessmentTypeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\Calculations\CalculationSettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Reports\ReportAssessmentSelectionController;
use App\Http\Controllers\Reports\ReportConfigurationController;
use App\Http\Controllers\Settings\SchoolSettingController;
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

    // Phase 9: Academic Years
    Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic_years.index');
    Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic_years.store');
    Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic_years.update');
    Route::post('/academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academic_years.close');
    Route::post('/academic-years/{academicYear}/reopen', [AcademicYearController::class, 'reopen'])->name('academic_years.reopen');

    // Phase 9: Terms (Dynamic N-Terms)
    Route::get('/terms', [TermController::class, 'index'])->name('terms.index');
    Route::post('/terms', [TermController::class, 'store'])->name('terms.store');
    Route::put('/terms/{term}', [TermController::class, 'update'])->name('terms.update');

    // Phase 9: Classes
    Route::get('/classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::post('/classes', [SchoolClassController::class, 'store'])->name('classes.store');
    Route::put('/classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');

    // Phase 9: Sections
    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');

    // Phase 9: Subjects
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');

    // Phase 9: Class Subjects
    Route::get('/class-subjects', [ClassSubjectController::class, 'index'])->name('class_subjects.index');
    Route::post('/class-subjects', [ClassSubjectController::class, 'store'])->name('class_subjects.store');
    Route::put('/class-subjects/{classSubject}', [ClassSubjectController::class, 'update'])->name('class_subjects.update');

    // Phase 9: Assessment Types
    Route::get('/assessments/types', [AssessmentTypeController::class, 'index'])->name('assessments.types.index');
    Route::post('/assessments/types', [AssessmentTypeController::class, 'store'])->name('assessments.types.store');
    Route::put('/assessments/types/{assessmentType}', [AssessmentTypeController::class, 'update'])->name('assessments.types.update');

    // Phase 9: Assessments
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');

    // Phase 9: Assessment Applicability
    Route::get('/assessments/{assessment}/applicability', [AssessmentApplicabilityController::class, 'index'])->name('assessments.applicability.index');
    Route::post('/assessments/{assessment}/applicability', [AssessmentApplicabilityController::class, 'store'])->name('assessments.applicability.store');
    Route::put('/assessments/{assessment}/applicability/{applicability}', [AssessmentApplicabilityController::class, 'update'])->name('assessments.applicability.update');

    // Phase 9: Calculation Settings
    Route::get('/calculations/settings', [CalculationSettingController::class, 'index'])->name('calculations.settings.index');
    Route::post('/calculations/settings', [CalculationSettingController::class, 'store'])->name('calculations.settings.store');
    Route::put('/calculations/settings/{calculationSetting}', [CalculationSettingController::class, 'update'])->name('calculations.settings.update');

    // Phase 9: Report Configuration & Assessment Selections
    Route::get('/reports/configurations', [ReportConfigurationController::class, 'index'])->name('reports.configurations.index');
    Route::post('/reports/configurations', [ReportConfigurationController::class, 'store'])->name('reports.configurations.store');
    Route::put('/reports/configurations/{reportConfiguration}', [ReportConfigurationController::class, 'update'])->name('reports.configurations.update');
    Route::post('/reports/configurations/{reportConfiguration}/selections', [ReportAssessmentSelectionController::class, 'store'])->name('reports.selections.store');
    Route::put('/reports/configurations/{reportConfiguration}/selections/{selection}', [ReportAssessmentSelectionController::class, 'update'])->name('reports.selections.update');
});
