<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportAssessmentSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        $reportConfiguration = $this->route('reportConfiguration');
        $reportConfigurationId = $reportConfiguration?->id;

        return [
            'assessment_id' => [
                'required',
                'integer',
                'exists:assessments,id',
                Rule::unique('report_assessment_selections', 'assessment_id')
                    ->where('report_configuration_id', $reportConfigurationId),
            ],
            'display_order' => ['nullable', 'integer', 'min:1'],
            'is_displayed' => ['nullable', 'boolean'],
        ];
    }
}
