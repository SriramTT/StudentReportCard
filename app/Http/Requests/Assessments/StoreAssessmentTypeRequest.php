<?php

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:assessment_types,name'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
