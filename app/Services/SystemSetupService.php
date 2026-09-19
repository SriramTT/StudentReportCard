<?php

namespace App\Services;

use App\Exceptions\SystemAlreadyInitializedException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SystemSetupService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Determine whether first-run Administrator setup is available.
     * Available strictly when the users table contains exactly zero records.
     * Historical user existence permanently disables setup, even if all users are deactivated.
     */
    public function isSetupAvailable(): bool
    {
        return User::query()->count() === 0;
    }

    /**
     * Transactionally create the initial Administrator account with PostgreSQL concurrency lock.
     *
     * @param  array{username: string, display_name: string, email?: string|null, password: string}  $data
     * @param  string|null  $ipAddress
     * @return User
     *
     * @throws SystemAlreadyInitializedException
     */
    public function createInitialAdministrator(array $data, ?string $ipAddress = null): User
    {
        return DB::transaction(function () use ($data, $ipAddress) {
            // Acquire PostgreSQL table-level lock on users table to serialize concurrent initialization requests
            DB::statement('LOCK TABLE users IN EXCLUSIVE MODE;');

            if (! $this->isSetupAvailable()) {
                throw new SystemAlreadyInitializedException('Initial Administrator setup has already been completed.');
            }

            // Resolve Administrator role dynamically from the database (never hardcode numeric ID)
            $adminRole = Role::query()->where('name', 'Administrator')->firstOrFail();

            // Hash the password securely using Laravel's configured hasher
            $passwordHash = Hash::make($data['password']);

            // Create the first user directly with Administrator role and active state
            $user = User::forceCreate([
                'role_id' => $adminRole->id,
                'username' => $data['username'],
                'password_hash' => $passwordHash,
                'display_name' => $data['display_name'],
                'email' => $data['email'] ?? null,
                'is_active' => true,
            ]);

            // Log security event via AuditService (actor user_id is null as no user existed prior)
            $this->auditService->logSecurityEvent(
                action: 'initial_admin_setup',
                userId: null,
                description: 'First-run initial Administrator account created',
                afterData: [
                    'created_user_id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'role' => 'Administrator',
                ],
                ipAddress: $ipAddress
            );

            return $user;
        });
    }
}
