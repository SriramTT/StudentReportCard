<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\Section;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentApplicabilityService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create multiple Assessment Applicability mappings in one transaction.
     *
     * @param  array<string, mixed>  $data
     * @return \Illuminate\Support\Collection<int, AssessmentApplicability>
     */
    public function createApplicabilities(Assessment $assessment, array $data): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($assessment, $data) {
            $classSubjectIds = (array) ($data['class_subject_ids'] ?? []);
            if (empty($classSubjectIds) && isset($data['class_subject_id'])) {
                $classSubjectIds = [(int) $data['class_subject_id']];
            }

            if (empty($classSubjectIds)) {
                throw ValidationException::withMessages([
                    'class_subject_ids' => ['Please select at least one class subject.'],
                ]);
            }

            // Check for duplicates in the submitted ID array
            if (count($classSubjectIds) !== count(array_unique($classSubjectIds))) {
                throw ValidationException::withMessages([
                    'class_subject_ids' => ['Duplicate class subjects cannot be selected in the same submission.'],
                ]);
            }

            // Check for already-mapped subjects for this assessment
            $alreadyMapped = AssessmentApplicability::where('assessment_id', $assessment->id)
                ->whereIn('class_subject_id', $classSubjectIds)
                ->with(['classSubject.schoolClass', 'classSubject.section', 'classSubject.subject'])
                ->get();

            if ($alreadyMapped->isNotEmpty()) {
                $names = $alreadyMapped->map(fn ($a) =>
                    ($a->classSubject?->schoolClass?->name ?? 'Class') . ' ' .
                    ($a->classSubject?->section?->name ? "({$a->classSubject->section->name})" : '(All Sections)') . ' - ' .
                    ($a->classSubject?->subject?->name ?? 'Subject')
                )->implode(', ');

                throw ValidationException::withMessages([
                    'class_subject_ids' => ["The following subject(s) are already mapped to this assessment: {$names}"],
                ]);
            }

            $classSubjects = ClassSubject::whereIn('id', $classSubjectIds)
                ->with(['schoolClass', 'section', 'subject'])
                ->get()
                ->keyBy('id');

            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;
            $created = collect();

            foreach ($classSubjectIds as $csId) {
                $classSubject = $classSubjects->get((int) $csId);
                if (! $classSubject) {
                    throw ValidationException::withMessages([
                        'class_subject_ids' => ["Selected class subject ID {$csId} does not exist."],
                    ]);
                }

                if ($classSubject->academic_year_id !== $assessment->academic_year_id) {
                    throw ValidationException::withMessages([
                        'class_subject_ids' => ["Class subject '{$classSubject->subject?->name}' belongs to a different academic year than this assessment."],
                    ]);
                }

                if (! $classSubject->is_active) {
                    throw ValidationException::withMessages([
                        'class_subject_ids' => ["Class subject '{$classSubject->subject?->name}' is inactive and cannot be mapped."],
                    ]);
                }

                // Resolve maximum marks: can be keyed array maximum_marks[id] or scalar
                $maxMarks = null;
                if (isset($data['maximum_marks'][$csId])) {
                    $maxMarks = (float) $data['maximum_marks'][$csId];
                } elseif (isset($data['maximum_marks']) && is_numeric($data['maximum_marks'])) {
                    $maxMarks = (float) $data['maximum_marks'];
                }

                if ($maxMarks === null || $maxMarks <= 0) {
                    throw ValidationException::withMessages([
                        "maximum_marks.{$csId}" => ['Maximum marks must be greater than zero.'],
                    ]);
                }

                if ($maxMarks > 9999.99) {
                    throw ValidationException::withMessages([
                        "maximum_marks.{$csId}" => ['Maximum marks cannot exceed 9999.99.'],
                    ]);
                }

                $applicability = AssessmentApplicability::create([
                    'assessment_id' => $assessment->id,
                    'class_subject_id' => (int) $csId,
                    'maximum_marks' => $maxMarks,
                    'is_active' => $isActive,
                ]);

                $this->auditService->logDomainAction(
                    userId: Auth::id(),
                    action: 'CREATE_ASSESSMENT_APPLICABILITY',
                    entityType: 'assessment_applicability',
                    entityId: $applicability->id,
                    beforeData: null,
                    afterData: $applicability->only(['assessment_id', 'class_subject_id', 'maximum_marks', 'is_active']),
                    description: "Applied assessment {$assessment->name} to class subject ID {$csId} with max marks {$maxMarks}"
                );

                $created->push($applicability);
            }

            return $created;
        });
    }

    /**
     * Create single Assessment Applicability mapping (preserves legacy interface).
     *
     * @param  array<string, mixed>  $data
     */
    public function createApplicability(Assessment $assessment, array $data): AssessmentApplicability
    {
        $collection = $this->createApplicabilities($assessment, $data);

        return $collection->first();
    }


    /**
     * Update Assessment Applicability mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateApplicability(AssessmentApplicability $applicability, array $data): AssessmentApplicability
    {
        return DB::transaction(function () use ($applicability, $data) {
            $beforeData = $applicability->only(['assessment_id', 'class_subject_id', 'maximum_marks', 'is_active']);

            $updateData = [];
            if (isset($data['maximum_marks'])) {
                $maxMarks = (float) $data['maximum_marks'];
                if ($maxMarks <= 0) {
                    throw ValidationException::withMessages([
                        'maximum_marks' => ['Maximum marks must be greater than zero.'],
                    ]);
                }

                $highestNumericMark = $applicability->marks()
                    ->where('result_status', 'numeric')
                    ->whereNotNull('mark_value')
                    ->max('mark_value');

                if ($highestNumericMark !== null && (float) $highestNumericMark > $maxMarks) {
                    throw ValidationException::withMessages([
                        'maximum_marks' => ["Cannot reduce maximum marks to {$maxMarks} because an existing mark of {$highestNumericMark} has already been recorded."],
                    ]);
                }

                $updateData['maximum_marks'] = $maxMarks;
            }

            if (isset($data['is_active'])) {
                $updateData['is_active'] = (bool) $data['is_active'];
            }

            $applicability->update($updateData);

            $afterData = $applicability->fresh()->only(['assessment_id', 'class_subject_id', 'maximum_marks', 'is_active']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_ASSESSMENT_APPLICABILITY',
                entityType: 'assessment_applicability',
                entityId: $applicability->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: "Updated assessment applicability ID {$applicability->id}"
            );

            return $applicability;
        });
    }

    /**
     * Delete Assessment Applicability mapping if and only if no marks exist.
     */
    public function deleteApplicability(AssessmentApplicability $applicability): void
    {
        DB::transaction(function () use ($applicability) {
            if ($applicability->marks()->exists()) {
                throw ValidationException::withMessages([
                    'applicability' => ['Assessment applicability cannot be deleted because student marks have already been recorded against it.'],
                ]);
            }

            $beforeData = $applicability->only(['assessment_id', 'class_subject_id', 'maximum_marks', 'is_active']);
            $id = $applicability->id;
            $assessmentId = $applicability->assessment_id;

            $applicability->delete();

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'DELETE_ASSESSMENT_APPLICABILITY',
                entityType: 'assessment_applicability',
                entityId: $id,
                beforeData: $beforeData,
                afterData: null,
                description: "Deleted assessment applicability ID {$id} for assessment ID {$assessmentId}"
            );
        });
    }

    /**
     * Propagate applicability to a newly created section for all assessments in the academic year,
     * unless the assessment already has persisted marks (Freeze Rule).
     */
    public function propagateApplicabilitiesToNewSection(Section $section): void
    {
        $academicYearId = $section->academic_year_id;
        $classId = $section->class_id;

        // Ensure ClassSubjects exist for the new section based on existing subjects for this class
        $existingClassSubjects = ClassSubject::where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->get();

        if ($existingClassSubjects->isEmpty()) {
            return;
        }

        $distinctSubjectIds = $existingClassSubjects->pluck('subject_id')->unique();
        $sectionClassSubjects = collect();

        foreach ($distinctSubjectIds as $subId) {
            $sampleCs = $existingClassSubjects->firstWhere('subject_id', $subId);
            $newCs = ClassSubject::firstOrCreate([
                'academic_year_id' => $academicYearId,
                'class_id' => $classId,
                'section_id' => $section->id,
                'subject_id' => $subId,
            ], [
                'subject_name_snapshot' => $sampleCs?->subject_name_snapshot ?? ($sampleCs?->subject?->name ?? 'Subject'),
                'is_active' => true,
            ]);
            $sectionClassSubjects->push($newCs);
        }

        // Find all assessments for this academic year
        $assessments = Assessment::where('academic_year_id', $academicYearId)->get();

        foreach ($assessments as $assessment) {
            // Check if assessment is configured for this class
            $classApps = AssessmentApplicability::where('assessment_id', $assessment->id)
                ->whereHas('classSubject', function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                })
                ->with('classSubject')
                ->get();

            if ($classApps->isEmpty()) {
                continue;
            }

            // CRITICAL FREEZE RULE:
            // Once an assessment has at least one persisted marks record,
            // DO NOT retroactively expand its applicability to newly created sections.
            $hasMarks = Mark::query()
                ->whereIn('assessment_applicability_id', $assessment->applicabilities()->pluck('id'))
                ->exists();

            if ($hasMarks) {
                // Historical applicability is frozen
                continue;
            }

            // No marks yet: expand applicability to the new section
            foreach ($sectionClassSubjects as $cs) {
                $sampleApp = $classApps->first(fn ($a) => $a->classSubject?->subject_id === $cs->subject_id);
                if ($sampleApp) {
                    AssessmentApplicability::firstOrCreate([
                        'assessment_id' => $assessment->id,
                        'class_subject_id' => $cs->id,
                    ], [
                        'maximum_marks' => $sampleApp->maximum_marks,
                        'is_active' => $sampleApp->is_active,
                    ]);
                }
            }
        }
    }
}
