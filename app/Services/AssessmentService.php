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

            $academicYear = \App\Models\AcademicYear::findOrFail($academicYearId);

            if (! empty($data['assessment_date'])) {
                $startDate = $academicYear->start_date instanceof \Carbon\CarbonInterface ? $academicYear->start_date->format('Y-m-d') : substr((string)$academicYear->start_date, 0, 10);
                $endDate = $academicYear->end_date instanceof \Carbon\CarbonInterface ? $academicYear->end_date->format('Y-m-d') : substr((string)$academicYear->end_date, 0, 10);
                $dateStr = substr((string)$data['assessment_date'], 0, 10);

                if ($dateStr < $startDate || $dateStr > $endDate) {
                    throw ValidationException::withMessages([
                        'assessment_date' => ["The assessment date must fall within the academic year dates ({$startDate} to {$endDate})."],
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

            // Reject structural changes if dependent marks or generated reports exist
            $isStructuralChanged = ($termId !== $assessment->term_id)
                || (isset($data['assessment_type_id']) && (int) $data['assessment_type_id'] !== $assessment->assessment_type_id);

            if ($isStructuralChanged) {
                $hasDependentData = \App\Models\Mark::whereIn(
                    'assessment_applicability_id',
                    $assessment->applicabilities()->pluck('id')
                )->exists() || $assessment->generatedReports()->exists();

                if ($hasDependentData) {
                    throw ValidationException::withMessages([
                        'term_id' => ['Cannot alter assessment term or assessment type because dependent marks or generated reports already exist for this assessment.'],
                    ]);
                }
            }

            $academicYear = $assessment->academicYear;
            $newDate = array_key_exists('assessment_date', $data) ? $data['assessment_date'] : $assessment->assessment_date;
            if (! empty($newDate) && $academicYear) {
                $startDate = $academicYear->start_date instanceof \Carbon\CarbonInterface ? $academicYear->start_date->format('Y-m-d') : substr((string)$academicYear->start_date, 0, 10);
                $endDate = $academicYear->end_date instanceof \Carbon\CarbonInterface ? $academicYear->end_date->format('Y-m-d') : substr((string)$academicYear->end_date, 0, 10);
                $dateStr = substr((string)$newDate, 0, 10);

                if ($dateStr < $startDate || $dateStr > $endDate) {
                    throw ValidationException::withMessages([
                        'assessment_date' => ["The assessment date must fall within the academic year dates ({$startDate} to {$endDate})."],
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

    /**
     * Delete an Assessment if completely unused (no applicabilities, selections, or reports).
     *
     * @throws \DomainException
     */
    public function deleteAssessment(Assessment $assessment): void
    {
        if (
            $assessment->applicabilities()->exists() ||
            $assessment->reportSelections()->exists() ||
            $assessment->generatedReports()->exists()
        ) {
            throw new \DomainException('This assessment cannot be removed because it has configured subject applicabilities, report selections, or generated report cards. Deactivate it instead.');
        }

        DB::transaction(function () use ($assessment) {
            $beforeData = [
                'id' => $assessment->id,
                'academic_year_id' => $assessment->academic_year_id,
                'term_id' => $assessment->term_id,
                'assessment_type_id' => $assessment->assessment_type_id,
                'name' => $assessment->name,
                'status' => $assessment->status->value,
            ];

            $assessment->delete();

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'DELETE_ASSESSMENT',
                entityType: 'assessments',
                entityId: $beforeData['id'],
                beforeData: $beforeData,
                afterData: null,
                description: 'Permanently removed assessment: ' . $beforeData['name']
            );
        });
    }
}
