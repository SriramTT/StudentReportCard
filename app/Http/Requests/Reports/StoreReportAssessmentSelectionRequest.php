<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportAssessmentSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $config = $this->route('reportConfiguration');
        return $this->user()?->can('update', $config) ?? false;
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\ReportConfiguration|null $config */
            $config = $this->route('reportConfiguration');
            if ($config && $config->isMidTerm() && $config->assessmentSelections()->count() >= 1) {
                $validator->errors()->add('assessment_id', 'Mid term Assessment configurations allow exactly one assessment. Remove the existing assessment before adding a new one, or use a Custom Report.');
            }
        });
    }
}

