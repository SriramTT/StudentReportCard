<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\MarkResultStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudentSubjectAllocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClassSubjectImprovementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected AcademicYear $currentYear;
    protected SchoolClass $class;
    protected Section $sectionA;
    protected Section $sectionB;
    protected Subject $math;
    protected Subject $science;
    protected Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();

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

        // Ensure active current academic year
        AcademicYear::query()->update(['is_current' => false]);

        $this->currentYear = AcademicYear::create([
            'name' => 'AY_CUR_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Class_T_' . uniqid(),
            'is_active' => true,
        ]);

        $this->sectionA = Section::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->sectionB = Section::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'name' => 'B',
            'is_active' => true,
        ]);

        $this->math = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MAT_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->science = Subject::create([
            'name' => 'Science',
            'code' => 'SCI_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->english = Subject::create([
            'name' => 'English',
            'code' => 'ENG_' . rand(1000, 9999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);
    }

    public function test_new_mapping_resolves_active_academic_year_server_side(): void
    {
        // Post without submitting academic_year_id
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_ids' => [$this->math->id],
        ]);

        $response->assertRedirect();

        $cs = ClassSubject::where('class_id', $this->class->id)
            ->where('section_id', $this->sectionA->id)
            ->where('subject_id', $this->math->id)
            ->firstOrFail();

        $this->assertEquals($this->currentYear->id, $cs->academic_year_id);
        $this->assertEquals('Mathematics', $cs->subject_name_snapshot);
    }

    public function test_missing_or_ambiguous_current_academic_year_fails_safely(): void
    {
        // 1. Missing active year
        $this->currentYear->update(['is_current' => false]);

        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_ids' => [$this->math->id],
        ]);

        $response->assertSessionHasErrors('academic_year_id');

        // 2. Ambiguous active year (multiple marked current)
        $this->currentYear->update(['is_current' => true]);
        $secondCurrentYear = AcademicYear::create([
            'name' => 'AY_DUP_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $response2 = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_ids' => [$this->math->id],
        ]);

        $response2->assertSessionHasErrors('academic_year_id');
    }

    public function test_existing_mapping_retains_original_academic_year_during_edit(): void
    {
        $historicalYear = AcademicYear::create([
            'name' => 'AY_HIST_' . rand(1000, 9999),
            'start_date' => '2024-06-01',
            'end_date' => '2025-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $histSection = Section::create([
            'academic_year_id' => $historicalYear->id,
            'class_id' => $this->class->id,
            'name' => 'H1',
            'is_active' => true,
        ]);

        $cs = ClassSubject::create([
            'academic_year_id' => $historicalYear->id,
            'class_id' => $this->class->id,
            'section_id' => $histSection->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        // Attempt update trying to change academic_year_id
        $response = $this->actingAs($this->officeStaff)->put(route('class_subjects.update', $cs), [
            'academic_year_id' => $this->currentYear->id,
            'is_active' => 0,
        ]);

        $response->assertRedirect();
        $cs->refresh();
        $this->assertEquals($historicalYear->id, $cs->academic_year_id, 'Academic year must never be silently changed on edit.');
        $this->assertFalse($cs->is_active);
    }

    public function test_section_is_required_and_invalid_combination_is_rejected(): void
    {
        // 1. Missing section
        $resMissing = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'subject_ids' => [$this->math->id],
        ]);
        $resMissing->assertSessionHasErrors('section_id');

        // 2. Section belonging to different class
        $otherClass = SchoolClass::create(['name' => 'OtherClass_' . uniqid(), 'is_active' => true]);
        $otherSection = Section::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $otherClass->id,
            'name' => 'Z',
            'is_active' => true,
        ]);

        $resMismatch = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $otherSection->id,
            'subject_ids' => [$this->math->id],
        ]);
        $resMismatch->assertSessionHasErrors('section_id');
    }

    public function test_multi_subject_submission_creates_all_mappings_correctly(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_ids' => [$this->math->id, $this->science->id, $this->english->id],
        ]);

        $response->assertRedirect();

        $count = ClassSubject::where('class_id', $this->class->id)
            ->where('section_id', $this->sectionA->id)
            ->whereIn('subject_id', [$this->math->id, $this->science->id, $this->english->id])
            ->count();

        $this->assertEquals(3, $count);
    }

    public function test_duplicate_subject_ids_in_request_are_rejected(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_ids' => [$this->math->id, $this->math->id],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_class_wide_mapping_expands_to_real_sections_without_creating_null_records(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => 'all_sections',
            'subject_ids' => [$this->science->id, $this->english->id],
        ]);

        $response->assertRedirect();

        // Must NOT create section_id = null
        $nullCount = ClassSubject::where('class_id', $this->class->id)
            ->whereNull('section_id')
            ->count();
        $this->assertEquals(0, $nullCount);

        // Must create mappings for Section A and Section B
        $this->assertTrue(ClassSubject::where('class_id', $this->class->id)->where('section_id', $this->sectionA->id)->where('subject_id', $this->science->id)->exists());
        $this->assertTrue(ClassSubject::where('class_id', $this->class->id)->where('section_id', $this->sectionA->id)->where('subject_id', $this->english->id)->exists());
        $this->assertTrue(ClassSubject::where('class_id', $this->class->id)->where('section_id', $this->sectionB->id)->where('subject_id', $this->science->id)->exists());
        $this->assertTrue(ClassSubject::where('class_id', $this->class->id)->where('section_id', $this->sectionB->id)->where('subject_id', $this->english->id)->exists());
    }

    public function test_class_wide_mapping_on_class_with_no_sections_fails_safely(): void
    {
        $emptyClass = SchoolClass::create(['name' => 'EmptyClass_' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $emptyClass->id,
            'section_id' => 'all_sections',
            'subject_ids' => [$this->math->id],
        ]);

        $response->assertSessionHasErrors('section_id');

        $this->assertEquals(0, ClassSubject::where('class_id', $emptyClass->id)->count());
    }

    public function test_legacy_null_mapping_coexisting_with_section_mapping_does_not_duplicate_student_allocations(): void
    {
        // 1. Create a legacy NULL section mapping for Mathematics
        $legacyCs = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => null,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        // 2. Create student enrolled in Section A
        $student = Student::create([
            'admission_number' => 'ADM_' . uniqid(),
            'student_name' => 'Alice Test',
        ]);

        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 101,
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);

        // 3. Allocate default subjects -> gets legacy mapping
        $allocationService = app(StudentSubjectAllocationService::class);
        $allocationService->allocateDefaultSubjectsForPlacement($sar);

        $initialAllocations = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)->get();
        $this->assertCount(1, $initialAllocations);
        $this->assertEquals($legacyCs->id, $initialAllocations[0]->class_subject_id);

        // 4. Now create Section A specific mapping for Mathematics
        $response = $this->actingAs($this->officeStaff)->post(route('class_subjects.store'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_ids' => [$this->math->id],
        ]);
        $response->assertRedirect();

        // 5. Verify student Alice STILL has exactly ONE active allocation for Mathematics!
        $currentAllocations = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)
            ->where('is_active', true)
            ->get();

        $this->assertCount(1, $currentAllocations, 'Student must NEVER receive duplicate subject allocations for the same subject.');

        // 6. Test placement for a new student Bob in Section A
        $studentBob = Student::create([
            'admission_number' => 'ADM_' . uniqid(),
            'student_name' => 'Bob Test',
        ]);

        $sarBob = StudentAcademicRecord::create([
            'student_id' => $studentBob->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 102,
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);

        $allocationService->allocateDefaultSubjectsForPlacement($sarBob);

        $bobAllocations = StudentSubjectAllocation::where('student_academic_record_id', $sarBob->id)->get();
        $this->assertCount(1, $bobAllocations, 'New student must receive section-specific mapping and not duplicate with legacy class-wide.');

        $secCs = ClassSubject::where('class_id', $this->class->id)
            ->where('section_id', $this->sectionA->id)
            ->where('subject_id', $this->math->id)
            ->firstOrFail();

        $this->assertEquals($secCs->id, $bobAllocations[0]->class_subject_id);
    }

    public function test_status_filter_and_grouped_display_work_consistently(): void
    {
        // Section A: Active math, Inactive science (Mixed group)
        ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => 'Science',
            'is_active' => false,
        ]);

        // Section B: Inactive english (All Inactive group)
        ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => $this->english->id,
            'subject_name_snapshot' => 'English',
            'is_active' => false,
        ]);

        // 1. GET with All Status
        $resAll = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
            'status' => 'all',
        ]));
        $resAll->assertStatus(200);
        $resAll->assertSee('Mixed');

        // 2. GET with active filter -> Only Section A with Mathematics
        $resActive = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
            'status' => 'active',
        ]));
        $resActive->assertStatus(200);
        $activeGroups = $resActive->viewData('groupedMappings');
        $this->assertCount(1, $activeGroups);
        $this->assertEquals($this->sectionA->id, $activeGroups[0]->section_id);
        $this->assertEquals('Mathematics', $activeGroups[0]->mappings[0]->subject_name_snapshot);

        // 3. GET with inactive filter -> Section A (Science) and Section B (English)
        $resInactive = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
            'status' => 'inactive',
        ]));
        $resInactive->assertStatus(200);
        $inactiveGroups = $resInactive->viewData('groupedMappings');
        $this->assertCount(2, $inactiveGroups);
    }

    public function test_group_actions_update_status_and_block_protected_removal(): void
    {
        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        $cs2 = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => 'Science',
            'is_active' => true,
        ]);

        // 1. Batch deactivate group
        $resDeact = $this->actingAs($this->admin)->post(route('class_subjects.group_status'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => 0,
        ]);
        $resDeact->assertRedirect();
        $this->assertFalse($cs1->fresh()->is_active);
        $this->assertFalse($cs2->fresh()->is_active);

        // 2. Create student allocation on cs1 -> cs1 is now protected
        $student = Student::create([
            'admission_number' => 'ADM_P_' . uniqid(),
            'student_name' => 'Protected Student',
        ]);

        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 103,
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $cs1->id,
            'allocation_type' => 'main',
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // 3. Attempt group delete -> must be blocked
        $resDel = $this->actingAs($this->admin)->delete(route('class_subjects.group_destroy'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'academic_year_id' => $this->currentYear->id,
        ]);

        $resDel->assertRedirect();
        $resDel->assertSessionHas('error');

        $this->assertTrue(ClassSubject::where('id', $cs1->id)->exists());
        $this->assertTrue(ClassSubject::where('id', $cs2->id)->exists());
    }

    public function test_historical_snapshot_column_is_absent_from_ui(): void
    {
        ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Historical Math Snapshot',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
        ]));

        $response->assertStatus(200);
        // The table header MUST NOT contain "Historical Snapshot"
        $response->assertDontSee('<th>Historical Snapshot</th>', false);
        // But the subject pill displays the snapshot name
        $response->assertSee('Historical Math Snapshot');
    }

    public function test_mapped_subjects_display_subject_code_and_status(): void
    {
        ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => 'Science',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Mathematics');
        $response->assertSee('(' . $this->math->code . ')');
        $response->assertSee('Science');
        $response->assertSee('(' . $this->science->code . ')');
        $response->assertSee('(Inactive)');
    }

    public function test_edit_modal_shows_active_mappings_checked_and_editable(): void
    {
        $csActive = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        $csInactive = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => 'Science',
            'is_active' => false,
        ]);

        // Student allocated to csActive -> should NOT make it disabled
        $student = Student::create([
            'admission_number' => 'ADM_M_' . uniqid(),
            'student_name' => 'Modal Student',
        ]);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 104,
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);
        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $csActive->id,
            'allocation_type' => 'main',
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
        ]));

        $response->assertStatus(200);
        // Active subject checkbox should be checked and NOT disabled
        $groupKey = $this->class->id . '_' . $this->sectionA->id;
        $activeCheckboxPattern = 'id="modal_sub_' . $groupKey . '_' . $this->math->id . '" checked';
        $response->assertSee($activeCheckboxPattern, false);
        $response->assertDontSee('Locked (Active Data)');

        // Inactive subject checkbox should NOT be checked, but should display (Inactive)
        $inactiveCheckboxPattern = 'id="modal_sub_' . $groupKey . '_' . $this->science->id . '" checked';
        $response->assertDontSee($inactiveCheckboxPattern, false);
    }

    public function test_sync_group_unchecking_unreferenced_subject_physically_removes_it(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        // Uncheck math by submitting empty subject_ids (or science instead)
        $response = $this->actingAs($this->admin)->post(route('class_subjects.group_sync'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'academic_year_id' => $this->currentYear->id,
            'subject_ids' => [$this->science->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Math had no allocations, so it is physically deleted
        $this->assertFalse(ClassSubject::where('id', $cs->id)->exists());
        // Science was added
        $this->assertTrue(ClassSubject::where('class_id', $this->class->id)
            ->where('section_id', $this->sectionA->id)
            ->where('subject_id', $this->science->id)
            ->exists());

        // Audit log exists
        $this->assertTrue(AuditLog::where('action', 'DELETE_CLASS_SUBJECT')
            ->where('entity_id', $cs->id)
            ->exists());
    }

    public function test_sync_group_unchecking_subject_with_allocations_deactivates_it_safely(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM_S_' . uniqid(),
            'student_name' => 'Deact Student',
        ]);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 105,
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);
        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $cs->id,
            'allocation_type' => 'main',
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Uncheck math by submitting science instead
        $response = $this->actingAs($this->admin)->post(route('class_subjects.group_sync'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'academic_year_id' => $this->currentYear->id,
            'subject_ids' => [$this->science->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Row MUST still exist in database, but is_active = false
        $this->assertTrue(ClassSubject::where('id', $cs->id)->exists());
        $this->assertFalse($cs->fresh()->is_active);

        // Allocation MUST still exist and be intact
        $this->assertTrue(StudentSubjectAllocation::where('id', $allocation->id)->exists());

        // Audit log exists
        $this->assertTrue(AuditLog::where('action', 'UPDATE_CLASS_SUBJECT')
            ->where('entity_id', $cs->id)
            ->exists());
    }

    public function test_sync_group_checking_previously_inactive_subject_reactivates_it_without_duplicate(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => false,
        ]);

        // Submit math in subject_ids to reactivate it
        $response = $this->actingAs($this->admin)->post(route('class_subjects.group_sync'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'academic_year_id' => $this->currentYear->id,
            'subject_ids' => [$this->math->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Exactly 1 row in class_subjects for this combination
        $matching = ClassSubject::where('academic_year_id', $this->currentYear->id)
            ->where('class_id', $this->class->id)
            ->where('section_id', $this->sectionA->id)
            ->where('subject_id', $this->math->id)
            ->get();

        $this->assertCount(1, $matching);
        $this->assertTrue($matching->first()->is_active);
    }

    public function test_sync_group_unchecking_subject_with_recorded_marks_is_blocked(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => 'Mathematics',
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM_MK_' . uniqid(),
            'student_name' => 'Marks Student',
        ]);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 106,
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);
        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $cs->id,
            'allocation_type' => 'main',
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $asmtType = AssessmentType::create(['name' => 'Exam Type ' . uniqid(), 'is_active' => true]);
        $assessment = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $asmtType->id,
            'name' => 'Unit Exam ' . uniqid(),
            'status' => 'active',
        ]);
        $app = AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 100,
            'is_active' => true,
        ]);

        Mark::create([
            'student_academic_record_id' => $sar->id,
            'assessment_applicability_id' => $app->id,
            'student_subject_allocation_id' => $allocation->id,
            'mark_value' => 85.00,
            'result_status' => MarkResultStatus::NUMERIC,
        ]);

        // Attempt to uncheck math (omitting it from subject_ids)
        $response = $this->actingAs($this->admin)->post(route('class_subjects.group_sync'), [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'academic_year_id' => $this->currentYear->id,
            'subject_ids' => [$this->science->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('subject_ids');

        // cs MUST remain active
        $this->assertTrue($cs->fresh()->is_active);

        // Also check that index view displays Locked (Recorded Marks)
        $viewResponse = $this->actingAs($this->admin)->get(route('class_subjects.index', [
            'class_id' => $this->class->id,
        ]));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Locked (Recorded Marks)');
    }

    public function test_sync_group_rejects_section_from_different_class(): void
    {
        $otherClass = SchoolClass::create(['name' => 'OtherClass_' . uniqid(), 'is_active' => true]);
        $otherSection = Section::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $otherClass->id,
            'name' => 'OtherSec',
            'is_active' => true,
        ]);

        // Submit mismatch: class = this->class->id, but section = otherSection->id
        $response = $this->actingAs($this->admin)->post(route('class_subjects.group_sync'), [
            'class_id' => $this->class->id,
            'section_id' => $otherSection->id,
            'academic_year_id' => $this->currentYear->id,
            'subject_ids' => [$this->math->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('section_id');
    }
}

