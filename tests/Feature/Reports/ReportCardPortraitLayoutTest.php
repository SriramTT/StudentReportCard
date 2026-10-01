<?php

namespace Tests\Feature\Reports;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\CalculationMethod;
use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Enums\StudentPlacementStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\Attendance;
use App\Models\CalculationSetting;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 13 — Issue #8: A4 Landscape → A4 Portrait
 *
 * Verifies:
 *  A. CSS canvas emits "A4 portrait"
 *  B. BrowsershotReportGenerator uses landscape(false)
 *  C. Student info grid uses 3-column layout
 *  D. Summary container stacks vertically (portrait)
 *  E. Signatures use space-between
 *  F. Term report HTML: all required sections present
 *  G. Term report HTML: multiple assessment columns visible
 *  H. Signatures layout stable when images absent
 *  I. Historical Rev1 PDF remains untouched
 *  J. Final report HTML: required sections present
 *
 * Database: school_report_card_audit (phpunit.xml).
 */
class ReportCardPortraitLayoutTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected AcademicYear $year;
    protected Term $term1;
    protected SchoolClass $class;
    protected Section $section;

    // 5 subjects for a normal term scenario
    protected Subject $math;
    protected Subject $science;
    protected Subject $english;
    protected Subject $history;
    protected Subject $geography;

    // Class-subjects
    protected ClassSubject $csMath;
    protected ClassSubject $csScience;
    protected ClassSubject $csEnglish;
    protected ClassSubject $csHistory;
    protected ClassSubject $csGeography;

    // Assessment infrastructure
    protected AssessmentType $examType;
    protected Assessment $termExam;

    // Applicabilities — one per subject
    protected AssessmentApplicability $appMath;
    protected AssessmentApplicability $appScience;
    protected AssessmentApplicability $appEnglish;
    protected AssessmentApplicability $appHistory;
    protected AssessmentApplicability $appGeography;

    // Student
    protected Student $student;
    protected StudentAcademicRecord $sar;

    // Subject allocations
    protected StudentSubjectAllocation $ssaMath;
    protected StudentSubjectAllocation $ssaScience;
    protected StudentSubjectAllocation $ssaEnglish;
    protected StudentSubjectAllocation $ssaHistory;
    protected StudentSubjectAllocation $ssaGeography;

    // Report config
    protected ReportConfiguration $reportConfig;

    // ─── setUp ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Portrait Test Academy',
            'pass_mark'   => 35.00,
        ]);

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id'       => $adminRole->id,
            'username'      => 'prt_' . substr(uniqid(), -8),
            'password_hash' => Hash::make('password'),
            'display_name'  => 'Portrait Test Admin',
            'is_active'     => true,
        ]);

        // academic_years.name is varchar(20) — keep short
        $this->year = AcademicYear::create([
            'name'       => 'PRT-' . substr(uniqid(), -8),
            'start_date' => '2026-06-01',
            'end_date'   => '2027-04-30',
            'status'     => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->term1 = Term::create([
            'academic_year_id' => $this->year->id,
            'name'             => 'Term 1',
            'sequence_no'      => 1,
            'is_active'        => true,
        ]);

        $this->class   = SchoolClass::create(['name' => 'PRT Class ' . rand(1, 99)]);
        $this->section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id'         => $this->class->id,
            'name'             => 'A',
        ]);

        // 5 subjects — normal single-page term scenario
        $uid = substr(uniqid(), -5);
        $this->math      = Subject::create(['name' => 'Mathematics',       'code' => 'PM' . $uid . '1']);
        $this->science   = Subject::create(['name' => 'Science',           'code' => 'PM' . $uid . '2']);
        $this->english   = Subject::create(['name' => 'English',           'code' => 'PM' . $uid . '3']);
        $this->history   = Subject::create(['name' => 'History',           'code' => 'PM' . $uid . '4']);
        $this->geography = Subject::create(['name' => 'Geography Studies', 'code' => 'PM' . $uid . '5']);

        $subjects = [$this->math, $this->science, $this->english, $this->history, $this->geography];

        foreach ($subjects as $subj) {
            ClassSubject::create([
                'academic_year_id'      => $this->year->id,
                'class_id'              => $this->class->id,
                'subject_id'            => $subj->id,
                'subject_name_snapshot' => $subj->name,
                'is_active'             => true,
            ]);
        }

        $this->csMath      = ClassSubject::where('subject_id', $this->math->id)->where('class_id', $this->class->id)->firstOrFail();
        $this->csScience   = ClassSubject::where('subject_id', $this->science->id)->where('class_id', $this->class->id)->firstOrFail();
        $this->csEnglish   = ClassSubject::where('subject_id', $this->english->id)->where('class_id', $this->class->id)->firstOrFail();
        $this->csHistory   = ClassSubject::where('subject_id', $this->history->id)->where('class_id', $this->class->id)->firstOrFail();
        $this->csGeography = ClassSubject::where('subject_id', $this->geography->id)->where('class_id', $this->class->id)->firstOrFail();

        CalculationSetting::create([
            'academic_year_id'   => $this->year->id,
            'class_id'           => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $this->examType = AssessmentType::firstOrCreate(['name' => 'PRT Term Exam']);

        $this->termExam = Assessment::create([
            'assessment_type_id' => $this->examType->id,
            'academic_year_id'   => $this->year->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Term 1 Exam',
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        // Applicabilities
        $this->appMath      = AssessmentApplicability::create(['assessment_id' => $this->termExam->id, 'class_subject_id' => $this->csMath->id,      'maximum_marks' => 100.00]);
        $this->appScience   = AssessmentApplicability::create(['assessment_id' => $this->termExam->id, 'class_subject_id' => $this->csScience->id,   'maximum_marks' => 100.00]);
        $this->appEnglish   = AssessmentApplicability::create(['assessment_id' => $this->termExam->id, 'class_subject_id' => $this->csEnglish->id,   'maximum_marks' => 100.00]);
        $this->appHistory   = AssessmentApplicability::create(['assessment_id' => $this->termExam->id, 'class_subject_id' => $this->csHistory->id,   'maximum_marks' => 100.00]);
        $this->appGeography = AssessmentApplicability::create(['assessment_id' => $this->termExam->id, 'class_subject_id' => $this->csGeography->id, 'maximum_marks' => 100.00]);

        $this->student = Student::create([
            'admission_number' => 'PRT-' . substr(uniqid(), -8),
            'student_name'     => 'Portrait Student Test',
        ]);

        $this->sar = StudentAcademicRecord::create([
            'student_id'       => $this->student->id,
            'academic_year_id' => $this->year->id,
            'class_id'         => $this->class->id,
            'section_id'       => $this->section->id,
            'roll_number'      => 42,
            'status'           => StudentPlacementStatus::ACTIVE,
            'effective_from'   => '2026-06-01',
        ]);

        // Subject allocations
        $this->ssaMath      = StudentSubjectAllocation::create(['student_academic_record_id' => $this->sar->id, 'class_subject_id' => $this->csMath->id,      'effective_from' => '2026-06-01', 'is_active' => true]);
        $this->ssaScience   = StudentSubjectAllocation::create(['student_academic_record_id' => $this->sar->id, 'class_subject_id' => $this->csScience->id,   'effective_from' => '2026-06-01', 'is_active' => true]);
        $this->ssaEnglish   = StudentSubjectAllocation::create(['student_academic_record_id' => $this->sar->id, 'class_subject_id' => $this->csEnglish->id,   'effective_from' => '2026-06-01', 'is_active' => true]);
        $this->ssaHistory   = StudentSubjectAllocation::create(['student_academic_record_id' => $this->sar->id, 'class_subject_id' => $this->csHistory->id,   'effective_from' => '2026-06-01', 'is_active' => true]);
        $this->ssaGeography = StudentSubjectAllocation::create(['student_academic_record_id' => $this->sar->id, 'class_subject_id' => $this->csGeography->id, 'effective_from' => '2026-06-01', 'is_active' => true]);

        // Marks — using correct Mark fillable fields
        $this->createMark($this->ssaMath,      $this->appMath,      75.00);
        $this->createMark($this->ssaScience,   $this->appScience,   82.00);
        $this->createMark($this->ssaEnglish,   $this->appEnglish,   68.00);
        $this->createMark($this->ssaHistory,   $this->appHistory,   91.00);
        $this->createMark($this->ssaGeography, $this->appGeography, 77.00);

        // Attendance
        Attendance::create([
            'student_academic_record_id' => $this->sar->id,
            'term_id'                    => $this->term1->id,
            'days_attended'              => 68,
            'total_working_days'         => 75,
            'entered_by_user_id'         => $this->admin->id,
            'updated_by_user_id'         => $this->admin->id,
        ]);

        // Report configuration
        $this->reportConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name'             => 'PRT Term Config',
            'report_type'      => ReportType::TERM,
            'is_active'        => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $this->reportConfig->id,
            'assessment_id'           => $this->termExam->id,
            'is_displayed'            => true,
            'display_order'           => 1,
        ]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    protected function createMark(
        StudentSubjectAllocation $ssa,
        AssessmentApplicability $app,
        float $value
    ): Mark {
        return Mark::create([
            'student_academic_record_id'  => $this->sar->id,
            'student_subject_allocation_id' => $ssa->id,
            'assessment_applicability_id' => $app->id,
            'mark_value'                  => number_format($value, 2, '.', ''),
            'result_status'               => MarkResultStatus::NUMERIC,
            'entered_by_user_id'          => $this->admin->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // A. CSS @page directive — file-level assertion (no DB needed)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_report_canvas_css_specifies_a4_portrait(): void
    {
        $canvasPath = resource_path('views/reports/pdf/layouts/report-canvas.blade.php');
        $this->assertFileExists($canvasPath);

        $css = file_get_contents($canvasPath);

        $this->assertStringContainsString(
            'size: A4 portrait',
            $css,
            'report-canvas.blade.php must specify A4 portrait in @page'
        );
        $this->assertStringNotContainsString(
            'size: A4 landscape',
            $css,
            'report-canvas.blade.php must NOT specify A4 landscape'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // B. BrowsershotReportGenerator orientation — file-level assertion
    // ──────────────────────────────────────────────────────────────────────────

    public function test_browsershot_generator_uses_portrait_orientation(): void
    {
        $generatorPath = app_path('Services/Report/Generators/BrowsershotReportGenerator.php');
        $this->assertFileExists($generatorPath);

        $source = file_get_contents($generatorPath);

        $this->assertStringContainsString(
            '->landscape(false)',
            $source,
            'BrowsershotReportGenerator must use ->landscape(false)'
        );
        $this->assertStringNotContainsString(
            '->landscape(true)',
            $source,
            'BrowsershotReportGenerator must NOT use ->landscape(true)'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // C. Student info grid — 3 columns (file-level assertion)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_student_info_grid_uses_three_columns(): void
    {
        $canvas = file_get_contents(resource_path('views/reports/pdf/layouts/report-canvas.blade.php'));

        $this->assertStringContainsString(
            'repeat(3, minmax(0, 1fr))',
            $canvas,
            'Portrait layout must use 3-column student info grid'
        );
        $this->assertStringNotContainsString(
            'repeat(4, 1fr)',
            $canvas,
            'Portrait layout must not retain the old 4-column grid'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // D. Summary container stacked — file-level assertion
    // ──────────────────────────────────────────────────────────────────────────

    public function test_summary_container_css_is_stacked_for_portrait(): void
    {
        $canvas = file_get_contents(resource_path('views/reports/pdf/layouts/report-canvas.blade.php'));

        $this->assertStringContainsString(
            'flex-direction: column',
            $canvas,
            'summary-container must stack vertically (flex-direction: column) in portrait'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // E. Signatures space-between — file-level assertion
    // ──────────────────────────────────────────────────────────────────────────

    public function test_signatures_container_css_uses_space_between(): void
    {
        $canvas = file_get_contents(resource_path('views/reports/pdf/layouts/report-canvas.blade.php'));

        $this->assertStringContainsString(
            'justify-content: space-between',
            $canvas,
            'signatures-container must use justify-content: space-between in portrait'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // F. Term report rendered HTML — all required sections
    // ──────────────────────────────────────────────────────────────────────────

    public function test_term_report_html_contains_all_required_sections(): void
    {
        $service = app(\App\Services\Report\ReportGenerationService::class);
        $html = $service->preview(
            sarId: $this->sar->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfigurationId: $this->reportConfig->id
        );

        // Portrait orientation
        $this->assertStringContainsString('A4 portrait', $html, 'HTML must declare A4 portrait');
        $this->assertStringNotContainsString('A4 landscape', $html, 'HTML must not contain A4 landscape');

        // Student info — all 6 fields
        $this->assertStringContainsString('Portrait Student Test', $html, 'Student name must appear');
        $this->assertStringContainsString('PRT-', $html, 'Admission number must appear');
        $this->assertStringContainsString('Term 1', $html, 'Term/Report Period must appear');
        $this->assertStringContainsString($this->year->name, $html, 'Academic year must appear');

        // All 5 subjects
        $this->assertStringContainsString('Mathematics', $html,      'Math subject must appear');
        $this->assertStringContainsString('Science', $html,          'Science subject must appear');
        $this->assertStringContainsString('English', $html,          'English subject must appear');
        $this->assertStringContainsString('History', $html,          'History subject must appear');
        $this->assertStringContainsString('Geography', $html,        'Geography subject must appear');

        // Assessment column header
        $this->assertStringContainsString('Term 1 Exam', $html, 'Assessment column header must appear');

        // Table structure
        $this->assertStringContainsString('TOTAL', $html,   'TOTAL row must appear');
        $this->assertStringContainsString('Term %', $html,  'Term % column must appear');
        $this->assertStringContainsString('Result', $html,  'Result column must appear');

        // Attendance
        $this->assertStringContainsString('Term Attendance Summary', $html, 'Attendance box must appear');
        $this->assertStringContainsString('Days Attended', $html, 'Days Attended label must appear');
        $this->assertStringContainsString('68', $html, 'Days attended value must appear');
        $this->assertStringContainsString('75', $html, 'Total working days value must appear');

        // Overall result
        $this->assertStringContainsString('Overall Result', $html, 'Overall Result must appear');

        // Portrait summary structure
        $this->assertStringContainsString('summary-container', $html, 'summary-container must appear');
        $this->assertStringContainsString('summary-top-row', $html,   'summary-top-row (portrait stacking) must appear');

        // Signatures
        $this->assertStringContainsString('signatures-container', $html, 'signatures-container must appear');
        $this->assertStringContainsString('Class Teacher', $html, 'Class Teacher label must appear');
        $this->assertStringContainsString('Principal', $html,     'Principal label must appear');

        // Footer
        $this->assertStringContainsString('Report Revision', $html,         'Report revision must appear');
        $this->assertStringContainsString('Official School Report Card', $html, 'Footer text must appear');
        $this->assertStringContainsString('report-footer', $html, 'report-footer class must appear');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // G. Term report — multiple assessment columns all visible
    // ──────────────────────────────────────────────────────────────────────────

    public function test_term_report_html_shows_all_assessment_columns_when_multiple(): void
    {
        // Add a second assessment
        $unitTest = Assessment::create([
            'assessment_type_id' => $this->examType->id,
            'academic_year_id'   => $this->year->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Unit Test 1',
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $appUT1 = AssessmentApplicability::create(['assessment_id' => $unitTest->id, 'class_subject_id' => $this->csMath->id,      'maximum_marks' => 25.00]);
        $appUT2 = AssessmentApplicability::create(['assessment_id' => $unitTest->id, 'class_subject_id' => $this->csScience->id,   'maximum_marks' => 25.00]);
        $appUT3 = AssessmentApplicability::create(['assessment_id' => $unitTest->id, 'class_subject_id' => $this->csEnglish->id,   'maximum_marks' => 25.00]);
        $appUT4 = AssessmentApplicability::create(['assessment_id' => $unitTest->id, 'class_subject_id' => $this->csHistory->id,   'maximum_marks' => 25.00]);
        $appUT5 = AssessmentApplicability::create(['assessment_id' => $unitTest->id, 'class_subject_id' => $this->csGeography->id, 'maximum_marks' => 25.00]);

        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaMath->id,      'assessment_applicability_id' => $appUT1->id, 'mark_value' => '20.00', 'result_status' => MarkResultStatus::NUMERIC, 'entered_by_user_id' => $this->admin->id]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaScience->id,   'assessment_applicability_id' => $appUT2->id, 'mark_value' => '18.00', 'result_status' => MarkResultStatus::NUMERIC, 'entered_by_user_id' => $this->admin->id]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaEnglish->id,   'assessment_applicability_id' => $appUT3->id, 'mark_value' => '22.00', 'result_status' => MarkResultStatus::NUMERIC, 'entered_by_user_id' => $this->admin->id]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaHistory->id,   'assessment_applicability_id' => $appUT4->id, 'mark_value' => '21.00', 'result_status' => MarkResultStatus::NUMERIC, 'entered_by_user_id' => $this->admin->id]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaGeography->id, 'assessment_applicability_id' => $appUT5->id, 'mark_value' => '19.00', 'result_status' => MarkResultStatus::NUMERIC, 'entered_by_user_id' => $this->admin->id]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $this->reportConfig->id,
            'assessment_id'           => $unitTest->id,
            'is_displayed'            => true,
            'display_order'           => 2,
        ]);

        $service = app(\App\Services\Report\ReportGenerationService::class);
        $html = $service->preview(
            sarId: $this->sar->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfigurationId: $this->reportConfig->id
        );

        $this->assertStringContainsString('Term 1 Exam', $html, 'First assessment column must appear');
        $this->assertStringContainsString('Unit Test 1',  $html, 'Second assessment column must appear');
        $this->assertStringContainsString('A4 portrait',  $html, 'Portrait directive must remain with multiple assessments');
        $this->assertStringContainsString('summary-top-row', $html, 'Portrait summary structure must remain');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // H. Missing signatures do not collapse layout
    // ──────────────────────────────────────────────────────────────────────────

    public function test_signatures_container_stable_when_signatures_absent(): void
    {
        $service = app(\App\Services\Report\ReportGenerationService::class);
        $html = $service->preview(
            sarId: $this->sar->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfigurationId: $this->reportConfig->id
        );

        // Placeholder must render when no signature images are uploaded
        $this->assertStringContainsString('signature-image-placeholder', $html,
            'Placeholder must appear when signature images are absent');

        $this->assertStringContainsString('Class Teacher', $html,        'Class Teacher label must remain');
        $this->assertStringContainsString('Principal', $html,            'Principal label must remain');
        $this->assertStringContainsString('signatures-container', $html, 'signatures-container must remain even without images');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // I. Historical immutability — existing Rev 1 PDF unchanged
    // ──────────────────────────────────────────────────────────────────────────

    public function test_historical_rev1_pdf_remains_untouched(): void
    {
        $historicalPath = storage_path(
            'app/private/reports/17/35/46/272/term/rev_1/Sowmiya_S_1_A_SVS-008_Term_1.pdf'
        );

        if (! file_exists($historicalPath)) {
            $this->markTestSkipped('Historical Rev 1 PDF not present in this environment.');
        }

        $this->assertFileExists($historicalPath, 'Historical Rev 1 PDF must still exist');

        // Original landscape MediaBox width was ~841pt; still must be present in binary
        $binary = file_get_contents($historicalPath);
        $this->assertStringContainsString('841', $binary,
            'Historical PDF must still contain landscape width (~841pt) — file must be unchanged');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // J. Final report rendered HTML — all required sections
    // ──────────────────────────────────────────────────────────────────────────

    public function test_final_report_html_contains_required_sections(): void
    {
        $finalConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name'             => 'PRT Final Config',
            'report_type'      => ReportType::FINAL,
            'is_active'        => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $finalConfig->id,
            'assessment_id'           => $this->termExam->id,
            'is_displayed'            => true,
            'display_order'           => 1,
        ]);

        $service = app(\App\Services\Report\ReportGenerationService::class);
        $html = $service->preview(
            sarId: $this->sar->id,
            reportType: 'final',
            termId: null,
            reportConfigurationId: $finalConfig->id
        );

        // Portrait directive
        $this->assertStringContainsString('A4 portrait', $html, 'Final report must declare A4 portrait');

        // Student info
        $this->assertStringContainsString('Portrait Student Test', $html, 'Student name must appear');
        $this->assertStringContainsString($this->year->name, $html,       'Academic year must appear');

        // Term summary section
        $this->assertStringContainsString('Term Reports Summary', $html, 'Term Reports Summary section must appear');

        // Portrait summary structure
        $this->assertStringContainsString('summary-container', $html);
        $this->assertStringContainsString('summary-top-row', $html, 'Final report must use portrait summary-top-row');

        // Signatures
        $this->assertStringContainsString('signatures-container', $html);
        $this->assertStringContainsString('Class Teacher', $html);
        $this->assertStringContainsString('Principal', $html);

        // Footer
        $this->assertStringContainsString('Report Revision', $html);
        $this->assertStringContainsString('Official School Report Card', $html);
    }
}
