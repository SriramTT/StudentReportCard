<?php

namespace Tests\Feature\Attendance;

use App\Enums\AcademicYearStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\AuditService;
use App\Services\TeacherAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected AttendanceService $attendanceService;

    protected User $admin;
    protected User $officeStaff;
    protected User $classTeacher;
    protected User $otherClassTeacher;
    protected User $subjectTeacher;

    protected AcademicYear $year;
    protected AcademicYear $closedYear;
    protected Term $term1;
    protected Term $term2;
    protected SchoolClass $class;
    protected Section $sectionA;
    protected Section $sectionB;
    protected Student $student1;
    protected Student $student2;
    protected StudentAcademicRecord $sar1;
    protected StudentAcademicRecord $sar2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendanceService = app(AttendanceService::class);

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Class Teacher')->firstOrFail();
        $subTeacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_att_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Att',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_att_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Att',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'ct_att_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Class Teacher Att',
            'is_active' => true,
        ]);

        $this->otherClassTeacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'oct_att_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Other CT Att',
            'is_active' => true,
        ]);

        $this->subjectTeacher = User::forceCreate([
            'role_id' => $subTeacherRole->id,
            'username' => 'st_att_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Subject Teacher Att',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_ATT_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->closedYear = AcademicYear::create([
            'name' => 'AY_CLOSED_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $this->term1 = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $this->term2 = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 2',
            'sequence_no' => 2,
            'is_active' => false,
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Class_Att_' . uniqid(),
            'is_active' => true,
        ]);

        $this->sectionA = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->sectionB = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'name' => 'B',
            'is_active' => true,
        ]);

        // Assign classTeacher to Section A
        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
        ]);

        // Assign otherClassTeacher to Section B
        TeacherAssignment::create([
            'user_id' => $this->otherClassTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
        ]);

        $this->student1 = Student::create([
            'admission_number' => 'ADM_ATT1_' . rand(10000, 99999),
            'student_name' => 'Alice Smith',
        ]);

        $this->student2 = Student::create([
            'admission_number' => 'ADM_ATT2_' . rand(10000, 99999),
            'student_name' => 'Bob Jones',
        ]);

        $this->sar1 = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 1,
            'effective_from' => '2026-06-01',
            'status' => StudentPlacementStatus::ACTIVE,
        ]);

        $this->sar2 = StudentAcademicRecord::create([
            'student_id' => $this->student2->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_number' => 2,
            'effective_from' => '2026-06-01',
            'status' => StudentPlacementStatus::ACTIVE,
        ]);
    }

    // =========================================================================
    // 1. ATTENDANCE CALCULATION & BOUNDS (Prompt Item 50)
    // =========================================================================

    public function test_attendance_calculations_normal_cases(): void
    {
        // 85 / 100 = 85.00%
        $res1 = $this->attendanceService->calculateAttendance(85, 100);
        $this->assertEquals(85.00, $res1->percentage);
        $this->assertEquals('85.00%', $res1->formattedPercentage);
        $this->assertTrue($res1->isAvailable);

        // 100 / 100 = 100.00%
        $res2 = $this->attendanceService->calculateAttendance(100, 100);
        $this->assertEquals(100.00, $res2->percentage);
        $this->assertEquals('100.00%', $res2->formattedPercentage);

        // 0 / 90 = 0.00%
        $res3 = $this->attendanceService->calculateAttendance(0, 90);
        $this->assertEquals(0.00, $res3->percentage);
        $this->assertEquals('0.00%', $res3->formattedPercentage);

        // 0 / 0 = N/A
        $res4 = $this->attendanceService->calculateAttendance(0, 0);
        $this->assertNull($res4->percentage);
        $this->assertEquals('N/A', $res4->formattedPercentage);
        $this->assertFalse($res4->isAvailable);
    }

    public function test_attendance_validation_rejects_exceeded_attended_days(): void
    {
        // 95 / 90: days attended exceeds total working days
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'days_attended' => 95,
                    'total_working_days' => 90,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('attendance.batch_save'), $payload);
        $response->assertSessionHasErrors();
    }

    public function test_attendance_validation_rejects_negative_values(): void
    {
        // -5 / 50
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'days_attended' => -5,
                    'total_working_days' => 50,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('attendance.batch_save'), $payload);
        $response->assertSessionHasErrors();
    }

    // =========================================================================
    // 2. ATTENDANCE AUTHORIZATION (Prompt Item 51)
    // =========================================================================

    public function test_administrator_and_office_staff_can_save_attendance(): void
    {
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'days_attended' => 45,
                    'total_working_days' => 50,
                ],
            ],
        ];

        // 1. Admin saves
        $adminResponse = $this->actingAs($this->admin)->post(route('attendance.batch_save'), $payload);
        $adminResponse->assertRedirect();
        $this->assertDatabaseHas('attendance', [
            'student_academic_record_id' => $this->sar1->id,
            'term_id' => $this->term1->id,
            'days_attended' => 45,
            'total_working_days' => 50,
        ]);

        // 2. Office Staff saves
        $payload['attendance_records'][0]['days_attended'] = 48;
        $officeResponse = $this->actingAs($this->officeStaff)->post(route('attendance.batch_save'), $payload);
        $officeResponse->assertRedirect();
        $this->assertDatabaseHas('attendance', [
            'student_academic_record_id' => $this->sar1->id,
            'days_attended' => 48,
        ]);
    }

    public function test_class_teacher_can_save_attendance_for_assigned_classroom(): void
    {
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'days_attended' => 40,
                    'total_working_days' => 50,
                ],
            ],
        ];

        $response = $this->actingAs($this->classTeacher)->post(route('attendance.batch_save'), $payload);
        $response->assertRedirect();
        $this->assertDatabaseHas('attendance', [
            'student_academic_record_id' => $this->sar1->id,
            'term_id' => $this->term1->id,
            'days_attended' => 40,
        ]);
    }

    public function test_class_teacher_cannot_save_attendance_for_unassigned_classroom(): void
    {
        // otherClassTeacher (assigned to Section B) tries to save attendance for Section A
        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'days_attended' => 40,
                    'total_working_days' => 50,
                ],
            ],
        ];

        $response = $this->actingAs($this->otherClassTeacher)->post(route('attendance.batch_save'), $payload);
        $response->assertForbidden();
    }

    public function test_subject_teacher_denied_attendance_access(): void
    {
        // Subject teacher has no attendance permissions
        $responseIndex = $this->actingAs($this->subjectTeacher)->get(route('attendance.index'));
        $responseIndex->assertForbidden();

        $payload = [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $this->sar1->id,
                    'days_attended' => 40,
                    'total_working_days' => 50,
                ],
            ],
        ];

        $responseSave = $this->actingAs($this->subjectTeacher)->post(route('attendance.batch_save'), $payload);
        $responseSave->assertForbidden();
    }

    public function test_teacher_cannot_edit_attendance_in_closed_academic_year(): void
    {
        // Setup a placement and assignment in closed year
        $sectionClosed = Section::create([
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'name' => 'ClosedSec',
            'is_active' => true,
        ]);

        $termClosed = Term::create([
            'academic_year_id' => $this->closedYear->id,
            'name' => 'Closed Term',
            'sequence_no' => 1,
            'is_active' => false,
        ]);

        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'section_id' => $sectionClosed->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2025-06-01',
        ]);

        $sarClosed = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'section_id' => $sectionClosed->id,
            'roll_number' => 10,
            'effective_from' => '2025-06-01',
            'status' => StudentPlacementStatus::ACTIVE,
        ]);

        $payload = [
            'academic_year_id' => $this->closedYear->id,
            'class_id' => $this->class->id,
            'section_id' => $sectionClosed->id,
            'term_id' => $termClosed->id,
            'attendance_records' => [
                [
                    'student_academic_record_id' => $sarClosed->id,
                    'days_attended' => 45,
                    'total_working_days' => 50,
                ],
            ],
        ];

        // 1. Teacher gets 403 in closed year
        $teacherResponse = $this->actingAs($this->classTeacher)->post(route('attendance.batch_save'), $payload);
        $teacherResponse->assertForbidden();

        // 2. Administrator has authorized correction access in closed year
        $adminResponse = $this->actingAs($this->admin)->post(route('attendance.batch_save'), $payload);
        $adminResponse->assertRedirect();
        $this->assertDatabaseHas('attendance', [
            'student_academic_record_id' => $sarClosed->id,
            'term_id' => $termClosed->id,
            'days_attended' => 45,
        ]);
    }

    // =========================================================================
    // 3. HISTORICAL CONTEXT & ISOLATION (Prompt Item 52)
    // =========================================================================

    public function test_attendance_term_and_historical_placement_isolation(): void
    {
        // 1. Term 1 Attendance: 45 / 50
        $this->actingAs($this->admin)->post(route('attendance.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                ['student_academic_record_id' => $this->sar1->id, 'days_attended' => 45, 'total_working_days' => 50],
            ],
        ]);

        // 2. Term 2 Attendance: 40 / 45
        $this->actingAs($this->admin)->post(route('attendance.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term2->id,
            'attendance_records' => [
                ['student_academic_record_id' => $this->sar1->id, 'days_attended' => 40, 'total_working_days' => 45],
            ],
        ]);

        $attTerm1 = Attendance::where('student_academic_record_id', $this->sar1->id)
            ->where('term_id', $this->term1->id)
            ->firstOrFail();
        $attTerm2 = Attendance::where('student_academic_record_id', $this->sar1->id)
            ->where('term_id', $this->term2->id)
            ->firstOrFail();

        $this->assertEquals(45, $attTerm1->days_attended);
        $this->assertEquals(40, $attTerm2->days_attended);

        // 3. Transferred student preserves historical placement attendance
        // Terminate sar1 and create new placement in Section B
        $this->sar1->update(['status' => StudentPlacementStatus::INTERNAL_TRANSFER]);
        $sarTransferred = StudentAcademicRecord::create([
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 15,
            'effective_from' => '2026-10-01',
            'status' => StudentPlacementStatus::ACTIVE,
        ]);

        // Attendance for historical sar1 in Term 1 is unaltered
        $this->assertDatabaseHas('attendance', [
            'student_academic_record_id' => $this->sar1->id,
            'term_id' => $this->term1->id,
            'days_attended' => 45,
        ]);

        // Record attendance for new placement
        $this->actingAs($this->admin)->post(route('attendance.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'term_id' => $this->term2->id,
            'attendance_records' => [
                ['student_academic_record_id' => $sarTransferred->id, 'days_attended' => 42, 'total_working_days' => 45],
            ],
        ]);

        $this->assertDatabaseHas('attendance', [
            'student_academic_record_id' => $sarTransferred->id,
            'term_id' => $this->term2->id,
            'days_attended' => 42,
        ]);
    }

    // =========================================================================
    // 4. AUDIT LOGGING (Prompt Item 53)
    // =========================================================================

    public function test_attendance_mutations_create_audit_logs_and_no_op_does_not(): void
    {
        // 1. Create produces audit log
        $this->actingAs($this->admin)->post(route('attendance.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                ['student_academic_record_id' => $this->sar1->id, 'days_attended' => 35, 'total_working_days' => 40],
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'CREATE_ATTENDANCE',
            'entity_type' => 'attendance',
        ]);

        $auditCountAfterCreate = AuditLog::where('action', 'like', '%ATTENDANCE%')->count();

        // 2. Submit identical values (No-op) -> must not produce new audit record
        $this->actingAs($this->admin)->post(route('attendance.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                ['student_academic_record_id' => $this->sar1->id, 'days_attended' => 35, 'total_working_days' => 40],
            ],
        ]);

        $this->assertEquals($auditCountAfterCreate, AuditLog::where('action', 'like', '%ATTENDANCE%')->count());

        // 3. Update with changed values -> produces UPDATE_ATTENDANCE audit log
        $this->actingAs($this->admin)->post(route('attendance.batch_save'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
            'attendance_records' => [
                ['student_academic_record_id' => $this->sar1->id, 'days_attended' => 38, 'total_working_days' => 40],
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'UPDATE_ATTENDANCE',
            'entity_type' => 'attendance',
        ]);
    }

    public function test_attendance_roster_resolves_actual_student_name_and_admission_number_contextually(): void
    {
        // Create an unrelated student in Section B
        $unrelatedStudent = Student::create([
            'admission_number' => 'ADM_UNRELATED_' . rand(10000, 99999),
            'student_name' => 'Charlie Unrelated',
        ]);

        StudentAcademicRecord::create([
            'student_id' => $unrelatedStudent->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'roll_number' => 99,
            'effective_from' => '2026-06-01',
            'status' => StudentPlacementStatus::ACTIVE,
        ]);

        // 1. Direct service verification
        $context = $this->attendanceService->loadAttendanceContext($this->admin, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
        ]);

        $roster = $context['roster'];
        $this->assertCount(2, $roster);

        $row1 = $roster->firstWhere('student_academic_record_id', $this->sar1->id);
        $this->assertNotNull($row1);
        $this->assertEquals($this->student1->admission_number, $row1['admission_number']);
        $this->assertEquals('Alice Smith', $row1['student_name']);
        $this->assertNotEquals('Unknown', $row1['student_name']);

        $row2 = $roster->firstWhere('student_academic_record_id', $this->sar2->id);
        $this->assertNotNull($row2);
        $this->assertEquals($this->student2->admission_number, $row2['admission_number']);
        $this->assertEquals('Bob Jones', $row2['student_name']);
        $this->assertNotEquals('Unknown', $row2['student_name']);

        // 2. HTTP response HTML verification
        $response = $this->actingAs($this->admin)->get(route('attendance.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'term_id' => $this->term1->id,
        ]));

        $response->assertOk();
        $response->assertSee('Alice Smith');
        $response->assertSee('Bob Jones');
        $response->assertSee($this->student1->admission_number);
        $response->assertSee($this->student2->admission_number);
        $response->assertDontSee('Charlie Unrelated');
        $response->assertDontSee('Unknown');
    }

    // =========================================================================
    // 5. PHASE 14 FOLLOW-UP: TEACHER ASSIGNMENT PREDICATE & CLASS TEACHER SCOPE
    // =========================================================================

    public function test_teacher_assignment_model_domain_predicates(): void
    {
        $ctAssignment = TeacherAssignment::where('user_id', $this->classTeacher->id)
            ->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
            ->firstOrFail();

        $this->assertTrue($ctAssignment->isClassTeacher());
        $this->assertFalse($ctAssignment->isSubjectTeacher());

        $subject = \App\Models\Subject::create([
            'name' => 'Predicate Subject',
            'code' => 'PSUB_' . uniqid(),
            'is_active' => true,
        ]);

        $stAssignment = TeacherAssignment::create([
            'user_id' => $this->subjectTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $subject->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
        ]);

        $this->assertFalse($stAssignment->isClassTeacher());
        $this->assertTrue($stAssignment->isSubjectTeacher());
    }

    public function test_class_teacher_can_load_attendance_index_without_exception(): void
    {
        $response = $this->actingAs($this->classTeacher)->get(route('attendance.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('attendance.index');
        $response->assertSee($this->class->name);
        $response->assertSee('Section ' . $this->sectionA->name);
    }

    public function test_class_teacher_with_multiple_assignments_only_receives_class_teacher_sections_for_attendance(): void
    {
        $subject = \App\Models\Subject::create([
            'name' => 'Multi Assignment Subject',
            'code' => 'MASUB_' . uniqid(),
            'is_active' => true,
        ]);

        // Give classTeacher a Subject Teacher assignment in Section B in the same class and year
        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => $subject->id,
            'assignment_type' => TeacherAssignmentType::SUBJECT_TEACHER,
            'effective_from' => '2026-06-01',
        ]);

        // Load attendance context as classTeacher
        $context = $this->attendanceService->loadAttendanceContext($this->classTeacher, [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]);

        $availableSectionIds = $context['availableSections']->pluck('id')->all();

        // Section A (where user is Class Teacher) MUST be present
        $this->assertContains($this->sectionA->id, $availableSectionIds);

        // Section B (where user is ONLY Subject Teacher) MUST NOT be present
        $this->assertNotContains($this->sectionB->id, $availableSectionIds);

        // HTTP GET verification
        $response = $this->actingAs($this->classTeacher)->get(route('attendance.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
        ]));

        $response->assertOk();
        $response->assertSee('Section ' . $this->sectionA->name);
        $response->assertDontSee('Section ' . $this->sectionB->name);
    }

    public function test_class_teacher_tampering_with_unassigned_section_is_denied(): void
    {
        // classTeacher is assigned to Section A, attempts to directly request Section B
        $response = $this->actingAs($this->classTeacher)->get(route('attendance.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'term_id' => $this->term1->id,
        ]));

        // Direct request resets or denies unassigned section
        $response->assertOk();
        // The roster for Section B should not be loaded for classTeacher
        $this->assertNull($response->viewData('selectedSectionId'));
    }
}
