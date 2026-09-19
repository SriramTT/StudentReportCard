<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolClassAndSectionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_cs_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin CS',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_cs_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office CS',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_cs_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher CS',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);
    }

    public function test_classes_can_be_created_and_updated(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('classes.store'), [
            'name' => 'Class 8',
            'is_active' => 1,
        ]);
        $response->assertRedirect(route('classes.index'));

        $class = SchoolClass::where('name', 'Class 8')->first();
        $this->assertNotNull($class);

        // Duplicate rejection
        $dupResponse = $this->actingAs($this->admin)->post(route('classes.store'), [
            'name' => 'Class 8',
        ]);
        $dupResponse->assertSessionHasErrors('name');

        // Deactivate class
        $this->actingAs($this->admin)->put(route('classes.update', $class), [
            'name' => 'Class 8',
            'is_active' => 0,
        ]);
        $class->refresh();
        $this->assertFalse($class->is_active);
    }

    public function test_sections_can_be_created_with_contextual_uniqueness(): void
    {
        $class8 = SchoolClass::create(['name' => 'Class 8_' . uniqid(), 'is_active' => true]);
        $class9 = SchoolClass::create(['name' => 'Class 9_' . uniqid(), 'is_active' => true]);

        // Create Section A for Class 8
        $response = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $class8->id,
            'name' => 'A',
            'is_active' => 1,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('sections', [
            'academic_year_id' => $this->year->id,
            'class_id' => $class8->id,
            'name' => 'A',
        ]);

        // Same section name 'A' is permitted under a DIFFERENT class (Class 9)
        $class9Response = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $class9->id,
            'name' => 'A',
            'is_active' => 1,
        ]);
        $class9Response->assertRedirect();

        // Duplicate section 'A' in the SAME class 8 is rejected
        $duplicateResponse = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $class8->id,
            'name' => 'A',
        ]);
        $duplicateResponse->assertSessionHasErrors('name');
    }

    public function test_teacher_cannot_create_classes_or_sections(): void
    {
        $classResponse = $this->actingAs($this->teacher)->post(route('classes.store'), [
            'name' => 'Class Teacher Attempt',
        ]);
        $classResponse->assertStatus(403);

        $class = SchoolClass::create(['name' => 'Class ' . uniqid(), 'is_active' => true]);

        $sectionResponse = $this->actingAs($this->teacher)->post(route('sections.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $class->id,
            'name' => 'X',
        ]);
        $sectionResponse->assertStatus(403);
    }
}
