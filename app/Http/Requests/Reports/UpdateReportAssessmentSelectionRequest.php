<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportAssessmentSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $config = $this->route('reportConfiguration');
        return $this->user()?->can('update', $config) ?? false;
    }

    public function rules(): array
    {
        return [
            'display_order' => ['sometimes', 'integer', 'min:1'],
            'is_displayed' => ['sometimes', 'boolean'],
        ];
    }
}
