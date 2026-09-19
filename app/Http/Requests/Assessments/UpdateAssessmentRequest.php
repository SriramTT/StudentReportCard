<?php

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        return [
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'assessment_type_id' => ['required', 'integer', 'exists:assessment_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'assessment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
