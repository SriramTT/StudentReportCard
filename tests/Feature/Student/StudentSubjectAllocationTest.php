<?php

namespace Tests\Feature\Student;

use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\User;
use App\Services\MarkService;
use App\Services\StudentSubjectAllocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentSubjectAllocationTest extends TestCase
{
    use DatabaseTransactions;

    protected AcademicYear $year;
    protected AcademicYear $otherYear;
    protected SchoolClass $class1;
    protected SchoolClass $class2;
    protected Section $sec1A;
    protected Section $sec1B;
    protected Section $sec2A;

    protected Subject $math;
    protected Subject $science;
    protected Subject $electiveArt;

    protected ClassSubject $csMath1A;
    protected ClassSubject $csScienceClassWide;
    protected ClassSubject $csArt1A;
    protected ClassSubject $csInactive;

    protected User $admin;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertEquals('school_report_card_audit', DB::connection()->getDatabaseName());

        $suffix = rand(1000, 9999);

        $this->year = AcademicYear::create([
            'name' => "2026-{$suffix}",
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'is_current' => true,
            'status' => 'open',
        ]);

        $this->otherYear = AcademicYear::create([
            'name' => "2025-{$suffix}",
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $this->class1 = SchoolClass::create(['name' => "Class 1 {$suffix}"]);
        $this->class2 = SchoolClass::create(['name' => "Class 2 {$suffix}"]);

        $this->sec1A = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->sec1B = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'name' => 'B',
            'is_active' => true,
        ]);

        $this->sec2A = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->math = Subject::create([
            'name' => "Mathematics {$suffix}",
            'code' => "MTH{$suffix}",
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->science = Subject::create([
            'name' => "Science {$suffix}",
            'code' => "SCI{$suffix}",
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->electiveArt = Subject::create([
            'name' => "Visual Art {$suffix}",
            'code' => "ART{$suffix}",
            'category' => SubjectCategory::ELECTIVE,
            'is_active' => true,
        ]);

        $this->csMath1A = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $this->csScienceClassWide = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => null, // class-wide
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        $this->csArt1A = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'subject_id' => $this->electiveArt->id,
            'subject_name_snapshot' => $this->electiveArt->name,
            'is_active' => true,
        ]);

        $inactiveSubject = Subject::create([
            'name' => "Music {$suffix}",
            'code' => "MUS{$suffix}",
            'category' => SubjectCategory::ELECTIVE,
            'is_active' => true,
        ]);

        $this->csInactive = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'subject_id' => $inactiveSubject->id,
            'subject_name_snapshot' => $inactiveSubject->name,
            'is_active' => false,
        ]);

        $this->admin = User::create([
            'role_id' => 1,
            'username' => 'admin_ssa_' . uniqid(),
            'email' => 'admin_' . uniqid() . '@school.test',
            'password_hash' => bcrypt('password'),
            'display_name' => 'Admin SSA',
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'role_id' => 3,
            'username' => 'teacher_ssa_' . uniqid(),
            'email' => 'teacher_' . uniqid() . '@school.test',
            'password_hash' => bcrypt('password'),
            'display_name' => 'Teacher SSA',
            'is_active' => true,
        ]);
    }

    public function test_01_valid_placement_creates_default_subject_allocations(): void
    {
        $student = Student::create(['admission_number' => 'ADM-A01', 'student_name' => 'Student One']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $allocatedCount = $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);

        // Should allocate: Math (1A specific), Science (class-wide), Art (1A elective) = 3 active class subjects
        $this->assertEquals(3, $allocatedCount);

        $allocations = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)
            ->where('is_active', true)
            ->get();

        $this->assertCount(3, $allocations);
        $this->assertTrue($allocations->contains('class_subject_id', $this->csMath1A->id));
        $this->assertTrue($allocations->contains('class_subject_id', $this->csScienceClassWide->id));
        $this->assertTrue($allocations->contains('class_subject_id', $this->csArt1A->id));
    }

    public function test_02_allocation_belongs_to_correct_academic_year(): void
    {
        $student = Student::create(['admission_number' => 'ADM-A02', 'student_name' => 'Student Two']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 2,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);

        $allocation = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)->first();
        $this->assertEquals($this->year->id, $allocation->classSubject->academic_year_id);
    }

    public function test_03_allocation_belongs_to_correct_class(): void
    {
        $student = Student::create(['admission_number' => 'ADM-A03', 'student_name' => 'Student Three']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 3,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);

        $allocation = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)->first();
        $this->assertEquals($this->class1->id, $allocation->classSubject->class_id);
    }

    public function test_04_section_specific_class_subject_allocates_only_to_same_section(): void
    {
        // Student in Section 1B (csMath1A is specifically for 1A)
        $student1B = Student::create(['admission_number' => 'ADM-1B01', 'student_name' => 'Student 1B']);
        $sar1B = StudentAcademicRecord::create([
            'student_id' => $student1B->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1B->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $service->allocateDefaultSubjectsForPlacement($sar1B, $this->admin->id);

        $allocations = StudentSubjectAllocation::where('student_academic_record_id', $sar1B->id)->get();

        // 1B student gets class-wide Science, but NOT section-specific Math or Art (which are 1A)
        $this->assertTrue($allocations->contains('class_subject_id', $this->csScienceClassWide->id));
        $this->assertFalse($allocations->contains('class_subject_id', $this->csMath1A->id));
        $this->assertFalse($allocations->contains('class_subject_id', $this->csArt1A->id));
    }

    public function test_05_class_wide_class_subject_allocates_to_all_sections(): void
    {
        $student1A = Student::create(['admission_number' => 'ADM-CW1A', 'student_name' => 'Student CW 1A']);
        $sar1A = StudentAcademicRecord::create([
            'student_id' => $student1A->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 4,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $student1B = Student::create(['admission_number' => 'ADM-CW1B', 'student_name' => 'Student CW 1B']);
        $sar1B = StudentAcademicRecord::create([
            'student_id' => $student1B->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1B->id,
            'roll_number' => 2,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $service->allocateDefaultSubjectsForPlacement($sar1A, $this->admin->id);
        $service->allocateDefaultSubjectsForPlacement($sar1B, $this->admin->id);

        $this->assertTrue(
            StudentSubjectAllocation::where('student_academic_record_id', $sar1A->id)
                ->where('class_subject_id', $this->csScienceClassWide->id)
                ->exists()
        );

        $this->assertTrue(
            StudentSubjectAllocation::where('student_academic_record_id', $sar1B->id)
                ->where('class_subject_id', $this->csScienceClassWide->id)
                ->exists()
        );
    }

    public function test_06_manual_update_rejects_wrong_class_class_subject(): void
    {
        $csClass2 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class2->id,
            'section_id' => $this->sec2A->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $student = Student::create(['admission_number' => 'ADM-REJ-CLASS', 'student_name' => 'Student Class1']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 5,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);

        $this->expectException(ValidationException::class);
        $service->updateStudentAllocations($sar, [$csClass2->id], $this->admin->id);
    }

    public function test_07_manual_update_rejects_wrong_academic_year_class_subject(): void
    {
        $csPastYear = ClassSubject::create([
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->class1->id,
            'section_id' => null,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $student = Student::create(['admission_number' => 'ADM-REJ-YEAR', 'student_name' => 'Student Year']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 6,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);

        $this->expectException(ValidationException::class);
        $service->updateStudentAllocations($sar, [$csPastYear->id], $this->admin->id);
    }

    public function test_08_inactive_class_subject_is_not_allocated(): void
    {
        $student = Student::create(['admission_number' => 'ADM-INACT-SUB', 'student_name' => 'Student Inactive']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 7,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);

        $this->assertFalse(
            StudentSubjectAllocation::where('student_academic_record_id', $sar->id)
                ->where('class_subject_id', $this->csInactive->id)
                ->exists()
        );
    }

    public function test_09_duplicate_allocation_call_is_idempotent(): void
    {
        $student = Student::create(['admission_number' => 'ADM-IDEM', 'student_name' => 'Student Idem']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 8,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $count1 = $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);
        $count2 = $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);

        $this->assertEquals(3, $count1);
        $this->assertEquals(0, $count2); // 0 newly allocated

        // Database must have exactly 1 record per class_subject
        $mathAllocCount = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)
            ->where('class_subject_id', $this->csMath1A->id)
            ->count();
        $this->assertEquals(1, $mathAllocCount);
    }

    public function test_10_deactivated_allocation_is_excluded_from_mark_entry(): void
    {
        $student = Student::create(['admission_number' => 'ADM-DEACT', 'student_name' => 'Student Deact']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 9,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $this->csMath1A->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => false, // Inactive!
        ]);

        $examType = AssessmentType::create(['name' => 'Term Exam ' . uniqid()]);
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $examType->id,
            'name' => 'Term Exam-1',
            'term_id' => null,
            'assessment_date' => '2026-09-10',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->csMath1A->id,
            'maximum_marks' => 100.00,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->class1->id,
            $this->sec1A->id,
            $this->math->id,
            $assessment->id
        );

        $this->assertFalse($roster->contains('student_id', $student->id));
    }

    public function test_11_historical_placement_allocations_do_not_leak_across_years(): void
    {
        $student = Student::create(['admission_number' => 'ADM-HIST-ALLOC', 'student_name' => 'Student Past']);
        $sarPast = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 10,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2025-06-01',
        ]);

        $csPast = ClassSubject::create([
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sarPast->id,
            'class_subject_id' => $csPast->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2025-06-01',
            'is_active' => true,
        ]);

        // Query Mark Entry for current year
        $examType = AssessmentType::create(['name' => 'Term Exam ' . uniqid()]);
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $examType->id,
            'name' => 'Term Exam-1',
            'term_id' => null,
            'assessment_date' => '2026-09-10',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->csMath1A->id,
            'maximum_marks' => 100.00,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->class1->id,
            $this->sec1A->id,
            $this->math->id,
            $assessment->id
        );

        $this->assertFalse($roster->contains('student_id', $student->id));
    }

    public function test_12_audit_log_is_generated_for_allocation_mutation(): void
    {
        $student = Student::create(['admission_number' => 'ADM-AUD-ALLOC', 'student_name' => 'Student Audit']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 11,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentSubjectAllocationService::class);
        $service->allocateDefaultSubjectsForPlacement($sar, $this->admin->id);

        $auditLog = AuditLog::where('entity_type', 'student_subject_allocations')
            ->where('action', 'allocate_subject')
            ->where('user_id', $this->admin->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('Allocated subject', $auditLog->description);
    }

    public function test_13_elective_lockout_rule_blocks_deallocation_once_marks_exist(): void
    {
        // DEC-017 / BR-021: An elective cannot be deallocated once marks exist for the student
        $student = Student::create(['admission_number' => 'ADM-LOCKOUT', 'student_name' => 'Student Locked']);
        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 12,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $allocationArt = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $this->csArt1A->id,
            'allocation_type' => SubjectCategory::ELECTIVE,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $examType = AssessmentType::create(['name' => 'Class Test ' . uniqid()]);
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $examType->id,
            'name' => 'Test 1',
            'term_id' => null,
            'assessment_date' => '2026-09-01',
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $applicability = AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->csArt1A->id,
            'maximum_marks' => 25.00,
        ]);

        // Record a mark for this elective allocation
        Mark::create([
            'student_academic_record_id' => $sar->id,
            'student_subject_allocation_id' => $allocationArt->id,
            'assessment_applicability_id' => $applicability->id,
            'result_status' => MarkResultStatus::NUMERIC,
            'mark_value' => 22.50,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $service = app(StudentSubjectAllocationService::class);

        // Attempting to deallocate Art (by passing only Math and Science) must be rejected!
        $this->expectException(ValidationException::class);
        $service->updateStudentAllocations(
            $sar,
            [$this->csMath1A->id, $this->csScienceClassWide->id], // Art omitted -> deallocation attempted
            $this->admin->id
        );
    }
}
