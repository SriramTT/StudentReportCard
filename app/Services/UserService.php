<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new user account.
     *
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return User
     */
    public function createUser(array $data, int $actorId): User
    {
        return DB::transaction(function () use ($data, $actorId) {
            $role = Role::findOrFail($data['role_id']);

            if ($role->name === 'Administrator') {
                throw ValidationException::withMessages([
                    'role_id' => 'Administrator accounts cannot be created via standard user management.',
                ]);
            }

            $user = new User();
            $user->username = trim($data['username']);
            $user->display_name = trim($data['display_name']);
            $user->email = ! empty($data['email']) ? trim($data['email']) : null;
            $user->role_id = $role->id;
            $user->password_hash = Hash::make($data['password']);
            $user->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;

            if (in_array($role->name, ['Subject Teacher', 'Class Teacher'], true) && ! empty($data['eligible_subject_ids'])) {
                $user->eligible_subject_ids = array_values(array_map('intval', array_unique($data['eligible_subject_ids'])));
            } else {
                $user->eligible_subject_ids = null;
            }

            $user->save();

            $this->auditService->logDomainAction(
                $actorId,
                'USER_CREATED',
                'users',
                $user->id,
                null,
                [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                    'role' => $role->name,
                    'is_active' => $user->is_active,
                    'eligible_subject_ids' => $user->eligible_subject_ids,
                ],
                "Created user '{$user->username}' with role '{$role->name}'"
            );

            return $user;
        });
    }

    /**
     * Update an existing user account.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return User
     */
    public function updateUser(User $user, array $data, int $actorId): User
    {
        return DB::transaction(function () use ($user, $data, $actorId) {
            $user->load('role');

            $beforeData = [
                'username' => $user->username,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $user->role?->name,
                'is_active' => $user->is_active,
                'eligible_subject_ids' => $user->eligible_subject_ids,
            ];

            // If target is admin, role must remain Administrator
            if ($user->isAdmin() && (int) $data['role_id'] !== $user->role_id) {
                throw ValidationException::withMessages([
                    'role_id' => 'The Administrator role cannot be changed via user management.',
                ]);
            }

            $newRole = Role::findOrFail($data['role_id']);

            // Preserve unchanged legacy username/email without mutation
            if ($user->username !== $data['username']) {
                $user->username = trim($data['username']);
            }
            if ($user->email !== ($data['email'] ?? null)) {
                $user->email = ! empty($data['email']) ? trim($data['email']) : null;
            }
            $user->display_name = trim($data['display_name']);
            $user->role_id = $newRole->id;

            if (in_array($newRole->name, ['Subject Teacher', 'Class Teacher'], true)) {
                $user->eligible_subject_ids = ! empty($data['eligible_subject_ids'])
                    ? array_values(array_map('intval', array_unique($data['eligible_subject_ids'])))
                    : null;
            } else {
                $user->eligible_subject_ids = null;
            }

            $user->save();

            $afterData = [
                'username' => $user->username,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $newRole->name,
                'is_active' => $user->is_active,
                'eligible_subject_ids' => $user->eligible_subject_ids,
            ];

            $this->auditService->logDomainAction(
                $actorId,
                'USER_UPDATED',
                'users',
                $user->id,
                $beforeData,
                $afterData,
                "Updated user '{$user->username}'"
            );

            return $user;
        });
    }

    /**
     * Activate a user account.
     *
     * @param  User  $user
     * @param  int  $actorId
     * @return User
     */
    public function activateUser(User $user, int $actorId): User
    {
        return DB::transaction(function () use ($user, $actorId) {
            $beforeStatus = $user->is_active;
            $user->is_active = true;
            $user->save();

            $this->auditService->logDomainAction(
                $actorId,
                'USER_ACTIVATED',
                'users',
                $user->id,
                ['is_active' => $beforeStatus],
                ['is_active' => true],
                "Activated user '{$user->username}'"
            );

            return $user;
        });
    }

    /**
     * Deactivate a user account.
     *
     * @param  User  $user
     * @param  int  $actorId
     * @return User
     */
    public function deactivateUser(User $user, int $actorId): User
    {
        return DB::transaction(function () use ($user, $actorId) {
            if ($user->id === $actorId) {
                throw ValidationException::withMessages([
                    'user' => 'Administrators cannot deactivate their own account.',
                ]);
            }

            if ($user->isAdmin()) {
                $activeAdminCount = User::query()
                    ->where('role_id', $user->role_id)
                    ->where('is_active', true)
                    ->count();

                if ($activeAdminCount <= 1) {
                    throw ValidationException::withMessages([
                        'user' => 'Cannot deactivate the last remaining active Administrator.',
                    ]);
                }
            }

            $beforeStatus = $user->is_active;
            $user->is_active = false;
            $user->save();

            $this->auditService->logDomainAction(
                $actorId,
                'USER_DEACTIVATED',
                'users',
                $user->id,
                ['is_active' => $beforeStatus],
                ['is_active' => false],
                "Deactivated user '{$user->username}'"
            );

            return $user;
        });
    }

    /**
     * Reset or change a user's password.
     *
     * @param  User  $user
     * @param  string  $newPassword
     * @param  int  $actorId
     * @return void
     */
    public function changePassword(User $user, string $newPassword, int $actorId): void
    {
        DB::transaction(function () use ($user, $newPassword, $actorId) {
            $user->password_hash = Hash::make($newPassword);
            $user->save();

            $this->auditService->logDomainAction(
                $actorId,
                'USER_PASSWORD_CHANGED',
                'users',
                $user->id,
                null,
                ['credential_action' => 'PASSWORD_RESET'],
                "Password changed for user '{$user->username}'"
            );
        });
    }
}
