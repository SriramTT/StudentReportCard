<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Sanitizes sensitive fields from context data before persisting to audit log.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    protected function sanitizeData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redactedKeys = [
            'password',
            'password_hash',
            'password_confirmation',
            'secret',
            'token',
            'remember_token',
            'credentials',
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $redactedKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Record a security event (login, logout, session revocation, failed login, etc.)
     *
     * @param  string  $action
     * @param  int|null  $userId
     * @param  string  $description
     * @param  array<string, mixed>|null  $afterData
     * @param  string|null  $ipAddress
     * @return AuditLog
     */
    public function logSecurityEvent(
        string $action,
        ?int $userId,
        string $description,
        ?array $afterData = null,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => 'security',
            'entity_id' => $userId,
            'before_data' => null,
            'after_data' => $this->sanitizeData($afterData),
            'description' => $description,
            'ip_address' => $ipAddress ?? Request::ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a domain action (mark update, attendance edit, report generation, etc.)
     *
     * @param  int|null  $userId
     * @param  string  $action
     * @param  string  $entityType
     * @param  int|null  $entityId
     * @param  array<string, mixed>|null  $beforeData
     * @param  array<string, mixed>|null  $afterData
     * @param  string|null  $description
     * @param  string|null  $ipAddress
     * @return AuditLog
     */
    public function logDomainAction(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?string $description = null,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $this->sanitizeData($beforeData),
            'after_data' => $this->sanitizeData($afterData),
            'description' => $description,
            'ip_address' => $ipAddress ?? Request::ip(),
            'created_at' => now(),
        ]);
    }
}
