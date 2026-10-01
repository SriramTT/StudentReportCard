<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schoolClass = $this->route('schoolClass');
        return $this->user()?->can('update', $schoolClass) ?? false;
    }

    public function rules(): array
    {
        $classId = $this->route('schoolClass')?->id ?? $this->route('class');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('classes', 'name')->ignore($classId)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
