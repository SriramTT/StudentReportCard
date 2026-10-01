<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student && ($this->user()?->can('update', $student) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'student_name' => is_string($this->student_name) ? trim($this->student_name) : $this->student_name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_name' => [
                'required',
                'string',
                'max:200',
            ],
        ];
    }
}
