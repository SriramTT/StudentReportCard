<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Detailed policy authorization happens in service/controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_academic_record_id' => ['required', 'integer', 'exists:student_academic_records,id'],
            'report_type' => ['required', 'string', Rule::in(['term', 'final', 'exam', 'mid_term', 'custom'])],
            'term_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('report_type') === 'term'),
                Rule::prohibitedIf(fn () => in_array($this->input('report_type'), ['final', 'exam', 'mid_term', 'custom'], true)),
                'integer',
                'exists:terms,id',
            ],
            'assessment_id' => [
                'nullable',
                'integer',
                'exists:assessments,id',
            ],
            'report_configuration_id' => [
                'required',
                'integer',
                'exists:report_configurations,id',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $configId = $this->input('report_configuration_id');
            $sarId = $this->input('student_academic_record_id');
            $reportType = $this->input('report_type');

            if ($configId && ! $validator->errors()->has('report_configuration_id')) {
                $config = \App\Models\ReportConfiguration::with('assessmentSelections')->find($configId);
                if ($config) {
                    if (! $config->is_active) {
                        $validator->errors()->add('report_configuration_id', 'The selected report configuration is inactive.');
                    }

                    $typeMatches = match ($reportType) {
                        'term' => $config->report_type === \App\Enums\ReportType::TERM,
                        'final' => $config->report_type === \App\Enums\ReportType::FINAL,
                        'mid_term', 'exam' => $config->isMidTerm(),
                        'custom' => $config->isCustom(),
                        default => false,
                    };

                    if (! $typeMatches) {
                        $validator->errors()->add('report_configuration_id', 'The selected report configuration does not match the report type.');
                    }

                    if (in_array($reportType, ['exam', 'mid_term', 'custom'], true) && $config->assessmentSelections->where('is_displayed', true)->isEmpty()) {
                        $validator->errors()->add('report_configuration_id', 'The selected report configuration has no displayed assessments configured.');
                    }
                    if ($sarId) {
                        $sar = \App\Models\StudentAcademicRecord::find($sarId);
                        if ($sar) {
                            if ($config->academic_year_id !== null && (int) $config->academic_year_id !== (int) $sar->academic_year_id) {
                                $validator->errors()->add('report_configuration_id', 'The selected report configuration is not applicable to the student academic year.');
                            }

                            $resolver = app(\App\Services\Report\ReportConfigurationResolver::class);
                            if (! $resolver->isConfigurationUsable($config, $sar->academic_year_id, $sar->class_id, $sar->section_id)) {
                                $validator->errors()->add('report_configuration_id', 'The selected report configuration is not usable for the student classroom section.');
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_academic_record_id.required' => 'Student record is required.',
            'report_type.required' => 'Report type is required.',
            'term_id.required_if' => 'Term must be specified for a Term Report.',
            'assessment_id.required_if' => 'Assessment must be specified for an Exam Report.',
            'report_configuration_id.required' => 'Report configuration must be selected.',
            'report_configuration_id.exists' => 'The selected report configuration does not exist.',
        ];
    }
}
