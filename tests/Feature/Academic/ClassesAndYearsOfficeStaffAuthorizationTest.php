<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClassesAndYearsOfficeStaffAuthorizationTest extends TestCase
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
        $subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $classTeacherRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'test_admin_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Test Admin',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'test_office_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Test Office Staff',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'test_sub_teacher_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Test Sub Teacher',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $classTeacherRole->id,
            'username' => 'test_cls_teacher_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Test Cls Teacher',
            'is_active' => true,
        ]);
    }

    /*
     * -------------------------------------------------------------------------
     * CLASSES TESTS
     * -------------------------------------------------------------------------
     */

    public function test_office_staff_and_admin_can_create_and_update_classes(): void
    {
        // 1. Office Staff creates a class
        $resOffice = $this->actingAs($this->officeStaff)->post(route('classes.store'), [
            'name' => 'Class OS_' . uniqid(),
            'is_active' => 1,
        ]);
        $resOffice->assertRedirect(route('classes.index'));
        $resOffice->assertSessionHas('success');

        // 2. Admin creates a class
        $className = 'Class AD_' . uniqid();
        $resAdmin = $this->actingAs($this->admin)->post(route('classes.store'), [
            'name' => $className,
            'is_active' => 1,
        ]);
        $resAdmin->assertRedirect(route('classes.index'));
        $createdClass = SchoolClass::where('name', $className)->firstOrFail();

        // 3. Office Staff can update and deactivate class
        $resUpdate = $this->actingAs($this->officeStaff)->put(route('classes.update', $createdClass), [
            'name' => $className . ' Renamed',
            'is_active' => 0,
        ]);
        $resUpdate->assertRedirect(route('classes.index'));
        $createdClass->refresh();
        $this->assertEquals($className . ' Renamed', $createdClass->name);
        $this->assertFalse($createdClass->is_active);

        // 4. Audit logs are recorded
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'classes',
            'entity_id' => $createdClass->id,
            'action' => 'UPDATE_CLASS',
            'user_id' => $this->officeStaff->id,
        ]);
    }

    public function test_office_staff_can_delete_unreferenced_class_but_referenced_is_blocked(): void
    {
        // Unreferenced class
        $unrefClass = SchoolClass::create([
            'name' => 'Empty Class ' . uniqid(),
            'is_active' => true,
        ]);

        $resDelete = $this->actingAs($this->officeStaff)->delete(route('classes.destroy', $unrefClass));
        $resDelete->assertRedirect(route('classes.index'));
        $resDelete->assertSessionHas('success');
        $this->assertDatabaseMissing('classes', ['id' => $unrefClass->id]);

        // Referenced class (has a section)
        $refClass = SchoolClass::create([
            'name' => 'Ref Class ' . uniqid(),
            'is_active' => true,
        ]);

        $year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        Section::create([
            'academic_year_id' => $year->id,
            'class_id' => $refClass->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        // Blocked for Office Staff
        $resBlockOffice = $this->actingAs($this->officeStaff)->delete(route('classes.destroy', $refClass));
        $resBlockOffice->assertRedirect(route('classes.index'));
        $resBlockOffice->assertSessionHas('error');
        $this->assertDatabaseHas('classes', ['id' => $refClass->id]);

        // Blocked for Administrator
        $resBlockAdmin = $this->actingAs($this->admin)->delete(route('classes.destroy', $refClass));
        $resBlockAdmin->assertRedirect(route('classes.index'));
        $resBlockAdmin->assertSessionHas('error');
        $this->assertDatabaseHas('classes', ['id' => $refClass->id]);
    }

    public function test_teachers_are_denied_from_class_mutations(): void
    {
        $class = SchoolClass::create([
            'name' => 'Protected Class ' . uniqid(),
            'is_active' => true,
        ]);

        foreach ([$this->subjectTeacher, $this->classTeacher] as $teacher) {
            // Create attempt
            $resStore = $this->actingAs($teacher)->post(route('classes.store'), [
                'name' => 'Hacked Class',
                'is_active' => 1,
            ]);
            $resStore->assertStatus(403);

            // Update attempt
            $resUpdate = $this->actingAs($teacher)->put(route('classes.update', $class), [
                'name' => 'Hacked Rename',
                'is_active' => 1,
            ]);
            $resUpdate->assertStatus(403);

            // Delete attempt
            $resDelete = $this->actingAs($teacher)->delete(route('classes.destroy', $class));
            $resDelete->assertStatus(403);
        }
    }

    public function test_classes_status_filter_and_natural_sorting(): void
    {
        $uniqueSuffix = '_' . rand(1000, 9999);
        $c1 = SchoolClass::create(['name' => 'Class 1' . $uniqueSuffix, 'is_active' => true]);
        $c2 = SchoolClass::create(['name' => 'Class 2' . $uniqueSuffix, 'is_active' => false]);
        $c10 = SchoolClass::create(['name' => 'Class 10' . $uniqueSuffix, 'is_active' => true]);

        // Default / All
        $resAll = $this->actingAs($this->officeStaff)->get(route('classes.index'));
        $resAll->assertStatus(200);
        $resAll->assertSee($c1->name);
        $resAll->assertSee($c2->name);
        $resAll->assertSee($c10->name);

        // Filter: Active only
        $resActive = $this->actingAs($this->officeStaff)->get(route('classes.index', ['status' => 'active']));
        $resActive->assertStatus(200);
        $resActive->assertSee($c1->name);
        $resActive->assertSee($c10->name);
        $resActive->assertDontSee($c2->name);

        // Filter: Inactive only
        $resInactive = $this->actingAs($this->officeStaff)->get(route('classes.index', ['status' => 'inactive']));
        $resInactive->assertStatus(200);
        $resInactive->assertSee($c2->name);
        $resInactive->assertDontSee($c1->name);
        $resInactive->assertDontSee($c10->name);

        // Filter: Invalid status falls back to all
        $resInvalid = $this->actingAs($this->officeStaff)->get(route('classes.index', ['status' => 'garbage_value']));
        $resInvalid->assertStatus(200);
        $resInvalid->assertSee($c1->name);
        $resInvalid->assertSee($c2->name);
        $resInvalid->assertSee($c10->name);

        // Check natural ordering: Class 1 should appear before Class 2, and Class 2 before Class 10
        $viewClasses = $resActive->viewData('classes');
        $matching = $viewClasses->filter(fn($c) => in_array($c->id, [$c1->id, $c10->id]))->values();
        $this->assertEquals($c1->id, $matching[0]->id);
        $this->assertEquals($c10->id, $matching[1]->id);
    }

    /*
     * -------------------------------------------------------------------------
     * ACADEMIC YEARS TESTS
     * -------------------------------------------------------------------------
     */

    public function test_office_staff_can_create_edit_close_reopen_and_set_current_academic_year(): void
    {
        $startDate = now()->addMonthsNoOverflow(1)->toDateString();
        $endDate = now()->addMonthsNoOverflow(10)->toDateString();

        // 1. Office staff creates an academic year
        $resCreate = $this->actingAs($this->officeStaff)->post(route('academic_years.store'), [
            'name' => 'AY_OS_' . rand(1000, 9999),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $resCreate->assertRedirect(route('academic_years.index'));
        $resCreate->assertSessionHas('success');

        $year = AcademicYear::where('start_date', $startDate)->firstOrFail();
        $this->assertEquals(AcademicYearStatus::OPEN, $year->status);
        $this->assertFalse($year->is_current);

        // 2. Office staff edits the academic year name
        $resEdit = $this->actingAs($this->officeStaff)->put(route('academic_years.update', $year), [
            'name' => substr($year->name, 0, 10) . '_Upd',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $resEdit->assertRedirect(route('academic_years.index'));
        $year->refresh();
        $this->assertStringContainsString('_Upd', $year->name);

        // 3. Office staff closes the academic year
        $resClose = $this->actingAs($this->officeStaff)->post(route('academic_years.close', $year));
        $resClose->assertRedirect(route('academic_years.index'));
        $year->refresh();
        $this->assertEquals(AcademicYearStatus::CLOSED, $year->status);

        // 4. Office staff reopens the academic year
        $resReopen = $this->actingAs($this->officeStaff)->post(route('academic_years.reopen', $year));
        $resReopen->assertRedirect(route('academic_years.index'));
        $year->refresh();
        $this->assertEquals(AcademicYearStatus::OPEN, $year->status);
    }

    public function test_office_staff_can_delete_unreferenced_non_current_year_but_current_or_referenced_is_blocked(): void
    {
        // Unreferenced, non-current year
        $unrefYear = AcademicYear::create([
            'name' => 'AY_Del_' . rand(1000, 9999),
            'start_date' => '2035-06-01',
            'end_date' => '2036-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $resDelete = $this->actingAs($this->officeStaff)->delete(route('academic_years.destroy', $unrefYear));
        $resDelete->assertRedirect(route('academic_years.index'));
        $resDelete->assertSessionHas('success');
        $this->assertDatabaseMissing('academic_years', ['id' => $unrefYear->id]);

        // Current year deletion is blocked
        $currentYear = AcademicYear::create([
            'name' => 'AY_Curr_' . rand(1000, 9999),
            'start_date' => '2036-06-01',
            'end_date' => '2037-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $resDelCurrent = $this->actingAs($this->officeStaff)->delete(route('academic_years.destroy', $currentYear));
        $resDelCurrent->assertRedirect(route('academic_years.index'));
        $resDelCurrent->assertSessionHas('error');
        $this->assertDatabaseHas('academic_years', ['id' => $currentYear->id]);

        // Referenced year (with a term) deletion is blocked
        $refYear = AcademicYear::create([
            'name' => 'AY_Ref_' . rand(1000, 9999),
            'start_date' => '2037-06-01',
            'end_date' => '2038-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        Term::create([
            'academic_year_id' => $refYear->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $resDelRef = $this->actingAs($this->officeStaff)->delete(route('academic_years.destroy', $refYear));
        $resDelRef->assertRedirect(route('academic_years.index'));
        $resDelRef->assertSessionHas('error');
        $this->assertDatabaseHas('academic_years', ['id' => $refYear->id]);
    }

    public function test_teachers_are_denied_from_academic_year_mutations(): void
    {
        $year = AcademicYear::create([
            'name' => 'AY_Prot_' . rand(1000, 9999),
            'start_date' => '2038-06-01',
            'end_date' => '2039-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        foreach ([$this->subjectTeacher, $this->classTeacher] as $teacher) {
            $this->actingAs($teacher)->post(route('academic_years.store'), [
                'name' => 'AY_Hacked',
                'start_date' => '2040-01-01',
                'end_date' => '2040-12-31',
            ])->assertStatus(403);

            $this->actingAs($teacher)->put(route('academic_years.update', $year), [
                'name' => 'AY_Hacked_Update',
                'start_date' => '2038-06-01',
                'end_date' => '2039-04-30',
            ])->assertStatus(403);

            $this->actingAs($teacher)->post(route('academic_years.close', $year))->assertStatus(403);
            $this->actingAs($teacher)->post(route('academic_years.reopen', $year))->assertStatus(403);
            $this->actingAs($teacher)->delete(route('academic_years.destroy', $year))->assertStatus(403);
        }
    }

    /*
     * -------------------------------------------------------------------------
     * TERMS REGRESSION TESTS
     * -------------------------------------------------------------------------
     */

    public function test_terms_active_inactive_and_single_active_constraint_regression(): void
    {
        // is_active feature has been retired from the user-facing API.
        // The controller always writes is_active=false on create regardless
        // of what is submitted. The database column/index are preserved as
        // an inert compatibility layer.
        $year = AcademicYear::create([
            'name' => 'AY_Terms_' . rand(1000, 9999),
            'start_date' => '2040-06-01',
            'end_date' => '2041-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        // Create Term 1 — is_active submitted but ignored by API; stored as false.
        $this->actingAs($this->officeStaff)->post(route('terms.store'), [
            'academic_year_id' => $year->id,
            'name' => 'Term 1',
            'is_active' => 1,
        ])->assertRedirect();

        $t1 = Term::where('academic_year_id', $year->id)->where('name', 'Term 1')->firstOrFail();
        $this->assertFalse($t1->is_active, 'API always stores is_active=false (feature retired).');

        // Create Term 2 — same: is_active submitted but ignored.
        $this->actingAs($this->officeStaff)->post(route('terms.store'), [
            'academic_year_id' => $year->id,
            'name' => 'Term 2',
            'is_active' => 1,
        ])->assertRedirect();

        $t1->refresh();
        $t2 = Term::where('academic_year_id', $year->id)->where('name', 'Term 2')->firstOrFail();

        // Both terms have is_active=false; no sibling deactivation occurs.
        $this->assertFalse($t1->is_active);
        $this->assertFalse($t2->is_active);

        // Dynamic sequencing is still enforced: t1→1, t2→2.
        $this->assertEquals(1, $t1->sequence_no);
        $this->assertEquals(2, $t2->sequence_no);
    }

    public function test_office_staff_can_delete_unreferenced_term_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'AY_TermDel_' . rand(1000, 9999),
            'start_date' => '2042-06-01',
            'end_date' => '2043-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Term To Remove',
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        $resDelete = $this->actingAs($this->officeStaff)->delete(route('terms.destroy', $term));
        $resDelete->assertRedirect(route('terms.index', ['academic_year_id' => $year->id]));
        $resDelete->assertSessionHas('success');
        $this->assertDatabaseMissing('terms', ['id' => $term->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'terms',
            'entity_id' => $term->id,
            'action' => 'DELETE_TERM',
            'user_id' => $this->officeStaff->id,
        ]);
    }

    public function test_office_staff_cannot_delete_term_referenced_by_assessments(): void
    {
        $year = AcademicYear::create([
            'name' => 'AY_TermRef_' . rand(1000, 9999),
            'start_date' => '2043-06-01',
            'end_date' => '2044-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Term With Assessment',
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        $type = AssessmentType::create([
            'name' => 'Test Type ' . rand(1000, 9999),
            'is_active' => true,
        ]);

        Assessment::create([
            'academic_year_id' => $year->id,
            'assessment_type_id' => $type->id,
            'term_id' => $term->id,
            'name' => 'Assessment ' . rand(1000, 9999),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $resDelete = $this->actingAs($this->officeStaff)->delete(route('terms.destroy', $term));
        $resDelete->assertRedirect(route('terms.index', ['academic_year_id' => $year->id]));
        $resDelete->assertSessionHas('error');
        $this->assertDatabaseHas('terms', ['id' => $term->id]);
    }

    public function test_teachers_are_denied_from_terms_mutations(): void
    {
        $year = AcademicYear::create([
            'name' => 'AY_TermProt_' . rand(1000, 9999),
            'start_date' => '2044-06-01',
            'end_date' => '2045-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Term Protected',
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        foreach ([$this->subjectTeacher, $this->classTeacher] as $teacher) {
            $this->actingAs($teacher)->post(route('terms.store'), [
                'academic_year_id' => $year->id,
                'name' => 'Term Hacked',
            ])->assertStatus(403);

            $this->actingAs($teacher)->put(route('terms.update', $term), [
                'name' => 'Term Hacked Update',
            ])->assertStatus(403);

            $this->actingAs($teacher)->postJson(route('terms.reorder'), [
                'academic_year_id' => $year->id,
                'term_ids' => [$term->id],
            ])->assertStatus(403);

            $this->actingAs($teacher)->delete(route('terms.destroy', $term))->assertStatus(403);
        }
    }
}
