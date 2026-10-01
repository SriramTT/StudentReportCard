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
        protected AuditService $auditService,
        protected StudentSubjectAllocationService $allocationService
    ) {}

    /**
     * Map multiple subjects to a specific section or all sections (class-wide).
     *
     * @param  array<string, mixed>  $data
     * @return array{created: int, skipped: int, mappings: array<ClassSubject>}
     */
    public function mapSubjectsToClass(array $data, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($data, $actorId) {
            $academicYearId = (int) $data['academic_year_id'];
            $classId = (int) $data['class_id'];
            $sectionInput = $data['section_id'] ?? null;
            $subjectIds = isset($data['subject_ids']) ? (array) $data['subject_ids'] : (isset($data['subject_id']) ? [(int) $data['subject_id']] : []);
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;
            $userId = $actorId ?? Auth::id();

            // Resolve target sections
            if ($sectionInput === 'all_sections') {
                $sections = Section::where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->where('is_active', true)
                    ->orderBy('name', 'asc')
                    ->get();

                if ($sections->isEmpty()) {
                    throw ValidationException::withMessages([
                        'section_id' => ['The selected class has no active sections in the current academic year.'],
                    ]);
                }
            } elseif ($sectionInput !== null && $sectionInput !== '') {
                $section = Section::findOrFail((int) $sectionInput);
                if ($section->academic_year_id !== $academicYearId || $section->class_id !== $classId) {
                    throw ValidationException::withMessages([
                        'section_id' => ['The selected section does not belong to the selected academic year and class.'],
                    ]);
                }
                $sections = collect([$section]);
            } else {
                // Legacy fallback: section_id is null
                $sections = collect([null]);
            }

            $created = 0;
            $skipped = 0;
            $createdMappings = [];

            foreach ($sections as $sec) {
                $secId = $sec?->id;

                foreach ($subjectIds as $subId) {
                    $subId = (int) $subId;

                    $existingQuery = ClassSubject::where('academic_year_id', $academicYearId)
                        ->where('class_id', $classId)
                        ->where('subject_id', $subId);

                    if ($secId !== null) {
                        $existingQuery->where('section_id', $secId);
                    } else {
                        $existingQuery->whereNull('section_id');
                    }

                    if ($existingQuery->exists()) {
                        $skipped++;
                        continue;
                    }

                    $subject = Subject::findOrFail($subId);
                    $subjectSnapshot = $subject->name;

                    try {
                        $classSubject = ClassSubject::create([
                            'academic_year_id' => $academicYearId,
                            'class_id' => $classId,
                            'section_id' => $secId,
                            'subject_id' => $subId,
                            'subject_name_snapshot' => $subjectSnapshot,
                            'is_active' => $isActive,
                        ]);
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                        $skipped++;
                        continue;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uk_class_subjects_config')) {
                            $skipped++;
                            continue;
                        }
                        throw $e;
                    }

                    $this->auditService->logDomainAction(
                        userId: $userId,
                        action: 'CREATE_CLASS_SUBJECT',
                        entityType: 'class_subjects',
                        entityId: $classSubject->id,
                        beforeData: null,
                        afterData: $classSubject->only([
                            'academic_year_id', 'class_id', 'section_id', 'subject_id', 'subject_name_snapshot', 'is_active',
                        ]),
                        description: "Mapped subject {$subjectSnapshot} to class ID {$classId}" . ($sec ? " section {$sec->name}" : '')
                    );

                    if ($classSubject->is_active) {
                        $this->allocationService->allocateClassSubjectToStudents($classSubject, $userId);
                    }

                    $createdMappings[] = $classSubject;
                    $created++;
                }
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
                'mappings' => $createdMappings,
            ];
        });
    }

    /**
     * Create a new ClassSubject mapping with derived subject name snapshot.
     * Backwards-compatible wrapper around mapSubjectsToClass.
     *
     * @param  array<string, mixed>  $data
     */
    public function createClassSubject(array $data): ClassSubject
    {
        if (! isset($data['subject_ids']) && isset($data['subject_id'])) {
            $data['subject_ids'] = [(int) $data['subject_id']];
        }

        $result = $this->mapSubjectsToClass($data);

        if (! empty($result['mappings'])) {
            return $result['mappings'][0];
        }

        $query = ClassSubject::where('academic_year_id', (int) $data['academic_year_id'])
            ->where('class_id', (int) $data['class_id'])
            ->where('subject_id', (int) ($data['subject_id'] ?? $data['subject_ids'][0]));

        if (! empty($data['section_id']) && $data['section_id'] !== 'all_sections') {
            $query->where('section_id', (int) $data['section_id']);
        } else {
            $query->whereNull('section_id');
        }

        return $query->firstOrFail();
    }

    /**
     * Batch activate or deactivate all mappings in a class-section group.
     */
    public function updateGroupStatus(int $classId, ?int $sectionId, int $academicYearId, bool $isActive, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($classId, $sectionId, $academicYearId, $isActive, $actorId) {
            $query = ClassSubject::where('academic_year_id', $academicYearId)
                ->where('class_id', $classId);

            if ($sectionId !== null) {
                $query->where('section_id', $sectionId);
            } else {
                $query->whereNull('section_id');
            }

            $mappings = $query->get();
            $userId = $actorId ?? Auth::id();
            $count = 0;

            foreach ($mappings as $cs) {
                if ($cs->is_active !== $isActive) {
                    $before = ['is_active' => $cs->is_active];
                    $cs->update(['is_active' => $isActive]);
                    $after = ['is_active' => $isActive];

                    $this->auditService->logDomainAction(
                        userId: $userId,
                        action: 'UPDATE_CLASS_SUBJECT',
                        entityType: 'class_subjects',
                        entityId: $cs->id,
                        beforeData: $before,
                        afterData: $after,
                        description: ($isActive ? 'Activated' : 'Deactivated') . " class subject mapping ID {$cs->id}"
                    );

                    if ($isActive) {
                        $this->allocationService->allocateClassSubjectToStudents($cs, $userId);
                    }

                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Delete all mappings for a class-section group if completely unreferenced.
     *
     * @throws \DomainException
     */
    public function deleteGroupMappings(int $classId, ?int $sectionId, int $academicYearId, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($classId, $sectionId, $academicYearId, $actorId) {
            $query = ClassSubject::where('academic_year_id', $academicYearId)
                ->where('class_id', $classId);

            if ($sectionId !== null) {
                $query->where('section_id', $sectionId);
            } else {
                $query->whereNull('section_id');
            }

            $mappings = $query->get();

            // Check dependencies across all mappings in the group
            foreach ($mappings as $cs) {
                if ($cs->studentSubjectAllocations()->exists() || $cs->assessmentApplicabilities()->exists()) {
                    throw new \DomainException(
                        "Cannot remove mappings for this section because subject '{$cs->subject_name_snapshot}' is referenced by student allocations or assessment rules. Please deactivate instead."
                    );
                }
            }

            $userId = $actorId ?? Auth::id();
            $count = 0;

            foreach ($mappings as $cs) {
                $beforeData = [
                    'id' => $cs->id,
                    'academic_year_id' => $cs->academic_year_id,
                    'class_id' => $cs->class_id,
                    'section_id' => $cs->section_id,
                    'subject_id' => $cs->subject_id,
                    'subject_name_snapshot' => $cs->subject_name_snapshot,
                    'is_active' => $cs->is_active,
                ];

                $cs->delete();

                $this->auditService->logDomainAction(
                    userId: $userId,
                    action: 'DELETE_CLASS_SUBJECT',
                    entityType: 'class_subjects',
                    entityId: $beforeData['id'],
                    beforeData: $beforeData,
                    afterData: null,
                    description: "Permanently removed class subject mapping: {$beforeData['subject_name_snapshot']}"
                );

                $count++;
            }

            return $count;
        });
    }

    /**
     * Synchronize subjects mapped to a class-section group from the edit modal.
     *
     * @param  array<int>  $selectedSubjectIds
     * @return array{added: int, reactivated: int, removed: int, deactivated: int, retained: int}
     *
     * @throws ValidationException|\DomainException
     */
    public function syncGroupSubjects(int $classId, ?int $sectionId, int $academicYearId, array $selectedSubjectIds, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($classId, $sectionId, $academicYearId, $selectedSubjectIds, $actorId) {
            // Validate section scope if provided
            if ($sectionId !== null) {
                $section = Section::findOrFail($sectionId);
                if ((int) $section->academic_year_id !== $academicYearId || (int) $section->class_id !== $classId) {
                    throw ValidationException::withMessages([
                        'section_id' => ['The selected section does not belong to the selected academic year and class.'],
                    ]);
                }
            }

            $query = ClassSubject::where('academic_year_id', $academicYearId)
                ->where('class_id', $classId);

            if ($sectionId !== null) {
                $query->where('section_id', $sectionId);
            } else {
                $query->whereNull('section_id');
            }

            $currentMappings = $query->get()->keyBy('subject_id');
            $selectedMap = array_flip($selectedSubjectIds);
            $userId = $actorId ?? Auth::id();

            $added = 0;
            $reactivated = 0;
            $removed = 0;
            $deactivated = 0;
            $retained = 0;

            // 1. Process existing mappings
            foreach ($currentMappings as $subId => $cs) {
                if (! isset($selectedMap[$subId])) {
                    // Subject is unchecked / omitted
                    if ($cs->is_active) {
                        // Guard against unmapping when marks are recorded
                        if ($cs->marks()->exists()) {
                            throw ValidationException::withMessages([
                                'subject_ids' => [
                                    "Cannot unmap '{$cs->subject_name_snapshot}': Marks have already been recorded for this subject.",
                                ],
                            ]);
                        }

                        $hasDependencies = $cs->studentSubjectAllocations()->exists() || $cs->assessmentApplicabilities()->exists();

                        if ($hasDependencies) {
                            // Safely deactivate to preserve student allocations and assessment rules
                            $before = ['is_active' => true];
                            $cs->update(['is_active' => false]);
                            $after = ['is_active' => false];

                            $this->auditService->logDomainAction(
                                userId: $userId,
                                action: 'UPDATE_CLASS_SUBJECT',
                                entityType: 'class_subjects',
                                entityId: $cs->id,
                                beforeData: $before,
                                afterData: $after,
                                description: "Deactivated class subject mapping: {$cs->subject_name_snapshot}"
                            );

                            $deactivated++;
                        } else {
                            // Safe physical removal when completely unreferenced
                            $beforeData = [
                                'id' => $cs->id,
                                'academic_year_id' => $cs->academic_year_id,
                                'class_id' => $cs->class_id,
                                'section_id' => $cs->section_id,
                                'subject_id' => $cs->subject_id,
                                'subject_name_snapshot' => $cs->subject_name_snapshot,
                                'is_active' => $cs->is_active,
                            ];

                            $cs->delete();

                            $this->auditService->logDomainAction(
                                userId: $userId,
                                action: 'DELETE_CLASS_SUBJECT',
                                entityType: 'class_subjects',
                                entityId: $beforeData['id'],
                                beforeData: $beforeData,
                                afterData: null,
                                description: "Removed class subject mapping: {$beforeData['subject_name_snapshot']}"
                            );

                            $removed++;
                        }
                    }
                } else {
                    // Subject is selected
                    if (! $cs->is_active) {
                        // Reactivate previously inactive mapping
                        $before = ['is_active' => false];
                        $cs->update(['is_active' => true]);
                        $after = ['is_active' => true];

                        $this->auditService->logDomainAction(
                            userId: $userId,
                            action: 'UPDATE_CLASS_SUBJECT',
                            entityType: 'class_subjects',
                            entityId: $cs->id,
                            beforeData: $before,
                            afterData: $after,
                            description: "Reactivated class subject mapping: {$cs->subject_name_snapshot}"
                        );

                        $this->allocationService->allocateClassSubjectToStudents($cs, $userId);
                        $reactivated++;
                    } else {
                        $retained++;
                    }
                }
            }

            // 2. Add newly selected subjects that are not in currentMappings
            foreach ($selectedSubjectIds as $subId) {
                if (! $currentMappings->has($subId)) {
                    $subject = Subject::findOrFail($subId);

                    if (! $subject->is_active) {
                        throw ValidationException::withMessages([
                            'subject_ids' => ["Cannot map inactive subject '{$subject->name}'."],
                        ]);
                    }

                    $snapshot = $subject->name;

                    $newCs = ClassSubject::create([
                        'academic_year_id' => $academicYearId,
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'subject_id' => $subId,
                        'subject_name_snapshot' => $snapshot,
                        'is_active' => true,
                    ]);

                    $this->auditService->logDomainAction(
                        userId: $userId,
                        action: 'CREATE_CLASS_SUBJECT',
                        entityType: 'class_subjects',
                        entityId: $newCs->id,
                        beforeData: null,
                        afterData: $newCs->only([
                            'academic_year_id', 'class_id', 'section_id', 'subject_id', 'subject_name_snapshot', 'is_active',
                        ]),
                        description: "Mapped subject {$snapshot} to class ID {$classId}"
                    );

                    $this->allocationService->allocateClassSubjectToStudents($newCs, $userId);
                    $added++;
                }
            }

            return [
                'added' => $added,
                'reactivated' => $reactivated,
                'removed' => $removed,
                'deactivated' => $deactivated,
                'retained' => $retained,
            ];
        });
    }

    /**
     * Update an existing ClassSubject mapping.
     * Note: subject_name_snapshot is historical and must not be modified here unless subject_id changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateClassSubject(ClassSubject $classSubject, array $data): ClassSubject
    {
        return DB::transaction(function () use ($classSubject, $data) {
            $beforeData = $classSubject->only([
                'academic_year_id', 'class_id', 'section_id', 'subject_id', 'subject_name_snapshot', 'is_active',
            ]);

            $academicYearId = isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : $classSubject->academic_year_id;
            $classId = isset($data['class_id']) ? (int) $data['class_id'] : $classSubject->class_id;
            $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : $classSubject->subject_id;
            $sectionId = array_key_exists('section_id', $data) ? (! empty($data['section_id']) ? (int) $data['section_id'] : null) : $classSubject->section_id;

            // Validate parent section context if provided
            if ($sectionId !== null) {
                $section = Section::findOrFail($sectionId);
                if ($section->academic_year_id !== $academicYearId || $section->class_id !== $classId) {
                    throw ValidationException::withMessages([
                        'section_id' => ['The selected section does not belong to the selected academic year and class.'],
                    ]);
                }
            }

            // Check duplicate excluding self
            $duplicateQuery = ClassSubject::where('academic_year_id', $academicYearId)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('id', '!=', $classSubject->id);

            if ($sectionId !== null) {
                $duplicateQuery->where('section_id', $sectionId);
            } else {
                $duplicateQuery->whereNull('section_id');
            }

            // Check downstream historical dependencies before altering identity
            $isIdentityChanged = ($subjectId !== $classSubject->subject_id)
                || ($sectionId !== $classSubject->section_id)
                || ($classId !== $classSubject->class_id)
                || ($academicYearId !== $classSubject->academic_year_id);

            if ($isIdentityChanged) {
                $hasDependentData = $classSubject->assessmentApplicabilities()->exists()
                    || $classSubject->marks()->exists()
                    || $classSubject->studentSubjectAllocations()->exists();

                if ($hasDependentData) {
                    throw ValidationException::withMessages([
                        'subject_id' => ['Cannot alter subject or section mapping because dependent marks, assessments, or student allocations already exist for this class subject. Please deactivate this mapping and create a new one.'],
                    ]);
                }
            }

            if ($duplicateQuery->exists()) {
                throw ValidationException::withMessages([
                    'subject_id' => ['This subject is already assigned to the selected class and section.'],
                ]);
            }

            $updateData = [];
            if (isset($data['is_active'])) {
                $updateData['is_active'] = (bool) $data['is_active'];
            }
            if (isset($data['academic_year_id'])) {
                $updateData['academic_year_id'] = $academicYearId;
            }
            if (isset($data['class_id'])) {
                $updateData['class_id'] = $classId;
            }
            if (array_key_exists('section_id', $data)) {
                $updateData['section_id'] = $sectionId;
            }
            if (isset($data['subject_id'])) {
                $updateData['subject_id'] = $subjectId;
                if ($subjectId !== $classSubject->subject_id) {
                    $newSubject = Subject::findOrFail($subjectId);
                    $updateData['subject_name_snapshot'] = $newSubject->name;
                }
            }

            if (! empty($updateData)) {
                try {
                    $classSubject->update($updateData);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    throw ValidationException::withMessages([
                        'subject_id' => ['This subject is already assigned to the selected class and section.'],
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uk_class_subjects_config')) {
                        throw ValidationException::withMessages([
                            'subject_id' => ['This subject is already assigned to the selected class and section.'],
                        ]);
                    }
                    throw $e;
                }
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

    /**
     * Delete a ClassSubject mapping if completely unused.
     *
     * @throws \DomainException
     */
    public function deleteClassSubject(ClassSubject $classSubject): void
    {
        if (
            $classSubject->studentSubjectAllocations()->exists() ||
            $classSubject->assessmentApplicabilities()->exists()
        ) {
            throw new \DomainException('This class-subject mapping cannot be removed because students or assessments are already linked to it. Deactivate it instead.');
        }

        DB::transaction(function () use ($classSubject) {
            $beforeData = [
                'id' => $classSubject->id,
                'academic_year_id' => $classSubject->academic_year_id,
                'class_id' => $classSubject->class_id,
                'section_id' => $classSubject->section_id,
                'subject_id' => $classSubject->subject_id,
                'subject_name_snapshot' => $classSubject->subject_name_snapshot,
                'is_active' => $classSubject->is_active,
            ];

            $classSubject->delete();

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'DELETE_CLASS_SUBJECT',
                entityType: 'class_subjects',
                entityId: $beforeData['id'],
                beforeData: $beforeData,
                afterData: null,
                description: "Permanently removed class subject mapping: {$beforeData['subject_name_snapshot']}"
            );
        });
    }
}
