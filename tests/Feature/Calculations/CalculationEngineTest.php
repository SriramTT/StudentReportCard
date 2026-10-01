<?php

namespace Tests\Feature\Calculations;

use App\Enums\AcademicYearStatus;
use App\Enums\CalculationMethod;
use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
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
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Calculation\CalculationService;
use App\Services\Calculation\Exceptions\CalculationSettingMissingException;
use App\Services\Calculation\Resolvers\TermExamParticipationResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CalculationEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected CalculationService $calculationService;
    protected TermExamParticipationResolver $participationResolver;

    protected AcademicYear $year;
    protected Term $term1;
    protected Term $term2;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $math;
    protected Subject $science;
    protected ClassSubject $csMath;
    protected ClassSubject $csScience;
    protected Student $student;
    protected StudentAcademicRecord $sar;
    protected StudentSubjectAllocation $allocMath;
    protected StudentSubjectAllocation $allocScience;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participationResolver = new TermExamParticipationResolver();
        $this->calculationService = new CalculationService($this->participationResolver);

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_calc_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Calc',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_CALC_' . rand(1000, 9999),
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

        $this->class = SchoolClass::create([
            'name' => 'Class_Calc_' . uniqid(),
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->math = Subject::create([
            'name' => 'Mathematics ' . uniqid(),
            'code' => 'MTH' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->science = Subject::create([
            'name' => 'Science ' . uniqid(),
            'code' => 'SCI' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->csMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $this->csScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'admission_number' => 'ADM_CALC_' . rand(10000, 99999),
            'student_name' => 'John Doe',
        ]);

        $this->sar = StudentAcademicRecord::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 1,
            'effective_from' => '2026-06-01',
            'status' => StudentPlacementStatus::ACTIVE,
        ]);

        $this->allocMath = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->allocScience = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $this->csScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // A. FORMULA ENGINE TESTS (Prompt Item 16-A, 45)
    // =========================================================================

    public function test_formula_method_1_equal_average_of_percentages(): void
    {
        // Controlled inputs:
        // Test 1: 20.00 / 25.00 = 80.00%
        // Test 2: 40.00 / 50.00 = 80.00%
        // Average = 80.00%
        $inputs = [
            ['maximum_marks' => 25.00, 'obtained_marks' => 20.00, 'result_status' => 'numeric'],
            ['maximum_marks' => 50.00, 'obtained_marks' => 40.00, 'result_status' => 'numeric'],
        ];

        $result = $this->calculationService->calculateMethod1($inputs);

        $this->assertEquals(80.00, $result['percentage']);
        $this->assertEquals('80.00%', $result['formatted_percentage']);
        $this->assertEquals(60.00, $result['obtained_marks']);
        $this->assertEquals(75.00, $result['maximum_marks']);
        $this->assertTrue($result['is_available']);
    }

    public function test_formula_method_1_handles_differing_percentages_and_decimals(): void
    {
        // Test 1: 33.33 / 50.00 = 66.66%
        // Test 2: 75.50 / 100.00 = 75.50%
        // Average: (66.66 + 75.50) / 2 = 71.08%
        $inputs = [
            ['maximum_marks' => 50.00, 'obtained_marks' => 33.33, 'result_status' => 'numeric'],
            ['maximum_marks' => 100.00, 'obtained_marks' => 75.50, 'result_status' => 'numeric'],
        ];

        $result = $this->calculationService->calculateMethod1($inputs);

        $this->assertEquals(71.08, $result['percentage']);
        $this->assertEquals('71.08%', $result['formatted_percentage']);
    }

    public function test_formula_method_1_handles_zero_and_absent(): void
    {
        // Test 1: Zero (0.00 / 50.00 = 0%)
        // Test 2: Absent (A / 50.00 = 0%)
        // Average = 0.00%
        $inputs = [
            ['maximum_marks' => 50.00, 'obtained_marks' => 0.00, 'result_status' => 'numeric'],
            ['maximum_marks' => 50.00, 'obtained_marks' => null, 'result_status' => 'absent'],
        ];

        $result = $this->calculationService->calculateMethod1($inputs);

        $this->assertEquals(0.00, $result['percentage']);
        $this->assertEquals('0.00%', $result['formatted_percentage']);
        $this->assertEquals(0.00, $result['obtained_marks']);
        $this->assertEquals(100.00, $result['maximum_marks']);
    }

    public function test_formula_method_1_blank_returns_incomplete(): void
    {
        $inputs = [
            ['maximum_marks' => 50.00, 'obtained_marks' => 45.00, 'result_status' => 'numeric'],
            ['maximum_marks' => 50.00, 'obtained_marks' => null, 'result_status' => 'blank'],
        ];

        $result = $this->calculationService->calculateMethod1($inputs);

        $this->assertNull($result['percentage']);
        $this->assertEquals('Incomplete', $result['formatted_percentage']);
    }

    public function test_formula_method_2_combined_marks_percentage(): void
    {
        // Test 1: 20.00 / 25.00
        // Test 2: 40.00 / 50.00
        // Combined: 60.00 / 75.00 * 100 = 80.00%
        $inputs = [
            ['maximum_marks' => 25.00, 'obtained_marks' => 20.00, 'result_status' => 'numeric'],
            ['maximum_marks' => 50.00, 'obtained_marks' => 40.00, 'result_status' => 'numeric'],
        ];

        $result = $this->calculationService->calculateMethod2($inputs);

        $this->assertEquals(80.00, $result['percentage']);
        $this->assertEquals('80.00%', $result['formatted_percentage']);
        $this->assertEquals(60.00, $result['obtained_marks']);
        $this->assertEquals(75.00, $result['maximum_marks']);
    }

    public function test_formula_method_2_safe_zero_denominator(): void
    {
        $inputs = [
            ['maximum_marks' => 0.00, 'obtained_marks' => 0.00, 'result_status' => 'numeric'],
        ];

        $result = $this->calculationService->calculateMethod2($inputs);

        $this->assertNull($result['percentage']);
        $this->assertEquals('N/A', $result['formatted_percentage']);
        $this->assertFalse($result['is_available']);
    }

    // =========================================================================
    // B. PARTICIPATION RESOLVER TESTS (Prompt Item 16-B, 46)
    // =========================================================================

    public function test_participation_resolver_term_exam_participates(): void
    {
        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);
        $asst = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Term 1 Exam',
        ]);

        $this->assertTrue($this->participationResolver->participatesInTermCalculation($asst, $this->term1));
    }

    public function test_participation_resolver_non_term_exams_do_not_participate(): void
    {
        $typeClassTest = AssessmentType::create(['name' => 'Class Test', 'is_active' => true]);
        $typeUnitTest = AssessmentType::create(['name' => 'Unit Test', 'is_active' => true]);
        $typeWeeklyTest = AssessmentType::create(['name' => 'Weekly Test', 'is_active' => true]);

        foreach ([$typeClassTest, $typeUnitTest, $typeWeeklyTest] as $type) {
            $asst = Assessment::create([
                'academic_year_id' => $this->year->id,
                'term_id' => $this->term1->id,
                'assessment_type_id' => $type->id,
                'name' => "Sample {$type->name}",
            ]);

            $this->assertFalse(
                $this->participationResolver->participatesInTermCalculation($asst, $this->term1),
                "Failed asserting that {$type->name} does NOT participate in term calculation."
            );
        }
    }

    public function test_participation_resolver_enforces_target_term_isolation(): void
    {
        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);

        // Assessment is in Term 2
        $asstTerm2 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term2->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Term 2 Exam',
        ]);

        // When evaluating for Term 1, Term 2's exam must NOT participate
        $this->assertFalse($this->participationResolver->participatesInTermCalculation($asstTerm2, $this->term1));

        // When evaluating for Term 2, it DOES participate
        $this->assertTrue($this->participationResolver->participatesInTermCalculation($asstTerm2, $this->term2));
    }

    // =========================================================================
    // C. INTEGRATION TESTS (Prompt Item 16-C, 46, 47, 48, 49)
    // =========================================================================

    public function test_only_term_exam_participates_in_real_student_calculation(): void
    {
        // 1. Configure calculation setting: average_percentage
        CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);
        $typeUnitTest = AssessmentType::create(['name' => 'Unit Test', 'is_active' => true]);
        $typeClassTest = AssessmentType::create(['name' => 'Class Test', 'is_active' => true]);

        // Assessments for Math
        $asstTermExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Math Term Exam',
        ]);
        $appTermExam = AssessmentApplicability::create([
            'assessment_id' => $asstTermExam->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $asstUnit = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeUnitTest->id,
            'name' => 'Math Unit Test 1',
        ]);
        $appUnit = AssessmentApplicability::create([
            'assessment_id' => $asstUnit->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 25.00,
            'is_active' => true,
        ]);

        $asstClass = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeClassTest->id,
            'name' => 'Math Class Test 1',
        ]);
        $appClass = AssessmentApplicability::create([
            'assessment_id' => $asstClass->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        // Science Term Exam
        $asstScienceExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Science Term Exam',
        ]);
        $appScienceExam = AssessmentApplicability::create([
            'assessment_id' => $asstScienceExam->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // Marks:
        // Math: Unit Test = 24/25, Class Test = 48/50, Term Exam = 70/100
        // Science: Term Exam = 90/100
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appUnit->id,
            'mark_value' => '24.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appClass->id,
            'mark_value' => '48.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appTermExam->id,
            'mark_value' => '70.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocScience->id,
            'assessment_applicability_id' => $appScienceExam->id,
            'mark_value' => '90.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $result = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);

        $this->assertTrue($result->isComplete);
        $this->assertTrue($result->isAvailable);

        // Math: derived strictly from Term Exam (70.00%), ignoring Unit Test (24/25) and Class Test (48/50)
        $mathResult = collect($result->subjectResults)->firstWhere('subjectId', $this->math->id);
        $this->assertEquals(70.00, $mathResult->percentage);
        $this->assertEquals('70.00%', $mathResult->formattedPercentage);
        $this->assertCount(1, $mathResult->participatingAssessments);

        // Science: derived strictly from Term Exam (90.00%)
        $sciResult = collect($result->subjectResults)->firstWhere('subjectId', $this->science->id);
        $this->assertEquals(90.00, $sciResult->percentage);

        // Overall: average of 70.00% and 90.00% = 80.00%
        $this->assertEquals(80.00, $result->overallPercentage);
        $this->assertEquals('80.00%', $result->formattedOverallPercentage);
    }

    public function test_applicability_vs_display_selection_vs_calculation_participation(): void
    {
        CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);
        $typeUnit = AssessmentType::create(['name' => 'Unit Test', 'is_active' => true]);

        // 1. Term Exam: Applicable + Displayed + Participates
        $asstExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Exam',
        ]);
        $appExam = AssessmentApplicability::create([
            'assessment_id' => $asstExam->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // 2. Unit Test A: Applicable + Displayed + Non-participating
        $asstUnitA = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeUnit->id,
            'name' => 'Unit Test A',
        ]);
        $appUnitA = AssessmentApplicability::create([
            'assessment_id' => $asstUnitA->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 20.00,
            'is_active' => true,
        ]);

        // 3. Unit Test B: Applicable + NOT Displayed + Non-participating
        $asstUnitB = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeUnit->id,
            'name' => 'Unit Test B',
        ]);
        $appUnitB = AssessmentApplicability::create([
            'assessment_id' => $asstUnitB->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 20.00,
            'is_active' => true,
        ]);

        // Create Report Configuration selecting Exam and Unit Test A, but NOT Unit Test B
        $reportConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1 Report Card',
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $reportConfig->id,
            'assessment_id' => $asstExam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $reportConfig->id,
            'assessment_id' => $asstUnitA->id,
            'display_order' => 2,
            'is_displayed' => true,
        ]);
        // Unit Test B has is_displayed = false
        ReportAssessmentSelection::create([
            'report_configuration_id' => $reportConfig->id,
            'assessment_id' => $asstUnitB->id,
            'display_order' => 3,
            'is_displayed' => false,
        ]);

        // Enter Marks
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appExam->id,
            'mark_value' => '85.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appUnitA->id,
            'mark_value' => '10.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appUnitB->id,
            'mark_value' => '5.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Science Term Exam
        $asstSci = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Sci Exam',
        ]);
        $appSci = AssessmentApplicability::create([
            'assessment_id' => $asstSci->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocScience->id,
            'assessment_applicability_id' => $appSci->id,
            'mark_value' => '85.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $result = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);

        // Assert Math is derived purely from Term Exam (85.00%), completely unaffected by Unit A or Unit B
        $mathResult = collect($result->subjectResults)->firstWhere('subjectId', $this->math->id);
        $this->assertEquals(85.00, $mathResult->percentage);
        $this->assertEquals(85.00, $result->overallPercentage);
    }

    public function test_absent_mark_contributes_zero_obtained_and_retains_maximum(): void
    {
        CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::COMBINED_MARKS,
        ]);

        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);

        // Math: 80.00 / 100.00
        $asstMath = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Math Exam',
        ]);
        $appMath = AssessmentApplicability::create([
            'assessment_id' => $asstMath->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appMath->id,
            'mark_value' => '80.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Science: Absent on max 100.00
        $asstSci = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Science Exam',
        ]);
        $appSci = AssessmentApplicability::create([
            'assessment_id' => $asstSci->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocScience->id,
            'assessment_applicability_id' => $appSci->id,
            'mark_value' => null,
            'result_status' => MarkResultStatus::ABSENT,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $result = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);

        $this->assertTrue($result->isComplete);

        // Science result: 0.00%
        $sciResult = collect($result->subjectResults)->firstWhere('subjectId', $this->science->id);
        $this->assertEquals(0.00, $sciResult->obtainedMarks);
        $this->assertEquals(100.00, $sciResult->maximumMarks);
        $this->assertEquals(0.00, $sciResult->percentage);
        $this->assertEquals('0.00%', $sciResult->formattedPercentage);

        // Overall: Total Obtained = 80.00, Total Max = 200.00 -> 40.00%
        $this->assertEquals(80.00, $result->totalObtainedMarks);
        $this->assertEquals(200.00, $result->totalMaximumMarks);
        $this->assertEquals(40.00, $result->overallPercentage);
        $this->assertEquals('40.00%', $result->formattedOverallPercentage);
    }

    public function test_all_participating_results_absent_yields_zero_percent_and_is_complete(): void
    {
        CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);

        // Math Absent
        $asstMath = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Math Exam',
        ]);
        $appMath = AssessmentApplicability::create([
            'assessment_id' => $asstMath->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appMath->id,
            'mark_value' => null,
            'result_status' => MarkResultStatus::ABSENT,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Science Absent
        $asstSci = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Science Exam',
        ]);
        $appSci = AssessmentApplicability::create([
            'assessment_id' => $asstSci->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocScience->id,
            'assessment_applicability_id' => $appSci->id,
            'mark_value' => null,
            'result_status' => MarkResultStatus::ABSENT,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $result = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);

        // Must be complete = true, overallPercentage = 0.00% (Prompt Item 11, 47)
        $this->assertTrue($result->isComplete);
        $this->assertEquals(0.00, $result->overallPercentage);
        $this->assertEquals('0.00%', $result->formattedOverallPercentage);
    }

    public function test_blank_mark_marks_calculation_incomplete(): void
    {
        CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);

        // Math has mark
        $asstMath = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Math Exam',
        ]);
        $appMath = AssessmentApplicability::create([
            'assessment_id' => $asstMath->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appMath->id,
            'mark_value' => '90.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Science has BLANK mark
        $asstSci = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Science Exam',
        ]);
        $appSci = AssessmentApplicability::create([
            'assessment_id' => $asstSci->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocScience->id,
            'assessment_applicability_id' => $appSci->id,
            'mark_value' => null,
            'result_status' => MarkResultStatus::BLANK,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $result = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);

        $this->assertFalse($result->isComplete);
        $this->assertNull($result->overallPercentage);
        $this->assertEquals('Incomplete', $result->formattedOverallPercentage);
    }

    public function test_changing_calculation_setting_immediately_recalculates_results(): void
    {
        // Start with Method 1: average_percentage
        $setting = CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::AVERAGE_PERCENTAGE,
        ]);

        $typeTermExam = AssessmentType::create(['name' => 'Term Exam', 'is_active' => true]);

        // Math: 50.00 / 50.00 (100%)
        $asstMath = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Math Exam',
        ]);
        $appMath = AssessmentApplicability::create([
            'assessment_id' => $asstMath->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocMath->id,
            'assessment_applicability_id' => $appMath->id,
            'mark_value' => '50.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Science: 50.00 / 100.00 (50%)
        $asstSci = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTermExam->id,
            'name' => 'Science Exam',
        ]);
        $appSci = AssessmentApplicability::create([
            'assessment_id' => $asstSci->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $this->allocScience->id,
            'assessment_applicability_id' => $appSci->id,
            'mark_value' => '50.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Method 1 Result: (100% + 50%) / 2 = 75.00%
        $result1 = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);
        $this->assertEquals(75.00, $result1->overallPercentage);

        // Update Setting to Method 2: combined_marks
        $setting->update(['calculation_method' => CalculationMethod::COMBINED_MARKS]);

        // Method 2 Result: (50 + 50) / (50 + 100) = 100 / 150 = 66.67%
        $result2 = $this->calculationService->calculateStudentTerm($this->sar, $this->term1);
        $this->assertEquals(66.67, $result2->overallPercentage);
    }

    public function test_missing_calculation_setting_throws_explicit_exception(): void
    {
        // No CalculationSetting row exists for this class/year
        $this->expectException(CalculationSettingMissingException::class);

        $this->calculationService->calculateStudentTerm($this->sar, $this->term1);
    }
}
