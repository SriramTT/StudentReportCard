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
use App\Services\MarkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarkEntryRosterAllocationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $subjectTeacher;
    protected User $classTeacher;
    protected User $unauthorizedTeacher;

    protected AcademicYear $year;
    protected AcademicYear $otherYear;
    protected SchoolClass $classA;
    protected SchoolClass $classB;
    protected Section $secA1;
    protected Section $secA2;
    protected Section $secB1;

    protected Subject $math;
    protected Subject $science;
    protected ClassSubject $csMathSectionSpecific;
    protected ClassSubject $csScienceClassWide;
    protected AssessmentType $examType;
    protected Assessment $mathAssessment;
    protected AssessmentApplicability $mathApplicability;

    protected Student $student1;
    protected StudentAcademicRecord $sar1;
    protected StudentSubjectAllocation $alloc1Math;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();
        $ctRole = Role::where('name', 'Class Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_ra_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Roster Allocation',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_ra_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Roster Allocation',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'st_ra_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Subject Teacher RA',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'ct_ra_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher RA',
            'is_active' => true,
        ]);

        $this->unauthorizedTeacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'unauth_ra_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Unauthorized Teacher RA',
            'is_active' => true,
        ]);

        // Academic Years
        $this->year = AcademicYear::create([
            'name' => 'AY_RA_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->otherYear = AcademicYear::create([
            'name' => 'AY_PREV_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        // Classes & Sections
        $this->classA = SchoolClass::create(['name' => 'Class A ' . uniqid(), 'is_active' => true]);
        $this->classB = SchoolClass::create(['name' => 'Class B ' . uniqid(), 'is_active' => true]);

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

        // Subjects & Class Subjects
        $this->math = Subject::create([
            'name' => 'Math ' . uniqid(),
            'code' => 'M_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->science = Subject::create([
            'name' => 'Science ' . uniqid(),
            'code' => 'S_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // Section-specific mapping (Class A, Section A1, Math)
        $this->csMathSectionSpecific = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        // Class-wide mapping (Class A, All Sections, Science)
        $this->csScienceClassWide = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => null,
            'subject_id' => $this->science->id,
            'subject_name_snapshot' => $this->science->name,
            'is_active' => true,
        ]);

        // Assessment & Applicability
        $this->examType = AssessmentType::create(['name' => 'Type RA ' . uniqid(), 'is_active' => true]);

        $this->mathAssessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term Exam ' . uniqid(),
            'assessment_date' => '2026-09-15',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->mathApplicability = AssessmentApplicability::create([
            'assessment_id' => $this->mathAssessment->id,
            'class_subject_id' => $this->csMathSectionSpecific->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // Teacher assignments
        TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Student 1: Active placement + Active Math allocation
        $this->student1 = Student::create(['admission_number' => 'S1_' . uniqid(), 'student_name' => 'Student One']);
        $this->sar1 = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $this->alloc1Math = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1->id,
            'class_subject_id' => $this->csMathSectionSpecific->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);
    }

    /**
     * TEST 1: Active student placement + active subject allocation -> student appears in Mark Entry roster.
     */
    public function test_01_active_placement_and_active_allocation_appears_in_roster(): void
    {
        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertCount(1, $roster);
        $this->assertEquals($this->sar1->id, $roster->first()->student_academic_record_id);
        $this->assertEquals($this->alloc1Math->id, $roster->first()->student_subject_allocation_id);
        $this->assertEquals($this->student1->admission_number, $roster->first()->admission_number);
    }

    /**
     * TEST 2: Active student placement but NO subject allocation -> student does NOT appear.
     * This explicitly confirms the approved allocation requirement.
     */
    public function test_02_active_placement_without_subject_allocation_does_not_appear(): void
    {
        // Student 2 is actively placed in Class A Section A1, but has NO subject allocations
        $student2 = Student::create(['admission_number' => 'S2_' . uniqid(), 'student_name' => 'Student Two (No Allocations)']);
        $sar2 = StudentAcademicRecord::create([
            'student_id' => $student2->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 2,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        // Student 1 appears, Student 2 is excluded because of 0 allocations
        $this->assertCount(1, $roster);
        $this->assertTrue($roster->contains('student_academic_record_id', $this->sar1->id));
        $this->assertFalse($roster->contains('student_academic_record_id', $sar2->id));
    }

    /**
     * TEST 3: Inactive subject allocation -> student does NOT appear.
     */
    public function test_03_inactive_subject_allocation_does_not_appear(): void
    {
        $student3 = Student::create(['admission_number' => 'S3_' . uniqid(), 'student_name' => 'Student Three (Inactive Alloc)']);
        $sar3 = StudentAcademicRecord::create([
            'student_id' => $student3->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 3,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar3->id,
            'class_subject_id' => $this->csMathSectionSpecific->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => false, // Inactive allocation!
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertFalse($roster->contains('student_academic_record_id', $sar3->id));
    }

    /**
     * TEST 4: Allocation belongs to another subject -> student does NOT appear in current subject roster.
     */
    public function test_04_allocation_to_another_subject_does_not_appear(): void
    {
        $student4 = Student::create(['admission_number' => 'S4_' . uniqid(), 'student_name' => 'Student Four (Science Only)']);
        $sar4 = StudentAcademicRecord::create([
            'student_id' => $student4->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 4,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Allocate only Science, NOT Math
        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar4->id,
            'class_subject_id' => $this->csScienceClassWide->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertFalse($roster->contains('student_academic_record_id', $sar4->id));
    }

    /**
     * TEST 5: Allocation belongs to another class -> student does NOT appear.
     */
    public function test_05_allocation_to_another_class_does_not_appear(): void
    {
        $student5 = Student::create(['admission_number' => 'S5_' . uniqid(), 'student_name' => 'Student Five (Class B)']);
        $sar5 = StudentAcademicRecord::create([
            'student_id' => $student5->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->secB1->id,
            'roll_number' => 5,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $csMathB = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->secB1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar5->id,
            'class_subject_id' => $csMathB->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $markService = app(MarkService::class);
        // Query Class A / Section A1
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertFalse($roster->contains('student_academic_record_id', $sar5->id));
    }

    /**
     * TEST 6: Allocation belongs to another academic year -> student does NOT appear.
     */
    public function test_06_allocation_to_another_academic_year_does_not_appear(): void
    {
        $student6 = Student::create(['admission_number' => 'S6_' . uniqid(), 'student_name' => 'Student Six (Old Year)']);
        $sar6 = StudentAcademicRecord::create([
            'student_id' => $student6->id,
            'academic_year_id' => $this->otherYear->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 6,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2025-06-01',
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar6->id,
            'class_subject_id' => $this->csMathSectionSpecific->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2025-06-01',
            'is_active' => true,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertFalse($roster->contains('student_academic_record_id', $sar6->id));
    }

    /**
     * TEST 7: Historical placement does not leak into current roster.
     */
    public function test_07_historical_placement_does_not_leak_into_current_roster(): void
    {
        $student7 = Student::create(['admission_number' => 'S7_' . uniqid(), 'student_name' => 'Student Seven (Transferred)']);
        
        // Old placement in Class A / Sec A1 marked internal_transfer
        $oldSar = StudentAcademicRecord::create([
            'student_id' => $student7->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 77,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-08-01',
        ]);

        StudentSubjectAllocation::create([
            'student_academic_record_id' => $oldSar->id,
            'class_subject_id' => $this->csMathSectionSpecific->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertFalse($roster->contains('student_academic_record_id', $oldSar->id));
    }

    /**
     * TEST 8: Section-specific Class Subject correctly resolves roster.
     */
    public function test_08_section_specific_class_subject_resolves_roster(): void
    {
        $this->assertNotNull($this->csMathSectionSpecific->section_id);
        $this->assertEquals($this->secA1->id, $this->csMathSectionSpecific->section_id);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $this->assertTrue($roster->contains('student_academic_record_id', $this->sar1->id));
    }

    /**
     * TEST 9: Class-wide Class Subject (section_id IS NULL) correctly resolves roster.
     */
    public function test_09_class_wide_class_subject_resolves_roster(): void
    {
        $this->assertNull($this->csScienceClassWide->section_id);

        // Allocate Science to student 1
        $allocScience = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar1->id,
            'class_subject_id' => $this->csScienceClassWide->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $scienceApplicability = AssessmentApplicability::create([
            'assessment_id' => $this->mathAssessment->id,
            'class_subject_id' => $this->csScienceClassWide->id,
            'maximum_marks' => 80.00,
            'is_active' => true,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csScienceClassWide->id,
            $scienceApplicability->id
        );

        $this->assertTrue($roster->contains('student_academic_record_id', $this->sar1->id));
        $this->assertEquals($allocScience->id, $roster->firstWhere('student_academic_record_id', $this->sar1->id)->student_subject_allocation_id);
    }

    /**
     * TEST 10: Unauthorized teacher receives read-only or empty scope on mark entry.
     */
    public function test_10_unauthorized_teacher_roster_access_rejected(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->unauthorizedTeacher, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
        ]);

        // Unauthorized teacher has no assignment here; class_id is rejected
        $this->assertNull($context['selectedClassId']);
    }

    /**
     * TEST 11: Authorized Subject Teacher receives correct subject roster.
     */
    public function test_11_authorized_subject_teacher_sees_roster(): void
    {
        $response = $this->actingAs($this->subjectTeacher)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->mathAssessment->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($this->student1->student_name);
        $response->assertSeeText($this->student1->admission_number);
    }

    /**
     * TEST 12: Authorized Class Teacher receives classroom subject roster.
     */
    public function test_12_authorized_class_teacher_sees_roster(): void
    {
        $response = $this->actingAs($this->classTeacher)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->mathAssessment->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($this->student1->student_name);
        $response->assertSeeText($this->student1->admission_number);
    }

    /**
     * TEST 13: Administrator receives valid roster.
     */
    public function test_13_administrator_sees_valid_roster(): void
    {
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->mathAssessment->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($this->student1->student_name);
        $response->assertSeeText($this->student1->admission_number);
    }

    /**
     * TEST 14: Office Staff receives valid roster.
     */
    public function test_14_office_staff_sees_valid_roster(): void
    {
        $response = $this->actingAs($this->officeStaff)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->mathAssessment->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($this->student1->student_name);
        $response->assertSeeText($this->student1->admission_number);
    }

    /**
     * TEST 15: Existing mark records load against the correct student allocation.
     */
    public function test_15_existing_mark_records_load_against_correct_allocation(): void
    {
        Mark::create([
            'student_academic_record_id' => $this->sar1->id,
            'student_subject_allocation_id' => $this->alloc1Math->id,
            'assessment_applicability_id' => $this->mathApplicability->id,
            'mark_value' => '92.50',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->subjectTeacher->id,
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMathSectionSpecific->id,
            $this->mathApplicability->id
        );

        $studentRow = $roster->firstWhere('student_academic_record_id', $this->sar1->id);
        $this->assertNotNull($studentRow);
        $this->assertEquals('92.50', $studentRow->formatted_value);
        $this->assertEquals('numeric', $studentRow->result_status);
        $this->assertNotNull($studentRow->mark_id);
    }
}
