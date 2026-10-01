<?php

namespace App\Http\Requests\Calculations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalculationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\CalculationSetting::class) ?? false;
    }

    public function rules(): array
    {
        $academicYearId = $this->input('academic_year_id');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id',
                Rule::unique('calculation_settings', 'class_id')->where('academic_year_id', $academicYearId),
            ],
            'calculation_method' => ['required', 'in:average_percentage,combined_marks'],
        ];
    }
}
