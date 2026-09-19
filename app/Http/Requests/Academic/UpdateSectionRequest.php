<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        $section = $this->route('section');
        $academicYearId = $section?->academic_year_id;
        $classId = $section?->class_id;
        $sectionId = $section?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->ignore($sectionId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
