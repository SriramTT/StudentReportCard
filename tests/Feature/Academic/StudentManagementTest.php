<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\StudentPlacementStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $classTeacher;
    protected User $subjectTeacher;

    protected AcademicYear $academicYear;
    protected SchoolClass $schoolClass;
    protected Section $sectionA;
    protected Section $sectionB;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $ctRole = Role::where('name', 'Class Teacher')->firstOrFail();
        $stRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'stud_admin_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Student Admin',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'stud_office_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Student Office',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'stud_ct_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Student Class Teacher',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 'stud_st_' . uniqid(),
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Student Subject Teacher',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::forceCreate([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->schoolClass = SchoolClass::forceCreate([
            'name' => 'Class X_' . rand(1000, 9999),
            'is_active' => true,
        ]);

        $this->sectionA = Section::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'name' => 'SecA_' . rand(100, 999),
            'is_active' => true,
        ]);

        $this->sectionB = Section::forceCreate([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'name' => 'SecB_' . rand(100, 999),
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. STUDENT CREATION TESTS
    // =========================================================================

    public function test_admin_can_create_student_with_admission_number(): void
    {
        $adm = 'ADM_' . uniqid();
        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'admission_number' => "  {$adm}  ",
            'student_name' => '  Rohan Sharma  ',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 1,
            'effective_from' => '2026-06-01',
        ]);

        $student = Student::where('admission_number', $adm)->first();
        $this->assertNotNull($student);
        $this->assertEquals('Rohan Sharma', $student->student_name);
        $this->assertEquals($adm, $student->admission_number);

        $response->assertRedirect(route('students.show', $student));

        $record = StudentAcademicRecord::where('student_id', $student->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals(1, $record->roll_number);
        $this->assertEquals(StudentPlacementStatus::ACTIVE, $record->status);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'entity_type' => 'students',
            'entity_id' => $student->id,
        ]);
    }

    public function test_office_staff_can_create_student(): void
    {
        $adm = 'ADM_' . uniqid();
        $response = $this->actingAs($this->officeStaff)->post(route('students.store'), [
            'admission_number' => $adm,
            'student_name' => 'Priya Patel',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 2,
        ]);

        $student = Student::where('admission_number', $adm)->first();
        $this->assertNotNull($student);
        $response->assertRedirect(route('students.show', $student));
    }

    public function test_teachers_cannot_create_student(): void
    {
        $adm = 'ADM_' . uniqid();

        $responseCT = $this->actingAs($this->classTeacher)->post(route('students.store'), [
            'admission_number' => $adm,
            'student_name' => 'Teacher Attempt',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 3,
        ]);
        $responseCT->assertForbidden();

        $responseST = $this->actingAs($this->subjectTeacher)->post(route('students.store'), [
            'admission_number' => $adm,
            'student_name' => 'Teacher Attempt',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 3,
        ]);
        $responseST->assertForbidden();
    }

    public function test_missing_admission_number_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'admission_number' => '',
            'student_name' => 'Invalid Student',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 4,
        ]);

        $response->assertSessionHasErrors(['admission_number']);
    }

    public function test_duplicate_admission_number_is_rejected(): void
    {
        $adm = 'ADM_DUP_' . uniqid();
        Student::create([
            'admission_number' => $adm,
            'student_name' => 'First Student',
        ]);

        $response = $this->actingAs($this->admin)->post(route('students.store'), [
            'admission_number' => $adm,
            'student_name' => 'Second Student',
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 5,
        ]);

        $response->assertSessionHasErrors(['admission_number']);
    }

    // =========================================================================
    // 2. STUDENT UPDATE & IMMUTABILITY TESTS
    // =========================================================================

    public function test_authorized_user_can_update_student_name(): void
    {
        $adm = 'ADM_UPD_' . uniqid();
        $student = Student::create([
            'admission_number' => $adm,
            'student_name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->admin)->put(route('students.update', $student), [
            'student_name' => 'Updated Name',
        ]);

        $response->assertRedirect(route('students.show', $student));
        $student->refresh();
        $this->assertEquals('Updated Name', $student->student_name);
        $this->assertEquals($adm, $student->admission_number);
    }

    public function test_admission_number_remains_immutable_on_update(): void
    {
        $adm = 'ADM_IMM_' . uniqid();
        $student = Student::create([
            'admission_number' => $adm,
            'student_name' => 'Original Student',
        ]);

        // Attempt to pass a new admission number
        $this->actingAs($this->admin)->put(route('students.update', $student), [
            'student_name' => 'New Name',
            'admission_number' => 'NEW_ADM_TAMPERED',
        ]);

        $student->refresh();
        $this->assertEquals($adm, $student->admission_number);
        $this->assertEquals('New Name', $student->student_name);
    }

    public function test_teachers_cannot_update_student(): void
    {
        $student = Student::create([
            'admission_number' => 'ADM_TUPD_' . uniqid(),
            'student_name' => 'Student Test',
        ]);

        $this->actingAs($this->subjectTeacher)
            ->put(route('students.update', $student), ['student_name' => 'Hacked Name'])
            ->assertForbidden();
    }

    // =========================================================================
    // 3. STUDENT DIRECTORY & SEARCH TESTS
    // =========================================================================

    public function test_student_directory_search_by_admission_number(): void
    {
        $admTarget = 'SEARCH_ADM_' . uniqid();
        $studentTarget = Student::create([
            'admission_number' => $admTarget,
            'student_name' => 'Special Target Student',
        ]);

        $studentOther = Student::create([
            'admission_number' => 'OTHER_ADM_' . uniqid(),
            'student_name' => 'Other Unrelated Student',
        ]);

        $response = $this->actingAs($this->admin)->get(route('students.index', ['search' => $admTarget]));
        $response->assertOk();
        $response->assertSee($admTarget);
        $response->assertSee('Special Target Student');
        $response->assertDontSee($studentOther->admission_number);
    }

    public function test_student_directory_search_by_name(): void
    {
        $adm = 'ADM_NAME_' . uniqid();
        Student::create([
            'admission_number' => $adm,
            'student_name' => 'Aarav UniqueChowdhury',
        ]);

        $response = $this->actingAs($this->admin)->get(route('students.index', ['search' => 'UniqueChowdhury']));
        $response->assertOk();
        $response->assertSee($adm);
        $response->assertSee('Aarav UniqueChowdhury');
    }

    public function test_student_directory_page_loads_without_lazy_loading_violation(): void
    {
        $student = Student::create([
            'admission_number' => 'ADM_LAZY_' . uniqid(),
            'student_name' => 'Lazy Test Student',
        ]);

        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 10,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $response = $this->actingAs($this->admin)->get(route('students.index'));
        $response->assertOk();
        $response->assertSee($student->admission_number);
    }

    // =========================================================================
    // 4. HISTORICAL PLACEMENT & TRANSFER TESTS
    // =========================================================================

    public function test_internal_transfer_closes_old_placement_and_creates_new_placement(): void
    {
        $adm = 'ADM_TRANS_' . uniqid();
        $student = Student::create([
            'admission_number' => $adm,
            'student_name' => 'Transfer Student',
        ]);

        $initialRecord = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $response = $this->actingAs($this->admin)->post(route('students.transfer', $student), [
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 15,
            'effective_date' => '2026-09-01',
        ]);

        $response->assertRedirect(route('students.show', $student));

        // Initial record is closed with internal transfer status
        $initialRecord->refresh();
        $this->assertEquals(StudentPlacementStatus::INTERNAL_TRANSFER, $initialRecord->status);
        $this->assertEquals('2026-09-01', $initialRecord->effective_to->format('Y-m-d'));

        // New active record created
        $newRecord = StudentAcademicRecord::where('student_id', $student->id)
            ->where('status', StudentPlacementStatus::ACTIVE)
            ->first();
        $this->assertNotNull($newRecord);
        $this->assertEquals($this->sectionB->id, $newRecord->section_id);
        $this->assertEquals(15, $newRecord->roll_number);
        $this->assertEquals('2026-09-01', $newRecord->effective_from->format('Y-m-d'));

        // Same master student id, exactly 2 placement records
        $this->assertEquals(2, $student->academicRecords()->count());
    }

    // =========================================================================
    // 5. CSV IMPORT IDENTITY MATCHING TESTS (CASES 1 THROUGH 6)
    // =========================================================================

    public function test_csv_import_case1_new_admission_number_creates_student(): void
    {
        $adm = 'ADM_CSV1_' . uniqid();
        $csvContent = "admission_number,student_name,roll_number\n{$adm},New CSV Student,101\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $student = Student::where('admission_number', $adm)->first();
        $this->assertNotNull($student);
        $this->assertEquals('New CSV Student', $student->student_name);

        $record = StudentAcademicRecord::where('student_id', $student->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals(101, $record->roll_number);
    }

    public function test_csv_import_case2_existing_admission_number_reuses_student_master(): void
    {
        $adm = 'ADM_CSV2_' . uniqid();
        $student = Student::create([
            'admission_number' => $adm,
            'student_name' => 'Existing Student One',
        ]);

        $initialStudentCount = Student::count();

        $csvContent = "admission_number,student_name,roll_number\n{$adm},Existing Student One,50\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionB->id,
        ]);

        $response->assertOk();
        // Zero additional student master rows created
        $this->assertEquals($initialStudentCount, Student::count());

        $record = StudentAcademicRecord::where('student_id', $student->id)
            ->where('section_id', $this->sectionB->id)
            ->first();
        $this->assertNotNull($record);
        $this->assertEquals(50, $record->roll_number);
    }

    public function test_csv_import_case3_name_mismatch_is_rejected_and_does_not_overwrite(): void
    {
        $adm = 'ADM_CSV3_' . uniqid();
        $student = Student::create([
            'admission_number' => $adm,
            'student_name' => 'True Original Name',
        ]);

        $csvContent = "admission_number,student_name,roll_number\n{$adm},Completely Different Name,25\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('does not match');

        // Original student name unchanged
        $student->refresh();
        $this->assertEquals('True Original Name', $student->student_name);
        $this->assertEquals(0, $student->academicRecords()->count());
    }

    public function test_csv_import_case4_duplicate_admission_number_in_same_csv_is_rejected(): void
    {
        $adm = 'ADM_CSV4_' . uniqid();
        $csvContent = "admission_number,student_name,roll_number\n{$adm},Student Alpha,1\n{$adm},Student Alpha,2\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('Duplicate admission number');

        // Student not created
        $this->assertNull(Student::where('admission_number', $adm)->first());
    }

    public function test_csv_import_case5_blank_admission_number_is_rejected(): void
    {
        $csvContent = "admission_number,student_name,roll_number\n,Nameless Student,1\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('students.import'), [
            'file' => $file,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->sectionA->id,
        ]);

        $response->assertOk();
        $response->assertSee('Admission number is required');
    }

    public function test_csv_import_template_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('students.template'));
        $response->assertOk();
        $this->assertStringContainsString('admission_number,student_name,roll_number', $response->getContent());
    }
}
