<?php

namespace App\Services;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Term;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new Assessment.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAssessment(array $data): Assessment
    {
        return DB::transaction(function () use ($data) {
            $academicYearId = (int) $data['academic_year_id'];
            $termId = ! empty($data['term_id']) ? (int) $data['term_id'] : null;

            if ($termId !== null) {
                $term = Term::findOrFail($termId);
                if ($term->academic_year_id !== $academicYearId) {
                    throw ValidationException::withMessages([
                        'term_id' => ['The selected term does not belong to the selected academic year.'],
                    ]);
                }
            }

            $assessment = Assessment::create([
                'academic_year_id' => $academicYearId,
                'term_id' => $termId,
                'assessment_type_id' => (int) $data['assessment_type_id'],
                'name' => trim($data['name']),
                'assessment_date' => $data['assessment_date'] ?? null,
                'status' => isset($data['status']) ? AssessmentStatus::from($data['status']) : AssessmentStatus::ACTIVE,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_ASSESSMENT',
                entityType: 'assessments',
                entityId: $assessment->id,
                beforeData: null,
                afterData: $assessment->only(['academic_year_id', 'term_id', 'assessment_type_id', 'name', 'status']),
                description: 'Created assessment: ' . $assessment->name
            );

            return $assessment;
        });
    }

    /**
     * Update an existing Assessment.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAssessment(Assessment $assessment, array $data): Assessment
    {
        return DB::transaction(function () use ($assessment, $data) {
            $beforeData = $assessment->only(['academic_year_id', 'term_id', 'assessment_type_id', 'name', 'status']);

            $termId = array_key_exists('term_id', $data)
                ? (! empty($data['term_id']) ? (int) $data['term_id'] : null)
                : $assessment->term_id;

            if ($termId !== null) {
                $term = Term::findOrFail($termId);
                if ($term->academic_year_id !== $assessment->academic_year_id) {
                    throw ValidationException::withMessages([
                        'term_id' => ['The selected term does not belong to the assessment academic year.'],
                    ]);
                }
            }

            $assessment->update([
                'term_id' => $termId,
                'assessment_type_id' => isset($data['assessment_type_id']) ? (int) $data['assessment_type_id'] : $assessment->assessment_type_id,
                'name' => isset($data['name']) ? trim($data['name']) : $assessment->name,
                'assessment_date' => array_key_exists('assessment_date', $data) ? $data['assessment_date'] : $assessment->assessment_date,
                'status' => isset($data['status']) ? AssessmentStatus::from($data['status']) : $assessment->status,
            ]);

            $afterData = $assessment->fresh()->only(['academic_year_id', 'term_id', 'assessment_type_id', 'name', 'status']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_ASSESSMENT',
                entityType: 'assessments',
                entityId: $assessment->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Updated assessment: ' . $assessment->name
            );

            return $assessment;
        });
    }
}
