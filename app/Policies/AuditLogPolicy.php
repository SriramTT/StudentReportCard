<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view audit logs (Administrator only).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view a specific audit log record (Administrator only).
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->isAdmin();
    }

    /**
     * Audit logs are created strictly by the application AuditService, not direct user requests.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Audit logs are strictly immutable (Section 28). Updates are forbidden.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Audit logs are strictly immutable (Section 28). Deletion is forbidden.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
