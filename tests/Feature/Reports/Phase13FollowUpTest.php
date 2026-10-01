<?php

namespace Tests\Feature\Reports;

use App\Contracts\ReportGeneratorContract;
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
use App\Models\GeneratedReport;
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
use App\Services\Calculation\CalculationService;
use App\Services\Report\DTOs\ReportDataPayload;
use App\Services\Report\ReportCompletionService;
use App\Services\Report\ReportDataPreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase13FollowUpTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected AcademicYear $year;
    protected Term $term1;
    protected Term $term2;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $english;
    protected Subject $math;
    protected ClassSubject $csEnglish;
    protected ClassSubject $csMath;
    protected StudentAcademicRecord $sar;
    protected StudentSubjectAllocation $ssaEnglish;
    protected StudentSubjectAllocation $ssaMath;
    protected CalculationSetting $calcSetting;
    protected AssessmentType $examType;
    protected AssessmentType $unitTestType;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['name' => 'Administrator']);

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_phase13_test'],
            [
                'email' => 'admin_p13@example.com',
                'password_hash' => Hash::make('Secret123!'),
                'display_name' => 'Phase 13 Admin',
                'role_id' => $roleAdmin->id,
                'is_active' => true,
            ]
        );

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Test Academy',
            'pass_mark' => 35.00,
        ]);

        $this->year = AcademicYear::create([
            'name' => '2026-2027 Test P13',
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->term1 = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $this->term2 = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 2',
            'sequence_no' => 2,
            'is_active' => false,
        ]);

        $this->class = SchoolClass::create(['name' => 'Class X-Test']);
        $this->section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
        ]);

        $this->english = Subject::create(['name' => 'English P13', 'code' => 'ENG-P13']);
        $this->math = Subject::create(['name' => 'Mathematics P13', 'code' => 'MATH-P13']);

        $this->csEnglish = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->english->id,
            'subject_name_snapshot' => 'English P13',
            'is_active' => true,
        ]);

        $this->csMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics P13',
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM-P13-001',
            'student_name' => 'Test Student P13',
        ]);

        $this->sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->ssaEnglish = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csEnglish->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->ssaMath = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csMath->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->calcSetting = CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $this->examType = AssessmentType::firstOrCreate(['name' => 'Term Exam']);
        $this->unitTestType = AssessmentType::firstOrCreate(['name' => 'Weekly Test']);
    }

    public function test_report_type_dropdown_contains_mid_term_assessments_with_exam_value(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('<option value="exam"', false);
        $response->assertSee('Mid Term Assessments', false);
    }

    public function test_final_report_request_prohibits_term_id_and_returns_422_when_supplied(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('reports.preview'), [
            'student_academic_record_id' => $this->sar->id,
            'report_type' => 'final',
            'term_id' => $this->term1->id, // Passing term_id for final report must be rejected
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['term_id']);
    }

    public function test_final_report_preview_succeeds_without_term_id_and_does_not_create_generated_reports(): void
    {
        $asmtTerm1 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 1 Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appEng = AssessmentApplicability::create([
            'assessment_id' => $asmtTerm1->id,
            'class_subject_id' => $this->csEnglish->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $appMath = AssessmentApplicability::create([
            'assessment_id' => $asmtTerm1->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaEnglish->id,
            'assessment_applicability_id' => $appEng->id,
            'mark_value' => 80.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaMath->id,
            'assessment_applicability_id' => $appMath->id,
            'mark_value' => 75.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        $config = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Annual Comprehensive Config',
            'report_type' => ReportType::FINAL,
            'is_active' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $asmtTerm1->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $initialGeneratedCount = GeneratedReport::count();

        $response = $this->actingAs($this->admin)->postJson(route('reports.preview'), [
            'student_academic_record_id' => $this->sar->id,
            'report_type' => 'final',
            'term_id' => null,
            'report_configuration_id' => $config->id,
        ]);

        $response->assertStatus(200);
        $this->assertSame($initialGeneratedCount, GeneratedReport::count(), 'Preview must not create generated report records');
    }

    public function test_mid_term_assessment_completion_and_payload_with_na_attendance(): void
    {
        $weeklyTest = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->unitTestType->id,
            'name' => 'Weekly Test 1',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appEng = AssessmentApplicability::create([
            'assessment_id' => $weeklyTest->id,
            'class_subject_id' => $this->csEnglish->id,
            'maximum_marks' => 25.00,
            'is_active' => true,
        ]);

        $appMath = AssessmentApplicability::create([
            'assessment_id' => $weeklyTest->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 25.00,
            'is_active' => true,
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

        $completionService = app(ReportCompletionService::class);

        // Initially no marks entered -> incomplete
        $this->assertFalse($completionService->isComplete($this->sar->id, 'exam', null, $config));

        // Mark English as absent (valid), Math as 20.00 (valid numeric)
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaEnglish->id,
            'assessment_applicability_id' => $appEng->id,
            'mark_value' => null,
            'result_status' => MarkResultStatus::ABSENT,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaMath->id,
            'assessment_applicability_id' => $appMath->id,
            'mark_value' => 20.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        $this->assertTrue($completionService->isComplete($this->sar->id, 'exam', null, $config));

        // Prepare report data
        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData(
            $this->sar,
            ReportType::EXAM,
            null,
            1,
            null,
            $config
        );

        $this->assertSame(ReportType::EXAM, $payload->reportType);
        $this->assertSame('N/A', $payload->attendanceFormatted);
        $this->assertNull($payload->attendanceDaysAttended);
        $this->assertNull($payload->attendanceTotalWorkingDays);
        $this->assertSame('N/A', $payload->attendance['formatted']);

        // Check math subject calculation
        $mathRow = collect($payload->subjectRows)->firstWhere('subjectId', $this->math->id);
        $this->assertNotNull($mathRow);
        $this->assertEquals(80.00, $mathRow->termPercentage);
        $this->assertSame('PASS', $mathRow->termStatus);

        // Check absent english subject calculation
        $engRow = collect($payload->subjectRows)->firstWhere('subjectId', $this->english->id);
        $this->assertNotNull($engRow);
        $this->assertEquals(0.00, $engRow->termPercentage);
        $this->assertSame('FAIL', $engRow->termStatus);
    }

    public function test_annual_exam_resolved_and_rendered_above_term_table_without_annual_aggregate_percentage(): void
    {
        // 1. Annual Exam assessment has term_id = NULL
        $annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Annual Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Term 1 exam
        $term1Exam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 1 Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appAnnualEng = AssessmentApplicability::create([
            'assessment_id' => $annualExam->id,
            'class_subject_id' => $this->csEnglish->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $appAnnualMath = AssessmentApplicability::create([
            'assessment_id' => $annualExam->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $appT1Eng = AssessmentApplicability::create([
            'assessment_id' => $term1Exam->id,
            'class_subject_id' => $this->csEnglish->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $appT1Math = AssessmentApplicability::create([
            'assessment_id' => $term1Exam->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaEnglish->id,
            'assessment_applicability_id' => $appAnnualEng->id,
            'mark_value' => 85.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaMath->id,
            'assessment_applicability_id' => $appAnnualMath->id,
            'mark_value' => 90.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaEnglish->id,
            'assessment_applicability_id' => $appT1Eng->id,
            'mark_value' => 70.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->ssaMath->id,
            'assessment_applicability_id' => $appT1Math->id,
            'mark_value' => 75.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        Attendance::create([
            'student_academic_record_id' => $this->sar->id,
            'term_id' => $this->term1->id,
            'days_attended' => 80,
            'total_working_days' => 90,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $config = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Annual Exam Config',
            'report_type' => ReportType::FINAL,
            'is_active' => true,
        ]);

        // Annual Exam displayed selection
        ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $annualExam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        // Term 1 Exam displayed selection
        ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $term1Exam->id,
            'display_order' => 2,
            'is_displayed' => true,
        ]);

        $prepService = app(ReportDataPreparationService::class);
        $payload = $prepService->prepareReportData(
            $this->sar,
            ReportType::FINAL,
            null,
            1,
            null,
            $config
        );

        // Verify Annual Exam data in payload
        $this->assertNotNull($payload->annualExamData);
        $this->assertSame('Annual Exam', $payload->annualExamData['assessment_name']);
        $this->assertSame('175.00 / 200.00', $payload->annualExamData['formatted_total_marks']);

        $engAnnualRow = collect($payload->annualExamData['rows'])->firstWhere('subject_id', $this->english->id);
        $this->assertNotNull($engAnnualRow);
        $this->assertEquals(85.00, $engAnnualRow['percentage']);
        $this->assertSame('PASS', $engAnnualRow['status']);

        // Verify overallResult in payload: under Phase 14, Final Overall Result is governed by Annual Exam outcome
        $this->assertSame('PASS', $payload->overallResult);
        $this->assertEquals(87.50, $payload->annualExamData['percentage']);
        $this->assertSame('87.50%', $payload->annualExamData['formatted_percentage']);
        $this->assertSame('PASS', $payload->annualExamData['result']);

        // Render final-report Blade view
        $html = view('reports.pdf.final-report', ['payload' => $payload])->render();

        // 1. Annual Exam table appears BEFORE Term Reports Summary
        $annualPos = strpos($html, 'Annual Exam');
        $termsPos = strpos($html, 'Term Reports Summary');
        $this->assertNotFalse($annualPos);
        $this->assertNotFalse($termsPos);
        $this->assertLessThan($termsPos, $annualPos, 'Annual Exam table must appear ABOVE Term Reports Summary');

        // 2. Annual Exam subject percentages and results appear
        $this->assertStringContainsString('85.00%', $html);
        $this->assertStringContainsString('90.00%', $html);

        // 3. Phase 14: Annual Exam Assessment Total Percentage and Result are displayed in table footer
        $this->assertStringContainsString('87.50%', $html);
        $this->assertStringContainsString('PASS', $html);

        // 4. Overall result appears below attendance
        $attendancePos = strpos($html, 'Term Attendance Records');
        $overallPos = strpos($html, 'Overall Result:');
        $this->assertNotFalse($attendancePos);
        $this->assertNotFalse($overallPos);
        $this->assertLessThan($overallPos, $attendancePos, 'Overall Result must appear BELOW attendance');
    }
}
