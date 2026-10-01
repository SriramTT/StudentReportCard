<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $academicYear = $this->route('academicYear');
        return $this->user()?->can('update', $academicYear) ?? false;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $academicYear = $this->route('academicYear');
            if (! $academicYear instanceof \App\Models\AcademicYear) {
                $academicYear = \App\Models\AcademicYear::find($academicYear);
            }
            if (! $academicYear) {
                return;
            }

            // Multiple current year overlap check
            $startDate = $this->input('start_date', $academicYear->start_date?->toDateString());
            $endDate = $this->input('end_date', $academicYear->end_date?->toDateString());
            if ($startDate && $endDate) {
                try {
                    $today = now()->startOfDay();
                    $parsedStart = \Illuminate\Support\Carbon::parse($startDate)->startOfDay();
                    $parsedEnd = \Illuminate\Support\Carbon::parse($endDate)->startOfDay();

                    if ($parsedStart->lte($today) && $today->lte($parsedEnd)) {
                        $hasOverlap = \App\Models\AcademicYear::where('id', '!=', $academicYear->id)
                            ->whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->where('start_date', '<=', $today->toDateString())
                            ->where('end_date', '>=', $today->toDateString())
                            ->exists();

                        if ($hasOverlap) {
                            $validator->errors()->add('start_date', 'Another academic year is already active for today. Overlapping current academic years are prohibited.');
                        }
                    }
                } catch (\Throwable $e) {}
            }
        });
    }

    public function rules(): array
    {
        $academicYearId = $this->route('academicYear')?->id ?? $this->route('academic_year');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('academic_years', 'name')->ignore($academicYearId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ];
    }
}
