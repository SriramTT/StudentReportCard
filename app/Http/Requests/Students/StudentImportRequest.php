<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Student::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:2048',
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
        ];
    }
}
