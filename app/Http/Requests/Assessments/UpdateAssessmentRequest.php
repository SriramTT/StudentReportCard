<?php

namespace App\Http\Requests\Assessments;

use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');
        return $this->user()?->can('update', $assessment) ?? false;
    }

    public function rules(): array
    {
        return [
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'assessment_type_id' => ['required', 'integer', 'exists:assessment_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'assessment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $assessment = $this->route('assessment');
            if (! $assessment) {
                return;
            }

            $year = $assessment->academicYear;
            $termId = $this->input('term_id');
            $assessmentDate = $this->input('assessment_date');

            if ($year) {
                if ($termId) {
                    $term = Term::find($termId);
                    if ($term && $term->academic_year_id !== $year->id) {
                        $validator->errors()->add('term_id', 'The selected term does not belong to the assessment academic year.');
                    }
                }

                if ($assessmentDate) {
                    $startDate = $year->start_date instanceof \Carbon\CarbonInterface ? $year->start_date->format('Y-m-d') : substr((string)$year->start_date, 0, 10);
                    $endDate = $year->end_date instanceof \Carbon\CarbonInterface ? $year->end_date->format('Y-m-d') : substr((string)$year->end_date, 0, 10);
                    $dateStr = substr((string)$assessmentDate, 0, 10);

                    if ($dateStr < $startDate || $dateStr > $endDate) {
                        $validator->errors()->add('assessment_date', "The assessment date must fall within the academic year dates ({$startDate} to {$endDate}).");
                    }
                }
            }
        });
    }
}
