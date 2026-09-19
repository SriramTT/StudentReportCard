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
}
