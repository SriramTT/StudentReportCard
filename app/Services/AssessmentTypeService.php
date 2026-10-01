<?php

namespace App\Services;

use App\Models\AssessmentType;
use Illuminate\Support\Facades\DB;

class AssessmentTypeService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new Assessment Type with transactional audit logging.
     *
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return AssessmentType
     */
    public function createAssessmentType(array $data, int $actorId): AssessmentType
    {
        return DB::transaction(function () use ($data, $actorId) {
            $name = trim((string) $data['name']);
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

            $assessmentType = AssessmentType::create([
                'name' => $name,
                'is_active' => $isActive,
            ]);

            $this->auditService->logDomainAction(
                userId: $actorId,
                action: 'CREATE_ASSESSMENT_TYPE',
                entityType: 'assessment_types',
                entityId: $assessmentType->id,
                beforeData: null,
                afterData: $assessmentType->only(['name', 'is_active']),
                description: "Created assessment type: {$assessmentType->name}"
            );

            return $assessmentType;
        });
    }

    /**
     * Update an existing Assessment Type with transactional audit logging.
     *
     * @param  AssessmentType  $assessmentType
     * @param  array<string, mixed>  $data
     * @param  int  $actorId
     * @return AssessmentType
     */
    public function updateAssessmentType(AssessmentType $assessmentType, array $data, int $actorId): AssessmentType
    {
        return DB::transaction(function () use ($assessmentType, $data, $actorId) {
            $beforeData = $assessmentType->only(['name', 'is_active']);

            $name = trim((string) ($data['name'] ?? $assessmentType->name));
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $assessmentType->is_active;

            $assessmentType->update([
                'name' => $name,
                'is_active' => $isActive,
            ]);

            $afterData = $assessmentType->fresh()->only(['name', 'is_active']);

            $this->auditService->logDomainAction(
                userId: $actorId,
                action: 'UPDATE_ASSESSMENT_TYPE',
                entityType: 'assessment_types',
                entityId: $assessmentType->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: "Updated assessment type: {$assessmentType->name}"
            );

            return $assessmentType;
        });
    }

    /**
     * Delete an Assessment Type if unused by any assessments.
     *
     * @param  AssessmentType  $assessmentType
     * @param  int  $actorId
     * @throws \DomainException
     */
    public function deleteAssessmentType(AssessmentType $assessmentType, int $actorId): void
    {
        if ($assessmentType->assessments()->exists()) {
            throw new \DomainException('This assessment type cannot be removed because it is referenced by existing assessments. Deactivate it instead.');
        }

        DB::transaction(function () use ($assessmentType, $actorId) {
            $beforeData = [
                'id' => $assessmentType->id,
                'name' => $assessmentType->name,
                'is_active' => $assessmentType->is_active,
            ];

            $assessmentType->delete();

            $this->auditService->logDomainAction(
                userId: $actorId,
                action: 'DELETE_ASSESSMENT_TYPE',
                entityType: 'assessment_types',
                entityId: $beforeData['id'],
                beforeData: $beforeData,
                afterData: null,
                description: "Permanently removed assessment type: {$beforeData['name']}"
            );
        });
    }
}
