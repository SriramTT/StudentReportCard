<?php

namespace App\Http\Requests\Assessments;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Assessment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
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
            $yearId = $this->input('academic_year_id');
            $termId = $this->input('term_id');
            $assessmentDate = $this->input('assessment_date');

            if ($yearId) {
                $year = AcademicYear::find($yearId);
                if ($year) {
                    if ($termId) {
                        $term = Term::find($termId);
                        if ($term && $term->academic_year_id !== (int) $yearId) {
                            $validator->errors()->add('term_id', 'The selected term does not belong to the selected academic year.');
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
            }
        });
    }
}
