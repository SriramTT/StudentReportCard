<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolSettingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_settings_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Settings',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_settings_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Settings',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_settings_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher Settings',
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_view_and_update_school_settings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.school_settings.edit'));
        $response->assertStatus(200);
        $response->assertSee('School Global Configuration');

        $updateResponse = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'St. Jude Global High School',
            'pass_mark' => 40.00,
            'school_logo_path' => 'logos/st_jude.png',
        ]);

        $updateResponse->assertRedirect(route('admin.school_settings.edit'));
        $updateResponse->assertSessionHas('success');

        $setting = SchoolSetting::first();
        $this->assertEquals('St. Jude Global High School', $setting->school_name);
        $this->assertEquals('40.00', (string) $setting->pass_mark);
        $this->assertEquals('logos/st_jude.png', $setting->school_logo_path);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_SCHOOL_SETTINGS',
            'entity_type' => 'school_settings',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_office_staff_is_denied_access_to_school_settings(): void
    {
        $response = $this->actingAs($this->officeStaff)->get(route('admin.school_settings.edit'));
        $response->assertStatus(403);

        $updateResponse = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Unauthorized School Update',
            'pass_mark' => 50.00,
        ]);
        $updateResponse->assertStatus(403);
    }

    public function test_teacher_is_denied_access_to_school_settings(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.school_settings.edit'));
        $response->assertStatus(403);

        $updateResponse = $this->actingAs($this->teacher)->put(route('admin.school_settings.update'), [
            'school_name' => 'Unauthorized School Update',
            'pass_mark' => 50.00,
        ]);
        $updateResponse->assertStatus(403);
    }

    public function test_negative_pass_mark_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Negative Pass Mark School',
            'pass_mark' => -5.00,
        ]);

        $response->assertSessionHasErrors('pass_mark');
    }
}
