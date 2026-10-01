<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class BatchSaveAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is verified contextually in the controller and service.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'attendance_records' => ['required', 'array', 'min:1'],
            'attendance_records.*.student_academic_record_id' => ['required', 'integer', 'exists:student_academic_records,id'],
            'attendance_records.*.days_attended' => ['required', 'integer', 'min:0'],
            'attendance_records.*.total_working_days' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance_records.required' => 'At least one student attendance record must be submitted.',
            'attendance_records.*.days_attended.min' => 'Days attended cannot be negative.',
            'attendance_records.*.total_working_days.min' => 'Total working days cannot be negative.',
        ];
    }
}
