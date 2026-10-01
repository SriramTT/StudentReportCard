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

class ClassLevelAssessmentApplicabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected AcademicYear $year;
    protected SchoolClass $class;
    protected Section $secA;
    protected Section $secB;
    protected Subject $math;
    protected Subject $science;
    protected Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertEquals(
            'school_report_card_audit',
            DB::connection()->getDatabaseName(),
            'CRITICAL: Tests must run only on school_report_card_audit'
        );

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $officeRole = Role::firstOrCreate(['name' => 'Office Staff']);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Class Level Test School',
            'pass_mark' => 40.00,
        ]);

        $this->admin = User::forceCreate([
            'username' => 'cl_admin_' . uniqid(),
            'display_name' => 'CL Admin',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'username' => 'cl_office_' . uniqid(),
            'display_name' => 'CL Office',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $officeRole->id,
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(10000, 99999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create(['name' => 'Grade ' . rand(1, 12), 'is_active' => true]);

        $this->secA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Sec-A',
            'is_active' => true,
        ]);

        $this->secB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Sec-B',
            'is_active' => true,
        ]);

        $this->math = Subject::create(['name' => 'Math_' . uniqid(), 'code' => 'M_' . rand(100, 999), 'is_active' => true]);
        $this->science = Subject::create(['name' => 'Science_' . uniqid(), 'code' => 'S_' . rand(100, 999), 'is_active' => true]);

        // Map subjects to this class for both sections
        ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->secA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);
        ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->secA->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);
        ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->secB->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);
        ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->secB->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $term = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $type = AssessmentType::create([
            'name' => 'Midterm_' . uniqid(),
            'is_active' => true,
        ]);

        $this->assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $term->id,
            'assessment_type_id' => $type->id,
            'name' => 'Class Level Assessment_' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);
    }

    public function test_class_level_applicability_configuration_applies_to_all_sections_of_class(): void
    {
        // When user configures assessment applicability at class level
        $response = $this->actingAs($this->officeStaff)->post(
            route('assessments.applicability.store', $this->assessment),
            [
                'class_id' => $this->class->id,
                'subject_maximum_marks' => [
                    $this->math->id => '80.00',
                    $this->science->id => '75.00',
                ],
                'is_active' => '1',
            ]
        );

        $response->assertRedirect(route('assessments.applicability.index', $this->assessment));
        $response->assertSessionHas('success');

        // Both sections (Sec-A and Sec-B) must have applicability rows for Math and Science
        $secAMath = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $this->secA->id)->where('subject_id', $this->math->id))
            ->first();
        $this->assertNotNull($secAMath);
        $this->assertEquals('80.00', $secAMath->maximum_marks);

        $secAScience = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $this->secA->id)->where('subject_id', $this->science->id))
            ->first();
        $this->assertNotNull($secAScience);
        $this->assertEquals('75.00', $secAScience->maximum_marks);

        $secBMath = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $this->secB->id)->where('subject_id', $this->math->id))
            ->first();
        $this->assertNotNull($secBMath);
        $this->assertEquals('80.00', $secBMath->maximum_marks);

        $secBScience = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $this->secB->id)->where('subject_id', $this->science->id))
            ->first();
        $this->assertNotNull($secBScience);
        $this->assertEquals('75.00', $secBScience->maximum_marks);
    }

    public function test_new_section_creation_auto_propagates_assessment_applicability_when_no_marks_exist(): void
    {
        // 1. Configure assessment at class level
        $this->actingAs($this->officeStaff)->post(
            route('assessments.applicability.store', $this->assessment),
            [
                'class_id' => $this->class->id,
                'subject_maximum_marks' => [
                    $this->math->id => '100.00',
                    $this->science->id => '50.00',
                ],
                'is_active' => '1',
            ]
        );

        // Verify initial count is 4 (2 subjects * 2 sections)
        $this->assertEquals(4, AssessmentApplicability::where('assessment_id', $this->assessment->id)->count());

        // 2. Create a new section (Sec-C) under this class via SectionController
        $resSection = $this->actingAs($this->admin)->post(route('sections.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Sec-C',
            'is_active' => '1',
        ]);
        $resSection->assertRedirect();

        $secC = Section::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('name', 'Sec-C')
            ->first();
        $this->assertNotNull($secC);

        // 3. Since the assessment has NO recorded marks, it must automatically propagate applicability to Sec-C
        $secCMath = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $secC->id)->where('subject_id', $this->math->id))
            ->first();
        $this->assertNotNull($secCMath, 'Sec-C should have inherited Math applicability');
        $this->assertEquals('100.00', $secCMath->maximum_marks);

        $secCScience = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $secC->id)->where('subject_id', $this->science->id))
            ->first();
        $this->assertNotNull($secCScience, 'Sec-C should have inherited Science applicability');
        $this->assertEquals('50.00', $secCScience->maximum_marks);

        // Total applicability should now be 6 (2 subjects * 3 sections)
        $this->assertEquals(6, AssessmentApplicability::where('assessment_id', $this->assessment->id)->count());
    }

    public function test_freeze_rule_prevents_propagation_to_new_sections_once_marks_exist(): void
    {
        // 1. Configure assessment at class level
        $this->actingAs($this->officeStaff)->post(
            route('assessments.applicability.store', $this->assessment),
            [
                'class_id' => $this->class->id,
                'subject_maximum_marks' => [
                    $this->math->id => '100.00',
                ],
                'is_active' => '1',
            ]
        );

        $secAMathApp = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $this->secA->id)->where('subject_id', $this->math->id))
            ->first();
        $this->assertNotNull($secAMathApp);

        // 2. Record a student mark against this assessment applicability
        $student = Student::create([
            'admission_number' => 'ADM_FRZ_' . uniqid(),
            'student_name' => 'Freeze Rule Student',
        ]);
        $record = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->secA->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);
        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $record->id,
            'class_subject_id' => $secAMathApp->class_subject_id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
        Mark::create([
            'student_academic_record_id' => $record->id,
            'student_subject_allocation_id' => $allocation->id,
            'assessment_applicability_id' => $secAMathApp->id,
            'mark_value' => '95.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        // 3. Now create a new section (Sec-D) under this class
        $resSection = $this->actingAs($this->admin)->post(route('sections.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'Sec-D',
            'is_active' => '1',
        ]);
        $resSection->assertRedirect();

        $secD = Section::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->where('name', 'Sec-D')
            ->first();
        $this->assertNotNull($secD);

        // 4. CRITICAL FREEZE RULE ASSERTION:
        // Because marks exist for this assessment, the historical applicability is frozen.
        // Sec-D MUST NOT receive assessment applicability!
        $secDMath = AssessmentApplicability::where('assessment_id', $this->assessment->id)
            ->whereHas('classSubject', fn($q) => $q->where('section_id', $secD->id))
            ->first();

        $this->assertNull($secDMath, 'CRITICAL: Assessment applicability must NOT propagate to Sec-D because marks exist');
    }

    public function test_add_applicability_button_and_modal_rendered_for_authorized_users(): void
    {
        $response = $this->actingAs($this->officeStaff)->get(route('assessments.applicability.index', $this->assessment));
        $response->assertStatus(200);

        // Header button
        $response->assertSee('id="btn-add-applicability"', false);
        $response->assertSee('+ Add Class Applicability');
        $response->assertSee("openModal('create-applicability-modal')", false);

        // Modal container with standard backdrop and dialog
        $response->assertSee('id="create-applicability-modal"', false);
        $response->assertSee('class="modal-backdrop"', false);
        $response->assertSee('class="modal-dialog modal-dialog--md"', false);
        $response->assertSee('Add Class Assessment Applicability');
        $response->assertSee('id="applicability_class_id"', false);
        $response->assertSee('id="class_sections_banner"', false);
        $response->assertSee('id="class_subjects_container"', false);
        $response->assertSee('id="btn_submit_applicability"', false);
    }

    public function test_unauthorized_teacher_cannot_see_modal_or_button_and_cannot_create_applicability(): void
    {
        $teacherRole = Role::firstOrCreate(['name' => 'Subject Teacher']);
        $teacher = User::forceCreate([
            'username' => 'app_teacher_' . uniqid(),
            'display_name' => 'App Teacher',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);

        // Teachers are forbidden from accessing assessment applicability
        $response = $this->actingAs($teacher)->get(route('assessments.applicability.index', $this->assessment));
        $response->assertStatus(403);

        // Teacher cannot post applicability
        $postRes = $this->actingAs($teacher)->post(route('assessments.applicability.store', $this->assessment), [
            'class_id' => $this->class->id,
            'subject_maximum_marks' => [$this->math->id => '50.00'],
            'is_active' => '1',
        ]);
        $postRes->assertStatus(403);
    }

    public function test_validation_errors_trigger_modal_reopen_script(): void
    {
        $response = $this->actingAs($this->officeStaff)
            ->from(route('assessments.applicability.index', $this->assessment))
            ->post(route('assessments.applicability.store', $this->assessment), [
                '_form_context' => 'create_applicability',
                'class_id' => '', // missing required class_id
            ]);

        $response->assertRedirect(route('assessments.applicability.index', $this->assessment));
        $response->assertSessionHasErrors(['class_subject_ids']);

        // Following redirect with session errors
        $followRes = $this->actingAs($this->officeStaff)
            ->withSession(['_flash' => ['old' => ['_form_context' => 'create_applicability']], 'errors' => session('errors')])
            ->get(route('assessments.applicability.index', $this->assessment));

        $followRes->assertStatus(200);
        $followRes->assertSee("openModal('create-applicability-modal')", false);
    }

    public function test_filter_classes_and_modal_classes_are_rendered_in_natural_ascending_order(): void
    {
        // Create classes out of order to ensure natural sorting is verified
        $c10 = SchoolClass::firstOrCreate(['name' => 'Class 10'], ['is_active' => true]);
        $c2 = SchoolClass::firstOrCreate(['name' => 'Class 2'], ['is_active' => true]);
        $c1 = SchoolClass::firstOrCreate(['name' => 'Class 1'], ['is_active' => true]);
        $c11 = SchoolClass::firstOrCreate(['name' => 'Class 11'], ['is_active' => true]);
        $c9 = SchoolClass::firstOrCreate(['name' => 'Class 9'], ['is_active' => true]);

        // Attach at least one section for this academic year to test modal sections data attribute
        Section::firstOrCreate(
            ['academic_year_id' => $this->assessment->academic_year_id, 'class_id' => $c1->id, 'name' => 'A'],
            ['is_active' => true]
        );

        $response = $this->actingAs($this->admin)->get(route('assessments.applicability.index', $this->assessment));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Verify in Filter dropdown (#filter_class_id)
        $filterPos = strpos($content, 'id="filter_class_id"');
        $this->assertNotFalse($filterPos);
        $filterEndPos = strpos($content, '</select>', $filterPos);
        $filterHtml = substr($content, $filterPos, $filterEndPos - $filterPos);

        $posC1 = strpos($filterHtml, 'value="' . $c1->id . '"');
        $posC2 = strpos($filterHtml, 'value="' . $c2->id . '"');
        $posC9 = strpos($filterHtml, 'value="' . $c9->id . '"');
        $posC10 = strpos($filterHtml, 'value="' . $c10->id . '"');
        $posC11 = strpos($filterHtml, 'value="' . $c11->id . '"');

        $this->assertNotFalse($posC1, 'Class 1 not found in filter dropdown');
        $this->assertNotFalse($posC2, 'Class 2 not found in filter dropdown');
        $this->assertNotFalse($posC9, 'Class 9 not found in filter dropdown');
        $this->assertNotFalse($posC10, 'Class 10 not found in filter dropdown');
        $this->assertNotFalse($posC11, 'Class 11 not found in filter dropdown');

        $this->assertTrue($posC1 < $posC2, 'Filter: Class 1 must appear before Class 2');
        $this->assertTrue($posC2 < $posC9, 'Filter: Class 2 must appear before Class 9');
        $this->assertTrue($posC9 < $posC10, 'Filter: Class 9 must appear before Class 10');
        $this->assertTrue($posC10 < $posC11, 'Filter: Class 10 must appear before Class 11');

        // 2. Verify in Modal dropdown (#applicability_class_id)
        $modalPos = strpos($content, 'id="applicability_class_id"');
        $this->assertNotFalse($modalPos);
        $modalEndPos = strpos($content, '</select>', $modalPos);
        $modalHtml = substr($content, $modalPos, $modalEndPos - $modalPos);

        $mPosC1 = strpos($modalHtml, 'value="' . $c1->id . '"');
        $mPosC2 = strpos($modalHtml, 'value="' . $c2->id . '"');
        $mPosC9 = strpos($modalHtml, 'value="' . $c9->id . '"');
        $mPosC10 = strpos($modalHtml, 'value="' . $c10->id . '"');
        $mPosC11 = strpos($modalHtml, 'value="' . $c11->id . '"');

        $this->assertNotFalse($mPosC1, 'Class 1 not found in modal dropdown');
        $this->assertNotFalse($mPosC2, 'Class 2 not found in modal dropdown');
        $this->assertNotFalse($mPosC9, 'Class 9 not found in modal dropdown');
        $this->assertNotFalse($mPosC10, 'Class 10 not found in modal dropdown');
        $this->assertNotFalse($mPosC11, 'Class 11 not found in modal dropdown');

        $this->assertTrue($mPosC1 < $mPosC2, 'Modal: Class 1 must appear before Class 2');
        $this->assertTrue($mPosC2 < $mPosC9, 'Modal: Class 2 must appear before Class 9');
        $this->assertTrue($mPosC9 < $mPosC10, 'Modal: Class 9 must appear before Class 10');
        $this->assertTrue($mPosC10 < $mPosC11, 'Modal: Class 10 must appear before Class 11');

        // 3. Verify modal data attributes and IDs remain intact
        $this->assertStringContainsString('value="' . $c1->id . '"', $modalHtml);
        $this->assertStringContainsString('data-sections=', $modalHtml);
        $this->assertStringContainsString('data-subjects=', $modalHtml);
    }
}

