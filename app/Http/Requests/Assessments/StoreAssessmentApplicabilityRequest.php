<?php

namespace App\Http\Requests\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ClassSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAssessmentApplicabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AssessmentApplicability::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $mergeData = [];

        // Support class-level selection: class_id or class_ids
        if ($this->has('class_id') || $this->has('class_ids')) {
            $classIds = $this->has('class_ids') ? (array) $this->input('class_ids') : [(int) $this->input('class_id')];
            $classIds = array_values(array_filter(array_map('intval', $classIds)));
            $subjectMaxMarks = (array) $this->input('subject_maximum_marks', $this->input('subject_max_marks', []));
            $globalMaxMarks = $this->input('maximum_marks');

            $assessment = $this->route('assessment');
            $academicYearId = $assessment instanceof Assessment ? $assessment->academic_year_id : null;

            if ($academicYearId && ! empty($classIds)) {
                // Ensure all active sections of these classes have ClassSubjects for the selected subjects
                $sections = \App\Models\Section::where('academic_year_id', $academicYearId)
                    ->whereIn('class_id', $classIds)
                    ->where('is_active', true)
                    ->get();

                $selectedSubjectIds = array_keys($subjectMaxMarks);
                if ($sections->isNotEmpty() && ! empty($selectedSubjectIds)) {
                    foreach ($classIds as $cId) {
                        $classSections = $sections->where('class_id', $cId);
                        foreach ($selectedSubjectIds as $subId) {
                            $subjectObj = \App\Models\Subject::find((int) $subId);
                            if (! $subjectObj) {
                                continue;
                            }
                            foreach ($classSections as $sec) {
                                ClassSubject::firstOrCreate([
                                    'academic_year_id' => $academicYearId,
                                    'class_id' => $cId,
                                    'section_id' => $sec->id,
                                    'subject_id' => (int) $subId,
                                ], [
                                    'subject_name_snapshot' => $subjectObj->name,
                                    'is_active' => true,
                                ]);
                            }
                        }
                    }
                }

                $classSubjects = ClassSubject::where('academic_year_id', $academicYearId)
                    ->whereIn('class_id', $classIds)
                    ->where('is_active', true)
                    ->get();

                $csIds = [];
                $marksArray = [];
                foreach ($classSubjects as $cs) {
                    $mark = $subjectMaxMarks[$cs->subject_id] ?? (is_numeric($globalMaxMarks) ? $globalMaxMarks : ($globalMaxMarks[$cs->id] ?? null));
                    if ($mark !== null) {
                        $csIds[] = $cs->id;
                        $marksArray[$cs->id] = $mark;
                    }
                }

                $mergeData['class_subject_ids'] = $csIds;
                $mergeData['maximum_marks'] = $marksArray;
            }
        }

        // Normalize legacy single class_subject_id to class_subject_ids array
        if ($this->has('class_subject_id') && ! $this->has('class_subject_ids')) {
            $singleId = $this->input('class_subject_id');
            $mergeData['class_subject_ids'] = ! empty($singleId) ? [(int) $singleId] : [];
        }

        // If maximum_marks is a single scalar value and we have class_subject_ids, normalize to array
        $maxMarks = $this->input('maximum_marks');
        if (! is_array($maxMarks) && is_numeric($maxMarks)) {
            $ids = $this->input('class_subject_ids', $mergeData['class_subject_ids'] ?? []);
            $marksArray = [];
            foreach ($ids as $id) {
                $marksArray[(int) $id] = $maxMarks;
            }
            $mergeData['maximum_marks'] = $marksArray;
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        return [
            'class_subject_ids' => ['required', 'array', 'min:1'],
            'class_subject_ids.*' => ['required', 'integer', 'distinct', 'exists:class_subjects,id'],
            'maximum_marks' => ['required', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'class_subject_ids.required' => 'Please select at least one class subject.',
            'class_subject_ids.min' => 'Please select at least one class subject.',
            'class_subject_ids.*.distinct' => 'Duplicate class subjects cannot be selected in the same submission.',
            'class_subject_ids.*.exists' => 'One or more selected class subjects do not exist.',
            'maximum_marks.required' => 'Maximum marks are required for each selected subject.',
            'maximum_marks.array' => 'Maximum marks must be provided per selected subject.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ids = (array) $this->input('class_subject_ids', []);
            $maxMarks = (array) $this->input('maximum_marks', []);

            if (count($ids) !== count(array_unique($ids))) {
                $v->errors()->add(
                    'class_subject_ids',
                    'Duplicate class subjects cannot be selected in the same submission.'
                );
            }

            // Cross-year validation check
            $assessment = $this->route('assessment');
            if ($assessment instanceof Assessment && ! empty($ids)) {
                $mismatchedCount = ClassSubject::whereIn('id', $ids)
                    ->where('academic_year_id', '!=', $assessment->academic_year_id)
                    ->count();

                if ($mismatchedCount > 0) {
                    $v->errors()->add('class_subject_id', 'Class subject belongs to a different academic year.');
                    $v->errors()->add('class_subject_ids', 'Class subject belongs to a different academic year.');
                }
            }

            foreach ($ids as $id) {
                if (! isset($maxMarks[$id]) || $maxMarks[$id] === '' || $maxMarks[$id] === null) {
                    $v->errors()->add("maximum_marks.{$id}", 'Maximum marks must be specified for each selected subject.');
                    $v->errors()->add('maximum_marks', 'Maximum marks must be specified for each selected subject.');
                    continue;
                }

                if (! is_numeric($maxMarks[$id])) {
                    $v->errors()->add("maximum_marks.{$id}", 'Maximum marks must be a valid number.');
                    $v->errors()->add('maximum_marks', 'Maximum marks must be a valid number.');
                    continue;
                }

                $numericVal = (float) $maxMarks[$id];
                if ($numericVal <= 0) {
                    $v->errors()->add("maximum_marks.{$id}", 'Maximum marks must be greater than zero.');
                    $v->errors()->add('maximum_marks', 'Maximum marks must be greater than zero.');
                } elseif ($numericVal > 9999.99) {
                    $v->errors()->add("maximum_marks.{$id}", 'Maximum marks cannot exceed 9999.99.');
                    $v->errors()->add('maximum_marks', 'Maximum marks cannot exceed 9999.99.');
                }
            }
        });
    }
}
