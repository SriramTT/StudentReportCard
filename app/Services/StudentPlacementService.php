<?php

namespace App\Services;

use App\Enums\StudentPlacementStatus;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentPlacementService
{
    public function __construct(
        protected AuditService $auditService,
        protected StudentSubjectAllocationService $allocationService
    ) {}

    /**
     * Transfer student to a new academic placement while preserving all history.
     *
     * @param  Student  $student
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return StudentAcademicRecord
     */
    public function transferStudent(Student $student, array $data, int $actorId): StudentAcademicRecord
    {
        return DB::transaction(function () use ($student, $data, $actorId) {
            $effectiveDate = $data['effective_date'];

            $section = \App\Models\Section::find($data['section_id']);
            if (! $section || (int) $section->class_id !== (int) $data['class_id']) {
                throw new InvalidArgumentException('The selected section does not belong to the selected class.');
            }

            // Find current active placement
            $activeRecord = StudentAcademicRecord::where('student_id', $student->id)
                ->where('status', StudentPlacementStatus::ACTIVE)
                ->latest('effective_from')
                ->first();

            if ($activeRecord) {
                if (
                    $activeRecord->academic_year_id == $data['academic_year_id'] &&
                    $activeRecord->class_id == $data['class_id'] &&
                    $activeRecord->section_id == $data['section_id']
                ) {
                    throw new InvalidArgumentException('Student is already placed in this class and section.');
                }

                $activeRecord->update([
                    'status' => StudentPlacementStatus::INTERNAL_TRANSFER,
                    'effective_to' => $effectiveDate,
                ]);

                $this->auditService->logDomainAction(
                    $actorId,
                    'close_placement',
                    'student_academic_records',
                    $activeRecord->id,
                    ['status' => StudentPlacementStatus::ACTIVE->value],
                    ['status' => StudentPlacementStatus::INTERNAL_TRANSFER->value, 'effective_to' => $effectiveDate],
                    "Closed active placement for student {$student->admission_number} due to internal transfer."
                );
            }

            $newRecord = StudentAcademicRecord::create([
                'student_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'],
                'roll_number' => (int) $data['roll_number'],
                'status' => StudentPlacementStatus::ACTIVE,
                'effective_from' => $effectiveDate,
            ]);

            $this->allocationService->allocateDefaultSubjectsForPlacement($newRecord, $actorId);

            $this->auditService->logDomainAction(
                $actorId,
                'transfer',
                'student_academic_records',
                $newRecord->id,
                $activeRecord ? ['previous_record_id' => $activeRecord->id] : null,
                [
                    'record_id' => $newRecord->id,
                    'academic_year_id' => $newRecord->academic_year_id,
                    'class_id' => $newRecord->class_id,
                    'section_id' => $newRecord->section_id,
                    'roll_number' => $newRecord->roll_number,
                ],
                "Created new placement for student {$student->admission_number} in Class {$newRecord->class_id} Section {$newRecord->section_id}."
            );

            return $newRecord;
        });
    }
}
