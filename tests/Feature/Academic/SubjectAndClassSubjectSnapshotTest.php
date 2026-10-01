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

        $this->section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'SecMain',
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
            'section_id' => $this->section->id,
            'subject_id' => $subject->id,
        ]);
        $response->assertRedirect();

        $classSubject1 = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('section_id', $this->section->id)
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
        $sec2 = Section::create(['academic_year_id' => $this->year->id, 'class_id' => $class2->id, 'name' => 'Sec2', 'is_active' => true]);
        $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $class2->id,
            'section_id' => $sec2->id,
            'subject_id' => $subject->id,
        ]);

        $classSubject2 = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $class2->id)
            ->where('section_id', $sec2->id)
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
            'section_id' => $this->section->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => 'Hacked Snapshot Value',
        ]);

        $cs = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('section_id', $this->section->id)
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

    public function test_class_subjects_index_page_loads_without_lazy_loading_violation(): void
    {
        $section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Rose',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'History',
            'code' => 'HIST_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => 'History',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Rose');
        $response->assertSee($this->class->name);
        $response->assertSee('History');
    }

    public function test_duplicate_class_subject_mapping_is_rejected(): void
    {
        $section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Lotus',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Geography',
            'code' => 'GEO_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // 1. Initial creation succeeds
        $res1 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
        $res1->assertRedirect();

        // 2. Duplicate submission for identical coordinates is rejected
        $res2 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
        $res2->assertSessionHasErrors('subject_id');

        $count = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_duplicate_class_wide_mapping_is_rejected(): void
    {
        $subject = Subject::create([
            'name' => 'Civics',
            'code' => 'CIV_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // 1. Blank section is rejected because section is required
        $resBlank = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => '',
            'subject_id' => $subject->id,
        ]);
        $resBlank->assertSessionHasErrors('section_id');

        // 2. Class-wide mapping (all_sections) expands to real sections
        $res1 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => 'all_sections',
            'subject_id' => $subject->id,
        ]);
        $res1->assertRedirect();

        // 3. Second class-wide mapping skips duplicate mappings without duplicating
        $res2 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => 'all_sections',
            'subject_id' => $subject->id,
        ]);
        $res2->assertRedirect();

        $count = ClassSubject::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('section_id', $this->section->id)
            ->where('subject_id', $subject->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_different_section_for_same_class_and_subject_is_allowed(): void
    {
        $secA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'SecA_' . rand(100, 999),
            'is_active' => true,
        ]);

        $secB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'SecB_' . rand(100, 999),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Physics',
            'code' => 'PHY_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // Section A
        $resA = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $secA->id,
            'subject_id' => $subject->id,
        ]);
        $resA->assertRedirect();

        // Section B
        $resB = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $secB->id,
            'subject_id' => $subject->id,
        ]);
        $resB->assertRedirect();

        $this->assertTrue(ClassSubject::where('section_id', $secA->id)->where('subject_id', $subject->id)->exists());
        $this->assertTrue(ClassSubject::where('section_id', $secB->id)->where('subject_id', $subject->id)->exists());
    }

    public function test_different_class_for_same_subject_is_allowed(): void
    {
        $classB = SchoolClass::create(['name' => 'ClassB_' . uniqid(), 'is_active' => true]);

        $secA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'SecA_' . rand(100, 999),
            'is_active' => true,
        ]);

        $secB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $classB->id,
            'name' => 'SecB_' . rand(100, 999),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Chemistry',
            'code' => 'CHE_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $resA = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $secA->id,
            'subject_id' => $subject->id,
        ]);
        $resA->assertRedirect();

        $resB = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $classB->id,
            'section_id' => $secB->id,
            'subject_id' => $subject->id,
        ]);
        $resB->assertRedirect();

        $this->assertTrue(ClassSubject::where('class_id', $this->class->id)->where('subject_id', $subject->id)->exists());
        $this->assertTrue(ClassSubject::where('class_id', $classB->id)->where('subject_id', $subject->id)->exists());
    }

    public function test_different_academic_year_for_same_class_section_subject_is_allowed(): void
    {
        $year2 = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2027-06-01',
            'end_date' => '2028-04-30',
            'status' => AcademicYearStatus::OPEN,
        ]);

        $sec1 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $sec2 = Section::create([
            'academic_year_id' => $year2->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Biology',
            'code' => 'BIO_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $res1 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $sec1->id,
            'subject_id' => $subject->id,
        ]);
        $res1->assertRedirect();

        $this->year->update(['is_current' => false]);
        $year2->update(['is_current' => true]);

        $res2 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $year2->id,
            'class_id' => $this->class->id,
            'section_id' => $sec2->id,
            'subject_id' => $subject->id,
        ]);
        $res2->assertRedirect();

        $this->assertTrue(ClassSubject::where('academic_year_id', $this->year->id)->where('subject_id', $subject->id)->exists());
        $this->assertTrue(ClassSubject::where('academic_year_id', $year2->id)->where('subject_id', $subject->id)->exists());
    }

    public function test_update_to_existing_combination_is_rejected(): void
    {
        $secA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A_' . rand(100, 999),
            'is_active' => true,
        ]);

        $secB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'B_' . rand(100, 999),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Economics',
            'code' => 'ECO_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $csA = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $secA->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => 'Economics',
            'is_active' => true,
        ]);

        $csB = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $secB->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => 'Economics',
            'is_active' => true,
        ]);

        // Attempt to update csB to point to secA (creating duplicate of csA)
        $response = $this->actingAs($this->officeStaff)->put(route('class_subjects.update', $csB), [
            'section_id' => $secA->id,
        ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_update_same_record_without_changing_identity_succeeds(): void
    {
        $sec = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A_' . rand(100, 999),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Art',
            'code' => 'ART_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $cs = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $sec->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => 'Art',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->put(route('class_subjects.update', $cs), [
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $cs->refresh();
        $this->assertFalse($cs->is_active);
    }

    public function test_same_subject_name_with_different_code_is_accepted(): void
    {
        $code1 = 'MATH_01_' . rand(1000, 9999);
        $code2 = 'MATH_02_' . rand(1000, 9999);

        $res1 = $this->actingAs($this->admin)->post(route('subjects.store'), [
            'name' => 'Mathematics',
            'code' => $code1,
            'category' => 'main',
            'is_active' => 1,
        ]);
        $res1->assertRedirect(route('subjects.index'));

        $res2 = $this->actingAs($this->admin)->post(route('subjects.store'), [
            'name' => 'Mathematics', // Same name!
            'code' => $code2,
            'category' => 'main',
            'is_active' => 1,
        ]);
        $res2->assertRedirect(route('subjects.index'));

        $this->assertEquals(2, Subject::where('name', 'Mathematics')->count());
    }

    public function test_duplicate_subject_code_is_rejected(): void
    {
        $code = 'UNIQUE_CODE_' . rand(1000, 9999);

        Subject::create([
            'name' => 'Subject One',
            'code' => $code,
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('subjects.store'), [
            'name' => 'Subject Two',
            'code' => $code, // Duplicate code!
            'category' => 'main',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_subject_update_retaining_own_code_is_allowed(): void
    {
        $code = 'CODE_OWN_' . rand(1000, 9999);
        $subject = Subject::create([
            'name' => 'Original Name',
            'code' => $code,
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('subjects.update', $subject), [
            'name' => 'Updated Name',
            'code' => $code,
            'category' => 'main',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('subjects.index'));
        $this->assertEquals('Updated Name', $subject->fresh()->name);
    }

    public function test_subject_update_to_another_existing_code_is_rejected(): void
    {
        $codeA = 'CODE_A_' . rand(1000, 9999);
        $codeB = 'CODE_B_' . rand(1000, 9999);

        $subA = Subject::create(['name' => 'Sub A', 'code' => $codeA, 'category' => SubjectCategory::MAIN, 'is_active' => true]);
        $subB = Subject::create(['name' => 'Sub B', 'code' => $codeB, 'category' => SubjectCategory::MAIN, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->put(route('subjects.update', $subB), [
            'name' => 'Sub B',
            'code' => $codeA, // Collides with Sub A!
            'category' => 'main',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_class_subjects_table_ordering_and_section_filter(): void
    {
        $class1 = SchoolClass::create(['name' => 'Class 1', 'is_active' => true]);
        $class2 = SchoolClass::create(['name' => 'Class 2', 'is_active' => true]);

        $sec1A = Section::create(['academic_year_id' => $this->year->id, 'class_id' => $class1->id, 'name' => 'A', 'is_active' => true]);
        $sec1B = Section::create(['academic_year_id' => $this->year->id, 'class_id' => $class1->id, 'name' => 'B', 'is_active' => true]);

        $subSci = Subject::create(['name' => 'Science', 'code' => 'SCI_' . rand(100, 999), 'category' => SubjectCategory::MAIN, 'is_active' => true]);
        $subMath = Subject::create(['name' => 'Mathematics', 'code' => 'MAT_' . rand(100, 999), 'category' => SubjectCategory::MAIN, 'is_active' => true]);
        $subEng = Subject::create(['name' => 'English', 'code' => 'ENG_' . rand(100, 999), 'category' => SubjectCategory::MAIN, 'is_active' => true]);

        // Mappings:
        // Class 1, Sec B -> Science
        ClassSubject::create(['academic_year_id' => $this->year->id, 'class_id' => $class1->id, 'section_id' => $sec1B->id, 'subject_id' => $subSci->id, 'subject_name_snapshot' => 'Science', 'is_active' => true]);
        // Class 1, Sec A -> Mathematics
        ClassSubject::create(['academic_year_id' => $this->year->id, 'class_id' => $class1->id, 'section_id' => $sec1A->id, 'subject_id' => $subMath->id, 'subject_name_snapshot' => 'Mathematics', 'is_active' => true]);
        // Class 1, Class-wide (null section) -> English
        ClassSubject::create(['academic_year_id' => $this->year->id, 'class_id' => $class1->id, 'section_id' => null, 'subject_id' => $subEng->id, 'subject_name_snapshot' => 'English', 'is_active' => true]);
        // Class 2, Class-wide -> Mathematics
        ClassSubject::create(['academic_year_id' => $this->year->id, 'class_id' => $class2->id, 'section_id' => null, 'subject_id' => $subMath->id, 'subject_name_snapshot' => 'Mathematics', 'is_active' => true]);

        // GET index for this academic year
        $response = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertStatus(200);

        /** @var \Illuminate\Database\Eloquent\Collection $items */
        $items = $response->viewData('classSubjects');

        // Verify order:
        // 1. Class 1 (Class-Wide) -> English
        // 2. Class 1 (Section A) -> Mathematics
        // 3. Class 1 (Section B) -> Science
        // 4. Class 2 (Class-Wide) -> Mathematics
        $this->assertEquals('Class 1', $items[0]->schoolClass->name);
        $this->assertNull($items[0]->section_id);
        $this->assertEquals('English', $items[0]->subject->name);

        $this->assertEquals('Class 1', $items[1]->schoolClass->name);
        $this->assertEquals('A', $items[1]->section->name);
        $this->assertEquals('Mathematics', $items[1]->subject->name);

        $this->assertEquals('Class 1', $items[2]->schoolClass->name);
        $this->assertEquals('B', $items[2]->section->name);
        $this->assertEquals('Science', $items[2]->subject->name);

        $this->assertEquals('Class 2', $items[3]->schoolClass->name);

        // Test section listing filter
        $filterResponse = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $class1->id,
            'section_id' => $sec1A->id,
        ]));

        $filterResponse->assertStatus(200);
        $filteredItems = $filterResponse->viewData('classSubjects');
        $this->assertCount(1, $filteredItems);
        $this->assertEquals($sec1A->id, $filteredItems[0]->section_id);
    }

    public function test_submitting_mismatched_class_and_section_is_rejected(): void
    {
        $classA = SchoolClass::create(['name' => 'Class A_' . rand(100, 999), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B_' . rand(100, 999), 'is_active' => true]);

        // Section belongs to Class B
        $secB = Section::create(['academic_year_id' => $this->year->id, 'class_id' => $classB->id, 'name' => 'B1', 'is_active' => true]);

        $sub = Subject::create(['name' => 'Sub_' . rand(100, 999), 'code' => 'SUB_' . rand(100, 999), 'category' => SubjectCategory::MAIN, 'is_active' => true]);

        // Submit Class A with Section B (mismatch!)
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $classA->id,
            'section_id' => $secB->id,
            'subject_id' => $sub->id,
        ]);

        $response->assertSessionHasErrors('section_id');
    }
}
