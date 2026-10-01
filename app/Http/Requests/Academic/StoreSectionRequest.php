<?php

namespace App\Http\Requests\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    protected ?string $academicYearResolutionError = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Section::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $activeYears = AcademicYear::query()
            ->where('is_current', true)
            ->where('status', AcademicYearStatus::OPEN)
            ->get();

        if ($activeYears->isEmpty()) {
            $this->academicYearResolutionError = 'No active and open academic year is currently configured. An active academic year is required to create sections.';
            return;
        }

        if ($activeYears->count() > 1) {
            $this->academicYearResolutionError = 'Multiple academic years are marked as current. Exactly one academic year must be designated as current.';
            return;
        }

        $year = $activeYears->first();

        // Check for tampered academic_year_id if submitted by client
        if ($this->has('academic_year_id') && ! empty($this->input('academic_year_id'))) {
            if ((int) $this->input('academic_year_id') !== (int) $year->id) {
                $this->academicYearResolutionError = 'The submitted academic year does not match the active academic year.';
                return;
            }
        }

        $this->merge([
            'academic_year_id' => $year->id,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->academicYearResolutionError) {
                $validator->errors()->add('academic_year_id', $this->academicYearResolutionError);
                return;
            }

            if (! $this->filled('class_id') && ! $this->filled('class_name')) {
                $validator->errors()->add('class_name', 'Class is required.');
            }

            if (! $this->filled('name') && (! is_array($this->input('section_names')) || count($this->input('section_names')) === 0)) {
                $validator->errors()->add('section_names', 'At least one section name is required.');
            }

            $rawNames = $this->input('section_names');
            if (is_array($rawNames)) {
                $trimmed = array_filter(array_map('trim', $rawNames), fn($v) => $v !== '');
                if (empty($trimmed)) {
                    $validator->errors()->add('section_names', 'At least one valid section name is required.');
                }
                $lowered = array_map('strtolower', $trimmed);
                if (count($lowered) !== count(array_unique($lowered))) {
                    $validator->errors()->add('section_names', 'Section names must be unique within the class.');
                }
            }

            $targetClassId = $this->input('class_id');
            if (! $targetClassId && $this->filled('class_name')) {
                $existingClass = \App\Models\SchoolClass::whereRaw('LOWER(name) = ?', [strtolower(trim($this->input('class_name')))])->first();
                $targetClassId = $existingClass?->id;
            }

            if ($targetClassId) {
                $namesToCheck = [];
                if (is_array($this->input('section_names'))) {
                    $namesToCheck = array_filter(array_map('trim', $this->input('section_names')), fn($v) => $v !== '');
                } elseif ($this->filled('name')) {
                    $namesToCheck = [trim($this->input('name'))];
                }

                foreach ($namesToCheck as $secName) {
                    $exists = \App\Models\Section::where('academic_year_id', $this->input('academic_year_id'))
                        ->where('class_id', $targetClassId)
                        ->whereRaw('LOWER(name) = ?', [strtolower($secName)])
                        ->exists();

                    if ($exists) {
                        $errorKey = is_array($this->input('section_names')) ? 'section_names' : 'name';
                        $validator->errors()->add($errorKey, "Section '{$secName}' already exists for this class in the active academic year.");
                    }
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:50'],
            'section_names' => ['nullable', 'array', 'min:1'],
            'section_names.*' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
