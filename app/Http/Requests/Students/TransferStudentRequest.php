<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class TransferStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student && ($this->user()?->can('transfer', $student) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => [
                'required',
                'integer',
                'exists:academic_years,id',
            ],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id',
            ],
            'section_id' => [
                'required',
                'integer',
                'exists:sections,id',
                function ($attribute, $value, $fail) {
                    $classId = $this->input('class_id');
                    if ($classId && $value) {
                        $section = \App\Models\Section::where('id', $value)->where('class_id', $classId)->first();
                        if (! $section) {
                            $fail('The selected section does not belong to the selected class.');
                        }
                    }
                },
            ],
            'roll_number' => [
                'required',
                'integer',
                'min:1',
            ],
            'effective_date' => [
                'required',
                'date',
            ],
        ];
    }
}
