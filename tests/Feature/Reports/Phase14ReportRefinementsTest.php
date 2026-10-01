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
use App\Services\Report\ReportCompletionService;
use App\Services\Report\ReportDataPreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase14ReportRefinementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected AcademicYear $year;
    protected Term $term1;
    protected Term $term2;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $tamil;
    protected Subject $english;
    protected Subject $science;
    protected ClassSubject $csTamil;
    protected ClassSubject $csEnglish;
    protected ClassSubject $csScience;
    protected StudentAcademicRecord $sar;
    protected StudentSubjectAllocation $ssaTamil;
    protected StudentSubjectAllocation $ssaEnglish;
    protected StudentSubjectAllocation $ssaScience;
    protected CalculationSetting $calcSetting;
    protected AssessmentType $examType;
    protected AssessmentType $unitTestType;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['name' => 'Administrator']);

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_phase14_test'],
            [
                'email' => 'admin_p14@example.com',
                'password_hash' => Hash::make('Secret123!'),
                'display_name' => 'Phase 14 Admin',
                'role_id' => $roleAdmin->id,
                'is_active' => true,
            ]
        );

        $this->year = AcademicYear::firstOrCreate(
            ['name' => '2026-2027 P14'],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-04-30',
                'status' => AcademicYearStatus::OPEN,
            ]
        );

        $this->term1 = Term::firstOrCreate(
            ['academic_year_id' => $this->year->id, 'sequence_no' => 1],
            ['name' => 'Term 1']
        );

        $this->term2 = Term::firstOrCreate(
            ['academic_year_id' => $this->year->id, 'sequence_no' => 2],
            ['name' => 'Term 2']
        );

        $this->class = SchoolClass::firstOrCreate(
            ['name' => 'Class 10 P14'],
            ['is_active' => true]
        );

        $this->section = Section::firstOrCreate([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A',
        ]);

        $this->tamil = Subject::firstOrCreate(['code' => 'TAM14'], ['name' => 'Tamil', 'is_active' => true]);
        $this->english = Subject::firstOrCreate(['code' => 'ENG14'], ['name' => 'English', 'is_active' => true]);
        $this->science = Subject::firstOrCreate(['code' => 'SCI14'], ['name' => 'Science', 'is_active' => true]);

        $this->csTamil = ClassSubject::firstOrCreate([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->tamil->id,
        ], [
            'subject_name_snapshot' => 'Tamil',
            'is_active' => true,
        ]);

        $this->csEnglish = ClassSubject::firstOrCreate([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->english->id,
        ], [
            'subject_name_snapshot' => 'English',
            'is_active' => true,
        ]);

        $this->csScience = ClassSubject::firstOrCreate([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->science->id,
        ], [
            'subject_name_snapshot' => 'Science',
            'is_active' => true,
        ]);

        $student = Student::firstOrCreate(
            ['admission_number' => 'SVS-P14-001'],
            ['student_name' => 'Suresh P14']
        );

        $this->sar = StudentAcademicRecord::firstOrCreate([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
        ], [
            'roll_number' => 101,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->ssaTamil = StudentSubjectAllocation::firstOrCreate([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csTamil->id,
        ], ['effective_from' => '2026-06-01', 'is_active' => true]);

        $this->ssaEnglish = StudentSubjectAllocation::firstOrCreate([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csEnglish->id,
        ], ['effective_from' => '2026-06-01', 'is_active' => true]);

        $this->ssaScience = StudentSubjectAllocation::firstOrCreate([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csScience->id,
        ], ['effective_from' => '2026-06-01', 'is_active' => true]);

        $this->calcSetting = CalculationSetting::updateOrCreate(
            ['academic_year_id' => $this->year->id, 'class_id' => $this->class->id],
            ['calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE]
        );

        $this->examType = AssessmentType::firstOrCreate(['name' => 'Term Exam']);
        $this->unitTestType = AssessmentType::firstOrCreate(['name' => 'Weekly Test']);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Test Academy P14',
            'pass_mark' => 35.00,
        ]);
    }

    /**
     * Test 1: All Annual Exam subjects pass -> Annual Exam total percentage & result is PASS, Final Overall Result is PASS.
     */
    public function test_all_annual_exam_subjects_pass_produces_pass_and_correct_total_percentage(): void
    {
        $annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Annual Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appTam = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csTamil->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        $appEng = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csEnglish->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        $appSci = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csScience->id, 'maximum_marks' => 100.00, 'is_active' => true]);

        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaTamil->id, 'assessment_applicability_id' => $appTam->id, 'mark_value' => 60.00, 'result_status' => MarkResultStatus::NUMERIC]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaEnglish->id, 'assessment_applicability_id' => $appEng->id, 'mark_value' => 70.00, 'result_status' => MarkResultStatus::NUMERIC]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaScience->id, 'assessment_applicability_id' => $appSci->id, 'mark_value' => 80.00, 'result_status' => MarkResultStatus::NUMERIC]);

        $config = ReportConfiguration::create(['academic_year_id' => $this->year->id, 'name' => 'Final Report', 'report_type' => ReportType::FINAL, 'is_active' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $annualExam->id, 'display_order' => 1, 'is_displayed' => true]);

        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);

        $this->assertNotNull($payload->annualExamData);
        $this->assertEquals(70.00, $payload->annualExamData['percentage']);
        $this->assertSame('70.00%', $payload->annualExamData['formatted_percentage']);
        $this->assertSame('PASS', $payload->annualExamData['result']);
        $this->assertSame('PASS', $payload->overallResult);

        $html = view('reports.pdf.final-report', ['payload' => $payload])->render();
        $this->assertStringContainsString('70.00%', $html);
        $this->assertStringContainsString('PASS', $html);
    }

    /**
     * Test 2: One Annual Exam subject fails -> Annual Exam total result is FAIL and Final Overall Result is FAIL.
     */
    public function test_one_annual_exam_subject_fails_produces_fail(): void
    {
        $annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Annual Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appTam = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csTamil->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        $appEng = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csEnglish->id, 'maximum_marks' => 100.00, 'is_active' => true]);

        // Tamil 70% (PASS), English 30% (FAIL, threshold 35%)
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaTamil->id, 'assessment_applicability_id' => $appTam->id, 'mark_value' => 70.00, 'result_status' => MarkResultStatus::NUMERIC]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaEnglish->id, 'assessment_applicability_id' => $appEng->id, 'mark_value' => 30.00, 'result_status' => MarkResultStatus::NUMERIC]);

        $config = ReportConfiguration::create(['academic_year_id' => $this->year->id, 'name' => 'Final Report', 'report_type' => ReportType::FINAL, 'is_active' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $annualExam->id, 'display_order' => 1, 'is_displayed' => true]);

        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);

        $this->assertSame('FAIL', $payload->annualExamData['result']);
        $this->assertSame('FAIL', $payload->overallResult);
    }

    /**
     * Test 3: Term failure + all Annual Exam subjects pass -> Final Overall Result is PASS (Suresh R scenario).
     */
    public function test_term_failure_does_not_fail_final_overall_result_when_annual_exam_passes(): void
    {
        // 1. Term 1 Exam - Science fails
        $t1Exam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 1 Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $appT1Sci = AssessmentApplicability::create(['assessment_id' => $t1Exam->id, 'class_subject_id' => $this->csScience->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaScience->id, 'assessment_applicability_id' => $appT1Sci->id, 'mark_value' => 20.00, 'result_status' => MarkResultStatus::NUMERIC]);

        // 2. Annual Exam - All subjects pass
        $annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Annual Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $appAnnualSci = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csScience->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaScience->id, 'assessment_applicability_id' => $appAnnualSci->id, 'mark_value' => 75.00, 'result_status' => MarkResultStatus::NUMERIC]);

        $config = ReportConfiguration::create(['academic_year_id' => $this->year->id, 'name' => 'Final Report', 'report_type' => ReportType::FINAL, 'is_active' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $annualExam->id, 'display_order' => 1, 'is_displayed' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $t1Exam->id, 'display_order' => 2, 'is_displayed' => true]);

        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);

        // Term summary still displays Term 1 Science as FAIL
        $sciRow = collect($payload->subjectRows)->firstWhere('subjectId', $this->science->id);
        $this->assertNotNull($sciRow);
        $t1Summary = $sciRow->termSummaries[$this->term1->id] ?? null;
        $this->assertSame('FAIL', $t1Summary['status']);

        // But Annual Exam result is PASS, and Final Overall Result is PASS!
        $this->assertSame('PASS', $payload->annualExamData['result']);
        $this->assertSame('PASS', $payload->overallResult);
    }

    /**
     * Test 4 & 5: Calculation Method 1 (Average Pct) vs Method 2 (Combined Marks) with differing maximum marks.
     */
    public function test_annual_exam_respects_configured_calculation_method(): void
    {
        $annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Annual Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Subject 1: max 50, mark 25 (50.00%)
        $appTam = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csTamil->id, 'maximum_marks' => 50.00, 'is_active' => true]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaTamil->id, 'assessment_applicability_id' => $appTam->id, 'mark_value' => 25.00, 'result_status' => MarkResultStatus::NUMERIC]);

        // Subject 2: max 100, mark 80 (80.00%)
        $appEng = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csEnglish->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaEnglish->id, 'assessment_applicability_id' => $appEng->id, 'mark_value' => 80.00, 'result_status' => MarkResultStatus::NUMERIC]);

        $config = ReportConfiguration::create(['academic_year_id' => $this->year->id, 'name' => 'Final Report', 'report_type' => ReportType::FINAL, 'is_active' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $annualExam->id, 'display_order' => 1, 'is_displayed' => true]);

        $prepService = app(ReportDataPreparationService::class);

        // Case A: Method 1 (Average of Percentages): (50 + 80) / 2 = 65.00%
        $this->calcSetting->update(['calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE]);
        $payload1 = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);
        $this->assertEquals(65.00, $payload1->annualExamData['percentage']);
        $this->assertSame('65.00%', $payload1->annualExamData['formatted_percentage']);

        // Case B: Method 2 (Combined Marks): (25 + 80) / (50 + 100) = 105 / 150 = 70.00%
        $this->calcSetting->update(['calculation_method' => CalculationMethod::COMBINED_MARKS]);
        $payload2 = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);
        $this->assertEquals(70.00, $payload2->annualExamData['percentage']);
        $this->assertSame('70.00%', $payload2->annualExamData['formatted_percentage']);
    }

    /**
     * Test 6: Pass-mark boundary test: 35.00% is PASS, 34.99% is FAIL, 35.01% is PASS.
     */
    public function test_pass_mark_boundary_conditions(): void
    {
        $annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Annual Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $appTam = AssessmentApplicability::create(['assessment_id' => $annualExam->id, 'class_subject_id' => $this->csTamil->id, 'maximum_marks' => 100.00, 'is_active' => true]);
        $mark = Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaTamil->id, 'assessment_applicability_id' => $appTam->id, 'mark_value' => 35.00, 'result_status' => MarkResultStatus::NUMERIC]);

        $config = ReportConfiguration::create(['academic_year_id' => $this->year->id, 'name' => 'Final Report', 'report_type' => ReportType::FINAL, 'is_active' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $annualExam->id, 'display_order' => 1, 'is_displayed' => true]);

        $prepService = app(ReportDataPreparationService::class);

        // Exactly 35.00 -> PASS
        $p1 = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);
        $this->assertSame('PASS', $p1->annualExamData['result']);

        // 34.99 -> FAIL
        $mark->update(['mark_value' => 34.99]);
        $p2 = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);
        $this->assertSame('FAIL', $p2->annualExamData['result']);

        // 35.01 -> PASS
        $mark->update(['mark_value' => 35.01]);
        $p3 = $prepService->prepareReportData($this->sar, ReportType::FINAL, null, 1, null, $config);
        $this->assertSame('PASS', $p3->annualExamData['result']);
    }

    /**
     * Test 7: Dynamic Mid-Term Assessment Report: Only applicable subjects are displayed (Janarthanan V scenario).
     * If Weekly Test is configured only for Tamil, only Tamil appears, no phantom 460.00 max, and overall result is complete PASS.
     */
    public function test_dynamic_mid_term_subject_applicability_excludes_unconfigured_subjects(): void
    {
        $weeklyTest = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->unitTestType->id,
            'name' => 'Weekly Test 1',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // ONLY Tamil is configured with max marks 60.00
        $appTam = AssessmentApplicability::create([
            'assessment_id' => $weeklyTest->id,
            'class_subject_id' => $this->csTamil->id,
            'maximum_marks' => 60.00,
            'is_active' => true,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaTamil->id,
            'assessment_applicability_id' => $appTam->id,
            'mark_value' => 59.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        $config = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Weekly Test - 1',
            'report_type' => ReportType::EXAM,
            'is_active' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $weeklyTest->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        // Verify completion service sees this marksheet as COMPLETE (only Tamil is applicable and entered)
        $completionService = app(ReportCompletionService::class);
        $this->assertTrue($completionService->isComplete($this->sar->id, 'exam', null, $config));

        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData($this->sar, ReportType::EXAM, null, 1, null, $config);

        // 1. Only Tamil appears in subject rows! English and Science MUST NOT appear.
        $this->assertCount(1, $payload->subjectRows);
        $this->assertSame('Tamil', $payload->subjectRows[0]->subjectName);
        $this->assertEquals(98.33, $payload->subjectRows[0]->termPercentage);
        $this->assertSame('PASS', $payload->subjectRows[0]->termStatus);

        // 2. Total row reflects only Tamil (59.00 / 60.00), NOT 460.00
        $colTotal = $payload->assessmentColumnTotals[$weeklyTest->id] ?? null;
        $this->assertNotNull($colTotal);
        $this->assertEquals(59.00, $colTotal['obtained']);
        $this->assertEquals(60.00, $colTotal['maximum']);
        $this->assertSame('59.00 / 60.00', $colTotal['display']);

        // 3. Overall result is PASS, not Incomplete!
        $this->assertSame('PASS', $payload->overallResult);
        $this->assertEquals(98.33, $payload->overallPercentage);
        $this->assertSame('98.33%', $payload->formattedOverallPercentage);

        // 4. Render Blade and confirm no phantom N/A rows or 460.00 max
        $html = view('reports.pdf.term-report', ['payload' => $payload])->render();
        $this->assertStringContainsString('Tamil', $html);
        $this->assertStringNotContainsString('English', $html);
        $this->assertStringNotContainsString('Science', $html);
        $this->assertStringNotContainsString('460.00', $html);
        $this->assertStringContainsString('60.00', $html);
    }

    /**
     * Test 8: Multiple displayed assessments with different subject applicability (union of applicable subjects).
     */
    public function test_multiple_assessments_union_of_applicable_subjects(): void
    {
        $asmtA = Assessment::create(['academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'assessment_type_id' => $this->unitTestType->id, 'name' => 'Test A', 'status' => AssessmentStatus::ACTIVE]);
        $asmtB = Assessment::create(['academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'assessment_type_id' => $this->unitTestType->id, 'name' => 'Test B', 'status' => AssessmentStatus::ACTIVE]);

        // Asmt A applies to Tamil & English
        $appA_Tam = AssessmentApplicability::create(['assessment_id' => $asmtA->id, 'class_subject_id' => $this->csTamil->id, 'maximum_marks' => 50.00, 'is_active' => true]);
        $appA_Eng = AssessmentApplicability::create(['assessment_id' => $asmtA->id, 'class_subject_id' => $this->csEnglish->id, 'maximum_marks' => 50.00, 'is_active' => true]);

        // Asmt B applies to Tamil & Science
        $appB_Tam = AssessmentApplicability::create(['assessment_id' => $asmtB->id, 'class_subject_id' => $this->csTamil->id, 'maximum_marks' => 50.00, 'is_active' => true]);
        $appB_Sci = AssessmentApplicability::create(['assessment_id' => $asmtB->id, 'class_subject_id' => $this->csScience->id, 'maximum_marks' => 50.00, 'is_active' => true]);

        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaTamil->id, 'assessment_applicability_id' => $appA_Tam->id, 'mark_value' => 45.00, 'result_status' => MarkResultStatus::NUMERIC]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaEnglish->id, 'assessment_applicability_id' => $appA_Eng->id, 'mark_value' => 40.00, 'result_status' => MarkResultStatus::NUMERIC]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaTamil->id, 'assessment_applicability_id' => $appB_Tam->id, 'mark_value' => 48.00, 'result_status' => MarkResultStatus::NUMERIC]);
        Mark::create(['student_academic_record_id' => $this->sar->id, 'student_subject_allocation_id' => $this->ssaScience->id, 'assessment_applicability_id' => $appB_Sci->id, 'mark_value' => 42.00, 'result_status' => MarkResultStatus::NUMERIC]);

        $config = ReportConfiguration::create(['academic_year_id' => $this->year->id, 'name' => 'Combined Tests', 'report_type' => ReportType::EXAM, 'is_active' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $asmtA->id, 'display_order' => 1, 'is_displayed' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $asmtB->id, 'display_order' => 2, 'is_displayed' => true]);

        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData($this->sar, ReportType::EXAM, null, 1, null, $config);

        // Subject roster is the union: Tamil, English, Science (3 subjects)
        $this->assertCount(3, $payload->subjectRows);

        // Column totals: Asmt A has Tamil (50) + English (50) = 100 max
        $colA = $payload->assessmentColumnTotals[$asmtA->id];
        $this->assertEquals(100.00, $colA['maximum']);
        $this->assertEquals(85.00, $colA['obtained']);

        // Column totals: Asmt B has Tamil (50) + Science (50) = 100 max
        $colB = $payload->assessmentColumnTotals[$asmtB->id];
        $this->assertEquals(100.00, $colB['maximum']);
        $this->assertEquals(90.00, $colB['obtained']);

        $this->assertSame('PASS', $payload->overallResult);
    }
}
