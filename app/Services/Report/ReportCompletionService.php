<?php

namespace App\Services\Report;

use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\Mark;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\StudentAcademicRecord;
use App\Models\Term;
use App\Services\Report\Exceptions\IncompleteMarksheetException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReportCompletionService
{
    public function __construct(
        protected ReportConfigurationResolver $configResolver
    ) {}

    /**
     * Convenience wrapper validating completeness by SAR ID.
     *
     * @throws IncompleteMarksheetException
     * @throws UnprocessableEntityHttpException
     */
    public function validateCompleteness(
        int $sarId,
        string $reportType,
        ?int $termId = null,
        ?ReportConfiguration $config = null,
        ?int $reportConfigurationId = null
    ): void {
        $sar = StudentAcademicRecord::with([
            'student',
            'subjectAllocations.classSubject.subject',
        ])->findOrFail($sarId);

        $reportTypeEnum = match($reportType) {
            'mid_term', 'custom', 'exam' => ReportType::EXAM,
            'term' => ReportType::TERM,
            'final' => ReportType::FINAL,
            default => $reportType instanceof ReportType ? $reportType : ReportType::from($reportType),
        };
        $term = $termId ? Term::find($termId) : null;

        if (! $config && $reportConfigurationId) {
            $config = $this->configResolver->resolve($reportConfigurationId, $sar->academic_year_id, $reportType, $termId);
        }

        if (! $config) {
            $config = $this->configResolver->resolveDefault($sar->academic_year_id, $reportType, $termId);
        }

        if (! $config) {
            throw new UnprocessableEntityHttpException('No active report configuration is available for this report context. Configure a report layout before generating.');
        }

        $this->validateCompletion($sar, $reportTypeEnum, $term, $config);
    }

    /**
     * Check if marksheet is complete without throwing exception.
     */
    public function isComplete(
        int $sarId,
        string $reportType,
        ?int $termId = null,
        ?ReportConfiguration $config = null,
        ?int $reportConfigurationId = null
    ): bool {
        try {
            $this->validateCompleteness($sarId, $reportType, $termId, $config, $reportConfigurationId);
            return true;
        } catch (IncompleteMarksheetException $e) {
            return false;
        } catch (UnprocessableEntityHttpException $e) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Retrieve list of missing assessments.
     *
     * @return array<string>
     */
    public function getMissingAssessments(
        int $sarId,
        string $reportType,
        ?int $termId = null,
        ?ReportConfiguration $config = null,
        ?int $reportConfigurationId = null
    ): array {
        try {
            $this->validateCompleteness($sarId, $reportType, $termId, $config, $reportConfigurationId);
            return [];
        } catch (IncompleteMarksheetException $e) {
            return $e->missingSubjects;
        } catch (UnprocessableEntityHttpException $e) {
            return [$e->getMessage()];
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }
    }

    /**
     * Validate marksheet completeness for a target student academic record and report context.
     *
     * In accordance with BRD V1.3 (BR-080 to BR-084) and Phase 13 specifications:
     * - Every required displayed and applicable assessment for the student's allocated subjects must have an entered mark.
     * - Only assessments selected and displayed (is_displayed = true) in the resolved ReportConfiguration participate in completion.
     * - 'numeric' (including 0.00) and 'absent' are valid complete results.
     * - 'blank' (or unentered) mark makes the marksheet incomplete and strictly blocks generation.
     * - Non-selected assessments NEVER block completion.
     * - Assessments not applicable to a subject NEVER block that subject.
     *
     * @throws IncompleteMarksheetException
     * @throws UnprocessableEntityHttpException
     */
    public function validateCompletion(
        StudentAcademicRecord $sar,
        ReportType $reportType,
        ?Term $term = null,
        ?ReportConfiguration $config = null
    ): void {
        if (! $config) {
            $config = $this->configResolver->resolveDefault($sar->academic_year_id, $reportType, $term?->id);
        }

        if (! $config) {
            throw new UnprocessableEntityHttpException('No active report configuration is available for this report context. Configure a report layout before generating.');
        }

        if (! $sar->relationLoaded('student')) {
            $sar->load('student');
        }
        if (! $sar->relationLoaded('subjectAllocations.classSubject.subject')) {
            $sar->load('subjectAllocations.classSubject.subject');
        }

        $activeAllocations = $sar->subjectAllocations->filter(function ($a) use ($sar) {
            if (! $a->is_active) {
                return false;
            }
            $cs = $a->classSubject;
            if ($cs === null || ! $cs->is_active || $cs->subject === null) {
                return false;
            }
            if ((int) $cs->academic_year_id !== (int) $sar->academic_year_id) {
                return false;
            }
            if ((int) $cs->class_id !== (int) $sar->class_id) {
                return false;
            }
            return true;
        });
        if ($activeAllocations->isEmpty()) {
            return;
        }

        $classSubjectIds = $activeAllocations->pluck('class_subject_id')->unique()->values();

        // 1. Resolve displayed assessment IDs strictly from the ReportConfiguration
        $displayedAssessmentIds = ReportAssessmentSelection::query()
            ->where('report_configuration_id', $config->id)
            ->where('is_displayed', true)
            ->pluck('assessment_id');

        if ($displayedAssessmentIds->isEmpty()) {
            return;
        }

        // 2. Resolve assessments participating in completion based on report type
        $assessments = match ($reportType) {
            ReportType::TERM => Assessment::query()
                ->where('academic_year_id', $sar->academic_year_id)
                ->whereIn('id', $displayedAssessmentIds)
                ->with('term')
                ->get(),
            ReportType::FINAL => Assessment::query()
                ->where('academic_year_id', $sar->academic_year_id)
                ->where(function ($q) use ($sar) {
                    $q->whereNull('term_id')
                      ->orWhereHas('term', function ($tq) use ($sar) {
                          $tq->where('academic_year_id', $sar->academic_year_id);
                      });
                })
                ->whereIn('id', $displayedAssessmentIds)
                ->with('term')
                ->get(),
            ReportType::EXAM => Assessment::query()
                ->where('academic_year_id', $sar->academic_year_id)
                ->whereIn('id', $displayedAssessmentIds)
                ->with('term')
                ->get(),
        };

        if ($assessments->isEmpty()) {
            return;
        }

        // 3. Check applicability for each class subject
        $applicabilities = AssessmentApplicability::query()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->whereIn('class_subject_id', $classSubjectIds)
            ->where('is_active', true)
            ->get();

        if ($applicabilities->isEmpty()) {
            return;
        }

        // 4. Preload marks for this student academic record
        $marks = Mark::query()
            ->where('student_academic_record_id', $sar->id)
            ->whereIn('assessment_applicability_id', $applicabilities->pluck('id'))
            ->get()
            ->keyBy('assessment_applicability_id');

        $missingSubjects = [];

        foreach ($activeAllocations as $alloc) {
            $cs = $alloc->classSubject;
            if (! $cs) {
                continue;
            }

            $subjectApplicabilities = $applicabilities->where('class_subject_id', $cs->id);
            foreach ($subjectApplicabilities as $app) {
                $mark = $marks->get($app->id);

                // A mark is incomplete if it does not exist or has result_status = blank
                if (! $mark || $mark->result_status === MarkResultStatus::BLANK) {
                    $subjName = $cs->subject_name_snapshot ?: ($cs->subject?->name ?? 'Subject');
                    $asmt = $assessments->firstWhere('id', $app->assessment_id);
                    $asmtName = $asmt?->name ?? 'Assessment';
                    $termSuffix = $asmt?->term ? " - {$asmt->term->name}" : '';
                    $missingSubjects[] = "{$subjName} ({$asmtName}{$termSuffix})";
                }
            }
        }

        if (! empty($missingSubjects)) {
            $uniqueMissing = array_values(array_unique($missingSubjects));
            throw new IncompleteMarksheetException(
                missingSubjects: $uniqueMissing,
                studentName: $sar->student?->student_name,
                admissionNumber: $sar->student?->admission_number,
                rollNumber: $sar->roll_number
            );
        }
    }
}
