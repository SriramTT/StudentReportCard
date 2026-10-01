<?php

namespace App\Http\Requests\Marks;

use Illuminate\Foundation\Http\FormRequest;

class BatchSaveMarksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is enforced by Gate/Policy and MarkService domain logic
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'marks' => ['required', 'array'],
            'marks.*.student_academic_record_id' => ['required', 'integer'],
            'marks.*.student_subject_allocation_id' => ['required', 'integer'],
            'marks.*.result_status' => ['required', 'string', 'in:blank,numeric,absent'],
            'marks.*.mark_value' => ['nullable'],
        ];
    }

    /**
     * Custom error messages for batch save validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marks.required' => 'No marks payload was provided for submission.',
            'marks.*.result_status.in' => 'The mark status must be blank, numeric, or absent.',
        ];
    }
}
