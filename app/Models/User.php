<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'display_name',
        'email',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Stateful session authentication only: remember tokens are not in the approved schema
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Determine if the user has a specific role or one of multiple roles.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role');
        }

        $roleName = $this->role?->name;
        if ($roleName === null) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($roleName, $roles, true);
        }

        return $roleName === $roles;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Administrator');
    }

    public function isOfficeStaff(): bool
    {
        return $this->hasRole('Office Staff');
    }

    public function isSubjectTeacher(): bool
    {
        return $this->hasRole('Subject Teacher');
    }

    public function isClassTeacher(): bool
    {
        return $this->hasRole('Class Teacher');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'user_id');
    }

    public function enteredMarks(): HasMany
    {
        return $this->hasMany(Mark::class, 'entered_by_user_id');
    }

    public function updatedMarks(): HasMany
    {
        return $this->hasMany(Mark::class, 'updated_by_user_id');
    }

    public function enteredAttendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'entered_by_user_id');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'generated_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
