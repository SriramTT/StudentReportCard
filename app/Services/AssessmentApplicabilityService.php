<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ClassSubject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentApplicabilityService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create Assessment Applicability mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function createApplicability(Assessment $assessment, array $data): AssessmentApplicability
    {
        return DB::transaction(function () use ($assessment, $data) {
            $classSubjectId = (int) $data['class_subject_id'];
            $classSubject = ClassSubject::findOrFail($classSubjectId);

            if ($classSubject->academic_year_id !== $assessment->academic_year_id) {
                throw ValidationException::withMessages([
                    'class_subject_id' => ['The selected class subject belongs to a different academic year than this assessment.'],
                ]);
            }

            $maxMarks = (float) $data['maximum_marks'];
            if ($maxMarks <= 0) {
                throw ValidationException::withMessages([
                    'maximum_marks' => ['Maximum marks must be greater than zero.'],
                ]);
            }

            $applicability = AssessmentApplicability::create([
                'assessment_id' => $assessment->id,
                'class_subject_id' => $classSubjectId,
                'maximum_marks' => $maxMarks,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_ASSESSMENT_APPLICABILITY',
                entityType: 'assessment_applicability',
                entityId: $applicability->id,
                beforeData: null,
                afterData: $applicability->only(['assessment_id', 'class_subject_id', 'maximum_marks', 'is_active']),
                description: "Applied assessment {$assessment->name} to class subject ID {$classSubjectId} with max marks {$maxMarks}"
            );

            return $applicability;
        });
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
}
