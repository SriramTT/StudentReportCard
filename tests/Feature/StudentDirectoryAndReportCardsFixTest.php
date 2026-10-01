<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\ReportType;
use App\Enums\StudentPlacementStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentDirectoryAndReportCardsFixTest extends TestCase
{
    use DatabaseTransactions;

    protected AcademicYear $year;
    protected SchoolClass $class1;
    protected Section $secA;
    protected Section $secB;
    protected Section $secC;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertEquals('school_report_card_audit', DB::connection()->getDatabaseName());

        $suffix = rand(10000, 99999);

        $roleAdmin = Role::firstOrCreate(['name' => 'Administrator']);

        $this->adminUser = User::create([
            'role_id' => $roleAdmin->id,
            'username' => "admin_{$suffix}",
            'email' => "admin_{$suffix}@school.test",
            'password_hash' => Hash::make('Secret123!'),
            'display_name' => "Admin {$suffix}",
            'is_active' => true,
        ]);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Test Academy',
            'pass_mark' => 35.00,
        ]);

        $this->year = AcademicYear::create([
            'name' => "2026-{$suffix}",
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'is_current' => true,
            'status' => AcademicYearStatus::OPEN,
        ]);

        $this->class1 = SchoolClass::create(['name' => "Class 1 {$suffix}"]);
        $this->secA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'A',
            'is_active' => true,
        ]);
        $this->secB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'B',
            'is_active' => true,
        ]);
        $this->secC = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'C',
            'is_active' => true,
        ]);
    }

    /**
     * ISSUE 1: Student Directory must filter by current placement, not historical transferred placements.
     */
    public function test_transferred_student_appears_only_in_current_placement_and_not_in_historical_classrooms(): void
    {
        $suffix = rand(1000, 9999);

        // Create student "Saranya K"
        $student = Student::create([
            'admission_number' => "ADM-SK-{$suffix}",
            'student_name' => 'Saranya K',
        ]);

        // Historical placement 1: Class 1-A (transferred out internally)
        $recA = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secA->id,
            'roll_number' => 101,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-06-01',
        ]);

        // Historical placement 2: Class 1-B (transferred out internally)
        $recB = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secB->id,
            'roll_number' => 201,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-07-02',
        ]);

        // Current placement 3: Class 1-C (active)
        $recC = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'roll_number' => 301,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-08-02',
        ]);

        // 1. Filtering by Class 1-A must NOT return Saranya K
        $resA = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secA->id,
        ]));
        $resA->assertStatus(200);
        $resA->assertDontSee($student->admission_number);

        // 2. Filtering by Class 1-B must NOT return Saranya K
        $resB = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secB->id,
        ]));
        $resB->assertStatus(200);
        $resB->assertDontSee($student->admission_number);

        // 3. Filtering by Class 1-C MUST return Saranya K exactly once
        $resC = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
        ]));
        $resC->assertStatus(200);
        $resC->assertSee($student->admission_number);
        $resC->assertSee('Saranya K');

        // 4. Filtering with status="all" and Class 1-A must still NOT return Saranya K
        $resAllStatusA = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secA->id,
            'status' => 'all',
        ]));
        $resAllStatusA->assertStatus(200);
        $resAllStatusA->assertDontSee($student->admission_number);

        // 5. Without classroom filter, Saranya K appears with Current Placement displayed as Section C
        $resNoClass = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'search' => $student->admission_number,
        ]));
        $resNoClass->assertStatus(200);
        $resNoClass->assertSee($student->admission_number);
        $resNoClass->assertSee($this->class1->name);

        // 6. Placement history on student profile remains intact
        $resShow = $this->actingAs($this->adminUser)->get(route('students.show', $student));
        $resShow->assertStatus(200);
        $resShow->assertSee('Academic Placement History');
    }

    /**
     * ISSUE 2: Report Cards must include Custom Report and filter configurations by report type.
     */
    public function test_report_cards_dropdown_and_configuration_isolation(): void
    {
        $suffix = rand(1000, 9999);

        $term = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => "Term 1 {$suffix}",
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $assType = AssessmentType::create([
            'name' => "Unit Test {$suffix}",
            'is_active' => true,
        ]);

        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $term->id,
            'assessment_type_id' => $assType->id,
            'name' => "Assessment 1 {$suffix}",
            'status' => 'active',
        ]);

        $subject = \App\Models\Subject::create([
            'name' => "Subject {$suffix}",
            'code' => "SUB{$suffix}",
            'is_active' => true,
        ]);

        $cs = \App\Models\ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        \App\Models\AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // 1. Create Term Configuration
        $termConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => "TC_Term_{$suffix}",
            'report_type' => ReportType::TERM,
            'configuration_data' => [],
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $termConfig->id,
            'assessment_id' => $assessment->id,
            'display_order' => 1,
        ]);

        // 2. Create Final Configuration
        $finalConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => "FC_Final_{$suffix}",
            'report_type' => ReportType::FINAL,
            'configuration_data' => [],
            'is_active' => true,
        ]);

        // 3. Create Mid Term Assessments Configuration (report_type=exam, subtype=mid_term)
        $midTermConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => "MC_MidTerm_{$suffix}",
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'mid_term'],
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $midTermConfig->id,
            'assessment_id' => $assessment->id,
            'display_order' => 1,
        ]);

        // 4. Create Custom Report Configuration (report_type=exam, subtype=custom)
        $customConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => "CC_Custom_{$suffix}",
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'custom'],
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $customConfig->id,
            'assessment_id' => $assessment->id,
            'display_order' => 1,
        ]);

        // Check index page renders all 4 report type options
        $response = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Term Report');
        $response->assertSee('Final Report');
        $response->assertSee('Mid Term Assessments');
        $response->assertSee('Custom Report');
        $response->assertSee('<option value="custom"', false);
        $response->assertSee('<option value="exam"', false);

        // Test filtering by report_type=exam (Mid Term Assessments): must show midTermConfig, never customConfig
        $resMidTerm = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'report_type' => 'exam',
        ]));
        $resMidTerm->assertStatus(200);
        $resMidTerm->assertSee($midTermConfig->name);
        $resMidTerm->assertDontSee($customConfig->name);
        $resMidTerm->assertDontSee($termConfig->name);
        $resMidTerm->assertDontSee($finalConfig->name);
        $resFinal = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'report_type' => 'final',
        ]));
        $resFinal->assertStatus(200);
        $resFinal->assertSee($finalConfig->name);
        $resFinal->assertDontSee($customConfig->name);
        $resFinal->assertDontSee($midTermConfig->name);

        // Test filtering by report_type=custom: must show customConfig, never midTermConfig
        $resCustom = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'report_type' => 'custom',
        ]));
        $resCustom->assertStatus(200);
        $resCustom->assertSee($customConfig->name);
        $resCustom->assertDontSee($midTermConfig->name);
        $resCustom->assertDontSee($termConfig->name);
        $resCustom->assertDontSee($finalConfig->name);

        // Test filtering by report_type=term: must show only termConfig
        $resTerm = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'report_type' => 'term',
        ]));
        $resTerm->assertStatus(200);
        $resTerm->assertSee($termConfig->name);
        $resTerm->assertDontSee($customConfig->name);
        $resTerm->assertDontSee($midTermConfig->name);

        // Server-side validation: Mismatched report_type and report_configuration_id must be rejected
        $student = Student::create([
            'admission_number' => "ADM-V-{$suffix}",
            'student_name' => 'Valid Student',
        ]);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'roll_number' => 501,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Attempting to preview with report_type=custom and midTermConfig ID must fail validation with 422
        $resMismatch = $this->actingAs($this->adminUser)->postJson(route('reports.preview'), [
            'student_academic_record_id' => $sar->id,
            'report_type' => 'custom',
            'report_configuration_id' => $midTermConfig->id,
        ]);
        $resMismatch->assertStatus(422);

        // Attempting to preview with report_type=exam and customConfig ID must fail validation with 422
        $resMismatchExam = $this->actingAs($this->adminUser)->postJson(route('reports.preview'), [
            'student_academic_record_id' => $sar->id,
            'report_type' => 'exam',
            'report_configuration_id' => $customConfig->id,
        ]);
        $resMismatchExam->assertStatus(422);
    }

    /**
     * Empty state for report configurations and stale ID rejection.
     */
    public function test_empty_configuration_state_and_neutral_status(): void
    {
        $suffix = rand(1000, 9999);

        // Academic year with NO configurations
        $emptyYear = AcademicYear::create([
            'name' => "2026-Empty-{$suffix}",
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'is_current' => false,
            'status' => AcademicYearStatus::OPEN,
        ]);

        $student = Student::create([
            'admission_number' => "ADM-E-{$suffix}",
            'student_name' => 'Empty Student',
        ]);
        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $emptyYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secA->id,
            'roll_number' => 10,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Request with report_type=custom where no configuration exists in this year
        $response = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'academic_year_id' => $emptyYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secA->id,
            'report_type' => 'custom',
            'report_configuration_id' => 99999, // stale / non-existent ID
        ]));

        $response->assertStatus(200);
        $response->assertSee('-- No Configuration Available --');
        $response->assertSee('disabled', false);

        // Verify neutral status '—' is displayed when no configuration is active/selected
        $response->assertSee('—');
        $response->assertDontSee('Incomplete (');
    }

    /**
     * Search by student name and admission number with transferred records.
     */
    public function test_student_directory_search_and_uniqueness_with_transferred_records(): void
    {
        $suffix = rand(1000, 9999);

        $student1 = Student::create([
            'admission_number' => "ADM-U1-{$suffix}",
            'student_name' => "Kavitha Raman {$suffix}",
        ]);

        // 3 historical placements in same year
        StudentAcademicRecord::create([
            'student_id' => $student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secA->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-06-01',
        ]);
        StudentAcademicRecord::create([
            'student_id' => $student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secB->id,
            'roll_number' => 2,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-07-01',
        ]);
        StudentAcademicRecord::create([
            'student_id' => $student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->secC->id,
            'roll_number' => 3,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-08-01',
        ]);

        // Search by admission number
        $resAdm = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'search' => $student1->admission_number,
        ]));
        $resAdm->assertStatus(200);
        // Ensure student appears in exactly one row in the table (1 delete modal rendered for this student)
        $this->assertEquals(1, substr_count($resAdm->getContent(), 'id="delete-student-modal-' . $student1->id . '"'));

        // Search by name
        $resName = $this->actingAs($this->adminUser)->get(route('students.index', [
            'academic_year_id' => $this->year->id,
            'search' => 'Kavitha Raman',
        ]));
        $resName->assertStatus(200);
        $resName->assertSee($student1->admission_number);
    }
}

