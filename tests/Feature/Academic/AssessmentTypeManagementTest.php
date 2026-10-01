<?php

namespace Tests\Feature\Academic;

use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentTypeManagementTest extends TestCase
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
        $subjectRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $classRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_at_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin AT',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_at_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office AT',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $subjectRole->id,
            'username' => 'sub_teacher_at_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Sub Teacher AT',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $classRole->id,
            'username' => 'cls_teacher_at_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Cls Teacher AT',
            'is_active' => true,
        ]);
    }

    // --- Administrator ---

    public function test_administrator_can_view_assessment_types(): void
    {
        $type = AssessmentType::create(['name' => 'Unit Test ' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('assessments.types.index'));

        $response->assertOk();
        $response->assertSeeText($type->name);
        $response->assertSee('btn-add-assessment-type');
    }

    public function test_administrator_can_create_assessment_type(): void
    {
        $typeName = 'Admin Exam ' . rand(100, 999);

        $response = $this->actingAs($this->admin)->post(route('assessments.types.store'), [
            'name' => $typeName,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('assessments.types.index'));
        $this->assertDatabaseHas('assessment_types', [
            'name' => $typeName,
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_update_assessment_type(): void
    {
        $type = AssessmentType::create(['name' => 'Old Admin Name ' . uniqid(), 'is_active' => true]);
        $newName = 'Updated Admin Name ' . rand(100, 999);

        $response = $this->actingAs($this->admin)->put(route('assessments.types.update', $type), [
            'name' => $newName,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('assessments.types.index'));
        $this->assertDatabaseHas('assessment_types', [
            'id' => $type->id,
            'name' => $newName,
        ]);
    }

    public function test_administrator_can_activate_and_deactivate_assessment_type(): void
    {
        $type = AssessmentType::create(['name' => 'Toggle Admin ' . uniqid(), 'is_active' => true]);

        // Deactivate
        $resDeactivate = $this->actingAs($this->admin)->put(route('assessments.types.update', $type), [
            'name' => $type->name,
            'is_active' => '0',
        ]);
        $resDeactivate->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);

        // Reactivate
        $resReactivate = $this->actingAs($this->admin)->put(route('assessments.types.update', $type), [
            'name' => $type->name,
            'is_active' => '1',
        ]);
        $resReactivate->assertRedirect();
        $this->assertTrue($type->fresh()->is_active);
    }

    // --- Office Staff ---

    public function test_office_staff_can_view_assessment_types(): void
    {
        $type = AssessmentType::create(['name' => 'Office View ' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->get(route('assessments.types.index'));

        $response->assertOk();
        $response->assertSeeText($type->name);
        $response->assertSee('btn-add-assessment-type');
        $response->assertSeeText('+ Add Assessment Type');
    }

    public function test_office_staff_can_create_assessment_type(): void
    {
        $typeName = 'Staff Created ' . rand(100, 999);

        $response = $this->actingAs($this->officeStaff)->post(route('assessments.types.store'), [
            'name' => $typeName,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('assessments.types.index'));
        $this->assertDatabaseHas('assessment_types', [
            'name' => $typeName,
            'is_active' => true,
        ]);
    }

    public function test_office_staff_can_update_assessment_type(): void
    {
        $type = AssessmentType::create(['name' => 'Staff Old ' . uniqid(), 'is_active' => true]);
        $newName = 'Staff Updated ' . rand(100, 999);

        $response = $this->actingAs($this->officeStaff)->put(route('assessments.types.update', $type), [
            'name' => $newName,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('assessments.types.index'));
        $this->assertDatabaseHas('assessment_types', [
            'id' => $type->id,
            'name' => $newName,
        ]);
    }

    public function test_office_staff_can_activate_and_deactivate_assessment_type(): void
    {
        $type = AssessmentType::create(['name' => 'Staff Toggle ' . uniqid(), 'is_active' => true]);

        // Deactivate
        $resDeactivate = $this->actingAs($this->officeStaff)->put(route('assessments.types.update', $type), [
            'name' => $type->name,
            'is_active' => '0',
        ]);
        $resDeactivate->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);

        // Reactivate
        $resReactivate = $this->actingAs($this->officeStaff)->put(route('assessments.types.update', $type), [
            'name' => $type->name,
            'is_active' => '1',
        ]);
        $resReactivate->assertRedirect();
        $this->assertTrue($type->fresh()->is_active);
    }

    // --- Teachers (Subject Teacher & Class Teacher) Receive 403 ---

    public function test_subject_teacher_cannot_access_assessment_types(): void
    {
        $type = AssessmentType::create(['name' => 'Sub Test ' . uniqid(), 'is_active' => true]);

        // View Any
        $this->actingAs($this->subjectTeacher)->get(route('assessments.types.index'))
            ->assertForbidden();

        // Create
        $this->actingAs($this->subjectTeacher)->post(route('assessments.types.store'), [
            'name' => 'Sub Forbidden ' . rand(100, 999),
        ])->assertForbidden();

        // Update
        $this->actingAs($this->subjectTeacher)->put(route('assessments.types.update', $type), [
            'name' => 'Sub Tampered',
        ])->assertForbidden();
    }

    public function test_class_teacher_cannot_access_assessment_types(): void
    {
        $type = AssessmentType::create(['name' => 'Cls Test ' . uniqid(), 'is_active' => true]);

        // View Any
        $this->actingAs($this->classTeacher)->get(route('assessments.types.index'))
            ->assertForbidden();

        // Create
        $this->actingAs($this->classTeacher)->post(route('assessments.types.store'), [
            'name' => 'Cls Forbidden ' . rand(100, 999),
        ])->assertForbidden();

        // Update
        $this->actingAs($this->classTeacher)->put(route('assessments.types.update', $type), [
            'name' => 'Cls Tampered',
        ])->assertForbidden();
    }

    // --- Audit Logs Verification ---

    public function test_assessment_type_creation_produces_audit_event(): void
    {
        $typeName = 'Audited Exam Type ' . rand(100, 999);

        $this->actingAs($this->officeStaff)->post(route('assessments.types.store'), [
            'name' => $typeName,
            'is_active' => '1',
        ]);

        $created = AssessmentType::where('name', $typeName)->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'CREATE_ASSESSMENT_TYPE',
            'entity_type' => 'assessment_types',
            'entity_id' => $created->id,
        ]);
    }

    public function test_assessment_type_update_produces_audit_event(): void
    {
        $type = AssessmentType::create(['name' => 'Pre-Audit ' . uniqid(), 'is_active' => true]);
        $newName = 'Post-Audit ' . rand(100, 999);

        $this->actingAs($this->officeStaff)->put(route('assessments.types.update', $type), [
            'name' => $newName,
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'UPDATE_ASSESSMENT_TYPE',
            'entity_type' => 'assessment_types',
            'entity_id' => $type->id,
        ]);
    }

    public function test_assessment_type_activate_deactivate_produces_audit_event(): void
    {
        $type = AssessmentType::create(['name' => 'Toggle Audit ' . uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin)->put(route('assessments.types.update', $type), [
            'name' => $type->name,
            'is_active' => '0',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'UPDATE_ASSESSMENT_TYPE',
            'entity_type' => 'assessment_types',
            'entity_id' => $type->id,
        ]);
    }

    // --- Validation Rules ---

    public function test_validation_rejects_empty_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('assessments.types.store'), [
            'name' => '',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_validation_rejects_duplicate_name(): void
    {
        $existing = AssessmentType::create(['name' => 'Unique Exam ' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->post(route('assessments.types.store'), [
            'name' => $existing->name,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
