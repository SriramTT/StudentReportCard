<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use App\Models\User;
use App\Rules\CaseInsensitiveUniqueEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedRoleIds = Role::query()
            ->whereIn('name', ['Office Staff', 'Subject Teacher', 'Class Teacher'])
            ->pluck('id')
            ->toArray();

        return [
            'username' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9._-]+$/',
                'lowercase',
                'unique:users,username',
            ],
            'display_name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'lowercase',
                new CaseInsensitiveUniqueEmail(),
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::in($allowedRoleIds),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
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
            'role_id.in' => 'Selected role must be Office Staff, Subject Teacher, or Class Teacher.',
        ];
    }
}
