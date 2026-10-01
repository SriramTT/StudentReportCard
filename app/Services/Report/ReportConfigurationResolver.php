<?php

namespace App\Services\Report;

use App\Enums\ReportType;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ReportConfiguration;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReportConfigurationResolver
{
    /**
     * Get available active configurations compatible with the given academic year, report type,
     * and optionally filtered to those usable for a specific class and section context.
     * Includes configurations specifically for this academic year, plus global ones (academic_year_id IS NULL).
     *
     * @return EloquentCollection<int, ReportConfiguration>
     */
    public function getAvailableConfigurations(
        ?int $academicYearId,
        string|ReportType $reportType,
        ?int $classId = null,
        ?int $sectionId = null
    ): EloquentCollection {
        $query = ReportConfiguration::query()
            ->where(function ($q) use ($academicYearId) {
                if ($academicYearId) {
                    $q->where('academic_year_id', $academicYearId)
                        ->orWhereNull('academic_year_id');
                } else {
                    $q->whereNull('academic_year_id');
                }
            })
            ->where('is_active', true)
            ->with([
                'assessmentSelections' => fn ($q) => $q->where('is_displayed', true)->orderBy('display_order')->with('assessment'),
            ])
            ->orderByRaw('academic_year_id NULLS LAST')
            ->orderBy('name');

        $typeStr = $reportType instanceof ReportType ? $reportType->value : (string) $reportType;

        match ($typeStr) {
            'term' => $query->where('report_type', ReportType::TERM),
            'final' => $query->where('report_type', ReportType::FINAL),
            'mid_term', 'exam' => $query->where('report_type', ReportType::EXAM)
                ->whereRaw("(configuration_data->>'subtype') = 'mid_term'"),
            'custom' => $query->where('report_type', ReportType::EXAM)
                ->whereRaw("((configuration_data->>'subtype') = 'custom' OR (configuration_data->>'subtype') IS NULL)"),
            default => $query->where('report_type', ReportType::from($typeStr)),
        };

        $configs = $query->get();

        if ($classId !== null) {
            $configs = $configs->filter(function (ReportConfiguration $config) use ($academicYearId, $classId, $sectionId) {
                return $this->isConfigurationUsable($config, $academicYearId, $classId, $sectionId);
            })->values();
        }

        return $configs;
    }

    /**
     * Determine if a ReportConfiguration is valid and usable for the given academic year, class, and section context.
     *
     * In accordance with BRD V1.3, Master Handover, and completion rules:
     * 1. The configuration must be active.
     * 2. The configuration must match the academic year (or be global).
     * 3. The configuration must have at least one displayed assessment (is_displayed = true).
     * 4. EVERY displayed assessment configured in this report layout must have at least one active
     *    AssessmentApplicability for an active ClassSubject belonging to the specified class
     *    (and section or all-sections). If even one displayed assessment is inapplicable to this
     *    classroom context, the configuration cannot be used for this class/section.
     */
    public function isConfigurationUsable(
        ReportConfiguration $config,
        ?int $academicYearId,
        ?int $classId,
        ?int $sectionId = null
    ): bool {
        if (! $config->is_active) {
            return false;
        }

        // Academic year compatibility
        if ($academicYearId !== null && $config->academic_year_id !== null && (int) $config->academic_year_id !== (int) $academicYearId) {
            return false;
        }

        // Must have at least one displayed assessment (Final reports aggregate terms and may have no individual assessments)
        $displayedSelections = $config->assessmentSelections
            ->where('is_displayed', true);

        if ($displayedSelections->isEmpty()) {
            return $config->report_type === ReportType::FINAL;
        }

        if ($classId === null) {
            return true;
        }

        $displayedAssessmentIds = $displayedSelections->pluck('assessment_id')->unique()->values();

        // Query which of these displayed assessments have active applicability to this class and section
        $applicableAssessmentIdsQuery = AssessmentApplicability::query()
            ->whereIn('assessment_id', $displayedAssessmentIds)
            ->where('is_active', true)
            ->whereHas('classSubject', function ($csQuery) use ($classId, $sectionId, $academicYearId) {
                $csQuery->where('class_id', $classId)
                    ->where('is_active', true);
                if ($academicYearId !== null) {
                    $csQuery->where('academic_year_id', $academicYearId);
                }
                if ($sectionId !== null) {
                    $csQuery->where(function ($sq) use ($sectionId) {
                        $sq->where('section_id', $sectionId)
                            ->orWhereNull('section_id'); // class-wide/all-sections
                    });
                }
            });

        $applicableAssessmentIds = $applicableAssessmentIdsQuery->pluck('assessment_id')->unique()->values();

        // Every displayed assessment must be applicable to this class/section context
        return $displayedAssessmentIds->every(fn ($asmtId) => $applicableAssessmentIds->contains($asmtId));
    }

    /**
     * Resolve and validate a specific configuration by ID against context.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function resolve(
        int $reportConfigurationId,
        ?int $academicYearId,
        string|ReportType $reportType,
        ?int $termId = null,
        ?int $classId = null,
        ?int $sectionId = null
    ): ReportConfiguration {
        /** @var ?ReportConfiguration $config */
        $config = ReportConfiguration::with([
            'assessmentSelections' => fn ($q) => $q->where('is_displayed', true)->orderBy('display_order')->with('assessment'),
        ])->find($reportConfigurationId);

        if (! $config) {
            throw new UnprocessableEntityHttpException("Report configuration with ID {$reportConfigurationId} does not exist.");
        }

        if (! $config->is_active) {
            throw new UnprocessableEntityHttpException("Report configuration '{$config->name}' is inactive.");
        }

        $typeStr = $reportType instanceof ReportType ? $reportType->value : (string) $reportType;

        $typeMatches = match ($typeStr) {
            'term' => $config->report_type === ReportType::TERM,
            'final' => $config->report_type === ReportType::FINAL,
            'mid_term' => $config->isMidTerm(),
            'custom' => $config->isCustom(),
            'exam' => $config->report_type === ReportType::EXAM,
            default => $config->report_type->value === $typeStr,
        };

        if (! $typeMatches) {
            throw new UnprocessableEntityHttpException("Report configuration '{$config->name}' does not match report type '{$typeStr}'.");
        }

        if ($config->academic_year_id !== null && $academicYearId !== null && (int) $config->academic_year_id !== (int) $academicYearId) {
            throw new UnprocessableEntityHttpException("Report configuration '{$config->name}' is not compatible with the selected academic year.");
        }

        if ($classId !== null && ! $this->isConfigurationUsable($config, $academicYearId, $classId, $sectionId)) {
            throw new UnprocessableEntityHttpException("Report configuration '{$config->name}' is not usable for the selected class and section.");
        }

        return $config;
    }

    /**
     * Resolve default configuration if exactly one valid active configuration exists for context.
     * If multiple exist, returns null (user must explicitly select).
     * If none exist, returns null.
     */
    public function resolveDefault(
        ?int $academicYearId,
        string|ReportType $reportType,
        ?int $termId = null,
        ?int $classId = null,
        ?int $sectionId = null
    ): ?ReportConfiguration {
        $available = $this->getAvailableConfigurations($academicYearId, $reportType, $classId, $sectionId);

        if ($available->count() === 1) {
            return $available->first();
        }

        return null;
    }

    /**
     * Get displayed assessments for a specific term from the resolved configuration.
     * Respects is_displayed = true, display_order ASC, and term compatibility.
     *
     * @return Collection<int, Assessment>
     */
    public function getDisplayedAssessmentsForTerm(ReportConfiguration $config, ?int $termId): Collection
    {
        $selections = $config->assessmentSelections
            ->where('is_displayed', true)
            ->sortBy('display_order');

        $assessmentIds = $selections->pluck('assessment_id')->unique()->values();

        if ($assessmentIds->isEmpty()) {
            return collect();
        }

        $query = Assessment::query()
            ->whereIn('id', $assessmentIds)
            ->with(['assessmentType', 'term']);

        $assessments = $query->get();

        // Sort by selection display_order
        return $assessments->sortBy(function (Assessment $a) use ($selections) {
            $sel = $selections->firstWhere('assessment_id', $a->id);
            return $sel?->display_order ?? 999;
        })->values();
    }
}
