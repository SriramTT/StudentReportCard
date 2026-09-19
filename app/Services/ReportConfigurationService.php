<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Models\Assessment;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportConfigurationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new Report Configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function createReportConfiguration(array $data): ReportConfiguration
    {
        return DB::transaction(function () use ($data) {
            $academicYearId = ! empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;

            $config = ReportConfiguration::create([
                'academic_year_id' => $academicYearId,
                'name' => trim($data['name']),
                'report_type' => ReportType::from($data['report_type']),
                'configuration_data' => $data['configuration_data'] ?? [],
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_REPORT_CONFIGURATION',
                entityType: 'report_configurations',
                entityId: $config->id,
                beforeData: null,
                afterData: $config->only(['academic_year_id', 'name', 'report_type', 'is_active']),
                description: 'Created report configuration: ' . $config->name
            );

            return $config;
        });
    }

    /**
     * Update an existing Report Configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReportConfiguration(ReportConfiguration $config, array $data): ReportConfiguration
    {
        return DB::transaction(function () use ($config, $data) {
            $beforeData = $config->only(['academic_year_id', 'name', 'report_type', 'configuration_data', 'is_active']);

            $config->update([
                'name' => isset($data['name']) ? trim($data['name']) : $config->name,
                'report_type' => isset($data['report_type']) ? ReportType::from($data['report_type']) : $config->report_type,
                'configuration_data' => array_key_exists('configuration_data', $data) ? $data['configuration_data'] : $config->configuration_data,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $config->is_active,
            ]);

            $afterData = $config->fresh()->only(['academic_year_id', 'name', 'report_type', 'configuration_data', 'is_active']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_REPORT_CONFIGURATION',
                entityType: 'report_configurations',
                entityId: $config->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Updated report configuration: ' . $config->name
            );

            return $config;
        });
    }

    /**
     * Add an Assessment display selection to a Report Configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function addAssessmentSelection(ReportConfiguration $config, array $data): ReportAssessmentSelection
    {
        return DB::transaction(function () use ($config, $data) {
            $assessmentId = (int) $data['assessment_id'];
            $assessment = Assessment::findOrFail($assessmentId);

            if ($config->academic_year_id !== null && $assessment->academic_year_id !== $config->academic_year_id) {
                throw ValidationException::withMessages([
                    'assessment_id' => ['The selected assessment belongs to a different academic year than this report configuration.'],
                ]);
            }

            $selection = ReportAssessmentSelection::create([
                'report_configuration_id' => $config->id,
                'assessment_id' => $assessmentId,
                'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : 1,
                'is_displayed' => isset($data['is_displayed']) ? (bool) $data['is_displayed'] : true,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_REPORT_ASSESSMENT_SELECTION',
                entityType: 'report_assessment_selections',
                entityId: $selection->id,
                beforeData: null,
                afterData: $selection->only(['report_configuration_id', 'assessment_id', 'display_order', 'is_displayed']),
                description: "Added assessment ID {$assessmentId} to report configuration ID {$config->id}"
            );

            return $selection;
        });
    }

    /**
     * Update an existing Report Assessment Selection.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAssessmentSelection(ReportAssessmentSelection $selection, array $data): ReportAssessmentSelection
    {
        return DB::transaction(function () use ($selection, $data) {
            $beforeData = $selection->only(['report_configuration_id', 'assessment_id', 'display_order', 'is_displayed']);

            $updateData = [];
            if (isset($data['display_order'])) {
                $updateData['display_order'] = (int) $data['display_order'];
            }
            if (isset($data['is_displayed'])) {
                $updateData['is_displayed'] = (bool) $data['is_displayed'];
            }

            $selection->update($updateData);

            $afterData = $selection->fresh()->only(['report_configuration_id', 'assessment_id', 'display_order', 'is_displayed']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_REPORT_ASSESSMENT_SELECTION',
                entityType: 'report_assessment_selections',
                entityId: $selection->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: "Updated report assessment selection ID {$selection->id}"
            );

            return $selection;
        });
    }
}
