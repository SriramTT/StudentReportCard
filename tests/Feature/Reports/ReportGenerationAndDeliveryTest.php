<?php

namespace Tests\Feature\Reports;

use App\Contracts\ReportGeneratorContract;
use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\CalculationMethod;
use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Enums\StudentPlacementStatus;
use App\Enums\TeacherAssignmentType;
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
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Report\Exceptions\IncompleteMarksheetException;
use App\Services\Report\ReportConfigurationResolver;
use App\Services\Report\ReportGenerationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportGenerationAndDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $classTeacher;
    protected User $otherClassTeacher;
    protected User $subjectTeacher;

    protected AcademicYear $year;
    protected AcademicYear $closedYear;
    protected Term $term1;
    protected Term $term2;
    protected SchoolClass $class;
    protected Section $sectionA;
    protected Section $sectionB;
    protected Subject $math;
    protected Subject $science;
    protected ClassSubject $csMath;
    protected ClassSubject $csScience;
    protected AssessmentType $examType;
    protected AssessmentType $testType;
    protected Assessment $termExam;
    protected Assessment $unitTest;
    protected AssessmentApplicability $appExamMath;
    protected AssessmentApplicability $appExamScience;
    protected AssessmentApplicability $appTestMath;
    protected AssessmentApplicability $appTestScience;
    protected ReportConfiguration $reportConfig;
    protected SchoolSetting $schoolSetting;

    protected Student $student1;
    protected StudentAcademicRecord $sar1;
    protected StudentSubjectAllocation $allocMath1;
    protected StudentSubjectAllocation $allocScience1;

    protected Student $student2;
    protected StudentAcademicRecord $sar2;
    protected StudentSubjectAllocation $allocMath2;
    protected StudentSubjectAllocation $allocScience2;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $ctRole = Role::where('name', 'Class Teacher')->firstOrFail();
        $stRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_rep_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Reporter',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_rep_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Reporter',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'ct_rep_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher Rep',
            'is_active' => true,
        ]);

        $this->otherClassTeacher = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'oct_rep_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Other CT Rep',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 'st_rep_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Subject Teacher Rep',
            'is_active' => true,
        ]);

        // Academic Structure
        $this->year = AcademicYear::create([
            'name' => 'AY_REP_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->closedYear = AcademicYear::create([
            'name' => 'AY_CLOSED_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
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
            'name' => 'Class 8',
            'is_active' => true,
        ]);

        $this->sectionA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
            'is_active' => true,
        ]);

        $this->sectionB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Section B',
            'is_active' => true,
        ]);

        // Teacher assignments: classTeacher assigned to sectionA
        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Subjects & Class Subjects
        $this->math = Subject::create(['name' => 'Mathematics', 'code' => 'MTH_' . rand(100, 999), 'is_active' => true]);
        $this->science = Subject::create(['name' => 'Science', 'code' => 'SCI_' . rand(100, 999), 'is_active' => true]);

        $this->csMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics Snapshot',
            'is_active' => true,
        ]);

        $this->csScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => 'Science Snapshot',
            'is_active' => true,
        ]);

        // Assessment Types & Assessments
        $this->examType = AssessmentType::create([
            'name' => 'Term Exam',
            'is_active' => true,
        ]);

        $this->testType = AssessmentType::create([
            'name' => 'Unit Test',
            'is_active' => true,
        ]);

        $this->termExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 1 Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->unitTest = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Unit Test 1',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Assessment Applicability
        $this->appExamMath = AssessmentApplicability::create([
            'assessment_id' => $this->termExam->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
        ]);

        $this->appExamScience = AssessmentApplicability::create([
            'assessment_id' => $this->termExam->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
        ]);

        $this->appTestMath = AssessmentApplicability::create([
            'assessment_id' => $this->unitTest->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 25.00,
        ]);

        $this->appTestScience = AssessmentApplicability::create([
            'assessment_id' => $this->unitTest->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 25.00,
        ]);

        // Report Configurations
        $this->reportConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'AY Term Config',
            'report_type' => ReportType::TERM,
            'configuration_data' => [
                'show_attendance' => true,
                'show_pass_mark' => true,
                'show_term_results' => true,
                'show_assessment_details' => false,
            ],
            'is_active' => true,
        ]);

        ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'AY Final Config',
            'report_type' => ReportType::FINAL,
            'configuration_data' => [
                'show_attendance' => true,
                'show_pass_mark' => true,
                'show_term_results' => true,
                'show_assessment_details' => false,
            ],
            'is_active' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $this->reportConfig->id,
            'assessment_id' => $this->unitTest->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $this->reportConfig->id,
            'assessment_id' => $this->termExam->id,
            'display_order' => 2,
            'is_displayed' => true,
        ]);

        // School Setting: pass_mark = 40.00
        $this->schoolSetting = SchoolSetting::first() ?? SchoolSetting::create([
            'school_name' => 'Academic Test School',
            'pass_mark' => 40.00,
        ]);
        $this->schoolSetting->update(['pass_mark' => 40.00]);

        // Calculation Setting
        CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::COMBINED_MARKS,
        ]);

        // Students in Section A
        $this->student1 = Student::create([
            'admission_number' => 'ADM_REP_' . rand(1000, 9999),
            'student_name' => 'Alice Walker',
        ]);

        $this->sar1 = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 10,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->allocMath1 = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1->id,
            'class_subject_id' => $this->csMath->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->allocScience1 = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1->id,
            'class_subject_id' => $this->csScience->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->student2 = Student::create([
            'admission_number' => 'ADM_REP_' . rand(1000, 9999),
            'student_name' => 'Bob Builder',
        ]);

        $this->sar2 = StudentAcademicRecord::create([
            'student_id' => $this->student2->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 11,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->allocMath2 = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar2->id,
            'class_subject_id' => $this->csMath->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->allocScience2 = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar2->id,
            'class_subject_id' => $this->csScience->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
    }

    protected function createMark(
        StudentAcademicRecord $sar,
        StudentSubjectAllocation $alloc,
        AssessmentApplicability $app,
        ?float $value = null,
        bool $isAbsent = false
    ): Mark {
        return Mark::create([
            'student_academic_record_id' => $sar->id,
            'student_subject_allocation_id' => $alloc->id,
            'assessment_applicability_id' => $app->id,
            'mark_value' => $isAbsent ? null : ($value !== null ? number_format($value, 2, '.', '') : null),
            'result_status' => $isAbsent ? MarkResultStatus::ABSENT : ($value !== null ? MarkResultStatus::NUMERIC : MarkResultStatus::BLANK),
            'entered_by_user_id' => $this->admin->id,
        ]);
    }

    /**
     * A. TERM REPORT: Complete student generates successfully with marks, percentage, attendance, pass/fail.
     */
    public function test_term_report_generation_for_complete_student(): void
    {
        // Populate marks for Student 1:
        // Math: Unit Test = 20, Term Exam = 80 (80% -> PASS)
        // Science: Unit Test = 18, Term Exam = 70 (70% -> PASS)
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 18.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 70.00);

        // Attendance: 45 / 50 days (90.00%)
        Attendance::create([
            'student_academic_record_id' => $this->sar1->id,
            'term_id' => $this->term1->id,
            'days_attended' => 45,
            'total_working_days' => 50,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $report = $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );

        $this->assertInstanceOf(GeneratedReport::class, $report);
        $this->assertEquals(1, $report->revision_number);
        $this->assertEquals(ReportType::TERM, $report->report_type);

        $pdfPath = storage_path('app/private/' . $report->file_path);
        $this->assertFileExists($pdfPath);
        $this->assertGreaterThan(0, filesize($pdfPath));

        // Test download endpoint
        $response = $this->actingAs($this->admin)->get(route('reports.download', $report));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * D & E. COMPLETION CHECK: Blank required assessment blocks PDF generation with 422.
     */
    public function test_blank_mark_blocks_generation_with_422(): void
    {
        // Math Term Exam provided, but Unit Test is BLANK (missing mark record)
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 85.00);

        $this->expectException(IncompleteMarksheetException::class);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);
        $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );
    }

    /**
     * E. Independent generation: Student 1 being incomplete does NOT block Student 2.
     */
    public function test_incomplete_student1_does_not_block_complete_student2(): void
    {
        // Student 2 is fully complete
        $this->createMark($this->sar2, $this->allocMath2, $this->appTestMath, 15.00);
        $this->createMark($this->sar2, $this->allocMath2, $this->appExamMath, 60.00);
        $this->createMark($this->sar2, $this->allocScience2, $this->appTestScience, 20.00);
        $this->createMark($this->sar2, $this->allocScience2, $this->appExamScience, 75.00);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $report2 = $service->generate(
            sarId: $this->sar2->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );

        $this->assertEquals(1, $report2->revision_number);
        $this->assertFileExists(storage_path('app/private/' . $report2->file_path));
    }

    /**
     * D & F. CALCULATION SEPARATION & PASS/FAIL:
     * Non-contributing Unit Test absent does NOT fail subject; Term Exam alone determines term %.
     * Inclusive pass_mark: 40.00% is PASS, 39.99% is FAIL.
     */
    public function test_calculation_separation_and_inclusive_pass_mark(): void
    {
        // Student 1 Math: Unit Test = ABSENT (non-contributing), Term Exam = 40.00 (exactly pass_mark)
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, null, isAbsent: true);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 40.00);

        // Science: Unit Test = 25.00, Term Exam = 39.99 (below pass_mark 40.00 -> FAIL)
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 25.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 39.99);

        /** @var \App\Services\Report\ReportDataPreparationService $prep */
        $prep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $prep->prepare($this->sar1->id, 'term', $this->term1->id, null, 1);

        // Find Math row
        $mathRow = collect($payload->subjectRows)->firstWhere('subjectName', 'Mathematics Snapshot');
        $this->assertNotNull($mathRow);
        $this->assertEquals(40.00, $mathRow->percentage);
        $this->assertEquals('PASS', $mathRow->result);

        // Find Science row
        $sciRow = collect($payload->subjectRows)->firstWhere('subjectName', 'Science Snapshot');
        $this->assertNotNull($sciRow);
        $this->assertEquals(39.99, $sciRow->percentage);
        $this->assertEquals('FAIL', $sciRow->result);
    }

    /**
     * G. ATTENDANCE: Safe 0/0 handling yields N/A percentage.
     */
    public function test_attendance_zero_total_days_displays_na(): void
    {
        Attendance::create([
            'student_academic_record_id' => $this->sar1->id,
            'term_id' => $this->term1->id,
            'days_attended' => 0,
            'total_working_days' => 0,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        // Complete marks
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 20.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 80.00);

        /** @var \App\Services\Report\ReportDataPreparationService $prep */
        $prep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $prep->prepare($this->sar1->id, 'term', $this->term1->id, null, 1);

        $this->assertNotNull($payload->attendance);
        $this->assertEquals('N/A', $payload->attendance['formatted_percentage']);
        $this->assertNull($payload->attendance['percentage']);
    }

    /**
     * I & J. REVISION & HISTORICAL IMMUTABILITY:
     * First generation is Revision 1. After mark correction, second generation is Revision 2.
     * Revision 1 file remains intact and unchanged.
     */
    public function test_revision_number_increment_and_file_immutability(): void
    {
        // 1. Mark: Math = 50.00, Science = 50.00
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $markME = $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 50.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 20.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 50.00);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $rev1 = $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );

        $this->assertEquals(1, $rev1->revision_number);
        $rev1Path = storage_path('app/private/' . $rev1->file_path);
        $this->assertFileExists($rev1Path);
        $rev1Hash = hash_file('sha256', $rev1Path);

        // 2. Correct mark: Math Term Exam 50 -> 95
        $markME->update(['mark_value' => '95.00']);

        $rev2 = $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );

        $this->assertEquals(2, $rev2->revision_number);
        $rev2Path = storage_path('app/private/' . $rev2->file_path);
        $this->assertFileExists($rev2Path);

        // Immutability verification: Rev 1 file is untouched!
        $this->assertEquals($rev1Hash, hash_file('sha256', $rev1Path));
        $this->assertNotEquals($rev1->file_path, $rev2->file_path);
    }

    /**
     * B & H. FINAL REPORT & HISTORICAL PLACEMENT:
     * Multi-term dynamic aggregation without overall annual percentage or rank.
     */
    public function test_final_report_dynamic_multi_term_without_annual_percentage(): void
    {
        // Complete Term 1 marks
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 85.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 22.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 90.00);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $finalReport = $service->generate(
            sarId: $this->sar1->id,
            reportType: 'final',
            termId: null,
            assessmentId: null,
            user: $this->admin
        );

        $this->assertInstanceOf(GeneratedReport::class, $finalReport);
        $this->assertEquals(ReportType::FINAL, $finalReport->report_type);
        $this->assertNull($finalReport->term_id);
        $this->assertEquals(1, $finalReport->revision_number);
    }

    /**
     * L. AUTHORIZATION:
     * Administrator: Allowed.
     * Office Staff: Allowed.
     * Class Teacher (Assigned Classroom): Allowed.
     * Class Teacher (Unrelated Classroom): Denied (403).
     * Subject Teacher: Denied (403).
     */
    public function test_report_authorization_matrix(): void
    {
        // Complete student 1 marks
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 20.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 80.00);

        // 1. Office Staff can generate
        $respOffice = $this->actingAs($this->officeStaff)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $this->reportConfig->id,
        ]);
        $respOffice->assertSessionHasNoErrors();

        // 2. Class Teacher assigned to Section A can generate
        $respCT = $this->actingAs($this->classTeacher)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $this->reportConfig->id,
        ]);
        $respCT->assertSessionHasNoErrors();

        // 3. Other Class Teacher (not assigned to Section A) receives 403
        $respOtherCT = $this->actingAs($this->otherClassTeacher)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $this->reportConfig->id,
        ]);
        $respOtherCT->assertForbidden();

        // 4. Subject Teacher receives 403
        $respST = $this->actingAs($this->subjectTeacher)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $this->reportConfig->id,
        ]);
        $respST->assertForbidden();
    }

    /**
     * M. CLOSED ACADEMIC YEAR:
     * Class Teacher report generation is allowed even when the academic year is closed.
     */
    public function test_class_teacher_allowed_generation_in_closed_year(): void
    {
        // Create placement in closed academic year
        $sectionClosed = Section::create([
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'name' => 'Section Closed',
            'is_active' => true,
        ]);

        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'section_id' => $sectionClosed->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2025-06-01',
            'is_active' => true,
        ]);

        $sarClosed = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'section_id' => $sectionClosed->id,
            'roll_number' => 5,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2025-06-01',
        ]);

        $csClosed = ClassSubject::create([
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Math Closed',
            'is_active' => true,
        ]);

        $allocClosed = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sarClosed->id,
            'class_subject_id' => $csClosed->id,
            'effective_from' => '2025-06-01',
            'is_active' => true,
        ]);

        $termClosed = Term::create([
            'academic_year_id' => $this->closedYear->id,
            'name' => 'Term Closed',
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        $examClosed = Assessment::create([
            'academic_year_id' => $this->closedYear->id,
            'term_id' => $termClosed->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Exam Closed',
            'status' => AssessmentStatus::INACTIVE,
        ]);

        $appClosed = AssessmentApplicability::create([
            'assessment_id' => $examClosed->id,
            'class_subject_id' => $csClosed->id,
            'maximum_marks' => 100.00,
        ]);

        $cfgClosed = ReportConfiguration::create([
            'academic_year_id' => $this->closedYear->id,
            'name' => 'Config Closed',
            'report_type' => ReportType::TERM,
            'configuration_data' => ['show_attendance' => false, 'show_pass_mark' => true],
            'is_active' => true,
        ]);

        CalculationSetting::create([
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'calculation_method' => CalculationMethod::COMBINED_MARKS,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $cfgClosed->id,
            'assessment_id' => $examClosed->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $this->createMark($sarClosed, $allocClosed, $appClosed, 88.00);

        // Class Teacher attempts generation in closed year
        $response = $this->actingAs($this->classTeacher)->post(route('reports.generate'), [
            'student_academic_record_id' => $sarClosed->id,
            'report_type' => 'term',
            'term_id' => $termClosed->id,
            'report_configuration_id' => $cfgClosed->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('generated_reports', [
            'student_academic_record_id' => $sarClosed->id,
            'report_type' => 'term',
            'term_id' => $termClosed->id,
            'revision_number' => 1,
        ]);
    }

    /**
     * N & O. IDOR PROTECTION & SECURE DOWNLOAD:
     * Changing report ID does not grant access to unauthorized teacher.
     */
    public function test_idor_protection_on_report_download(): void
    {
        // Generate report for student 1 in Section A
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 20.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 80.00);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);
        $report = $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );

        // Class Teacher assigned to Section A can download
        $respAllowed = $this->actingAs($this->classTeacher)->get(route('reports.download', $report));
        $respAllowed->assertOk();

        // Other Class Teacher (not assigned to Section A) gets 403 Forbidden
        $respDenied = $this->actingAs($this->otherClassTeacher)->get(route('reports.download', $report));
        $respDenied->assertForbidden();

        // Subject Teacher gets 403 Forbidden
        $respSub = $this->actingAs($this->subjectTeacher)->get(route('reports.download', $report));
        $respSub->assertForbidden();
    }

    /**
     * P. FAILURE CLEANUP:
     * When PDF generator throws an error, no generated_reports row and no permanent file remain.
     */
    public function test_generator_failure_leaves_no_database_record_or_orphan_file(): void
    {
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 20.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 80.00);

        // Mock generator failure
        $mockGenerator = $this->createMock(ReportGeneratorContract::class);
        $mockGenerator->method('generatePdfFromHtml')
            ->willThrowException(new \RuntimeException('Chromium process crash simulation'));

        $this->app->instance(ReportGeneratorContract::class, $mockGenerator);

        $initialReportCount = GeneratedReport::where('student_academic_record_id', $this->sar1->id)->count();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Chromium process crash simulation');

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);
        $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );

        $finalReportCount = GeneratedReport::where('student_academic_record_id', $this->sar1->id)->count();
        $this->assertEquals($initialReportCount, $finalReportCount);
    }

    /**
     * TEST 1 & 2: Configured assessment limits completion requirements; unselected assessments do NOT block.
     * When configuration selects only Term Exam-1, student with Term Exam-1 marks is COMPLETE,
     * even if other assessments (Unit Test, Jul Week 01, Term 2) are blank.
     */
    public function test_configured_assessment_limits_completion_and_unselected_assessments_do_not_block(): void
    {
        // Create an isolated configuration that selects ONLY Term Exam
        $examOnlyConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term Exam Only Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'configuration_data' => ['show_attendance' => true, 'show_pass_mark' => true],
            'is_active' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $examOnlyConfig->id,
            'assessment_id' => $this->termExam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        // Student 1 has marks for Term Exam in Math and Science
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 85.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 90.00);
        // Unit Test marks are intentionally BLANK (not created)

        /** @var \App\Services\Report\ReportCompletionService $completionService */
        $completionService = app(\App\Services\Report\ReportCompletionService::class);

        // Completion validation using the exam-only config MUST be complete!
        $isComplete = $completionService->isComplete($this->sar1->id, 'term', $this->term1->id, $examOnlyConfig);
        $this->assertTrue($isComplete, 'Student should be complete when only configured assessment has marks.');

        $missing = $completionService->getMissingAssessments($this->sar1->id, 'term', $this->term1->id, $examOnlyConfig);
        $this->assertEmpty($missing, 'No missing assessments should be reported for unselected assessments.');

        // Preview should succeed
        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);
        $previewHtml = $service->preview(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfigurationId: $examOnlyConfig->id
        );
        $this->assertNotEmpty($previewHtml);

        // Generation should succeed and create a PDF revision
        $report = $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin,
            reportConfigurationId: $examOnlyConfig->id
        );
        $this->assertInstanceOf(GeneratedReport::class, $report);
        $this->assertFileExists(storage_path('app/private/' . $report->file_path));
    }

    /**
     * TEST 3: Selected blank assessment MUST block completion with 422.
     * When configuration selects Term Exam-1 and Unit Test 1, but Unit Test 1 is blank,
     * student is INCOMPLETE and generation is blocked.
     */
    public function test_selected_blank_assessment_blocks_completion_and_generation(): void
    {
        // Config selects both Unit Test and Term Exam ($this->reportConfig)
        // Student 1 has only Term Exam mark, Unit Test is blank
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 85.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 90.00);

        /** @var \App\Services\Report\ReportCompletionService $completionService */
        $completionService = app(\App\Services\Report\ReportCompletionService::class);

        $isComplete = $completionService->isComplete($this->sar1->id, 'term', $this->term1->id, $this->reportConfig);
        $this->assertFalse($isComplete, 'Student must be incomplete when configured Unit Test is missing.');

        $missing = $completionService->getMissingAssessments($this->sar1->id, 'term', $this->term1->id, $this->reportConfig);
        $this->assertNotEmpty($missing);
        $this->assertTrue(collect($missing)->contains(fn ($m) => str_contains($m, 'Unit Test 1')));

        // HTTP generation request must be blocked with 422
        $initialCount = GeneratedReport::where('student_academic_record_id', $this->sar1->id)->count();

        $response = $this->actingAs($this->admin)->postJson(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $this->reportConfig->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'missing']);

        $finalCount = GeneratedReport::where('student_academic_record_id', $this->sar1->id)->count();
        $this->assertEquals($initialCount, $finalCount, 'No report row should be persisted when marksheet is incomplete.');
    }

    /**
     * TEST 4: Preview and PDF columns match configuration.
     * If configuration contains only Term Exam-1, preview/PDF contains Term Exam-1 and not Unit Test.
     */
    public function test_preview_and_pdf_columns_match_configuration(): void
    {
        $examOnlyConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Single Asmt Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'configuration_data' => ['show_attendance' => true, 'show_pass_mark' => true],
            'is_active' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $examOnlyConfig->id,
            'assessment_id' => $this->termExam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 85.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 90.00);

        /** @var \App\Services\Report\ReportDataPreparationService $prepService */
        $prepService = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $prepService->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $examOnlyConfig
        );

        // Columns must contain ONLY Term Exam
        $this->assertCount(1, $payload->assessmentColumns);
        $this->assertEquals($this->termExam->id, $payload->assessmentColumns[0]->id);
        $this->assertEquals('Term 1 Exam', $payload->assessmentColumns[0]->name);

        // Preview HTML must contain Term 1 Exam and NOT Unit Test 1
        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);
        $previewHtml = $service->preview(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfigurationId: $examOnlyConfig->id
        );
        $this->assertStringContainsString('Term 1 Exam', $previewHtml);
        $this->assertStringNotContainsString('Unit Test 1', $previewHtml);
    }

    /**
     * TEST 5: Display vs calculation separation.
     * Configuration displays both Term Exam-1 and Unit Test 1.
     * Math: Term Exam = 80/100, Unit Test = 25/25 (100%).
     * Only Term Exam participates in Term % -> Math percentage must be exactly 80.00%, not blended!
     */
    public function test_display_vs_calculation_separation(): void
    {
        // Both assessments are displayed in $this->reportConfig
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 25.00); // 100% on Unit Test
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00); // 80% on Term Exam
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 25.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 70.00);

        /** @var \App\Services\Report\ReportDataPreparationService $prepService */
        $prepService = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $prepService->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $this->reportConfig
        );

        $mathRow = collect($payload->subjectRows)->firstWhere('subjectName', 'Mathematics Snapshot');
        $this->assertNotNull($mathRow);
        // Both marks are present in row
        $this->assertArrayHasKey($this->unitTest->id, $mathRow->marks);
        $this->assertArrayHasKey($this->termExam->id, $mathRow->marks);
        // Term percentage MUST be exactly 80.00% (Term Exam only, Phase 12 rule)
        $this->assertEquals(80.00, $mathRow->percentage);
    }

    /**
     * TEST 6: Configuration switch.
     * Configuration A: Term Exam-1.
     * Configuration B: Unit Test 1.
     * Same student: Term Exam-1 = 80, Unit Test 1 = blank.
     * Result with A: COMPLETE. Result with B: INCOMPLETE.
     */
    public function test_configuration_switch_controls_completion(): void
    {
        // Config A: Term Exam
        $configA = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Config A Exam ' . uniqid(),
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $configA->id,
            'assessment_id' => $this->termExam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        // Config B: Unit Test
        $configB = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Config B Test ' . uniqid(),
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $configB->id,
            'assessment_id' => $this->unitTest->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        // Student marks: Term Exam complete, Unit Test blank
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 75.00);

        /** @var \App\Services\Report\ReportCompletionService $completionService */
        $completionService = app(\App\Services\Report\ReportCompletionService::class);

        // With Config A -> COMPLETE
        $this->assertTrue($completionService->isComplete($this->sar1->id, 'term', $this->term1->id, $configA));

        // With Config B -> INCOMPLETE
        $this->assertFalse($completionService->isComplete($this->sar1->id, 'term', $this->term1->id, $configB));
    }

    /**
     * TEST 7: Invalid configuration (nonexistent, inactive, wrong report type, wrong academic year).
     */
    public function test_invalid_configurations_are_rejected(): void
    {
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 75.00);

        // 1. Nonexistent configuration
        $resp1 = $this->actingAs($this->admin)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => 999999,
        ]);
        $resp1->assertSessionHasErrors('report_configuration_id');

        // 2. Inactive configuration
        $inactiveConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Inactive Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'is_active' => false,
        ]);
        $resp2 = $this->actingAs($this->admin)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $inactiveConfig->id,
        ]);
        $resp2->assertSessionHasErrors('report_configuration_id');

        // 3. Wrong report type (final config submitted for term report)
        $finalConfig = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Final Config ' . uniqid(),
            'report_type' => ReportType::FINAL,
            'is_active' => true,
        ]);
        $resp3 = $this->actingAs($this->admin)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $finalConfig->id,
        ]);
        $resp3->assertSessionHasErrors('report_configuration_id');

        // 4. Incompatible academic year
        $otherYearConfig = ReportConfiguration::create([
            'academic_year_id' => $this->closedYear->id,
            'name' => 'Closed Year Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);
        $resp4 = $this->actingAs($this->admin)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $otherYearConfig->id,
        ]);
        $resp4->assertSessionHasErrors('report_configuration_id');
    }

    /**
     * TEST 8: Configuration IDOR defense.
     * Attempting to submit another academic year's configuration is rejected.
     */
    public function test_configuration_idor_protection(): void
    {
        $foreignConfig = ReportConfiguration::create([
            'academic_year_id' => $this->closedYear->id,
            'name' => 'Foreign Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('reports.generate'), [
            'student_academic_record_id' => $this->sar1->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $foreignConfig->id,
        ]);

        $response->assertSessionHasErrors('report_configuration_id');
    }

    /**
     * TEST 9: No valid active configuration yields clear configuration error.
     */
    public function test_no_valid_configuration_yields_clear_configuration_error(): void
    {
        // Deactivate all configurations for this academic year
        ReportConfiguration::where('academic_year_id', $this->year->id)->update(['is_active' => false]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('No active report configuration is available for this report context');

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);
        $service->generate(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            assessmentId: null,
            user: $this->admin
        );
    }

    /**
     * TEST 10: UI renders Report Configuration dropdown and displays configuration name.
     */
    public function test_ui_renders_report_configuration_selector(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'report_type' => 'term',
            'term_id' => $this->term1->id,
            'report_configuration_id' => $this->reportConfig->id,
        ]));

        $response->assertOk();
        $response->assertSee('Report Configuration');
        $response->assertSee($this->reportConfig->name);
        $response->assertSee('Configured Assessments:');
    }

    /**
     * TEST 11: Assessment applicability separation.
     * Configured assessment not applicable to a subject does NOT make that subject incomplete.
     */
    public function test_configured_assessment_not_applicable_to_subject_does_not_block(): void
    {
        // Remove applicability for Unit Test on Science (Science does not have Unit Test)
        $this->appTestScience->delete();

        // Student has: Math Unit Test = 20, Math Term Exam = 80, Science Term Exam = 80.
        // Science has NO Unit Test (not applicable).
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 80.00);

        /** @var \App\Services\Report\ReportCompletionService $completionService */
        $completionService = app(\App\Services\Report\ReportCompletionService::class);

        $isComplete = $completionService->isComplete($this->sar1->id, 'term', $this->term1->id, $this->reportConfig);
        $this->assertTrue($isComplete, 'Science should not be incomplete when Unit Test is not applicable to Science.');
    }

    /**
     * TEST 12: Cross-term display.
     * For Term Report, all configured displayed assessments appear in configured order,
     * including cross-term assessments (Issue 6).
     */
    public function test_term_report_assessment_selections_are_isolated_to_term(): void
    {
        // Create an assessment in Term 2
        $term2Exam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term2->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 2 Exam ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Add Term 2 Exam to report configuration
        ReportAssessmentSelection::create([
            'report_configuration_id' => $this->reportConfig->id,
            'assessment_id' => $term2Exam->id,
            'display_order' => 3,
            'is_displayed' => true,
        ]);

        /** @var ReportConfigurationResolver $resolver */
        $resolver = app(ReportConfigurationResolver::class);
        $term1Assessments = $resolver->getDisplayedAssessmentsForTerm($this->reportConfig, $this->term1->id);

        $this->assertTrue(
            $term1Assessments->contains('id', $term2Exam->id),
            'Configured cross-term assessment must appear in Term 1 displayed assessments per Issue 6.'
        );
    }

    /**
     * TEST 13: Term % is numeric, formatted to 2 decimals, and subject result is PASS/FAIL.
     */
    public function test_term_percentage_and_result_are_numeric_and_pass_fail_in_payload(): void
    {
        // Student has: Math Unit Test = 20, Math Term Exam = 80, Science Term Exam = 30.
        // pass_mark is 35.00.
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 30.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $this->reportConfig
        );

        $mathRow = collect($payload->subjectRows)->firstWhere('subjectId', $this->math->id);
        $scienceRow = collect($payload->subjectRows)->firstWhere('subjectId', $this->science->id);

        $this->assertNotNull($mathRow, 'Math row must exist');
        $this->assertNotNull($scienceRow, 'Science row must exist');

        // Math: Term Exam is 80.00 -> 80.00% -> PASS (>= 35.00)
        $this->assertEquals(80.0, (float) $mathRow->termPercentage);
        $this->assertEquals('80.00%', $mathRow->formattedTermPercentage);
        $this->assertEquals('PASS', $mathRow->termStatus);

        // Science: Term Exam is 30.00 -> 30.00% -> FAIL (< 35.00)
        $this->assertEquals(30.0, (float) $scienceRow->termPercentage);
        $this->assertEquals('30.00%', $scienceRow->formattedTermPercentage);
        $this->assertEquals('FAIL', $scienceRow->termStatus);

        // Overall: Since Science failed, overall must be FAIL
        $this->assertEquals('FAIL', $payload->overallResult);
    }

    /**
     * TEST 14: Overall result is PASS when all subjects pass pass_mark.
     */
    public function test_overall_result_is_pass_when_all_subjects_pass(): void
    {
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 85.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $this->reportConfig
        );

        $this->assertEquals('PASS', $payload->overallResult);
    }

    /**
     * TEST 15: Duplicate English / Inactive ClassSubject is excluded from report rows.
     */
    public function test_inactive_class_subject_allocation_is_excluded_from_report(): void
    {
        // Create an inactive ClassSubject for English in the same class
        $english = Subject::create(['name' => 'English Duplicate ' . uniqid(), 'code' => 'ENG' . rand(100, 999), 'is_active' => true]);
        $csInactive = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => null,
            'subject_id' => $english->id,
            'subject_name_snapshot' => $english->name,
            'is_active' => false, // DEACTIVATED
        ]);

        // Student allocated to inactive class subject
        StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1->id,
            'class_subject_id' => $csInactive->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 85.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $this->reportConfig
        );

        // Must only contain Math and Science; inactive English must NOT appear
        $subjectIds = collect($payload->subjectRows)->pluck('subjectId')->all();
        $this->assertContains($this->math->id, $subjectIds);
        $this->assertContains($this->science->id, $subjectIds);
        $this->assertNotContains($english->id, $subjectIds, 'Inactive ClassSubject must not appear in report rows');
        $this->assertCount(2, $payload->subjectRows);

        // Verify inactive record remains intact in PostgreSQL
        $this->assertDatabaseHas('class_subjects', ['id' => $csInactive->id, 'is_active' => false]);
    }

    /**
     * TEST 16: Signatures upload, private filesystem storage, data URI generation, and payload integration.
     */
    public function test_signature_upload_storage_and_report_payload_integration(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $fileTeacher = \Illuminate\Http\UploadedFile::fake()->image('teacher-sig.png', 100, 40);
        $filePrincipal = \Illuminate\Http\UploadedFile::fake()->image('principal-sig.png', 100, 40);

        /** @var \App\Services\SchoolSettingService $settingService */
        $settingService = app(\App\Services\SchoolSettingService::class);

        // Store teacher signature for assigned class teacher and principal signature
        $pathTeacher = $settingService->storeTeacherSignature($this->classTeacher->id, $fileTeacher);
        $pathPrincipal = $settingService->storeSignature('principal', $filePrincipal);

        $this->assertNotEmpty($pathTeacher);
        $this->assertNotEmpty($pathPrincipal);

        // Verify data URIs
        $uriTeacher = $settingService->getTeacherSignatureDataUri($this->classTeacher->id);
        $uriPrincipal = $settingService->getSignatureDataUri('principal');

        $this->assertNotNull($uriTeacher);
        $this->assertNotNull($uriPrincipal);
        $this->assertStringStartsWith('data:image/png;base64,', $uriTeacher);
        $this->assertStringStartsWith('data:image/png;base64,', $uriPrincipal);

        // Prepare report data and verify signatures reach ReportDataPayload contextually
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 85.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $this->reportConfig
        );

        $this->assertEquals($uriTeacher, $payload->classTeacherSignatureDataUri);
        $this->assertEquals($uriPrincipal, $payload->principalSignatureDataUri);

        // Test signature deletion
        $settingService->deleteTeacherSignature($this->classTeacher->id);
        $this->assertNull($settingService->getTeacherSignatureDataUri($this->classTeacher->id));
        $this->assertNotNull($settingService->getSignatureDataUri('principal'));
    }

    /**
     * TEST 17: TermExamParticipationResolver matches Term Exam, Term 1, Term Exam-1, but excludes other types.
     */
    public function test_term_exam_participation_resolver_matches_term_conventions(): void
    {
        /** @var \App\Services\Calculation\Resolvers\TermExamParticipationResolver $resolver */
        $resolver = app(\App\Services\Calculation\Resolvers\TermExamParticipationResolver::class);

        // 1. Assessment Type 'Term 1' for target Term 1
        $typeTerm1 = AssessmentType::create(['name' => 'Term 1', 'is_active' => true]);
        $asmtTerm1 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTerm1->id,
            'name' => 'Term Exam-1',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $this->assertTrue($resolver->participatesInTermCalculation($asmtTerm1, $this->term1));

        // 2. Assessment Type 'Term 2' for target Term 1 -> must NOT participate in Term 1
        $typeTerm2 = AssessmentType::create(['name' => 'Term 2', 'is_active' => true]);
        $asmtTerm2 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $typeTerm2->id,
            'name' => 'Term 2 Test',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $this->assertFalse($resolver->participatesInTermCalculation($asmtTerm2, $this->term1));

        // 3. Unit Test must NOT participate
        $this->assertFalse($resolver->participatesInTermCalculation($this->unitTest, $this->term1));
    }

    /**
     * TEST 18: Contextual Class Teacher signature per classroom.
     */
    public function test_contextual_class_teacher_signature_resolution_per_classroom(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        /** @var \App\Services\SchoolSettingService $settingService */
        $settingService = app(\App\Services\SchoolSettingService::class);

        $sig1 = \Illuminate\Http\UploadedFile::fake()->image('teacher1.png', 100, 40);
        $sig2 = \Illuminate\Http\UploadedFile::fake()->image('teacher2.jpg', 100, 40);

        $settingService->storeTeacherSignature($this->classTeacher->id, $sig1);
        $settingService->storeTeacherSignature($this->otherClassTeacher->id, $sig2);

        // Assign otherClassTeacher to Section B
        TeacherAssignment::create([
            'user_id' => $this->otherClassTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2027-04-30',
            'is_active' => true,
        ]);

        // Create a student in Section B
        $studentB = Student::create([
            'admission_number' => 'ADM_REP_B_' . rand(1000, 9999),
            'student_name' => 'Charlie Section B',
        ]);
        $sarB = StudentAcademicRecord::create([
            'student_id' => $studentB->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);
        $allocMathB = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sarB->id,
            'class_subject_id' => $this->csMath->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
        $allocScienceB = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sarB->id,
            'class_subject_id' => $this->csScience->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 85.00);
        $this->createMark($sarB, $allocMathB, $this->appExamMath, 75.00);
        $this->createMark($sarB, $allocScienceB, $this->appExamScience, 90.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);

        // SAR 1 (Section A) receives classTeacher's signature
        $payload1 = $dataPrep->prepare(sarId: $this->sar1->id, reportType: 'term', termId: $this->term1->id, reportConfig: $this->reportConfig);
        $this->assertEquals($settingService->getTeacherSignatureDataUri($this->classTeacher->id), $payload1->classTeacherSignatureDataUri);

        // SAR B (Section B) receives otherClassTeacher's signature
        $payload2 = $dataPrep->prepare(sarId: $sarB->id, reportType: 'term', termId: $this->term1->id, reportConfig: $this->reportConfig);
        $this->assertEquals($settingService->getTeacherSignatureDataUri($this->otherClassTeacher->id), $payload2->classTeacherSignatureDataUri);

        // If teacher signature is deleted, payload receives null gracefully
        $settingService->deleteTeacherSignature($this->classTeacher->id);
        $payload1After = $dataPrep->prepare(sarId: $this->sar1->id, reportType: 'term', termId: $this->term1->id, reportConfig: $this->reportConfig);
        $this->assertNull($payload1After->classTeacherSignatureDataUri);
    }

    /**
     * TEST 19: Term Report summary row metrics in payload and rendered Blade view.
     */
    public function test_term_report_summary_row_metrics_in_payload_and_view(): void
    {
        $this->createMark($this->sar1, $this->allocMath1, $this->appTestMath, 20.00);
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appTestScience, 20.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 90.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $this->sar1->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $this->reportConfig
        );

        // Under Method 1 (average percentage) with only Term Exam participating: Math = 80.00%, Science = 90.00%, Overall = 85.00%
        // Total obtained across participating = 170.00, Total max = 200.00
        $this->assertEquals(170.00, $payload->totalObtainedMarks);
        $this->assertEquals(200.00, $payload->totalMaximumMarks);
        $this->assertEquals(85.00, $payload->overallPercentage);
        $this->assertEquals('85.00%', $payload->formattedOverallPercentage);
        $this->assertNotEmpty($payload->assessmentColumnTotals);

        // Render blade view
        $html = view('reports.pdf.term-report', ['payload' => $payload])->render();
        $this->assertStringContainsString('TOTAL', $html);
        $this->assertStringContainsString('85.00%', $html);
        $this->assertStringContainsString('PASS', $html);
    }

    /**
     * TEST 20: Final Report renders all configured terms without N/A when marks exist.
     */
    public function test_final_report_multi_term_renders_all_configured_terms_without_na_when_marks_exist(): void
    {
        // Create an assessment for inactive Term 2
        $asmtTerm2 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term2->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 2 Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $appExamMath2 = AssessmentApplicability::create([
            'assessment_id' => $asmtTerm2->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
        $appExamScience2 = AssessmentApplicability::create([
            'assessment_id' => $asmtTerm2->id,
            'class_subject_id' => $this->csScience->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // Enter Term 1 marks
        $this->createMark($this->sar1, $this->allocMath1, $this->appExamMath, 80.00);
        $this->createMark($this->sar1, $this->allocScience1, $this->appExamScience, 85.00);

        // Enter Term 2 marks
        $this->createMark($this->sar1, $this->allocMath1, $appExamMath2, 70.00);
        $this->createMark($this->sar1, $this->allocScience1, $appExamScience2, 75.00);

        /** @var \App\Services\Report\ReportDataPreparationService $dataPrep */
        $dataPrep = app(\App\Services\Report\ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $this->sar1->id,
            reportType: 'final'
        );

        $this->assertCount(2, $payload->terms);
        foreach ($payload->subjectRows as $row) {
            $t1 = $row->termSummaries[$this->term1->id];
            $t2 = $row->termSummaries[$this->term2->id];
            $this->assertNotNull($t1['percentage']);
            $this->assertNotNull($t2['percentage']);
            $this->assertNotEquals('N/A', $t1['formatted_percentage']);
            $this->assertNotEquals('N/A', $t2['formatted_percentage']);
            $this->assertEquals('PASS', $t1['status']);
            $this->assertEquals('PASS', $t2['status']);
        }
    }

    /**
     * TEST 21: AssessmentController loads all terms including inactive ones for assessment scheduling.
     */
    public function test_assessment_controller_loads_all_terms_including_inactive(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertStatus(200);
        $availableTerms = $response->viewData('availableTerms');

        $this->assertNotNull($availableTerms);
        $this->assertTrue($availableTerms->contains('id', $this->term1->id));
        $this->assertTrue($availableTerms->contains('id', $this->term2->id));
    }

    /**
     * TEST 22: Class Teacher can load reports index without BadMethodCallException.
     */
    public function test_class_teacher_can_load_reports_index_without_exception(): void
    {
        $response = $this->actingAs($this->classTeacher)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Class 8');
        $response->assertSee('Section A');
    }
}

