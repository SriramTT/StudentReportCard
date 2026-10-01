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

        $ay = \App\Models\AcademicYear::where('status', \App\Enums\AcademicYearStatus::OPEN)->first()
            ?? \App\Models\AcademicYear::create([
                'name' => 'AY_SETTING_' . rand(1000, 9999),
                'start_date' => '2026-06-01',
                'end_date' => '2027-04-30',
                'status' => \App\Enums\AcademicYearStatus::OPEN,
                'is_current' => true,
            ]);
        $cls = \App\Models\SchoolClass::first() ?? \App\Models\SchoolClass::create(['name' => 'Class 1', 'is_active' => true]);
        $sec = \App\Models\Section::where('academic_year_id', $ay->id)->first()
            ?? \App\Models\Section::create(['academic_year_id' => $ay->id, 'class_id' => $cls->id, 'name' => 'A', 'is_active' => true]);

        \App\Models\TeacherAssignment::create([
            'user_id' => $this->teacher->id,
            'academic_year_id' => $ay->id,
            'class_id' => $cls->id,
            'section_id' => $sec->id,
            'subject_id' => null,
            'assignment_type' => \App\Enums\TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $settings = SchoolSetting::first();
        if ($settings) {
            $settings->update(['school_logo_path' => 'logos/default_test_logo.png']);
        } else {
            SchoolSetting::create([
                'school_name' => 'Test Academy',
                'school_logo_path' => 'logos/default_test_logo.png',
                'pass_mark' => 35.00,
            ]);
        }
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

    public function test_office_staff_can_view_and_update_school_settings(): void
    {
        $response = $this->actingAs($this->officeStaff)->get(route('admin.school_settings.edit'));
        $response->assertStatus(200);
        $response->assertSee('School Global Configuration');

        $updateResponse = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'St. Jude Office Updated High School',
            'pass_mark' => 45.00,
        ]);
        $updateResponse->assertRedirect(route('admin.school_settings.edit'));
        $updateResponse->assertSessionHas('success');

        $setting = SchoolSetting::first();
        $this->assertEquals('St. Jude Office Updated High School', $setting->school_name);
        $this->assertEquals('45.00', (string) $setting->pass_mark);
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

    public function test_administrator_can_upload_valid_logo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('logo.png', 150, 150);

        $response = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Logo Test School',
            'pass_mark' => 35.00,
            'school_logo' => $file,
        ]);

        $response->assertRedirect(route('admin.school_settings.edit'));
        $response->assertSessionHas('success');

        $setting = SchoolSetting::first();
        $this->assertNotNull($setting->school_logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($setting->school_logo_path);
    }

    public function test_invalid_logo_file_is_rejected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $invalidFile = \Illuminate\Http\UploadedFile::fake()->create('malicious.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Invalid Logo School',
            'pass_mark' => 35.00,
            'school_logo' => $invalidFile,
        ]);

        $response->assertSessionHasErrors('school_logo');
    }

    public function test_sidebar_reflects_school_logo_only(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('branding_logo.png', 160, 60);

        $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Dynamic Global Academy',
            'pass_mark' => 40.00,
            'school_logo' => $file,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('class="sidebar-logo"', false);
        $response->assertDontSee('<h2>Dynamic Global Academy</h2>', false);
        $response->assertDontSee('School System</h2>', false);
    }

    public function test_office_staff_can_upload_and_preview_principal_signature(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $principalSig = \Illuminate\Http\UploadedFile::fake()->image('principal_sig.jpg', 100, 40);

        $response = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Signature Enabled School',
            'pass_mark' => 35.00,
            'principal_signature' => $principalSig,
        ]);

        $response->assertRedirect(route('admin.school_settings.edit'));
        $response->assertSessionHas('success');

        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('school-settings/signatures/principal.jpg');

        $viewResponse = $this->actingAs($this->officeStaff)->get(route('admin.school_settings.edit'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('data:image/jpeg;base64,', false);
    }

    public function test_principal_signature_replacement_removes_stale_extension_variants(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        // First upload PNG
        $initialSig = \Illuminate\Http\UploadedFile::fake()->image('principal.png', 100, 40);
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Sig Test School',
            'pass_mark' => 35.00,
            'principal_signature' => $initialSig,
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('school-settings/signatures/principal.png');

        // Replace with JPG
        $newSig = \Illuminate\Http\UploadedFile::fake()->image('principal.jpg', 100, 40);
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Sig Test School',
            'pass_mark' => 35.00,
            'principal_signature' => $newSig,
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('school-settings/signatures/principal.jpg');
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing('school-settings/signatures/principal.png');
    }

    public function test_principal_signature_removal_works(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $principalSig = \Illuminate\Http\UploadedFile::fake()->image('principal.png', 100, 40);
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Sig Test School',
            'pass_mark' => 35.00,
            'principal_signature' => $principalSig,
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('school-settings/signatures/principal.png');

        // Remove signature
        $response = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Sig Test School',
            'pass_mark' => 35.00,
            'remove_principal_signature' => '1',
        ]);
        $response->assertRedirect(route('admin.school_settings.edit'));
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing('school-settings/signatures/principal.png');
    }

    public function test_invalid_signature_file_type_and_oversize_are_rejected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $invalidType = \Illuminate\Http\UploadedFile::fake()->create('signature.pdf', 100, 'application/pdf');
        $oversized = \Illuminate\Http\UploadedFile::fake()->create('huge.png', 3000, 'image/png');

        $response = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Sig Test School',
            'pass_mark' => 35.00,
            'principal_signature' => $oversized,
            'teacher_signatures' => [
                $this->teacher->id => $invalidType,
            ],
        ]);

        $response->assertSessionHasErrors(['principal_signature', "teacher_signatures.{$this->teacher->id}"]);
    }

    public function test_office_staff_can_upload_and_preview_individual_teacher_signatures(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $teacherSig = \Illuminate\Http\UploadedFile::fake()->image('teacher.png', 100, 40);

        $response = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Teacher Sig Test School',
            'pass_mark' => 35.00,
            'teacher_signatures' => [
                $this->teacher->id => $teacherSig,
            ],
        ]);

        $response->assertRedirect(route('admin.school_settings.edit'));
        $response->assertSessionHas('success');

        \Illuminate\Support\Facades\Storage::disk('local')->assertExists("school-settings/signatures/teachers/{$this->teacher->id}.png");

        $viewResponse = $this->actingAs($this->officeStaff)->get(route('admin.school_settings.edit'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('data:image/png;base64,', false);
    }

    public function test_teacher_signature_replacement_removes_stale_extension_variants(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        // First upload PNG
        $initialSig = \Illuminate\Http\UploadedFile::fake()->image('teacher.png', 100, 40);
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Teacher Sig Test School',
            'pass_mark' => 35.00,
            'teacher_signatures' => [
                $this->teacher->id => $initialSig,
            ],
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists("school-settings/signatures/teachers/{$this->teacher->id}.png");

        // Replace with JPG
        $newSig = \Illuminate\Http\UploadedFile::fake()->image('teacher.jpg', 100, 40);
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Teacher Sig Test School',
            'pass_mark' => 35.00,
            'teacher_signatures' => [
                $this->teacher->id => $newSig,
            ],
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->assertExists("school-settings/signatures/teachers/{$this->teacher->id}.jpg");
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing("school-settings/signatures/teachers/{$this->teacher->id}.png");
    }

    public function test_teacher_signature_removal_works(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $teacherSig = \Illuminate\Http\UploadedFile::fake()->image('teacher.png', 100, 40);
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Teacher Sig Test School',
            'pass_mark' => 35.00,
            'teacher_signatures' => [
                $this->teacher->id => $teacherSig,
            ],
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists("school-settings/signatures/teachers/{$this->teacher->id}.png");

        // Remove signature
        $response = $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Teacher Sig Test School',
            'pass_mark' => 35.00,
            'remove_teacher_signatures' => [$this->teacher->id],
        ]);
        $response->assertRedirect(route('admin.school_settings.edit'));
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing("school-settings/signatures/teachers/{$this->teacher->id}.png");
    }

    public function test_logo_required_lifecycle_rejects_removal_without_replacement(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $initialLogo = \Illuminate\Http\UploadedFile::fake()->image('existing_logo.png', 100, 100);

        $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Existing Logo School',
            'pass_mark' => 35.00,
            'school_logo' => $initialLogo,
        ]);

        $setting = SchoolSetting::first();
        $this->assertNotNull($setting->school_logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($setting->school_logo_path);

        // Attempt removal without replacement
        $response = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Existing Logo School',
            'pass_mark' => 35.00,
            'remove_school_logo' => 1,
        ]);

        $response->assertSessionHasErrors('school_logo');

        // Existing file must remain intact
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($setting->school_logo_path);
    }

    public function test_logo_required_lifecycle_allows_removal_with_replacement(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $initialLogo = \Illuminate\Http\UploadedFile::fake()->image('old_logo.png', 100, 100);

        $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Replacement Test School',
            'pass_mark' => 35.00,
            'school_logo' => $initialLogo,
        ]);

        $oldPath = SchoolSetting::first()->school_logo_path;
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($oldPath);

        // Replacement upload with remove flag
        $replacementLogo = \Illuminate\Http\UploadedFile::fake()->image('new_logo.png', 100, 100);
        $response = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Replacement Test School',
            'pass_mark' => 35.00,
            'remove_school_logo' => 1,
            'school_logo' => $replacementLogo,
        ]);

        $response->assertRedirect(route('admin.school_settings.edit'));
        $response->assertSessionHas('success');

        $newPath = SchoolSetting::first()->school_logo_path;
        $this->assertNotEquals($oldPath, $newPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($newPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_logo_can_remain_unchanged_during_unrelated_settings_update(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $initialLogo = \Illuminate\Http\UploadedFile::fake()->image('stable_logo.png', 100, 100);

        $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Stable Logo School',
            'pass_mark' => 35.00,
            'school_logo' => $initialLogo,
        ]);

        $originalPath = SchoolSetting::first()->school_logo_path;

        // Change only pass mark and name
        $response = $this->actingAs($this->admin)->put(route('admin.school_settings.update'), [
            'school_name' => 'Updated School Name',
            'pass_mark' => 45.00,
        ]);

        $response->assertRedirect(route('admin.school_settings.edit'));
        $response->assertSessionHas('success');

        $setting = SchoolSetting::first();
        $this->assertEquals('Updated School Name', $setting->school_name);
        $this->assertEquals(45.00, $setting->pass_mark);
        $this->assertEquals($originalPath, $setting->school_logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($originalPath);
    }

    public function test_class_teacher_signature_table_filters_only_active_class_teachers(): void
    {
        $ay = \App\Models\AcademicYear::create([
            'name' => 'AY_FILTER_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => \App\Enums\AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $cls = \App\Models\SchoolClass::create(['name' => 'Class Filter', 'is_active' => true]);
        $sec = \App\Models\Section::create(['academic_year_id' => $ay->id, 'class_id' => $cls->id, 'name' => 'A', 'is_active' => true]);
        $subj = \App\Models\Subject::create(['name' => 'Subj Filter', 'code' => 'SF_' . uniqid(), 'is_active' => true]);

        $ctRole = \App\Models\Role::firstOrCreate(['name' => 'Class Teacher'], ['description' => 'CT', 'is_active' => true]);
        $stRole = \App\Models\Role::firstOrCreate(['name' => 'Subject Teacher'], ['description' => 'ST', 'is_active' => true]);

        // Teacher 1: Active Class Teacher
        $teacherCT = \App\Models\User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'ct_only_' . uniqid(),
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'display_name' => 'Teacher CT Only',
            'is_active' => true,
        ]);
        \App\Models\TeacherAssignment::create([
            'user_id' => $teacherCT->id,
            'academic_year_id' => $ay->id,
            'class_id' => $cls->id,
            'section_id' => $sec->id,
            'subject_id' => null,
            'assignment_type' => \App\Enums\TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Teacher 2: Pure Subject Teacher
        $teacherST = \App\Models\User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 'st_only_' . uniqid(),
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'display_name' => 'Teacher ST Only',
            'is_active' => true,
        ]);
        \App\Models\TeacherAssignment::create([
            'user_id' => $teacherST->id,
            'academic_year_id' => $ay->id,
            'class_id' => $cls->id,
            'section_id' => $sec->id,
            'subject_id' => $subj->id,
            'assignment_type' => \App\Enums\TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $viewResponse = $this->actingAs($this->officeStaff)->get(route('admin.school_settings.edit'));
        $viewResponse->assertStatus(200);

        $classTeachers = $viewResponse->viewData('classTeachers');
        $this->assertNotNull($classTeachers);

        // Teacher CT MUST be in class teachers list
        $this->assertTrue($classTeachers->contains('id', $teacherCT->id));

        // Teacher ST MUST NOT be in class teachers list
        $this->assertFalse($classTeachers->contains('id', $teacherST->id));
    }
}
