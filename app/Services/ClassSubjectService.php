<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassSubjectService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new ClassSubject mapping with derived subject name snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function createClassSubject(array $data): ClassSubject
    {
        return DB::transaction(function () use ($data) {
            $academicYearId = (int) $data['academic_year_id'];
            $classId = (int) $data['class_id'];
            $sectionId = ! empty($data['section_id']) ? (int) $data['section_id'] : null;
            $subjectId = (int) $data['subject_id'];

            // Validate parent section context if provided
            if ($sectionId !== null) {
                $section = Section::findOrFail($sectionId);
                if ($section->academic_year_id !== $academicYearId || $section->class_id !== $classId) {
                    throw ValidationException::withMessages([
                        'section_id' => ['The selected section does not belong to the selected academic year and class.'],
                    ]);
                }
            }

            // Fetch subject and snapshot its current name
            $subject = Subject::findOrFail($subjectId);
            $subjectSnapshot = $subject->name;

            $classSubject = ClassSubject::create([
                'academic_year_id' => $academicYearId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'subject_name_snapshot' => $subjectSnapshot,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_CLASS_SUBJECT',
                entityType: 'class_subjects',
                entityId: $classSubject->id,
                beforeData: null,
                afterData: $classSubject->only([
                    'academic_year_id', 'class_id', 'section_id', 'subject_id', 'subject_name_snapshot', 'is_active',
                ]),
                description: "Mapped subject {$subjectSnapshot} to class ID {$classId}"
            );

            return $classSubject;
        });
    }

    /**
     * Update an existing ClassSubject mapping.
     * Note: subject_name_snapshot is historical and must not be modified here.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateClassSubject(ClassSubject $classSubject, array $data): ClassSubject
    {
        return DB::transaction(function () use ($classSubject, $data) {
            $beforeData = $classSubject->only([
                'academic_year_id', 'class_id', 'section_id', 'subject_id', 'subject_name_snapshot', 'is_active',
            ]);

            if (isset($data['is_active'])) {
                $classSubject->update([
                    'is_active' => (bool) $data['is_active'],
                ]);
            }

            $afterData = $classSubject->fresh()->only([
                'academic_year_id', 'class_id', 'section_id', 'subject_id', 'subject_name_snapshot', 'is_active',
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_CLASS_SUBJECT',
                entityType: 'class_subjects',
                entityId: $classSubject->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: "Updated class subject mapping ID {$classSubject->id}"
            );

            return $classSubject;
        });
    }
}
