<?php

namespace Tests\Feature\Student;

use App\Enums\AcademicYearStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherStudentDirectoryAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $classTeacher8A;
    protected User $subTeacher9B;
    protected User $multiTeacher;

    protected AcademicYear $yearCurrent;
    protected AcademicYear $yearOther;

    protected SchoolClass $class8;
    protected SchoolClass $class9;
    protected SchoolClass $class10;

    protected Section $sec8A;
    protected Section $sec8B;
    protected Section $sec9A;
    protected Section $sec9B;
    protected Section $sec10A;

    protected Subject $math;

    protected Student $student8A;
    protected Student $student8B;
    protected Student $student9A;
    protected Student $student9B;
    protected Student $student10A;
    protected Student $studentTransferred8Ato8B;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $classRole = Role::where('name', 'Class Teacher')->firstOrFail();
        $subRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'dir_admin_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Directory Admin',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'dir_office_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Directory Office Staff',
            'is_active' => true,
        ]);

        $this->classTeacher8A = User::forceCreate([
            'role_id' => $classRole->id,
            'username' => 'dir_ct8a_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher 8A',
            'is_active' => true,
        ]);

        $this->subTeacher9B = User::forceCreate([
            'role_id' => $subRole->id,
            'username' => 'dir_st9b_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Subject Teacher 9B',
            'is_active' => true,
        ]);

        $this->multiTeacher = User::forceCreate([
            'role_id' => $classRole->id,
            'username' => 'dir_multi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Multi Assignment Teacher',
            'is_active' => true,
        ]);

        $this->yearCurrent = AcademicYear::create([
            'name' => 'AY_' . substr(uniqid(), 0, 10),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->yearOther = AcademicYear::create([
            'name' => 'AY_O_' . substr(uniqid(), 0, 10),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $this->class8 = SchoolClass::create(['name' => 'C8_' . uniqid()]);
        $this->class9 = SchoolClass::create(['name' => 'C9_' . uniqid()]);
        $this->class10 = SchoolClass::create(['name' => 'C10_' . uniqid()]);

        $this->sec8A = Section::create(['academic_year_id' => $this->yearCurrent->id, 'class_id' => $this->class8->id, 'name' => 'A']);
        $this->sec8B = Section::create(['academic_year_id' => $this->yearCurrent->id, 'class_id' => $this->class8->id, 'name' => 'B']);
        $this->sec9A = Section::create(['academic_year_id' => $this->yearCurrent->id, 'class_id' => $this->class9->id, 'name' => 'A']);
        $this->sec9B = Section::create(['academic_year_id' => $this->yearCurrent->id, 'class_id' => $this->class9->id, 'name' => 'B']);
        $this->sec10A = Section::create(['academic_year_id' => $this->yearCurrent->id, 'class_id' => $this->class10->id, 'name' => 'A']);

        $this->math = Subject::create(['name' => 'Mathematics', 'code' => 'MTH_' . uniqid(), 'category' => SubjectCategory::MAIN]);

        ClassSubject::create([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->sec9B->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        // Teacher assignments:
        // 1. Class Teacher for 8A
        TeacherAssignment::create([
            'user_id' => $this->classTeacher8A->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8A->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // 2. Subject Teacher for 9B Math
        TeacherAssignment::create([
            'user_id' => $this->subTeacher9B->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->sec9B->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // 3. Multi Teacher: 8A Class Teacher + 9B Math
        TeacherAssignment::create([
            'user_id' => $this->multiTeacher->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8A->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
        TeacherAssignment::create([
            'user_id' => $this->multiTeacher->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->sec9B->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Create Students with Active Placements
        $this->student8A = Student::create(['admission_number' => 'ADM_8A_' . uniqid(), 'student_name' => 'Alice 8A']);
        StudentAcademicRecord::create([
            'student_id' => $this->student8A->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->student8B = Student::create(['admission_number' => 'ADM_8B_' . uniqid(), 'student_name' => 'Bob 8B']);
        StudentAcademicRecord::create([
            'student_id' => $this->student8B->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8B->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->student9A = Student::create(['admission_number' => 'ADM_9A_' . uniqid(), 'student_name' => 'Charlie 9A']);
        StudentAcademicRecord::create([
            'student_id' => $this->student9A->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->sec9A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->student9B = Student::create(['admission_number' => 'ADM_9B_' . uniqid(), 'student_name' => 'Daisy 9B']);
        StudentAcademicRecord::create([
            'student_id' => $this->student9B->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->sec9B->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->student10A = Student::create(['admission_number' => 'ADM_10A_' . uniqid(), 'student_name' => 'Ethan 10A']);
        StudentAcademicRecord::create([
            'student_id' => $this->student10A->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class10->id,
            'section_id' => $this->sec10A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Student who formerly was in 8A but internally transferred to 8B
        $this->studentTransferred8Ato8B = Student::create(['admission_number' => 'ADM_TR_' . uniqid(), 'student_name' => 'Transferred Tom']);
        // Historical 8A placement (now internal_transfer)
        StudentAcademicRecord::create([
            'student_id' => $this->studentTransferred8Ato8B->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8A->id,
            'roll_number' => 99,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-08-01',
        ]);
        // Current active 8B placement
        StudentAcademicRecord::create([
            'student_id' => $this->studentTransferred8Ato8B->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8B->id,
            'roll_number' => 99,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-08-01',
            'effective_to' => null,
        ]);
    }

    public function test_administrator_and_office_staff_can_see_all_students(): void
    {
        $adminRes = $this->actingAs($this->admin)->get(route('students.index'));
        $adminRes->assertStatus(200);
        $adminRes->assertSee($this->student8A->admission_number);
        $adminRes->assertSee($this->student8B->admission_number);
        $adminRes->assertSee($this->student9A->admission_number);
        $adminRes->assertSee($this->student9B->admission_number);
        $adminRes->assertSee($this->student10A->admission_number);

        $officeRes = $this->actingAs($this->officeStaff)->get(route('students.index'));
        $officeRes->assertStatus(200);
        $officeRes->assertSee($this->student8A->admission_number);
        $officeRes->assertSee($this->student8B->admission_number);
        $officeRes->assertSee($this->student9A->admission_number);
        $officeRes->assertSee($this->student9B->admission_number);
        $officeRes->assertSee($this->student10A->admission_number);
    }

    public function test_class_teacher_sees_only_assigned_class_and_section(): void
    {
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index'));
        $response->assertStatus(200);

        // 8A student must be visible
        $response->assertSee($this->student8A->admission_number);

        // Other classes/sections must NOT be visible
        $response->assertDontSee($this->student8B->admission_number);
        $response->assertDontSee($this->student9A->admission_number);
        $response->assertDontSee($this->student9B->admission_number);
        $response->assertDontSee($this->student10A->admission_number);
    }

    public function test_subject_teacher_sees_only_assigned_class_and_section(): void
    {
        $response = $this->actingAs($this->subTeacher9B)->get(route('students.index'));
        $response->assertStatus(200);

        // 9B student must be visible
        $response->assertSee($this->student9B->admission_number);

        // Other classes/sections must NOT be visible
        $response->assertDontSee($this->student8A->admission_number);
        $response->assertDontSee($this->student8B->admission_number);
        $response->assertDontSee($this->student9A->admission_number);
        $response->assertDontSee($this->student10A->admission_number);
    }

    public function test_teacher_with_multiple_assignments_sees_union_of_scopes(): void
    {
        $response = $this->actingAs($this->multiTeacher)->get(route('students.index'));
        $response->assertStatus(200);

        // Assigned scopes: 8A and 9B
        $response->assertSee($this->student8A->admission_number);
        $response->assertSee($this->student9B->admission_number);

        // Unassigned scopes: 8B, 9A, 10A must NOT be visible
        $response->assertDontSee($this->student8B->admission_number);
        $response->assertDontSee($this->student9A->admission_number);
        $response->assertDontSee($this->student10A->admission_number);
    }

    public function test_historical_placement_does_not_leak_into_former_class_directory(): void
    {
        // studentTransferred8Ato8B was in 8A, but is now actively in 8B
        // 8A Class Teacher must NOT see them
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index'));
        $response->assertStatus(200);
        $response->assertDontSee($this->studentTransferred8Ato8B->admission_number);
    }

    public function test_teacher_without_active_assignments_sees_empty_directory(): void
    {
        $unassignedTeacher = User::forceCreate([
            'role_id' => $this->classTeacher8A->role_id,
            'username' => 'dir_unassigned_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Unassigned Teacher',
            'is_active' => true,
        ]);

        $response = $this->actingAs($unassignedTeacher)->get(route('students.index'));
        $response->assertStatus(200);
        $response->assertDontSee($this->student8A->admission_number);
        $response->assertDontSee($this->student9B->admission_number);
    }

    public function test_idor_direct_student_access_outside_teacher_scope_is_forbidden(): void
    {
        // 8A Class Teacher accessing 8A student -> 200
        $this->actingAs($this->classTeacher8A)
            ->get(route('students.show', $this->student8A))
            ->assertStatus(200);

        // 8A Class Teacher accessing 8B student -> 403
        $this->actingAs($this->classTeacher8A)
            ->get(route('students.show', $this->student8B))
            ->assertStatus(403);

        // 8A Class Teacher accessing 9B student -> 403
        $this->actingAs($this->classTeacher8A)
            ->get(route('students.show', $this->student9B))
            ->assertStatus(403);

        // 8A Class Teacher accessing student who transferred out of 8A to 8B -> 403
        $this->actingAs($this->classTeacher8A)
            ->get(route('students.show', $this->studentTransferred8Ato8B))
            ->assertStatus(403);

        // Subject Teacher 9B accessing 9B student -> 200
        $this->actingAs($this->subTeacher9B)
            ->get(route('students.show', $this->student9B))
            ->assertStatus(200);

        // Subject Teacher 9B accessing 8A student -> 403
        $this->actingAs($this->subTeacher9B)
            ->get(route('students.show', $this->student8A))
            ->assertStatus(403);
    }

    public function test_teacher_cannot_bypass_scope_via_url_query_parameters(): void
    {
        // 8A teacher passing class_id for Class 9 in query
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index', [
            'class_id' => $this->class9->id,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee($this->student9A->admission_number);
        $response->assertDontSee($this->student9B->admission_number);
    }

    public function test_searching_by_admission_number_cannot_bypass_teacher_scope(): void
    {
        // 8A teacher searches for 9B student's admission number
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index', [
            'search' => $this->student9B->admission_number,
        ]));

        $response->assertStatus(200);
        $response->assertSee('No students found matching the selected criteria.');
        $response->assertDontSee(route('students.show', $this->student9B));
    }

    public function test_searching_by_student_name_cannot_bypass_teacher_scope(): void
    {
        // 8A teacher searches for 9B student's name
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index', [
            'search' => 'Daisy 9B',
        ]));

        $response->assertStatus(200);
        $response->assertSee('No students found matching the selected criteria.');
        $response->assertDontSee(route('students.show', $this->student9B));
    }

    public function test_class_teacher_with_both_class_teacher_and_subject_teacher_assignments_sees_all_scopes(): void
    {
        // multiTeacher has:
        // 1. Class Teacher -> 8A
        // 2. Subject Teacher -> 9B (math)
        $response = $this->actingAs($this->multiTeacher)->get(route('students.index'));
        $response->assertStatus(200);
        $response->assertSee($this->student8A->admission_number);
        $response->assertSee($this->student9B->admission_number);
        $response->assertDontSee($this->student8B->admission_number);
        $response->assertDontSee($this->student10A->admission_number);
    }

    public function test_pagination_preserves_teacher_scope_and_filters(): void
    {
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index', ['page' => 1]));
        $response->assertStatus(200);
        $response->assertSee($this->student8A->admission_number);
        $response->assertDontSee($this->student8B->admission_number);
    }

    public function test_admin_and_office_staff_see_all_classes_and_sections_in_filter_dropdowns(): void
    {
        $adminRes = $this->actingAs($this->admin)->get(route('students.index'));
        $adminRes->assertStatus(200);
        $adminRes->assertSee('<option value="' . $this->class8->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->class9->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->class10->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->sec8A->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->sec8B->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->sec9A->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->sec9B->id . '"', false);
        $adminRes->assertSee('<option value="' . $this->sec10A->id . '"', false);

        $officeRes = $this->actingAs($this->officeStaff)->get(route('students.index'));
        $officeRes->assertStatus(200);
        $officeRes->assertSee('<option value="' . $this->class8->id . '"', false);
        $officeRes->assertSee('<option value="' . $this->class9->id . '"', false);
        $officeRes->assertSee('<option value="' . $this->class10->id . '"', false);
        $officeRes->assertSee('<option value="' . $this->sec8A->id . '"', false);
        $officeRes->assertSee('<option value="' . $this->sec10A->id . '"', false);
    }

    public function test_class_teacher_sees_only_assigned_class_and_section_options(): void
    {
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index'));
        $response->assertStatus(200);

        // Class 8 is assigned
        $response->assertSee('<option value="' . $this->class8->id . '"', false);
        // Section 8A is assigned
        $response->assertSee('<option value="' . $this->sec8A->id . '"', false);

        // Class 9 and 10 are NOT assigned
        $response->assertDontSee('<option value="' . $this->class9->id . '"', false);
        $response->assertDontSee('<option value="' . $this->class10->id . '"', false);

        // Section 8B, 9A, 9B, 10A are NOT assigned
        $response->assertDontSee('<option value="' . $this->sec8B->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec9A->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec9B->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec10A->id . '"', false);
    }

    public function test_subject_teacher_sees_only_assigned_class_and_section_options(): void
    {
        $response = $this->actingAs($this->subTeacher9B)->get(route('students.index'));
        $response->assertStatus(200);

        // Class 9 is assigned
        $response->assertSee('<option value="' . $this->class9->id . '"', false);
        // Section 9B is assigned
        $response->assertSee('<option value="' . $this->sec9B->id . '"', false);

        // Class 8 and 10 are NOT assigned
        $response->assertDontSee('<option value="' . $this->class8->id . '"', false);
        $response->assertDontSee('<option value="' . $this->class10->id . '"', false);

        // Section 8A, 8B, 9A, 10A are NOT assigned
        $response->assertDontSee('<option value="' . $this->sec8A->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec8B->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec9A->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec10A->id . '"', false);
    }

    public function test_multi_teacher_sees_union_of_authorized_classes_and_sections(): void
    {
        $response = $this->actingAs($this->multiTeacher)->get(route('students.index'));
        $response->assertStatus(200);

        // Class 8 and 9 are assigned
        $response->assertSee('<option value="' . $this->class8->id . '"', false);
        $response->assertSee('<option value="' . $this->class9->id . '"', false);
        // Section 8A and 9B are assigned
        $response->assertSee('<option value="' . $this->sec8A->id . '"', false);
        $response->assertSee('<option value="' . $this->sec9B->id . '"', false);

        // Class 10 is NOT assigned
        $response->assertDontSee('<option value="' . $this->class10->id . '"', false);

        // Section 8B, 9A, 10A are NOT assigned
        $response->assertDontSee('<option value="' . $this->sec8B->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec9A->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec10A->id . '"', false);
    }

    public function test_unassigned_teacher_sees_empty_state_options(): void
    {
        $unassignedTeacher = User::forceCreate([
            'role_id' => $this->classTeacher8A->role_id,
            'username' => 'dir_unassigned_opt_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Unassigned Teacher Opt',
            'is_active' => true,
        ]);

        $response = $this->actingAs($unassignedTeacher)->get(route('students.index'));
        $response->assertStatus(200);
        $response->assertSee('No authorized classes');
        $response->assertSee('No authorized sections');
        $response->assertDontSee('<option value="' . $this->class8->id . '"', false);
        $response->assertDontSee('<option value="' . $this->sec8A->id . '"', false);
    }

    public function test_teacher_dropdown_options_filtered_by_selected_academic_year(): void
    {
        // When yearOther (2025-2026) is selected, teacher has no assignments in that year
        $response = $this->actingAs($this->classTeacher8A)->get(route('students.index', [
            'academic_year_id' => $this->yearOther->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('No authorized classes');
        $response->assertSee('No authorized sections');

        // When yearCurrent is selected, teacher has 8A
        $responseCurrent = $this->actingAs($this->classTeacher8A)->get(route('students.index', [
            'academic_year_id' => $this->yearCurrent->id,
        ]));
        $responseCurrent->assertStatus(200);
        $responseCurrent->assertSee('<option value="' . $this->class8->id . '"', false);
        $responseCurrent->assertSee('<option value="' . $this->sec8A->id . '"', false);
    }

    public function test_cross_combination_filter_sanitization_for_teacher(): void
    {
        // multiTeacher is assigned to 8-A and 9-B
        // If they manually pass class_id=8 and section_id=sec9B (which is Section B from Class 9):
        // The controller should sanitize section_id because (8, 9B) is NOT an authorized combination!
        $response = $this->actingAs($this->multiTeacher)->get(route('students.index', [
            'class_id' => $this->class8->id,
            'section_id' => $this->sec9B->id,
        ]));
        $response->assertStatus(200);
        // Student in 8A is visible because class 8 is selected and incompatible section was sanitized to null
        $response->assertSee($this->student8A->admission_number);
        // Student in 9B is NOT visible because class_id is 8
        $response->assertDontSee($this->student9B->admission_number);
    }
}
