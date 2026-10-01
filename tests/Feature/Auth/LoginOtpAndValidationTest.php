<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginOtpAndValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $adminRole;
    protected Role $officeRole;
    protected Role $teacherRole;
    protected User $adminUser;
    protected User $activeUser;
    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('testotpuser|127.0.0.1');

        $this->adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $this->officeRole = Role::where('name', 'Office Staff')->firstOrFail();
        $this->teacherRole = Role::where('name', 'Subject Teacher')->firstOrFail();

        $this->adminUser = User::forceCreate([
            'role_id' => $this->adminRole->id,
            'username' => 'admin_tester',
            'display_name' => 'Admin Tester',
            'email' => 'admin_tester@school.test',
            'password_hash' => Hash::make('ValidPassword123!'),
            'is_active' => true,
        ]);

        $this->activeUser = User::forceCreate([
            'role_id' => $this->teacherRole->id,
            'username' => 'active_teacher',
            'display_name' => 'Active Teacher',
            'email' => 'teacher@school.test',
            'password_hash' => Hash::make('ValidPassword123!'),
            'is_active' => true,
        ]);

        $this->inactiveUser = User::forceCreate([
            'role_id' => $this->teacherRole->id,
            'username' => 'inactive_teacher',
            'display_name' => 'Inactive Teacher',
            'email' => 'inactive@school.test',
            'password_hash' => Hash::make('ValidPassword123!'),
            'is_active' => false,
        ]);
    }

    // ============================================================
    // SCOPE A: USERNAME & EMAIL VALIDATION
    // ============================================================

    public function test_new_username_with_uppercase_letters_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'UpperTeacher',
            'display_name' => 'Upper Teacher',
            'email' => 'upper@school.test',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseMissing('users', ['email' => 'upper@school.test']);
    }

    public function test_new_lowercase_username_is_accepted(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'lower_teacher',
            'display_name' => 'Lower Teacher',
            'email' => 'lower@school.test',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'lower_teacher']);
    }

    public function test_new_email_with_uppercase_letters_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'teacher_caps_email',
            'display_name' => 'Caps Email Teacher',
            'email' => 'TeacherCaps@School.Test',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_missing_or_invalid_email_is_rejected_on_new_user_creation(): void
    {
        // Missing email
        $resMissing = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'no_email_teacher',
            'display_name' => 'No Email Teacher',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $resMissing->assertSessionHasErrors('email');

        // Invalid format email
        $resInvalid = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'bad_email_teacher',
            'display_name' => 'Bad Email Teacher',
            'email' => 'not-a-valid-email',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $resInvalid->assertSessionHasErrors('email');
    }

    public function test_duplicate_email_is_rejected_regardless_of_letter_case(): void
    {
        // Active user has 'teacher@school.test'
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'duplicate_email_user',
            'display_name' => 'Duplicate Email',
            'email' => 'TEACHER@school.test',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_duplicate_email_belonging_to_inactive_account_is_rejected(): void
    {
        // Inactive user has 'inactive@school.test'
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'username' => 'stealing_inactive_email',
            'display_name' => 'Stealing Inactive Email',
            'email' => 'inactive@school.test',
            'role_id' => $this->teacherRole->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_editing_unrelated_fields_allows_unchanged_legacy_uppercase_credentials(): void
    {
        // Create legacy user with uppercase username and email
        $legacyUser = User::forceCreate([
            'role_id' => $this->teacherRole->id,
            'username' => 'LegacyUPPER_User',
            'display_name' => 'Legacy User',
            'email' => 'LegacyUPPER@School.Test',
            'password_hash' => Hash::make('ValidPassword123!'),
            'is_active' => true,
        ]);

        // Administrator edits ONLY the display name, submitting existing username and email unchanged
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $legacyUser), [
            'username' => 'LegacyUPPER_User',
            'display_name' => 'Updated Display Name',
            'email' => 'LegacyUPPER@School.Test',
            'role_id' => $this->teacherRole->id,
        ]);

        $response->assertSessionHasNoErrors();
        $legacyUser->refresh();

        // Ensure legacy uppercase credentials were NOT normalized or rejected
        $this->assertEquals('LegacyUPPER_User', $legacyUser->username);
        $this->assertEquals('LegacyUPPER@School.Test', $legacyUser->email);
        $this->assertEquals('Updated Display Name', $legacyUser->display_name);
    }

    public function test_changing_an_existing_username_to_uppercase_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $this->activeUser), [
            'username' => 'NEW_UPPER_USERNAME',
            'display_name' => 'New Upper',
            'email' => $this->activeUser->email,
            'role_id' => $this->teacherRole->id,
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_changing_an_existing_email_to_uppercase_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $this->activeUser), [
            'username' => $this->activeUser->username,
            'display_name' => $this->activeUser->display_name,
            'email' => 'NEW_UPPER@school.test',
            'role_id' => $this->teacherRole->id,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_editing_email_to_another_accounts_email_is_rejected_case_insensitively(): void
    {
        // Admin has 'admin_tester@school.test'. Active teacher tries to take it in uppercase.
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $this->activeUser), [
            'username' => $this->activeUser->username,
            'display_name' => $this->activeUser->display_name,
            'email' => 'ADMIN_TESTER@school.test',
            'role_id' => $this->teacherRole->id,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_existing_username_login_matching_behavior_remains_unchanged(): void
    {
        // Test that exact username matching is preserved
        $response = $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        // When OTP is disabled, valid password logs in directly
        config(['auth.otp.enabled' => false]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->activeUser);
    }

    // ============================================================
    // SCOPE B: SCHOOL BRANDING ON LOGIN VIEW
    // ============================================================

    public function test_login_shows_both_school_logo_and_name_when_configured(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/test_logo.png', 'dummy image content');

        SchoolSetting::truncate();
        SchoolSetting::create([
            'school_name' => 'Springfield High Academy',
            'school_logo_path' => 'logos/test_logo.png',
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Springfield High Academy');
        $response->assertSee('storage/logos/test_logo.png');
    }

    public function test_login_shows_logo_only_when_name_is_not_configured(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/logo_only.png', 'dummy image content');

        SchoolSetting::truncate();
        SchoolSetting::create([
            'school_name' => '',
            'school_logo_path' => 'logos/logo_only.png',
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('storage/logos/logo_only.png');
        $response->assertDontSee('Springfield High Academy');
        $response->assertDontSee('School Examination System');
    }

    public function test_login_shows_name_only_when_logo_is_not_configured(): void
    {
        SchoolSetting::truncate();
        SchoolSetting::create([
            'school_name' => 'Capital City Grammar',
            'school_logo_path' => null,
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Capital City Grammar');
        $response->assertDontSee('auth-logo');
    }

    public function test_login_shows_default_fallback_when_neither_is_configured(): void
    {
        SchoolSetting::truncate();

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('School Examination System');
        $response->assertDontSee('auth-logo');
    }

    // ============================================================
    // SCOPE C: TWO-STEP VERIFICATION (EMAIL OTP)
    // ============================================================

    public function test_valid_password_redirects_to_otp_and_does_not_authenticate_user_when_otp_enabled(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $response = $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        // Must redirect to OTP screen, user must NOT be authenticated yet
        $response->assertRedirect(route('login.otp'));
        $this->assertGuest();

        // Must send LoginOtpMail
        Mail::assertSent(LoginOtpMail::class, function ($mail) {
            return $mail->hasTo('teacher@school.test');
        });

        // OTP screen can be viewed
        $otpScreen = $this->get(route('login.otp'));
        $otpScreen->assertStatus(200);
        $otpScreen->assertSee('Two-Step Verification');
        $otpScreen->assertSee('t***r@school.test');
    }

    public function test_correct_otp_authenticates_user_and_clears_challenge(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        // 1. Password step
        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);
        $this->assertGuest();

        // 2. Fetch the challenge and simulate correct OTP
        // Since OTP is securely hashed in session, let's inject a known hashed OTP in session
        $plainOtp = '123456';
        $challenge = session('auth.otp_challenge');
        $challenge['hashed_otp'] = Hash::make($plainOtp);
        session(['auth.otp_challenge' => $challenge]);

        // 3. Submit valid OTP
        $response = $this->post(route('login.otp.verify'), [
            'otp' => $plainOtp,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->activeUser);

        // Challenge must be cleared to prevent reuse
        $this->assertNull(session('auth.otp_challenge'));
    }

    public function test_invalid_otp_decrements_attempts_and_does_not_authenticate(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        $plainOtp = '654321';
        $challenge = session('auth.otp_challenge');
        $challenge['hashed_otp'] = Hash::make($plainOtp);
        session(['auth.otp_challenge' => $challenge]);

        // Submit wrong code
        $response = $this->from(route('login.otp'))->post(route('login.otp.verify'), [
            'otp' => '000000',
        ]);

        $response->assertRedirect(route('login.otp'));
        $this->assertGuest();

        // Attempts incremented
        $challengeAfter = session('auth.otp_challenge');
        $this->assertEquals(1, $challengeAfter['attempts']);
    }

    public function test_three_failed_otp_attempts_invalidates_challenge(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        $plainOtp = '654321';
        $challenge = session('auth.otp_challenge');
        $challenge['hashed_otp'] = Hash::make($plainOtp);
        $challenge['attempts'] = 2; // already 2 failed attempts
        session(['auth.otp_challenge' => $challenge]);

        // 3rd failed attempt
        $response = $this->from(route('login.otp'))->post(route('login.otp.verify'), [
            'otp' => '000000',
        ]);

        // Challenge must be invalidated and redirect to login
        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth.otp_challenge'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        $plainOtp = '123456';
        $challenge = session('auth.otp_challenge');
        $challenge['hashed_otp'] = Hash::make($plainOtp);
        $challenge['expires_at'] = now()->subMinutes(10)->timestamp; // Expired!
        session(['auth.otp_challenge' => $challenge]);

        $response = $this->from(route('login.otp'))->post(route('login.otp.verify'), [
            'otp' => $plainOtp,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth.otp_challenge'));
    }

    public function test_resend_cooldown_is_enforced_server_side(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        // Immediately attempt resend (within 60s cooldown)
        $response = $this->from(route('login.otp'))->post(route('login.otp.resend'));

        $response->assertRedirect(route('login.otp'));
        $response->assertSessionHasErrors('otp');
        $this->assertStringContainsString('60 seconds', session('errors')->first('otp'));
    }

    public function test_resend_after_cooldown_invalidates_previous_otp_and_sends_new_code(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        // Simulate 65 seconds elapsed
        $challenge = session('auth.otp_challenge');
        $challenge['last_sent_at'] = now()->subSeconds(65)->timestamp;
        $originalHash = $challenge['hashed_otp'];
        session(['auth.otp_challenge' => $challenge]);

        // Request resend
        $response = $this->from(route('login.otp'))->post(route('login.otp.resend'));

        $response->assertRedirect(route('login.otp'));
        $response->assertSessionHas('status');

        $newChallenge = session('auth.otp_challenge');
        // Old hash invalidated
        $this->assertNotEquals($originalHash, $newChallenge['hashed_otp']);
        $this->assertEquals(0, $newChallenge['attempts']);
    }

    public function test_otp_verification_without_pending_challenge_redirects_to_login(): void
    {
        config(['auth.otp.enabled' => true]);

        // No session challenge
        $response = $this->post(route('login.otp.verify'), [
            'otp' => '123456',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_otp_is_never_exposed_in_blade_view(): void
    {
        config(['auth.otp.enabled' => true]);
        Mail::fake();

        $this->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        $response = $this->get(route('login.otp'));
        $response->assertStatus(200);

        // Plain OTP must NOT exist in the HTML output
        $otpMail = Mail::sent(LoginOtpMail::class)->first();
        $this->assertNotNull($otpMail);
        $plainOtp = $otpMail->otp;

        $response->assertDontSee($plainOtp);
    }

    public function test_mail_delivery_failure_fails_closed_and_does_not_authenticate(): void
    {
        config(['auth.otp.enabled' => true]);
        
        // Mock Mail to throw exception
        Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP Connection Refused'));

        $response = $this->from('/login')->post(route('login.submit'), [
            'username' => 'active_teacher',
            'password' => 'ValidPassword123!',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
        // Challenge must be cleaned up / fail-closed
        $this->assertNull(session('auth.otp_challenge'));
    }

    // ============================================================
    // SCOPE H: LOGIN UX & CREATE STAFF CREDENTIAL SAFETY
    // ============================================================

    public function test_login_shows_field_specific_error_when_username_is_empty(): void
    {
        $response = $this->from(route('login'))->post(route('login.submit'), [
            'username' => '',
            'password' => 'SomePassword123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();

        $errors = session('errors')->get('username');
        $this->assertContains('Username is required.', $errors);
    }

    public function test_login_shows_field_specific_error_when_password_is_empty(): void
    {
        $response = $this->from(route('login'))->post(route('login.submit'), [
            'username' => 'admin_tester',
            'password' => '',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('password');
        $this->assertGuest();

        $errors = session('errors')->get('password');
        $this->assertContains('Password is required.', $errors);
    }

    public function test_login_shows_both_field_errors_when_both_fields_are_empty(): void
    {
        $response = $this->from(route('login'))->post(route('login.submit'), [
            'username' => '',
            'password' => '',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username', 'password']);
        $this->assertGuest();
    }

    public function test_login_page_renders_password_eye_toggle_button(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('id="toggle-password-btn"', false);
        $response->assertSee('id="eye-icon-show"', false);
        $response->assertSee('id="eye-icon-hide"', false);
    }

    public function test_create_staff_form_does_not_prefill_authenticated_user_credentials(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('users.index'));

        $response->assertStatus(200);
        // Form attributes must prevent autofill
        $response->assertSee('id="create-user-form"', false);
        $response->assertSee('autocomplete="new-password"', false);
        $response->assertSee('id="create_password"', false);
        $response->assertSee('id="create_password_confirmation"', false);

        // Ensure current user's username is not rendered inside the input value of create form
        $response->assertDontSee('id="create_username" name="username" class="form-control" required maxlength="100" placeholder="e.g. john.doe" value="' . $this->adminUser->username . '"', false);
    }
}
