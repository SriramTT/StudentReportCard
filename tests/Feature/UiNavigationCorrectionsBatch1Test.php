<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\ReportType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Report\ReportConfigurationResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UiNavigationCorrectionsBatch1Test extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected AcademicYear $year;
    protected SchoolClass $classA;
    protected SchoolClass $classB;
    protected Section $sectionA1;
    protected Section $sectionA2;
    protected Section $sectionB1;
    protected Subject $subject;
    protected ClassSubject $csA;
    protected ClassSubject $csB;
    protected AssessmentType $examType;
    protected Term $term1;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'Administrator'], ['description' => 'Administrator']);
        $this->admin = User::firstOrCreate(
            ['username' => 'admin_test_ui'],
            [
                'email' => 'admin_test_ui@example.com',
                'display_name' => 'Test Admin',
                'password_hash' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
        $this->admin->setRelation('role', $adminRole);

        $this->year = AcademicYear::create([
            'name' => '2026-27_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->term1 = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1 ' . rand(100, 999),
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $this->classA = SchoolClass::create(['name' => 'Class Alpha ' . uniqid(), 'is_active' => true]);
        $this->classB = SchoolClass::create(['name' => 'Class Beta ' . uniqid(), 'is_active' => true]);

        $this->sectionA1 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'name' => 'A1',
            'is_active' => true,
        ]);

        $this->sectionA2 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'name' => 'A2',
            'is_active' => true,
        ]);

        $this->sectionB1 = Section::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'name' => 'B1',
            'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'name' => 'Mathematics ' . uniqid(),
            'code' => 'MTH_' . rand(100, 999),
            'is_active' => true,
        ]);

        $this->csA = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classA->id,
            'section_id' => $this->sectionA1->id,
            'subject_id' => $this->subject->id,
            'subject_name_snapshot' => $this->subject->name,
            'is_active' => true,
        ]);

        $this->csB = ClassSubject::create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->sectionB1->id,
            'subject_id' => $this->subject->id,
            'subject_name_snapshot' => $this->subject->name,
            'is_active' => true,
        ]);

        $this->examType = AssessmentType::create([
            'name' => 'Term Exam ' . uniqid(),
            'is_active' => true,
        ]);
    }

    // ========================================================
    // ISSUE 1: Duplicate Success / Failure Alerts
    // ========================================================
    public function test_issue_1_reports_page_renders_single_success_alert(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['success' => 'Unique report card generated successfully.'])
            ->get(route('reports.index', ['academic_year_id' => $this->year->id]));

        $response->assertOk();
        $content = $response->getContent();
        // Assert the success message appears exactly once in the rendered HTML
        $count = substr_count($content, 'Unique report card generated successfully.');
        $this->assertSame(1, $count, 'Success alert must render exactly once in the layout, not duplicated.');
    }

    public function test_issue_1_attendance_page_renders_single_success_alert(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['success' => 'Attendance recorded successfully.'])
            ->get(route('attendance.index', ['academic_year_id' => $this->year->id]));

        $response->assertOk();
        $content = $response->getContent();
        $count = substr_count($content, 'Attendance recorded successfully.');
        $this->assertSame(1, $count, 'Attendance success alert must render exactly once.');
    }

    public function test_issue_1_school_settings_renders_single_success_alert(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['success' => 'School settings saved.'])
            ->get(route('admin.school_settings.edit'));

        $response->assertOk();
        $content = $response->getContent();
        $count = substr_count($content, 'School settings saved.');
        $this->assertSame(1, $count, 'Settings success alert must render exactly once.');
    }

    // ========================================================
    // ISSUE 2: Student Directory Contextual Return Navigation
    // ========================================================
    public function test_issue_2_student_edit_returns_to_originating_filtered_directory(): void
    {
        $student = Student::create([
            'admission_number' => 'ADM_TEST_' . rand(1000, 9999),
            'student_name' => 'Context Test Student',
        ]);

        $originatingUrl = route('students.index', [
            'class_id' => $this->classA->id,
            'search' => 'Context',
            'page' => 1,
        ]);

        // 1. Visit edit page with return_url
        $editResponse = $this->actingAs($this->admin)
            ->get(route('students.edit', [$student, 'return_url' => $originatingUrl]));

        $editResponse->assertOk();
        $editResponse->assertSee('class_id=' . $this->classA->id);
        $editResponse->assertSee('search=Context');
        $editResponse->assertSee('name="return_url"', false);

        // 2. Submit update with return_url
        $updateResponse = $this->actingAs($this->admin)
            ->put(route('students.update', $student), [
                'student_name' => 'Updated Context Student',
                'return_url' => $originatingUrl,
            ]);

        $updateResponse->assertRedirect($originatingUrl);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'student_name' => 'Updated Context Student',
        ]);
    }

    public function test_issue_2_student_edit_rejects_external_or_malicious_return_url(): void
    {
        $student = Student::create([
            'admission_number' => 'ADM_SEC_' . rand(1000, 9999),
            'student_name' => 'Security Student',
        ]);

        $maliciousUrls = [
            'https://evil.com/phish',
            '//evil.com/redirect',
            'javascript:alert(1)',
            '/users', // unrelated route
        ];

        foreach ($maliciousUrls as $maliciousUrl) {
            $response = $this->actingAs($this->admin)
                ->put(route('students.update', $student), [
                    'student_name' => 'Security Student Updated',
                    'return_url' => $maliciousUrl,
                ]);

            // Must fall back safely to students.show
            $response->assertRedirect(route('students.show', $student));
        }
    }

    public function test_issue_2_student_transfer_returns_to_profile_when_entered_from_profile(): void
    {
        $student = Student::create([
            'admission_number' => 'ADM_TRF_' . rand(1000, 9999),
            'student_name' => 'Transfer Student',
        ]);

        $profileUrl = route('students.show', $student);

        $transferResponse = $this->actingAs($this->admin)
            ->post(route('students.transfer', $student), [
                'academic_year_id' => $this->year->id,
                'class_id' => $this->classA->id,
                'section_id' => $this->sectionA1->id,
                'roll_number' => rand(1, 99),
                'effective_date' => '2026-09-01',
                'return_url' => $profileUrl,
            ]);

        $transferResponse->assertRedirect($profileUrl);
    }

    // ========================================================
    // ISSUE 3: Assessments Default Sort Order (newest created first)
    // ========================================================
    public function test_issue_3_unfiltered_assessments_order_by_created_at_desc_and_id_desc(): void
    {
        // Assessment 1: created earlier, but later exam date
        $asmt1 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Asmt Old Created ' . uniqid(),
            'assessment_date' => '2026-11-01',
            'status' => 'active',
        ]);
        $asmt1->created_at = CarbonImmutable::now()->subHours(5);
        $asmt1->saveQuietly();

        // Assessment 2: created recently, but earlier exam date
        $asmt2 = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Asmt New Created ' . uniqid(),
            'assessment_date' => '2026-08-01',
            'status' => 'active',
        ]);
        $asmt2->created_at = CarbonImmutable::now()->subMinutes(10);
        $asmt2->saveQuietly();

        $response = $this->actingAs($this->admin)
            ->get(route('assessments.index'));

        $response->assertOk();
        /** @var \Illuminate\Support\Collection $assessments */
        $assessments = $response->viewData('assessments');

        $pos1 = $assessments->search(fn ($a) => $a->id === $asmt1->id);
        $pos2 = $assessments->search(fn ($a) => $a->id === $asmt2->id);

        $this->assertNotFalse($pos1);
        $this->assertNotFalse($pos2);
        $this->assertLessThan($pos1, $pos2, 'Newest created assessment (asmt2) must appear before older created assessment (asmt1).');
    }

    // ========================================================
    // ISSUE 4: Sections / Class Subjects Auto-Filtering Removed
    // ========================================================
    public function test_issue_4_section_creation_redirects_to_clean_index_without_auto_filter(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('sections.store'), [
                'class_id' => $this->classA->id,
                'name' => 'New Section ' . rand(10, 99),
                'is_active' => true,
            ]);

        // Must redirect to route('sections.index') WITHOUT ?class_id=...
        $response->assertRedirect(route('sections.index'));
        $targetUrl = $response->headers->get('Location');
        $this->assertStringNotContainsString('class_id=', $targetUrl);
    }

    public function test_issue_4_class_subject_mapping_redirects_without_class_or_section_filter(): void
    {
        $newSubject = Subject::create([
            'name' => 'Chemistry ' . uniqid(),
            'code' => 'CHM_' . rand(100, 999),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('class_subjects.store'), [
                'academic_year_id' => $this->year->id,
                'class_id' => $this->classA->id,
                'section_id' => $this->sectionA1->id,
                'subject_ids' => [$newSubject->id],
            ]);

        $response->assertRedirect(route('class_subjects.index'));
        $targetUrl = $response->headers->get('Location');
        $this->assertStringNotContainsString('class_id=', $targetUrl);
        $this->assertStringNotContainsString('section_id=', $targetUrl);
    }

    // ========================================================
    // ISSUE 5: Modal Close Button Style
    // ========================================================
    public function test_issue_5_assessment_modals_render_times_close_and_aria_label(): void
    {
        $typeResponse = $this->actingAs($this->admin)->get(route('assessments.types.index'));
        $typeResponse->assertOk();
        $typeContent = $typeResponse->getContent();

        // Must NOT render literal text ">Close</button>"
        $this->assertStringNotContainsString('>Close</button>', $typeContent);
        // Must render aria-label="Close">&times;</button>
        $this->assertStringContainsString('aria-label="Close" onclick="closeModal', $typeContent);

        // Seed an assessment so assessments.index renders table row with edit/delete modals
        Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Modal Test Assessment ' . uniqid(),
            'status' => 'active',
        ]);

        $asmtResponse = $this->actingAs($this->admin)->get(route('assessments.index'));
        $asmtResponse->assertOk();
        $asmtContent = $asmtResponse->getContent();
        $this->assertStringNotContainsString('>Close</button>', $asmtContent);
        $this->assertStringContainsString('aria-label="Close" onclick="closeModal', $asmtContent);
    }

    // ========================================================
    // ISSUE 6: Report Configuration Dropdown Usability & Scoping
    // ========================================================
    public function test_issue_6_resolver_only_returns_configurations_where_all_displayed_assessments_apply(): void
    {
        /** @var ReportConfigurationResolver $resolver */
        $resolver = app(ReportConfigurationResolver::class);

        // Assessment 1: applicable to Class A Section 1
        $asmtA = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Class A Exam ' . uniqid(),
            'status' => 'active',
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $asmtA->id,
            'class_subject_id' => $this->csA->id, // Class A, Section A1
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // Assessment 2: applicable only to Class B Section 1
        $asmtB = Assessment::create([
            'academic_year_id' => $this->year->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Class B Exam ' . uniqid(),
            'status' => 'active',
        ]);
        AssessmentApplicability::create([
            'assessment_id' => $asmtB->id,
            'class_subject_id' => $this->csB->id, // Class B, Section B1
            'maximum_marks' => 100.00,
            'is_active' => true,
        ]);

        // Config 1: contains only Assessment A
        $configA = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Config For Class A Only',
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $configA->id,
            'assessment_id' => $asmtA->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        // Config 2: mixed config containing Assessment A AND Assessment B
        $configMixed = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Mixed Config A and B',
            'report_type' => ReportType::TERM,
            'is_active' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $configMixed->id,
            'assessment_id' => $asmtA->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);
        ReportAssessmentSelection::create([
            'report_configuration_id' => $configMixed->id,
            'assessment_id' => $asmtB->id,
            'display_order' => 2,
            'is_displayed' => true,
        ]);

        // For Class A, Section A1:
        // Config A is fully usable
        $configsForA = $resolver->getAvailableConfigurations($this->year->id, 'term', $this->classA->id, $this->sectionA1->id);
        $this->assertTrue($configsForA->contains('id', $configA->id), 'Config A must be available for Class A Section A1.');
        $this->assertFalse($configsForA->contains('id', $configMixed->id), 'Mixed Config must NOT be available for Class A Section A1 because Assessment B does not apply to Class A.');

        // For Class A, Section A2 (different section with no mapping):
        $configsForA2 = $resolver->getAvailableConfigurations($this->year->id, 'term', $this->classA->id, $this->sectionA2->id);
        $this->assertFalse($configsForA2->contains('id', $configA->id), 'Config A must not be available for Section A2 where applicability does not exist.');

        // For Class B, Section B1:
        $configsForB = $resolver->getAvailableConfigurations($this->year->id, 'term', $this->classB->id, $this->sectionB1->id);
        $this->assertFalse($configsForB->contains('id', $configA->id), 'Config A must NOT be available for Class B.');
    }

    public function test_issue_6_reports_index_displays_no_configuration_available_when_none_applies(): void
    {
        // Class B has no report configurations created for it
        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->classB->id,
            'section_id' => $this->sectionB1->id,
            'report_type' => 'term',
        ]));

        $response->assertOk();
        $response->assertSee('-- No Configuration Available --');
    }

    // ========================================================
    // ISSUE 7: Collapsible Sidebar & Navigation Icons
    // ========================================================
    public function test_issue_7_layout_contains_sidebar_toggle_and_svg_icons(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
        $content = $response->getContent();

        // Hamburger toggle button exists
        $this->assertStringContainsString('id="sidebar-toggle-btn"', $content);
        $this->assertStringContainsString('aria-label="Toggle Navigation Sidebar"', $content);

        // Navigation SVGs exist
        $this->assertStringContainsString('class="nav-icon"', $content);
        $this->assertStringContainsString('data-tooltip="Students Directory"', $content);
        $this->assertStringContainsString('data-tooltip="Report Cards"', $content);
    }
}
