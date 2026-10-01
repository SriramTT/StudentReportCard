<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\StudentPlacementStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentAcademicRecord;
use App\Models\StudentSubjectAllocation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkService
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth,
        protected AuditService $auditService
    ) {}

    /**
     * Resolve mark entry context and cascading dropdown options based on live user authorization.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function loadMarkContext(User $user, array $filters = []): array
    {
        $today = now()->toDateString();
        $isTeacher = $user->isClassTeacher() || $user->isSubjectTeacher();
        $isAdminOrOffice = $user->isAdmin() || $user->isOfficeStaff();

        // 1. Academic Years
        $academicYears = AcademicYear::query()->orderBy('start_date', 'desc')->get();
        $selectedYearId = isset($filters['academic_year_id']) && $filters['academic_year_id'] !== ''
            ? (int) $filters['academic_year_id']
            : ($academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);

        $selectedYear = $academicYears->firstWhere('id', $selectedYearId);

        // Fetch active assignments for teacher in this year
        $activeAssignments = $isTeacher && $selectedYearId
            ? $this->teacherAuth->getActiveAssignments($user, $selectedYearId)
            : collect();

        // 2. Classes
        if ($isAdminOrOffice) {
            $availableClasses = SchoolClass::getNaturallySorted(true);
        } else {
            $classIds = $activeAssignments->pluck('class_id')->unique()->filter()->values();
            $availableClasses = SchoolClass::getNaturallySorted(true, $classIds->all());
        }

        $selectedClassId = isset($filters['class_id']) && $filters['class_id'] !== ''
            ? (int) $filters['class_id']
            : null;

        if ($selectedClassId && ! $availableClasses->contains('id', $selectedClassId)) {
            $selectedClassId = null;
        }

        // 3. Sections
        $availableSections = collect();
        $selectedSectionId = isset($filters['section_id']) && $filters['section_id'] !== ''
            ? (int) $filters['section_id']
            : null;

        if ($selectedClassId) {
            if ($isAdminOrOffice) {
                $availableSections = Section::query()
                    ->where('academic_year_id', $selectedYearId)
                    ->where('class_id', $selectedClassId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            } else {
                $sectionIds = $activeAssignments->where('class_id', $selectedClassId)->pluck('section_id')->unique()->filter()->values();
                $availableSections = Section::query()
                    ->where('academic_year_id', $selectedYearId)
                    ->where('class_id', $selectedClassId)
                    ->whereIn('id', $sectionIds)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }

            if ($selectedSectionId && ! $availableSections->contains('id', $selectedSectionId)) {
                $selectedSectionId = null;
            }
        } else {
            $selectedSectionId = null;
        }

        // 4. Subjects
        $availableSubjects = collect();
        $selectedSubjectId = isset($filters['subject_id']) && $filters['subject_id'] !== ''
            ? (int) $filters['subject_id']
            : null;

        $targetClassSubject = null;

        if ($selectedYearId && $selectedClassId && $selectedSectionId) {
            // Get all active class_subjects mapped for this classroom in this year (section-specific or class-wide)
            $classSubjectsQuery = ClassSubject::query()
                ->with(['subject', 'schoolClass', 'section'])
                ->where('academic_year_id', $selectedYearId)
                ->where('class_id', $selectedClassId)
                ->where(function ($q) use ($selectedSectionId) {
                    $q->where('section_id', $selectedSectionId)
                      ->orWhereNull('section_id');
                })
                ->where('is_active', true);

            if ($isTeacher) {
                // Determine if teacher is Class Teacher for this exact classroom
                $isClassTeacherHere = $activeAssignments->contains(function ($a) use ($selectedClassId, $selectedSectionId) {
                    return $a->assignment_type === TeacherAssignmentType::CLASS_TEACHER
                        && (int) $a->class_id === (int) $selectedClassId
                        && (int) $a->section_id === (int) $selectedSectionId;
                });

                if (! $isClassTeacherHere) {
                    // Subject Teacher scope: filter to assigned subject_ids
                    $assignedSubjectIds = $activeAssignments
                        ->where('class_id', $selectedClassId)
                        ->where('section_id', $selectedSectionId)
                        ->where('assignment_type', TeacherAssignmentType::SUBJECT_TEACHER)
                        ->pluck('subject_id')
                        ->filter()
                        ->values();

                    $classSubjectsQuery->whereIn('subject_id', $assignedSubjectIds);
                }
            }

            $classSubjects = $classSubjectsQuery->get();
            $availableSubjects = $classSubjects->map(fn($cs) => $cs->subject)->filter()->unique('id')->values();

            if ($selectedSubjectId && ! $availableSubjects->contains('id', $selectedSubjectId)) {
                $selectedSubjectId = null;
            }

            if ($selectedSubjectId) {
                // Prefer section-specific mapping over class-wide mapping
                $targetClassSubject = $classSubjects->first(fn($cs) => (int)$cs->subject_id === (int)$selectedSubjectId && (int)$cs->section_id === (int)$selectedSectionId)
                    ?? $classSubjects->first(fn($cs) => (int)$cs->subject_id === (int)$selectedSubjectId);
            }
        } else {
            $selectedSubjectId = null;
        }

        // 5. Assessments
        $availableAssessments = collect();
        $selectedAssessmentId = isset($filters['assessment_id']) && $filters['assessment_id'] !== ''
            ? (int) $filters['assessment_id']
            : null;

        $targetApplicability = null;

        if ($selectedYearId && $targetClassSubject && !empty($classSubjects)) {
            $matchedClassSubjectIds = $classSubjects
                ->where('subject_id', $targetClassSubject->subject_id)
                ->pluck('id')
                ->values();

            $applicabilities = AssessmentApplicability::query()
                ->with(['assessment.assessmentType', 'assessment.term'])
                ->whereIn('class_subject_id', $matchedClassSubjectIds)
                ->where('is_active', true)
                ->whereHas('assessment', function ($q) use ($selectedYearId) {
                    $q->where('academic_year_id', $selectedYearId)
                      ->where('status', AssessmentStatus::ACTIVE);
                })
                ->get();

            $availableAssessments = $applicabilities->map(function ($app) {
                $assessment = $app->assessment;
                $assessment->applicability_instance = $app;
                return $assessment;
            })->filter()->unique('id')->values();

            if ($selectedAssessmentId && ! $availableAssessments->contains('id', $selectedAssessmentId)) {
                $selectedAssessmentId = null;
            }

            if ($selectedAssessmentId) {
                $selectedAssessment = $availableAssessments->firstWhere('id', $selectedAssessmentId);
                $targetApplicability = $selectedAssessment?->applicability_instance;
            }
        } else {
            $selectedAssessmentId = null;
        }

        // 6. Authorization & Read-Only Evaluation
        $isReadOnly = false;
        $canEdit = false;

        if ($selectedYear && $selectedClassId && $selectedSectionId && $selectedSubjectId) {
            $canEdit = $this->teacherAuth->userCanEditMarksInContext(
                $user,
                $selectedYearId,
                $selectedClassId,
                $selectedSectionId,
                $selectedSubjectId
            );

            if (! $canEdit) {
                $isReadOnly = true;
            }
        }

        // 7. Resolve model instances for selected IDs
        $selectedClass = $selectedClassId ? $availableClasses->firstWhere('id', $selectedClassId) : null;
        $selectedSection = $selectedSectionId ? $availableSections->firstWhere('id', $selectedSectionId) : null;
        $selectedSubject = $selectedSubjectId ? $availableSubjects->firstWhere('id', $selectedSubjectId) : null;
        $selectedAssessment = $selectedAssessmentId ? $availableAssessments->firstWhere('id', $selectedAssessmentId) : null;

        return [
            'academicYears' => $academicYears,
            'selectedYearId' => $selectedYearId,
            'selectedYear' => $selectedYear,
            'selectedAcademicYear' => $selectedYear,
            'availableClasses' => $availableClasses,
            'classes' => $availableClasses,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'availableSections' => $availableSections,
            'sections' => $availableSections,
            'selectedSectionId' => $selectedSectionId,
            'selectedSection' => $selectedSection,
            'availableSubjects' => $availableSubjects,
            'subjects' => $availableSubjects,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedSubject' => $selectedSubject,
            'targetClassSubject' => $targetClassSubject,
            'availableAssessments' => $availableAssessments,
            'assessments' => $availableAssessments,
            'selectedAssessmentId' => $selectedAssessmentId,
            'selectedAssessment' => $selectedAssessment,
            'targetApplicability' => $targetApplicability,
            'maximumMarks' => $targetApplicability?->maximum_marks,
            'canEdit' => $canEdit,
            'isReadOnly' => $isReadOnly,
        ];
    }

    /**
     * Resolve the deterministic student roster for the authorized mark-entry context.
     *
     * @param  int  $academicYearId
     * @param  int  $classId
     * @param  int  $sectionId
     * @param  int  $classSubjectId
     * @param  int  $applicabilityId
     * @return Collection<int, object>
     */
    public function getMarkEntryRoster(
        int $academicYearId,
        int $classId,
        int $sectionId,
        int $classSubjectId,
        int $applicabilityId
    ): Collection {
        $targetCs = ClassSubject::find($classSubjectId);
        $subjectId = $targetCs?->subject_id;

        $applicableClassSubjectIds = [$classSubjectId];
        if ($subjectId) {
            $applicableClassSubjectIds = ClassSubject::query()
                ->where('academic_year_id', $academicYearId)
                ->where('class_id', $classId)
                ->where(function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId)->orWhereNull('section_id');
                })
                ->where('subject_id', $subjectId)
                ->pluck('id')
                ->all();
        }

        // Query active student placements in the exact classroom context
        $records = StudentAcademicRecord::query()
            ->with([
                'student',
                'subjectAllocations' => fn($q) => $q->whereIn('class_subject_id', $applicableClassSubjectIds)->where('is_active', true),
                'marks' => fn($q) => $q->where('assessment_applicability_id', $applicabilityId),
            ])
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('status', StudentPlacementStatus::ACTIVE)
            ->whereHas('subjectAllocations', fn($q) => $q->whereIn('class_subject_id', $applicableClassSubjectIds)->where('is_active', true))
            ->orderBy('roll_number', 'asc')
            ->get();

        return $records->map(function ($sar) {
            $allocation = $sar->subjectAllocations->first();
            $mark = $sar->marks->first();

            return (object) [
                'student_academic_record_id' => $sar->id,
                'student_subject_allocation_id' => $allocation?->id,
                'student_id' => $sar->student_id,
                'admission_number' => $sar->student?->admission_number ?? '—',
                'student_name' => $sar->student?->student_name ?? '—',
                'roll_number' => $sar->roll_number,
                'mark_id' => $mark?->id,
                'result_status' => $mark?->result_status?->value ?? 'blank',
                'mark_value' => $mark?->mark_value,
                'formatted_value' => $mark && $mark->result_status === MarkResultStatus::NUMERIC && $mark->mark_value !== null
                    ? number_format((float) $mark->mark_value, 2, '.', '')
                    : ($mark && $mark->result_status === MarkResultStatus::ABSENT ? 'A' : ''),
            ];
        });
    }

    /**
     * Transactionally save a batch of entered/edited marks with atomic validation and immutable audit logs.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     *
     * @throws ValidationException
     */
    public function saveMarks(User $user, array $data): array
    {
        $academicYearId = (int) $data['academic_year_id'];
        $classId = (int) $data['class_id'];
        $sectionId = (int) $data['section_id'];
        $subjectId = (int) $data['subject_id'];
        $assessmentId = (int) $data['assessment_id'];

        // 1. Authorization Gateway
        if (! $this->teacherAuth->userCanEditMarksInContext($user, $academicYearId, $classId, $sectionId, $subjectId)) {
            throw ValidationException::withMessages([
                'authorization' => ['You are not authorized to enter or edit marks in this classroom and subject context.'],
            ]);
        }

        // 2. Validate ClassSubject (supporting section-specific or class-wide)
        $classSubject = ClassSubject::query()
            ->with(['schoolClass', 'section', 'subject'])
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where(function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId)->orWhereNull('section_id');
            })
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->orderByRaw('section_id IS NOT NULL DESC')
            ->first();

        if (! $classSubject) {
            throw ValidationException::withMessages([
                'subject_id' => ['The selected subject is not configured or active for this classroom.'],
            ]);
        }

        // 3. Validate Assessment & Applicability
        $assessment = Assessment::query()
            ->where('id', $assessmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', AssessmentStatus::ACTIVE)
            ->first();

        if (! $assessment) {
            throw ValidationException::withMessages([
                'assessment_id' => ['The assessment is invalid, inactive, or belongs to a different academic year.'],
            ]);
        }

        $applicableClassSubjectIds = ClassSubject::query()
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where(function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId)->orWhereNull('section_id');
            })
            ->where('subject_id', $subjectId)
            ->pluck('id');

        $applicability = AssessmentApplicability::query()
            ->where('assessment_id', $assessment->id)
            ->whereIn('class_subject_id', $applicableClassSubjectIds)
            ->where('is_active', true)
            ->first();

        if (! $applicability) {
            throw ValidationException::withMessages([
                'assessment_id' => ['Assessment applicability is not configured or active for this subject and classroom.'],
            ]);
        }

        $authoritativeMaxMarks = (float) $applicability->maximum_marks;
        $submittedMarks = $data['marks'] ?? [];

        // 4. Begin Database Transaction
        return DB::transaction(function () use (
            $user,
            $academicYearId,
            $classId,
            $sectionId,
            $subjectId,
            $classSubject,
            $assessment,
            $applicability,
            $applicableClassSubjectIds,
            $authoritativeMaxMarks,
            $submittedMarks
        ) {
            $savedCount = 0;
            $unchangedCount = 0;

            foreach ($submittedMarks as $index => $item) {
                $sarId = (int) $item['student_academic_record_id'];
                $resultStatusStr = (string) $item['result_status'];
                $markValueInput = array_key_exists('mark_value', $item) && $item['mark_value'] !== null && $item['mark_value'] !== ''
                    ? (float) $item['mark_value']
                    : null;

                // A. Validate StudentAcademicRecord IDOR
                $sar = StudentAcademicRecord::query()
                    ->with('student')
                    ->where('id', $sarId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->where('section_id', $sectionId)
                    ->where('status', StudentPlacementStatus::ACTIVE)
                    ->first();

                if (! $sar) {
                    throw ValidationException::withMessages([
                        "marks.{$index}.student_academic_record_id" => ["Student placement record {$sarId} is invalid or does not belong to this active classroom."],
                    ]);
                }

                // B. Validate StudentSubjectAllocation IDOR
                $allocation = StudentSubjectAllocation::query()
                    ->where('student_academic_record_id', $sar->id)
                    ->whereIn('class_subject_id', $applicableClassSubjectIds)
                    ->where('is_active', true)
                    ->first();

                if (! $allocation) {
                    throw ValidationException::withMessages([
                        "marks.{$index}.student_subject_allocation_id" => ["Student {$sar->student?->admission_number} is not actively allocated to {$classSubject->subject_name_snapshot}."],
                    ]);
                }

                // Determine and validate mark value & semantic status
                $rawMarkValue = $item['mark_value'] ?? null;
                $numericMarkValue = null;

                if ($resultStatusStr === 'numeric') {
                    if ($rawMarkValue === null || $rawMarkValue === '') {
                        throw ValidationException::withMessages([
                            "marks.{$index}.mark_value" => ["Mark value is required when result status is numeric for student {$sar->student?->admission_number}."],
                        ]);
                    }

                    if (! is_numeric($rawMarkValue)) {
                        throw ValidationException::withMessages([
                            "marks.{$index}.mark_value" => ["Mark must be a valid number for student {$sar->student?->admission_number}."],
                        ]);
                    }

                    $parsedVal = (float) $rawMarkValue;

                    if ($parsedVal < 0) {
                        throw ValidationException::withMessages([
                            "marks.{$index}.mark_value" => ["Mark cannot be negative for student {$sar->student?->admission_number}."],
                        ]);
                    }

                    if ($parsedVal > $authoritativeMaxMarks) {
                        throw ValidationException::withMessages([
                            "marks.{$index}.mark_value" => ["Mark ({$parsedVal}) cannot exceed maximum marks ({$authoritativeMaxMarks}) for student {$sar->student?->admission_number}."],
                        ]);
                    }

                    $numericMarkValue = number_format($parsedVal, 2, '.', '');
                } elseif ($resultStatusStr === 'absent' || $resultStatusStr === 'blank') {
                    $numericMarkValue = null;
                } else {
                    throw ValidationException::withMessages([
                        "marks.{$index}.result_status" => ["Invalid result status '{$resultStatusStr}'."],
                    ]);
                }

                // Find existing mark
                $existingMark = Mark::query()
                    ->where('student_subject_allocation_id', $allocation->id)
                    ->where('assessment_applicability_id', $applicability->id)
                    ->first();

                if ($existingMark) {
                    $prevStatus = $existingMark->result_status->value;
                    $prevValue = $existingMark->mark_value !== null ? number_format((float) $existingMark->mark_value, 2, '.', '') : null;

                    // Detect no-op change
                    if ($prevStatus === $resultStatusStr && $prevValue === $numericMarkValue) {
                        $unchangedCount++;
                        continue;
                    }

                    $beforeData = [
                        'student_academic_record_id' => $existingMark->student_academic_record_id,
                        'student_subject_allocation_id' => $existingMark->student_subject_allocation_id,
                        'assessment_applicability_id' => $existingMark->assessment_applicability_id,
                        'mark_value' => $prevValue,
                        'result_status' => $prevStatus,
                    ];

                    $existingMark->update([
                        'mark_value' => $numericMarkValue,
                        'result_status' => $resultStatusStr,
                        'updated_by_user_id' => $user->id,
                    ]);

                    $afterData = [
                        'student_academic_record_id' => $existingMark->student_academic_record_id,
                        'student_subject_allocation_id' => $existingMark->student_subject_allocation_id,
                        'assessment_applicability_id' => $existingMark->assessment_applicability_id,
                        'mark_value' => $numericMarkValue,
                        'result_status' => $resultStatusStr,
                    ];

                    $actionType = $resultStatusStr === 'blank' ? 'CLEAR_MARK' : 'UPDATE_MARK';

                    $this->auditService->logDomainAction(
                        userId: $user->id,
                        action: $actionType,
                        entityType: 'mark',
                        entityId: $existingMark->id,
                        beforeData: $beforeData,
                        afterData: $afterData,
                        description: "Updated mark for student {$sar->student?->admission_number} in {$classSubject->subject_name_snapshot} ({$assessment->name}): [{$prevStatus}, {$prevValue}] -> [{$resultStatusStr}, {$numericMarkValue}]"
                    );

                    $savedCount++;
                } else {
                    // If no existing record and user entered 'blank', do not persist an empty row or false audit
                    if ($resultStatusStr === 'blank') {
                        $unchangedCount++;
                        continue;
                    }

                    $newMark = Mark::create([
                        'student_academic_record_id' => $sar->id,
                        'student_subject_allocation_id' => $allocation->id,
                        'assessment_applicability_id' => $applicability->id,
                        'mark_value' => $numericMarkValue,
                        'result_status' => $resultStatusStr,
                        'entered_by_user_id' => $user->id,
                        'updated_by_user_id' => $user->id,
                    ]);

                    $afterData = [
                        'student_academic_record_id' => $newMark->student_academic_record_id,
                        'student_subject_allocation_id' => $newMark->student_subject_allocation_id,
                        'assessment_applicability_id' => $newMark->assessment_applicability_id,
                        'mark_value' => $numericMarkValue,
                        'result_status' => $resultStatusStr,
                    ];

                    $this->auditService->logDomainAction(
                        userId: $user->id,
                        action: 'CREATE_MARK',
                        entityType: 'mark',
                        entityId: $newMark->id,
                        beforeData: null,
                        afterData: $afterData,
                        description: "Recorded mark for student {$sar->student?->admission_number} in {$classSubject->subject_name_snapshot} ({$assessment->name}): [{$resultStatusStr}, {$numericMarkValue}]"
                    );

                    $savedCount++;
                }
            }

            return [
                'saved' => $savedCount,
                'unchanged' => $unchangedCount,
            ];
        });
    }
}
