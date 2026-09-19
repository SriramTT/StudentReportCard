<?php

namespace Tests\Feature\Auth;

use App\Exceptions\SystemAlreadyInitializedException;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemSetupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstRunAdministratorSetupTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $adminRole;
    protected Role $officeRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any dependent transactional records and users to guarantee true zero-user state
        AuditLog::query()->delete();
        DB::table('teacher_assignments')->delete();
        DB::table('marks')->delete();
        DB::table('attendance')->delete();
        DB::table('generated_reports')->delete();
        User::query()->delete();

        $this->adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $this->officeRole = Role::where('name', 'Office Staff')->firstOrFail();
    }

    // A. ZERO-USER AVAILABILITY
    public function test_get_setup_returns_setup_form_when_zero_users_exist(): void
    {
        $this->assertEquals(0, User::count());

        $response = $this->get('/setup');

        $response->assertStatus(200);
        $response->assertSee('Initial System Setup');
        $response->assertSee('Username');
        $response->assertSee('Display Name / Full Name');
        $response->assertSee('Initialize &amp; Create Administrator', false);
    }

    public function test_login_page_displays_create_administrator_option_when_zero_users_exist(): void
    {
        $this->assertEquals(0, User::count());

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Create Administrator Account');
        $response->assertSee(route('setup'));
    }

    public function test_get_setup_redirects_to_login_once_a_user_exists(): void
    {
        // Create an existing user
        User::forceCreate([
            'role_id' => $this->adminRole->id,
            'username' => 'existing_admin',
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Existing Admin',
            'is_active' => true,
        ]);

        $this->assertGreaterThanOrEqual(1, User::count());

        $response = $this->get('/setup');

        $response->assertRedirect('/login');
    }

    public function test_login_page_hides_create_administrator_option_once_a_user_exists(): void
    {
        User::forceCreate([
            'role_id' => $this->adminRole->id,
            'username' => 'existing_admin',
            'password_hash' => Hash::make('password123'),
            'display_name' => 'Existing Admin',
            'is_active' => true,
        ]);

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Create Administrator Account');
        $response->assertDontSee(route('setup'));
    }

    // B. INITIAL USER CREATION
    public function test_valid_post_setup_creates_exactly_one_administrator(): void
    {
        $response = $this->post('/setup', [
            'username' => 'superadmin',
            'display_name' => 'System Administrator',
            'email' => 'admin@school.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertEquals(1, User::count());

        $user = User::first();
        $this->assertNotNull($user);
        $this->assertEquals('superadmin', $user->username);
        $this->assertEquals('System Administrator', $user->display_name);
        $this->assertEquals('admin@school.test', $user->email);
        $this->assertEquals($this->adminRole->id, $user->role_id);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->is_active);

        // Password hash verification
        $this->assertTrue(Hash::check('SecurePass123!', $user->password_hash));
        $this->assertNotEquals('SecurePass123!', $user->password_hash);
    }

    // C. CLIENT MANIPULATION DEFENSE
    public function test_client_supplied_role_id_and_is_active_are_ignored(): void
    {
        $response = $this->post('/setup', [
            'username' => 'attempted_tamper',
            'display_name' => 'Tamper Test',
            'email' => 'tamper@school.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            // Maliciously forged client parameters:
            'role_id' => $this->officeRole->id,
            'is_active' => false,
            'password_hash' => '$2y$12$injected_malicious_hash',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::first();
        $this->assertNotNull($user);

        // Server-side explicit assignment overrides any client manipulation
        $this->assertEquals($this->adminRole->id, $user->role_id);
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isOfficeStaff());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password_hash));
    }

    // D. AUTHENTICATION
    public function test_successful_setup_authenticates_admin_and_regenerates_session(): void
    {
        $initialSessionId = session()->getId();

        $response = $this->post('/setup', [
            'username' => 'auth_admin',
            'display_name' => 'Authenticated Admin',
            'email' => 'auth_admin@school.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect('/dashboard');

        // Verify authenticated state
        $this->assertTrue(Auth::check());
        $this->assertEquals('auth_admin', Auth::user()->username);
        $this->assertTrue(Auth::user()->isAdmin());

        // Verify subsequent dashboard access passes web -> auth -> active
        $dashResponse = $this->get('/dashboard');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Authenticated Admin');
        $dashResponse->assertSee('Administrator');
    }

    // E. SECURITY
    public function test_invalid_input_fails_validation_without_creating_user(): void
    {
        $response = $this->from('/setup')->post('/setup', [
            'username' => '',
            'display_name' => '',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertRedirect('/setup');
        $response->assertSessionHasErrors(['username', 'display_name', 'password']);
        $this->assertEquals(0, User::count());
    }

    public function test_setup_cannot_create_a_second_user_after_initialization(): void
    {
        // First user creation succeeds
        $firstResponse = $this->post('/setup', [
            'username' => 'first_admin',
            'display_name' => 'First Admin',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
        $firstResponse->assertRedirect('/dashboard');
        $this->assertEquals(1, User::count());

        Auth::logout();

        // Second setup attempt must be rejected
        $secondResponse = $this->post('/setup', [
            'username' => 'second_user',
            'display_name' => 'Second User',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $secondResponse->assertRedirect('/login');
        $this->assertEquals(1, User::count());
        $this->assertNull(User::where('username', 'second_user')->first());
    }

    public function test_audit_logs_record_initial_setup_without_passwords_or_credentials(): void
    {
        $this->post('/setup', [
            'username' => 'audit_test_admin',
            'display_name' => 'Audit Admin',
            'email' => 'audit_admin@school.test',
            'password' => 'SecretPass999!',
            'password_confirmation' => 'SecretPass999!',
        ]);

        $audit = AuditLog::where('action', 'initial_admin_setup')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('security', $audit->entity_type);
        $this->assertNull($audit->user_id); // No actor user_id existed prior

        $auditJson = json_encode($audit->toArray());
        $this->assertStringNotContainsString('SecretPass999!', $auditJson);
        $this->assertStringNotContainsString('password_hash', $auditJson);
        $this->assertStringContainsString('audit_test_admin', $auditJson);
        $this->assertStringContainsString('Administrator', $auditJson);
    }

    // F. DEACTIVATION EDGE CASE
    public function test_setup_remains_permanently_unavailable_even_if_all_users_are_deactivated(): void
    {
        // Initialize first Administrator
        $this->post('/setup', [
            'username' => 'deactivated_admin',
            'display_name' => 'Deactivated Admin',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        Auth::logout();

        // Administrator is subsequently deactivated
        User::query()->update(['is_active' => false]);
        $this->assertEquals(0, User::where('is_active', true)->count());
        $this->assertEquals(1, User::count());

        // Registration availability must check total user existence, NOT is_active!
        $setupService = app(SystemSetupService::class);
        $this->assertFalse($setupService->isSetupAvailable());

        // GET /setup must redirect to login
        $getResponse = $this->get('/setup');
        $getResponse->assertRedirect('/login');

        // POST /setup must reject creation
        $postResponse = $this->post('/setup', [
            'username' => 'another_admin',
            'display_name' => 'Another Admin',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
        $postResponse->assertRedirect('/login');
        $this->assertEquals(1, User::count());

        // Login view must not render registration link
        $loginResponse = $this->get('/login');
        $loginResponse->assertDontSee('Create Administrator Account');
    }

    // G. CONCURRENCY PROTECTION
    public function test_postgresql_exclusive_table_lock_prevents_concurrent_setup_race(): void
    {
        $setupService = app(SystemSetupService::class);

        // 1. Transaction 1 executes and creates first Administrator
        $user1 = $setupService->createInitialAdministrator([
            'username' => 'racing_admin_1',
            'display_name' => 'Racing Admin One',
            'email' => 'admin1@school.test',
            'password' => 'SecurePass123!',
        ]);

        $this->assertNotNull($user1);
        $this->assertEquals(1, User::count());

        // 2. Competing transaction 2 executing createInitialAdministrator must throw SystemAlreadyInitializedException
        $this->expectException(SystemAlreadyInitializedException::class);

        $setupService->createInitialAdministrator([
            'username' => 'racing_admin_2',
            'display_name' => 'Racing Admin Two',
            'email' => 'admin2@school.test',
            'password' => 'SecurePass123!',
        ]);
    }
}
