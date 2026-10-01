<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\AuditLog;
use App\Models\CalculationSetting;
use App\Models\ClassSubject;
use App\Models\GeneratedReport;
use App\Models\Mark;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
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

class AdministrativeCrudCompletionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $subjectTeacher;
    protected User $classTeacher;
    protected AcademicYear $academicYear;
    protected SchoolClass $schoolClass;
    protected Section $section;
    protected Subject $subject;
    protected Term $term;
    protected AssessmentType $assessmentType;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $officeRole = Role::firstOrCreate(['name' => 'Office Staff']);
        $subTeacherRole = Role::firstOrCreate(['name' => 'Subject Teacher']);
        $classTeacherRole = Role::firstOrCreate(['name' => 'Class Teacher']);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'St Jude High School',
            'school_logo_path' => 'logos/demo.png',
            'pass_mark' => 40.00,
        ]);

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_crud_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin CRUD',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_crud_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office CRUD',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'sub_teacher_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Sub Teacher',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $classTeacherRole->id,
            'username' => 'class_teacher_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10_' . uniqid(),
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'name' => 'Section A',
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'name' => 'Physics_' . uniqid(),
            'code' => 'PHY_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->term = Term::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $this->assessmentType = AssessmentType::create([
            'name' => 'Unit Exam_' . uniqid(),
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. Role Matrix & Unauthorized Access Tests (Office Staff & Teachers)
    // =========================================================================

    public function test_office_staff_is_denied_from_mutating_admin_only_entities(): void
    {
        // School Settings: Office Staff is authorized (Issue 1), Teachers receive 403
        $this->actingAs($this->officeStaff)->put(route('admin.school_settings.update'), [
            'school_name' => 'Staff Configured School',
            'pass_mark' => 40.00,
        ])->assertRedirect(route('admin.school_settings.edit'));

        $this->actingAs($this->subjectTeacher)->put(route('admin.school_settings.update'), [
            'school_name' => 'Hacked School',
            'pass_mark' => 40.00,
        ])->assertStatus(403);

        // Academic Years: Teachers receive 403
        $this->actingAs($this->subjectTeacher)->post(route('academic_years.store'), [
            'name' => 'AY_Forbidden',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ])->assertStatus(403);

        $this->actingAs($this->subjectTeacher)->put(route('academic_years.update', $this->academicYear), [
            'name' => 'AY_Renamed',
            'start_date' => $this->academicYear->start_date->toDateString(),
            'end_date' => $this->academicYear->end_date->toDateString(),
        ])->assertStatus(403);

        // Classes: Office Staff CAN create and update per authoritative business rule
        $this->actingAs($this->officeStaff)->post(route('classes.store'), [
            'name' => 'Class Allowed ' . uniqid(),
            'is_active' => '1',
        ])->assertRedirect(route('classes.index'));

        $this->actingAs($this->officeStaff)->put(route('classes.update', $this->schoolClass), [
            'name' => 'Class Renamed ' . uniqid(),
            'is_active' => '1',
        ])->assertRedirect(route('classes.index'));

        // Subjects: Office Staff CAN create and update per authoritative business rule
        $this->actingAs($this->officeStaff)->post(route('subjects.store'), [
            'name' => 'Staff Subject ' . uniqid(),
            'code' => 'SUB_' . rand(1000, 9999),
            'category' => 'main',
            'is_active' => '1',
        ])->assertRedirect(route('subjects.index'));

        $this->actingAs($this->officeStaff)->put(route('subjects.update', $this->subject), [
            'name' => 'Renamed Subject ' . uniqid(),
            'code' => $this->subject->code,
            'category' => 'main',
            'is_active' => '1',
        ])->assertRedirect(route('subjects.index'));

        // Assessment Types: Office Staff CAN create and update per authoritative business rule
        $this->actingAs($this->officeStaff)->post(route('assessments.types.store'), [
            'name' => 'Office Allowed Type ' . uniqid(),
            'is_active' => '1',
        ])->assertRedirect(route('assessments.types.index'));

        $this->actingAs($this->officeStaff)->put(route('assessments.types.update', $this->assessmentType), [
            'name' => 'Staff Renamed Type',
            'is_active' => '1',
        ])->assertRedirect(route('assessments.types.index'));
    }

    public function test_teachers_are_denied_from_all_administrative_crud(): void
    {
        $teachers = [$this->subjectTeacher, $this->classTeacher];

        foreach ($teachers as $teacher) {
            // Terms
            $this->actingAs($teacher)->put(route('terms.update', $this->term), [
                'name' => 'Teacher Term',
                'sequence_no' => 2,
            ])->assertStatus(403);

            // Sections
            $this->actingAs($teacher)->put(route('sections.update', $this->section), [
                'name' => 'Teacher Sec',
            ])->assertStatus(403);

            // Classes
            $this->actingAs($teacher)->put(route('classes.update', $this->schoolClass), [
                'name' => 'Teacher Class',
            ])->assertStatus(403);

            // Subjects
            $this->actingAs($teacher)->get(route('subjects.index'))->assertStatus(403);
            $this->actingAs($teacher)->post(route('subjects.store'), [
                'name' => 'Teacher Subject',
                'code' => 'TCH_' . rand(1000, 9999),
                'category' => 'main',
            ])->assertStatus(403);
            $this->actingAs($teacher)->put(route('subjects.update', $this->subject), [
                'name' => 'Teacher Subject Edit',
                'code' => $this->subject->code,
                'category' => 'main',
            ])->assertStatus(403);
            $this->actingAs($teacher)->delete(route('subjects.destroy', $this->subject))->assertStatus(403);

            // School Settings
            $this->actingAs($teacher)->put(route('admin.school_settings.update'), [
                'school_name' => 'Teacher School',
                'pass_mark' => 50,
            ])->assertStatus(403);
        }
    }

    // =========================================================================
    // 2. Office Staff Authorized Operations
    // =========================================================================

    public function test_office_staff_can_update_terms_sections_class_subjects_and_assessments(): void
    {
        // 1. Term update
        $termResponse = $this->actingAs($this->officeStaff)->put(route('terms.update', $this->term), [
            'name' => 'Term 1 Updated',
            'sequence_no' => 1,
            'is_active' => 1,
        ]);
        $termResponse->assertRedirect();
        $this->term->refresh();
        $this->assertEquals('Term 1 Updated', $this->term->name);

        // 2. Section update
        $secResponse = $this->actingAs($this->officeStaff)->put(route('sections.update', $this->section), [
            'name' => 'Section A Renamed',
            'is_active' => 1,
        ]);
        $secResponse->assertRedirect();
        $this->section->refresh();
        $this->assertEquals('Section A Renamed', $this->section->name);

        // 3. Class Subject creation & update
        $cs = ClassSubject::create([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'subject_name_snapshot' => $this->subject->name,
            'is_active' => true,
        ]);

        $csUpdateResponse = $this->actingAs($this->officeStaff)->put(route('class_subjects.update', $cs), [
            'is_active' => 0,
        ]);
        $csUpdateResponse->assertRedirect();
        $cs->refresh();
        $this->assertFalse($cs->is_active);

        // 4. Assessment creation & update
        $assessment = Assessment::create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'assessment_type_id' => $this->assessmentType->id,
            'name' => 'Mid Term Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $assUpdateResponse = $this->actingAs($this->officeStaff)->put(route('assessments.update', $assessment), [
            'name' => 'Mid Term Exam Revised',
            'assessment_type_id' => $this->assessmentType->id,
            'term_id' => $this->term->id,
            'status' => 'active',
        ]);
        $assUpdateResponse->assertRedirect();
        $assessment->refresh();
        $this->assertEquals('Mid Term Exam Revised', $assessment->name);
    }

    // =========================================================================
    // 3. Structural Immutability & Historical Protection Tests
    // =========================================================================

    public function test_class_subject_structural_edit_is_rejected_when_dependent_records_exist(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'subject_name_snapshot' => $this->subject->name,
            'is_active' => true,
        ]);

        $assessment = Assessment::create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'assessment_type_id' => $this->assessmentType->id,
            'name' => 'Test Eval',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Create applicability pointing to this class subject
        AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $otherSubject = Subject::create([
            'name' => 'Chemistry_' . uniqid(),
            'code' => 'CHE_' . rand(100, 999),
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // Attempting to change subject_id when dependent applicability exists must fail
        $response = $this->actingAs($this->officeStaff)->put(route('class_subjects.update', $cs), [
            'subject_id' => $otherSubject->id,
        ]);

        $response->assertSessionHasErrors('subject_id');
        $cs->refresh();
        $this->assertEquals($this->subject->id, $cs->subject_id);
    }

    public function test_assessment_structural_edit_is_rejected_when_dependent_reports_exist(): void
    {
        $assessment = Assessment::create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'assessment_type_id' => $this->assessmentType->id,
            'name' => 'Final Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Create student & record
        $student = Student::create([
            'admission_number' => 'ADM_CRUD_001',
            'student_name' => 'John Doe',
        ]);

        $record = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
            'roll_number' => 1,
            'status' => \App\Enums\StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Create generated report tied to this assessment
        GeneratedReport::forceCreate([
            'student_academic_record_id' => $record->id,
            'assessment_id' => $assessment->id,
            'report_type' => ReportType::EXAM,
            'revision_number' => 1,
            'file_path' => 'reports/sample.pdf',
            'generated_by_user_id' => $this->admin->id,
            'generated_at' => now(),
        ]);

        $otherTerm = Term::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Term 2',
            'sequence_no' => 2,
            'is_active' => false,
        ]);

        // Attempting to change term_id after report is generated must fail
        $response = $this->actingAs($this->officeStaff)->put(route('assessments.update', $assessment), [
            'name' => 'Final Exam Renamed',
            'assessment_type_id' => $this->assessmentType->id,
            'term_id' => $otherTerm->id,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('term_id');
        $assessment->refresh();
        $this->assertEquals($this->term->id, $assessment->term_id);
    }

    // =========================================================================
    // 4. Physical Deletions & Removal Verification
    // =========================================================================

    public function test_assessment_applicability_deletion_succeeds_without_marks(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'subject_name_snapshot' => $this->subject->name,
            'is_active' => true,
        ]);

        $assessment = Assessment::create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'assessment_type_id' => $this->assessmentType->id,
            'name' => 'Practical Test',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $app = AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->delete(
            route('assessments.applicability.destroy', [$assessment, $app])
        );

        $response->assertRedirect(route('assessments.applicability.index', $assessment));
        $this->assertDatabaseMissing('assessment_applicability', ['id' => $app->id]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'DELETE_ASSESSMENT_APPLICABILITY',
            'entity_id' => $app->id,
        ]);
    }

    public function test_assessment_applicability_deletion_is_rejected_when_marks_exist(): void
    {
        $cs = ClassSubject::create([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'subject_name_snapshot' => $this->subject->name,
            'is_active' => true,
        ]);

        $assessment = Assessment::create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'assessment_type_id' => $this->assessmentType->id,
            'name' => 'Marks Protected Assessment',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $app = AssessmentApplicability::create([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM_CRUD_002',
            'student_name' => 'Alice Smith',
        ]);

        $record = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
            'roll_number' => 2,
            'status' => \App\Enums\StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $record->id,
            'class_subject_id' => $cs->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Insert mark
        Mark::create([
            'student_academic_record_id' => $record->id,
            'student_subject_allocation_id' => $allocation->id,
            'assessment_applicability_id' => $app->id,
            'mark_value' => '85.50',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        // Attempt deletion of applicability
        $response = $this->actingAs($this->officeStaff)->delete(
            route('assessments.applicability.destroy', [$assessment, $app])
        );

        $response->assertSessionHasErrors('applicability');
        $this->assertDatabaseHas('assessment_applicability', ['id' => $app->id]);
    }

    public function test_calculation_setting_deletion_succeeds_and_is_audited(): void
    {
        $setting = CalculationSetting::create([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->schoolClass->id,
            'calculation_method' => 'average_percentage',
        ]);

        $response = $this->actingAs($this->officeStaff)->delete(
            route('calculations.settings.destroy', $setting)
        );

        $response->assertRedirect(route('calculations.settings.index', ['academic_year_id' => $this->academicYear->id]));
        $this->assertDatabaseMissing('calculation_settings', ['id' => $setting->id]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'DELETE_CALCULATION_SETTING',
            'entity_id' => $setting->id,
        ]);
    }

    public function test_report_assessment_selection_removal_succeeds_and_preserves_assessment_and_reports(): void
    {
        $assessment = Assessment::create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'assessment_type_id' => $this->assessmentType->id,
            'name' => 'Report Selection Test',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $config = ReportConfiguration::create([
            'name' => 'Term 1 Card',
            'report_type' => 'term',
            'academic_year_id' => $this->academicYear->id,
            'is_active' => true,
        ]);

        $selection = ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $assessment->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->delete(
            route('reports.selections.destroy', [$config, $selection])
        );

        $response->assertRedirect(route('reports.configurations.index', ['academic_year_id' => $config->academic_year_id]));
        $this->assertDatabaseMissing('report_assessment_selections', ['id' => $selection->id]);

        // Assessment itself and config must remain intact!
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseHas('report_configurations', ['id' => $config->id]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->officeStaff->id,
            'action' => 'DELETE_REPORT_ASSESSMENT_SELECTION',
            'entity_id' => $selection->id,
        ]);
    }

    // =========================================================================
    // 5. Audit Log & User Immutability
    // =========================================================================

    public function test_audit_logs_are_strictly_immutable_and_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'TEST_IMMUTABLE',
            'entity_type' => 'school_settings',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        // There are no delete routes for audit logs
        $response = $this->actingAs($this->admin)->delete("/audit-logs/{$log->id}");
        $this->assertContains($response->status(), [404, 405]);
    }

    // =========================================================================
    // 6. Administrator Update & Validation Rules Tests
    // =========================================================================

    public function test_administrator_can_update_academic_years_classes_subjects_and_assessment_types(): void
    {
        // 1. Academic Year Update
        $ayResponse = $this->actingAs($this->admin)->put(route('academic_years.update', $this->academicYear), [
            'name' => 'AY_Admin_Updated',
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'is_current' => 1,
        ]);
        $ayResponse->assertRedirect();
        $this->academicYear->refresh();
        $this->assertEquals('AY_Admin_Updated', $this->academicYear->name);

        // 2. Class Update
        $classResponse = $this->actingAs($this->admin)->put(route('classes.update', $this->schoolClass), [
            'name' => 'Class 10 Advanced',
            'is_active' => 1,
        ]);
        $classResponse->assertRedirect();
        $this->schoolClass->refresh();
        $this->assertEquals('Class 10 Advanced', $this->schoolClass->name);

        // 3. Subject Update
        $subResponse = $this->actingAs($this->admin)->put(route('subjects.update', $this->subject), [
            'name' => 'Advanced Physics',
            'code' => $this->subject->code,
            'category' => 'main',
            'is_active' => 1,
        ]);
        $subResponse->assertRedirect();
        $this->subject->refresh();
        $this->assertEquals('Advanced Physics', $this->subject->name);

        // 4. Assessment Type Update
        $typeResponse = $this->actingAs($this->admin)->put(route('assessments.types.update', $this->assessmentType), [
            'name' => 'Unit Exam Advanced',
            'is_active' => 1,
        ]);
        $typeResponse->assertRedirect();
        $this->assessmentType->refresh();
        $this->assertEquals('Unit Exam Advanced', $this->assessmentType->name);
    }

    public function test_unique_validation_ignores_current_record_id_on_update(): void
    {
        // Saving subject with its same code must succeed (ignoring itself)
        $response = $this->actingAs($this->admin)->put(route('subjects.update', $this->subject), [
            'name' => 'Physics Same Code',
            'code' => $this->subject->code,
            'category' => 'main',
            'is_active' => 1,
        ]);
        $response->assertRedirect();
        $this->subject->refresh();
        $this->assertEquals('Physics Same Code', $this->subject->name);

        // Saving class with its same name must succeed (ignoring itself)
        $classRes = $this->actingAs($this->admin)->put(route('classes.update', $this->schoolClass), [
            'name' => $this->schoolClass->name,
            'is_active' => 1,
        ]);
        $classRes->assertRedirect();

        // But taking another existing class's name must fail
        $otherClass = SchoolClass::create(['name' => 'Other Class ' . uniqid(), 'is_active' => true]);
        $dupClassRes = $this->actingAs($this->admin)->put(route('classes.update', $this->schoolClass), [
            'name' => $otherClass->name,
            'is_active' => 1,
        ]);
        $dupClassRes->assertSessionHasErrors('name');
    }

    public function test_invalid_date_range_for_academic_year_is_rejected(): void
    {
        // End date before start date
        $response = $this->actingAs($this->admin)->put(route('academic_years.update', $this->academicYear), [
            'name' => $this->academicYear->name,
            'start_date' => '2027-06-01',
            'end_date' => '2026-04-30',
        ]);
        $response->assertSessionHasErrors('end_date');
    }
}
