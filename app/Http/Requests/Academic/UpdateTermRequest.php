<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $term = $this->route('term');
        return $this->user()?->can('update', $term) ?? false;
    }

    public function rules(): array
    {
        $term = $this->route('term');
        $academicYearId = $term?->academic_year_id;
        $termId = $term?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('terms', 'name')
                    ->where('academic_year_id', $academicYearId)
                    ->ignore($termId),
            ],
            'sequence_no' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('terms', 'sequence_no')
                    ->where('academic_year_id', $academicYearId)
                    ->ignore($termId),
            ],
        ];
    }
}
