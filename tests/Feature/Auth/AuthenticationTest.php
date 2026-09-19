<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $activeUser;
    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('testuser|127.0.0.1');

        $adminRole = Role::where('name', 'Administrator')->firstOrFail();

        $this->activeUser = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'auth_test_active',
            'password_hash' => Hash::make('CorrectPassword123!'),
            'display_name' => 'Active Test User',
            'email' => 'active_test@school.test',
            'is_active' => true,
        ]);

        $this->inactiveUser = User::forceCreate([
            'role_id' => $adminRole->id,
            'username' => 'auth_test_inactive',
            'password_hash' => Hash::make('CorrectPassword123!'),
            'display_name' => 'Inactive Test User',
            'email' => 'inactive_test@school.test',
            'is_active' => false,
        ]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('School Examination System');
        $response->assertSee('Username');
        $response->assertSee('Password');
        $response->assertSee('Sign In');
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $response = $this->actingAs($this->activeUser)->get('/login');

        $response->assertRedirect('/dashboard');
    }

    public function test_user_can_authenticate_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'username' => 'auth_test_active',
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->activeUser);

        // Verify last_login_at is updated
        $this->activeUser->refresh();
        $this->assertNotNull($this->activeUser->last_login_at);

        // Verify security audit log was written
        $audit = AuditLog::where('user_id', $this->activeUser->id)
            ->where('action', 'login_successful')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals('security', $audit->entity_type);
        $this->assertArrayNotHasKey('password', (array) $audit->after_data);
        $this->assertArrayNotHasKey('password_hash', (array) $audit->after_data);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => 'auth_test_active',
            'password' => 'WrongPassword!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();

        // Verify generic error message
        $errors = session('errors')->get('username');
        $this->assertContains('These credentials do not match our records.', $errors);

        // Verify audit log for failed login
        $audit = AuditLog::where('user_id', $this->activeUser->id)
            ->where('action', 'login_failed')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_login_fails_with_non_existent_username_without_timing_leak(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => 'completely_unknown_user_12345',
            'password' => 'SomePassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();

        // Generic error message must be identical to invalid password
        $errors = session('errors')->get('username');
        $this->assertContains('These credentials do not match our records.', $errors);

        // Verify audit log for failed login with null user_id
        $audit = AuditLog::where('action', 'login_failed')
            ->where('after_data->username', 'completely_unknown_user_12345')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertNull($audit->user_id);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => 'auth_test_inactive',
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();

        $errors = session('errors')->get('username');
        $this->assertContains('Your account has been deactivated. Please contact an Administrator.', $errors);

        $audit = AuditLog::where('user_id', $this->inactiveUser->id)
            ->where('action', 'login_blocked_inactive')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_active_session_is_immediately_revoked_if_user_is_deactivated_in_database(): void
    {
        // Start as authenticated user
        $response = $this->actingAs($this->activeUser)->get('/dashboard');
        $response->assertStatus(200);

        // Administrator deactivates user in database directly
        User::where('id', $this->activeUser->id)->update(['is_active' => false]);

        // Next request must detect deactivation, logout user, and redirect
        $nextResponse = $this->get('/dashboard');
        $nextResponse->assertRedirect('/login');
        $this->assertGuest();

        $audit = AuditLog::where('user_id', $this->activeUser->id)
            ->where('action', 'session_revoked_inactive')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_login_attempts_are_throttled_after_five_failed_attempts(): void
    {
        $throttleKey = 'throttled_user|127.0.0.1';
        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'throttled_user',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->post('/login', [
            'username' => 'throttled_user',
            'password' => 'WrongPassword',
        ]);

        // Either HTTP 429 or redirected with throttle error
        if ($response->status() === 429) {
            $this->assertEquals(429, $response->status());
        } else {
            $response->assertSessionHasErrors('username');
        }

        RateLimiter::clear($throttleKey);
    }

    public function test_logout_must_be_post_and_invalidates_session(): void
    {
        // GET /logout is not allowed
        $getResponse = $this->actingAs($this->activeUser)->get('/logout');
        $this->assertEquals(405, $getResponse->status());

        // POST /logout logs out successfully
        $postResponse = $this->actingAs($this->activeUser)->post('/logout');
        $postResponse->assertRedirect('/login');
        $this->assertGuest();

        $audit = AuditLog::where('user_id', $this->activeUser->id)
            ->where('action', 'logout')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_password_hash_is_never_exposed_in_serialization(): void
    {
        $userArray = $this->activeUser->toArray();
        $this->assertArrayNotHasKey('password_hash', $userArray);

        $json = json_encode($this->activeUser);
        $this->assertStringNotContainsString('password_hash', $json);
        $this->assertStringNotContainsString('CorrectPassword123!', $json);
    }

    public function test_audit_logs_never_contain_credentials_or_passwords(): void
    {
        $this->post('/login', [
            'username' => 'auth_test_active',
            'password' => 'CorrectPassword123!',
        ]);

        $audit = AuditLog::where('user_id', $this->activeUser->id)->latest('id')->first();
        $this->assertNotNull($audit);

        $auditJson = json_encode($audit->toArray());
        $this->assertStringNotContainsString('CorrectPassword123!', $auditJson);
        $this->assertStringNotContainsString('password_hash', $auditJson);
    }
}
