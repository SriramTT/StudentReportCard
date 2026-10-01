<?php

namespace Tests\Feature\Reports;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\CalculationMethod;
use App\Enums\ReportType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\AssessmentClassApplicability;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Calculation\CalculationService;
use App\Services\Report\ReportCompletionService;
use App\Services\Report\ReportConfigurationResolver;
use App\Services\Report\ReportDataPreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportConfigurationCorrectionsPhase13Test extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $currentYear;
    protected SchoolClass $class;
    protected Section $section;
    protected Term $term1;
    protected Term $term2;
    protected AssessmentType $examType;
    protected AssessmentType $testType;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_p13_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin P13',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_p13_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office P13',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_p13_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher P13',
            'is_active' => true,
        ]);

        // Find or ensure single current academic year
        $existingCurrent = AcademicYear::where('is_current', true)->first();
        if ($existingCurrent) {
            $this->currentYear = $existingCurrent;
        } else {
            $this->currentYear = AcademicYear::create([
                'name' => 'AY_Current_' . rand(1000, 9999),
                'start_date' => '2026-06-01',
                'end_date' => '2027-04-30',
                'status' => AcademicYearStatus::OPEN,
                'is_current' => true,
            ]);
        }

        $t1 = $this->currentYear->terms()->where('is_active', true)->first();
        if (! $t1) {
            $t1 = Term::create([
                'academic_year_id' => $this->currentYear->id,
                'name' => 'Term 1 ' . uniqid(),
                'sequence_no' => 1,
                'is_active' => true,
            ]);
        }
        $this->term1 = $t1;

        $t2 = $this->currentYear->terms()->where('id', '!=', $this->term1->id)->first();
        if (! $t2) {
            $t2 = Term::create([
                'academic_year_id' => $this->currentYear->id,
                'name' => 'Term 2 ' . uniqid(),
                'sequence_no' => 2,
                'is_active' => false,
            ]);
        }
        $this->term2 = $t2;

        $this->class = SchoolClass::create([
            'name' => 'Class_' . uniqid(),
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->examType = AssessmentType::where('name', 'Term Exam')->first()
            ?? AssessmentType::create([
                'name' => 'Term Exam',
                'is_active' => true,
            ]);

        $this->testType = AssessmentType::create([
            'name' => 'Unit Test ' . uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * ========================================================
     * ISSUE 1: Automatic Current Academic Year Assignment
     * ========================================================
     */
    public function test_issue_1_new_configuration_is_automatically_assigned_current_academic_year(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'Auto Year Config ' . uniqid(),
            'report_type' => 'term',
            'is_active' => 1,
        ]);

        $response->assertRedirect();

        $config = ReportConfiguration::where('name', 'like', 'Auto Year Config%')->latest('id')->firstOrFail();
        $this->assertEquals($this->currentYear->id, $config->academic_year_id);
    }

    public function test_issue_1_client_supplied_academic_year_cannot_override_server_assignment(): void
    {
        $otherYear = AcademicYear::create([
            'name' => 'AY_Other_' . rand(1000, 9999),
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'Forged Year Config ' . uniqid(),
            'report_type' => 'term',
            'academic_year_id' => $otherYear->id,
            'is_active' => 1,
        ]);

        $response->assertRedirect();

        $config = ReportConfiguration::where('name', 'like', 'Forged Year Config%')->latest('id')->firstOrFail();
        $this->assertEquals($this->currentYear->id, $config->academic_year_id, 'Server must assign current year, ignoring client input');
    }

    public function test_issue_1_blocks_creation_when_no_current_academic_year(): void
    {
        // Unset all is_current years
        AcademicYear::where('is_current', true)->update(['is_current' => false]);

        $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'No Year Config ' . uniqid(),
            'report_type' => 'term',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('academic_year');
    }

    public function test_issue_1_blocks_creation_when_multiple_current_academic_years(): void
    {
        // Create second current year with short name
        AcademicYear::create([
            'name' => 'AY_Dup_' . rand(100, 999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'Multi Current Config ' . uniqid(),
            'report_type' => 'term',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('academic_year');
    }

    public function test_issue_1_existing_null_and_historical_configurations_preserved_on_update(): void
    {
        $historicalYear = AcademicYear::create([
            'name' => 'AY_Historical_' . rand(1000, 9999),
            'start_date' => '2024-06-01',
            'end_date' => '2025-04-30',
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $historicalConfig = ReportConfiguration::create([
            'name' => 'Historical Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $historicalYear->id,
            'is_active' => true,
        ]);

        $nullConfig = ReportConfiguration::create([
            'name' => 'Universal Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => null,
            'is_active' => true,
        ]);

        // Update historical config name
        $this->actingAs($this->officeStaff)->put(route('reports.configurations.update', $historicalConfig), [
            'name' => 'Historical Config Updated',
            'report_type' => 'term',
            'is_active' => 1,
        ]);
        $historicalConfig->refresh();
        $this->assertEquals($historicalYear->id, $historicalConfig->academic_year_id, 'Historical academic_year_id must remain unchanged');

        // Update universal config name
        $this->actingAs($this->officeStaff)->put(route('reports.configurations.update', $nullConfig), [
            'name' => 'Universal Config Updated',
            'report_type' => 'term',
            'is_active' => 1,
        ]);
        $nullConfig->refresh();
        $this->assertNull($nullConfig->academic_year_id, 'Universal NULL academic_year_id must remain unchanged');
    }

    /**
     * ========================================================
     * ISSUE 2 & 3: Ordering & Drag-and-Drop
     * ========================================================
     */
    public function test_issue_2_assessment_added_without_display_order_gets_server_assigned_position(): void
    {
        $config = ReportConfiguration::create([
            'name' => 'Order Test Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $asmt1 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Asmt 1 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $asmt2 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Asmt 2 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Add first assessment without display_order
        $response1 = $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $asmt1->id,
            'is_displayed' => 1,
        ]);
        $response1->assertRedirect();

        // Add second assessment without display_order
        $response2 = $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $asmt2->id,
            'is_displayed' => 1,
        ]);
        $response2->assertRedirect();

        $sel1 = ReportAssessmentSelection::where('report_configuration_id', $config->id)->where('assessment_id', $asmt1->id)->firstOrFail();
        $sel2 = ReportAssessmentSelection::where('report_configuration_id', $config->id)->where('assessment_id', $asmt2->id)->firstOrFail();

        $this->assertEquals(1, $sel1->display_order);
        $this->assertEquals(2, $sel2->display_order);

        // Visibility toggle does not alter display_order
        $this->actingAs($this->officeStaff)->put(route('reports.selections.update', [$config, $sel1]), [
            'is_displayed' => 0,
        ]);
        $sel1->refresh();
        $this->assertFalse($sel1->is_displayed);
        $this->assertEquals(1, $sel1->display_order);
    }

    public function test_issue_3_reorder_persists_valid_permutation_and_rejects_invalid_payloads(): void
    {
        $config = ReportConfiguration::create([
            'name' => 'Reorder Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $asmt1 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Asmt 1 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $asmt2 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Asmt 2 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $sel1 = ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $asmt1->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $sel2 = ReportAssessmentSelection::create([
            'report_configuration_id' => $config->id,
            'assessment_id' => $asmt2->id,
            'display_order' => 2,
            'is_displayed' => true,
        ]);

        // 1. Unauthorized user (Subject Teacher) cannot reorder
        $unauthResp = $this->actingAs($this->teacher)->postJson(route('reports.selections.reorder', $config), [
            'selection_ids' => [$sel2->id, $sel1->id],
        ]);
        $unauthResp->assertStatus(403);

        // 2. Incomplete permutation rejected
        $incompleteResp = $this->actingAs($this->officeStaff)->postJson(route('reports.selections.reorder', $config), [
            'selection_ids' => [$sel2->id],
        ]);
        $incompleteResp->assertStatus(422);

        // 3. Duplicate ID rejected
        $dupResp = $this->actingAs($this->officeStaff)->postJson(route('reports.selections.reorder', $config), [
            'selection_ids' => [$sel2->id, $sel2->id],
        ]);
        $dupResp->assertStatus(422);

        // 4. Foreign selection ID rejected
        $foreignConfig = ReportConfiguration::create([
            'name' => 'Foreign Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);
        $foreignAsmt = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Foreign Asmt ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $foreignSel = ReportAssessmentSelection::create([
            'report_configuration_id' => $foreignConfig->id,
            'assessment_id' => $foreignAsmt->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $foreignResp = $this->actingAs($this->officeStaff)->postJson(route('reports.selections.reorder', $config), [
            'selection_ids' => [$sel1->id, $foreignSel->id],
        ]);
        $foreignResp->assertStatus(422);

        // 5. Valid reverse reorder succeeds
        $validResp = $this->actingAs($this->officeStaff)->postJson(route('reports.selections.reorder', $config), [
            'selection_ids' => [$sel2->id, $sel1->id],
        ]);
        $validResp->assertStatus(200);
        $validResp->assertJson(['success' => true]);

        $sel1->refresh();
        $sel2->refresh();
        $this->assertEquals(1, $sel2->display_order);
        $this->assertEquals(2, $sel1->display_order);
    }

    /**
     * ========================================================
     * ISSUE 4: Report Type Filter on Index
     * ========================================================
     */
    public function test_issue_4_report_type_filter(): void
    {
        $termConfig = ReportConfiguration::create([
            'name' => 'Filter Term ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $midtermConfig = ReportConfiguration::create([
            'name' => 'Filter Midterm ' . uniqid(),
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'mid_term'],
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $finalConfig = ReportConfiguration::create([
            'name' => 'Filter Final ' . uniqid(),
            'report_type' => ReportType::FINAL,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $customConfig = ReportConfiguration::create([
            'name' => 'Filter Custom ' . uniqid(),
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'custom'],
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        // Filter: Term
        $resp = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', ['report_type' => 'term']));
        $resp->assertSee($termConfig->name);
        $resp->assertDontSee($midtermConfig->name);
        $resp->assertDontSee($finalConfig->name);
        $resp->assertDontSee($customConfig->name);

        // Filter: Midterm
        $resp = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', ['report_type' => 'mid_term']));
        $resp->assertSee($midtermConfig->name);
        $resp->assertDontSee($termConfig->name);
        $resp->assertDontSee($finalConfig->name);
        $resp->assertDontSee($customConfig->name);

        // Filter: Final
        $resp = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', ['report_type' => 'final']));
        $resp->assertSee($finalConfig->name);
        $resp->assertDontSee($termConfig->name);
        $resp->assertDontSee($midtermConfig->name);
        $resp->assertDontSee($customConfig->name);

        // Filter: Custom
        $resp = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', ['report_type' => 'custom']));
        $resp->assertSee($customConfig->name);
        $resp->assertDontSee($termConfig->name);
        $resp->assertDontSee($midtermConfig->name);
        $resp->assertDontSee($finalConfig->name);

        // Filter: All
        $resp = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', ['report_type' => 'all']));
        $resp->assertSee($termConfig->name);
        $resp->assertSee($midtermConfig->name);
        $resp->assertSee($finalConfig->name);
        $resp->assertSee($customConfig->name);
    }

    /**
     * ========================================================
     * ISSUE 5: Custom Report vs. Single-Assessment Mid-Term
     * ========================================================
     */
    public function test_issue_5_mid_term_enforces_exactly_one_assessment_limit(): void
    {
        // 1. Create Mid-term configuration
        $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'Single Midterm ' . uniqid(),
            'report_type' => 'exam_midterm',
            'is_active' => 1,
        ]);
        $response->assertRedirect();

        $config = ReportConfiguration::where('name', 'like', 'Single Midterm%')->latest('id')->firstOrFail();
        $this->assertEquals(ReportType::EXAM, $config->report_type);
        $this->assertEquals('mid_term', $config->configuration_data['subtype']);
        $this->assertTrue($config->isMidTerm());
        $this->assertFalse($config->isCustom());
        $this->assertEquals('Mid term Assessments', $config->user_facing_type_label);

        $asmt1 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Midterm Asmt 1 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $asmt2 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Midterm Asmt 2 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Add 1st assessment -> Allowed
        $asmtResp1 = $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $asmt1->id,
            'is_displayed' => 1,
        ]);
        $asmtResp1->assertRedirect();
        $this->assertEquals(1, $config->assessmentSelections()->count());

        // Add 2nd assessment -> Rejected
        $asmtResp2 = $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $asmt2->id,
            'is_displayed' => 1,
        ]);
        $asmtResp2->assertSessionHasErrors('assessment_id');
        $this->assertEquals(1, $config->assessmentSelections()->count());
    }

    public function test_issue_5_custom_report_allows_multiple_assessments(): void
    {
        $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'Multi Custom ' . uniqid(),
            'report_type' => 'exam_custom',
            'is_active' => 1,
        ]);
        $response->assertRedirect();

        $config = ReportConfiguration::where('name', 'like', 'Multi Custom%')->latest('id')->firstOrFail();
        $this->assertEquals(ReportType::EXAM, $config->report_type);
        $this->assertEquals('custom', $config->configuration_data['subtype']);
        $this->assertTrue($config->isCustom());
        $this->assertFalse($config->isMidTerm());
        $this->assertEquals('Custom Report', $config->user_facing_type_label);

        $asmt1 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Custom Asmt 1 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $asmt2 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Custom Asmt 2 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $asmt1->id,
        ])->assertRedirect();

        $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $asmt2->id,
        ])->assertRedirect();

        $this->assertEquals(2, $config->assessmentSelections()->count());
    }

    public function test_issue_5_converting_multi_assessment_custom_to_midterm_is_rejected(): void
    {
        $config = ReportConfiguration::create([
            'name' => 'Custom To Midterm ' . uniqid(),
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'custom'],
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $asmt1 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Asmt 1 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);
        $asmt2 = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Asmt 2 ' . uniqid(),
            'status' => AssessmentStatus::ACTIVE,
        ]);

        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $asmt1->id, 'display_order' => 1, 'is_displayed' => true]);
        ReportAssessmentSelection::create(['report_configuration_id' => $config->id, 'assessment_id' => $asmt2->id, 'display_order' => 2, 'is_displayed' => true]);

        // Attempt converting to exam_midterm while having 2 selections -> Rejected
        $response = $this->actingAs($this->officeStaff)->put(route('reports.configurations.update', $config), [
            'name' => 'Custom To Midterm',
            'report_type' => 'exam_midterm',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('report_type');
    }

    public function test_issue_5_legacy_exam_configuration_with_no_subtype_treated_as_custom(): void
    {
        $legacyConfig = ReportConfiguration::create([
            'name' => 'Legacy Exam ' . uniqid(),
            'report_type' => ReportType::EXAM,
            'configuration_data' => null, // No subtype
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        $this->assertFalse($legacyConfig->isMidTerm());
        $this->assertTrue($legacyConfig->isCustom());
        $this->assertEquals('Custom Report', $legacyConfig->user_facing_type_label);
    }

    /**
     * ========================================================
     * ISSUE 6: Term Report Displays Every Configured Assessment
     * Without Altering Term % Calculation
     * ========================================================
     */
    public function test_issue_6_cross_term_configured_assessment_displays_without_affecting_term_percentage(): void
    {
        // 1. Create a Term Report Configuration
        $termConfig = ReportConfiguration::create([
            'name' => 'Term 1 Multi-Assessment Layout ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'is_active' => true,
        ]);

        // Term 1 Exam (contributes to calculation)
        $term1Exam = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 1 Exam',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Term 2 Exam (cross-term assessment, configured for display on Term 1 report)
        $term2Exam = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term2->id,
            'assessment_type_id' => $this->examType->id,
            'name' => 'Term 2 Exam Cross-Term',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Unconfigured assessment (must NOT display)
        $unconfiguredAsmt = Assessment::create([
            'academic_year_id' => $this->currentYear->id,
            'term_id' => $this->term1->id,
            'assessment_type_id' => $this->testType->id,
            'name' => 'Unconfigured Test',
            'status' => AssessmentStatus::ACTIVE,
        ]);

        // Configure Term 1 Exam (order 1) and Term 2 Exam (order 2)
        $sel1 = ReportAssessmentSelection::create([
            'report_configuration_id' => $termConfig->id,
            'assessment_id' => $term1Exam->id,
            'display_order' => 1,
            'is_displayed' => true,
        ]);

        $sel2 = ReportAssessmentSelection::create([
            'report_configuration_id' => $termConfig->id,
            'assessment_id' => $term2Exam->id,
            'display_order' => 2,
            'is_displayed' => true,
        ]);

        // 2. Verify ReportConfigurationResolver returns BOTH configured assessments in order
        /** @var ReportConfigurationResolver $resolver */
        $resolver = app(ReportConfigurationResolver::class);
        $displayedAssessments = $resolver->getDisplayedAssessmentsForTerm($termConfig, $this->term1->id);

        $this->assertCount(2, $displayedAssessments);
        $this->assertEquals($term1Exam->id, $displayedAssessments->first()->id);
        $this->assertEquals($term2Exam->id, $displayedAssessments->last()->id);
        $this->assertFalse($displayedAssessments->contains('id', $unconfiguredAsmt->id));

        // 3. Set up subject, student, applicability, marks
        $subject = Subject::create([
            'name' => 'Mathematics ' . uniqid(),
            'code' => 'MATH_' . rand(100, 999),
            'is_active' => true,
        ]);

        $classSubject = ClassSubject::create([
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'subject_name_snapshot' => $subject->name,
            'is_active' => true,
        ]);

        $student = Student::create([
            'admission_number' => 'ADM_P13_' . rand(1000, 9999),
            'student_name' => 'Alice P13',
        ]);

        $sar = StudentAcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->currentYear->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 1,
            'status' => \App\Enums\StudentPlacementStatus::ACTIVE,
            'effective_from' => '2026-06-01',
        ]);

        $allocation = StudentSubjectAllocation::create([
            'student_academic_record_id' => $sar->id,
            'class_subject_id' => $classSubject->id,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $app1 = AssessmentApplicability::create([
            'assessment_id' => $term1Exam->id,
            'class_subject_id' => $classSubject->id,
            'maximum_marks' => 100.00,
        ]);

        $app2 = AssessmentApplicability::create([
            'assessment_id' => $term2Exam->id,
            'class_subject_id' => $classSubject->id,
            'maximum_marks' => 50.00,
        ]);

        $schoolSetting = \App\Models\SchoolSetting::first() ?? \App\Models\SchoolSetting::create([
            'school_name' => 'Test School',
            'pass_mark' => 35.00,
        ]);
        $schoolSetting->update(['pass_mark' => 35.00]);

        \App\Models\CalculationSetting::firstOrCreate(
            ['academic_year_id' => $this->currentYear->id, 'class_id' => $this->class->id],
            ['calculation_method' => CalculationMethod::COMBINED_MARKS]
        );

        // 4. Test Completion: missing mark on configured cross-term assessment blocks completion
        /** @var ReportCompletionService $completionService */
        $completionService = app(ReportCompletionService::class);

        // Put mark only for Term 1 Exam: 80 / 100
        Mark::create([
            'student_academic_record_id' => $sar->id,
            'student_subject_allocation_id' => $allocation->id,
            'assessment_applicability_id' => $app1->id,
            'mark_value' => '80.00',
            'result_status' => \App\Enums\MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        $this->assertFalse(
            $completionService->isComplete($sar->id, 'term', $this->term1->id, $termConfig),
            'Configured displayed Term 2 assessment is missing a mark, so report must be incomplete'
        );

        // Add mark for Term 2 Exam: 40 / 50
        Mark::create([
            'student_academic_record_id' => $sar->id,
            'student_subject_allocation_id' => $allocation->id,
            'assessment_applicability_id' => $app2->id,
            'mark_value' => '40.00',
            'result_status' => \App\Enums\MarkResultStatus::NUMERIC,
            'entered_by_user_id' => $this->admin->id,
        ]);

        $this->assertTrue(
            $completionService->isComplete($sar->id, 'term', $this->term1->id, $termConfig),
            'Report must be complete when both configured assessments have marks'
        );

        // 5. Test Data Preparation and Calculation:
        // Let's change Term 2 mark to 10/50 to guarantee any cross-term calculation leak changes the percentage.
        Mark::where('assessment_applicability_id', $app2->id)->update(['mark_value' => '10.00']);

        /** @var ReportDataPreparationService $dataPrep */
        $dataPrep = app(ReportDataPreparationService::class);
        $payload = $dataPrep->prepare(
            sarId: $sar->id,
            reportType: 'term',
            termId: $this->term1->id,
            reportConfig: $termConfig
        );

        // Assessment columns must contain both in order
        $this->assertCount(2, $payload->assessmentColumns);
        $this->assertEquals($term1Exam->id, $payload->assessmentColumns[0]->id);
        $this->assertEquals($term2Exam->id, $payload->assessmentColumns[1]->id);

        // Verify subject row contains marks for both assessments
        $mathRow = collect($payload->subjectRows)->firstWhere('subjectId', $subject->id);
        $this->assertNotNull($mathRow);
        $this->assertEquals('80.00', $mathRow->marks[$term1Exam->id]['display_mark']);
        $this->assertEquals('10.00', $mathRow->marks[$term2Exam->id]['display_mark']);

        // Term % must be 80.00% (from Term 1 Exam 80/100), NOT 60.00% ((80+10)/(100+50) = 90/150 = 60.00%)
        $this->assertEquals(80.0, (float) $mathRow->termPercentage);
        $this->assertEquals('80.00%', $mathRow->formattedTermPercentage);
        $this->assertEquals('PASS', $mathRow->termStatus);
        $this->assertEquals('PASS', $payload->overallResult);
    }

    /**
     * ========================================================
     * ISSUE 1 (UI) & ISSUE 2 (ORDERING): NEWEST-FIRST & STICKY PANEL
     * ========================================================
     */
    public function test_issue_2_configurations_ordered_newest_created_first_not_alphabetical(): void
    {
        // Create older configuration with alphabetically earlier name 'AAA Layout'
        $olderConfig = ReportConfiguration::forceCreate([
            'name' => 'AAA Older Layout ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'created_at' => now()->subHours(2),
            'is_active' => true,
        ]);

        // Create newer configuration with alphabetically later name 'ZZZ Layout'
        $newerConfig = ReportConfiguration::forceCreate([
            'name' => 'ZZZ Newer Layout ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'created_at' => now()->subHour(1),
            'is_active' => true,
        ]);

        // Create newest configuration via store endpoint
        $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
            'name' => 'MMM Brand New Layout ' . uniqid(),
            'report_type' => 'term',
            'is_active' => 1,
        ]);

        $newestConfig = ReportConfiguration::where('name', 'like', 'MMM Brand New Layout%')->latest('id')->firstOrFail();

        $response = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', [
            'academic_year_id' => $this->currentYear->id,
        ]));
        $response->assertStatus(200);

        /** @var \Illuminate\Database\Eloquent\Collection $configs */
        $configs = $response->viewData('configurations');

        // Newest created must appear first
        $this->assertEquals($newestConfig->id, $configs->first()->id, 'Newest configuration must appear at the top');

        // Index of each configuration in the returned collection:
        $newestIdx = $configs->search(fn ($c) => $c->id === $newestConfig->id);
        $newerIdx = $configs->search(fn ($c) => $c->id === $newerConfig->id);
        $olderIdx = $configs->search(fn ($c) => $c->id === $olderConfig->id);

        $this->assertTrue($newestIdx < $newerIdx, 'Newest must be before newer');
        $this->assertTrue($newerIdx < $olderIdx, 'Newer must be before older (overriding alphabetical AAA/ZZZ)');
    }

    public function test_issue_2_equal_created_at_ordered_by_id_desc_tie_breaker(): void
    {
        $exactTime = now()->subMinutes(10);

        $config1 = ReportConfiguration::forceCreate([
            'name' => 'Batch Config 1 ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'created_at' => $exactTime,
            'is_active' => true,
        ]);

        $config2 = ReportConfiguration::forceCreate([
            'name' => 'Batch Config 2 ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'created_at' => $exactTime,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', [
            'academic_year_id' => $this->currentYear->id,
        ]));

        $configs = $response->viewData('configurations');
        $c1Idx = $configs->search(fn ($c) => $c->id === $config1->id);
        $c2Idx = $configs->search(fn ($c) => $c->id === $config2->id);

        $this->assertTrue($c2Idx < $c1Idx, 'Higher ID must appear first as deterministic tie-breaker when created_at is identical');
    }

    public function test_issue_2_updating_older_configuration_does_not_change_its_position(): void
    {
        $olderConfig = ReportConfiguration::forceCreate([
            'name' => 'Original Older Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'created_at' => now()->subHours(5),
            'is_active' => true,
        ]);

        $newerConfig = ReportConfiguration::forceCreate([
            'name' => 'Original Newer Config ' . uniqid(),
            'report_type' => ReportType::TERM,
            'academic_year_id' => $this->currentYear->id,
            'created_at' => now()->subHours(1),
            'is_active' => true,
        ]);

        // Update the older configuration
        $this->actingAs($this->officeStaff)->put(route('reports.configurations.update', $olderConfig), [
            'name' => 'Edited Older Config Name',
            'report_type' => 'term',
            'is_active' => 1,
        ]);

        $olderConfig->refresh();
        $this->assertNotNull($olderConfig->updated_at);

        $response = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', [
            'academic_year_id' => $this->currentYear->id,
        ]));

        $configs = $response->viewData('configurations');
        $olderIdx = $configs->search(fn ($c) => $c->id === $olderConfig->id);
        $newerIdx = $configs->search(fn ($c) => $c->id === $newerConfig->id);

        $this->assertTrue($newerIdx < $olderIdx, 'Editing an older configuration must not jump it ahead of a newer configuration');
    }

    public function test_issue_2_newest_first_composes_with_report_type_filter(): void
    {
        $olderCustom = ReportConfiguration::forceCreate([
            'name' => 'Older Custom ' . uniqid(),
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'custom'],
            'academic_year_id' => $this->currentYear->id,
            'created_at' => now()->subHours(3),
            'is_active' => true,
        ]);

        $newerCustom = ReportConfiguration::forceCreate([
            'name' => 'Newer Custom ' . uniqid(),
            'report_type' => ReportType::EXAM,
            'configuration_data' => ['subtype' => 'custom'],
            'academic_year_id' => $this->currentYear->id,
            'created_at' => now()->subHour(1),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index', [
            'academic_year_id' => $this->currentYear->id,
            'report_type' => 'custom',
        ]));

        $configs = $response->viewData('configurations');
        $this->assertEquals($newerCustom->id, $configs->first()->id, 'Newest custom config must be first in filtered results');
    }

    public function test_issue_1_sticky_layout_classes_rendered(): void
    {
        // Admin or Office Staff views the page
        $response = $this->actingAs($this->officeStaff)->get(route('reports.configurations.index'));
        $response->assertStatus(200);

        // Verify layout classes and sticky card exist in rendered HTML
        $response->assertSee('report-config-layout');
        $response->assertSee('report-config-list-col');
        $response->assertSee('report-config-side-col');
        $response->assertSee('report-config-sticky-card');
        $response->assertSee('New Report Configuration');

        // Subject teacher is unauthorized
        $unauthResp = $this->actingAs($this->teacher)->get(route('reports.configurations.index'));
        $unauthResp->assertStatus(403);
    }
}
