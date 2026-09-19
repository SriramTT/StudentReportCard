<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        $academicYearId = $this->input('academic_year_id');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('terms', 'name')->where('academic_year_id', $academicYearId),
            ],
            'sequence_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('terms', 'sequence_no')->where('academic_year_id', $academicYearId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
