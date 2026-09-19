<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\CalculationMethod;
use App\Enums\ReportType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\CalculationSetting;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CalculationAndReportConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $year;
    protected SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_cr_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin CR',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_cr_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office CR',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_cr_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher CR',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Class_' . uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_calculation_settings_unique_per_academic_year_and_class(): void
    {
        // 1. Create average_percentage setting
        $response = $this->actingAs($this->officeStaff)->post(route('calculations.settings.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => 'average_percentage',
        ]);
        $response->assertRedirect();

        $setting = CalculationSetting::where('academic_year_id', $this->year->id)
            ->where('class_id', $this->class->id)
            ->firstOrFail();

        $this->assertEquals(CalculationMethod::AVERAGE_PERCENTAGE, $setting->calculation_method);

        // 2. Duplicate setting for same year and class is rejected
        $dupResponse = $this->actingAs($this->admin)->post(route('calculations.settings.store'), [
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'calculation_method' => 'combined_marks',
        ]);
        $dupResponse->assertSessionHasErrors('class_id');

        // 3. Update to combined_marks
        $updateResponse = $this->actingAs($this->admin)->put(route('calculations.settings.update', $setting), [
            'calculation_method' => 'combined_marks',
        ]);
        $updateResponse->assertRedirect();
        $setting->refresh();
        $this->assertEquals(CalculationMethod::COMBINED_MARKS, $setting->calculation_method);
    }

    public function test_report_configuration_supports_exam_term_and_final_types(): void
    {
        foreach (['exam', 'term', 'final'] as $type) {
            $response = $this->actingAs($this->officeStaff)->post(route('reports.configurations.store'), [
                'name' => "Layout {$type} " . uniqid(),
                'report_type' => $type,
                'academic_year_id' => $this->year->id,
                'is_active' => 1,
            ]);
            $response->assertRedirect();
        }

        $this->assertEquals(3, ReportConfiguration::where('academic_year_id', $this->year->id)->count());
    }

    public function test_report_assessment_selections_separate_from_calculation_participation(): void
    {
        $config = ReportConfiguration::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Annual Report Layout',
            'report_type' => ReportType::FINAL,
            'is_active' => true,
        ]);

        $type = AssessmentType::create(['name' => 'Type_' . uniqid(), 'is_active' => true]);
        $assessment = Assessment::create([
            'academic_year_id' => $this->year->id,
            'assessment_type_id' => $type->id,
            'name' => 'Annual Exam',
        ]);

        // Add assessment to report configuration
        $response = $this->actingAs($this->officeStaff)->post(route('reports.selections.store', $config), [
            'assessment_id' => $assessment->id,
            'display_order' => 1,
            'is_displayed' => 1,
        ]);
        $response->assertRedirect();

        $selection = ReportAssessmentSelection::where('report_configuration_id', $config->id)
            ->where('assessment_id', $assessment->id)
            ->firstOrFail();

        $this->assertEquals(1, $selection->display_order);
        $this->assertTrue($selection->is_displayed);

        // Explicit architectural assertions: contributes_to_calculation is NOT a column/attribute
        $this->assertArrayNotHasKey('contributes_to_calculation', $selection->getAttributes());
        $this->assertArrayNotHasKey('weight', $selection->getAttributes());
    }
}
