<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $officeStaffUser;
    protected User $subjectTeacherUser;
    protected User $classTeacherUser;
    protected Role $adminRole;
    protected Role $officeRole;
    protected Role $subTeacherRole;
    protected Role $classTeacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $this->officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $this->subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $this->classTeacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->adminUser = User::forceCreate([
            'role_id' => $this->adminRole->id,
            'username' => 'um_test_admin_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Test Admin',
            'email' => 'admin_test@school.test',
            'is_active' => true,
        ]);

        $this->officeStaffUser = User::forceCreate([
            'role_id' => $this->officeRole->id,
            'username' => 'um_test_office_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Test Office Staff',
            'email' => 'office_test@school.test',
            'is_active' => true,
        ]);

        $this->subjectTeacherUser = User::forceCreate([
            'role_id' => $this->subTeacherRole->id,
            'username' => 'um_test_sub_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Test Subject Teacher',
            'email' => 'sub_test@school.test',
            'is_active' => true,
        ]);

        $this->classTeacherUser = User::forceCreate([
            'role_id' => $this->classTeacherRole->id,
            'username' => 'um_test_class_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Test Class Teacher',
            'email' => 'class_test@school.test',
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_view_users(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee('User Account Management');
        $response->assertSee($this->adminUser->username);
        $response->assertSee($this->officeStaffUser->username);
    }

    public function test_office_staff_can_view_and_manage_permitted_users(): void
    {
        // 1. Office Staff can view user management
        $response = $this->actingAs($this->officeStaffUser)->get(route('users.index'));
        $response->assertStatus(200);
        $response->assertSee('User Account Management');

        // 2. Office Staff can create a subject teacher
        $username = 'staff_created_' . uniqid();
        $createRes = $this->actingAs($this->officeStaffUser)->post(route('users.store'), [
            'username' => $username,
            'display_name' => 'Staff Created Teacher',
            'email' => $username . '@school.test',
            'role_id' => $this->subTeacherRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);
        $createRes->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['username' => $username]);

        $createdUser = User::where('username', $username)->firstOrFail();

        // 3. Office Staff can edit permitted user
        $editRes = $this->actingAs($this->officeStaffUser)->put(route('users.update', $createdUser), [
            'username' => $username,
            'display_name' => 'Staff Updated Teacher',
            'email' => $username . '@school.test',
            'role_id' => $this->classTeacherRole->id,
        ]);
        $editRes->assertRedirect(route('users.index'));
        $this->assertEquals('Staff Updated Teacher', $createdUser->fresh()->display_name);

        // 4. Office Staff can deactivate and reactivate permitted user
        $deactRes = $this->actingAs($this->officeStaffUser)->post(route('users.deactivate', $createdUser));
        $deactRes->assertRedirect(route('users.index'));
        $this->assertFalse((bool) $createdUser->fresh()->is_active);

        $actRes = $this->actingAs($this->officeStaffUser)->post(route('users.activate', $createdUser));
        $actRes->assertRedirect(route('users.index'));
        $this->assertTrue((bool) $createdUser->fresh()->is_active);

        // 5. Office Staff CANNOT create Administrator accounts
        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $adminCreateRes = $this->actingAs($this->officeStaffUser)->post(route('users.store'), [
            'username' => 'illegal_admin_' . uniqid(),
            'display_name' => 'Illegal Admin',
            'email' => 'illegal_admin@school.test',
            'role_id' => $adminRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);
        $adminCreateRes->assertSessionHasErrors('role_id');

        // 6. Office Staff CANNOT edit Administrator
        $adminEditRes = $this->actingAs($this->officeStaffUser)->put(route('users.update', $this->adminUser), [
            'username' => $this->adminUser->username,
            'display_name' => 'Hacked Admin Name',
            'role_id' => $this->officeRole->id,
        ]);
        $adminEditRes->assertStatus(403);

        // 7. Office Staff CANNOT deactivate Administrator
        $adminDeactRes = $this->actingAs($this->officeStaffUser)->post(route('users.deactivate', $this->adminUser));
        $adminDeactRes->assertStatus(403);

        // 8. Office Staff CANNOT change password of Administrator
        $adminPwRes = $this->actingAs($this->officeStaffUser)->post(route('users.password', $this->adminUser), [
            'password' => 'NewSecretPassword123',
            'password_confirmation' => 'NewSecretPassword123',
        ]);
        $adminPwRes->assertStatus(403);
    }

    public function test_subject_teacher_receives_403_for_user_management(): void
    {
        $response = $this->actingAs($this->subjectTeacherUser)->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_class_teacher_receives_403_for_user_management(): void
    {
        $response = $this->actingAs($this->classTeacherUser)->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_administrator_can_filter_users_by_role_and_status(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('users.index', [
            'role_id' => $this->officeRole->id,
            'status' => 'active',
        ]));

        $response->assertStatus(200);
        $response->assertSee($this->officeStaffUser->username);
    }

    public function test_administrator_can_create_office_staff(): void
    {
        $username = 'new_office_' . uniqid();
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $username,
            'display_name' => 'New Office Staff',
            'email' => 'newoffice@school.test',
            'role_id' => $this->officeRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'username' => $username,
            'role_id' => $this->officeRole->id,
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_create_subject_teacher(): void
    {
        $username = 'new_sub_teacher_' . uniqid();
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $username,
            'display_name' => 'New Subject Teacher',
            'email' => 'newsub@school.test',
            'role_id' => $this->subTeacherRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'username' => $username,
            'role_id' => $this->subTeacherRole->id,
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_create_class_teacher(): void
    {
        $username = 'new_class_teacher_' . uniqid();
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $username,
            'display_name' => 'New Class Teacher',
            'email' => 'newclass@school.test',
            'role_id' => $this->classTeacherRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'username' => $username,
            'role_id' => $this->classTeacherRole->id,
            'is_active' => true,
        ]);
    }

    public function test_administrator_cannot_create_administrator_via_standard_user_management(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'illegal_admin_' . uniqid(),
            'display_name' => 'Illegal Admin',
            'role_id' => $this->adminRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertSessionHasErrors('role_id');
    }

    public function test_duplicate_username_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $this->officeStaffUser->username,
            'display_name' => 'Duplicate User',
            'email' => 'duplicate_user@school.test',
            'role_id' => $this->officeRole->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_password_is_properly_hashed_and_never_exposed(): void
    {
        $username = 'hashed_user_' . uniqid();
        $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $username,
            'display_name' => 'Hashed User',
            'email' => $username . '@school.test',
            'role_id' => $this->officeRole->id,
            'password' => 'SecretPlaintext123',
            'password_confirmation' => 'SecretPlaintext123',
        ]);

        $created = User::where('username', $username)->firstOrFail();
        $this->assertNotEquals('SecretPlaintext123', $created->password_hash);
        $this->assertTrue(Hash::check('SecretPlaintext123', $created->password_hash));

        // Ensure password_hash is hidden from array serialization
        $this->assertArrayNotHasKey('password_hash', $created->toArray());

        // Ensure password is not exposed in listing view
        $viewResponse = $this->actingAs($this->adminUser)->get(route('users.index'));
        $viewResponse->assertDontSee('SecretPlaintext123');
        $viewResponse->assertDontSee($created->password_hash);
    }

    public function test_password_is_never_written_to_audit_logs(): void
    {
        $username = 'audit_pass_user_' . uniqid();
        $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $username,
            'display_name' => 'Audit Pass User',
            'email' => $username . '@school.test',
            'role_id' => $this->officeRole->id,
            'password' => 'SensitivePassword123!',
            'password_confirmation' => 'SensitivePassword123!',
        ]);

        $created = User::where('username', $username)->firstOrFail();

        $auditLog = AuditLog::where('entity_type', 'users')
            ->where('entity_id', $created->id)
            ->where('action', 'USER_CREATED')
            ->firstOrFail();

        $jsonContent = json_encode($auditLog->after_data);
        $this->assertStringNotContainsString('SensitivePassword123!', $jsonContent);
        $this->assertStringNotContainsString('password_hash', $jsonContent);
    }

    public function test_administrator_can_update_permitted_user_details(): void
    {
        $target = User::forceCreate([
            'role_id' => $this->officeRole->id,
            'username' => 'upd_target_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Original Name',
            'email' => 'original@school.test',
            'is_active' => true,
        ]);

        $newUsername = 'upd_target_mod_' . uniqid();
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $target), [
            'username' => $newUsername,
            'display_name' => 'Updated Name',
            'email' => 'updated@school.test',
            'role_id' => $this->subTeacherRole->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertEquals($newUsername, $target->username);
        $this->assertEquals('Updated Name', $target->display_name);
        $this->assertEquals($this->subTeacherRole->id, $target->role_id);
    }

    public function test_administrator_cannot_change_administrator_role(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $this->adminUser), [
            'username' => $this->adminUser->username,
            'display_name' => $this->adminUser->display_name,
            'email' => $this->adminUser->email,
            'role_id' => $this->officeRole->id,
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->adminUser->refresh();
        $this->assertEquals($this->adminRole->id, $this->adminUser->role_id);
    }

    public function test_administrator_can_deactivate_and_activate_another_user(): void
    {
        $target = User::forceCreate([
            'role_id' => $this->officeRole->id,
            'username' => 'deact_target_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Deact Target',
            'is_active' => true,
        ]);

        // Deactivate
        $deactResponse = $this->actingAs($this->adminUser)->post(route('users.deactivate', $target));
        $deactResponse->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertFalse($target->is_active);

        // Activate
        $actResponse = $this->actingAs($this->adminUser)->post(route('users.activate', $target));
        $actResponse->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertTrue($target->is_active);
    }

    public function test_administrator_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.deactivate', $this->adminUser));
        $response->assertStatus(403);
        $this->adminUser->refresh();
        $this->assertTrue($this->adminUser->is_active);
    }

    public function test_deactivated_user_cannot_authenticate(): void
    {
        $deactivated = User::forceCreate([
            'role_id' => $this->officeRole->id,
            'username' => 'inactive_user_' . uniqid(),
            'password_hash' => Hash::make('secretpassword'),
            'display_name' => 'Inactive User',
            'is_active' => false,
        ]);

        $response = $this->post(route('login.submit'), [
            'username' => $deactivated->username,
            'password' => 'secretpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }

    public function test_existing_active_session_is_revoked_if_user_is_deactivated(): void
    {
        $target = User::forceCreate([
            'role_id' => $this->officeRole->id,
            'username' => 'session_revoked_' . uniqid(),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Session Revoked User',
            'is_active' => true,
        ]);

        // Access while active succeeds
        $activeResponse = $this->actingAs($target)->get(route('dashboard'));
        $activeResponse->assertStatus(200);

        // Deactivate user in database
        $target->update(['is_active' => false]);

        // Next request immediately revoked by EnsureUserIsActive middleware
        $revokedResponse = $this->actingAs($target)->get(route('dashboard'));
        $revokedResponse->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_administrator_can_change_another_users_password(): void
    {
        $target = User::forceCreate([
            'role_id' => $this->officeRole->id,
            'username' => 'pwd_change_' . uniqid(),
            'password_hash' => Hash::make('OldPassword123'),
            'display_name' => 'Password Target',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('users.password', $target), [
            'password' => 'BrandNewPassword123',
            'password_confirmation' => 'BrandNewPassword123',
        ]);

        $response->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword123', $target->password_hash));
    }

    public function test_users_cannot_be_hard_deleted(): void
    {
        $this->assertFalse(Gate::forUser($this->adminUser)->allows('delete', $this->officeStaffUser));
        $this->assertFalse(Gate::forUser($this->officeStaffUser)->allows('delete', $this->subjectTeacherUser));
    }

    public function test_teachers_cannot_mutate_users_via_direct_routes(): void
    {
        // Subject Teacher direct POST
        $this->actingAs($this->subjectTeacherUser)
            ->post(route('users.store'), [
                'username' => 'hack_user_' . uniqid(),
                'display_name' => 'Hack User',
                'email' => 'hack_user@school.test',
                'role_id' => $this->subTeacherRole->id,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(403);

        // Subject Teacher direct PUT
        $this->actingAs($this->subjectTeacherUser)
            ->put(route('users.update', $this->officeStaffUser), [
                'username' => $this->officeStaffUser->username,
                'display_name' => 'Hacked Name',
                'role_id' => $this->subTeacherRole->id,
            ])
            ->assertStatus(403);

        // Class Teacher direct deactivate
        $this->actingAs($this->classTeacherUser)
            ->post(route('users.deactivate', $this->officeStaffUser))
            ->assertStatus(403);

        // Class Teacher direct change password
        $this->actingAs($this->classTeacherUser)
            ->post(route('users.password', $this->officeStaffUser), [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertStatus(403);
    }

    public function test_teacher_can_have_multiple_eligible_subjects_and_same_subject_can_belong_to_multiple_teachers(): void
    {
        $subject1 = \App\Models\Subject::forceCreate([
            'name' => 'SubMath ' . rand(100, 999),
            'code' => 'SMATH' . rand(100, 999),
            'category' => \App\Enums\SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $subject2 = \App\Models\Subject::forceCreate([
            'name' => 'SubSci ' . rand(100, 999),
            'code' => 'SSCI' . rand(100, 999),
            'category' => \App\Enums\SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // Teacher 1 with Subjects 1 and 2
        $teacher1Username = 't1_' . rand(1000, 9999);
        $res1 = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $teacher1Username,
            'display_name' => 'Teacher One',
            'email' => "t1_{$teacher1Username}@school.test",
            'role_id' => $this->subTeacherRole->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'eligible_subject_ids' => [$subject1->id, $subject2->id],
        ]);
        $res1->assertRedirect(route('users.index'));

        $teacher1 = User::where('username', $teacher1Username)->firstOrFail();
        $this->assertEquals([$subject1->id, $subject2->id], $teacher1->eligible_subject_ids);

        // Teacher 2 also with Subject 1 (many teachers to same subject)
        $teacher2Username = 't2_' . rand(1000, 9999);
        $res2 = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $teacher2Username,
            'display_name' => 'Teacher Two',
            'email' => "t2_{$teacher2Username}@school.test",
            'role_id' => $this->classTeacherRole->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'eligible_subject_ids' => [$subject1->id],
        ]);
        $res2->assertRedirect(route('users.index'));

        $teacher2 = User::where('username', $teacher2Username)->firstOrFail();
        $this->assertEquals([$subject1->id], $teacher2->eligible_subject_ids);

        // Update Teacher 1 eligibility to only subject 2
        $resUpdate = $this->actingAs($this->adminUser)->put(route('users.update', $teacher1), [
            'username' => $teacher1->username,
            'display_name' => 'Teacher One Updated',
            'email' => $teacher1->email,
            'role_id' => $teacher1->role_id,
            'eligible_subject_ids' => [$subject2->id],
        ]);
        $resUpdate->assertRedirect(route('users.index'));

        $teacher1->refresh();
        $this->assertEquals([$subject2->id], $teacher1->eligible_subject_ids);
    }

    public function test_office_staff_and_admin_cannot_have_subject_eligibility(): void
    {
        $subject = \App\Models\Subject::forceCreate([
            'name' => 'OfficeSub ' . rand(100, 999),
            'code' => 'OSUB' . rand(100, 999),
            'category' => \App\Enums\SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $officeUsername = 'off_el_' . rand(1000, 9999);
        $res = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => $officeUsername,
            'display_name' => 'Office Eligibility Test',
            'email' => "off_{$officeUsername}@school.test",
            'role_id' => $this->officeRole->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'eligible_subject_ids' => [$subject->id],
        ]);
        $res->assertRedirect(route('users.index'));

        $officeUser = User::where('username', $officeUsername)->firstOrFail();
        $this->assertNull($officeUser->eligible_subject_ids, 'Office staff must not have subject eligibility.');
    }

    public function test_subject_eligibility_filter_returns_all_matching_teachers(): void
    {
        $subject = \App\Models\Subject::forceCreate([
            'name' => 'FilterSub ' . rand(100, 999),
            'code' => 'FSUB' . rand(100, 999),
            'category' => \App\Enums\SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $teacher1 = User::forceCreate([
            'role_id' => $this->subTeacherRole->id,
            'username' => 'filter_t1_' . rand(1000, 9999),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Eligible Teacher Alpha',
            'email' => 't1_' . rand(1000, 9999) . '@school.test',
            'eligible_subject_ids' => [$subject->id],
            'is_active' => true,
        ]);

        $teacher2 = User::forceCreate([
            'role_id' => $this->subTeacherRole->id,
            'username' => 'filter_t2_' . rand(1000, 9999),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'NonEligible Teacher Beta',
            'email' => 't2_' . rand(1000, 9999) . '@school.test',
            'eligible_subject_ids' => null,
            'is_active' => true,
        ]);

        $indexRes = $this->actingAs($this->adminUser)->get(route('users.index', [
            'subject_id' => $subject->id,
        ]));
        $indexRes->assertStatus(200);
        $indexRes->assertSee($teacher1->display_name);
        $indexRes->assertDontSee($teacher2->display_name);
    }

    public function test_subject_eligibility_does_not_grant_teacher_authorization(): void
    {
        $subject = \App\Models\Subject::forceCreate([
            'name' => 'AuthTestSub ' . rand(100, 999),
            'code' => 'ATSUB' . rand(100, 999),
            'category' => \App\Enums\SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $teacher = User::forceCreate([
            'role_id' => $this->subTeacherRole->id,
            'username' => 'auth_t_' . rand(1000, 9999),
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Teacher Eligibility No Auth',
            'email' => 't_auth_' . rand(1000, 9999) . '@school.test',
            'eligible_subject_ids' => [$subject->id],
            'is_active' => true,
        ]);

        // Teacher has eligibility for $subject, but zero teacher_assignments
        $authService = app(\App\Services\TeacherAuthorizationService::class);
        $canEnter = $authService->isAuthorized($teacher, 99999, 99999, 99999, $subject->id);
        $this->assertFalse($canEnter, 'Subject eligibility must NEVER grant mark entry authorization without a teacher assignment.');
    }
}
