<?php

namespace App\Http\Requests\Academic;

use App\Models\ClassSubject;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClassSubjectRequest extends FormRequest
{
    protected ?ClassSubject $resolvedClassSubject = null;

    protected function getClassSubject(): ?ClassSubject
    {
        if ($this->resolvedClassSubject !== null) {
            return $this->resolvedClassSubject;
        }

        $param = $this->route('classSubject') ?? $this->route('class_subject');
        if ($param instanceof ClassSubject) {
            $this->resolvedClassSubject = $param;
        } elseif (is_numeric($param)) {
            $this->resolvedClassSubject = ClassSubject::find((int) $param);
        }

        return $this->resolvedClassSubject;
    }

    public function authorize(): bool
    {
        $classSubject = $this->getClassSubject();
        return $classSubject ? ($this->user()?->can('update', $classSubject) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'class_id' => ['sometimes', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $classSubject = $this->getClassSubject();
            if (! $classSubject) {
                return;
            }

            // Always preserve existing academic_year_id
            $academicYearId = $classSubject->academic_year_id;
            $classId = $this->has('class_id') ? (int) $this->input('class_id') : $classSubject->class_id;
            $subjectId = $this->has('subject_id') ? (int) $this->input('subject_id') : $classSubject->subject_id;
            $sectionId = $this->has('section_id') ? (! empty($this->input('section_id')) ? (int) $this->input('section_id') : null) : $classSubject->section_id;

            $query = ClassSubject::where('academic_year_id', $academicYearId)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('id', '!=', $classSubject->id);

            if ($sectionId !== null) {
                $section = \App\Models\Section::find($sectionId);
                if (! $section || (int) $section->class_id !== $classId || (int) $section->academic_year_id !== $academicYearId) {
                    $validator->errors()->add('section_id', 'The selected section does not belong to the selected class and academic year.');
                    return;
                }
                $query->where('section_id', $sectionId);
            } else {
                $query->whereNull('section_id');
            }

            if ($query->exists()) {
                $validator->errors()->add('subject_id', 'This subject is already assigned to the selected class and section.');
            }
        });
    }
}
