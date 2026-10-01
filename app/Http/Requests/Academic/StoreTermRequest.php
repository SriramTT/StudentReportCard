<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Term::class) ?? false;
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
                'nullable',
                'integer',
                'min:1',
                Rule::unique('terms', 'sequence_no')->where('academic_year_id', $academicYearId),
            ],
        ];
    }
}
