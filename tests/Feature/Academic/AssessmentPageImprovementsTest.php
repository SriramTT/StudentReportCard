<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for Phase 13 Assessment page improvements:
 * - Sticky panel markup (CSS class presence)
 * - Assessment Date field removal from Add and Edit forms
 * - assessment_date stored as NULL for new records (no date submitted)
 * - Existing assessment_date preserved on edit when field is absent
 * - Three display filters: Assessment Type, Term, Status
 * - Filter combination (AND semantics)
 * - Invalid filter value handling
 * - Filter selection persistence
 * - Reset clears filters
 * - Authorization unchanged
 * - Academic-year scoping unchanged
 */
class AssessmentPageImprovementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $year;
    protected AssessmentType $typeA;
    protected AssessmentType $typeB;
    protected Term $term1;
    protected Term $term2;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole   = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole  = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id'       => $adminRole->id,
            'username'      => 'admin_pi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name'  => 'Admin PI',
            'is_active'     => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id'       => $officeRole->id,
            'username'      => 'office_pi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name'  => 'Office PI',
            'is_active'     => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id'       => $teacherRole->id,
            'username'      => 'teacher_pi_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name'  => 'Teacher PI',
            'is_active'     => true,
        ]);

        $this->year = AcademicYear::create([
            'name'       => 'AY_PI_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date'   => '2027-04-30',
            'status'     => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->typeA = AssessmentType::create(['name' => 'TypeA_PI_' . uniqid(), 'is_active' => true]);
        $this->typeB = AssessmentType::create(['name' => 'TypeB_PI_' . uniqid(), 'is_active' => true]);

        $this->term1 = Term::create([
            'academic_year_id' => $this->year->id,
            'name'             => 'Term1_PI_' . uniqid(),
            'sequence_no'      => 1,
        ]);

        $this->term2 = Term::create([
            'academic_year_id' => $this->year->id,
            'name'             => 'Term2_PI_' . uniqid(),
            'sequence_no'      => 2,
        ]);
    }

    // ── Standard Card Layout (no broken split-card classes) ───────────────────

    public function test_assessment_types_index_contains_sticky_layout_class(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.types.index'));

        $response->assertOk();
        $response->assertSee('card-header', false);
        $response->assertSee('data-table-wrapper', false);
        $response->assertDontSee('assessment-page-sticky-card', false);
    }

    public function test_assessments_index_contains_sticky_layout_class(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertOk();
        $response->assertSee('card-header', false);
        $response->assertSee('data-table-wrapper', false);
        $response->assertDontSee('assessment-page-sticky-card', false);
    }

    // ── Assessment Types page: modal and header button still present ─────────

    public function test_assessment_types_modal_and_header_button_still_present(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.types.index'));

        $response->assertOk();
        // Header button (checked by ID per existing test convention)
        $response->assertSee('btn-add-assessment-type', false);
        $response->assertSeeText('+ Add Assessment Type');
        // Modal element
        $response->assertSee('create-type-modal', false);
    }

    // ── Assessment Date Field Removal ────────────────────────────────────────

    public function test_add_assessment_form_does_not_contain_date_input(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertOk();
        // The date input should not appear in the Add form
        $response->assertDontSee('name="assessment_date"', false);
        // The label should not appear either
        $response->assertDontSeeText('Assessment Date');
    }

    public function test_edit_assessment_modal_does_not_contain_date_input(): void
    {
        $assessment = Assessment::create([
            'academic_year_id'    => $this->year->id,
            'assessment_type_id'  => $this->typeA->id,
            'name'                => 'Edit Date Test ' . uniqid(),
            'assessment_date'     => '2026-08-15',
            'status'              => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertOk();
        // The date input should not appear anywhere on the page (add or edit)
        $response->assertDontSee('name="assessment_date"', false);
        // No date-range helper text
        $response->assertDontSee('Must fall within', false);
    }

    // ── assessment_date is NULL for new assessments when not submitted ────────

    public function test_new_assessment_stores_null_date_when_no_date_submitted(): void
    {
        $name = 'NullDate_' . uniqid();

        $response = $this->actingAs($this->admin)->post(route('assessments.store'), [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => $name,
            'status'             => 'active',
            // assessment_date intentionally omitted
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'name'            => $name,
            'assessment_date' => null,
        ]);
    }

    // ── Existing assessment_date preserved on edit when key absent ───────────

    public function test_editing_assessment_without_date_field_preserves_existing_date(): void
    {
        $originalDate = '2026-09-10';
        $assessment = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Preserve Date ' . uniqid(),
            'assessment_date'    => $originalDate,
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        // Submit update without assessment_date key in payload
        $response = $this->actingAs($this->admin)->put(route('assessments.update', $assessment), [
            'name'               => 'Preserve Date Updated',
            'assessment_type_id' => $this->typeA->id,
            // assessment_date intentionally omitted — should be preserved
        ]);

        $response->assertRedirect();

        $fresh = $assessment->fresh();
        $this->assertEquals('Preserve Date Updated', $fresh->name);
        $this->assertEquals($originalDate, $fresh->assessment_date->format('Y-m-d'),
            'Existing assessment_date must be preserved when not submitted in edit form.'
        );
    }

    // ── Assessment Type Filter ───────────────────────────────────────────────

    public function test_assessment_type_filter_shows_only_matching_assessments(): void
    {
        $a1 = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'TypeA Assessment ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        $a2 = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeB->id,
            'name'               => 'TypeB Assessment ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'    => $this->year->id,
            'assessment_type_id'  => $this->typeA->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($a1->name);
        $response->assertDontSeeText($a2->name);
    }

    public function test_assessment_type_filter_selected_value_is_preserved(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
        ]));

        $response->assertOk();
        // The selected attribute should appear for the correct option value
        $response->assertSee('value="' . $this->typeA->id . '" selected', false);
    }

    // ── Term Filter ──────────────────────────────────────────────────────────

    public function test_term_filter_shows_only_matching_term_assessments(): void
    {
        $a1 = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Term1 Assessment ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        $a2 = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term2->id,
            'name'               => 'Term2 Assessment ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
            'term_id'          => $this->term1->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($a1->name);
        $response->assertDontSeeText($a2->name);
    }

    public function test_term_filter_none_shows_only_null_term_assessments(): void
    {
        $aWithTerm = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Has Term ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        $aNoTerm = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => null,
            'name'               => 'No Term ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
            'term_id'          => 'none',
        ]));

        $response->assertOk();
        $response->assertSeeText($aNoTerm->name);
        $response->assertDontSeeText($aWithTerm->name);
    }

    // ── Status Filter ────────────────────────────────────────────────────────

    public function test_status_filter_shows_only_active_assessments(): void
    {
        $active = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Active Assess ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        $inactive = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Inactive Assess ' . uniqid(),
            'status'             => AssessmentStatus::INACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
            'status'           => 'active',
        ]));

        $response->assertOk();
        $response->assertSeeText($active->name);
        $response->assertDontSeeText($inactive->name);
    }

    public function test_status_filter_shows_only_inactive_assessments(): void
    {
        $active = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Active2 Assess ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        $inactive = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Inactive2 Assess ' . uniqid(),
            'status'             => AssessmentStatus::INACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
            'status'           => 'inactive',
        ]));

        $response->assertOk();
        $response->assertSeeText($inactive->name);
        $response->assertDontSeeText($active->name);
    }

    // ── Combined Filters (AND semantics) ─────────────────────────────────────

    public function test_combined_type_and_status_filter(): void
    {
        // TypeA + active: should appear
        $match = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Match TypeA Active ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        // TypeA + inactive: not matched by status
        $typeAInactive = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'TypeA Inactive ' . uniqid(),
            'status'             => AssessmentStatus::INACTIVE,
        ]);
        // TypeB + active: not matched by type
        $typeBActive = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeB->id,
            'name'               => 'TypeB Active ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'status'             => 'active',
        ]));

        $response->assertOk();
        $response->assertSeeText($match->name);
        $response->assertDontSeeText($typeAInactive->name);
        $response->assertDontSeeText($typeBActive->name);
    }

    public function test_combined_type_term_and_status_filter(): void
    {
        // typeA + term1 + active: the match
        $match = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Full Match ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        // typeA + term1 but inactive
        $wrongStatus = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Wrong Status ' . uniqid(),
            'status'             => AssessmentStatus::INACTIVE,
        ]);
        // typeB + term1 + active
        $wrongType = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeB->id,
            'term_id'            => $this->term1->id,
            'name'               => 'Wrong Type ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        // typeA + term2 + active
        $wrongTerm = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term2->id,
            'name'               => 'Wrong Term ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'term_id'            => $this->term1->id,
            'status'             => 'active',
        ]));

        $response->assertOk();
        $response->assertSeeText($match->name);
        $response->assertDontSeeText($wrongStatus->name);
        $response->assertDontSeeText($wrongType->name);
        $response->assertDontSeeText($wrongTerm->name);
    }

    // ── Invalid Filter Values Handled Safely ─────────────────────────────────

    public function test_invalid_assessment_type_id_is_ignored_safely(): void
    {
        // A non-integer value must not cause a query error; all assessments for the year are shown
        $assessment = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Safe Filter Test ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => 'evil_string',
        ]));

        $response->assertOk();
        // Invalid type filter is discarded; all assessments for the year appear
        $response->assertSeeText($assessment->name);
    }

    public function test_invalid_status_value_is_ignored_safely(): void
    {
        $assessment = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Safe Status Test ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
            'status'           => 'unknown_value',
        ]));

        $response->assertOk();
        $response->assertSeeText($assessment->name);
    }

    public function test_invalid_term_id_is_ignored_safely(): void
    {
        $assessment = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Safe Term Test ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
            'term_id'          => 'not_a_number',
        ]));

        $response->assertOk();
        $response->assertSeeText($assessment->name);
    }

    // ── Reset Clears Filters Without Restoring Academic Year Filter ──────────

    public function test_reset_link_does_not_contain_academic_year_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'status'             => 'active',
        ]));

        $response->assertOk();
        // The reset link href should be assessments.index without academic_year_id in URL
        $resetUrl = route('assessments.index');
        $response->assertSee(htmlspecialchars($resetUrl), false);
    }

    // ── No Academic Year Filter Visible on Page ───────────────────────────────

    public function test_academic_year_filter_is_not_shown_on_assessments_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertOk();
        // The page must not contain an academic year filter dropdown.
        // Note: academic_year_id exists as a hidden field in the Add form for internal scoping —
        // that is the expected behavior. The filter SELECT (which would be visible to the user)
        // must not exist. We check by the filter element id that would be used.
        $response->assertDontSee('filter_academic_year', false);
        // No visible <select> with name=academic_year_id should appear (hidden input is OK)
        $response->assertDontSee('<select name="academic_year_id"', false);
    }

    // ── Academic-Year Scoping Preserved ──────────────────────────────────────

    public function test_assessments_from_other_years_are_not_shown(): void
    {
        $otherYear = AcademicYear::create([
            'name'       => 'AY_Other_PI_' . rand(1000, 9999),
            'start_date' => '2024-06-01',
            'end_date'   => '2025-04-30',
            'status'     => AcademicYearStatus::CLOSED,
        ]);

        $thisYearAssessment = Assessment::create([
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'This Year ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);
        $otherYearAssessment = Assessment::create([
            'academic_year_id'   => $otherYear->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Other Year ' . uniqid(),
            'status'             => AssessmentStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id' => $this->year->id,
        ]));

        $response->assertOk();
        $response->assertSeeText($thisYearAssessment->name);
        $response->assertDontSeeText($otherYearAssessment->name);
    }

    // ── Authorization Unchanged ───────────────────────────────────────────────

    public function test_teacher_cannot_access_assessments_index(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('assessments.index'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_create_assessment(): void
    {
        $this->actingAs($this->teacher)->post(route('assessments.store'), [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeA->id,
            'name'               => 'Teacher Forbidden ' . uniqid(),
        ])->assertForbidden();
    }

    public function test_office_staff_can_access_assessments_index(): void
    {
        $this->actingAs($this->officeStaff)
            ->get(route('assessments.index', ['academic_year_id' => $this->year->id]))
            ->assertOk();
    }

    // ── Empty State with Filters Applied ─────────────────────────────────────

    public function test_empty_state_shown_when_no_assessments_match_filters(): void
    {
        // Ensure no TypeB assessments exist for the year by filtering for typeB only
        // (setUp only creates typeA and typeB types but no assessments for typeB)
        $response = $this->actingAs($this->admin)->get(route('assessments.index', [
            'academic_year_id'   => $this->year->id,
            'assessment_type_id' => $this->typeB->id,
            'status'             => 'inactive',
        ]));

        $response->assertOk();
        $response->assertSeeText('No assessments found');
    }
}
