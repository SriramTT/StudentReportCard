<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for Subject Master page improvements:
 * - Office Staff authorized for create, edit, activate/deactivate, and delete (TC-01, TC-02)
 * - Subject Teacher and Class Teacher strictly denied (TC-03, TC-04)
 * - Direct crafted requests prevented (TC-05)
 * - Deletion safeguards for class_subjects and teacher_assignments (TC-06)
 * - Status filter: active, inactive, all, invalid fallback, and reset (TC-07 to TC-11)
 * - Sticky panel markup and responsive structure (TC-12 to TC-14)
 */
class SubjectMasterImprovementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $subjectTeacher;
    protected User $classTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $classTeacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_smi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin SMI',
            'email' => 'admin_smi_' . uniqid() . '@school.test',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_smi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office SMI',
            'email' => 'office_smi_' . uniqid() . '@school.test',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'subt_smi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Sub Teacher SMI',
            'email' => 'subt_smi_' . uniqid() . '@school.test',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $classTeacherRole->id,
            'username' => 'classt_smi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher SMI',
            'email' => 'classt_smi_' . uniqid() . '@school.test',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. Role Authorization & Actions (Admin & Office Staff)
    // =========================================================================

    public function test_admin_can_access_subject_master_and_perform_all_actions(): void
    {
        // 1. Access page
        $res = $this->actingAs($this->admin)->get(route('subjects.index'));
        $res->assertOk();
        $res->assertSee('Subject Master Catalog');
        $res->assertSee('Add New Subject');
        $res->assertSee('subject-page-sticky-card');

        // 2. Create subject
        $code = 'ADM_' . rand(1000, 9999);
        $createRes = $this->actingAs($this->admin)->post(route('subjects.store'), [
            'name' => 'Admin Created Subject',
            'code' => $code,
            'category' => 'main',
            'is_active' => '1',
        ]);
        $createRes->assertRedirect(route('subjects.index'));
        $this->assertDatabaseHas('subjects', ['code' => $code]);

        $subject = Subject::where('code', $code)->firstOrFail();

        // 3. Edit subject
        $editRes = $this->actingAs($this->admin)->put(route('subjects.update', $subject), [
            'name' => 'Admin Renamed Subject',
            'code' => $code,
            'category' => 'elective',
            'is_active' => '1',
        ]);
        $editRes->assertRedirect(route('subjects.index'));
        $this->assertEquals('Admin Renamed Subject', $subject->fresh()->name);

        // 4. Deactivate subject
        $deactivateRes = $this->actingAs($this->admin)->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'code' => $subject->code,
            'category' => $subject->category->value,
            'is_active' => '0',
        ]);
        $deactivateRes->assertRedirect(route('subjects.index'));
        $this->assertFalse($subject->fresh()->is_active);

        // 5. Remove unreferenced subject
        $deleteRes = $this->actingAs($this->admin)->delete(route('subjects.destroy', $subject));
        $deleteRes->assertRedirect(route('subjects.index'));
        $deleteRes->assertSessionHas('success');
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    public function test_office_staff_can_access_subject_master_and_perform_all_actions(): void
    {
        // 1. Access page
        $res = $this->actingAs($this->officeStaff)->get(route('subjects.index'));
        $res->assertOk();
        $res->assertSee('Subject Master Catalog');
        $res->assertSee('Add New Subject');
        $res->assertSee('subject-page-sticky-card');

        // 2. Create subject
        $code = 'OFF_' . rand(1000, 9999);
        $createRes = $this->actingAs($this->officeStaff)->post(route('subjects.store'), [
            'name' => 'Staff Created Subject',
            'code' => $code,
            'category' => 'main',
            'is_active' => '1',
        ]);
        $createRes->assertRedirect(route('subjects.index'));
        $this->assertDatabaseHas('subjects', ['code' => $code]);

        $subject = Subject::where('code', $code)->firstOrFail();

        // 3. Edit subject
        $editRes = $this->actingAs($this->officeStaff)->put(route('subjects.update', $subject), [
            'name' => 'Staff Renamed Subject',
            'code' => $code,
            'category' => 'elective',
            'is_active' => '1',
        ]);
        $editRes->assertRedirect(route('subjects.index'));
        $this->assertEquals('Staff Renamed Subject', $subject->fresh()->name);

        // 4. Deactivate subject
        $deactivateRes = $this->actingAs($this->officeStaff)->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'code' => $subject->code,
            'category' => $subject->category->value,
            'is_active' => '0',
        ]);
        $deactivateRes->assertRedirect(route('subjects.index'));
        $this->assertFalse($subject->fresh()->is_active);

        // 5. Activate subject
        $activateRes = $this->actingAs($this->officeStaff)->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'code' => $subject->code,
            'category' => $subject->category->value,
            'is_active' => '1',
        ]);
        $activateRes->assertRedirect(route('subjects.index'));
        $this->assertTrue($subject->fresh()->is_active);

        // 6. Remove unreferenced subject
        $deleteRes = $this->actingAs($this->officeStaff)->delete(route('subjects.destroy', $subject));
        $deleteRes->assertRedirect(route('subjects.index'));
        $deleteRes->assertSessionHas('success');
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'subjects',
            'entity_id' => $subject->id,
            'action' => 'DELETE_SUBJECT',
            'user_id' => $this->officeStaff->id,
        ]);
    }

    // =========================================================================
    // 2. Role Denial (Subject Teacher & Class Teacher)
    // =========================================================================

    public function test_subject_teacher_and_class_teacher_are_strictly_denied(): void
    {
        $subject = Subject::create([
            'name' => 'Protected Subject ' . uniqid(),
            'code' => 'PROT_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $teachers = [
            'Subject Teacher' => $this->subjectTeacher,
            'Class Teacher' => $this->classTeacher,
        ];

        foreach ($teachers as $roleName => $teacher) {
            // Index view denied
            $this->actingAs($teacher)->get(route('subjects.index'))
                ->assertStatus(403);

            // Create denied
            $this->actingAs($teacher)->post(route('subjects.store'), [
                'name' => 'Forbidden ' . $roleName,
                'code' => 'FORB_' . rand(1000, 9999),
                'category' => 'main',
            ])->assertStatus(403);

            // Update denied
            $this->actingAs($teacher)->put(route('subjects.update', $subject), [
                'name' => 'Hacked ' . $roleName,
                'code' => $subject->code,
                'category' => 'main',
            ])->assertStatus(403);

            // Delete denied
            $this->actingAs($teacher)->delete(route('subjects.destroy', $subject))
                ->assertStatus(403);
        }
    }

    // =========================================================================
    // 3. Deletion Safeguards (ClassSubject & TeacherAssignment references)
    // =========================================================================

    public function test_subject_referenced_by_class_subject_cannot_be_deleted(): void
    {
        $year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'name' => 'Class ' . uniqid(),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Math Guarded',
            'code' => 'CS_GD_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        ClassSubject::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => false, // Even if inactive!
        ]);

        // Attempt deletion by Office Staff
        $response = $this->actingAs($this->officeStaff)->delete(route('subjects.destroy', $subject));
        $response->assertRedirect(route('subjects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);

        // Attempt deletion by Admin
        $responseAdmin = $this->actingAs($this->admin)->delete(route('subjects.destroy', $subject));
        $responseAdmin->assertRedirect(route('subjects.index'));
        $responseAdmin->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_subject_referenced_by_teacher_assignment_cannot_be_deleted(): void
    {
        $year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'name' => 'Class ' . uniqid(),
            'is_active' => true,
        ]);

        $section = Section::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'Section A',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Science Guarded',
            'code' => 'TA_GD_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'assignment_type' => 'subject_teacher',
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->delete(route('subjects.destroy', $subject));
        $response->assertRedirect(route('subjects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    // =========================================================================
    // 4. Status Filter Tests
    // =========================================================================

    public function test_status_filter_filters_records_correctly(): void
    {
        $activeSub = Subject::create([
            'name' => 'Active Filter Sub ' . uniqid(),
            'code' => 'ACT_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $inactiveSub = Subject::create([
            'name' => 'Inactive Filter Sub ' . uniqid(),
            'code' => 'INACT_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => false,
        ]);

        // 1. Default (no status param) returns both
        $resDefault = $this->actingAs($this->officeStaff)->get(route('subjects.index'));
        $resDefault->assertOk();
        $resDefault->assertSee($activeSub->name);
        $resDefault->assertSee($inactiveSub->name);

        // 2. status=all returns both
        $resAll = $this->actingAs($this->officeStaff)->get(route('subjects.index', ['status' => 'all']));
        $resAll->assertOk();
        $resAll->assertSee($activeSub->name);
        $resAll->assertSee($inactiveSub->name);

        // 3. status=active returns only active
        $resActive = $this->actingAs($this->officeStaff)->get(route('subjects.index', ['status' => 'active']));
        $resActive->assertOk();
        $resActive->assertSee($activeSub->name);
        $resActive->assertDontSee($inactiveSub->name);

        // 4. status=inactive returns only inactive
        $resInactive = $this->actingAs($this->officeStaff)->get(route('subjects.index', ['status' => 'inactive']));
        $resInactive->assertOk();
        $resInactive->assertDontSee($activeSub->name);
        $resInactive->assertSee($inactiveSub->name);

        // 5. Invalid status falls back safely to all
        $resInvalid = $this->actingAs($this->officeStaff)->get(route('subjects.index', ['status' => 'malicious_input']));
        $resInvalid->assertOk();
        $resInvalid->assertSee($activeSub->name);
        $resInvalid->assertSee($inactiveSub->name);
    }

    public function test_filter_selection_is_preserved_and_reset_link_present(): void
    {
        $response = $this->actingAs($this->officeStaff)->get(route('subjects.index', ['status' => 'inactive']));
        $response->assertOk();

        // Check selected attribute
        $response->assertSee('<option value="inactive" selected>', false);

        // Check reset link points to unfiltered subjects.index
        $response->assertSee(route('subjects.index'));
        $response->assertSee('id="btn_reset_filters"', false);
    }
}
