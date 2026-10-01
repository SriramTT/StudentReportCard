<?php

namespace App\Services\Auth;

use App\Mail\LoginOtpMail;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LoginOtpService
{
    private const SESSION_KEY = 'auth.otp_challenge';

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Determine whether email OTP verification is globally enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('auth.otp.enabled', false);
    }

    /**
     * Generate a cryptographically secure 6-digit numeric OTP.
     */
    public function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Mask an email address safely for user display.
     * e.g., "sriram@school.test" -> "s***m@school.test"
     */
    public function maskEmail(?string $email): string
    {
        if (empty($email) || ! str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);
        $len = strlen($local);

        if ($len <= 1) {
            $maskedLocal = $local . '*';
        } elseif ($len === 2) {
            $maskedLocal = $local[0] . '*' . $local[1];
        } else {
            $maskedLocal = $local[0] . '***' . $local[$len - 1];
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Create and store a pending OTP challenge, then send the code via email.
     *
     * @throws \RuntimeException if email is missing or delivery fails.
     */
    public function createAndSendChallenge(User $user, Request $request): bool
    {
        if (empty($user->email)) {
            $this->auditService->logSecurityEvent(
                action: 'login_otp_blocked_missing_email',
                userId: $user->id,
                description: 'Login OTP challenge blocked: account has no registered email',
                afterData: ['username' => $user->username],
                ipAddress: $request->ip()
            );

            throw new \RuntimeException('No registered email address is configured for this account.');
        }

        $otp = $this->generateOtp();
        $expiresInMinutes = (int) config('auth.otp.expires_in_minutes', 5);

        // Store hashed OTP challenge in session
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'username' => $user->username,
            'hashed_otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
            'attempts' => 0,
            'last_sent_at' => now()->timestamp,
        ]);

        try {
            $schoolSetting = SchoolSetting::first();
            $schoolName = $schoolSetting?->school_name;

            Mail::to($user->email)->send(new LoginOtpMail($otp, $expiresInMinutes, $schoolName));

            $this->auditService->logSecurityEvent(
                action: 'login_otp_sent',
                userId: $user->id,
                description: 'Login OTP verification email sent',
                afterData: ['username' => $user->username, 'masked_email' => $this->maskEmail($user->email)],
                ipAddress: $request->ip()
            );

            return true;
        } catch (\Throwable $e) {
            // Fail closed: clear the challenge so it cannot be verified without delivery
            $this->clearChallenge($request);

            $this->auditService->logSecurityEvent(
                action: 'login_otp_mail_failed',
                userId: $user->id,
                description: 'Failed to send login OTP email: ' . $e->getMessage(),
                afterData: ['username' => $user->username],
                ipAddress: $request->ip()
            );

            throw $e;
        }
    }

    /**
     * Get the active OTP challenge from session, if not expired.
     *
     * @return array{user_id: int, hashed_otp: string, expires_at: int, attempts: int, last_sent_at: int}|null
     */
    public function getChallenge(Request $request): ?array
    {
        $challenge = $request->session()->get(self::SESSION_KEY);

        if (! is_array($challenge)) {
            return null;
        }

        if (now()->timestamp > ($challenge['expires_at'] ?? 0)) {
            $this->clearChallenge($request);
            return null;
        }

        return $challenge;
    }

    /**
     * Verify the submitted OTP against the active session challenge.
     *
     * @return array{success: bool, reason?: string, user?: User, attempts_remaining?: int}
     */
    public function verifyChallenge(Request $request, string $submittedOtp): array
    {
        $challenge = $this->getChallenge($request);

        if ($challenge === null) {
            return [
                'success' => false,
                'reason' => 'expired',
            ];
        }

        $user = User::find($challenge['user_id']);

        if (! $user instanceof User || ! $user->is_active) {
            $this->clearChallenge($request);
            return [
                'success' => false,
                'reason' => 'invalid_user',
            ];
        }

        $maxAttempts = (int) config('auth.otp.max_attempts', 3);

        if (($challenge['attempts'] ?? 0) >= $maxAttempts) {
            $this->clearChallenge($request);
            return [
                'success' => false,
                'reason' => 'max_attempts_exceeded',
                'attempts_remaining' => 0,
            ];
        }

        // Constant-time hash check
        if (! Hash::check($submittedOtp, $challenge['hashed_otp'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;

            if ($challenge['attempts'] >= $maxAttempts) {
                $this->clearChallenge($request);

                $this->auditService->logSecurityEvent(
                    action: 'login_otp_max_attempts_exceeded',
                    userId: $user->id,
                    description: 'Login OTP challenge invalidated: maximum attempts exceeded',
                    afterData: ['username' => $user->username],
                    ipAddress: $request->ip()
                );

                return [
                    'success' => false,
                    'reason' => 'max_attempts_exceeded',
                    'attempts_remaining' => 0,
                ];
            }

            // Save incremented attempts count
            $request->session()->put(self::SESSION_KEY, $challenge);

            $this->auditService->logSecurityEvent(
                action: 'login_otp_invalid_attempt',
                userId: $user->id,
                description: 'Incorrect OTP submitted for login verification',
                afterData: [
                    'username' => $user->username,
                    'attempts' => $challenge['attempts'],
                    'remaining' => $maxAttempts - $challenge['attempts'],
                ],
                ipAddress: $request->ip()
            );

            return [
                'success' => false,
                'reason' => 'invalid_code',
                'attempts_remaining' => $maxAttempts - $challenge['attempts'],
            ];
        }

        // Successful verification: invalidate challenge to prevent replay
        $this->clearChallenge($request);

        $this->auditService->logSecurityEvent(
            action: 'login_otp_verified',
            userId: $user->id,
            description: 'Login OTP verified successfully',
            afterData: ['username' => $user->username],
            ipAddress: $request->ip()
        );

        return [
            'success' => true,
            'user' => $user,
        ];
    }

    /**
     * Resend a fresh OTP to the challenge's registered email address.
     * Enforces the 60-second cooldown server-side.
     *
     * @return array{success: bool, reason?: string, cooldown_remaining?: int}
     */
    public function resend(Request $request): array
    {
        $challenge = $this->getChallenge($request);

        if ($challenge === null) {
            return [
                'success' => false,
                'reason' => 'no_challenge',
            ];
        }

        $user = User::find($challenge['user_id']);

        if (! $user instanceof User || ! $user->is_active || empty($user->email)) {
            $this->clearChallenge($request);
            return [
                'success' => false,
                'reason' => 'invalid_user',
            ];
        }

        $cooldownSec = (int) config('auth.otp.resend_cooldown_seconds', 60);
        $elapsed = now()->timestamp - ($challenge['last_sent_at'] ?? 0);

        if ($elapsed < $cooldownSec) {
            return [
                'success' => false,
                'reason' => 'cooldown',
                'cooldown_remaining' => $cooldownSec - $elapsed,
            ];
        }

        $newOtp = $this->generateOtp();
        $expiresInMinutes = (int) config('auth.otp.expires_in_minutes', 5);

        // Previous OTP is invalidated: replace with new hash and reset attempts
        $challenge['hashed_otp'] = Hash::make($newOtp);
        $challenge['expires_at'] = now()->addMinutes($expiresInMinutes)->timestamp;
        $challenge['attempts'] = 0;
        $challenge['last_sent_at'] = now()->timestamp;

        $request->session()->put(self::SESSION_KEY, $challenge);

        try {
            $schoolSetting = SchoolSetting::first();
            $schoolName = $schoolSetting?->school_name;

            Mail::to($user->email)->send(new LoginOtpMail($newOtp, $expiresInMinutes, $schoolName));

            $this->auditService->logSecurityEvent(
                action: 'login_otp_resent',
                userId: $user->id,
                description: 'Login OTP resent to registered email',
                afterData: ['username' => $user->username],
                ipAddress: $request->ip()
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->auditService->logSecurityEvent(
                action: 'login_otp_resend_failed',
                userId: $user->id,
                description: 'Failed to resend login OTP email: ' . $e->getMessage(),
                afterData: ['username' => $user->username],
                ipAddress: $request->ip()
            );

            return [
                'success' => false,
                'reason' => 'mail_failed',
            ];
        }
    }

    /**
     * Clear and invalidate any pending OTP challenge from the session.
     */
    public function clearChallenge(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
