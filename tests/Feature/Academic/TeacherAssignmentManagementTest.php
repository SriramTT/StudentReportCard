<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\SubjectCategory;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TeacherAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherAssignmentManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $subjectTeacher;
    protected User $classTeacher;

    protected AcademicYear $year;
    protected AcademicYear $otherYear;

    protected SchoolClass $class8;
    protected SchoolClass $class9;
    protected SchoolClass $class10;

    protected Section $section8A;
    protected Section $section8B;
    protected Section $section9A;
    protected Section $section9B;
    protected Section $section10A;

    protected Subject $math;
    protected Subject $science;
    protected Subject $english;

    protected ClassSubject $cs8AMath;
    protected ClassSubject $cs8AScience;
    protected ClassSubject $cs8AEnglish;
    protected ClassSubject $cs9BMath;
    protected ClassSubject $cs9BScience;
    protected ClassSubject $cs9BEnglish;

    protected TeacherAuthorizationService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = app(TeacherAuthorizationService::class);

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $classTeacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'ta_admin_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'TA Admin',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'ta_office_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'TA Office',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'ta_sub_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'TA Subject Teacher',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $classTeacherRole->id,
            'username' => 'ta_class_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'TA Class Teacher',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . substr(uniqid(), 0, 10),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->otherYear = AcademicYear::create([
            'name' => 'AY2_' . substr(uniqid(), 0, 10),
            'start_date' => '2027-06-01',
            'end_date' => '2028-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $this->class8 = SchoolClass::create(['name' => 'Class 8_' . uniqid()]);
        $this->class9 = SchoolClass::create(['name' => 'Class 9_' . uniqid()]);
        $this->class10 = SchoolClass::create(['name' => 'Class 10_' . uniqid()]);

        $this->section8A = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'name' => 'A',
        ]);
        $this->section8B = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'name' => 'B',
        ]);
        $this->section9A = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'name' => 'A',
        ]);
        $this->section9B = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'name' => 'B',
        ]);
        $this->section10A = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class10->id,
            'name' => 'A',
        ]);

        $this->math = Subject::create([
            'name' => 'Mathematics',
            'code' => 'M_' . uniqid(),
            'category' => SubjectCategory::MAIN,
        ]);
        $this->science = Subject::create([
            'name' => 'Science',
            'code' => 'S_' . uniqid(),
            'category' => SubjectCategory::MAIN,
        ]);
        $this->english = Subject::create([
            'name' => 'English',
            'code' => 'E_' . uniqid(),
            'category' => SubjectCategory::MAIN,
        ]);

        // Map subjects to 8A
        $this->cs8AMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);
        $this->cs8AScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);
        $this->cs8AEnglish = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->english->id,
            'subject_name_snapshot' => $this->english->name,
            'is_active' => true,
        ]);

        // Map subjects to 9B
        $this->cs9BMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);
        $this->cs9BScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);
        $this->cs9BEnglish = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->english->id,
            'subject_name_snapshot' => $this->english->name,
            'is_active' => true,
        ]);
    }

    public function test_administrator_and_office_staff_can_view_assignments(): void
    {
        $this->actingAs($this->admin)->get(route('teacher_assignments.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('teacher_assignments.index'))->assertStatus(200);
    }

    public function test_teachers_receive_403_for_assignment_management(): void
    {
        $this->actingAs($this->subjectTeacher)->get(route('teacher_assignments.index'))->assertStatus(403);
        $this->actingAs($this->classTeacher)->get(route('teacher_assignments.index'))->assertStatus(403);

        $this->actingAs($this->subjectTeacher)->post(route('teacher_assignments.store'), [])->assertStatus(403);
        $this->actingAs($this->classTeacher)->post(route('teacher_assignments.store'), [])->assertStatus(403);
    }

    public function test_administrator_and_office_staff_can_create_subject_teacher_assignment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => 'subject_teacher',
            'is_active' => true,
        ]);
    }

    public function test_administrator_and_office_staff_can_create_class_teacher_assignment(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'subject_id' => null,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->classTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => null,
            'assignment_type' => 'class_teacher',
            'is_active' => true,
        ]);
    }

    public function test_non_teacher_account_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->officeStaff->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_invalid_class_and_section_relationship_is_rejected(): void
    {
        // section9A belongs to class9, attempting to pair with class8
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section9A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('section_id');
    }

    public function test_invalid_subject_not_mapped_to_class_is_rejected(): void
    {
        $unmappedSubject = Subject::create([
            'name' => 'Sanskrit',
            'code' => 'SKT_' . uniqid(),
            'category' => SubjectCategory::ELECTIVE,
        ]);

        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $unmappedSubject->id,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_class_teacher_assignment_with_non_null_subject_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_subject_teacher_assignment_without_subject_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => null,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_effective_dates_govern_contextual_access(): void
    {
        $today = now()->toDateString();
        $futureDate = now()->addMonth()->toDateString();
        $pastDate = now()->subMonths(3)->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // 1. Future assignment does not grant access today
        $futureAssign = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => $futureDate,
            'effective_to' => null,
            'is_active' => true,
        ]);
        $this->assertFalse($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));

        // 2. Currently effective assignment grants access
        $futureAssign->update(['effective_from' => $pastDate]);
        $this->assertTrue($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));

        // 3. Expired assignment does not grant access
        $futureAssign->update(['effective_to' => $yesterday]);
        $this->assertFalse($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));

        // 4. Inactive assignment does not grant access
        $futureAssign->update(['effective_to' => null, 'is_active' => false]);
        $this->assertFalse($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));
    }

    public function test_assignment_deactivation_and_activation_take_effect_immediately_without_relogin(): void
    {
        $assignment = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        // Active -> authorized
        $this->assertTrue($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));

        // Admin deactivates assignment via endpoint
        $this->actingAs($this->admin)->post(route('teacher_assignments.deactivate', $assignment));
        $assignment->refresh();
        $this->assertFalse($assignment->is_active);

        // Immediately evaluated as unauthorized from live database
        $this->assertFalse($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));

        // Admin reactivates assignment via endpoint
        $this->actingAs($this->admin)->post(route('teacher_assignments.activate', $assignment));
        $assignment->refresh();
        $this->assertTrue($assignment->is_active);

        // Immediately re-authorized
        $this->assertTrue($this->authService->isAuthorized($this->subjectTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id));
    }

    public function test_conflicting_overlapping_active_assignments_are_rejected(): void
    {
        TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-12-31',
            'is_active' => true,
        ]);

        // Overlapping assignment attempt
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-09-01',
            'effective_to' => '2027-03-31',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('effective_from');
    }

    public function test_historical_sequential_assignments_are_accepted(): void
    {
        TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-09-30',
            'is_active' => false,
        ]);

        // Sequential assignment for active academic year succeeds when prior assignment is inactive
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertEquals(2, TeacherAssignment::where('user_id', $this->subjectTeacher->id)->count());
    }

    /**
     * MANDATORY MIXED-ASSIGNMENT TEST (Sections 10, 26, 27, 44)
     *
     * A single teacher account simultaneously has:
     * Assignment A: Class Teacher for 8A (subject_id = NULL)
     * Assignment B: Subject Teacher for 9B Mathematics (subject_id = math)
     *
     * Verify:
     * 8A Mathematics -> allowed
     * 8A Science -> allowed
     * 8A English -> allowed
     * 9B Mathematics -> allowed
     * 9B Science -> denied
     * 9B English -> denied
     * 10A Mathematics -> denied
     * 9A Mathematics -> denied
     * 8B Mathematics -> denied
     */
    public function test_mandatory_mixed_class_and_subject_teacher_assignment(): void
    {
        $mixedTeacher = User::forceCreate([
            'role_id' => $this->classTeacher->role_id,
            'username' => 'teacher_mixed_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Mixed Teacher',
            'is_active' => true,
        ]);

        // Assignment A: Class Teacher for 8A (subject_id = null)
        TeacherAssignment::create([
            'user_id' => $mixedTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // Assignment B: Subject Teacher for 9B Mathematics (subject_id = math->id)
        TeacherAssignment::create([
            'user_id' => $mixedTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // 8A Class Teacher scope: covers ALL applicable subjects in 8A
        $this->assertTrue(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->math->id),
            '8A Mathematics must be allowed via Class Teacher scope'
        );
        $this->assertTrue(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->science->id),
            '8A Science must be allowed via Class Teacher scope'
        );
        $this->assertTrue(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class8->id, $this->section8A->id, $this->english->id),
            '8A English must be allowed via Class Teacher scope'
        );

        // 9B Subject Teacher scope: covers ONLY Mathematics
        $this->assertTrue(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class9->id, $this->section9B->id, $this->math->id),
            '9B Mathematics must be allowed via Subject Teacher scope'
        );
        $this->assertFalse(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class9->id, $this->section9B->id, $this->science->id),
            '9B Science must be denied'
        );
        $this->assertFalse(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class9->id, $this->section9B->id, $this->english->id),
            '9B English must be denied'
        );

        // Unassigned classrooms/sections must be denied
        $this->assertFalse(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class10->id, $this->section10A->id, $this->math->id),
            '10A Mathematics must be denied'
        );
        $this->assertFalse(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class9->id, $this->section9A->id, $this->math->id),
            '9A Mathematics must be denied'
        );
        $this->assertFalse(
            $this->authService->isAuthorized($mixedTeacher, $this->year->id, $this->class8->id, $this->section8B->id, $this->math->id),
            '8B Mathematics must be denied'
        );

        // Different Academic Year must be denied
        $this->assertFalse(
            $this->authService->isAuthorized($mixedTeacher, $this->otherYear->id, $this->class8->id, $this->section8A->id, $this->math->id),
            'Other academic year must be denied'
        );
    }

    public function test_audit_events_are_created_for_teacher_assignments(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);

        $assignment = TeacherAssignment::where('user_id', $this->subjectTeacher->id)
            ->where('class_id', $this->class8->id)
            ->firstOrFail();

        $auditLog = AuditLog::where('entity_type', 'teacher_assignments')
            ->where('entity_id', $assignment->id)
            ->where('action', 'TEACHER_ASSIGNMENT_CREATED')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->admin->id, $auditLog->user_id);
    }

    public function test_teacher_assignments_cannot_be_hard_deleted(): void
    {
        $assignment = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $assignment));
        $this->assertTrue(Gate::forUser($this->officeStaff)->allows('delete', $assignment));
        $this->assertFalse(Gate::forUser($this->subjectTeacher)->allows('delete', $assignment));
    }

    public function test_new_assignment_automatically_uses_active_academic_year_and_date_boundaries_without_client_inputs(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'effective_from' => $this->year->start_date->toDateString(),
            'effective_to' => $this->year->end_date->toDateString(),
        ]);
    }

    public function test_client_submitted_dates_cannot_override_server_derived_boundaries(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2025-01-01',
            'effective_to' => '2029-12-31',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->subjectTeacher->id,
            'effective_from' => $this->year->start_date->toDateString(),
            'effective_to' => $this->year->end_date->toDateString(),
        ]);
    }

    public function test_tampered_academic_year_id_is_rejected_on_create(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_tampered_academic_year_id_is_rejected_on_update(): void
    {
        $assignment = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => $this->year->start_date->toDateString(),
            'effective_to' => $this->year->end_date->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('teacher_assignments.update', $assignment), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_update_preserves_stored_academic_year_and_dates(): void
    {
        $assignment = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2027-04-30',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('teacher_assignments.update', $assignment), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->science->id,
            'is_active' => 0,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $assignment->refresh();
        $this->assertEquals($this->year->id, $assignment->academic_year_id);
        $this->assertEquals('2026-06-01', $assignment->effective_from->toDateString());
        $this->assertEquals('2027-04-30', $assignment->effective_to->toDateString());
        $this->assertFalse($assignment->is_active);
        $this->assertEquals($this->science->id, $assignment->subject_id);
    }

    public function test_reject_section_from_different_academic_year(): void
    {
        $otherYearSection = Section::create([
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->class8->id,
            'name' => 'Section Other Year',
        ]);

        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $otherYearSection->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('section_id');
    }

    public function test_creation_rejected_when_no_active_academic_year_configured(): void
    {
        AcademicYear::query()->update(['is_current' => false]);

        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_creation_rejected_when_multiple_active_academic_years_configured(): void
    {
        $this->otherYear->update([
            'is_current' => true,
            'status' => AcademicYearStatus::OPEN,
        ]);

        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_creation_rejected_when_active_academic_year_has_missing_date_boundaries(): void
    {
        $this->year->update([
            'start_date' => null,
        ]);

        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_index_view_renders_disabled_section_and_active_year_display(): void
    {
        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index'));

        $response->assertStatus(200);
        $response->assertViewHas('currentAcademicYear');
        $response->assertSee('id="modal_academic_year_display"', false);
        $response->assertSee('id="modal_section_id"', false);
        $response->assertSee('Select Class first', false);
        $response->assertDontSee('id="modal_effective_from"', false);
        $response->assertDontSee('id="modal_effective_to"', false);
    }

    public function test_effective_dates_matching_academic_year_boundaries_succeed(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
            'effective_to' => '2027-04-30',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->subjectTeacher->id,
            'effective_from' => '2026-06-01',
            'effective_to' => '2027-04-30',
        ]);
    }

    public function test_subject_teacher_account_cannot_receive_class_teacher_assignment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'subject_id' => null,
            'effective_from' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors(['user_id', 'assignment_type']);
    }

    public function test_class_teacher_account_can_receive_subject_teacher_assignment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->classTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => 'subject_teacher',
        ]);
    }

    public function test_class_teacher_can_have_both_class_teacher_and_subject_teacher_assignments_simultaneously(): void
    {
        // 1. Assign Class Teacher for Class 8-A
        $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'subject_id' => null,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ])->assertRedirect(route('teacher_assignments.index'));

        // 2. Assign Subject Teacher (Mathematics) for Class 8-A to the same user (coexists)
        $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ])->assertRedirect(route('teacher_assignments.index'));

        // 3. Assign Subject Teacher (Science) for Class 9-B to the same user
        $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->science->id,
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ])->assertRedirect(route('teacher_assignments.index'));

        $this->assertEquals(3, TeacherAssignment::where('user_id', $this->classTeacher->id)->count());
    }

    public function test_assignment_type_filter_distinguishes_class_and_subject_assignments(): void
    {
        // 1. Create one Class Teacher assignment
        $classAssign = TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // 2. Create one Subject Teacher assignment
        $subjectAssign = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Filter by class_teacher: only class teacher assignment appears
        $resClass = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'assignment_type' => 'class_teacher',
        ]));
        $resClass->assertStatus(200);
        $resClass->assertSee('All Applicable Subjects (Class Teacher)');
        $resClass->assertSee($this->classTeacher->display_name);

        // Filter by subject_teacher: only subject teacher assignment appears
        $resSub = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'assignment_type' => 'subject_teacher',
        ]));
        $resSub->assertStatus(200);
        $resSub->assertSee($this->math->name);
        $resSub->assertSee($this->subjectTeacher->display_name);
    }

    public function test_filter_section_dropdown_is_disabled_with_all_sections_when_no_class_selected(): void
    {
        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index'));

        $response->assertStatus(200);
        // Section filter dropdown is rendered disabled
        $response->assertSee('id="filter_section_id" name="section_id" class="form-control" disabled', false);
        // Only All Sections is present in the select options
        $response->assertSee('<option value="">All Sections</option>', false);
        // Combined labels like Class X - A are never present in filter_section_id
        $response->assertDontSee($this->class8->name . ' - A', false);
        $response->assertDontSee('Class-Wide Only', false);
    }

    public function test_filter_section_dropdown_renders_only_simple_names_for_selected_class(): void
    {
        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'class_id' => $this->class8->id,
        ]));

        $response->assertStatus(200);
        // Section filter dropdown is enabled (not disabled)
        $response->assertSee('id="filter_section_id" name="section_id" class="form-control" >', false);
        // Contains All Sections
        $response->assertSee('<option value="">All Sections</option>', false);
        // Renders simple names A and B for Class 8
        $response->assertSee('<option value="' . $this->section8A->id . '" >' . "\n                                    " . $this->section8A->name . "\n", false);
        $response->assertSee('<option value="' . $this->section8B->id . '" >' . "\n                                    " . $this->section8B->name . "\n", false);
        // Does NOT render combined labels
        $response->assertDontSee($this->class8->name . ' - ' . $this->section8A->name, false);
        $response->assertDontSee('Class-Wide Only', false);
        // Does NOT render Class 9 sections in the server-rendered dropdown
        $response->assertDontSee('<option value="' . $this->section9A->id . '"', false);
        $response->assertDontSee('<option value="' . $this->section9B->id . '"', false);
    }

    public function test_filter_by_class_and_valid_section_returns_only_matching_assignments(): void
    {
        $assign8A = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $assign8B = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8B->id,
            'subject_id' => $this->science->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
        ]));

        $response->assertStatus(200);
        $assignments = $response->viewData('assignments');
        $this->assertTrue($assignments->contains('id', $assign8A->id));
        $this->assertFalse($assignments->contains('id', $assign8B->id));
        // Preserves selected section in the dropdown
        $response->assertSee('value="' . $this->section8A->id . '" selected', false);
    }

    public function test_section_id_supplied_without_class_id_is_ignored_by_backend(): void
    {
        $assign8A = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $assign9B = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->science->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Request with section_id but without class_id
        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'section_id' => $this->section8A->id,
        ]));

        $response->assertStatus(200);
        // Both assignments appear because section_id was ignored
        $assignments = $response->viewData('assignments');
        $this->assertTrue($assignments->contains('id', $assign8A->id));
        $this->assertTrue($assignments->contains('id', $assign9B->id));
        // Section dropdown remains disabled showing All Sections
        $response->assertSee('id="filter_section_id" name="section_id" class="form-control" disabled', false);
    }

    public function test_mismatched_section_id_for_different_class_is_safely_ignored(): void
    {
        $assign8A = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Request class 8 with section 9B (mismatched)
        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'class_id' => $this->class8->id,
            'section_id' => $this->section9B->id,
        ]));

        $response->assertStatus(200);
        // Assignment 8A still appears because mismatched section_id was ignored and fell back to Class 8 all sections
        $assignments = $response->viewData('assignments');
        $this->assertTrue($assignments->contains('id', $assign8A->id));
        // Section 9B is not marked selected
        $response->assertDontSee('value="' . $this->section9B->id . '" selected', false);
    }

    public function test_filter_class_with_no_sections_shows_no_sections_available_and_is_disabled(): void
    {
        $emptyClass = SchoolClass::create(['name' => 'Class Empty_' . uniqid()]);

        $response = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'class_id' => $emptyClass->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('No sections available');
        $response->assertSee('id="filter_section_id" name="section_id" class="form-control" disabled', false);
    }

    public function test_all_filters_work_together_independently_and_combined(): void
    {
        // 1. Class teacher for 8A, active
        $assign1 = TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // 2. Subject teacher for 8A, inactive
        $assign2 = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => false,
        ]);

        // Filter combination: user_id, assignment_type, class_id, section_id, status
        $resActive = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'user_id' => $this->classTeacher->id,
            'assignment_type' => 'class_teacher',
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'status' => 'active',
        ]));
        $resActive->assertStatus(200);
        $activeAssignments = $resActive->viewData('assignments');
        $this->assertTrue($activeAssignments->contains('id', $assign1->id));
        $this->assertFalse($activeAssignments->contains('id', $assign2->id));

        $resInactive = $this->actingAs($this->admin)->get(route('teacher_assignments.index', [
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'status' => 'inactive',
        ]));
        $resInactive->assertStatus(200);
        $inactiveAssignments = $resInactive->viewData('assignments');
        $this->assertTrue($inactiveAssignments->contains('id', $assign2->id));
        $this->assertFalse($inactiveAssignments->contains('id', $assign1->id));
    }

    // ============================================================
    // CHANGE #4: DUAL CLASS TEACHER + SUBJECT TEACHER ATOMIC WORKFLOW
    // ============================================================

    public function test_dual_assignment_atomically_creates_both_class_and_subject_teacher_records(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
            'also_assign_subject' => 1,
            'also_subject_id' => $this->math->id,
        ]);

        $response->assertRedirect(route('teacher_assignments.index'));
        $response->assertSessionHas('success');

        // Verify ROW 1: Class Teacher with subject_id NULL
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'subject_id' => null,
            'is_active' => true,
        ]);

        // Verify ROW 2: Subject Teacher with selected subject
        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'subject_teacher',
            'subject_id' => $this->math->id,
            'is_active' => true,
        ]);
    }

    public function test_dual_assignment_rejects_subject_not_mapped_to_class(): void
    {
        // Unmapped subject
        $unmappedSubject = \App\Models\Subject::create([
            'name' => 'Astronomy Unmapped',
            'code' => 'ASTRO_UNMAPPED',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
            'also_assign_subject' => 1,
            'also_subject_id' => $unmappedSubject->id,
        ]);

        $response->assertSessionHasErrors('also_subject_id');

        // Neither row must be created (atomic rollback / fail)
        $this->assertDatabaseMissing('teacher_assignments', [
            'user_id' => $this->classTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
        ]);
    }

    public function test_dual_assignment_rolls_back_entirely_if_second_assignment_conflicts(): void
    {
        // Pre-create active subject teacher assignment for this teacher and subject in 8A
        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Attempt dual assignment for Class Teacher + same Subject Teacher
        $response = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
            'also_assign_subject' => 1,
            'also_subject_id' => $this->math->id,
        ]);

        $response->assertSessionHasErrors('also_subject_id');

        // Class teacher assignment must NOT exist because the transaction rolled back
        $this->assertDatabaseMissing('teacher_assignments', [
            'user_id' => $this->classTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
        ]);
    }

    public function test_teacher_can_only_be_assigned_as_class_teacher_to_one_class_per_year(): void
    {
        // 1. Assign as Class Teacher to Class 8 Section A -> Success
        $response1 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $response1->assertRedirect(route('teacher_assignments.index'));
        $response1->assertSessionHas('success');

        // 2. Attempt to assign same teacher as Class Teacher to Class 9 Section B in same year -> Rejection
        $response2 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $response2->assertSessionHasErrors('assignment_type');

        // 3. Same teacher CAN still be assigned as Subject Teacher to Class 9 Section B -> Success
        $response3 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'subject_id' => $this->math->id,
            'assignment_type' => 'subject_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $response3->assertRedirect(route('teacher_assignments.index'));
        $response3->assertSessionHas('success');

        // 4. Editing the existing Class Teacher assignment without changing class/section -> Success
        $existing = TeacherAssignment::where('user_id', $this->classTeacher->id)
            ->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
            ->firstOrFail();

        $response4 = $this->actingAs($this->admin)->put(route('teacher_assignments.update', $existing), [
            'user_id' => $this->classTeacher->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $response4->assertRedirect(route('teacher_assignments.index'));
        $response4->assertSessionHas('success');

        // 5. Deactivating the Class Teacher assignment allows assigning as Class Teacher to another class
        $existing->update(['is_active' => false]);

        $response5 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9B->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $response5->assertRedirect(route('teacher_assignments.index'));
        $response5->assertSessionHas('success');
    }
}
