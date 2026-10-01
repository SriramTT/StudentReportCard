<?php

namespace App\Http\Requests\Academic;

use App\Enums\TeacherAssignmentType;
use App\Models\ClassSubject;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherAssignmentRequest extends FormRequest
{
    protected ?string $academicYearTamperError = null;

    public function authorize(): bool
    {
        $assignment = $this->route('teacherAssignment');

        return $assignment instanceof TeacherAssignment && ($this->user()?->can('update', $assignment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $assignment = $this->route('teacherAssignment');

        if ($assignment instanceof TeacherAssignment) {
            // Reject tampered academic_year_id if submitted by client and differs
            if ($this->has('academic_year_id') && ! empty($this->input('academic_year_id'))) {
                if ((int) $this->input('academic_year_id') !== (int) $assignment->academic_year_id) {
                    $this->academicYearTamperError = 'Changing the academic year of an existing assignment is not permitted.';
                }
            }

            // Always enforce preserved assignment academic_year_id and dates
            $this->merge([
                'academic_year_id' => $assignment->academic_year_id,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->academicYearTamperError) {
                $validator->errors()->add('academic_year_id', $this->academicYearTamperError);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $assignment = $this->route('teacherAssignment');

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
                function ($attribute, $value, $fail) use ($assignment) {
                    $classId = $this->input('class_id');
                    if ($classId && $value && $assignment) {
                        $section = Section::query()
                            ->where('id', $value)
                            ->where('class_id', $classId)
                            ->first();

                        if (! $section) {
                            $fail('The selected section does not belong to the selected class.');
                        } elseif ((int) $section->academic_year_id !== (int) $assignment->academic_year_id) {
                            $fail('The selected section does not belong to the assignment academic year.');
                        }
                    }
                },
            ],
            'assignment_type' => [
                'required',
                'string',
                Rule::in([TeacherAssignmentType::CLASS_TEACHER->value, TeacherAssignmentType::SUBJECT_TEACHER->value]),
                function ($attribute, $value, $fail) use ($assignment) {
                    $userId = $this->input('user_id');
                    if ($userId) {
                        $targetUser = User::query()->with('role')->find($userId);
                        if ($targetUser && $targetUser->role) {
                            if ($targetUser->role->name === 'Subject Teacher' && $value !== TeacherAssignmentType::SUBJECT_TEACHER->value) {
                                $fail('A Subject Teacher account cannot receive a Class Teacher assignment.');
                            }
                        }

                        $isActive = $this->has('is_active') ? $this->boolean('is_active') : ($assignment?->is_active ?? true);
                        if ($value === TeacherAssignmentType::CLASS_TEACHER->value && $isActive) {
                            $academicYearId = $assignment?->academic_year_id ?? $this->input('academic_year_id');
                            if ($academicYearId) {
                                $existing = TeacherAssignment::query()
                                    ->with(['schoolClass', 'section'])
                                    ->where('user_id', $userId)
                                    ->where('academic_year_id', $academicYearId)
                                    ->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
                                    ->where('is_active', true)
                                    ->when($assignment, fn ($q) => $q->where('id', '!=', $assignment->id))
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
                function ($attribute, $value, $fail) use ($assignment) {
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

                        $academicYearId = $assignment?->academic_year_id;
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
            'effective_to' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
