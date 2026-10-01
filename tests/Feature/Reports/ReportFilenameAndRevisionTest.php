<?php

namespace Tests\Feature\Reports;

use App\Contracts\ReportGeneratorContract;
use App\Enums\AcademicYearStatus;
use App\Enums\ReportType;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
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
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Report\ReportGenerationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportFilenameAndRevisionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $classTeacher;
    protected User $unauthorizedTeacher;
    protected AcademicYear $year;
    protected SchoolClass $class;
    protected Section $section;
    protected Student $student;
    protected StudentAcademicRecord $sar;
    protected ReportConfiguration $config;
    protected Assessment $annualExam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ReportGeneratorContract::class, function () {
            return new class implements ReportGeneratorContract {
                public function generatePdfFromHtml(string $html): string
                {
                    return "%PDF-1.4\n1 0 obj\n<< /Title (Test PDF) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
                }
            };
        });

        $adminRole = Role::firstOrCreate(['name' => 'Administrator'], ['description' => 'Admin']);
        $ctRole = Role::firstOrCreate(['name' => 'Class Teacher'], ['description' => 'CT']);
        $stRole = Role::firstOrCreate(['name' => 'Subject Teacher'], ['description' => 'ST']);

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_fn_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin FN',
            'is_active' => true,
        ]);

        $this->classTeacher = User::forceCreate([
            'role_id' => $ctRole->id,
            'username' => 'ct_fn_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'CT FN',
            'is_active' => true,
        ]);

        $this->unauthorizedTeacher = User::forceCreate([
            'role_id' => $stRole->id,
            'username' => 'unauth_fn_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Unauth Teacher',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create(['name' => 'Class 1', 'is_active' => true]);
        $this->section = Section::create(['academic_year_id' => $this->year->id, 'class_id' => $this->class->id, 'name' => 'A', 'is_active' => true]);

        TeacherAssignment::create([
            'user_id' => $this->classTeacher->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => null,
            'assignment_type' => TeacherAssignmentType::CLASS_TEACHER,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'student_name' => 'Kumar V',
            'admission_number' => 'SVS-006',
        ]);

        $this->sar = StudentAcademicRecord::create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '101',
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);

        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH_' . uniqid(), 'is_active' => true]);
        $cs = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        $alloc = StudentSubjectAllocation::create([
            'student_academic_record_id' => $this->sar->id,
            'class_subject_id' => $cs->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $asmtType = AssessmentType::firstOrCreate(['name' => 'Annual Exam'], ['is_active' => true]);
        $this->annualExam = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $asmtType->id,
            'term_id' => null,
            'name' => 'Annual Exam',
            'status' => 'active',
        ]);

        $app = AssessmentApplicability::create([
            'assessment_id' => $this->annualExam->id,
            'class_subject_id' => $cs->id,
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        Mark::create([
            'student_academic_record_id' => $this->sar->id,
            'student_subject_allocation_id' => $alloc->id,
            'assessment_applicability_id' => $app->id,
            'mark_value' => '88.00',
            'result_status' => \App\Enums\MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $this->config = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Annual Exam',
            'report_type' => ReportType::FINAL,
            'is_active' => true,
        ]);

        ReportAssessmentSelection::create([
            'report_configuration_id' => $this->config->id,
            'assessment_id' => $this->annualExam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        SchoolSetting::firstOrCreate([], [
            'school_name' => 'Demonstration School',
            'school_logo_path' => 'logos/demo.png',
            'pass_mark' => 35.00,
        ]);

        \App\Models\CalculationSetting::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => \App\Enums\CalculationMethod::COMBINED_MARKS,
        ]);
    }

    public function test_human_readable_filename_matches_exact_example(): void
    {
        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $filename = $service->buildHumanReadableFilename(
            $this->sar,
            ReportType::FINAL,
            null,
            null,
            $this->config
        );

        // Expected: Kumar_V_1_A_SVS-006_Annual_Exam.pdf
        $this->assertEquals('Kumar_V_1_A_SVS-006_Annual_Exam.pdf', $filename);
    }

    public function test_special_characters_and_whitespace_are_safely_sanitized(): void
    {
        $dirtyStudent = Student::create([
            'student_name' => 'John / Jane   Doe...#1',
            'admission_number' => 'ADM:2026/01',
        ]);

        $dirtySar = StudentAcademicRecord::create([
            'student_id' => $dirtyStudent->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '102',
            'status' => 'active',
            'effective_from' => '2026-06-01',
        ]);

        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $filename = $service->buildHumanReadableFilename(
            $dirtySar,
            ReportType::FINAL,
            null,
            null,
            $this->config
        );

        // Forward slashes, colons, hashes, dots converted/removed, no path traversal possible
        $this->assertStringNotContainsString('/', $filename);
        $this->assertStringNotContainsString('\\', $filename);
        $this->assertStringNotContainsString(':', $filename);
        $this->assertStringNotContainsString('..', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertEquals('John_Jane_Doe_1_1_A_ADM_2026_01_Annual_Exam.pdf', $filename);
    }

    public function test_download_response_delivers_human_readable_filename_header(): void
    {
        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $report = $service->generate(
            sarId: $this->sar->id,
            reportType: 'final',
            termId: null,
            assessmentId: null,
            user: $this->admin,
            reportConfigurationId: $this->config->id
        );

        $response = $this->actingAs($this->admin)->get(route('reports.download', $report));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('Kumar_V_1_A_SVS-006_Annual_Exam.pdf', $disposition);
    }

    public function test_multi_revisions_stored_in_isolated_directories_without_collision(): void
    {
        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        // Generate Revision 1
        $rev1 = $service->generate(
            sarId: $this->sar->id,
            reportType: 'final',
            termId: null,
            assessmentId: null,
            user: $this->admin,
            reportConfigurationId: $this->config->id
        );

        $this->assertEquals(1, $rev1->revision_number);
        $path1 = storage_path('app/private/' . $rev1->file_path);
        $this->assertFileExists($path1);
        $this->assertStringContainsString('/rev_1/', str_replace('\\', '/', $rev1->file_path));

        // Generate Revision 2
        $rev2 = $service->generate(
            sarId: $this->sar->id,
            reportType: 'final',
            termId: null,
            assessmentId: null,
            user: $this->admin,
            reportConfigurationId: $this->config->id
        );

        $this->assertEquals(2, $rev2->revision_number);
        $path2 = storage_path('app/private/' . $rev2->file_path);
        $this->assertFileExists($path2);
        $this->assertStringContainsString('/rev_2/', str_replace('\\', '/', $rev2->file_path));

        // Both revisions must exist concurrently on disk without overwriting each other
        $this->assertNotEquals($path1, $path2);
        $this->assertFileExists($path1);
        $this->assertFileExists($path2);

        // Both download successfully
        $resp1 = $this->actingAs($this->admin)->get(route('reports.download', $rev1));
        $resp1->assertOk();
        $this->assertStringContainsString('Kumar_V_1_A_SVS-006_Annual_Exam.pdf', $resp1->headers->get('content-disposition'));

        $resp2 = $this->actingAs($this->admin)->get(route('reports.download', $rev2));
        $resp2->assertOk();
        $this->assertStringContainsString('Kumar_V_1_A_SVS-006_Annual_Exam.pdf', $resp2->headers->get('content-disposition'));
    }

    public function test_historical_report_with_legacy_path_remains_downloadable(): void
    {
        // Simulate a historical report stored with the legacy path format: reports/.../revision-1.pdf
        $legacyRelative = "reports/{$this->year->id}/{$this->class->id}/{$this->section->id}/{$this->sar->id}/final/revision-1.pdf";
        $legacyAbsolute = storage_path('app/private/' . $legacyRelative);
        File::ensureDirectoryExists(dirname($legacyAbsolute));
        File::put($legacyAbsolute, "%PDF-1.4\nLegacy Historical Report\n%%EOF");

        $historicalReport = GeneratedReport::create([
            'student_academic_record_id' => $this->sar->id,
            'report_type' => ReportType::FINAL,
            'term_id' => null,
            'assessment_id' => null,
            'revision_number' => 1,
            'file_path' => $legacyRelative,
            'generated_by_user_id' => $this->admin->id,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.download', $historicalReport));
        $response->assertOk();
        $this->assertStringContainsString('Kumar_V_1_A_SVS-006_Annual_Exam.pdf', $response->headers->get('content-disposition'));
    }

    public function test_unauthorized_teacher_denied_report_download(): void
    {
        /** @var ReportGenerationService $service */
        $service = app(ReportGenerationService::class);

        $report = $service->generate(
            sarId: $this->sar->id,
            reportType: 'final',
            termId: null,
            assessmentId: null,
            user: $this->admin,
            reportConfigurationId: $this->config->id
        );

        $response = $this->actingAs($this->unauthorizedTeacher)->get(route('reports.download', $report));
        $response->assertForbidden();
    }
}
