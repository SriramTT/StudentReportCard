<?php

namespace Tests\Feature\Marks;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
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

class MarkEntryContextTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $subjectTeacher;
    protected AcademicYear $year;
    protected SchoolClass $classA;
    protected SchoolClass $classB;
    protected Section $secA1;
    protected Section $secA2;
    protected Subject $math;
    protected Subject $science;
    protected ClassSubject $csMath;
    protected AssessmentType $examType;
    protected Assessment $assessment;
    protected AssessmentApplicability $applicability;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_mc_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Context',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_mc_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher Context',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_MC_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

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

        $this->math = Subject::create(['name' => 'Math ' . uniqid(), 'code' => 'M_' . rand(100, 999), 'category' => SubjectCategory::MAIN, 'is_active' => true]);
        $this->science = Subject::create(['name' => 'Science ' . uniqid(), 'code' => 'S_' . rand(100, 999), 'category' => SubjectCategory::MAIN, 'is_active' => true]);

        $this->csMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'subject_name_snapshot' => $this->math->name,
            'is_active' => true,
        ]);

        $this->examType = AssessmentType::create(['name' => 'Context Test ' . uniqid(), 'is_active' => true]);

        $this->assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 1 Exam ' . uniqid(),
            'assessment_date' => '2026-09-15',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->applicability = AssessmentApplicability::create([
            'assessment_id' => $this->assessment->id,
            'class_subject_id' => $this->csMath->id,
            'maximum_marks' => 75.00,
            'is_active' => true,
        ]);

        // Teacher assignment for Math in Class A - Section A1
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
    }

    public function test_academic_year_loads_valid_options(): void
    {
        $response = $this->actingAs($this->admin)->get(route('marks.index'));
        $response->assertOk();
        $response->assertSeeText($this->year->name);
    }

    public function test_selecting_academic_year_loads_valid_classes(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
        ]);

        $this->assertEquals($this->year->id, $context['selectedYearId']);
        $this->assertTrue($context['availableClasses']->contains('id', $this->classA->id));
        $this->assertTrue($context['availableClasses']->contains('id', $this->classB->id));
    }

    public function test_selecting_class_loads_valid_sections(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
        ]);

        $this->assertEquals($this->classA->id, $context['selectedClassId']);
        $this->assertTrue($context['availableSections']->contains('id', $this->secA1->id));
        $this->assertTrue($context['availableSections']->contains('id', $this->secA2->id));
    }

    public function test_selecting_section_loads_valid_subjects(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
        ]);

        $this->assertEquals($this->secA1->id, $context['selectedSectionId']);
        $this->assertTrue($context['availableSubjects']->contains('id', $this->math->id));
        // Science is not mapped to this classroom
        $this->assertFalse($context['availableSubjects']->contains('id', $this->science->id));
    }

    public function test_selecting_subject_loads_valid_assessments(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
        ]);

        $this->assertEquals($this->math->id, $context['selectedSubjectId']);
        $this->assertTrue($context['availableAssessments']->contains('id', $this->assessment->id));
    }

    public function test_invalid_class_section_combination_is_rejected(): void
    {
        $otherClassSec = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'name' => 'B1',
            'is_active' => true,
        ]);

        $markService = app(MarkService::class);
        // Request Class A with Section B1 (which belongs to Class B)
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $otherClassSec->id,
        ]);

        // Section should be reset to null because it does not belong to Class A
        $this->assertNull($context['selectedSectionId']);
    }

    public function test_invalid_section_subject_combination_is_rejected(): void
    {
        $markService = app(MarkService::class);
        // Science is not mapped in Section A1
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->science->id,
        ]);

        $this->assertNull($context['selectedSubjectId']);
    }

    public function test_invalid_assessment_context_combination_is_rejected(): void
    {
        $unrelatedAssessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Unrelated Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $unrelatedAssessment->id,
        ]);

        // Unrelated assessment without applicability in this context must be rejected
        $this->assertNull($context['selectedAssessmentId']);
    }

    public function test_unauthorized_teacher_context_is_rejected(): void
    {
        // Teacher has assignment in Class A / Sec A1 / Math only.
        // Trying to access Class B
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->subjectTeacher, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
        ]);

        $this->assertNull($context['selectedClassId']);
    }

    public function test_authorized_teacher_context_succeeds(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->subjectTeacher, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertEquals($this->classA->id, $context['selectedClassId']);
        $this->assertEquals($this->secA1->id, $context['selectedSectionId']);
        $this->assertEquals($this->math->id, $context['selectedSubjectId']);
        $this->assertEquals($this->assessment->id, $context['selectedAssessmentId']);
        $this->assertTrue($context['canEdit']);
        $this->assertFalse($context['isReadOnly']);
    }

    public function test_historical_student_placement_does_not_appear_in_current_roster(): void
    {
        $student = Student::create(['admission_number' => 'ADM_HIST_' . uniqid(), 'student_name' => 'Historical Student']);

        // Historical placement (closed due to transfer)
        $oldSar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'roll_number' => 99,
            'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-08-01',
        ]);

        // Active placement in Class B
        $newSar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->secA2->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-08-02',
        ]);

        $markService = app(MarkService::class);
        $roster = $markService->getMarkEntryRoster(
            $this->year->id,
            $this->classA->id,
            $this->secA1->id,
            $this->csMath->id,
            $this->applicability->id
        );

        $this->assertFalse($roster->contains('student_academic_record_id', $oldSar->id));
    }

    public function test_assessment_applicability_resolves_authoritative_maximum_marks(): void
    {
        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
        ]);

        $this->assertNotNull($context['targetApplicability']);
        $this->assertEquals(75.00, (float) $context['targetApplicability']->maximum_marks);
    }

    // --- HTTP Controller & Blade Rendering Verification ---

    public function test_http_get_marks_with_only_academic_year_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($this->year->name);
        $response->assertSeeText('Select a Class to Begin');
    }

    public function test_http_get_marks_with_academic_year_and_class_succeeds_without_undefined_variable(): void
    {
        // This directly tests the defect: GET /marks?academic_year_id={id}&class_id={id}
        // Previously threw ErrorException: Undefined variable $selectedClass at line 146
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
        ]));

        $response->assertOk();
        $response->assertSeeText('Select a Section for ' . $this->classA->name);
        $response->assertSeeText('Section ' . $this->secA1->name);
        $response->assertSeeText('Section ' . $this->secA2->name);
    }

    public function test_http_get_marks_with_year_class_and_section_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
        ]));

        $response->assertOk();
        $response->assertSeeText('Select a Subject');
        $response->assertSeeText($this->math->name);
    }

    public function test_http_get_marks_with_year_class_section_and_subject_succeeds(): void
    {
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
        ]));

        $response->assertOk();
        $response->assertSeeText('Select an Assessment');
        $response->assertSeeText($this->assessment->name);
    }

    public function test_http_get_marks_with_complete_context_succeeds_and_renders_details(): void
    {
        $response = $this->actingAs($this->admin)->get(route('marks.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->secA1->id,
            'subject_id' => $this->math->id,
            'assessment_id' => $this->assessment->id,
        ]));

        $response->assertOk();
        $response->assertSeeText('Maximum Marks:');
        $response->assertSeeText('75');
    }

    public function test_invalid_academic_year_class_combination_is_rejected(): void
    {
        $inactiveClass = SchoolClass::create(['name' => 'Inactive Class ' . uniqid(), 'is_active' => false]);

        $markService = app(MarkService::class);
        $context = $markService->loadMarkContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $inactiveClass->id,
        ]);

        $this->assertNull($context['selectedClassId']);
        $this->assertNull($context['selectedClass']);
    }
}
