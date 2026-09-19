<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $officeStaffUser;
    protected User $subjectTeacherUser;
    protected User $classTeacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a temporary test route protected by role middleware
        Route::middleware(['web', 'auth', 'active', 'role:Administrator'])
            ->get('/test-admin-only', function () {
                return response()->json(['message' => 'Admin authorized']);
            });

        Route::middleware(['web', 'auth', 'active', 'role:Administrator,Office Staff'])
            ->get('/test-admin-and-office', function () {
                return response()->json(['message' => 'Staff authorized']);
            });

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $classTeacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->adminUser = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'role_test_admin',
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin User',
            'email' => 'admin@school.test',
            'is_active' => true,
        ]);

        $this->officeStaffUser = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'role_test_office',
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Staff User',
            'email' => 'office@school.test',
            'is_active' => true,
        ]);

        $this->subjectTeacherUser = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'role_test_sub_teacher',
            'password_hash' => Hash::make('password'),
            'display_name' => 'Subject Teacher User',
            'email' => 'subteacher@school.test',
            'is_active' => true,
        ]);

        $this->classTeacherUser = User::forceCreate([
            'role_id' => $classTeacherRole->id,
            'username' => 'role_test_class_teacher',
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher User',
            'email' => 'classteacher@school.test',
            'is_active' => true,
        ]);
    }

    public function test_role_helpers_identify_exact_roles(): void
    {
        $this->assertTrue($this->adminUser->isAdmin());
        $this->assertFalse($this->adminUser->isOfficeStaff());
        $this->assertFalse($this->adminUser->isSubjectTeacher());
        $this->assertFalse($this->adminUser->isClassTeacher());

        $this->assertFalse($this->officeStaffUser->isAdmin());
        $this->assertTrue($this->officeStaffUser->isOfficeStaff());
        $this->assertFalse($this->officeStaffUser->isSubjectTeacher());
        $this->assertFalse($this->officeStaffUser->isClassTeacher());

        $this->assertFalse($this->subjectTeacherUser->isAdmin());
        $this->assertFalse($this->subjectTeacherUser->isOfficeStaff());
        $this->assertTrue($this->subjectTeacherUser->isSubjectTeacher());
        $this->assertFalse($this->subjectTeacherUser->isClassTeacher());

        $this->assertFalse($this->classTeacherUser->isAdmin());
        $this->assertFalse($this->classTeacherUser->isOfficeStaff());
        $this->assertFalse($this->classTeacherUser->isSubjectTeacher());
        $this->assertTrue($this->classTeacherUser->isClassTeacher());
    }

    public function test_check_role_middleware_allows_authorized_role(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/test-admin-only');
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Admin authorized']);
    }

    public function test_check_role_middleware_blocks_unauthorized_roles_with_403(): void
    {
        $officeResponse = $this->actingAs($this->officeStaffUser)->get('/test-admin-only');
        $officeResponse->assertStatus(403);

        $subTeacherResponse = $this->actingAs($this->subjectTeacherUser)->get('/test-admin-only');
        $subTeacherResponse->assertStatus(403);

        $classTeacherResponse = $this->actingAs($this->classTeacherUser)->get('/test-admin-only');
        $classTeacherResponse->assertStatus(403);
    }

    public function test_check_role_middleware_allows_multi_role_matching(): void
    {
        $adminResponse = $this->actingAs($this->adminUser)->get('/test-admin-and-office');
        $adminResponse->assertStatus(200);

        $officeResponse = $this->actingAs($this->officeStaffUser)->get('/test-admin-and-office');
        $officeResponse->assertStatus(200);

        $subTeacherResponse = $this->actingAs($this->subjectTeacherUser)->get('/test-admin-and-office');
        $subTeacherResponse->assertStatus(403);
    }

    public function test_audit_logs_can_only_be_viewed_by_administrator(): void
    {
        $this->assertTrue(Gate::forUser($this->adminUser)->allows('viewAny', AuditLog::class));
        $this->assertFalse(Gate::forUser($this->officeStaffUser)->allows('viewAny', AuditLog::class));
        $this->assertFalse(Gate::forUser($this->subjectTeacherUser)->allows('viewAny', AuditLog::class));
        $this->assertFalse(Gate::forUser($this->classTeacherUser)->allows('viewAny', AuditLog::class));
    }

    public function test_audit_logs_are_immutable_and_cannot_be_mutated(): void
    {
        $log = new AuditLog();
        $this->assertFalse(Gate::forUser($this->adminUser)->allows('create', AuditLog::class));
        $this->assertFalse(Gate::forUser($this->adminUser)->allows('update', $log));
        $this->assertFalse(Gate::forUser($this->adminUser)->allows('delete', $log));
    }

    public function test_user_management_is_restricted_to_administrator(): void
    {
        $this->assertTrue(Gate::forUser($this->adminUser)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($this->officeStaffUser)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($this->subjectTeacherUser)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($this->classTeacherUser)->allows('create', User::class));

        // Users can never be hard-deleted
        $this->assertFalse(Gate::forUser($this->adminUser)->allows('delete', $this->officeStaffUser));
    }

    public function test_school_settings_can_only_be_mutated_by_administrator(): void
    {
        $setting = new SchoolSetting();
        $this->assertTrue(Gate::forUser($this->adminUser)->allows('update', $setting));
        $this->assertFalse(Gate::forUser($this->officeStaffUser)->allows('update', $setting));
        $this->assertFalse(Gate::forUser($this->subjectTeacherUser)->allows('update', $setting));
        $this->assertFalse(Gate::forUser($this->classTeacherUser)->allows('update', $setting));
    }

    public function test_academic_year_lifecycle_management_is_administrator_only(): void
    {
        $academicYear = new AcademicYear();
        $this->assertTrue(Gate::forUser($this->adminUser)->allows('close', $academicYear));
        $this->assertFalse(Gate::forUser($this->officeStaffUser)->allows('close', $academicYear));
        $this->assertFalse(Gate::forUser($this->subjectTeacherUser)->allows('close', $academicYear));
        $this->assertFalse(Gate::forUser($this->classTeacherUser)->allows('close', $academicYear));
    }
}
