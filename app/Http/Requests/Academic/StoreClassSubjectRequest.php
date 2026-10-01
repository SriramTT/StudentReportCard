<?php

namespace App\Http\Requests\Academic;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class StoreClassSubjectRequest extends FormRequest
{
    protected ?string $academicYearResolutionError = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ClassSubject::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $activeYears = AcademicYear::query()
            ->where('is_current', true)
            ->where('status', AcademicYearStatus::OPEN)
            ->get();

        if ($activeYears->isEmpty()) {
            $this->academicYearResolutionError = 'No active and open academic year is currently configured. An active academic year is required to map subjects.';
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

        $mergeData = [
            'academic_year_id' => $year->id,
        ];

        // Normalize single subject_id to subject_ids array if needed
        if ($this->has('subject_id') && ! $this->has('subject_ids')) {
            $singleId = $this->input('subject_id');
            $mergeData['subject_ids'] = ! empty($singleId) ? [(int) $singleId] : [];
        }

        $this->merge($mergeData);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.required' => 'The section field is required. Select a specific section or All Sections (Class-Wide).',
            'subject_ids.required' => 'Please select at least one subject to map.',
            'subject_ids.min' => 'Please select at least one subject to map.',
            'subject_ids.*.distinct' => 'Duplicate subjects cannot be selected.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->academicYearResolutionError) {
                $validator->errors()->add('academic_year_id', $this->academicYearResolutionError);
                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $academicYearId = (int) $this->input('academic_year_id');
            $classId = (int) $this->input('class_id');
            $sectionInput = $this->input('section_id');
            $subjectIds = (array) $this->input('subject_ids', []);

            if ($sectionInput === 'all_sections') {
                $activeSections = Section::where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->where('is_active', true)
                    ->get();

                if ($activeSections->isEmpty()) {
                    $validator->errors()->add('section_id', 'The selected class has no active sections in the current academic year.');
                    return;
                }
            } else {
                $sectionId = (int) $sectionInput;
                $section = Section::find($sectionId);

                if (! $section || (int) $section->class_id !== $classId || (int) $section->academic_year_id !== $academicYearId) {
                    $validator->errors()->add('section_id', 'The selected section does not belong to the selected class and academic year.');
                    return;
                }

                // If single subject mapping for a single section, validate against duplicate
                if (count($subjectIds) === 1) {
                    $exists = ClassSubject::where('academic_year_id', $academicYearId)
                        ->where('class_id', $classId)
                        ->where('section_id', $sectionId)
                        ->where('subject_id', (int) $subjectIds[0])
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add('subject_id', 'This subject is already assigned to the selected class and section.');
                        $validator->errors()->add('subject_ids', 'This subject is already assigned to the selected class and section.');
                    }
                }
            }
        });
    }
}
