<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'admission_number' => is_string($this->admission_number) ? trim($this->admission_number) : $this->admission_number,
            'student_name' => is_string($this->student_name) ? trim($this->student_name) : $this->student_name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admission_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'admission_number'),
            ],
            'student_name' => [
                'required',
                'string',
                'max:200',
            ],
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
                Rule::unique('student_academic_records', 'roll_number')->where(function ($query) {
                    return $query->where('academic_year_id', $this->input('academic_year_id'))
                        ->where('class_id', $this->input('class_id'))
                        ->where('section_id', $this->input('section_id'))
                        ->where('status', 'active');
                }),
            ],
            'effective_from' => [
                'nullable',
                'date',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roll_number.unique' => 'This roll number is already assigned to an active student in the selected class and section.',
        ];
    }
}
