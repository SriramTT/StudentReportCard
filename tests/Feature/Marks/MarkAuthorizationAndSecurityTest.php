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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarkAuthorizationAndSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher1;
    protected User $teacher2;
    protected User $classTeacherUser;

    protected AcademicYear $year;
    protected AcademicYear $closedYear;

    protected SchoolClass $class1;
    protected SchoolClass $class2;
    protected Section $sectionA;
    protected Section $sectionB;
    protected Section $section2A;

    protected Subject $science;
    protected Subject $math;

    protected ClassSubject $cs1AScience;
    protected ClassSubject $cs1AMath;
    protected ClassSubject $cs1BScience;
    protected ClassSubject $cs2AScience;

    protected AssessmentType $examType;
    protected Assessment $asmt1AScience;
    protected AssessmentApplicability $app1AScience;

    protected Student $student1A;
    protected StudentAcademicRecord $sar1A;
    protected StudentSubjectAllocation $alloc1AScience;

    protected Student $student1B;
    protected StudentAcademicRecord $sar1B;
    protected StudentSubjectAllocation $alloc1BScience;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $stRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $ctRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'adm_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Sec',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'off_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Sec',
            'is_active' => true,
        ]);

        $this->teacher1 = User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 't1_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher 1 Sec',
            'is_active' => true,
        ]);

        $this->teacher2 = User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 't2_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher 2 Sec',
            'is_active' => true,
        ]);

        $this->classTeacherUser = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'ct_sec_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher User Sec',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_SEC_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->closedYear = AcademicYear::create([
            'name' => 'AY_CL_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $this->class1 = SchoolClass::create(['name' => 'Class 1_' . uniqid(), 'is_active' => true]);
        $this->class2 = SchoolClass::create(['name' => 'Class 2_' . uniqid(), 'is_active' => true]);
        $this->sectionA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'A_' . uniqid(),
            'is_active' => true,
        ]);
        $this->sectionB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'B_' . uniqid(),
            'is_active' => true,
        ]);
        $this->section2A = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'name' => 'A_' . uniqid(),
            'is_active' => true,
        ]);

        $this->science = Subject::create(['name' => 'Science_' . uniqid(), 'code' => 'SCI_' . rand(100, 999), 'category' => SubjectCategory::MAIN]);
        $this->math = Subject::create(['name' => 'Math_' . uniqid(), 'code' => 'MAT_' . rand(100, 999), 'category' => SubjectCategory::MAIN]);

        $this->cs1AScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $this->cs1AMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $this->cs1BScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $this->cs2AScience = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->section2A->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $this->examType = AssessmentType::create(['name' => 'Evaluation_' . uniqid(), 'is_active' => true]);
        $this->asmt1AScience = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Science Midterm 1A',
            'assessment_date' => '2026-10-20',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->app1AScience = AssessmentApplicability::create([
            'assessment_id' => $this->asmt1AScience->id,
            'class_subject_id' => $this->cs1AScience->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        // Student in 1-A
        $this->student1A = Student::create(['admission_number' => 'ADM_1A_' . uniqid(), 'student_name' => 'Student 1A']);
        $this->sar1A = StudentAcademicRecord::create([
            'student_id' => $this->student1A->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);
        $this->alloc1AScience = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1A->id,
            'class_subject_id' => $this->cs1AScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Student in 1-B
        $this->student1B = Student::create(['admission_number' => 'ADM_1B_' . uniqid(), 'student_name' => 'Student 1B']);
        $this->sar1B = StudentAcademicRecord::create([
            'student_id' => $this->student1B->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);
        $this->alloc1BScience = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1B->id,
            'class_subject_id' => $this->cs1BScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Assign Teacher 1 to Science in Class 1-A only
        TeacherAssignment::create([
            'user_id' => $this->teacher1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'is_active' => true,
            'effective_from' => '2026-06-01',
        ]);
    }

    public function test_subject_teacher_can_edit_assigned_scope(): void
    {
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1A->id,
                    'student_subject_allocation_id' => $this->alloc1AScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '42.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload);
        $response->assertOk();
        $this->assertDatabaseHas('marks', [
            'student_academic_record_id' => $this->sar1A->id,
            'mark_value' => '42.00',
        ]);
    }

    public function test_subject_teacher_cannot_edit_unassigned_subject_in_same_classroom(): void
    {
        // Teacher 1 attempts to edit Math in Class 1-A
        $asmtMath = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Math Midterm 1A',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appMath = AssessmentApplicability::create([
            'assessment_id' => $asmtMath->id,
            'class_subject_id' => $this->cs1AMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $allocMath = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1A->id,
            'class_subject_id' => $this->cs1AMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $asmtMath->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1A->id,
                    'student_subject_allocation_id' => $allocMath->id,
                    'result_status' => 'numeric',
                    'mark_value' => '30.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload);
        $response->assertStatus(403);
    }

    public function test_subject_teacher_cannot_edit_another_section_or_class(): void
    {
        // Teacher 1 attempts to edit Science in Class 1-B
        $asmt1B = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Science 1B',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $asmt1B->id,
            'class_subject_id' => $this->cs1BScience->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $asmt1B->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1B->id,
                    'student_subject_allocation_id' => $this->alloc1BScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '35.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload);
        $response->assertStatus(403);
    }

    public function test_idor_cross_classroom_student_placement_tampering_is_rejected(): void
    {
        // Teacher 1 submits valid context for Class 1-A Science, but slips in SAR from Class 1-B!
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1B->id, // Tampered SAR from 1-B!
                    'student_subject_allocation_id' => $this->alloc1BScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '40.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('marks.0.student_academic_record_id');
    }

    public function test_class_teacher_can_edit_all_classroom_subjects_and_correct_marks(): void
    {
        // Assign Class Teacher to Class 1-A (subject_id = null)
        TeacherAssignment::create([
            'user_id' => $this->classTeacherUser->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'is_active' => true,
            'effective_from' => '2026-06-01',
        ]);

        // 1. Subject Teacher enters initial mark for Science
        $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1A->id,
                    'student_subject_allocation_id' => $this->alloc1AScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '25.00',
                ],
            ],
        ]);

        // 2. Class Teacher corrects mark 25.00 -> 30.00
        $respCorrect = $this->actingAs($this->classTeacherUser)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1A->id,
                    'student_subject_allocation_id' => $this->alloc1AScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '30.00',
                ],
            ],
        ]);

        $respCorrect->assertOk();
        $this->assertDatabaseHas('marks', [
            'student_academic_record_id' => $this->sar1A->id,
            'mark_value' => '30.00',
            'updated_by_user_id' => $this->classTeacherUser->id,
        ]);
    }

    public function test_class_teacher_account_holding_multiple_assignments_resolves_both_scopes(): void
    {
        // User has Role: Class Teacher
        // Assignment 1: Class Teacher for Class 1-A
        // Assignment 2: Subject Teacher for Science in Class 2-A
        TeacherAssignment::create([
            'user_id' => $this->classTeacherUser->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'is_active' => true,
            'effective_from' => '2026-06-01',
        ]);

        TeacherAssignment::create([
            'user_id' => $this->classTeacherUser->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->section2A->id,
            'subject_id' => $this->science->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'is_active' => true,
            'effective_from' => '2026-06-01',
        ]);

        // 1. Can edit in Class 1-A (Class Teacher scope)
        $resp1 = $this->actingAs($this->classTeacherUser)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1A->id,
                    'student_subject_allocation_id' => $this->alloc1AScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '38.00',
                ],
            ],
        ]);
        $resp1->assertOk();

        // 2. Can edit Science in Class 2-A (Subject Teacher scope)
        $student2A = Student::create(['admission_number' => 'ADM_2A_' . uniqid(), 'student_name' => 'Student 2A']);
        $sar2A = StudentAcademicRecord::create([
            'student_id' => $student2A->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->section2A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);
        $alloc2AScience = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar2A->id,
            'class_subject_id' => $this->cs2AScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
        $asmt2AScience = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Science 2A',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $asmt2AScience->id,
            'class_subject_id' => $this->cs2AScience->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $resp2 = $this->actingAs($this->classTeacherUser)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->section2A->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $asmt2AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $sar2A->id,
                    'student_subject_allocation_id' => $alloc2AScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '41.00',
                ],
            ],
        ]);
        $resp2->assertOk();

        // 3. Denied editing Math in Class 2-A (only Science was assigned for Class 2)
        $cs2AMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->section2A->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);
        $asmt2AMath = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Math 2A',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $asmt2AMath->id,
            'class_subject_id' => $cs2AMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);
        $alloc2AMath = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar2A->id,
            'class_subject_id' => $cs2AMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $resp3 = $this->actingAs($this->classTeacherUser)->postJson(route('marks.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->section2A->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $asmt2AMath->id,
            'marks' => [
                [
                    'student_academic_record_id' => $sar2A->id,
                    'student_subject_allocation_id' => $alloc2AMath->id,
                    'result_status' => 'numeric',
                    'mark_value' => '22.00',
                ],
            ],
        ]);
        $resp3->assertStatus(403);
    }

    public function test_closed_academic_year_blocks_teacher_edits_while_allowing_admin_and_office(): void
    {
        $secClosed = Section::create([
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class1->id,
            'name' => 'A_' . uniqid(),
            'is_active' => true,
        ]);

        // Setup assessment and applicability in closed academic year
        $csClosed = ClassSubject::create([
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $secClosed->id,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $asmtClosed = Assessment::create([
            'academic_year_id' => $this->closedYear->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Closed Year Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appClosed = AssessmentApplicability::create([
            'assessment_id' => $asmtClosed->id,
            'class_subject_id' => $csClosed->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $sarClosed = StudentAcademicRecord::create([
            'student_id' => $this->student1A->id,
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $secClosed->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2025-06-01',
        ]);

        $allocClosed = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sarClosed->id,
            'class_subject_id' => $csClosed->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2025-06-01',
            'is_active' => true,
        ]);

        TeacherAssignment::create([
            'user_id' => $this->teacher1->id,
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $secClosed->id,
            'subject_id' => $this->science->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'is_active' => true,
            'effective_from' => '2025-06-01',
        ]);

        $payload = [
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $secClosed->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $asmtClosed->id,
            'marks' => [
                [
                    'student_academic_record_id' => $sarClosed->id,
                    'student_subject_allocation_id' => $allocClosed->id,
                    'result_status' => 'numeric',
                    'mark_value' => '30.00',
                ],
            ],
        ];

        // 1. Teacher edit in closed year is DENIED
        $respTeacher = $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload);
        $respTeacher->assertStatus(403);

        // 2. Administrator edit in closed year is ALLOWED
        $respAdmin = $this->actingAs($this->admin)->postJson(route('marks.batch_save'), $payload);
        $respAdmin->assertOk();

        // 3. Office Staff edit in closed year is ALLOWED
        $payload['marks'][0]['mark_value'] = '35.00';
        $respOffice = $this->actingAs($this->officeStaff)->postJson(route('marks.batch_save'), $payload);
        $respOffice->assertOk();
    }

    public function test_live_assignment_changes_take_immediate_effect_without_relogin(): void
    {
        $assignment = TeacherAssignment::where('user_id', $this->teacher1->id)->firstOrFail();

        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
            'marks' => [
                [
                    'student_academic_record_id' => $this->sar1A->id,
                    'student_subject_allocation_id' => $this->alloc1AScience->id,
                    'result_status' => 'numeric',
                    'mark_value' => '20.00',
                ],
            ],
        ];

        // Step A: Initially allowed
        $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload)->assertOk();

        // Step B: Deactivate assignment
        $assignment->update(['is_active' => false]);

        // Step C: Immediately denied without relogin
        $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload)->assertStatus(403);

        // Step D: Reactivate assignment
        $assignment->update(['is_active' => true]);

        // Step E: Immediately allowed without relogin
        $payload['marks'][0]['mark_value'] = '25.00';
        $this->actingAs($this->teacher1)->postJson(route('marks.batch_save'), $payload)->assertOk();
    }

    public function test_transferred_student_historical_placement_does_not_leak_into_roster(): void
    {
        // Transfer student1A from 1-A to 1-B
        $this->sar1A->update([
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_to' => '2026-09-01',
        ]);

        $sar1ANewIn1B = StudentAcademicRecord::create([
            'student_id' => $this->student1A->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 2,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-09-01',
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar1ANewIn1B->id,
            'class_subject_id' => $this->cs1BScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-09-01',
            'is_active' => true,
        ]);

        // Load 1-A Science roster
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->science->id,
            'assessment_id' => $this->asmt1AScience->id,
        ]));

        $response->assertOk();
        // student1A was transferred out of 1-A, so must NOT appear in 1-A roster!
        $response->assertDontSee($this->student1A->admission_number);
    }
}
