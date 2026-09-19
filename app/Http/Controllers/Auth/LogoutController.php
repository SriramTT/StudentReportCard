<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Log the user out of the application (POST-only with CSRF protection).
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $userId = $user?->id;
        $username = $user?->username;

        if ($userId !== null) {
            $this->auditService->logSecurityEvent(
                action: 'logout',
                userId: $userId,
                description: 'User logged out',
                afterData: ['username' => $username],
                ipAddress: $request->ip()
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'You have been logged out successfully.');
    }
}
