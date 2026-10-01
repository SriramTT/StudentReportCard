<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SectionPageImprovementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected User $classTeacher;
    protected AcademicYear $activeYear;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $classTeacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Sec',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Sec',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher Sec',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $classTeacherRole->id,
            'username' => 'cteacher_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher Sec',
            'is_active' => true,
        ]);

        // Ensure there is exactly one active open academic year
        AcademicYear::query()->update(['is_current' => false]);

        $this->activeYear = AcademicYear::create([
            'name' => 'AY_Active_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);
    }

    public function test_classes_and_sections_are_displayed_in_natural_ascending_order(): void
    {
        $class10 = SchoolClass::create(['name' => 'Class 10_' . uniqid(), 'is_active' => true]);
        $class2 = SchoolClass::create(['name' => 'Class 2_' . uniqid(), 'is_active' => true]);
        $class1 = SchoolClass::create(['name' => 'Class 1_' . uniqid(), 'is_active' => true]);

        // Add sections in mixed order
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class2->id, 'name' => 'C', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class2->id, 'name' => 'A', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class2->id, 'name' => 'B', 'is_active' => true]);

        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class1->id, 'name' => 'A', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class10->id, 'name' => 'A', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('sections.index'));
        $response->assertOk();

        $groupedSections = $response->viewData('groupedSections');

        // Extract class names matching our created classes
        $names = $groupedSections->pluck('class_name')->values();
        $pos1 = $names->search($class1->name);
        $pos2 = $names->search($class2->name);
        $pos10 = $names->search($class10->name);

        $this->assertNotFalse($pos1);
        $this->assertNotFalse($pos2);
        $this->assertNotFalse($pos10);

        // Natural sort: Class 1 < Class 2 < Class 10
        $this->assertTrue($pos1 < $pos2);
        $this->assertTrue($pos2 < $pos10);

        // Within Class 2, sections must be sorted A, B, C
        $class2Group = $groupedSections->firstWhere('class_id', $class2->id);
        $this->assertNotNull($class2Group);
        $secNames = $class2Group->sections->pluck('name')->values()->all();
        $this->assertSame(['A', 'B', 'C'], $secNames);
    }

    public function test_grouped_status_calculation_all_active_all_inactive_and_mixed(): void
    {
        $classActive = SchoolClass::create(['name' => 'Class AllActive_' . uniqid(), 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classActive->id, 'name' => 'A', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classActive->id, 'name' => 'B', 'is_active' => true]);

        $classInactive = SchoolClass::create(['name' => 'Class AllInactive_' . uniqid(), 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classInactive->id, 'name' => 'A', 'is_active' => false]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classInactive->id, 'name' => 'B', 'is_active' => false]);

        $classMixed = SchoolClass::create(['name' => 'Class Mixed_' . uniqid(), 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classMixed->id, 'name' => 'A', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classMixed->id, 'name' => 'B', 'is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('sections.index'));
        $response->assertOk();

        $groupedSections = $response->viewData('groupedSections');

        $activeGroup = $groupedSections->firstWhere('class_id', $classActive->id);
        $this->assertSame('active', $activeGroup->status);
        $this->assertSame(2, $activeGroup->active_count);
        $this->assertSame(0, $activeGroup->inactive_count);

        $inactiveGroup = $groupedSections->firstWhere('class_id', $classInactive->id);
        $this->assertSame('inactive', $inactiveGroup->status);
        $this->assertSame(0, $inactiveGroup->active_count);
        $this->assertSame(2, $inactiveGroup->inactive_count);

        $mixedGroup = $groupedSections->firstWhere('class_id', $classMixed->id);
        $this->assertSame('mixed', $mixedGroup->status);
        $this->assertSame(1, $mixedGroup->active_count);
        $this->assertSame(1, $mixedGroup->inactive_count);
    }

    public function test_only_sections_from_active_academic_year_are_listed(): void
    {
        $pastYear = AcademicYear::create([
            'name' => 'AY_Past_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $class = SchoolClass::create(['name' => 'Class YearTest_' . uniqid(), 'is_active' => true]);

        $activeSection = Section::create([
            'academic_year_id' => $this->activeYear->id,
            'class_id' => $class->id,
            'name' => 'ActiveYearSec',
            'is_active' => true,
        ]);

        $pastSection = Section::create([
            'academic_year_id' => $pastYear->id,
            'class_id' => $class->id,
            'name' => 'PastYearSec',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('sections.index'));
        $response->assertOk();

        $group = $response->viewData('groupedSections')->firstWhere('class_id', $class->id);
        $this->assertNotNull($group);

        $secNames = $group->sections->pluck('name')->all();
        $this->assertContains('ActiveYearSec', $secNames);
        $this->assertNotContains('PastYearSec', $secNames);
    }

    public function test_new_section_automatically_receives_active_academic_year_server_side(): void
    {
        $class = SchoolClass::create(['name' => 'Class AutoYear_' . uniqid(), 'is_active' => true]);

        // Omitting academic_year_id from request payload
        $response = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'class_id' => $class->id,
            'name' => 'AutoAssigned',
            'is_active' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('sections', [
            'class_id' => $class->id,
            'name' => 'AutoAssigned',
            'academic_year_id' => $this->activeYear->id,
            'is_active' => true,
        ]);
    }

    public function test_client_supplied_tampered_academic_year_id_is_rejected(): void
    {
        $otherYear = AcademicYear::create([
            'name' => 'AY_Other_' . rand(1000, 9999),
            'start_date' => '2027-06-01',
            'end_date' => '2028-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $class = SchoolClass::create(['name' => 'Class Tamper_' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'academic_year_id' => $otherYear->id,
            'class_id' => $class->id,
            'name' => 'TamperAttempt',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('academic_year_id');
        $this->assertDatabaseMissing('sections', [
            'name' => 'TamperAttempt',
        ]);
    }

    public function test_creation_fails_safely_when_no_active_year_exists(): void
    {
        AcademicYear::query()->update(['is_current' => false]);

        $class = SchoolClass::create(['name' => 'Class NoYear_' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'class_id' => $class->id,
            'name' => 'NoActiveYearSec',
        ]);

        $response->assertSessionHasErrors('academic_year_id');

        // Check index page shows notice
        $indexResponse = $this->actingAs($this->admin)->get(route('sections.index'));
        $indexResponse->assertOk();
        $this->assertNotNull($indexResponse->viewData('activeYearError'));
    }

    public function test_creation_fails_safely_when_multiple_current_years_exist(): void
    {
        AcademicYear::create([
            'name' => 'AY_Conflict_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $class = SchoolClass::create(['name' => 'Class Conflict_' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'class_id' => $class->id,
            'name' => 'ConflictYearSec',
        ]);

        $response->assertSessionHasErrors('academic_year_id');

        $indexResponse = $this->actingAs($this->admin)->get(route('sections.index'));
        $indexResponse->assertOk();
        $this->assertNotNull($indexResponse->viewData('activeYearError'));
    }

    public function test_class_filter_filters_table_by_class(): void
    {
        $classA = SchoolClass::create(['name' => 'Class FilterA_' . uniqid(), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class FilterB_' . uniqid(), 'is_active' => true]);

        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classA->id, 'name' => 'SecA', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classB->id, 'name' => 'SecB', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('sections.index', ['class_id' => $classA->id]));
        $response->assertOk();

        $grouped = $response->viewData('groupedSections');
        $this->assertTrue($grouped->contains('class_id', $classA->id));
        $this->assertFalse($grouped->contains('class_id', $classB->id));
    }

    public function test_section_filter_filters_table_by_section_name(): void
    {
        $classA = SchoolClass::create(['name' => 'Class SecFilterA_' . uniqid(), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class SecFilterB_' . uniqid(), 'is_active' => true]);

        // Class A has Lotus and Rose
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classA->id, 'name' => 'Lotus', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classA->id, 'name' => 'Rose', 'is_active' => true]);

        // Class B has only Rose
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classB->id, 'name' => 'Rose', 'is_active' => true]);

        // Filter by Lotus: only Class A should appear, and inside Class A, only Lotus is listed
        $response = $this->actingAs($this->admin)->get(route('sections.index', ['section_name' => 'Lotus']));
        $response->assertOk();

        $grouped = $response->viewData('groupedSections');
        $this->assertTrue($grouped->contains('class_id', $classA->id));
        $this->assertFalse($grouped->contains('class_id', $classB->id));

        $groupA = $grouped->firstWhere('class_id', $classA->id);
        $this->assertSame(['Lotus'], $groupA->sections->pluck('name')->all());
    }

    public function test_class_and_section_filters_work_together(): void
    {
        $classA = SchoolClass::create(['name' => 'Class ComboA_' . uniqid(), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class ComboB_' . uniqid(), 'is_active' => true]);

        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classA->id, 'name' => 'Alpha', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classB->id, 'name' => 'Alpha', 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classA->id, 'name' => 'Beta', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('sections.index', [
            'class_id' => $classA->id,
            'section_name' => 'Alpha',
        ]));
        $response->assertOk();

        $grouped = $response->viewData('groupedSections');
        $this->assertCount(1, $grouped);
        $this->assertSame($classA->id, $grouped->first()->class_id);
        $this->assertSame(['Alpha'], $grouped->first()->sections->pluck('name')->all());
    }

    public function test_stale_or_invalid_section_filter_is_sanitized(): void
    {
        $classA = SchoolClass::create(['name' => 'Class StaleA_' . uniqid(), 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $classA->id, 'name' => 'Gamma', 'is_active' => true]);

        // Section 'NonExistent' does not exist
        $response = $this->actingAs($this->admin)->get(route('sections.index', [
            'class_id' => $classA->id,
            'section_name' => 'NonExistent',
        ]));
        $response->assertOk();

        // Stale section name should be reset to null
        $this->assertNull($response->viewData('selectedSectionName'));
    }

    public function test_reset_filters_restores_default_active_year_view(): void
    {
        $class = SchoolClass::create(['name' => 'Class Reset_' . uniqid(), 'is_active' => true]);
        Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'A', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('sections.index'));
        $response->assertOk();

        $this->assertNull($response->viewData('selectedClassId'));
        $this->assertNull($response->viewData('selectedSectionName'));
        $this->assertTrue($response->viewData('groupedSections')->contains('class_id', $class->id));
    }

    public function test_editing_one_section_updates_only_that_section(): void
    {
        $class = SchoolClass::create(['name' => 'Class EditOne_' . uniqid(), 'is_active' => true]);
        $secA = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'A', 'is_active' => true]);
        $secB = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'B', 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->put(route('sections.update', $secA), [
            'name' => 'A-Renamed',
            'is_active' => 1,
        ]);
        $response->assertRedirect();

        $secA->refresh();
        $secB->refresh();

        $this->assertSame('A-Renamed', $secA->name);
        $this->assertSame('B', $secB->name);
        $this->assertSame($this->activeYear->id, $secA->academic_year_id);
        $this->assertSame($class->id, $secA->class_id);
    }

    public function test_activating_or_deactivating_one_section_changes_only_that_section(): void
    {
        $class = SchoolClass::create(['name' => 'Class Toggle_' . uniqid(), 'is_active' => true]);
        $secA = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'A', 'is_active' => true]);
        $secB = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'B', 'is_active' => true]);

        $response = $this->actingAs($this->officeStaff)->put(route('sections.update', $secA), [
            'name' => $secA->name,
            'is_active' => 0,
        ]);
        $response->assertRedirect();

        $secA->refresh();
        $secB->refresh();

        $this->assertFalse($secA->is_active);
        $this->assertTrue($secB->is_active);
    }

    public function test_section_removal_is_blocked_when_referenced_by_class_subject(): void
    {
        $class = SchoolClass::create(['name' => 'Class RefDel_' . uniqid(), 'is_active' => true]);
        $sec = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'RefSec', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Sub_' . uniqid(), 'code' => 'SB' . rand(100, 999), 'is_active' => true]);

        ClassSubject::create([
            'academic_year_id' => $this->activeYear->id,
            'class_id' => $class->id,
            'section_id' => $sec->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('sections.destroy', $sec));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('sections', [
            'id' => $sec->id,
        ]);
    }

    public function test_unreferenced_section_can_be_removed_by_admin_with_audit_log(): void
    {
        $class = SchoolClass::create(['name' => 'Class CleanDel_' . uniqid(), 'is_active' => true]);
        $sec = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'UnrefSec', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->delete(route('sections.destroy', $sec));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('sections', [
            'id' => $sec->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE_SECTION',
            'entity_type' => 'sections',
            'entity_id' => $sec->id,
        ]);
    }

    public function test_office_staff_can_view_create_and_update_but_cannot_delete(): void
    {
        $class = SchoolClass::create(['name' => 'Class OS_' . uniqid(), 'is_active' => true]);
        $sec = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'OSSec', 'is_active' => true]);

        // View
        $viewResponse = $this->actingAs($this->officeStaff)->get(route('sections.index'));
        $viewResponse->assertOk();

        // Create
        $createResponse = $this->actingAs($this->officeStaff)->post(route('sections.store'), [
            'class_id' => $class->id,
            'name' => 'OSNew',
            'is_active' => 1,
        ]);
        $createResponse->assertRedirect();

        // Update
        $updateResponse = $this->actingAs($this->officeStaff)->put(route('sections.update', $sec), [
            'name' => 'OSRenamed',
            'is_active' => 1,
        ]);
        $updateResponse->assertRedirect();

        // Delete (forbidden for Office Staff per SectionPolicy)
        $deleteResponse = $this->actingAs($this->officeStaff)->delete(route('sections.destroy', $sec));
        $deleteResponse->assertStatus(403);
    }

    public function test_teachers_remain_unauthorized_for_sections(): void
    {
        $class = SchoolClass::create(['name' => 'Class TeacherTest_' . uniqid(), 'is_active' => true]);
        $sec = Section::create(['academic_year_id' => $this->activeYear->id, 'class_id' => $class->id, 'name' => 'TSec', 'is_active' => true]);

        foreach ([$this->teacher, $this->classTeacher] as $t) {
            $this->actingAs($t)->get(route('sections.index'))->assertStatus(403);
            $this->actingAs($t)->post(route('sections.store'), ['class_id' => $class->id, 'name' => 'TAttempt'])->assertStatus(403);
            $this->actingAs($t)->put(route('sections.update', $sec), ['name' => 'TRenamed', 'is_active' => 1])->assertStatus(403);
            $this->actingAs($t)->delete(route('sections.destroy', $sec))->assertStatus(403);
        }
    }

    public function test_historical_section_records_and_relationships_remain_intact(): void
    {
        $pastYear = AcademicYear::create([
            'name' => 'AY_Hist_' . rand(1000, 9999),
            'start_date' => '2024-06-01',
            'end_date' => '2025-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $class = SchoolClass::create(['name' => 'Class Hist_' . uniqid(), 'is_active' => true]);

        $histSec = Section::create([
            'academic_year_id' => $pastYear->id,
            'class_id' => $class->id,
            'name' => 'HistSecA',
            'is_active' => true,
        ]);

        // Creating and updating active year sections
        $activeSec = Section::create([
            'academic_year_id' => $this->activeYear->id,
            'class_id' => $class->id,
            'name' => 'ActiveSecA',
            'is_active' => true,
        ]);

        $this->actingAs($this->officeStaff)->put(route('sections.update', $activeSec), [
            'name' => 'ActiveSecA-Mod',
            'is_active' => 1,
        ]);

        $histSec->refresh();
        $this->assertSame($pastYear->id, $histSec->academic_year_id);
        $this->assertSame($class->id, $histSec->class_id);
        $this->assertSame('HistSecA', $histSec->name);
    }
}
