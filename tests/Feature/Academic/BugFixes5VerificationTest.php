<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BugFixes5VerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $teacher;
    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $teacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'bf5_admin_' . uniqid(),
            'password_hash' => Hash::make('Secret123!'),
            'display_name' => 'BF5 Admin User',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'bf5_teacher_' . uniqid(),
            'password_hash' => Hash::make('Secret123!'),
            'display_name' => 'BF5 Class Teacher',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'BF5_AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);
    }

    /**
     * Issue #1: Report Configuration occupies full width (single layout container).
     */
    public function test_issue_1_report_configuration_uses_full_width_layout(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.configurations.index'));
        $response->assertStatus(200);
        $response->assertSee('report-config-layout--single', false);
    }

    /**
     * Issue #2: Assessments and Assessment Types pages alignment / normal card layout.
     */
    public function test_issue_2_assessments_and_types_cards_use_standard_layout(): void
    {
        // 1. Assessments index
        $assessmentsResponse = $this->actingAs($this->admin)->get(route('assessments.index'));
        $assessmentsResponse->assertStatus(200);
        $assessmentsResponse->assertDontSee('card assessment-page-layout');

        // 2. Assessment Types index
        $typesResponse = $this->actingAs($this->admin)->get(route('assessments.types.index'));
        $typesResponse->assertStatus(200);
        $typesResponse->assertDontSee('card assessment-page-layout');
    }

    /**
     * Issue #3: Class Subjects Actions column alignment.
     */
    public function test_issue_3_class_subjects_actions_column_alignment(): void
    {
        $response = $this->actingAs($this->admin)->get(route('class_subjects.index'));
        $response->assertStatus(200);
        $response->assertSee('text-align: right; padding-right: 1.25rem;', false);
    }

    /**
     * Issue #4: Academic Year Add form is a modal.
     */
    public function test_issue_4_academic_year_add_is_modal(): void
    {
        $response = $this->actingAs($this->admin)->get(route('academic_years.index'));
        $response->assertStatus(200);
        $response->assertSee('id="btn-add-academic-year"', false);
        $response->assertSee('id="create-year-modal"', false);
    }

    /**
     * Issue #5: Academic Year Configuration Window calculation & UI status.
     */
    public function test_issue_5_academic_year_configuration_window_behavior(): void
    {
        // 2026-06-01 to 2027-04-30
        // Window start: 2026-05-01, Window end: 2027-02-28
        $this->assertEquals('2026-05-01', $this->year->configurationWindowStart()?->toDateString());
        $this->assertEquals('2027-02-28', $this->year->configurationWindowEnd()?->toDateString());

        $insideDate = Carbon::parse('2026-10-01');
        $this->assertTrue($this->year->isConfigurationWindowOpen($insideDate));
        $this->assertFalse($this->year->isConfigurationWindowLocked($insideDate));

        $afterDate = Carbon::parse('2027-03-01');
        $this->assertFalse($this->year->isConfigurationWindowOpen($afterDate));
        $this->assertTrue($this->year->isConfigurationWindowLocked($afterDate));

        // Index page does NOT display misleading "Locked (opens ...)"
        $response = $this->actingAs($this->admin)->get(route('academic_years.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Locked (opens');
    }

    /**
     * Issue #6: Class Teacher can only be Class Teacher for one class in an academic year.
     */
    public function test_issue_6_class_teacher_restricted_to_one_class_per_year(): void
    {
        $classA = SchoolClass::create(['name' => 'Class A_' . uniqid()]);
        $sectionA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $classA->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $classB = SchoolClass::create(['name' => 'Class B_' . uniqid()]);
        $sectionB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $classB->id,
            'name' => 'B',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'code' => 'SUB_' . strtoupper(substr(uniqid(), 0, 5)),
            'name' => 'General Science',
            'category' => \App\Enums\SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        // 1. Assign as Class Teacher to Class A Section A -> OK
        $res1 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->teacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $classA->id,
            'section_id' => $sectionA->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $res1->assertRedirect(route('teacher_assignments.index'));

        // 2. Second Class Teacher assignment in same academic year -> Rejected
        $res2 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->teacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'assignment_type' => 'class_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $res2->assertSessionHasErrors('assignment_type');

        // 3. Subject Teacher assignment for same teacher in Class B Section B -> OK
        $res3 = $this->actingAs($this->admin)->post(route('teacher_assignments.store'), [
            'user_id' => $this->teacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'subject_id' => $subject->id,
            'assignment_type' => 'subject_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => 1,
        ]);
        $res3->assertRedirect(route('teacher_assignments.index'));
    }

    /**
     * Issue #7 (Bug Fixes 6): Header user profile dropdown rendering and accessibility.
     */
    public function test_issue_7_header_user_profile_dropdown_renders(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);

        // Header user dropdown trigger and menu container
        $response->assertSee('id="user-menu-container"', false);
        $response->assertSee('id="user-menu-trigger"', false);
        $response->assertSee('id="user-menu-dropdown"', false);

        // Dynamic name, role, and logout button for Administrator
        $response->assertSee('BF5 Admin User');
        $response->assertSee('Administrator');
        $response->assertSee('id="topbar-logout-btn"', false);
        $response->assertSeeText('Logout');

        // Form method POST and CSRF
        $response->assertSee(route('logout'), false);

        // Verify Office Staff
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $officeUser = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'bf5_office_' . uniqid(),
            'password_hash' => Hash::make('Secret123!'),
            'display_name' => 'BF5 Office Staff',
            'is_active' => true,
        ]);
        $officeResponse = $this->actingAs($officeUser)->get(route('dashboard'));
        $officeResponse->assertStatus(200);
        $officeResponse->assertSee('BF5 Office Staff');
        $officeResponse->assertSee('Office Staff');

        // Verify Subject Teacher
        $subjectTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $subjectUser = User::forceCreate([
            'role_id' => $subjectTeacherRole->id,
            'username' => 'bf5_subteacher_' . uniqid(),
            'password_hash' => Hash::make('Secret123!'),
            'display_name' => 'BF5 Subject Teacher',
            'is_active' => true,
        ]);
        $subjectResponse = $this->actingAs($subjectUser)->get(route('dashboard'));
        $subjectResponse->assertStatus(200);
        $subjectResponse->assertSee('BF5 Subject Teacher');
        $subjectResponse->assertSee('Subject Teacher');

        // Verify Class Teacher
        $classTeacherResponse = $this->actingAs($this->teacher)->get(route('dashboard'));
        $classTeacherResponse->assertStatus(200);
        $classTeacherResponse->assertSee('BF5 Class Teacher');
        $classTeacherResponse->assertSee('Class Teacher');
    }

    /**
     * Issue #8 (Bug Fixes 6): Login failure messaging, field-level validation, and enumeration prevention.
     */
    public function test_issue_8_login_error_messaging_and_security(): void
    {
        // 1. Empty username -> custom field error "Username is required."
        $emptyUserRes = $this->post(route('login.submit'), [
            'username' => '',
            'password' => 'SomePass',
        ]);
        $emptyUserRes->assertSessionHasErrors(['username' => 'Username is required.']);

        // 2. Empty password -> custom field error "Password is required."
        $emptyPassRes = $this->post(route('login.submit'), [
            'username' => $this->admin->username,
            'password' => '',
        ]);
        $emptyPassRes->assertSessionHasErrors(['password' => 'Password is required.']);

        // 3. Both empty -> both custom field errors
        $bothEmptyRes = $this->post(route('login.submit'), [
            'username' => '',
            'password' => '',
        ]);
        $bothEmptyRes->assertSessionHasErrors([
            'username' => 'Username is required.',
            'password' => 'Password is required.',
        ]);

        // 4. Wrong password for existing user -> generic credentials error
        $wrongPassRes = $this->post(route('login.submit'), [
            'username' => $this->admin->username,
            'password' => 'WrongPassword!',
        ]);
        $wrongPassRes->assertSessionHasErrors(['credentials', 'username', 'password']);
        $this->assertEquals(
            'Invalid username or password. Please verify your credentials and try again.',
            session('errors')->first('credentials')
        );

        // 5. Non-existent user -> SAME generic credentials error
        $nonExistentRes = $this->post(route('login.submit'), [
            'username' => 'non_existent_staff_xyz',
            'password' => 'WrongPassword!',
        ]);
        $nonExistentRes->assertSessionHasErrors(['credentials', 'username', 'password']);
        $this->assertEquals(
            'Invalid username or password. Please verify your credentials and try again.',
            session('errors')->first('credentials')
        );
    }
}
