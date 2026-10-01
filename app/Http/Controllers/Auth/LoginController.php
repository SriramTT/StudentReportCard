<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Auth\LoginOtpService;
use App\Services\SystemSetupService;
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
        protected SystemSetupService $setupService,
        protected LoginOtpService $otpService
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
                ->withErrors([
                    'credentials' => 'Invalid username or password. Please verify your credentials and try again.',
                    'username' => 'These credentials do not match our records.',
                    'password' => 'These credentials do not match our records.',
                ]);
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
                ->withErrors([
                    'credentials' => 'Invalid username or password. Please verify your credentials and try again.',
                    'username' => 'These credentials do not match our records.',
                    'password' => 'These credentials do not match our records.',
                ]);
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

        // Two-step verification: mandatory email OTP when enabled
        if ($this->otpService->isEnabled()) {
            if (empty($user->email)) {
                $this->auditService->logSecurityEvent(
                    action: 'login_blocked_missing_email',
                    userId: $user->id,
                    description: 'Login blocked: user has no registered email for OTP verification',
                    afterData: ['username' => $username],
                    ipAddress: $request->ip()
                );

                return redirect()
                    ->route('login')
                    ->withInput($request->only('username'))
                    ->withErrors(['username' => 'No registered email address is configured for this account. Please contact an Administrator.']);
            }

            try {
                $this->otpService->createAndSendChallenge($user, $request);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('login')
                    ->withInput($request->only('username'))
                    ->withErrors(['username' => 'Unable to send verification code. Please contact an Administrator or try again later.']);
            }

            return redirect()->route('login.otp');
        }

        // Authenticate user directly when OTP is not enabled
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

    /**
     * Display the OTP verification screen.
     */
    public function showOtpForm(Request $request): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->is_active) {
            return redirect()->route('dashboard');
        }

        $challenge = $this->otpService->getChallenge($request);

        if ($challenge === null) {
            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Your verification session has expired. Please sign in again.']);
        }

        $user = User::find($challenge['user_id']);

        if (! $user instanceof User || ! $user->is_active) {
            $this->otpService->clearChallenge($request);
            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Your account is invalid or deactivated. Please sign in again.']);
        }

        $cooldownSec = (int) config('auth.otp.resend_cooldown_seconds', 60);
        $elapsed = now()->timestamp - ($challenge['last_sent_at'] ?? 0);
        $cooldownRemaining = max(0, $cooldownSec - $elapsed);

        return view('auth.otp', [
            'maskedEmail' => $this->otpService->maskEmail($user->email),
            'cooldownRemaining' => $cooldownRemaining,
            'attemptsRemaining' => (int) config('auth.otp.max_attempts', 3) - ($challenge['attempts'] ?? 0),
        ]);
    }

    /**
     * Verify the submitted OTP and complete authentication.
     */
    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $submittedOtp = $request->validated('otp');

        $result = $this->otpService->verifyChallenge($request, $submittedOtp);

        if (! $result['success']) {
            if (($result['reason'] ?? '') === 'max_attempts_exceeded') {
                return redirect()
                    ->route('login')
                    ->withErrors(['username' => 'Maximum verification attempts exceeded. Please sign in again.']);
            }

            if (($result['reason'] ?? '') === 'expired') {
                return redirect()
                    ->route('login')
                    ->withErrors(['username' => 'Your verification session has expired. Please sign in again.']);
            }

            if (($result['reason'] ?? '') === 'invalid_code') {
                $remaining = $result['attempts_remaining'] ?? 0;
                return back()
                    ->withErrors(['otp' => "Invalid verification code. You have {$remaining} attempt(s) remaining."]);
            }

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Verification failed. Please sign in again.']);
        }

        /** @var User $user */
        $user = $result['user'];

        // Authenticate user with native Laravel session
        Auth::login($user);

        // Session fixation protection (TH-07)
        $request->session()->regenerate();

        // Record last login timestamp
        $user->forceFill(['last_login_at' => now()])->save();

        // Log successful login security audit event
        $this->auditService->logSecurityEvent(
            action: 'login_successful',
            userId: $user->id,
            description: 'User logged in successfully via two-step verification',
            afterData: ['username' => $user->username, 'role' => $user->role?->name],
            ipAddress: $request->ip()
        );

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Resend a fresh OTP to the registered email address.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $result = $this->otpService->resend($request);

        if (! $result['success']) {
            if (($result['reason'] ?? '') === 'cooldown') {
                $sec = $result['cooldown_remaining'] ?? 60;
                return back()->withErrors(['otp' => "Please wait {$sec} seconds before requesting a new code."]);
            }

            if (($result['reason'] ?? '') === 'mail_failed') {
                return back()->withErrors(['otp' => 'Unable to resend verification code. Please try again later.']);
            }

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Your verification session has expired. Please sign in again.']);
        }

        return back()->with('status', 'A new verification code has been sent to your registered email.');
    }
}
