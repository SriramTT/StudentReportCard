<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentSubjectAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_subject_ids' => ['nullable', 'array'],
            'class_subject_ids.*' => ['integer', 'exists:class_subjects,id'],
        ];
    }
}
