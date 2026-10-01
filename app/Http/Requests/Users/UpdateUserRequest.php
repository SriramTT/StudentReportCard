<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use App\Models\User;
use App\Rules\CaseInsensitiveUniqueEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetUser = $this->route('user');

        return $targetUser instanceof User && ($this->user()?->can('update', $targetUser) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $targetUser = $this->route('user');
        $userId = $targetUser instanceof User ? $targetUser->id : null;
        $isTargetAdmin = $targetUser instanceof User && $targetUser->isAdmin();

        if ($isTargetAdmin) {
            $adminRole = Role::query()->where('name', 'Administrator')->first();
            $allowedRoleIds = $adminRole ? [$adminRole->id] : [];
        } else {
            $allowedRoleIds = Role::query()
                ->whereIn('name', ['Office Staff', 'Subject Teacher', 'Class Teacher'])
                ->pluck('id')
                ->toArray();
        }

        $submittedUsername = $this->input('username');
        $isUsernameUnchanged = $targetUser instanceof User && $submittedUsername === $targetUser->username;

        if ($isUsernameUnchanged) {
            // Unchanged legacy username permitted as-is (even if containing uppercase)
            $usernameRules = [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ];
        } else {
            // Modified or new username must be lowercase
            $usernameRules = [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9._-]+$/',
                'lowercase',
                Rule::unique('users', 'username')->ignore($userId),
            ];
        }

        $submittedEmail = $this->input('email');
        $isEmailUnchanged = $targetUser instanceof User && $submittedEmail === $targetUser->email;

        if ($isEmailUnchanged) {
            // Unchanged legacy email permitted as-is (even if null or containing uppercase)
            $emailRules = [
                'nullable',
                'string',
                'max:255',
            ];
        } else {
            // Modified or new email must be required, valid, lowercase, and unique across all accounts
            $emailRules = [
                'required',
                'string',
                'email',
                'max:255',
                'lowercase',
                new CaseInsensitiveUniqueEmail($userId),
            ];
        }

        return [
            'username' => $usernameRules,
            'display_name' => ['required', 'string', 'max:150'],
            'email' => $emailRules,
            'role_id' => [
                'required',
                'integer',
                Rule::in($allowedRoleIds),
            ],
            'eligible_subject_ids' => ['nullable', 'array'],
            'eligible_subject_ids.*' => ['integer', 'exists:subjects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username must be lowercase and may only contain letters, numbers, dots, dashes, and underscores.',
            'username.lowercase' => 'Username must be lowercase.',
            'email.required' => 'Email address is required.',
            'email.lowercase' => 'Email address must be lowercase.',
            'role_id.in' => 'Selected role is invalid or role modification for this account type is not permitted.',
        ];
    }
}
