<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $officeStaff;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->admin = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'admin_ay_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Admin AY',
            'is_active' => true,
        ]);

        $this->officeStaff = User::forceCreate([
            'role_id' => $officeRole->id,
            'username' => 'office_ay_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Office AY',
            'is_active' => true,
        ]);

        $this->teacher = User::forceCreate([
            'role_id' => $teacherRole->id,
            'username' => 'teacher_ay_' . uniqid(),
            'password_hash' => Hash::make('password'),
            'display_name' => 'Teacher AY',
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_create_and_view_academic_years(): void
    {
        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-04-30',
            'is_current' => 1,
        ]);

        $response->assertRedirect(route('academic_years.index'));
        $response->assertSessionHas('success');

        $year = AcademicYear::where('name', '2026-2027')->first();
        $this->assertNotNull($year);
        $this->assertTrue($year->is_current);
        $this->assertEquals(AcademicYearStatus::OPEN, $year->status);

        $indexResponse = $this->actingAs($this->admin)->get(route('academic_years.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('2026-2027');
    }

    public function test_at_most_one_academic_year_is_current(): void
    {
        $year1 = AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => '2024-06-01',
            'end_date' => '2025-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => '2025-2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-04-30',
            'is_current' => 1,
        ]);

        $year1->refresh();
        $year2 = AcademicYear::where('name', '2025-2026')->firstOrFail();

        $this->assertFalse($year1->is_current, 'Previous academic year must no longer be current.');
        $this->assertTrue($year2->is_current, 'New academic year must now be current.');

        $currentCount = AcademicYear::where('is_current', true)->count();
        $this->assertEquals(1, $currentCount);
    }

    public function test_academic_year_can_be_closed_and_reopened_by_administrator(): void
    {
        $year = AcademicYear::create([
            'name' => '2023-2024',
            'start_date' => '2023-06-01',
            'end_date' => '2024-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        // Close year
        $closeResponse = $this->actingAs($this->admin)->post(route('academic_years.close', $year));
        $closeResponse->assertRedirect(route('academic_years.index'));
        $year->refresh();
        $this->assertEquals(AcademicYearStatus::CLOSED, $year->status);

        // Reopen year
        $reopenResponse = $this->actingAs($this->admin)->post(route('academic_years.reopen', $year));
        $reopenResponse->assertRedirect(route('academic_years.index'));
        $year->refresh();
        $this->assertEquals(AcademicYearStatus::OPEN, $year->status);
    }

    public function test_office_staff_can_view_but_cannot_modify_or_close_academic_year(): void
    {
        $year = AcademicYear::create([
            'name' => '2022-2023',
            'start_date' => '2022-06-01',
            'end_date' => '2023-04-30',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        // Office Staff can view
        $viewResponse = $this->actingAs($this->officeStaff)->get(route('academic_years.index'));
        $viewResponse->assertStatus(200);

        // Office Staff cannot create
        $createResponse = $this->actingAs($this->officeStaff)->post(route('academic_years.store'), [
            'name' => '2027-2028',
            'start_date' => '2027-06-01',
            'end_date' => '2028-04-30',
        ]);
        $createResponse->assertStatus(403);

        // Office Staff cannot close
        $closeResponse = $this->actingAs($this->officeStaff)->post(route('academic_years.close', $year));
        $closeResponse->assertStatus(403);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => 'Invalid-Dates-Year',
            'start_date' => '2026-06-01',
            'end_date' => '2025-06-01',
        ]);

        $response->assertSessionHasErrors('end_date');
    }
}
