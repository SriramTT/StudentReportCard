<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Handle an incoming request.
     *
     * MUST execute AFTER 'auth' middleware has resolved the authenticated user.
     * Checks live PostgreSQL state to immediately revoke sessions if an account is deactivated.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            // Check live PostgreSQL database status (never rely on stale session data)
            $isActive = (bool) User::query()->where('id', $user->id)->value('is_active');

            if (! $isActive) {
                $this->auditService->logSecurityEvent(
                    action: 'session_revoked_inactive',
                    userId: $user->id,
                    description: 'Active session revoked: user account has been deactivated',
                    afterData: ['username' => $user->username],
                    ipAddress: $request->ip()
                );

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->withErrors(['username' => 'Your account has been deactivated. Please contact an Administrator.']);
            }
        }

        return $next($request);
    }
}
