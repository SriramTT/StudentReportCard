<?php

namespace App\Http\Requests\Auth;

use App\Rules\CaseInsensitiveUniqueEmail;
use Illuminate\Foundation\Http\FormRequest;

class SetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
                'lowercase',
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
        ];
    }
}
