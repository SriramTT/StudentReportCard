<?php

namespace Tests\Feature\Student;

use App\Enums\StudentPlacementStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\User;
use App\Services\StudentImportService;
use App\Services\StudentPlacementService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StudentDirectoryAndPlacementRelationalIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected AcademicYear $yearCurrent;
    protected AcademicYear $yearPast;
    protected SchoolClass $class1;
    protected SchoolClass $class8;
    protected Section $sec1A;
    protected Section $sec1B;
    protected Section $sec8A;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertEquals('school_report_card_audit', DB::connection()->getDatabaseName());

        $suffix = rand(1000, 9999);

        $this->yearCurrent = AcademicYear::create([
            'name' => "2026-{$suffix}",
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'is_current' => true,
            'status' => 'open',
        ]);

        $this->yearPast = AcademicYear::create([
            'name' => "2025-{$suffix}",
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $this->class1 = SchoolClass::create(['name' => "Class 1 {$suffix}"]);
        $this->class8 = SchoolClass::create(['name' => "Class 8 {$suffix}"]);

        $this->sec1A = Section::create([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->sec1B = Section::create([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'name' => 'B',
            'is_active' => true,
        ]);

        $this->sec8A = Section::create([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'role_id' => 1,
            'username' => 'admin_test_' . uniqid(),
            'email' => 'admin_' . uniqid() . '@school.test',
            'password_hash' => bcrypt('password'),
            'display_name' => 'Admin Test',
            'is_active' => true,
        ]);
    }

    public function test_01_correct_class_and_section_returns_student(): void
    {
        $student = Student::create(['admission_number' => 'ADM-T01', 'student_name' => 'Alice']);
        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $studentService = app(StudentService::class);
        $results = $studentService->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
        ], 25, $this->adminUser);

        $this->assertEquals(1, $results->total());
        $this->assertEquals('ADM-T01', $results->first()->admission_number);
    }

    public function test_02_same_section_name_across_different_classes_does_not_cause_cross_class_leakage(): void
    {
        // Student in Class 8 Section A (both sections named 'A')
        $student8 = Student::create(['admission_number' => 'ADM-8A01', 'student_name' => 'Bob Class8']);
        StudentAcademicRecord::create([
            'student_id' => $student8->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8A->id, // Name 'A', but class 8
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        // Student in Class 1 Section A
        $student1 = Student::create(['admission_number' => 'ADM-1A01', 'student_name' => 'Charlie Class1']);
        StudentAcademicRecord::create([
            'student_id' => $student1->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id, // Name 'A', class 1
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $studentService = app(StudentService::class);

        // Filter for Class 1 Section A (sec1A)
        $results1 = $studentService->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
        ], 25, $this->adminUser);

        $this->assertEquals(1, $results1->total());
        $this->assertEquals('ADM-1A01', $results1->first()->admission_number);

        // Filter for Class 8 Section A (sec8A)
        $results8 = $studentService->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class8->id,
            'section_id' => $this->sec8A->id,
        ], 25, $this->adminUser);

        $this->assertEquals(1, $results8->total());
        $this->assertEquals('ADM-8A01', $results8->first()->admission_number);
    }

    public function test_03_mismatched_section_from_another_class_does_not_return_student(): void
    {
        // Student erroneously seeded with class_id = Class 1 but section_id = sec8A
        $studentMismatched = Student::create(['admission_number' => 'ADM-CORRUPT', 'student_name' => 'Corrupt']);
        StudentAcademicRecord::create([
            'student_id' => $studentMismatched->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec8A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $studentService = app(StudentService::class);

        // When filtering Class 1 Section A (sec1A.id), corrupt student must NOT appear
        $results = $studentService->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
        ], 25, $this->adminUser);

        $this->assertEquals(0, $results->total());
    }

    public function test_04_historical_academic_year_does_not_leak_into_current_year(): void
    {
        $student = Student::create(['admission_number' => 'ADM-HIST', 'student_name' => 'Historical Student']);
        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->yearPast->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2025-06-01',
        ]);

        $studentService = app(StudentService::class);
        $results = $studentService->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
        ], 25, $this->adminUser);

        $this->assertEquals(0, $results->total());
    }

    public function test_05_inactive_placement_excluded_by_default(): void
    {
        $student = Student::create(['admission_number' => 'ADM-INACTIVE', 'student_name' => 'Transferred Out']);
        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::TRANSFERRED_OUT,
            'effective_from' => '2026-06-01',
        ]);

        $studentService = app(StudentService::class);
        $results = $studentService->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
        ], 25, $this->adminUser);

        $this->assertEquals(0, $results->total());
    }

    public function test_06_student_service_create_rejects_section_class_mismatch(): void
    {
        $studentService = app(StudentService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected section does not belong to the selected class.');

        $studentService->createStudent([
            'admission_number' => 'ADM-BAD-CREATE',
            'student_name' => 'Bad Create',
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec8A->id, // Section belonging to Class 8!
            'roll_number' => 1,
        ], $this->adminUser->id);
    }

    public function test_07_student_placement_transfer_rejects_section_class_mismatch(): void
    {
        $student = Student::create(['admission_number' => 'ADM-XFER', 'student_name' => 'Transfer Me']);
        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $placementService = app(StudentPlacementService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected section does not belong to the selected class.');

        $placementService->transferStudent($student, [
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec8A->id, // Mismatched section!
            'roll_number' => 2,
            'effective_date' => '2026-09-01',
        ], $this->adminUser->id);
    }

    public function test_08_student_import_rejects_section_class_mismatch(): void
    {
        $importService = app(StudentImportService::class);

        $csvContent = "admission_number,student_name,roll_number\nADM-IMP-01,Import Person,1\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected section does not belong to the selected class.');

        $importService->import(
            $file,
            $this->yearCurrent->id,
            $this->class1->id,
            $this->sec8A->id, // Mismatched section!
            $this->adminUser->id
        );
    }

    public function test_09_http_post_students_with_mismatched_section_returns_validation_error(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('students.store'), [
            'admission_number' => 'ADM-HTTP-BAD',
            'student_name' => 'Http Bad',
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec8A->id, // Section belonging to Class 8
            'roll_number' => 1,
        ]);

        $response->assertSessionHasErrors('section_id');
    }

    public function test_10_http_post_transfer_with_mismatched_section_returns_validation_error(): void
    {
        $student = Student::create(['admission_number' => 'ADM-HTTP-XFER', 'student_name' => 'Http Xfer']);
        StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 1,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('students.transfer', $student), [
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec8A->id, // Section belonging to Class 8
            'roll_number' => 2,
            'effective_date' => '2026-09-01',
        ]);

        $response->assertSessionHasErrors('section_id');
    }

    public function test_11_natural_admission_number_sorting(): void
    {
        // Clean out any conflicting admission numbers created in this test run
        Student::whereIn('admission_number', [
            'SVS-001', 'SVS-002', 'SVS-010', 'SVS-0012', 'SV-1', 'SV-2', 'SV-12'
        ])->delete();

        // Create in intentionally randomized order
        $testNumbers = ['SVS-010', 'SV-12', 'SVS-0012', 'SV-1', 'SVS-002', 'SV-2', 'SVS-001'];
        foreach ($testNumbers as $idx => $adm) {
            Student::create([
                'admission_number' => $adm,
                'student_name' => "Sort Student {$idx}",
            ]);
        }

        $service = app(StudentService::class);
        $paginator = $service->getPaginatedStudents([], 100, $this->adminUser);

        $orderedAdm = collect($paginator->items())
            ->pluck('admission_number')
            ->filter(fn ($adm) => in_array($adm, $testNumbers, true))
            ->values()
            ->all();

        $expected = ['SV-1', 'SV-2', 'SV-12', 'SVS-001', 'SVS-002', 'SVS-010', 'SVS-0012'];
        $this->assertSame($expected, $orderedAdm);
    }

    public function test_12_all_statuses_filter_includes_inactive_and_active_placements(): void
    {
        $studentActive = Student::create(['admission_number' => 'ADM-ACT-FLT', 'student_name' => 'Active Student']);
        StudentAcademicRecord::create([
            'student_id' => $studentActive->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 88,
            'status' => StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $studentInactive = Student::create(['admission_number' => 'ADM-XFR-FLT', 'student_name' => 'Transferred Student']);
        StudentAcademicRecord::create([
            'student_id' => $studentInactive->id,
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'roll_number' => 89,
            'status' => StudentPlacementStatus::TRANSFERRED_OUT,
            'effective_from' => '2026-06-01',
        ]);

        $service = app(StudentService::class);

        // 1. Default (no status): inactive student excluded
        $defaultResults = $service->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
        ], 25, $this->adminUser);
        $defaultIds = collect($defaultResults->items())->pluck('id')->all();
        $this->assertContains($studentActive->id, $defaultIds);
        $this->assertNotContains($studentInactive->id, $defaultIds);

        // 2. Explicit 'all': both active and transferred_out students included
        $allResults = $service->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'status' => 'all',
        ], 25, $this->adminUser);
        $allIds = collect($allResults->items())->pluck('id')->all();
        $this->assertContains($studentActive->id, $allIds);
        $this->assertContains($studentInactive->id, $allIds);

        // 3. Explicit 'transferred_out': only transferred_out student included
        $transferredResults = $service->getPaginatedStudents([
            'academic_year_id' => $this->yearCurrent->id,
            'class_id' => $this->class1->id,
            'section_id' => $this->sec1A->id,
            'status' => 'transferred_out',
        ], 25, $this->adminUser);
        $transferredIds = collect($transferredResults->items())->pluck('id')->all();
        $this->assertNotContains($studentActive->id, $transferredIds);
        $this->assertContains($studentInactive->id, $transferredIds);
    }
}

