<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Fixed bcrypt hash used for constant-time comparison when a username does not exist.
     * Prevents timing attacks and username enumeration (TH-08, TH-09).
     */
    private const DUMMY_HASH = '$2y$12$e8uPZgWk2YQ7K8l4mJ8U4uN9.9Xg.bA3vQGk4A4qf5rKz9e2xL3.u';

    public function __construct(
        protected AuditService $auditService,
        protected \App\Services\SystemSetupService $setupService
    ) {}

    /**
     * Display the login view.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->is_active) {
            return redirect()->route('dashboard');
        }

        $setupAvailable = $this->setupService->isSetupAvailable();

        return view('auth.login', [
            'setupAvailable' => $setupAvailable,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $username = $credentials['username'];
        $password = $credentials['password'];

        $user = User::query()->where('username', $username)->first();

        // Constant-time defense: always perform hash check even if user does not exist
        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            $this->auditService->logSecurityEvent(
                action: 'login_failed',
                userId: null,
                description: 'Failed login attempt: username not found',
                afterData: ['username' => $username],
                ipAddress: $request->ip()
            );

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'These credentials do not match our records.']);
        }

        // Verify password against stored password_hash
        if (! Hash::check($password, $user->getAuthPassword())) {
            $this->auditService->logSecurityEvent(
                action: 'login_failed',
                userId: $user->id,
                description: 'Failed login attempt: invalid password',
                afterData: ['username' => $username],
                ipAddress: $request->ip()
            );

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'These credentials do not match our records.']);
        }

        // Active account check
        if (! $user->is_active) {
            $this->auditService->logSecurityEvent(
                action: 'login_blocked_inactive',
                userId: $user->id,
                description: 'Login blocked: user account is deactivated',
                afterData: ['username' => $username],
                ipAddress: $request->ip()
            );

            return redirect()
                ->route('login')
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Your account has been deactivated. Please contact an Administrator.']);
        }

        // Authenticate user
        Auth::login($user);

        // Session fixation protection (TH-07)
        $request->session()->regenerate();

        // Record last login timestamp
        $user->forceFill(['last_login_at' => now()])->save();

        // Log successful login security audit event
        $this->auditService->logSecurityEvent(
            action: 'login_successful',
            userId: $user->id,
            description: 'User logged in successfully',
            afterData: ['username' => $username, 'role' => $user->role?->name],
            ipAddress: $request->ip()
        );

        // Clear rate limiter
        $request->clearRateLimit();

        return redirect()->intended(route('dashboard'));
    }
}
