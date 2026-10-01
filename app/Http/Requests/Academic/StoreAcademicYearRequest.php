<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\AcademicYear::class) ?? false;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if ($startDate && $endDate) {
                try {
                    $parsedStart = \Illuminate\Support\Carbon::parse($startDate)->startOfDay();
                    $parsedEnd = \Illuminate\Support\Carbon::parse($endDate)->startOfDay();
                    $today = now()->startOfDay();

                    if ($parsedStart->lte($today) && $today->lte($parsedEnd)) {
                        $hasOverlappingCurrent = \App\Models\AcademicYear::whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->where('start_date', '<=', $today->toDateString())
                            ->where('end_date', '>=', $today->toDateString())
                            ->exists();

                        if ($hasOverlappingCurrent) {
                            $validator->errors()->add('start_date', 'Another academic year is already active for today. Overlapping current academic years are prohibited.');
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignored here if invalid format, caught by date rule
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
                function ($attribute, $value, $fail) {
                    try {
                        $date = \Illuminate\Support\Carbon::parse($value)->startOfDay();
                        $maxStart = now()->addMonthsNoOverflow(6)->endOfDay();
                        if ($date->gt($maxStart)) {
                            $fail('Start date cannot exceed 6 calendar months from today.');
                        }
                    } catch (\Throwable $e) {}
                },
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                function ($attribute, $value, $fail) {
                    try {
                        $date = \Illuminate\Support\Carbon::parse($value)->endOfDay();
                        $maxEnd = now()->addMonthsNoOverflow(18)->endOfDay();
                        if ($date->gt($maxEnd)) {
                            $fail('End date cannot exceed 18 calendar months from today.');
                        }
                    } catch (\Throwable $e) {}
                },
            ],
            'is_current' => ['nullable', 'boolean'],
        ];
    }
}
