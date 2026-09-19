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
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubjectAndClassSubjectSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $year;
    protected SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_sub_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Sub',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_sub_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Sub',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_sub_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher Sub',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Class_' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_subject_renaming_preserves_historical_class_subject_snapshot(): void
    {
        // 1. Create master Subject "Mathematics"
        $subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // 2. Create Class Subject mapping
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
        ]);
        $response->assertRedirect();

        $classSubject1 = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        // 3. Verify initial snapshot is "Mathematics"
        $this->assertEquals('Mathematics', $classSubject1->subject_name_snapshot);

        // 4. Rename master subject to "Advanced Mathematics"
        $this->actingAs($this->admin)->put(route('subjects.update', $subject), [
            'name' => 'Advanced Mathematics',
            'code' => $subject->code,
            'category' => 'main',
            'is_active' => 1,
        ]);

        $subject->refresh();
        $this->assertEquals('Advanced Mathematics', $subject->name);

        // 5. Existing Class Subject snapshot MUST remain "Mathematics"
        $classSubject1->refresh();
        $this->assertEquals('Mathematics', $classSubject1->subject_name_snapshot, 'Historical snapshot must never be rewritten on subject rename.');

        // 6. Create a second class and map the renamed subject
        $class2 = SchoolClass::create(['name' => 'Class2_' . uniqid(), 'is_active' => true]);
        $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $class2->id,
            'subject_id' => $subject->id,
        ]);

        $classSubject2 = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $class2->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        // New Class Subject gets the new name "Advanced Mathematics"
        $this->assertEquals('Advanced Mathematics', $classSubject2->subject_name_snapshot);
    }

    public function test_client_cannot_tamper_with_subject_name_snapshot(): void
    {
        $subject = Subject::create([
            'name' => 'Science',
            'code' => 'SCI_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // Malicious client tries to submit fake snapshot
        $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => 'Hacked Snapshot Value',
        ]);

        $cs = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        // Server derives snapshot from master record, ignoring client input
        $this->assertEquals('Science', $cs->subject_name_snapshot);
    }

    public function test_class_subject_with_mismatched_section_is_rejected(): void
    {
        $otherYear = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::OPEN,
        ]);

        // Section belongs to otherYear, not this->year
        $foreignSection = Section::create([
            'academic_year_id' => $otherYear->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'English',
            'code' => 'ENG_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $foreignSection->id,
            'subject_id' => $subject->id,
        ]);

        $response->assertSessionHasErrors('section_id');
    }
}
