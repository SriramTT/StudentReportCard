<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:subjects,name'],
            'code' => ['required', 'string', 'max:20', 'unique:subjects,code'],
            'category' => ['required', 'in:main,elective'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
