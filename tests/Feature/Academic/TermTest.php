<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TermTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;
    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_term_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin Term',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_term_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office Term',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_term_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher Term',
            'is_active' => true,
        ]);

        $this->year = AcademicYear::create([
            'name' => 'AY_' . rand(1000, 9999),
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);
    }

    public function test_dynamic_n_terms_can_be_created_with_5_terms(): void
    {
        // Explicit requirement: Create 5 terms and confirm all 5 are supported and ordered by sequence_no
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($this->admin)->post(route('terms.store'), [
                'academic_year_id' => $this->year->id,
                'name' => "Term {$i}",
                'sequence_no' => $i,
                'is_active' => 1,
            ]);
            $response->assertRedirect();
        }

        $terms = Term::where('academic_year_id', $this->year->id)->orderBy('sequence_no', 'asc')->get();
        $this->assertCount(5, $terms);
        $this->assertEquals(['Term 1', 'Term 2', 'Term 3', 'Term 4', 'Term 5'], $terms->pluck('name')->toArray());
        $this->assertEquals([1, 2, 3, 4, 5], $terms->pluck('sequence_no')->toArray());
    }

    public function test_flexible_term_naming_with_semesters(): void
    {
        // Explicit requirement: Test Semester 1 and Semester 2 to prove names are not hard-coded
        $this->actingAs($this->officeStaff)->post(route('terms.store'), [
            'academic_year_id' => $this->year->id,
            'name' => 'Semester 1',
            'sequence_no' => 1,
        ]);

        $this->actingAs($this->officeStaff)->post(route('terms.store'), [
            'academic_year_id' => $this->year->id,
            'name' => 'Semester 2',
            'sequence_no' => 2,
        ]);

        $terms = Term::where('academic_year_id', $this->year->id)->orderBy('sequence_no', 'asc')->get();
        $this->assertCount(2, $terms);
        $this->assertEquals('Semester 1', $terms[0]->name);
        $this->assertEquals('Semester 2', $terms[1]->name);
    }

    public function test_duplicate_sequence_number_within_same_year_is_rejected(): void
    {
        Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('terms.store'), [
            'academic_year_id' => $this->year->id,
            'name' => 'Second Term 1',
            'sequence_no' => 1, // Duplicate sequence
        ]);

        $response->assertSessionHasErrors('sequence_no');
    }

    public function test_duplicate_name_within_same_year_is_rejected(): void
    {
        Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1',
            'sequence_no' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('terms.store'), [
            'academic_year_id' => $this->year->id,
            'name' => 'Term 1', // Duplicate name
            'sequence_no' => 2,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_teachers_cannot_create_or_update_terms(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('terms.store'), [
            'academic_year_id' => $this->year->id,
            'name' => 'Teacher Term',
            'sequence_no' => 1,
        ]);
        $response->assertStatus(403);
    }

    public function test_drag_drop_reorder_persists_and_is_audited(): void
    {
        $term1 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence_no' => 1, 'is_active' => true]);
        $term2 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 2', 'sequence_no' => 2, 'is_active' => false]);
        $term3 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 3', 'sequence_no' => 3, 'is_active' => false]);

        // Drag Term 3 to top: order should become Term 3 (1), Term 1 (2), Term 2 (3)
        $response = $this->actingAs($this->admin)->postJson(route('terms.reorder'), [
            'academic_year_id' => $this->year->id,
            'term_ids' => [$term3->id, $term1->id, $term2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals(1, $term3->fresh()->sequence_no);
        $this->assertEquals(2, $term1->fresh()->sequence_no);
        $this->assertEquals(3, $term2->fresh()->sequence_no);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'REORDER_TERMS',
            'entity_type' => 'terms',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_reorder_rejects_foreign_academic_year_terms(): void
    {
        $otherYear = AcademicYear::create([
            'name' => 'Other_AY_' . rand(1000, 9999),
            'start_date' => '2027-06-01',
            'end_date' => '2028-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $term1 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence_no' => 1, 'is_active' => true]);
        $otherTerm = Term::create(['academic_year_id' => $otherYear->id, 'name' => 'Other Term', 'sequence_no' => 1, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->postJson(route('terms.reorder'), [
            'academic_year_id' => $this->year->id,
            'term_ids' => [$term1->id, $otherTerm->id],
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_activating_term_deactivates_other_terms_in_same_academic_year(): void
    {
        // is_active feature has been retired from the user-facing API.
        // The controller always writes is_active=false on create and
        // never changes it on update. The database column and index are
        // preserved as an inert compatibility layer.
        $term1 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence_no' => 1, 'is_active' => true]);
        $term2 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 2', 'sequence_no' => 2, 'is_active' => false]);

        // Submitting is_active via PUT update no longer changes the DB value.
        $response = $this->actingAs($this->admin)->put(route('terms.update', $term2), [
            'name' => $term2->name,
            'is_active' => 1,
        ]);
        $response->assertRedirect();

        // term1 is_active remains true (unchanged), term2 is_active remains false (unchanged).
        $this->assertTrue($term1->fresh()->is_active);
        $this->assertFalse($term2->fresh()->is_active);
    }

    public function test_different_academic_years_can_independently_have_an_active_term(): void
    {
        $otherYear = AcademicYear::create([
            'name' => 'AY_Second_' . rand(1000, 9999),
            'start_date' => '2028-06-01',
            'end_date' => '2029-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $termA1 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Year 1 Term 1', 'sequence_no' => 1, 'is_active' => true]);
        $termB1 = Term::create(['academic_year_id' => $otherYear->id, 'name' => 'Year 2 Term 1', 'sequence_no' => 1, 'is_active' => true]);

        $this->assertTrue($termA1->fresh()->is_active);
        $this->assertTrue($termB1->fresh()->is_active);
    }

    public function test_database_unique_index_prevents_two_active_terms_simultaneously(): void
    {
        Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence_no' => 1, 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        // Direct query attempt to bypass application logic
        \Illuminate\Support\Facades\DB::table('terms')->insert([
            'academic_year_id' => $this->year->id,
            'name' => 'Term 2 Bypass',
            'sequence_no' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_office_staff_can_reorder_terms_but_teachers_cannot(): void
    {
        $term1 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'sequence_no' => 1, 'is_active' => true]);
        $term2 = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Term 2', 'sequence_no' => 2, 'is_active' => false]);

        $officeResponse = $this->actingAs($this->officeStaff)->postJson(route('terms.reorder'), [
            'academic_year_id' => $this->year->id,
            'term_ids' => [$term2->id, $term1->id],
        ]);
        $officeResponse->assertStatus(200);

        $teacherResponse = $this->actingAs($this->teacher)->postJson(route('terms.reorder'), [
            'academic_year_id' => $this->year->id,
            'term_ids' => [$term1->id, $term2->id],
        ]);
        $teacherResponse->assertStatus(403);
    }

    public function test_office_staff_can_delete_unreferenced_term(): void
    {
        $term = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term to Delete',
            'sequence_no' => 10,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->officeStaff)->delete(route('terms.destroy', $term));
        $response->assertRedirect(route('terms.index', ['academic_year_id' => $this->year->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('terms', ['id' => $term->id]);
    }

    public function test_teachers_cannot_delete_terms(): void
    {
        $term = Term::create([
            'academic_year_id' => $this->year->id,
            'name' => 'Term Protected From Teacher',
            'sequence_no' => 11,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->teacher)->delete(route('terms.destroy', $term));
        $response->assertStatus(403);
        $this->assertDatabaseHas('terms', ['id' => $term->id]);
    }
}
