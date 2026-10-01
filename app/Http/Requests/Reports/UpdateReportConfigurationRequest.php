<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $config = $this->route('reportConfiguration');
        return $this->user()?->can('update', $config) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'report_type' => ['required', 'string', 'in:term,exam_midterm,final,exam_custom,exam'],
            'configuration_data' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
