<?php

namespace Tests\Feature\Marks;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Enums\TeacherAssignmentType;
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
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AssessmentApplicabilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarkEntryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $subjectTeacher;
    protected User $classTeacher;

    protected AcademicYear $year;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $math;
    protected ClassSubject $csMath;
    protected AssessmentType $examType;
    protected Assessment $assessment;
    protected AssessmentApplicability $applicability;

    protected Student $student1;
    protected Student $student2;
    protected StudentAcademicRecord $sar1;
    protected StudentAcademicRecord $sar2;
    protected StudentSubjectAllocation $alloc1;
    protected StudentSubjectAllocation $alloc2;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $stRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $ctRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_mk_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Mark',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_mk_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Mark',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 'st_mk_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Subject Teacher Mark',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'ct_mk_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher Mark',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_MK_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create(['name' => 'Class_MK_' . uniqid(), 'is_active' => true]);
        $this->section = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A_' . uniqid(),
            'is_active' => true,
        ]);

        $this->math = Subject::create([
            'name' => 'Mathematics_' . uniqid(),
            'code' => 'M_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
        ]);

        $this->csMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $this->examType = AssessmentType::create(['name' => 'Midterm_' . uniqid(), 'is_active' => true]);
        $this->assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Midterm Math',
            'assessment_date' => '2026-10-15',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->applicability = AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        // Setup Teacher Assignments
        TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'is_active' => true,
            'effective_from' => '2026-06-01',
        ]);

        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'is_active' => true,
            'effective_from' => '2026-06-01',
        ]);

        // Setup Students & Placements & Allocations
        $this->student1 = Student::create(['admission_number' => 'ADM_MK_1_' . uniqid(), 'student_name' => 'Arun Kumar']);
        $this->student2 = Student::create(['admission_number' => 'ADM_MK_2_' . uniqid(), 'student_name' => 'Bala Suresh']);

        $this->sar1 = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->sar2 = StudentAcademicRecord::create([
            'student_id' => $this->student2->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 2,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->alloc1 = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1->id,
            'class_subject_id' => $this->csMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->alloc2 = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar2->id,
            'class_subject_id' => $this->csMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
    }

    public function test_mark_entry_roster_loads_allocated_active_students_in_roll_order(): void
    {
        $response = $this->actingAs($this->subjectTeacher)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('roster');
        $response->assertSee($this->student1->admission_number);
        $response->assertSee($this->student2->admission_number);
        $response->assertSee('50.00'); // Maximum marks badge
    }

    public function test_numeric_decimal_zero_and_absent_mark_saving_and_semantics(): void
    {
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '18.50',
                ],
                [
                    'student_academic_record_id' => $this->sar2->id,
                    'student_subject_allocation_id' => $this->alloc2->id,
                    'result_status' => 'numeric',
                    'mark_value' => '0.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $payload);
        $response->assertOk();
        $response->assertJson(['success' => true, 'saved' => 2]);

        $mark1 = Mark::where('student_academic_record_id', $this->sar1->id)->firstOrFail();
        $mark2 = Mark::where('student_academic_record_id', $this->sar2->id)->firstOrFail();

        $this->assertEquals('18.50', (string) $mark1->mark_value);
        $this->assertEquals(MarkResultStatus::NUMERIC, $mark1->result_status);

        $this->assertEquals('0.00', (string) $mark2->mark_value);
        $this->assertEquals(MarkResultStatus::NUMERIC, $mark2->result_status);
        $this->assertFalse($mark2->result_status === MarkResultStatus::BLANK, 'Zero must not be blank.');

        // Now update Student 1 to Absent and Student 2 to 45.75
        $updatePayload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'absent',
                    'mark_value' => null,
                ],
                [
                    'student_academic_record_id' => $this->sar2->id,
                    'student_subject_allocation_id' => $this->alloc2->id,
                    'result_status' => 'numeric',
                    'mark_value' => '45.75',
                ],
            ],
        ];

        $respUpdate = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $updatePayload);
        $respUpdate->assertOk();

        $mark1->refresh();
        $mark2->refresh();

        $this->assertEquals(MarkResultStatus::ABSENT, $mark1->result_status);
        $this->assertNull($mark1->mark_value, 'Absent mark must have null mark_value per CHECK constraint.');

        $this->assertEquals('45.75', (string) $mark2->mark_value);
        $this->assertEquals(MarkResultStatus::NUMERIC, $mark2->result_status);
    }

    public function test_partial_saving_allows_blank_students_without_coercing_to_zero(): void
    {
        // Only student 1 is entered; student 2 is omitted from submitted batch (partial save)
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '32.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $payload);
        $response->assertOk();

        $this->assertDatabaseHas('marks', [
            'student_academic_record_id' => $this->sar1->id,
            'mark_value' => '32.00',
            'result_status' => 'numeric',
        ]);

        // Student 2 has no mark recorded and is NOT coerced to zero
        $this->assertDatabaseMissing('marks', [
            'student_academic_record_id' => $this->sar2->id,
        ]);
    }

    public function test_clearing_a_mark_sets_status_to_blank_and_is_audited(): void
    {
        // Setup initial mark
        $mark = Mark::create([
            'student_academic_record_id' => $this->sar1->id,
            'student_subject_allocation_id' => $this->alloc1->id,
            'assessment_applicability_id' => $this->applicability->id,
            'mark_value' => '40.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->subjectTeacher->id,
        ]);

        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'blank',
                    'mark_value' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $payload);
        $response->assertOk();

        $mark->refresh();
        $this->assertEquals(MarkResultStatus::BLANK, $mark->result_status);
        $this->assertNull($mark->mark_value);

        // Audit log exists for clearing mark
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->subjectTeacher->id,
            'action' => 'CLEAR_MARK',
            'entity_type' => 'mark',
            'entity_id' => $mark->id,
        ]);
    }

    public function test_mark_greater_than_maximum_marks_is_rejected(): void
    {
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '55.00', // Max is 50.00!
                ],
            ],
        ];

        $response = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('marks.0.mark_value');
    }

    public function test_negative_mark_is_rejected(): void
    {
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '-5.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('marks.0.mark_value');
    }

    public function test_no_op_save_creates_no_duplicate_audit_logs(): void
    {
        // Initial mark
        Mark::create([
            'student_academic_record_id' => $this->sar1->id,
            'student_subject_allocation_id' => $this->alloc1->id,
            'assessment_applicability_id' => $this->applicability->id,
            'mark_value' => '40.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->subjectTeacher->id,
        ]);

        $auditCountBefore = AuditLog::where('entity_type', 'mark')->count();

        // Save same value (40.00)
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '40.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), $payload);
        $response->assertOk();
        $response->assertJson(['saved' => 0, 'unchanged' => 1]);

        $auditCountAfter = AuditLog::where('entity_type', 'mark')->count();
        $this->assertEquals($auditCountBefore, $auditCountAfter, 'No-op save must not generate false audit entries.');
    }

    public function test_latest_authorized_edit_becomes_current_mark_with_immutable_audit_history(): void
    {
        // 1. Subject Teacher enters mark 35
        $this->actingAs($this->subjectTeacher)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '35.00',
                ],
            ],
        ]);

        $mark = Mark::where('student_academic_record_id', $this->sar1->id)->firstOrFail();
        $this->assertEquals('35.00', (string) $mark->mark_value);

        // 2. Class Teacher corrects mark 35 -> 38
        $this->actingAs($this->classTeacher)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '38.00',
                ],
            ],
        ]);

        $mark->refresh();
        $this->assertEquals('38.00', (string) $mark->mark_value);
        $this->assertEquals($this->classTeacher->id, $mark->updated_by_user_id);

        // 3. Administrator corrects mark 38 -> 40
        $this->actingAs($this->admin)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '40.00',
                ],
            ],
        ]);

        $mark->refresh();
        $this->assertEquals('40.00', (string) $mark->mark_value);
        $this->assertEquals($this->admin->id, $mark->updated_by_user_id);

        // Verify immutable audit history has all 3 transitions
        $audits = AuditLog::where('entity_type', 'mark')
            ->where('entity_id', $mark->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(3, $audits);
        $this->assertEquals('CREATE_MARK', $audits[0]->action);
        $this->assertEquals($this->subjectTeacher->id, $audits[0]->user_id);

        $this->assertEquals('UPDATE_MARK', $audits[1]->action);
        $this->assertEquals($this->classTeacher->id, $audits[1]->user_id);

        $this->assertEquals('UPDATE_MARK', $audits[2]->action);
        $this->assertEquals($this->admin->id, $audits[2]->user_id);
    }

    public function test_assessment_applicability_maximum_marks_cannot_be_reduced_below_existing_mark(): void
    {
        // Record mark = 45.00 against applicability with max = 50.00
        Mark::create([
            'student_academic_record_id' => $this->sar1->id,
            'student_subject_allocation_id' => $this->alloc1->id,
            'assessment_applicability_id' => $this->applicability->id,
            'mark_value' => '45.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        $appService = app(AssessmentApplicabilityService::class);

        // Attempt to reduce max marks to 40.00 (below 45.00)
        $this->expectException(ValidationException::class);

        $appService->updateApplicability($this->applicability, [
            'maximum_marks' => 40.00,
        ]);
    }

    public function test_batch_save_with_full_context_payload_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '42.50',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'saved' => 1,
        ]);

        $this->assertDatabaseHas('marks', [
            'student_academic_record_id' => $this->sar1->id,
            'student_subject_allocation_id' => $this->alloc1->id,
            'mark_value' => '42.50',
            'result_status' => 'numeric',
        ]);
    }

    public function test_batch_save_missing_academic_year_id_fails_validation(): void
    {
        // Must reject when academic_year_id is omitted or null
        $response = $this->actingAs($this->admin)->postJson(route('marks.batch_save'), [
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '30.00',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['academic_year_id']);
    }

    public function test_batch_save_html_form_submission_with_hidden_context_inputs_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->post(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'student_subject_allocation_id' => $this->alloc1->id,
                    'result_status' => 'numeric',
                    'mark_value' => '48.00',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('marks', [
            'student_academic_record_id' => $this->sar1->id,
            'student_subject_allocation_id' => $this->alloc1->id,
            'mark_value' => '48.00',
            'result_status' => 'numeric',
        ]);
    }
}
