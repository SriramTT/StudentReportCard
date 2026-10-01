<?php

namespace App\Services;

use App\Enums\StudentPlacementStatus;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public function __construct(
        protected AuditService $auditService,
        protected TeacherAuthorizationService $teacherAuth,
        protected StudentSubjectAllocationService $allocationService
    ) {}

    /**
     * Get paginated students with eagerness on academic records.
     * Contextually scoped for teachers to only their active assigned classes/sections.
     *
     * @param  array<string, mixed>  $filters
     * @param  int  $perPage
     * @param  User|null  $actor
     * @return LengthAwarePaginator
     */
    public function getPaginatedStudents(array $filters = [], int $perPage = 25, ?User $actor = null): LengthAwarePaginator
    {
        $query = Student::query()
            ->with([
                'academicRecords' => function ($q) {
                    $q->orderBy('effective_from', 'desc')
                        ->orderBy('id', 'desc')
                        ->with(['academicYear', 'schoolClass', 'section']);
                },
            ]);

        // Teacher contextual scoping: restrict to union of active assigned classroom contexts
        if ($actor && ($actor->isClassTeacher() || $actor->isSubjectTeacher()) && ! $actor->isAdmin() && ! $actor->isOfficeStaff()) {
            $activeAssignments = $this->teacherAuth->getActiveAssignments($actor);

            if ($activeAssignments->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('academicRecords', function ($q) use ($activeAssignments) {
                    $q->where('status', StudentPlacementStatus::ACTIVE->value)
                      ->where(function ($sub) use ($activeAssignments) {
                          foreach ($activeAssignments as $assignment) {
                              $sub->orWhere(function ($clause) use ($assignment) {
                                  $clause->where('academic_year_id', $assignment->academic_year_id)
                                         ->where('class_id', $assignment->class_id)
                                         ->where('section_id', $assignment->section_id);
                              });
                          }
                      });
                });
            }
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('admission_number', 'ilike', "%{$search}%")
                    ->orWhere('student_name', 'ilike', "%{$search}%");
            });
        }

        $hasAcademicFilter = !empty($filters['academic_year_id']) || !empty($filters['class_id']) || !empty($filters['section_id']);
        $hasStatusFilter = !empty($filters['status']);
        $hasClassroomFilter = !empty($filters['class_id']) || !empty($filters['section_id']);

        if ($hasAcademicFilter || $hasStatusFilter) {
            $query->whereHas('academicRecords', function ($q) use ($filters, $hasAcademicFilter, $hasClassroomFilter) {
                if (!empty($filters['academic_year_id'])) {
                    $q->where('academic_year_id', $filters['academic_year_id']);
                }
                if (!empty($filters['class_id'])) {
                    $q->where('class_id', $filters['class_id']);
                }
                if (!empty($filters['section_id'])) {
                    $q->where('section_id', $filters['section_id']);
                }
                if (!empty($filters['status']) && $filters['status'] !== 'all') {
                    $q->where('status', $filters['status']);
                } elseif (empty($filters['status']) && $hasAcademicFilter) {
                    // Default behavior: when classroom/academic scope is applied without an explicit status,
                    // default to active placements only (excludes historical internal_transfer, withdrawn, transferred_out).
                    $q->where('status', StudentPlacementStatus::ACTIVE->value);
                }

                // A historical placement closed by an internal transfer must not make the student match that classroom
                if ($hasClassroomFilter) {
                    $q->where('status', '!=', StudentPlacementStatus::INTERNAL_TRANSFER->value);
                }
            });
        }


        // Natural alphanumeric sort: compare non-numeric prefix first, then the numeric suffix numerically,
        // then the raw string as a final tiebreaker, then id for deterministic pagination.
        return $query->orderByRaw("
            SUBSTRING(admission_number FROM '^[^0-9]*') ASC,
            COALESCE(NULLIF(REGEXP_REPLACE(admission_number, '[^0-9]', '', 'g'), '')::bigint, 0) ASC,
            admission_number ASC,
            id ASC
        ")->paginate($perPage)->withQueryString();
    }

    /**
     * Load all placement history and associations for a student.
     */
    public function getStudentWithPlacementHistory(Student $student): Student
    {
        return $student->load([
            'academicRecords' => function ($q) {
                $q->orderBy('effective_from', 'desc')
                    ->with([
                        'academicYear',
                        'schoolClass',
                        'section',
                        'subjectAllocations.classSubject.subject',
                        'marks',
                    ]);
            },
        ]);
    }

    /**
     * Create student master and initial academic placement.
     *
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return Student
     */
    public function createStudent(array $data, int $actorId): Student
    {
        return DB::transaction(function () use ($data, $actorId) {
            $admissionNumber = trim((string) $data['admission_number']);
            $studentName = trim((string) $data['student_name']);

            $section = \App\Models\Section::find($data['section_id']);
            if (! $section || (int) $section->class_id !== (int) $data['class_id']) {
                throw new \InvalidArgumentException('The selected section does not belong to the selected class.');
            }

            $student = Student::create([
                'admission_number' => $admissionNumber,
                'student_name' => $studentName,
            ]);

            $record = StudentAcademicRecord::create([
                'student_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'],
                'roll_number' => (int) $data['roll_number'],
                'status' => StudentPlacementStatus::ACTIVE,
                'effective_from' => !empty($data['effective_from']) ? $data['effective_from'] : now()->toDateString(),
            ]);

            $this->allocationService->allocateDefaultSubjectsForPlacement($record, $actorId);

            $this->auditService->logDomainAction(
                $actorId,
                'create',
                'students',
                $student->id,
                null,
                [
                    'admission_number' => $student->admission_number,
                    'student_name' => $student->student_name,
                    'record_id' => $record->id,
                ],
                "Created student {$student->student_name} ({$student->admission_number}) with initial placement."
            );

            return $student;
        });
    }

    /**
     * Update editable student attributes (admission_number is strictly immutable).
     *
     * @param  Student  $student
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return Student
     */
    public function updateStudent(Student $student, array $data, int $actorId): Student
    {
        return DB::transaction(function () use ($student, $data, $actorId) {
            $before = [
                'student_name' => $student->student_name,
            ];

            $student->update([
                'student_name' => trim((string) $data['student_name']),
            ]);

            $after = [
                'student_name' => $student->student_name,
            ];

            $this->auditService->logDomainAction(
                $actorId,
                'update',
                'students',
                $student->id,
                $before,
                $after,
                "Updated student name for admission number {$student->admission_number}."
            );

            return $student;
        });
    }

    /**
     * Delete a student if completely unused (no academic placement records exist).
     *
     * @param  Student  $student
     * @param  int  $actorId
     * @throws \DomainException
     */
    public function deleteStudent(Student $student, int $actorId): void
    {
        if ($student->academicRecords()->exists()) {
            throw new \DomainException('This student cannot be removed because academic placement records exist. Historical student records must be preserved for reports and academic history.');
        }

        DB::transaction(function () use ($student, $actorId) {
            $beforeData = [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'student_name' => $student->student_name,
            ];

            $student->delete();

            $this->auditService->logDomainAction(
                $actorId,
                'DELETE_STUDENT',
                'students',
                $beforeData['id'],
                $beforeData,
                null,
                "Permanently removed unused student {$beforeData['student_name']} ({$beforeData['admission_number']})"
            );
        });
    }
}
