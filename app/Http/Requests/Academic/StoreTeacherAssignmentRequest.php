<?php

namespace App\Http\Requests\Academic;

use App\Enums\AcademicYearStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherAssignmentRequest extends FormRequest
{
    protected ?AcademicYear $resolvedAcademicYear = null;
    protected ?string $academicYearResolutionError = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', TeacherAssignment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $activeYears = AcademicYear::query()
            ->where('is_current', true)
            ->where('status', AcademicYearStatus::OPEN)
            ->get();

        if ($activeYears->isEmpty()) {
            $this->academicYearResolutionError = 'No active and open academic year is currently configured. An active academic year is required to create teacher assignments.';
            return;
        }

        if ($activeYears->count() > 1) {
            $this->academicYearResolutionError = 'Multiple academic years are marked as current. Exactly one academic year must be designated as current.';
            return;
        }

        $year = $activeYears->first();

        if (empty($year->start_date) || empty($year->end_date)) {
            $this->academicYearResolutionError = 'The active academic year does not have valid start and end date boundaries.';
            return;
        }

        if ($year->end_date->lt($year->start_date)) {
            $this->academicYearResolutionError = 'The active academic year has an invalid date boundary where end date is before start date.';
            return;
        }

        $this->resolvedAcademicYear = $year;

        // Check for tampered academic_year_id if submitted by client
        if ($this->has('academic_year_id') && ! empty($this->input('academic_year_id'))) {
            if ((int) $this->input('academic_year_id') !== (int) $year->id) {
                $this->academicYearResolutionError = 'The submitted academic year does not match the active academic year.';
                return;
            }
        }

        // Always enforce server-derived values (client inputs cannot override)
        $this->merge([
            'academic_year_id' => $year->id,
            'effective_from' => $year->start_date->toDateString(),
            'effective_to' => $year->end_date->toDateString(),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->academicYearResolutionError) {
                $validator->errors()->add('academic_year_id', $this->academicYearResolutionError);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $activeYear = $this->resolvedAcademicYear;

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $targetUser = User::query()->with('role')->find($value);
                    if (! $targetUser || ! in_array($targetUser->role?->name, ['Subject Teacher', 'Class Teacher'], true)) {
                        $fail('The selected user must have a Subject Teacher or Class Teacher role.');
                        return;
                    }

                    $assignmentType = $this->input('assignment_type');
                    if ($targetUser->role->name === 'Subject Teacher' && $assignmentType && $assignmentType !== TeacherAssignmentType::SUBJECT_TEACHER->value) {
                        $fail('A Subject Teacher account can only receive a Subject Teacher assignment.');
                    }
                },
            ],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => [
                'required',
                'integer',
                'exists:sections,id',
                function ($attribute, $value, $fail) use ($activeYear) {
                    $classId = $this->input('class_id');
                    if ($classId && $value) {
                        $section = Section::query()
                            ->where('id', $value)
                            ->where('class_id', $classId)
                            ->first();

                        if (! $section) {
                            $fail('The selected section does not belong to the selected class.');
                        } elseif ($activeYear && (int) $section->academic_year_id !== (int) $activeYear->id) {
                            $fail('The selected section does not belong to the active academic year.');
                        }
                    }
                },
            ],
            'assignment_type' => [
                'required',
                'string',
                Rule::in([TeacherAssignmentType::CLASS_TEACHER->value, TeacherAssignmentType::SUBJECT_TEACHER->value]),
                function ($attribute, $value, $fail) use ($activeYear) {
                    $userId = $this->input('user_id');
                    if ($userId) {
                        $targetUser = User::query()->with('role')->find($userId);
                        if ($targetUser && $targetUser->role) {
                            if ($targetUser->role->name === 'Subject Teacher' && $value !== TeacherAssignmentType::SUBJECT_TEACHER->value) {
                                $fail('A Subject Teacher account cannot receive a Class Teacher assignment.');
                            }
                        }

                        if ($value === TeacherAssignmentType::CLASS_TEACHER->value && $this->boolean('is_active', true)) {
                            $academicYearId = $activeYear?->id ?? $this->input('academic_year_id');
                            if ($academicYearId) {
                                $existing = TeacherAssignment::query()
                                    ->with(['schoolClass', 'section'])
                                    ->where('user_id', $userId)
                                    ->where('academic_year_id', $academicYearId)
                                    ->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
                                    ->where('is_active', true)
                                    ->first();

                                if ($existing) {
                                    $className = $existing->schoolClass?->name ?? 'another class';
                                    $sectionName = $existing->section?->name ? " ({$existing->section->name})" : '';
                                    $fail("This teacher already has an active Class Teacher assignment for {$className}{$sectionName} in this academic year. A teacher can only be assigned as Class Teacher to one class.");
                                }
                            }
                        }
                    }
                },
            ],
            'subject_id' => [
                'nullable',
                'required_if:assignment_type,' . TeacherAssignmentType::SUBJECT_TEACHER->value,
                'integer',
                'exists:subjects,id',
                function ($attribute, $value, $fail) use ($activeYear) {
                    $assignmentType = $this->input('assignment_type');

                    if ($assignmentType === TeacherAssignmentType::CLASS_TEACHER->value) {
                        if (! empty($value)) {
                            $fail('Class Teacher assignment must not specify a subject (it applies to all subjects).');
                        }
                    } elseif ($assignmentType === TeacherAssignmentType::SUBJECT_TEACHER->value) {
                        if (empty($value)) {
                            $fail('Subject is required for a Subject Teacher assignment.');
                            return;
                        }

                        $academicYearId = $activeYear?->id ?? $this->input('academic_year_id');
                        $classId = $this->input('class_id');
                        $sectionId = $this->input('section_id');

                        if ($academicYearId && $classId && $sectionId) {
                            $isMapped = ClassSubject::query()
                                ->where('academic_year_id', $academicYearId)
                                ->where('class_id', $classId)
                                ->where(function ($q) use ($sectionId) {
                                    $q->where('section_id', $sectionId)
                                      ->orWhereNull('section_id');
                                })
                                ->where('subject_id', $value)
                                ->where('is_active', true)
                                ->exists();

                            if (! $isMapped) {
                                $fail('The selected subject is not offered for this class and section.');
                            }
                        }
                    }
                },
            ],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['required', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'also_assign_subject' => ['nullable', 'boolean'],
            'also_subject_id' => [
                'nullable',
                'required_if:also_assign_subject,1,true',
                'integer',
                'exists:subjects,id',
                function ($attribute, $value, $fail) use ($activeYear) {
                    if (! $this->boolean('also_assign_subject')) {
                        return;
                    }

                    if ($this->input('assignment_type') !== TeacherAssignmentType::CLASS_TEACHER->value) {
                        $fail('Dual assignment is only supported when assignment type is Class Teacher.');
                        return;
                    }

                    $academicYearId = $activeYear?->id ?? $this->input('academic_year_id');
                    $classId = $this->input('class_id');
                    $sectionId = $this->input('section_id');

                    if ($academicYearId && $classId && $sectionId && $value) {
                        $isMapped = ClassSubject::query()
                            ->where('academic_year_id', $academicYearId)
                            ->where('class_id', $classId)
                            ->where(function ($q) use ($sectionId) {
                                $q->where('section_id', $sectionId)
                                  ->orWhereNull('section_id');
                            })
                            ->where('subject_id', $value)
                            ->where('is_active', true)
                            ->exists();

                        if (! $isMapped) {
                            $fail('The selected subject for dual assignment is not offered for this class and section.');
                        }
                    }
                },
            ],
        ];
    }
}
