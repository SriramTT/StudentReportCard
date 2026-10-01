<?php

namespace App\Services;

use App\Enums\SubjectCategory;
use App\Models\ClassSubject;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StudentSubjectAllocationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Allocate all active classroom subjects (class-wide and section-specific)
     * to a student academic placement record.
     */
    public function allocateDefaultSubjectsForPlacement(StudentAcademicRecord $record, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($record, $actorId) {
            $applicableClassSubjects = ClassSubject::query()
                ->where('academic_year_id', $record->academic_year_id)
                ->where('class_id', $record->class_id)
                ->where('is_active', true)
                ->where(function ($q) use ($record) {
                    $q->where('section_id', $record->section_id)
                      ->orWhereNull('section_id');
                })
                ->with('subject')
                ->orderByRaw('CASE WHEN section_id IS NOT NULL THEN 0 ELSE 1 END')
                ->get()
                ->unique('subject_id');

            $count = 0;
            foreach ($applicableClassSubjects as $cs) {
                $existing = StudentSubjectAllocation::where('student_academic_record_id', $record->id)
                    ->where('class_subject_id', $cs->id)
                    ->first();

                $category = $cs->subject?->category ?? SubjectCategory::MAIN;

                if (! $existing) {
                    $hasActiveForSubject = StudentSubjectAllocation::where('student_academic_record_id', $record->id)
                        ->where('is_active', true)
                        ->whereHas('classSubject', function ($q) use ($cs) {
                            $q->where('subject_id', $cs->subject_id);
                        })
                        ->exists();

                    if ($hasActiveForSubject) {
                        continue;
                    }

                    $allocation = StudentSubjectAllocation::create([
                        'student_academic_record_id' => $record->id,
                        'class_subject_id' => $cs->id,
                        'allocation_type' => $category,
                        'effective_from' => $record->effective_from ?? now()->toDateString(),
                        'is_active' => true,
                    ]);

                    if ($actorId) {
                        $this->auditService->logDomainAction(
                            $actorId,
                            'allocate_subject',
                            'student_subject_allocations',
                            $allocation->id,
                            null,
                            [
                                'student_academic_record_id' => $record->id,
                                'class_subject_id' => $cs->id,
                                'allocation_type' => $category instanceof SubjectCategory ? $category->value : (string) $category,
                                'is_active' => true,
                            ],
                            "Allocated subject {$cs->subject_name_snapshot} to student placement {$record->id}."
                        );
                    }
                    $count++;
                } elseif (! $existing->is_active) {
                    $existing->update([
                        'is_active' => true,
                        'effective_to' => null,
                    ]);

                    if ($actorId) {
                        $this->auditService->logDomainAction(
                            $actorId,
                            'reactivate_allocation',
                            'student_subject_allocations',
                            $existing->id,
                            ['is_active' => false],
                            ['is_active' => true],
                            "Reactivated subject allocation {$cs->subject_name_snapshot} for placement {$record->id}."
                        );
                    }
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Allocate a newly created or active ClassSubject to all active students in the classroom.
     */
    public function allocateClassSubjectToStudents(ClassSubject $classSubject, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($classSubject, $actorId) {
            $studentRecords = StudentAcademicRecord::query()
                ->where('academic_year_id', $classSubject->academic_year_id)
                ->where('class_id', $classSubject->class_id)
                ->where('status', 'active')
                ->when($classSubject->section_id !== null, function ($q) use ($classSubject) {
                    $q->where('section_id', $classSubject->section_id);
                })
                ->get();

            $count = 0;
            $category = $classSubject->subject?->category ?? SubjectCategory::MAIN;

            foreach ($studentRecords as $sar) {
                $existing = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)
                    ->where('class_subject_id', $classSubject->id)
                    ->first();

                if (! $existing) {
                    $hasActiveForSubject = StudentSubjectAllocation::where('student_academic_record_id', $sar->id)
                        ->where('is_active', true)
                        ->whereHas('classSubject', function ($q) use ($classSubject) {
                            $q->where('subject_id', $classSubject->subject_id);
                        })
                        ->exists();

                    if ($hasActiveForSubject) {
                        continue;
                    }

                    $allocation = StudentSubjectAllocation::create([
                        'student_academic_record_id' => $sar->id,
                        'class_subject_id' => $classSubject->id,
                        'allocation_type' => $category,
                        'effective_from' => $sar->effective_from ?? now()->toDateString(),
                        'is_active' => true,
                    ]);

                    if ($actorId) {
                        $this->auditService->logDomainAction(
                            $actorId,
                            'allocate_subject',
                            'student_subject_allocations',
                            $allocation->id,
                            null,
                            [
                                'student_academic_record_id' => $sar->id,
                                'class_subject_id' => $classSubject->id,
                                'allocation_type' => $category instanceof SubjectCategory ? $category->value : (string) $category,
                                'is_active' => true,
                            ],
                            "Allocated subject {$classSubject->subject_name_snapshot} to student placement {$sar->id}."
                        );
                    }
                    $count++;
                } elseif (! $existing->is_active) {
                    $existing->update([
                        'is_active' => true,
                        'effective_to' => null,
                    ]);

                    if ($actorId) {
                        $this->auditService->logDomainAction(
                            $actorId,
                            'reactivate_allocation',
                            'student_subject_allocations',
                            $existing->id,
                            ['is_active' => false],
                            ['is_active' => true],
                            "Reactivated subject allocation {$classSubject->subject_name_snapshot} for placement {$sar->id}."
                        );
                    }
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Get all active class subjects applicable to a student placement.
     *
     * @return Collection<int, ClassSubject>
     */
    public function getAvailableClassSubjects(StudentAcademicRecord $record): Collection
    {
        return ClassSubject::query()
            ->where('academic_year_id', $record->academic_year_id)
            ->where('class_id', $record->class_id)
            ->where('is_active', true)
            ->where(function ($q) use ($record) {
                $q->where('section_id', $record->section_id)
                  ->orWhereNull('section_id');
            })
            ->with('subject')
            ->orderByRaw('CASE WHEN section_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('subject_name_snapshot', 'asc')
            ->get()
            ->unique('subject_id')
            ->values();
    }

    /**
     * Update/toggle student subject allocations with validation and DEC-017 / BR-021 lockout.
     *
     * @param  array<int, int>  $selectedClassSubjectIds
     * @throws ValidationException
     */
    public function updateStudentAllocations(
        StudentAcademicRecord $record,
        array $selectedClassSubjectIds,
        int $actorId
    ): void {
        DB::transaction(function () use ($record, $selectedClassSubjectIds, $actorId) {
            // 1. Validate that all selected class subjects legitimately belong to this student's classroom context
            $validClassSubjects = $this->getAvailableClassSubjects($record)->keyBy('id');

            foreach ($selectedClassSubjectIds as $csId) {
                if (! $validClassSubjects->has($csId)) {
                    throw ValidationException::withMessages([
                        'class_subject_ids' => ["Class Subject ID {$csId} is not valid for this classroom."],
                    ]);
                }
            }

            // 2. Load existing allocations for this student academic record
            $existingAllocations = StudentSubjectAllocation::where('student_academic_record_id', $record->id)
                ->with('classSubject')
                ->get()
                ->keyBy('class_subject_id');

            $selectedMap = array_flip($selectedClassSubjectIds);

            // 3. Handle deallocations (present in existing, but not in selected)
            foreach ($existingAllocations as $classSubjectId => $allocation) {
                if (! isset($selectedMap[$classSubjectId])) {
                    if ($allocation->is_active) {
                        // Business Invariant DEC-017 / BR-021:
                        // An allocation can be deactivated ONLY if no marks exist for this student in this subject
                        if ($allocation->marks()->exists()) {
                            $subjName = $allocation->classSubject?->subject_name_snapshot ?? "Subject #{$classSubjectId}";
                            throw ValidationException::withMessages([
                                'class_subject_ids' => [
                                    "Cannot deallocate {$subjName}: Marks have already been recorded for this student.",
                                ],
                            ]);
                        }

                        $allocation->update([
                            'is_active' => false,
                            'effective_to' => now()->toDateString(),
                        ]);

                        $this->auditService->logDomainAction(
                            $actorId,
                            'deallocate_subject',
                            'student_subject_allocations',
                            $allocation->id,
                            ['is_active' => true],
                            ['is_active' => false, 'effective_to' => now()->toDateString()],
                            "Deallocated subject {$allocation->classSubject?->subject_name_snapshot} from student placement {$record->id}."
                        );
                    }
                }
            }

            // 4. Handle allocations (present in selected)
            foreach ($selectedClassSubjectIds as $csId) {
                $cs = $validClassSubjects->get($csId);
                $category = $cs->subject?->category ?? SubjectCategory::MAIN;

                if ($existingAllocations->has($csId)) {
                    $allocation = $existingAllocations->get($csId);
                    if (! $allocation->is_active) {
                        $allocation->update([
                            'is_active' => true,
                            'effective_to' => null,
                        ]);

                        $this->auditService->logDomainAction(
                            $actorId,
                            'reactivate_allocation',
                            'student_subject_allocations',
                            $allocation->id,
                            ['is_active' => false],
                            ['is_active' => true],
                            "Reactivated subject allocation {$cs->subject_name_snapshot} for student placement {$record->id}."
                        );
                    }
                } else {
                    $allocation = StudentSubjectAllocation::create([
                        'student_academic_record_id' => $record->id,
                        'class_subject_id' => $csId,
                        'allocation_type' => $category,
                        'effective_from' => $record->effective_from ?? now()->toDateString(),
                        'is_active' => true,
                    ]);

                    $this->auditService->logDomainAction(
                        $actorId,
                        'allocate_subject',
                        'student_subject_allocations',
                        $allocation->id,
                        null,
                        [
                            'student_academic_record_id' => $record->id,
                            'class_subject_id' => $csId,
                            'allocation_type' => $category instanceof SubjectCategory ? $category->value : (string) $category,
                            'is_active' => true,
                        ],
                        "Allocated subject {$cs->subject_name_snapshot} to student placement {$record->id}."
                    );
                }
            }
        });
    }
}
