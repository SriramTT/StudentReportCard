<?php

namespace Tests\Feature\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\User;
use App\Services\AcademicYearService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
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

    public function test_administrator_can_create_and_view_academic_years_within_date_boundaries(): void
    {
        $startDate = now()->toDateString();
        $endDate = now()->addMonthsNoOverflow(10)->toDateString();
        $yearName = 'AY-' . rand(10000, 99999);

        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => $yearName,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response->assertRedirect(route('academic_years.index'));
        $response->assertSessionHas('success');

        $year = AcademicYear::where('name', $yearName)->first();
        $this->assertNotNull($year);
        $this->assertTrue($year->is_current, 'A year spanning today should automatically derive is_current as true.');
        $this->assertEquals(AcademicYearStatus::OPEN, $year->status);

        $indexResponse = $this->actingAs($this->admin)->get(route('academic_years.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee($yearName);
    }

    public function test_start_date_cannot_be_before_today(): void
    {
        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => 'Past-' . rand(1000, 9999),
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonthsNoOverflow(10)->toDateString(),
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_start_date_cannot_exceed_six_calendar_months_from_today(): void
    {
        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => 'Future-' . rand(1000, 9999),
            'start_date' => now()->addMonthsNoOverflow(6)->addDays(2)->toDateString(),
            'end_date' => now()->addMonthsNoOverflow(12)->toDateString(),
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $startDate = now()->addMonthsNoOverflow(1)->toDateString();

        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => 'EndBf-' . rand(1000, 9999),
            'start_date' => $startDate,
            'end_date' => $startDate,
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_end_date_cannot_exceed_eighteen_calendar_months_from_today(): void
    {
        $startDate = now()->addMonthsNoOverflow(1)->toDateString();
        $endDate = now()->addMonthsNoOverflow(18)->addDays(2)->toDateString();

        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => 'FarEnd-' . rand(1000, 9999),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_configuration_window_start_and_end_calculation(): void
    {
        // Start date: 2027-04-01, End date: 2028-03-31
        $year = AcademicYear::forceCreate([
            'name' => 'CalWin-' . rand(1000, 9999),
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        // Window start: start_date - 1 calendar month = 2027-03-01
        $this->assertEquals('2027-03-01', $year->configurationWindowStart()?->toDateString());

        // Window end: end_date - 2 calendar months = 2028-01-31
        $this->assertEquals('2028-01-31', $year->configurationWindowEnd()?->toDateString());

        // Before window: 2027-02-28 -> Locked
        $beforeDate = Carbon::parse('2027-02-28');
        $this->assertFalse($year->isConfigurationWindowOpen($beforeDate));
        $this->assertTrue($year->isConfigurationWindowLocked($beforeDate));
        $this->assertStringContainsString('Locked', $year->configurationWindowStatus($beforeDate));

        // Exact opening date: 2027-03-01 -> Open
        $openDate = Carbon::parse('2027-03-01');
        $this->assertTrue($year->isConfigurationWindowOpen($openDate));
        $this->assertFalse($year->isConfigurationWindowLocked($openDate));
        $this->assertStringContainsString('Open', $year->configurationWindowStatus($openDate));

        // Inside window: 2027-08-15 -> Open
        $midDate = Carbon::parse('2027-08-15');
        $this->assertTrue($year->isConfigurationWindowOpen($midDate));

        // Exact closing date: 2028-01-31 -> Open
        $closeDate = Carbon::parse('2028-01-31');
        $this->assertTrue($year->isConfigurationWindowOpen($closeDate));

        // One day after closing: 2028-02-01 -> Locked
        $afterCloseDate = Carbon::parse('2028-02-01');
        $this->assertFalse($year->isConfigurationWindowOpen($afterCloseDate));
        $this->assertTrue($year->isConfigurationWindowLocked($afterCloseDate));
        $this->assertStringContainsString('Locked', $year->configurationWindowStatus($afterCloseDate));
    }

    public function test_academic_year_add_modal_and_index_presentation(): void
    {
        $response = $this->actingAs($this->admin)->get(route('academic_years.index'));
        $response->assertStatus(200);

        // Assert "+ Add Academic Year" button and modal are present
        $response->assertSee('id="btn-add-academic-year"', false);
        $response->assertSee('id="create-year-modal"', false);

        // Assert misleading "Locked (opens ...)" is NOT present
        $response->assertDontSee('Locked (opens');
    }

    public function test_date_derived_current_and_expired_resolution(): void
    {
        $service = app(AcademicYearService::class);

        // Upcoming year (start date is next month)
        $upcomingYear = AcademicYear::forceCreate([
            'name' => 'Upc-' . rand(1000, 9999),
            'start_date' => now()->addMonthsNoOverflow(1)->toDateString(),
            'end_date' => now()->addMonthsNoOverflow(11)->toDateString(),
            'status' => AcademicYearStatus::OPEN,
            'is_current' => false,
        ]);

        $this->assertTrue($upcomingYear->isDateUpcoming());
        $this->assertFalse($upcomingYear->isDateCurrent());
        $this->assertFalse($upcomingYear->isDateExpired());

        // Expired year (ended yesterday)
        $expiredYear = AcademicYear::forceCreate([
            'name' => 'Exp-' . rand(1000, 9999),
            'start_date' => now()->subMonthsNoOverflow(10)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'status' => AcademicYearStatus::CLOSED,
            'is_current' => false,
        ]);

        $this->assertTrue($expiredYear->isDateExpired());
        $this->assertTrue($expiredYear->isClosed());
        $this->assertFalse($expiredYear->isDateCurrent());
    }

    public function test_multiple_overlapping_current_academic_years_are_rejected(): void
    {
        // Year 1 covers today
        $year1 = AcademicYear::forceCreate([
            'name' => 'Over1-' . rand(1000, 9999),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonthsNoOverflow(10)->toDateString(),
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        // Attempting to create Year 2 that also covers today should be rejected by server validation
        $response = $this->actingAs($this->admin)->post(route('academic_years.store'), [
            'name' => 'Over2-' . rand(1000, 9999),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonthsNoOverflow(8)->toDateString(),
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_ui_does_not_contain_set_current_close_or_reopen_actions(): void
    {
        $year = AcademicYear::forceCreate([
            'name' => 'UI-' . rand(1000, 9999),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonthsNoOverflow(10)->toDateString(),
            'status' => AcademicYearStatus::OPEN,
            'is_current' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('academic_years.index'));
        $response->assertStatus(200);

        // Verify Set Current, Close, Reopen buttons/actions are NOT present
        $response->assertDontSee('Set Current');
        $response->assertDontSee('Set as Current');
        $response->assertDontSee('Close Academic Year');
        $response->assertDontSee('Reopen Academic Year');
    }

    public function test_office_staff_can_create_academic_year_while_teacher_is_forbidden(): void
    {
        $startDate = now()->addMonthsNoOverflow(1)->toDateString();
        $endDate = now()->addMonthsNoOverflow(11)->toDateString();

        // Office Staff can view
        $viewResponse = $this->actingAs($this->officeStaff)->get(route('academic_years.index'));
        $viewResponse->assertStatus(200);

        // Office Staff can create
        $createResponse = $this->actingAs($this->officeStaff)->post(route('academic_years.store'), [
            'name' => 'OffAY-' . rand(1000, 9999),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $createResponse->assertRedirect(route('academic_years.index'));
        $createResponse->assertSessionHas('success');

        // Teacher cannot create
        $teacherCreate = $this->actingAs($this->teacher)->post(route('academic_years.store'), [
            'name' => 'TchAY-' . rand(1000, 9999),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $teacherCreate->assertStatus(403);
    }
}
