<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\ReportType;
use App\Enums\SubjectCategory;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManageableEntityDeletionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $officeRole = Role::firstOrCreate(['name' => 'Office Staff']);
        $teacherRole = Role::firstOrCreate(['name' => 'Subject Teacher']);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Test School',
            'pass_mark' => 40.00,
        ]);

        $this->admin = User::forceCreate([
            'username' => 'del_admin_' . uniqid(),
            'display_name' => 'Delete Admin User',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'username' => 'del_office_' . uniqid(),
            'display_name' => 'Delete Office User',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $officeRole->id,
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'username' => 'del_teacher_' . uniqid(),
            'display_name' => 'Delete Teacher User',
            'password_hash' => Hash::make('Password@123'),
            'role_id' => $teacherRole->id,
            'is_active' => true,
        ]);
    }

    public function test_academic_year_deletion_is_blocked_when_dependencies_exist(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Term 1 ' . rand(10, 99),
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('academic_years.destroy', $year));

        $response->assertRedirect(route('academic_years.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_empty_academic_year_deletion_succeeds_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2031-01-01',
            'end_date' => '2031-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('academic_years.destroy', $year));

        $response->assertRedirect(route('academic_years.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'academic_years',
            'entity_id' => $year->id,
            'action' => 'DELETE_ACADEMIC_YEAR',
        ]);
    }

    public function test_term_deletion_is_blocked_when_dependencies_exist(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2032-01-01',
            'end_date' => '2032-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'T1 ' . rand(10, 99),
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        $type = AssessmentType::create([
            'name' => 'Type ' . rand(100, 999),
            'is_active' => true,
        ]);

        Assessment::create([
            'academic_year_id' => $year->id,
            'assessment_type_id' => $type->id,
            'term_id' => $term->id,
            'name' => 'Assessment ' . rand(100, 999),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('terms.destroy', $term));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('terms', ['id' => $term->id]);
    }

    public function test_empty_term_deletion_succeeds_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2033-01-01',
            'end_date' => '2033-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'T ' . rand(100, 999),
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('terms.destroy', $term));

        $response->assertRedirect(route('terms.index', ['academic_year_id' => $year->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('terms', ['id' => $term->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'terms',
            'entity_id' => $term->id,
            'action' => 'DELETE_TERM',
        ]);
    }

    public function test_school_class_deletion_is_blocked_when_sections_exist(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2033-01-01',
            'end_date' => '2033-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        Section::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'S ' . rand(10, 99),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('classes.destroy', $class));

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_empty_school_class_deletion_succeeds_and_is_audited(): void
    {
        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('classes.destroy', $class));

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('classes', ['id' => $class->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'classes',
            'entity_id' => $class->id,
            'action' => 'DELETE_CLASS',
        ]);
    }

    public function test_section_deletion_is_blocked_when_student_records_exist(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2034-01-01',
            'end_date' => '2034-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        $section = Section::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'S ' . rand(10, 99),
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM' . rand(100000, 999999),
            'student_name' => 'Test Student ' . rand(100, 999),
        ]);

        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => 1,
            'status' => 'active',
            'effective_from' => '2034-01-01',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('sections.destroy', $section));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_empty_section_deletion_succeeds_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2034-01-01',
            'end_date' => '2034-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        $section = Section::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'S ' . rand(10, 99),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('sections.destroy', $section));

        $response->assertRedirect(route('sections.index', ['academic_year_id' => $year->id, 'class_id' => $class->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'sections',
            'entity_id' => $section->id,
            'action' => 'DELETE_SECTION',
        ]);
    }

    public function test_subject_deletion_is_blocked_when_class_subjects_exist(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2034-01-01',
            'end_date' => '2034-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'S ' . rand(100, 999),
            'code' => 'SB' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        ClassSubject::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('subjects.destroy', $subject));

        $response->assertRedirect(route('subjects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_empty_subject_deletion_succeeds_and_is_audited(): void
    {
        $subject = Subject::create([
            'name' => 'S ' . rand(100, 999),
            'code' => 'ES' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('subjects.destroy', $subject));

        $response->assertRedirect(route('subjects.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'subjects',
            'entity_id' => $subject->id,
            'action' => 'DELETE_SUBJECT',
        ]);
    }

    public function test_class_subject_deletion_succeeds_when_unused_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2034-01-01',
            'end_date' => '2034-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'S ' . rand(100, 999),
            'code' => 'CS' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $cs = ClassSubject::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('class_subjects.destroy', $cs));

        $response->assertRedirect(route('class_subjects.index', ['academic_year_id' => $year->id, 'class_id' => $class->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('class_subjects', ['id' => $cs->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'class_subjects',
            'entity_id' => $cs->id,
            'action' => 'DELETE_CLASS_SUBJECT',
        ]);
    }

    public function test_teacher_assignment_deletion_succeeds_when_unreferenced_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2035-01-01',
            'end_date' => '2035-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $class = SchoolClass::create([
            'name' => 'C ' . rand(100, 999),
            'is_active' => true,
        ]);

        $section = Section::create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $ta = TeacherAssignment::create([
            'user_id' => $this->teacher->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2035-01-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('teacher_assignments.destroy', $ta));

        $response->assertRedirect(route('teacher_assignments.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('teacher_assignments', ['id' => $ta->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'teacher_assignments',
            'entity_id' => $ta->id,
            'action' => 'DELETE_TEACHER_ASSIGNMENT',
        ]);
    }

    public function test_assessment_type_deletion_succeeds_when_unused_and_is_audited(): void
    {
        $type = AssessmentType::create([
            'name' => 'Type ' . rand(100, 999),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('assessments.types.destroy', $type));

        $response->assertRedirect(route('assessments.types.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('assessment_types', ['id' => $type->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'assessment_types',
            'entity_id' => $type->id,
            'action' => 'DELETE_ASSESSMENT_TYPE',
        ]);
    }

    public function test_assessment_deletion_succeeds_when_unused_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2036-01-01',
            'end_date' => '2036-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $type = AssessmentType::create([
            'name' => 'Type ' . rand(100, 999),
            'is_active' => true,
        ]);

        $assessment = Assessment::create([
            'academic_year_id' => $year->id,
            'assessment_type_id' => $type->id,
            'name' => 'Assessment ' . rand(100, 999),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('assessments.destroy', $assessment));

        $response->assertRedirect(route('assessments.index', ['academic_year_id' => $year->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'assessments',
            'entity_id' => $assessment->id,
            'action' => 'DELETE_ASSESSMENT',
        ]);
    }

    public function test_report_configuration_deletion_succeeds_when_unused_and_is_audited(): void
    {
        $year = AcademicYear::create([
            'name' => 'Y' . rand(1000, 9999),
            'start_date' => '2037-01-01',
            'end_date' => '2037-12-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $rc = ReportConfiguration::create([
            'academic_year_id' => $year->id,
            'name' => 'Config ' . rand(100, 999),
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('reports.configurations.destroy', $rc));

        $response->assertRedirect(route('reports.configurations.index', ['academic_year_id' => $year->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('report_configurations', ['id' => $rc->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'report_configurations',
            'entity_id' => $rc->id,
            'action' => 'DELETE_REPORT_CONFIGURATION',
        ]);
    }

    public function test_student_deletion_succeeds_when_unplaced_and_is_audited(): void
    {
        $student = Student::create([
            'admission_number' => 'ADM' . rand(100000, 999999),
            'student_name' => 'Unplaced Student ' . uniqid(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('students.destroy', $student));

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'students',
            'entity_id' => $student->id,
            'action' => 'DELETE_STUDENT',
        ]);
    }

    public function test_user_deletion_is_strictly_prohibited(): void
    {
        // Deleting user via policy check is prohibited
        $this->assertFalse($this->admin->can('delete', $this->teacher));
        $this->assertFalse($this->admin->can('delete', $this->admin));
    }
}
