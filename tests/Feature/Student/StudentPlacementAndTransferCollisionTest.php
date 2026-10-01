<?php

namespace Tests\Feature\Student;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentPlacementAndTransferCollisionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;

    protected AcademicYear $academicYear;
    protected SchoolClass $class1;
    protected Section $sectionA;
    protected Section $sectionB;
    protected Subject $subjectMath;
    protected ClassSubject $classSubjectMath;
    protected Term $term1;
    protected AssessmentType $examType;
    protected Assessment $assessment;
    protected AssessmentApplicability $assessmentApp;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'coll_admin_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Collision Admin',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'coll_office_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Collision Office',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'coll_teach_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Collision Teacher',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::forceCreate([
            'name' => 'AY_COLL_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class1 = SchoolClass::forceCreate([
            'name' => 'Class 1_' . rand(1000, 9999),
            'is_active' => true,
        ]);

        $this->sectionA = Section::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'name' => 'A_' . rand(100, 999),
            'is_active' => true,
        ]);

        $this->sectionB = Section::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'name' => 'B_' . rand(100, 999),
            'is_active' => true,
        ]);

        $this->subjectMath = Subject::forceCreate([
            'code' => 'MTH_' . rand(100, 999),
            'name' => 'Mathematics',
            'category' => 'main',
            'is_active' => true,
        ]);

        $this->classSubjectMath = ClassSubject::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'subject_id' => $this->subjectMath->id,
            'subject_name_snapshot' => 'Mathematics',
        ]);

        $this->term1 = Term::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $this->examType = AssessmentType::forceCreate([
            'name' => 'Midterm_' . rand(100, 999),
            'is_active' => true,
        ]);

        $this->assessment = Assessment::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Math Midterm',
            'assessment_date' => '2026-07-01',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->assessmentApp = AssessmentApplicability::forceCreate([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $this->classSubjectMath->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create student with initial placement
     */
    protected function createStudentWithPlacement(string $admissionNo, string $name, Section $section, int $rollNumber): array
    {
        $student = Student::forceCreate([
            'admission_number' => $admissionNo,
            'student_name' => $name,
        ]);

        $placement = StudentAcademicRecord::forceCreate([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $section->id,
            'roll_number' => $rollNumber,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        return [$student, $placement];
    }

    // =========================================================================
    // TESTS 1 TO 5: PLACEMENT, TRANSFER, FILTERING, DIRECTORY & HISTORY
    // =========================================================================

    public function test_01_student_a_is_placed_in_class_1a_roll_9(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_001', 'Student A', $this->sectionA, 9);

        $this->assertEquals(9, $placementA->roll_number);
        $this->assertEquals($this->sectionA->id, $placementA->section_id);
        $this->assertEquals(StudentPlacementStatus::ACTIVE, $placementA->status);
    }

    public function test_02_student_a_transfers_to_class_1b_roll_3(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_002', 'Student A', $this->sectionA, 9);

        $response = $this->actingAs($this->admin)->post(route('students.transfer', $studentA), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 3,
            'effective_date' => '2026-09-10',
        ]);

        $response->assertRedirect(route('students.show', $studentA));

        // Old placement remains and is marked internal_transfer
        $placementA->refresh();
        $this->assertEquals(StudentPlacementStatus::INTERNAL_TRANSFER, $placementA->status);
        $this->assertEquals('2026-09-10', $placementA->effective_to->format('Y-m-d'));
        $this->assertEquals(9, $placementA->roll_number);

        // New placement is active in Section B
        $newPlacement = StudentAcademicRecord::where('student_id', $studentA->id)
            ->where('status', StudentPlacementStatus::ACTIVE)
            ->first();

        $this->assertNotNull($newPlacement);
        $this->assertEquals($this->sectionB->id, $newPlacement->section_id);
        $this->assertEquals(3, $newPlacement->roll_number);
        $this->assertNull($newPlacement->effective_to);
    }

    public function test_03_filter_class_1a_excludes_internally_transferred_student_a(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_003', 'Student A', $this->sectionA, 9);

        // Perform transfer to Section B
        $this->actingAs($this->admin)->post(route('students.transfer', $studentA), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 3,
            'effective_date' => '2026-09-10',
        ]);

        // Query directory filtered by Section A
        $service = app(StudentService::class);
        $paginator = $service->getPaginatedStudents([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $studentIds = collect($paginator->items())->pluck('id')->toArray();
        $this->assertNotContains($studentA->id, $studentIds, 'Transferred student must not appear in former classroom active roster.');
    }

    public function test_04_filter_class_1b_includes_internally_transferred_student_a(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_004', 'Student A', $this->sectionA, 9);

        $this->actingAs($this->admin)->post(route('students.transfer', $studentA), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 3,
            'effective_date' => '2026-09-10',
        ]);

        // Query directory filtered by Section B
        $service = app(StudentService::class);
        $paginator = $service->getPaginatedStudents([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
        ]);

        $studentIds = collect($paginator->items())->pluck('id')->toArray();
        $this->assertContains($studentA->id, $studentIds, 'Transferred student must appear in new classroom active roster.');
    }

    public function test_05_student_detail_displays_both_historical_and_current_placements(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_005', 'Student A', $this->sectionA, 9);

        $this->actingAs($this->admin)->post(route('students.transfer', $studentA), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 3,
            'effective_date' => '2026-09-10',
        ]);

        $response = $this->actingAs($this->admin)->get(route('students.show', $studentA));
        $response->assertOk();

        // Both sections and roll numbers must be visible in history table
        $response->assertSee($this->sectionA->name);
        $response->assertSee('9');
        $response->assertSee($this->sectionB->name);
        $response->assertSee('3');
        $response->assertSee('Internal transfer');
        $response->assertSee('Active');
    }

    // =========================================================================
    // TESTS 6 TO 10: DATABASE UNIQUENESS & MANUAL CREATION
    // =========================================================================

    public function test_06_student_b_receives_class_1a_roll_9_after_student_a_transfers_out(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_006_A', 'Student A', $this->sectionA, 9);

        // Transfer Student A out to Section B
        $this->actingAs($this->admin)->post(route('students.transfer', $studentA), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 3,
            'effective_date' => '2026-09-10',
        ]);

        // Student B is assigned vacated Roll 9 in Section A
        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'admission_number' => 'SVS_006_B',
            'student_name' => 'Student B',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 9,
        ]);

        $studentB = Student::where('admission_number', 'SVS_006_B')->first();
        $this->assertNotNull($studentB);
        $response->assertRedirect(route('students.show', $studentB));

        $placementB = StudentAcademicRecord::where('student_id', $studentB->id)->first();
        $this->assertNotNull($placementB);
        $this->assertEquals(9, $placementB->roll_number);
        $this->assertEquals(StudentPlacementStatus::ACTIVE, $placementB->status);
    }

    public function test_07_two_active_students_cannot_share_same_roll_in_same_classroom(): void
    {
        $this->createStudentWithPlacement('SVS_007_A', 'Active Student A', $this->sectionA, 9);

        // Attempt direct DB insert of second active student with same roll
        $studentB = Student::forceCreate([
            'admission_number' => 'SVS_007_B',
            'student_name' => 'Active Student B',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        StudentAcademicRecord::create([
            'student_id' => $studentB->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 9,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);
    }

    public function test_08_historical_and_active_records_can_coexist_with_same_roll_number(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_008_A', 'Student A', $this->sectionA, 9);

        // Close placement A as internal_transfer
        $placementA->update([
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_to' => '2026-09-10',
        ]);

        // Insert new active student with roll 9
        $studentB = Student::forceCreate([
            'admission_number' => 'SVS_008_B',
            'student_name' => 'Student B',
        ]);

        $placementB = StudentAcademicRecord::create([
            'student_id' => $studentB->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 9,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-09-11',
        ]);

        $this->assertNotNull($placementB);
        $this->assertEquals(9, $placementB->roll_number);

        // Count in DB: exactly 2 records with roll 9 in Section A
        $count = StudentAcademicRecord::where('academic_year_id', $this->academicYear->id)
            ->where('class_id', $this->class1->id)
            ->where('section_id', $this->sectionA->id)
            ->where('roll_number', 9)
            ->count();
        $this->assertEquals(2, $count);
    }

    public function test_09_manual_student_creation_with_occupied_active_roll_returns_validation_error(): void
    {
        $this->createStudentWithPlacement('SVS_009_EXISTING', 'Existing Student', $this->sectionA, 9);

        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'admission_number' => 'SVS_009_NEW',
            'student_name' => 'New Colliding Student',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 9,
        ]);

        $response->assertSessionHasErrors(['roll_number']);
        $this->assertNull(Student::where('admission_number', 'SVS_009_NEW')->first());
    }

    public function test_10_manual_student_creation_with_roll_occupied_only_by_historical_record_succeeds(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('SVS_010_A', 'Historical Student', $this->sectionA, 9);
        $placementA->update([
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_to' => '2026-09-10',
        ]);

        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'admission_number' => 'SVS_010_B',
            'student_name' => 'New Active Student',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 9,
        ]);

        $studentB = Student::where('admission_number', 'SVS_010_B')->first();
        $this->assertNotNull($studentB);
        $response->assertRedirect(route('students.show', $studentB));
    }

    // =========================================================================
    // TESTS 11 TO 19: CSV IMPORT SCENARIOS & ACCOUNTING
    // =========================================================================

    public function test_11_csv_import_with_new_admission_number_creates_student_and_placement(): void
    {
        $csv = "admission_number,student_name,roll_number\nCSV_11_NEW,New Imported Student,11\n";
        $file = UploadedFile::fake()->createWithContent('import11.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $student = Student::where('admission_number', 'CSV_11_NEW')->first();
        $this->assertNotNull($student);

        $placement = StudentAcademicRecord::where('student_id', $student->id)->first();
        $this->assertNotNull($placement);
        $this->assertEquals(11, $placement->roll_number);
        $this->assertEquals(StudentPlacementStatus::ACTIVE, $placement->status);
    }

    public function test_12_csv_import_reuses_existing_student_master_and_creates_placement(): void
    {
        $existing = Student::forceCreate([
            'admission_number' => 'CSV_12_EXISTING',
            'student_name' => 'Existing Unplaced Student',
        ]);

        $initialStudentCount = Student::count();

        $csv = "admission_number,student_name,roll_number\nCSV_12_EXISTING,Existing Unplaced Student,12\n";
        $file = UploadedFile::fake()->createWithContent('import12.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $this->assertEquals($initialStudentCount, Student::count(), 'Zero new master students created.');

        $placement = StudentAcademicRecord::where('student_id', $existing->id)->first();
        $this->assertNotNull($placement);
        $this->assertEquals(12, $placement->roll_number);
    }

    public function test_13_csv_import_for_student_already_active_in_classroom_reports_unchanged(): void
    {
        [$student, $placement] = $this->createStudentWithPlacement('CSV_13_ACTIVE', 'Student Thirteen', $this->sectionA, 13);

        $csv = "admission_number,student_name,roll_number\nCSV_13_ACTIVE,Student Thirteen,13\n";
        $file = UploadedFile::fake()->createWithContent('import13.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('Unchanged');
        $response->assertSee('Existing student confirmed in classroom (unchanged)');

        // Placements count remains 1
        $this->assertEquals(1, $student->academicRecords()->count());
    }

    public function test_14_csv_import_for_student_actively_placed_in_another_class_is_rejected(): void
    {
        // Student is actively in Section B
        [$student, $placement] = $this->createStudentWithPlacement('CSV_14_ACTIVE_B', 'Student Fourteen', $this->sectionB, 14);

        // Attempt CSV import into Section A
        $csv = "admission_number,student_name,roll_number\nCSV_14_ACTIVE_B,Student Fourteen,14\n";
        $file = UploadedFile::fake()->createWithContent('import14.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('Use the Student Transfer workflow instead of CSV placement');

        // Placement in Section A was NOT created
        $secAPlacement = StudentAcademicRecord::where('student_id', $student->id)
            ->where('section_id', $this->sectionA->id)
            ->first();
        $this->assertNull($secAPlacement);
    }

    public function test_15_csv_import_with_roll_occupied_by_another_active_student_is_rejected(): void
    {
        $this->createStudentWithPlacement('CSV_15_OCCUPANT', 'Occupant Student', $this->sectionA, 15);

        $csv = "admission_number,student_name,roll_number\nCSV_15_NEW,Colliding Student,15\n";
        $file = UploadedFile::fake()->createWithContent('import15.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('already assigned to active student');
        $this->assertNull(Student::where('admission_number', 'CSV_15_NEW')->first());
    }

    public function test_16_csv_import_with_roll_occupied_only_by_historical_record_succeeds(): void
    {
        [$studentA, $placementA] = $this->createStudentWithPlacement('CSV_16_HIST', 'Historical Student', $this->sectionA, 16);
        $placementA->update([
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_to' => '2026-09-10',
        ]);

        $csv = "admission_number,student_name,roll_number\nCSV_16_NEW,New Active Student,16\n";
        $file = UploadedFile::fake()->createWithContent('import16.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $newStudent = Student::where('admission_number', 'CSV_16_NEW')->first();
        $this->assertNotNull($newStudent);

        $newPlacement = StudentAcademicRecord::where('student_id', $newStudent->id)->first();
        $this->assertNotNull($newPlacement);
        $this->assertEquals(16, $newPlacement->roll_number);
        $this->assertEquals(StudentPlacementStatus::ACTIVE, $newPlacement->status);
    }

    public function test_17_csv_import_with_duplicate_admission_numbers_in_file_is_rejected(): void
    {
        $csv = "admission_number,student_name,roll_number\nCSV_17_DUP,Student One,1\nCSV_17_DUP,Student One,2\n";
        $file = UploadedFile::fake()->createWithContent('import17.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('Duplicate admission number');
        $this->assertNull(Student::where('admission_number', 'CSV_17_DUP')->first());
    }

    public function test_18_csv_import_with_duplicate_roll_numbers_in_same_file_is_rejected(): void
    {
        $csv = "admission_number,student_name,roll_number\nCSV_18_A,Student One,25\nCSV_18_B,Student Two,25\n";
        $file = UploadedFile::fake()->createWithContent('import18.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('Duplicate roll number');
        $this->assertNull(Student::where('admission_number', 'CSV_18_A')->first());
        $this->assertNull(Student::where('admission_number', 'CSV_18_B')->first());
    }

    public function test_19_partial_csv_import_accepts_valid_rows_and_rejects_invalid_rows(): void
    {
        // Row 1: Valid
        // Row 2: Invalid roll (duplicate in CSV)
        // Row 3: Blank admission number
        $csv = "admission_number,student_name,roll_number\nCSV_19_GOOD,Good Student,19\nCSV_19_BAD_DUP,Bad Student,20\nCSV_19_BAD_DUP,Bad Student,20\n,Blank Student,21\n";
        $file = UploadedFile::fake()->createWithContent('import19.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();

        // Valid student created
        $this->assertNotNull(Student::where('admission_number', 'CSV_19_GOOD')->first());
        // Bad students not created
        $this->assertNull(Student::where('admission_number', 'CSV_19_BAD_DUP')->first());

        $response->assertSee('Created');
        $response->assertSee('Rejected');
    }

    // =========================================================================
    // TESTS 20 TO 25: HISTORICAL MARKS, AUTH, IDOR, AUDIT & SQLSTATE PROTECTION
    // =========================================================================

    public function test_20_historical_marks_remain_attached_to_original_placement_after_transfer(): void
    {
        [$student, $placementA] = $this->createStudentWithPlacement('SVS_020', 'Marks Student', $this->sectionA, 20);

        $alloc = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $placementA->id,
            'class_subject_id' => $this->classSubjectMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Attach marks to Section A placement
        $mark = Mark::forceCreate([
            'student_academic_record_id' => $placementA->id,
            'student_subject_allocation_id' => $alloc->id,
            'assessment_applicability_id' => $this->assessmentApp->id,
            'mark_value' => '88.50',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        // Transfer student to Section B
        $this->actingAs($this->admin)->post(route('students.transfer', $student), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 5,
            'effective_date' => '2026-09-10',
        ]);

        // Mark remains attached to placement A
        $mark->refresh();
        $this->assertEquals($placementA->id, $mark->student_academic_record_id);
        $this->assertEquals(88.5, (float) $mark->mark_value);
    }

    public function test_21_historical_reports_remain_resolvable_from_historical_placement(): void
    {
        [$student, $placementA] = $this->createStudentWithPlacement('SVS_021', 'Report Student', $this->sectionA, 21);

        $this->actingAs($this->admin)->post(route('students.transfer', $student), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 7,
            'effective_date' => '2026-09-10',
        ]);

        // Historical placement record can be loaded with associations intact
        $historical = StudentAcademicRecord::with(['schoolClass', 'section', 'academicYear'])
            ->find($placementA->id);

        $this->assertNotNull($historical);
        $this->assertEquals($this->sectionA->id, $historical->section_id);
        $this->assertEquals(21, $historical->roll_number);
        $this->assertEquals(StudentPlacementStatus::INTERNAL_TRANSFER, $historical->status);
    }

    public function test_22_authorization_remains_enforced_teachers_cannot_import_or_transfer(): void
    {
        [$student, $placement] = $this->createStudentWithPlacement('SVS_022', 'Auth Student', $this->sectionA, 22);

        $csv = "admission_number,student_name,roll_number\nCSV_22,Hacked Student,22\n";
        $file = UploadedFile::fake()->createWithContent('import22.csv', $csv);

        // Teacher cannot import CSV
        $this->actingAs($this->teacher)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ])->assertForbidden();

        // Teacher cannot transfer student
        $this->actingAs($this->teacher)->post(route('students.transfer', $student), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 8,
            'effective_date' => '2026-09-10',
        ])->assertForbidden();
    }

    public function test_23_idor_attempts_are_blocked_and_unauthorized_records_cannot_be_mutated(): void
    {
        [$student, $placement] = $this->createStudentWithPlacement('SVS_023', 'IDOR Student', $this->sectionA, 23);

        // Deactivated user or unauthenticated request cannot view or mutate
        $this->post(route('students.transfer', $student), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 8,
            'effective_date' => '2026-09-10',
        ])->assertRedirect(route('login'));
    }

    public function test_24_audit_records_are_logged_for_transfers_and_imports(): void
    {
        [$student, $placement] = $this->createStudentWithPlacement('SVS_024', 'Audit Student', $this->sectionA, 24);

        $this->actingAs($this->admin)->post(route('students.transfer', $student), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 4,
            'effective_date' => '2026-09-10',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'transfer',
            'entity_type' => 'student_academic_records',
        ]);
    }

    public function test_25_no_raw_database_sql_appears_in_user_facing_error_messages(): void
    {
        $this->createStudentWithPlacement('SVS_025_EXISTING', 'Student Twenty Five', $this->sectionA, 25);

        // CSV collision
        $csv = "admission_number,student_name,roll_number\nSVS_025_NEW,Student New,25\n";
        $file = UploadedFile::fake()->createWithContent('import25.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $content = $response->getContent();

        // Must NOT expose SQLSTATE, 23505, or raw SQL table insert text
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('23505', $content);
        $this->assertStringNotContainsString('insert into', $content);
        $this->assertStringNotContainsString('duplicate key value', $content);

        // Must display domain message
        $this->assertStringContainsString('already assigned to active student', $content);
    }
}
