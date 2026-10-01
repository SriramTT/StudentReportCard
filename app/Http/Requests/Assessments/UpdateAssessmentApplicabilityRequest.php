<?php

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentApplicabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $applicability = $this->route('applicability');
        return $this->user()?->can('update', $applicability) ?? false;
    }

    public function rules(): array
    {
        return [
            'maximum_marks' => ['sometimes', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
