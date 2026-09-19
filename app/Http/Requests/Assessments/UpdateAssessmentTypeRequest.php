<?php

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        $typeId = $this->route('assessmentType')?->id ?? $this->route('assessment_type');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('assessment_types', 'name')->ignore($typeId)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
