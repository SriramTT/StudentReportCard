<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentApplicabilityImprovementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected AcademicYear $year;
    protected SchoolClass $classA;
    protected SchoolClass $classB;
    protected Section $secA1;
    protected Section $secA2;
    protected Section $secB1;
    protected Subject $math;
    protected Subject $science;
    protected Subject $english;
    protected Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety assertion: confirm connected to isolated audit test database
        $this->assertEquals(
            'school_report_card_audit',
            DB::connection()->getDatabaseName(),
            'CRITICAL: Tests must run only on school_report_card_audit'
        );

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $officeRole = Role::firstOrCreate(['name' => 'Office Staff']);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Applicability Test School',
            'pass_mark' => 40.00,
        ]);

        $this->admin = User::forceCreate([
            'username' => 'app_admin_' . uniqid(),
            'display_name' => 'App Admin',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'username' => 'app_office_' . uniqid(),
            'display_name' => 'App Office',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $officeRole->id,
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . uniqid(),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->classA = SchoolClass::create(['name' => 'Class Alpha_' . uniqid(), 'is_active' => true]);
        $this->classB = SchoolClass::create(['name' => 'Class Beta_' . uniqid(), 'is_active' => true]);

        $this->secA1 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'name' => 'A1',
            'is_active' => true,
        ]);

        $this->secA2 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'name' => 'A2',
            'is_active' => true,
        ]);

        $this->secB1 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'name' => 'B1',
            'is_active' => true,
        ]);

        $this->math = Subject::create(['name' => 'Math_' . uniqid(), 'code' => 'M_' . rand(100, 999), 'is_active' => true]);
        $this->science = Subject::create(['name' => 'Science_' . uniqid(), 'code' => 'S_' . rand(100, 999), 'is_active' => true]);
        $this->english = Subject::create(['name' => 'English_' . uniqid(), 'code' => 'E_' . rand(100, 999), 'is_active' => true]);

        $term = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $type = AssessmentType::create([
            'name' => 'Unit Exam_' . uniqid(),
            'is_active' => true,
        ]);

        $this->assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $term->id,
            'assessment_type_id' => $type->id,
            'name' => 'Midterm Assessment_' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);
    }

    public function test_filter_by_class_narrows_applicability_rows(): void
    {
        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $cs2 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->secB1->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs1->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs2->id,
            'maximum_marks' => 75.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->get(route('assessments.applicability.index', [
            'assessment' => $this->assessment,
            'class_id' => $this->classA->id,
        ]));

        $response->assertStatus(200);
        $apps = $response->viewData('applicabilities');
        $this->assertEquals(1, $apps->count());
        $this->assertEquals($this->classA->id, $apps->first()->classSubject->class_id);
        $this->assertEquals($this->math->id, $apps->first()->classSubject->subject_id);
    }

    public function test_filter_by_section_and_status_narrows_applicability_rows(): void
    {
        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $cs2 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA2->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs1->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs2->id,
            'maximum_marks' => 75.00,
            'is_active' => false,
        ]);

        // Filter by section A1
        $resSec = $this->actingAs($this->officeStaff)->get(route('assessments.applicability.index', [
            'assessment' => $this->assessment,
            'section_id' => $this->secA1->id,
        ]));
        $resSec->assertStatus(200);
        $appsSec = $resSec->viewData('applicabilities');
        $this->assertEquals(1, $appsSec->count());
        $this->assertEquals($this->secA1->id, $appsSec->first()->classSubject->section_id);

        // Filter by status active
        $resActive = $this->actingAs($this->officeStaff)->get(route('assessments.applicability.index', [
            'assessment' => $this->assessment,
            'status' => 'active',
        ]));
        $resActive->assertStatus(200);
        $appsActive = $resActive->viewData('applicabilities');
        $this->assertEquals(1, $appsActive->count());
        $this->assertTrue($appsActive->first()->is_active);

        // Filter by status inactive
        $resInactive = $this->actingAs($this->officeStaff)->get(route('assessments.applicability.index', [
            'assessment' => $this->assessment,
            'status' => 'inactive',
        ]));
        $resInactive->assertStatus(200);
        $appsInactive = $resInactive->viewData('applicabilities');
        $this->assertEquals(1, $appsInactive->count());
        $this->assertFalse($appsInactive->first()->is_active);
    }


    public function test_dependent_sections_filter_options_scope_to_selected_class(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.applicability.index', [
            'assessment' => $this->assessment,
            'class_id' => $this->classA->id,
        ]));

        $response->assertStatus(200);
        $viewSections = $response->viewData('filterSections');
        $this->assertTrue($viewSections->contains('id', $this->secA1->id));
        $this->assertTrue($viewSections->contains('id', $this->secA2->id));
        $this->assertFalse($viewSections->contains('id', $this->secB1->id));
    }

    public function test_table_filtering_never_leaks_already_mapped_subjects_in_add_panel(): void
    {
        $csA = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $csB = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->secB1->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        // Map csB to assessment
        AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $csB->id,
            'maximum_marks' => 60.00,
            'is_active' => true,
        ]);

        // Now filter the table by Class A (so csB is hidden from table)
        $response = $this->actingAs($this->officeStaff)->get(route('assessments.applicability.index', [
            'assessment' => $this->assessment,
            'class_id' => $this->classA->id,
        ]));

        $availableClassSubjects = $response->viewData('availableClassSubjects');

        // csA is unmapped, so it must be available
        $this->assertTrue($availableClassSubjects->contains('id', $csA->id));
        // csB is mapped (even though hidden from filtered table), so it MUST NOT be available!
        $this->assertFalse($availableClassSubjects->contains('id', $csB->id));
    }

    public function test_batch_store_creates_all_mappings_with_individual_maximum_marks(): void
    {
        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $cs2 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $this->assessment), [
            'class_subject_ids' => [$cs1->id, $cs2->id],
            'maximum_marks' => [
                $cs1->id => '50.00',
                $cs2->id => '75.50',
            ],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('assessments.applicability.index', $this->assessment));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('assessment_applicability', [
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs1->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('assessment_applicability', [
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs2->id,
            'maximum_marks' => 75.50,
            'is_active' => true,
        ]);

        // Verify audit logs
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'CREATE_ASSESSMENT_APPLICABILITY',
            'entity_type' => 'assessment_applicability',
        ]);
    }

    public function test_batch_store_rejects_duplicate_ids_or_different_academic_year(): void
    {
        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        // 1. Duplicate IDs
        $resDup = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $this->assessment), [
            'class_subject_ids' => [$cs1->id, $cs1->id],
            'maximum_marks' => [
                $cs1->id => '50.00',
            ],
        ]);
        $resDup->assertSessionHasErrors('class_subject_ids');

        // 2. Different academic year
        $otherYear = AcademicYear::create([
            'name' => 'O_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);
        $csOther = ClassSubject::create([
            'academic_year_id' => $otherYear->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->english->id,
            'subject_name_snapshot' => $this->english->name,
            'is_active' => true,
        ]);

        $resOtherYear = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $this->assessment), [
            'class_subject_ids' => [$csOther->id],
            'maximum_marks' => [
                $csOther->id => '50.00',
            ],
        ]);
        $resOtherYear->assertSessionHasErrors('class_subject_ids');
    }

    public function test_batch_store_rejects_missing_or_invalid_individual_maximum_marks(): void
    {
        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        // Zero maximum marks
        $resZero = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $this->assessment), [
            'class_subject_ids' => [$cs1->id],
            'maximum_marks' => [
                $cs1->id => '0',
            ],
        ]);
        $resZero->assertSessionHasErrors("maximum_marks.{$cs1->id}");

        // Negative maximum marks
        $resNeg = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $this->assessment), [
            'class_subject_ids' => [$cs1->id],
            'maximum_marks' => [
                $cs1->id => '-10.5',
            ],
        ]);
        $resNeg->assertSessionHasErrors("maximum_marks.{$cs1->id}");

        // Exceeds 9999.99
        $resMax = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $this->assessment), [
            'class_subject_ids' => [$cs1->id],
            'maximum_marks' => [
                $cs1->id => '10000.00',
            ],
        ]);
        $resMax->assertSessionHasErrors("maximum_marks.{$cs1->id}");
    }

    public function test_remove_action_succeeds_when_no_marks_exist(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $app = AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->delete(
            route('assessments.applicability.destroy', [$this->assessment, $app])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('assessment_applicability', ['id' => $app->id]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'DELETE_ASSESSMENT_APPLICABILITY',
            'entity_id' => $app->id,
        ]);
    }

    public function test_remove_action_is_strictly_blocked_on_backend_when_marks_exist(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $app = AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM_AP_' . uniqid(),
            'student_name' => 'Test Student',
        ]);

        $record = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => rand(1, 99),
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $record->id,
            'class_subject_id' => $cs->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        Mark::create([
            'student_academic_record_id' => $record->id,
            'student_subject_allocation_id' => $allocation->id,
            'assessment_applicability_id' => $app->id,
            'mark_value' => '88.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        // Attempt deletion via direct DELETE request
        $response = $this->actingAs($this->officeStaff)->delete(
            route('assessments.applicability.destroy', [$this->assessment, $app])
        );

        $response->assertSessionHasErrors('applicability');
        $this->assertDatabaseHas('assessment_applicability', ['id' => $app->id]);

        // Verify mark still exists untouched
        $this->assertDatabaseHas('marks', [
            'assessment_applicability_id' => $app->id,
            'mark_value' => '88.00',
        ]);
    }
}
