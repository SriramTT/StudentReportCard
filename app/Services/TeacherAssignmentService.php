<?php

namespace App\Services;

use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAssignmentService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Validate academic year date boundaries and account role consistency.
     */
    protected function validateAssignmentDomainRules(
        int $userId,
        int $academicYearId,
        string $assignmentType,
        ?int $subjectId,
        string $effectiveFrom,
        ?string $effectiveTo
    ): void {
        $academicYear = AcademicYear::find($academicYearId);
        if (! $academicYear) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'The selected academic year does not exist.',
            ]);
        }

        $ayStart = $academicYear->start_date?->toDateString();
        $ayEnd = $academicYear->end_date?->toDateString();

        if ($ayStart === null || $ayEnd === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'The academic year does not have valid start and end date boundaries.',
            ]);
        }

        if ($effectiveFrom < $ayStart || $effectiveFrom > $ayEnd) {
            throw ValidationException::withMessages([
                'effective_from' => "Effective From date must fall within the selected Academic Year ({$ayStart} to {$ayEnd}).",
            ]);
        }

        if ($effectiveTo !== null) {
            if ($effectiveTo < $ayStart || $effectiveTo > $ayEnd) {
                throw ValidationException::withMessages([
                    'effective_to' => "Effective To date must fall within the selected Academic Year ({$ayStart} to {$ayEnd}).",
                ]);
            }
            if ($effectiveTo < $effectiveFrom) {
                throw ValidationException::withMessages([
                    'effective_to' => 'Effective To date cannot be earlier than Effective From date.',
                ]);
            }
        }

        $user = User::query()->with('role')->find($userId);
        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user does not exist.',
            ]);
        }

        $roleName = $user->role?->name;
        if ($roleName === 'Class Teacher') {
            if ($assignmentType === TeacherAssignmentType::CLASS_TEACHER->value && $subjectId !== null) {
                throw ValidationException::withMessages([
                    'subject_id' => 'Class Teacher assignment must not specify a subject (it covers all applicable subjects).',
                ]);
            }
            if ($assignmentType === TeacherAssignmentType::SUBJECT_TEACHER->value && $subjectId === null) {
                throw ValidationException::withMessages([
                    'subject_id' => 'Subject is required when creating a Subject Teacher assignment.',
                ]);
            }
        } elseif ($roleName === 'Subject Teacher') {
            if ($assignmentType !== TeacherAssignmentType::SUBJECT_TEACHER->value || $subjectId === null) {
                throw ValidationException::withMessages([
                    'assignment_type' => 'Subject Teacher accounts can only be assigned as Subject Teacher with a specific subject.',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user must have a Subject Teacher or Class Teacher role.',
            ]);
        }
    }

    /**
     * Check if an overlapping active assignment exists.
     *
     * @param  int  $userId
     * @param  int  $academicYearId
     * @param  int  $classId
     * @param  int  $sectionId
     * @param  int|null  $subjectId
     * @param  string  $effectiveFrom
     * @param  string|null  $effectiveTo
     * @param  int|null  $ignoreId
     * @return bool
     */
    public function hasOverlappingAssignment(
        int $userId,
        int $academicYearId,
        int $classId,
        int $sectionId,
        ?int $subjectId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): bool {
        $query = TeacherAssignment::query()
            ->where('user_id', $userId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('is_active', true);

        if ($subjectId === null) {
            $query->whereNull('subject_id');
        } else {
            $query->where('subject_id', $subjectId);
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        // Check date range overlap:
        // (A_start <= B_end) AND (B_start <= A_end)
        // With NULL representing unbounded future/infinity
        $query->where(function ($q) use ($effectiveFrom, $effectiveTo) {
            $q->where(function ($sub) use ($effectiveFrom) {
                $sub->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveFrom);
            });

            if ($effectiveTo !== null) {
                $q->where('effective_from', '<=', $effectiveTo);
            }
        });

        return $query->exists();
    }

    /**
     * Check if teacher already has an active Class Teacher assignment in the academic year.
     */
    public function getActiveOtherClassTeacherAssignment(
        int $userId,
        int $academicYearId,
        ?int $ignoreId = null
    ): ?TeacherAssignment {
        return TeacherAssignment::query()
            ->with(['schoolClass', 'section'])
            ->where('user_id', $userId)
            ->where('academic_year_id', $academicYearId)
            ->where('assignment_type', TeacherAssignmentType::CLASS_TEACHER)
            ->where('is_active', true)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();
    }

    /**
     * Create a new teacher assignment.
     *
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return TeacherAssignment
     */
    public function createAssignment(array $data, int $actorId): TeacherAssignment
    {
        return DB::transaction(function () use ($data, $actorId) {
            $userId = (int) $data['user_id'];
            $academicYearId = (int) $data['academic_year_id'];
            $classId = (int) $data['class_id'];
            $sectionId = (int) $data['section_id'];
            $assignmentType = $data['assignment_type'];
            $subjectId = ($assignmentType === TeacherAssignmentType::SUBJECT_TEACHER->value && ! empty($data['subject_id']))
                ? (int) $data['subject_id']
                : null;
            $effectiveFrom = $data['effective_from'];
            $effectiveTo = ! empty($data['effective_to']) ? $data['effective_to'] : null;
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

            $this->validateAssignmentDomainRules(
                $userId,
                $academicYearId,
                $assignmentType,
                $subjectId,
                $effectiveFrom,
                $effectiveTo
            );

            if ($isActive && $assignmentType === TeacherAssignmentType::CLASS_TEACHER->value) {
                $existingClassTeacher = $this->getActiveOtherClassTeacherAssignment($userId, $academicYearId);
                if ($existingClassTeacher) {
                    $className = $existingClassTeacher->schoolClass?->name ?? 'another class';
                    $sectionName = $existingClassTeacher->section?->name ? " ({$existingClassTeacher->section->name})" : '';
                    throw ValidationException::withMessages([
                        'assignment_type' => "This teacher already has an active Class Teacher assignment for {$className}{$sectionName} in this academic year. A teacher can only be assigned as Class Teacher to one class.",
                    ]);
                }
            }

            if ($isActive && $this->hasOverlappingAssignment(
                $userId,
                $academicYearId,
                $classId,
                $sectionId,
                $subjectId,
                $effectiveFrom,
                $effectiveTo
            )) {
                throw ValidationException::withMessages([
                    'effective_from' => 'An active assignment already exists for this teacher, class, section, and subject scope during the specified effective period.',
                ]);
            }

            $assignment = new TeacherAssignment();
            $assignment->user_id = $userId;
            $assignment->academic_year_id = $academicYearId;
            $assignment->class_id = $classId;
            $assignment->section_id = $sectionId;
            $assignment->subject_id = $subjectId;
            $assignment->assignment_type = TeacherAssignmentType::from($assignmentType);
            $assignment->effective_from = $effectiveFrom;
            $assignment->effective_to = $effectiveTo;
            $assignment->is_active = $isActive;
            $assignment->save();

            $user = User::find($userId);
            $this->auditService->logDomainAction(
                $actorId,
                'TEACHER_ASSIGNMENT_CREATED',
                'teacher_assignments',
                $assignment->id,
                null,
                [
                    'id' => $assignment->id,
                    'user_id' => $assignment->user_id,
                    'username' => $user?->username,
                    'academic_year_id' => $assignment->academic_year_id,
                    'class_id' => $assignment->class_id,
                    'section_id' => $assignment->section_id,
                    'subject_id' => $assignment->subject_id,
                    'assignment_type' => $assignment->assignment_type->value,
                    'effective_from' => $assignment->effective_from?->toDateString(),
                    'effective_to' => $assignment->effective_to?->toDateString(),
                    'is_active' => $assignment->is_active,
                ],
                "Created teacher assignment #{$assignment->id} for user '{$user?->username}'"
            );

            // Handle Dual Assignment: Also assign as Subject Teacher
            $alsoAssignSubject = ! empty($data['also_assign_subject']) && filter_var($data['also_assign_subject'], FILTER_VALIDATE_BOOLEAN);
            if ($alsoAssignSubject && ! empty($data['also_subject_id'])) {
                $dualSubjectId = (int) $data['also_subject_id'];
                $dualAssignmentType = TeacherAssignmentType::SUBJECT_TEACHER->value;

                $this->validateAssignmentDomainRules(
                    $userId,
                    $academicYearId,
                    $dualAssignmentType,
                    $dualSubjectId,
                    $effectiveFrom,
                    $effectiveTo
                );

                if ($isActive && $this->hasOverlappingAssignment(
                    $userId,
                    $academicYearId,
                    $classId,
                    $sectionId,
                    $dualSubjectId,
                    $effectiveFrom,
                    $effectiveTo
                )) {
                    throw ValidationException::withMessages([
                        'also_subject_id' => 'An active subject teacher assignment already exists for this teacher, class, section, and subject.',
                    ]);
                }

                $dualAssignment = new TeacherAssignment();
                $dualAssignment->user_id = $userId;
                $dualAssignment->academic_year_id = $academicYearId;
                $dualAssignment->class_id = $classId;
                $dualAssignment->section_id = $sectionId;
                $dualAssignment->subject_id = $dualSubjectId;
                $dualAssignment->assignment_type = TeacherAssignmentType::SUBJECT_TEACHER;
                $dualAssignment->effective_from = $effectiveFrom;
                $dualAssignment->effective_to = $effectiveTo;
                $dualAssignment->is_active = $isActive;
                $dualAssignment->save();

                $this->auditService->logDomainAction(
                    $actorId,
                    'TEACHER_ASSIGNMENT_CREATED',
                    'teacher_assignments',
                    $dualAssignment->id,
                    null,
                    [
                        'id' => $dualAssignment->id,
                        'user_id' => $dualAssignment->user_id,
                        'username' => $user?->username,
                        'academic_year_id' => $dualAssignment->academic_year_id,
                        'class_id' => $dualAssignment->class_id,
                        'section_id' => $dualAssignment->section_id,
                        'subject_id' => $dualAssignment->subject_id,
                        'assignment_type' => $dualAssignment->assignment_type->value,
                        'effective_from' => $dualAssignment->effective_from?->toDateString(),
                        'effective_to' => $dualAssignment->effective_to?->toDateString(),
                        'is_active' => $dualAssignment->is_active,
                    ],
                    "Created dual subject teacher assignment #{$dualAssignment->id} for user '{$user?->username}'"
                );
            }

            return $assignment;
        });
    }

    /**
     * Update an existing teacher assignment.
     *
     * @param  TeacherAssignment  $assignment
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return TeacherAssignment
     */
    public function updateAssignment(TeacherAssignment $assignment, array $data, int $actorId): TeacherAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $actorId) {
            $beforeData = [
                'id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'academic_year_id' => $assignment->academic_year_id,
                'class_id' => $assignment->class_id,
                'section_id' => $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'assignment_type' => $assignment->assignment_type->value,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
                'is_active' => $assignment->is_active,
            ];

            $userId = (int) $data['user_id'];
            $academicYearId = (int) $data['academic_year_id'];
            $classId = (int) $data['class_id'];
            $sectionId = (int) $data['section_id'];
            $assignmentType = $data['assignment_type'];
            $subjectId = ($assignmentType === TeacherAssignmentType::SUBJECT_TEACHER->value && ! empty($data['subject_id']))
                ? (int) $data['subject_id']
                : null;
            $effectiveFrom = $data['effective_from'];
            $effectiveTo = ! empty($data['effective_to']) ? $data['effective_to'] : null;
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $assignment->is_active;

            $this->validateAssignmentDomainRules(
                $userId,
                $academicYearId,
                $assignmentType,
                $subjectId,
                $effectiveFrom,
                $effectiveTo
            );

            if ($isActive && $assignmentType === TeacherAssignmentType::CLASS_TEACHER->value) {
                $existingClassTeacher = $this->getActiveOtherClassTeacherAssignment($userId, $academicYearId, $assignment->id);
                if ($existingClassTeacher) {
                    $className = $existingClassTeacher->schoolClass?->name ?? 'another class';
                    $sectionName = $existingClassTeacher->section?->name ? " ({$existingClassTeacher->section->name})" : '';
                    throw ValidationException::withMessages([
                        'assignment_type' => "This teacher already has an active Class Teacher assignment for {$className}{$sectionName} in this academic year. A teacher can only be assigned as Class Teacher to one class.",
                    ]);
                }
            }

            if ($isActive && $this->hasOverlappingAssignment(
                $userId,
                $academicYearId,
                $classId,
                $sectionId,
                $subjectId,
                $effectiveFrom,
                $effectiveTo,
                $assignment->id
            )) {
                throw ValidationException::withMessages([
                    'effective_from' => 'An active assignment already exists for this teacher, class, section, and subject scope during the specified effective period.',
                ]);
            }

            $assignment->user_id = $userId;
            $assignment->academic_year_id = $academicYearId;
            $assignment->class_id = $classId;
            $assignment->section_id = $sectionId;
            $assignment->subject_id = $subjectId;
            $assignment->assignment_type = TeacherAssignmentType::from($assignmentType);
            $assignment->effective_from = $effectiveFrom;
            $assignment->effective_to = $effectiveTo;
            $assignment->is_active = $isActive;
            $assignment->save();

            $afterData = [
                'id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'academic_year_id' => $assignment->academic_year_id,
                'class_id' => $assignment->class_id,
                'section_id' => $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'assignment_type' => $assignment->assignment_type->value,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
                'is_active' => $assignment->is_active,
            ];

            $this->auditService->logDomainAction(
                $actorId,
                'TEACHER_ASSIGNMENT_UPDATED',
                'teacher_assignments',
                $assignment->id,
                $beforeData,
                $afterData,
                "Updated teacher assignment #{$assignment->id}"
            );

            return $assignment;
        });
    }

    /**
     * Activate a teacher assignment.
     *
     * @param  TeacherAssignment  $assignment
     * @param  int  $actorId
     * @return TeacherAssignment
     */
    public function activateAssignment(TeacherAssignment $assignment, int $actorId): TeacherAssignment
    {
        return DB::transaction(function () use ($assignment, $actorId) {
            $effectiveFrom = $assignment->effective_from?->toDateString() ?? now()->toDateString();
            $effectiveTo = $assignment->effective_to?->toDateString();

            if ($this->hasOverlappingAssignment(
                $assignment->user_id,
                $assignment->academic_year_id,
                $assignment->class_id,
                $assignment->section_id,
                $assignment->subject_id,
                $effectiveFrom,
                $effectiveTo,
                $assignment->id
            )) {
                throw ValidationException::withMessages([
                    'assignment' => 'Cannot activate assignment: a conflicting active assignment exists for this teacher, class, section, and subject scope during this effective period.',
                ]);
            }

            $beforeStatus = $assignment->is_active;
            $assignment->is_active = true;
            $assignment->save();

            $this->auditService->logDomainAction(
                $actorId,
                'TEACHER_ASSIGNMENT_ACTIVATED',
                'teacher_assignments',
                $assignment->id,
                ['is_active' => $beforeStatus],
                ['is_active' => true],
                "Activated teacher assignment #{$assignment->id}"
            );

            return $assignment;
        });
    }

    /**
     * Deactivate a teacher assignment.
     *
     * @param  TeacherAssignment  $assignment
     * @param  int  $actorId
     * @return TeacherAssignment
     */
    public function deactivateAssignment(TeacherAssignment $assignment, int $actorId): TeacherAssignment
    {
        return DB::transaction(function () use ($assignment, $actorId) {
            $beforeStatus = $assignment->is_active;
            $assignment->is_active = false;
            $assignment->save();

            $this->auditService->logDomainAction(
                $actorId,
                'TEACHER_ASSIGNMENT_DEACTIVATED',
                'teacher_assignments',
                $assignment->id,
                ['is_active' => $beforeStatus],
                ['is_active' => false],
                "Deactivated teacher assignment #{$assignment->id}"
            );

            return $assignment;
        });
    }

    /**
     * Delete a teacher assignment if completely unused (no historical marks or attendance recorded).
     *
     * @param  TeacherAssignment  $assignment
     * @param  int  $actorId
     * @throws \DomainException
     */
    public function deleteAssignment(TeacherAssignment $assignment, int $actorId): void
    {
        $hasMarks = \App\Models\Mark::where(function ($q) use ($assignment) {
            $q->where('entered_by_user_id', $assignment->user_id)
              ->orWhere('updated_by_user_id', $assignment->user_id);
        })->whereHas('studentAcademicRecord', function ($q) use ($assignment) {
            $q->where('academic_year_id', $assignment->academic_year_id)
              ->where('class_id', $assignment->class_id)
              ->where('section_id', $assignment->section_id);
        })->exists();

        $hasAttendance = \App\Models\Attendance::where(function ($q) use ($assignment) {
            $q->where('entered_by_user_id', $assignment->user_id)
              ->orWhere('updated_by_user_id', $assignment->user_id);
        })->whereHas('studentAcademicRecord', function ($q) use ($assignment) {
            $q->where('academic_year_id', $assignment->academic_year_id)
              ->where('class_id', $assignment->class_id)
              ->where('section_id', $assignment->section_id);
        })->exists();

        if ($hasMarks || $hasAttendance) {
            throw new \DomainException('This assignment cannot be removed because this teacher has already recorded marks or attendance for this class. Deactivate the assignment instead to preserve historical attribution.');
        }

        DB::transaction(function () use ($assignment, $actorId) {
            $beforeData = [
                'id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'academic_year_id' => $assignment->academic_year_id,
                'class_id' => $assignment->class_id,
                'section_id' => $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'assignment_type' => $assignment->assignment_type->value,
                'is_active' => $assignment->is_active,
            ];

            $assignment->delete();

            $this->auditService->logDomainAction(
                $actorId,
                'DELETE_TEACHER_ASSIGNMENT',
                'teacher_assignments',
                $beforeData['id'],
                $beforeData,
                null,
                "Permanently removed teacher assignment #{$beforeData['id']}"
            );
        });
    }
}
