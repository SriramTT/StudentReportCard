<?php

namespace Tests\Feature\Auth;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Enums\StudentPlacementStatus;
use App\Enums\SubjectCategory;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\GeneratedReport;
use App\Models\Mark;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\TeacherAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherScopeAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected TeacherAuthorizationService $teacherAuth;

    protected User $adminUser;
    protected User $officeStaffUser;
    protected User $teacherA;
    protected User $teacherB;

    protected AcademicYear $yearOpen;
    protected AcademicYear $yearClosed;

    protected SchoolClass $class8;
    protected SchoolClass $class9;

    protected Section $section8A;
    protected Section $section8B;
    protected Section $section9A;

    protected Subject $subjectMath;
    protected Subject $subjectScience;
    protected Subject $subjectEnglish;

    protected ClassSubject $cs8AMath;
    protected ClassSubject $cs8AScience;
    protected ClassSubject $cs8BScience;
    protected ClassSubject $cs9AMath;
    protected ClassSubject $cs9AScience;
    protected ClassSubject $cs9AEnglish;

    protected Mark $mark8AMath;
    protected Mark $mark8AScience;
    protected Mark $mark8BScience;
    protected Mark $mark9AMath;
    protected Mark $mark9AScience;
    protected Mark $markClosedYear;

    protected Attendance $attendance9A;
    protected Attendance $attendance8A;
    protected Attendance $attendanceClosedYear;

    protected GeneratedReport $report9A;
    protected GeneratedReport $report8A;

    protected TeacherAssignment $assign8AMath;
    protected TeacherAssignment $assign8BScience;
    protected TeacherAssignment $assign9AClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacherAuth = app(TeacherAuthorizationService::class);

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->adminUser = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'tscope_admin',
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Scope Admin',
            'email' => 'scope_admin@school.test',
            'is_active' => true,
        ]);

        $this->officeStaffUser = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'tscope_office',
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Scope Office',
            'email' => 'scope_office@school.test',
            'is_active' => true,
        ]);

        $this->teacherA = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'teacher_a',
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Teacher Multi-Assignment',
            'email' => 'teacher_a@school.test',
            'is_active' => true,
        ]);

        $this->teacherB = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'teacher_b',
            'password_hash' => Hash::make('secret'),
            'display_name' => 'Teacher Inactive/Future',
            'email' => 'teacher_b@school.test',
            'is_active' => true,
        ]);

        // Academic Years
        $this->yearOpen = AcademicYear::forceCreate([
            'name' => '2026/2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->yearClosed = AcademicYear::forceCreate([
            'name' => '2025/2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        // Classes
        $this->class8 = SchoolClass::forceCreate([
            'name' => 'Grade 8',
            'is_active' => true,
        ]);

        $this->class9 = SchoolClass::forceCreate([
            'name' => 'Grade 9',
            'is_active' => true,
        ]);

        // Sections
        $this->section8A = Section::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->section8B = Section::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'name' => 'B',
            'is_active' => true,
        ]);

        $this->section9A = Section::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class9->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        // Subjects
        $this->subjectMath = Subject::forceCreate([
            'name' => 'Mathematics Test',
            'code' => 'MATH_TEST',
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->subjectScience = Subject::forceCreate([
            'name' => 'Science Test',
            'code' => 'SCI_TEST',
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        $this->subjectEnglish = Subject::forceCreate([
            'name' => 'English Test',
            'code' => 'ENG_TEST',
            'category' => SubjectCategory::MAIN,
            'is_active' => true,
        ]);

        // Class Subjects
        $this->cs8AMath = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectMath->id,
            'subject_name_snapshot' => 'Mathematics Test',
            'is_active' => true,
        ]);

        $this->cs8AScience = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectScience->id,
            'subject_name_snapshot' => 'Science Test',
            'is_active' => true,
        ]);

        $this->cs8BScience = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8B->id,
            'subject_id' => $this->subjectScience->id,
            'subject_name_snapshot' => 'Science Test',
            'is_active' => true,
        ]);

        $this->cs9AMath = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9A->id,
            'subject_id' => $this->subjectMath->id,
            'subject_name_snapshot' => 'Mathematics Test',
            'is_active' => true,
        ]);

        $this->cs9AScience = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9A->id,
            'subject_id' => $this->subjectScience->id,
            'subject_name_snapshot' => 'Science Test',
            'is_active' => true,
        ]);

        $this->cs9AEnglish = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9A->id,
            'subject_id' => $this->subjectEnglish->id,
            'subject_name_snapshot' => 'English Test',
            'is_active' => true,
        ]);

        // Terms & Assessment Infrastructure
        $term1 = Term::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'name' => 'Term 1 Test',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $asstType = AssessmentType::forceCreate([
            'name' => 'Unit Test',
            'is_active' => true,
        ]);

        $assessment = Assessment::forceCreate([
            'academic_year_id' => $this->yearOpen->id,
            'term_id' => $term1->id,
            'assessment_type_id' => $asstType->id,
            'name' => 'Unit Test 1',
            'assessment_date' => '2026-07-01',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $app8AMath = AssessmentApplicability::forceCreate([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->cs8AMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $app8AScience = AssessmentApplicability::forceCreate([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->cs8AScience->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $app8BScience = AssessmentApplicability::forceCreate([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->cs8BScience->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $app9AMath = AssessmentApplicability::forceCreate([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->cs9AMath->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        $app9AScience = AssessmentApplicability::forceCreate([
            'assessment_id' => $assessment->id,
            'class_subject_id' => $this->cs9AScience->id,
            'maximum_marks' => 50.00,
            'is_active' => true,
        ]);

        // Students & Records
        $student1 = Student::forceCreate([
            'admission_number' => 'ADM_TS_001',
            'student_name' => 'Student EightA',
        ]);

        $student2 = Student::forceCreate([
            'admission_number' => 'ADM_TS_002',
            'student_name' => 'Student EightB',
        ]);

        $student3 = Student::forceCreate([
            'admission_number' => 'ADM_TS_003',
            'student_name' => 'Student NineA',
        ]);

        $sar8A = StudentAcademicRecord::forceCreate([
            'student_id' => $student1->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $sar8B = StudentAcademicRecord::forceCreate([
            'student_id' => $student2->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8B->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $sar9A = StudentAcademicRecord::forceCreate([
            'student_id' => $student3->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Student Subject Allocations
        $alloc8AMath = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $sar8A->id,
            'class_subject_id' => $this->cs8AMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $alloc8AScience = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $sar8A->id,
            'class_subject_id' => $this->cs8AScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $alloc8BScience = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $sar8B->id,
            'class_subject_id' => $this->cs8BScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $alloc9AMath = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $sar9A->id,
            'class_subject_id' => $this->cs9AMath->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $alloc9AScience = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $sar9A->id,
            'class_subject_id' => $this->cs9AScience->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        // Marks
        $this->mark8AMath = Mark::forceCreate([
            'student_academic_record_id' => $sar8A->id,
            'student_subject_allocation_id' => $alloc8AMath->id,
            'assessment_applicability_id' => $app8AMath->id,
            'mark_value' => '45.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->mark8AScience = Mark::forceCreate([
            'student_academic_record_id' => $sar8A->id,
            'student_subject_allocation_id' => $alloc8AScience->id,
            'assessment_applicability_id' => $app8AScience->id,
            'mark_value' => '40.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->mark8BScience = Mark::forceCreate([
            'student_academic_record_id' => $sar8B->id,
            'student_subject_allocation_id' => $alloc8BScience->id,
            'assessment_applicability_id' => $app8BScience->id,
            'mark_value' => '42.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->mark9AMath = Mark::forceCreate([
            'student_academic_record_id' => $sar9A->id,
            'student_subject_allocation_id' => $alloc9AMath->id,
            'assessment_applicability_id' => $app9AMath->id,
            'mark_value' => '48.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->mark9AScience = Mark::forceCreate([
            'student_academic_record_id' => $sar9A->id,
            'student_subject_allocation_id' => $alloc9AScience->id,
            'assessment_applicability_id' => $app9AScience->id,
            'mark_value' => '44.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        // Attendance
        $this->attendance9A = Attendance::forceCreate([
            'student_academic_record_id' => $sar9A->id,
            'term_id' => $term1->id,
            'days_attended' => 85,
            'total_working_days' => 90,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->attendance8A = Attendance::forceCreate([
            'student_academic_record_id' => $sar8A->id,
            'term_id' => $term1->id,
            'days_attended' => 88,
            'total_working_days' => 90,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        // Generated Reports
        $this->report9A = GeneratedReport::forceCreate([
            'student_academic_record_id' => $sar9A->id,
            'report_type' => ReportType::TERM,
            'term_id' => $term1->id,
            'assessment_id' => null,
            'revision_number' => 1,
            'file_path' => 'reports/2026/term1/sar_9a_1.pdf',
            'generated_by_user_id' => $this->adminUser->id,
            'generated_at' => now(),
        ]);

        $this->report8A = GeneratedReport::forceCreate([
            'student_academic_record_id' => $sar8A->id,
            'report_type' => ReportType::TERM,
            'term_id' => $term1->id,
            'assessment_id' => null,
            'revision_number' => 1,
            'file_path' => 'reports/2026/term1/sar_8a_1.pdf',
            'generated_by_user_id' => $this->adminUser->id,
            'generated_at' => now(),
        ]);

        // Closed Year Records for closed year testing
        $sectionClosed = Section::forceCreate([
            'academic_year_id' => $this->yearClosed->id,
            'class_id' => $this->class8->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $csClosed = ClassSubject::forceCreate([
            'academic_year_id' => $this->yearClosed->id,
            'class_id' => $this->class8->id,
            'section_id' => $sectionClosed->id,
            'subject_id' => $this->subjectMath->id,
            'subject_name_snapshot' => 'Mathematics Test',
            'is_active' => true,
        ]);

        $termClosed = Term::forceCreate([
            'academic_year_id' => $this->yearClosed->id,
            'name' => 'Term 1 Closed',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $asstClosed = Assessment::forceCreate([
            'academic_year_id' => $this->yearClosed->id,
            'term_id' => $termClosed->id,
            'assessment_type_id' => $asstType->id,
            'name' => 'Past Exam',
            'assessment_date' => '2025-09-01',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $appClosed = AssessmentApplicability::forceCreate([
            'assessment_id' => $asstClosed->id,
            'class_subject_id' => $csClosed->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        $sarClosed = StudentAcademicRecord::forceCreate([
            'student_id' => $student1->id,
            'academic_year_id' => $this->yearClosed->id,
            'class_id' => $this->class8->id,
            'section_id' => $sectionClosed->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2025-06-01',
        ]);

        $allocClosed = StudentSubjectAllocation::forceCreate([
            'student_academic_record_id' => $sarClosed->id,
            'class_subject_id' => $csClosed->id,
            'allocation_type' => SubjectCategory::MAIN,
            'effective_from' => '2025-06-01',
            'is_active' => true,
        ]);

        $this->markClosedYear = Mark::forceCreate([
            'student_academic_record_id' => $sarClosed->id,
            'student_subject_allocation_id' => $allocClosed->id,
            'assessment_applicability_id' => $appClosed->id,
            'mark_value' => '78.00',
            'result_status' => MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->attendanceClosedYear = Attendance::forceCreate([
            'student_academic_record_id' => $sarClosed->id,
            'term_id' => $termClosed->id,
            'days_attended' => 90,
            'total_working_days' => 95,
            'entered_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        // Teacher A assignments (multi-assignment example from prompt):
        // 1. 2026/27, 8A, Mathematics, Subject Teacher
        $this->assign8AMath = TeacherAssignment::forceCreate([
            'user_id' => $this->teacherA->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectMath->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // 2. 2026/27, 8B, Science, Subject Teacher
        $this->assign8BScience = TeacherAssignment::forceCreate([
            'user_id' => $this->teacherA->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8B->id,
            'subject_id' => $this->subjectScience->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // 3. 2026/27, 9A, Class Teacher (subject_id = null)
        $this->assign9AClass = TeacherAssignment::forceCreate([
            'user_id' => $this->teacherA->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class9->id,
            'section_id' => $this->section9A->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // Also assign Teacher A to the closed year subject for closed-year read-only testing
        TeacherAssignment::forceCreate([
            'user_id' => $this->teacherA->id,
            'academic_year_id' => $this->yearClosed->id,
            'class_id' => $this->class8->id,
            'section_id' => $sectionClosed->id,
            'subject_id' => $this->subjectMath->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
            'is_active' => true,
        ]);
    }

    public function test_teacher_with_multiple_assignments_evaluates_all_active_scopes_together(): void
    {
        // 1. Can view/edit 8A Mathematics (Assignment 1)
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8AMath));
        $this->assertTrue($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark8AMath));
        $this->assertTrue(Gate::forUser($this->teacherA)->allows('update', $this->mark8AMath));

        // 2. CANNOT view/edit 8A Science (exact subject constraint)
        $this->assertFalse($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8AScience));
        $this->assertFalse($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark8AScience));
        $this->assertFalse(Gate::forUser($this->teacherA)->allows('update', $this->mark8AScience));

        // 3. Can view/edit 8B Science (Assignment 2)
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8BScience));
        $this->assertTrue($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark8BScience));
        $this->assertTrue(Gate::forUser($this->teacherA)->allows('update', $this->mark8BScience));

        // 4. Can view/edit 9A Mathematics AND 9A Science via Class Teacher scope (Assignment 3)
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark9AMath));
        $this->assertTrue($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark9AMath));
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark9AScience));
        $this->assertTrue($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark9AScience));

        // 5. Attendance authority: Class Teacher has authority in 9A, but ZERO authority in 8A
        $this->assertTrue($this->teacherAuth->userCanEditAttendance($this->teacherA, $this->attendance9A));
        $this->assertFalse($this->teacherAuth->userCanEditAttendance($this->teacherA, $this->attendance8A));

        // 6. Report generation: Class Teacher can generate/download for 9A, but NOT 8A
        $this->assertTrue($this->teacherAuth->userCanGenerateReport($this->teacherA, $this->yearOpen->id, $this->class9->id, $this->section9A->id));
        $this->assertFalse($this->teacherAuth->userCanGenerateReport($this->teacherA, $this->yearOpen->id, $this->class8->id, $this->section8A->id));

        $this->assertTrue($this->teacherAuth->userCanDownloadReport($this->teacherA, $this->report9A));
        $this->assertFalse($this->teacherAuth->userCanDownloadReport($this->teacherA, $this->report8A));
    }

    public function test_inactive_assignment_is_completely_denied(): void
    {
        // Assignment created with is_active = false
        TeacherAssignment::forceCreate([
            'user_id' => $this->teacherB->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectMath->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'is_active' => false,
        ]);

        $this->assertFalse($this->teacherAuth->userCanViewMark($this->teacherB, $this->mark8AMath));
        $this->assertFalse($this->teacherAuth->userCanEditMark($this->teacherB, $this->mark8AMath));
    }

    public function test_future_assignment_is_denied_until_effective_date(): void
    {
        // Assignment starting next year
        TeacherAssignment::forceCreate([
            'user_id' => $this->teacherB->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectMath->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => now()->addDays(10)->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        $this->assertFalse($this->teacherAuth->userCanViewMark($this->teacherB, $this->mark8AMath));
        $this->assertFalse($this->teacherAuth->userCanEditMark($this->teacherB, $this->mark8AMath));
    }

    public function test_expired_assignment_is_denied_after_effective_to_date(): void
    {
        // Assignment that expired yesterday
        TeacherAssignment::forceCreate([
            'user_id' => $this->teacherB->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectMath->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => now()->subMonths(2)->toDateString(),
            'effective_to' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertFalse($this->teacherAuth->userCanViewMark($this->teacherB, $this->mark8AMath));
        $this->assertFalse($this->teacherAuth->userCanEditMark($this->teacherB, $this->mark8AMath));
    }

    public function test_dynamic_assignment_deactivation_takes_immediate_effect_without_relogin(): void
    {
        // Teacher A initially authorized for 8A Mathematics
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8AMath));

        // Administrator deactivates 8A Mathematics assignment in database directly
        TeacherAssignment::where('id', $this->assign8AMath->id)->update(['is_active' => false]);

        // Without any logout/login: access must immediately disappear!
        $this->assertFalse($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8AMath));
        $this->assertFalse($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark8AMath));
    }

    public function test_dynamic_assignment_addition_takes_immediate_effect_without_relogin(): void
    {
        // Teacher A initially has NO access to 8A Science
        $this->assertFalse($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8AScience));

        // Administrator adds active 8A Science assignment in database directly
        TeacherAssignment::forceCreate([
            'user_id' => $this->teacherA->id,
            'academic_year_id' => $this->yearOpen->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->section8A->id,
            'subject_id' => $this->subjectScience->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        // Without logout/login: new scope is immediately available!
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->mark8AScience));
        $this->assertTrue($this->teacherAuth->userCanEditMark($this->teacherA, $this->mark8AScience));
    }

    public function test_cross_classroom_idor_is_blocked_by_relational_graph(): void
    {
        // Teacher A has Class Teacher role for 9A.
        // If an attacker tampers mark_id to access 8A Science, authorization must resolve the relational graph and reject it.
        $this->assertFalse(Gate::forUser($this->teacherA)->allows('view', $this->mark8AScience));
        $this->assertFalse(Gate::forUser($this->teacherA)->allows('update', $this->mark8AScience));
    }

    public function test_closed_academic_year_restricts_teachers_to_read_only(): void
    {
        // In a closed academic year:
        // Teacher CAN VIEW the mark if assigned
        $this->assertTrue($this->teacherAuth->userCanViewMark($this->teacherA, $this->markClosedYear));
        $this->assertTrue(Gate::forUser($this->teacherA)->allows('view', $this->markClosedYear));

        // Teacher CANNOT EDIT the mark (must be read-only!)
        $this->assertFalse($this->teacherAuth->userCanEditMark($this->teacherA, $this->markClosedYear));
        $this->assertFalse(Gate::forUser($this->teacherA)->allows('update', $this->markClosedYear));

        // Administrator and Office Staff CAN still edit marks in closed year (administrative correction)
        $this->assertTrue($this->teacherAuth->userCanEditMark($this->adminUser, $this->markClosedYear));
        $this->assertTrue(Gate::forUser($this->adminUser)->allows('update', $this->markClosedYear));

        $this->assertTrue($this->teacherAuth->userCanEditMark($this->officeStaffUser, $this->markClosedYear));
        $this->assertTrue(Gate::forUser($this->officeStaffUser)->allows('update', $this->markClosedYear));

        // Attendance write in closed year: Teacher blocked, Admin allowed
        $this->assertFalse($this->teacherAuth->userCanEditAttendance($this->teacherA, $this->attendanceClosedYear));
        $this->assertTrue($this->teacherAuth->userCanEditAttendance($this->adminUser, $this->attendanceClosedYear));
        $this->assertTrue($this->teacherAuth->userCanEditAttendance($this->officeStaffUser, $this->attendanceClosedYear));
    }
}
