<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentAndApplicabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $year;
    protected AssessmentType $type;
    protected SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_as_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin AS',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_as_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office AS',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_as_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher AS',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->type = AssessmentType::create([
            'name' => 'Unit Test ' . uniqid(),
            'is_active' => true,
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Class_' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_assessments_have_no_maximum_marks_field(): void
    {
        // Explicit architectural check: assessments table does not contain maximum_marks
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => null,
            'assessment_type_id' => $this->type->id,
            'name' => 'Mid-Term Evaluation',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->assertArrayNotHasKey('maximum_marks', $assessment->getAttributes(), 'Assessment entity must not store maximum marks.');
    }

    public function test_assessment_applicability_supports_different_maximum_marks_per_subject(): void
    {
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->type->id,
            'name' => 'Unit Test 1',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Create 3 subjects: Mathematics, English, Science
        $math = Subject::create(['name' => 'Math_' . uniqid(), 'code' => 'M_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);
        $eng = Subject::create(['name' => 'Eng_' . uniqid(), 'code' => 'E_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);
        $sci = Subject::create(['name' => 'Sci_' . uniqid(), 'code' => 'S_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);

        $csMath = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $math->id,
            'subject_name_snapshot' => $math->name,
            'is_active' => true,
        ]);

        $csEng = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $eng->id,
            'subject_name_snapshot' => $eng->name,
            'is_active' => true,
        ]);

        $csSci = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $sci->id,
            'subject_name_snapshot' => $sci->name,
            'is_active' => true,
        ]);

        // Explicit requirement: Configure Mathematics = 20, English = 25, Science = 30
        $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $csMath->id,
            'maximum_marks' => 20.00,
        ]);

        $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $csEng->id,
            'maximum_marks' => 25.00,
        ]);

        $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $csSci->id,
            'maximum_marks' => 30.00,
        ]);

        $appMath = AssessmentApplicability::where('assessment_id', $assessment->id)->where('class_subject_id', $csMath->id)->firstOrFail();
        $appEng = AssessmentApplicability::where('assessment_id', $assessment->id)->where('class_subject_id', $csEng->id)->firstOrFail();
        $appSci = AssessmentApplicability::where('assessment_id', $assessment->id)->where('class_subject_id', $csSci->id)->firstOrFail();

        $this->assertEquals('20.00', (string) $appMath->maximum_marks);
        $this->assertEquals('25.00', (string) $appEng->maximum_marks);
        $this->assertEquals('30.00', (string) $appSci->maximum_marks);
    }

    public function test_decimal_and_greater_than_100_maximum_marks_are_supported(): void
    {
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->type->id,
            'name' => 'Practical & Project Assessment',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $subject1 = Subject::create(['name' => 'Sub1_' . uniqid(), 'code' => 'S1_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);
        $subject2 = Subject::create(['name' => 'Sub2_' . uniqid(), 'code' => 'S2_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);

        $cs1 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject1->id,
            'subject_name_snapshot' => $subject1->name,
        ]);

        $cs2 = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject2->id,
            'subject_name_snapshot' => $subject2->name,
        ]);

        // 25.5 decimal
        $respDecimal = $this->actingAs($this->admin)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $cs1->id,
            'maximum_marks' => 25.50,
        ]);
        $respDecimal->assertRedirect();

        // 150.00 (>100)
        $respGreater100 = $this->actingAs($this->admin)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $cs2->id,
            'maximum_marks' => 150.00,
        ]);
        $respGreater100->assertRedirect();

        $app1 = AssessmentApplicability::where('assessment_id', $assessment->id)->where('class_subject_id', $cs1->id)->firstOrFail();
        $app2 = AssessmentApplicability::where('assessment_id', $assessment->id)->where('class_subject_id', $cs2->id)->firstOrFail();

        $this->assertEquals('25.50', (string) $app1->maximum_marks);
        $this->assertEquals('150.00', (string) $app2->maximum_marks);
    }

    public function test_negative_or_zero_maximum_marks_is_rejected(): void
    {
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->type->id,
            'name' => 'Negative Test',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $subject = Subject::create(['name' => 'Sub_' . uniqid(), 'code' => 'S_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);
        $cs = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
        ]);

        $response = $this->actingAs($this->admin)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $cs->id,
            'maximum_marks' => 0.00,
        ]);

        $response->assertSessionHasErrors('maximum_marks');
    }

    public function test_cross_year_assessment_term_tampering_is_rejected(): void
    {
        $otherYear = AcademicYear::create([
            'name' => 'AY_P_' . rand(1000, 9999),
            'start_date' => '2024-06-01',
            'end_date' => '2025-04-30',
            'status' => AcademicYearStatus::CLOSED,
        ]);

        $otherTerm = Term::create([
            'academic_year_id' => $otherYear->id,
            'name' => 'Term from Past Year',
            'sequence_no' => 1,
        ]);

        // Submit Assessment for this->year with Term belonging to otherYear
        $response = $this->actingAs($this->officeStaff)->post(route('assessments.store'), [
            'academic_year_id' => $this->year->id,
            'term_id' => $otherTerm->id,
            'assessment_type_id' => $this->type->id,
            'name' => 'Mismatched Assessment',
        ]);

        $response->assertSessionHasErrors('term_id');
    }

    public function test_cross_year_assessment_applicability_tampering_is_rejected(): void
    {
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->type->id,
            'name' => 'Current Assessment',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $otherYear = AcademicYear::create([
            'name' => 'AY_D_' . rand(1000, 9999),
            'start_date' => '2024-06-01',
            'end_date' => '2025-04-30',
            'status' => AcademicYearStatus::CLOSED,
        ]);

        $subject = Subject::create(['name' => 'Sub_' . uniqid(), 'code' => 'S_' . rand(10, 99), 'category' => SubjectCategory::MAIN]);
        $otherClassSubject = ClassSubject::create([
            'academic_year_id' => $otherYear->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
        ]);

        // Attempt to apply assessment of this->year to classSubject of otherYear
        $response = $this->actingAs($this->officeStaff)->post(route('assessments.applicability.store', $assessment), [
            'class_subject_id' => $otherClassSubject->id,
            'maximum_marks' => 50.00,
        ]);

        $response->assertSessionHasErrors('class_subject_id');
    }
}
