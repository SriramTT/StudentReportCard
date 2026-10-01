<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\CalculationSetting;
use App\Models\ClassSubject;
use App\Models\ReportConfiguration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UiNavigationCorrectionsBatch2Test extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'Administrator'], ['description' => 'Administrator']);
        $this->admin = User::firstOrCreate(
            ['username' => 'admin_test_b2'],
            [
                'email' => 'admin_test_b2@example.com',
                'display_name' => 'Batch 2 Test Admin',
                'password_hash' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
        $this->admin->setRelation('role', $adminRole);

        $this->year = AcademicYear::firstOrCreate(
            ['name' => '2026-2027'],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-04-30',
                'status' => 'open',
                'is_current' => true,
            ]
        );
    }

    /**
     * CHANGE #1: Natural Ascending Class Sorting
     */
    public function test_school_class_natural_sorting_helper_orders_human_naturally(): void
    {
        // Create classes with alphanumeric naming
        $names = ['Class 10', 'Class 2', 'Class 1', 'Class 11', 'Class 9', 'Grade 10', 'Grade 1', 'LKG', 'UKG'];
        $created = [];
        foreach ($names as $name) {
            $created[] = SchoolClass::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $sorted = SchoolClass::getNaturallySorted(false);
        $sortedNames = $sorted->pluck('name')->toArray();

        // Extract indices
        $idxC1 = array_search('Class 1', $sortedNames);
        $idxC2 = array_search('Class 2', $sortedNames);
        $idxC9 = array_search('Class 9', $sortedNames);
        $idxC10 = array_search('Class 10', $sortedNames);
        $idxC11 = array_search('Class 11', $sortedNames);

        $this->assertNotFalse($idxC1);
        $this->assertNotFalse($idxC2);
        $this->assertNotFalse($idxC9);
        $this->assertNotFalse($idxC10);
        $this->assertNotFalse($idxC11);

        $this->assertTrue($idxC1 < $idxC2, 'Class 1 must appear before Class 2');
        $this->assertTrue($idxC2 < $idxC9, 'Class 2 must appear before Class 9');
        $this->assertTrue($idxC9 < $idxC10, 'Class 9 must appear before Class 10');
        $this->assertTrue($idxC10 < $idxC11, 'Class 10 must appear before Class 11');

        // Check Grade 1 before Grade 10
        $idxG1 = array_search('Grade 1', $sortedNames);
        $idxG10 = array_search('Grade 10', $sortedNames);
        if ($idxG1 !== false && $idxG10 !== false) {
            $this->assertTrue($idxG1 < $idxG10, 'Grade 1 must appear before Grade 10');
        }
    }

    public function test_natural_class_order_rendered_in_dropdowns_across_endpoints(): void
    {
        $this->actingAs($this->admin);

        // Create unordered classes
        SchoolClass::firstOrCreate(['name' => 'Class 1'], ['is_active' => true]);
        SchoolClass::firstOrCreate(['name' => 'Class 2'], ['is_active' => true]);
        SchoolClass::firstOrCreate(['name' => 'Class 10'], ['is_active' => true]);
        SchoolClass::firstOrCreate(['name' => 'Class 11'], ['is_active' => true]);

        $endpoints = [
            route('class_subjects.index'),
            route('calculations.settings.index'),
            route('students.index'),
            route('marks.index'),
            route('attendance.index'),
            route('reports.index'),
            route('teacher_assignments.index'),
        ];

        foreach ($endpoints as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);

            $content = $response->getContent();
            $posC2 = strpos($content, 'Class 2');
            $posC10 = strpos($content, 'Class 10');

            if ($posC2 !== false && $posC10 !== false) {
                $this->assertTrue(
                    $posC2 < $posC10,
                    "In {$url}, Class 2 must appear before Class 10 in dropdowns/selectors"
                );
            }
        }
    }

    /**
     * CHANGE #2: Modal Add Features for 10 Views
     */
    

    public function test_terms_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('terms.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-term-modal"', false);
        $response->assertSee('openModal(\'create-term-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_classes_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('classes.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-class-modal"', false);
        $response->assertSee('openModal(\'create-class-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_sections_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('sections.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-section-modal"', false);
        $response->assertSee('openModal(\'create-section-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_subjects_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('subjects.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-subject-modal"', false);
        $response->assertSee('openModal(\'create-subject-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_class_subjects_index_has_map_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('class_subjects.index'));
        $response->assertStatus(200);
        $response->assertSee('id="map-subject-modal"', false);
        $response->assertSee('openModal(\'map-subject-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('id="mapping_form"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_assessment_types_index_has_single_modal_and_no_side_card(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('assessments.types.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-type-modal"', false);
        $response->assertSee('openModal(\'create-type-modal\')', false);
        $response->assertDontSee('class="assessment-page-side-col"', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_assessments_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('assessments.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-assessment-modal"', false);
        $response->assertSee('openModal(\'create-assessment-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_calculation_settings_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('calculations.settings.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-calc-setting-modal"', false);
        $response->assertSee('openModal(\'create-calc-setting-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_report_configurations_index_has_add_modal_and_validates(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reports.configurations.index'));
        $response->assertStatus(200);
        $response->assertSee('id="create-report-config-modal"', false);
        $response->assertSee('openModal(\'create-report-config-modal\')', false);
        $response->assertSee('name="_form_context"', false);
        $response->assertSee('aria-label="Close"', false);
    }

    /**
     * CHANGE #3: Header User Menu Dropdown
     */
    public function test_header_shows_avatar_and_dropdown_contains_user_details_and_post_signout(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Header trigger elements
        $response->assertSee('id="user-menu-trigger"', false);
        $response->assertSee('id="user-menu-dropdown"', false);
        $response->assertSee('aria-haspopup="true"', false);
        $response->assertSee('aria-expanded="false"', false);

        // Inside dropdown: authenticated user's actual display name and role
        $response->assertSee('Batch 2 Test Admin');
        $response->assertSee('Administrator');

        // Sign Out button and form
        $response->assertSee('id="topbar-logout-btn"', false);
        $response->assertSee(route('logout'), false);
        $response->assertSee('method="POST"', false);
    }
}
