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
     * Resolve the single current academic year.
     *
     * @throws ValidationException
     */
    public function resolveCurrentAcademicYear(): \App\Models\AcademicYear
    {
        $currentYears = \App\Models\AcademicYear::where('is_current', true)->get();

        if ($currentYears->isEmpty()) {
            throw ValidationException::withMessages([
                'academic_year' => ['No current academic year is configured in the system. Exactly one academic year must be designated as current before creating report configurations.'],
            ]);
        }

        if ($currentYears->count() > 1) {
            $names = $currentYears->pluck('name')->join(', ');
            throw ValidationException::withMessages([
                'academic_year' => ["Multiple current academic years detected ({$names}). Configuration error: exactly one academic year must be marked as current."],
            ]);
        }

        return $currentYears->first();
    }

    /**
     * Normalize user-facing report type string into internal ReportType enum and subtype.
     *
     * @return array{0: ReportType, 1: ?string}
     */
    public function normalizeReportTypeAndSubtype(string|ReportType $type): array
    {
        $val = $type instanceof ReportType ? $type->value : (string) $type;

        return match ($val) {
            'exam_midterm' => [ReportType::EXAM, 'mid_term'],
            'exam_custom' => [ReportType::EXAM, 'custom'],
            'exam' => [ReportType::EXAM, 'custom'],
            'term' => [ReportType::TERM, null],
            'final' => [ReportType::FINAL, null],
            default => throw new \InvalidArgumentException("Invalid report type: {$val}"),
        };
    }

    /**
     * Create a new Report Configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function createReportConfiguration(array $data): ReportConfiguration
    {
        return DB::transaction(function () use ($data) {
            $currentYear = $this->resolveCurrentAcademicYear();
            [$reportTypeEnum, $subtype] = $this->normalizeReportTypeAndSubtype($data['report_type']);

            $configData = $data['configuration_data'] ?? [];
            if ($subtype !== null) {
                $configData['subtype'] = $subtype;
            }

            $config = ReportConfiguration::create([
                'academic_year_id' => $currentYear->id,
                'name' => trim($data['name']),
                'report_type' => $reportTypeEnum,
                'configuration_data' => $configData,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_REPORT_CONFIGURATION',
                entityType: 'report_configurations',
                entityId: $config->id,
                beforeData: null,
                afterData: $config->only(['academic_year_id', 'name', 'report_type', 'configuration_data', 'is_active']),
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

            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['is_active'])) {
                $updateData['is_active'] = (bool) $data['is_active'];
            }

            $existingData = $config->configuration_data ?? [];

            if (isset($data['report_type'])) {
                [$reportTypeEnum, $targetSubtype] = $this->normalizeReportTypeAndSubtype($data['report_type']);

                if ($targetSubtype === 'mid_term') {
                    $selectionCount = $config->assessmentSelections()->count();
                    if ($selectionCount > 1) {
                        throw ValidationException::withMessages([
                            'report_type' => ["Cannot change report type to Mid term Assessments because this configuration currently has {$selectionCount} assessments attached. A Mid term Assessment allows at most one assessment. Remove extra assessments before converting, or select Custom Report."],
                        ]);
                    }
                }

                $updateData['report_type'] = $reportTypeEnum;

                if ($targetSubtype !== null) {
                    $existingData['subtype'] = $targetSubtype;
                } elseif ($reportTypeEnum !== ReportType::EXAM) {
                    unset($existingData['subtype']);
                }
            }

            if (array_key_exists('configuration_data', $data) && is_array($data['configuration_data'])) {
                $existingData = array_merge($existingData, $data['configuration_data']);
            }
            $updateData['configuration_data'] = $existingData;

            $config->update($updateData);

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
            // Lock parent configuration row to serialize concurrent additions
            ReportConfiguration::where('id', $config->id)->lockForUpdate()->first();
            $existingSelections = $config->assessmentSelections()->lockForUpdate()->get();

            if ($config->isMidTerm()) {
                if ($existingSelections->count() >= 1) {
                    throw ValidationException::withMessages([
                        'assessment_id' => ['Mid term Assessment configurations allow exactly one assessment. Remove the existing assessment before adding a new one, or use a Custom Report.'],
                    ]);
                }
            }

            $assessmentId = (int) $data['assessment_id'];
            $assessment = Assessment::findOrFail($assessmentId);

            if ($config->academic_year_id !== null && $assessment->academic_year_id !== $config->academic_year_id) {
                throw ValidationException::withMessages([
                    'assessment_id' => ['The selected assessment belongs to a different academic year than this report configuration.'],
                ]);
            }

            $maxOrder = (int) ($existingSelections->max('display_order') ?? 0);
            $displayOrder = $maxOrder + 1;

            $selection = ReportAssessmentSelection::create([
                'report_configuration_id' => $config->id,
                'assessment_id' => $assessmentId,
                'display_order' => $displayOrder,
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
     * Reorder assessment selections for a Report Configuration.
     *
     * @param  array<int>  $selectionIds
     * @throws ValidationException
     */
    public function reorderAssessmentSelections(ReportConfiguration $config, array $selectionIds): void
    {
        DB::transaction(function () use ($config, $selectionIds) {
            $existingSelections = $config->assessmentSelections()->lockForUpdate()->get();
            $existingIds = $existingSelections->pluck('id')->sort()->values()->all();
            $submittedIds = collect($selectionIds)->map(fn ($id) => (int) $id)->values();

            if ($submittedIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'selection_ids' => ['Duplicate selection IDs provided in reorder list.'],
                ]);
            }

            $sortedSubmitted = $submittedIds->sort()->values()->all();
            if ($existingIds !== $sortedSubmitted) {
                throw ValidationException::withMessages([
                    'selection_ids' => ['The reorder selection list must contain a complete, exact permutation of selections for this configuration.'],
                ]);
            }

            foreach ($submittedIds as $index => $id) {
                $existingSelections->firstWhere('id', $id)->update([
                    'display_order' => $index + 1,
                ]);
            }

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'REORDER_REPORT_ASSESSMENT_SELECTIONS',
                entityType: 'report_configurations',
                entityId: $config->id,
                beforeData: ['selection_ids' => $existingSelections->sortBy('display_order')->pluck('id')->all()],
                afterData: ['selection_ids' => $submittedIds->all()],
                description: "Reordered assessment selections for report configuration ID {$config->id} ({$config->name})"
            );
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

    /**
     * Remove an Assessment display selection from a Report Configuration.
     */
    public function removeAssessmentSelection(ReportAssessmentSelection $selection): void
    {
        DB::transaction(function () use ($selection) {
            $beforeData = $selection->only(['report_configuration_id', 'assessment_id', 'display_order', 'is_displayed']);
            $id = $selection->id;
            $configId = $selection->report_configuration_id;

            $selection->delete();

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'DELETE_REPORT_ASSESSMENT_SELECTION',
                entityType: 'report_assessment_selections',
                entityId: $id,
                beforeData: $beforeData,
                afterData: null,
                description: "Removed assessment selection ID {$id} from report configuration ID {$configId}"
            );
        });
    }

    /**
     * Delete a Report Configuration if it has no associated assessment selections.
     *
     * @throws \DomainException
     */
    public function deleteReportConfiguration(ReportConfiguration $config): void
    {
        if ($config->assessmentSelections()->exists()) {
            throw new \DomainException('This report configuration cannot be removed because it has associated assessment selections. Remove all assessment selections first or deactivate the configuration.');
        }

        DB::transaction(function () use ($config) {
            $beforeData = [
                'id' => $config->id,
                'academic_year_id' => $config->academic_year_id,
                'name' => $config->name,
                'report_type' => $config->report_type->value,
                'is_active' => $config->is_active,
            ];

            $config->delete();

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'DELETE_REPORT_CONFIGURATION',
                entityType: 'report_configurations',
                entityId: $beforeData['id'],
                beforeData: $beforeData,
                afterData: null,
                description: 'Permanently removed report configuration: ' . $beforeData['name']
            );
        });
    }
}
