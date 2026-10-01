<?php

namespace App\Services\Report;

use App\Contracts\ReportGeneratorContract;
use App\Enums\ReportType;
use App\Models\AcademicYear;
use App\Models\GeneratedReport;
use App\Models\ReportConfiguration;
use App\Models\StudentAcademicRecord;
use App\Models\Term;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TeacherAuthorizationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReportGenerationService
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth,
        protected ReportCompletionService $completionService,
        protected ReportDataPreparationService $dataPrepService,
        protected ReportGeneratorContract $pdfGenerator,
        protected AuditService $auditService,
        protected ReportConfigurationResolver $configResolver
    ) {}

    /**
     * Preview a report without persisting any records or files.
     * Returns the rendered HTML.
     */
    public function preview(
        int $sarId,
        string $reportType,
        ?int $termId = null,
        ?int $assessmentId = null,
        ?int $reportConfigurationId = null
    ): string {
        $sar = StudentAcademicRecord::with(['student', 'academicYear', 'schoolClass', 'section'])
            ->findOrFail($sarId);

        $this->validateReportContext($sar, $reportType, $termId, $assessmentId);

        $config = $reportConfigurationId
            ? $this->configResolver->resolve($reportConfigurationId, $sar->academic_year_id, $reportType, $termId)
            : $this->configResolver->resolveDefault($sar->academic_year_id, $reportType, $termId);

        if (! $config) {
            throw new UnprocessableEntityHttpException('No active report configuration is available for this report context. Configure a report layout before generating.');
        }

        // Completion check throws IncompleteMarksheetException if required marks are missing
        $this->completionService->validateCompleteness($sarId, $reportType, $termId, $config);

        // Determine provisional next revision for display
        $query = DB::table('generated_reports')
            ->where('student_academic_record_id', $sarId)
            ->where('report_type', $reportType);

        if ($termId === null) {
            $query->whereNull('term_id');
        } else {
            $query->where('term_id', $termId);
        }

        if ($assessmentId === null) {
            $query->whereNull('assessment_id');
        } else {
            $query->where('assessment_id', $assessmentId);
        }

        $currentMax = (int) $query->max('revision_number');
        $previewRevision = $currentMax > 0 ? $currentMax + 1 : 1;

        $payload = $this->dataPrepService->prepare(
            sarId: $sarId,
            reportType: $reportType,
            termId: $termId,
            assessmentId: $assessmentId,
            revisionNumber: $previewRevision,
            generatedAt: CarbonImmutable::now(),
            reportConfig: $config
        );

        $viewName = $reportType === 'final' ? 'reports.pdf.final-report' : 'reports.pdf.term-report';

        return view($viewName, ['payload' => $payload])->render();
    }

    /**
     * Atomically generate, persist, and audit a formal report PDF revision.
     */
    public function generate(
        int $sarId,
        string $reportType,
        ?int $termId,
        ?int $assessmentId,
        User $user,
        ?int $reportConfigurationId = null
    ): GeneratedReport {
        $sar = StudentAcademicRecord::with(['student', 'academicYear', 'schoolClass', 'section'])
            ->findOrFail($sarId);

        // Authorization check
        if (! $this->teacherAuth->userCanGenerateReport($user, $sar->academic_year_id, $sar->class_id, $sar->section_id)) {
            throw new AccessDeniedHttpException('You are not authorized to generate report cards for this classroom.');
        }

        $this->validateReportContext($sar, $reportType, $termId, $assessmentId);

        $config = $reportConfigurationId
            ? $this->configResolver->resolve($reportConfigurationId, $sar->academic_year_id, $reportType, $termId)
            : $this->configResolver->resolveDefault($sar->academic_year_id, $reportType, $termId);

        if (! $config) {
            throw new UnprocessableEntityHttpException('No active report configuration is available for this report context. Configure a report layout before generating.');
        }

        // Completion check throws IncompleteMarksheetException
        $this->completionService->validateCompleteness($sarId, $reportType, $termId, $config);

        // Retry loop to handle concurrent generation with advisory locks and unique violation retry
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $stagingPath = null;
            $permanentPath = null;

            try {
                return DB::transaction(function () use (
                    $sar,
                    $sarId,
                    $reportType,
                    $termId,
                    $assessmentId,
                    $user,
                    $config,
                    &$stagingPath,
                    &$permanentPath
                ) {
                    // 1. Lock SAR row
                    StudentAcademicRecord::where('id', $sarId)->lockForUpdate()->firstOrFail();

                    // 2. Transaction advisory lock on report identity
                    $lockKey = crc32("report_lock:{$sarId}:{$reportType}:" . ($termId ?? 0) . ':' . ($assessmentId ?? 0));
                    DB::statement('SELECT pg_advisory_xact_lock(?);', [$lockKey]);

                    // 3. Determine next revision
                    $query = DB::table('generated_reports')
                        ->where('student_academic_record_id', $sarId)
                        ->where('report_type', $reportType);

                    if ($termId === null) {
                        $query->whereNull('term_id');
                    } else {
                        $query->where('term_id', $termId);
                    }

                    if ($assessmentId === null) {
                        $query->whereNull('assessment_id');
                    } else {
                        $query->where('assessment_id', $assessmentId);
                    }

                    $currentMax = (int) $query->max('revision_number');
                    $nextRevision = $currentMax + 1;

                    // 4. Authoritative timestamp
                    $generatedAt = CarbonImmutable::now();

                    // 5. Prepare authoritative immutable payload
                    $payload = $this->dataPrepService->prepare(
                        sarId: $sarId,
                        reportType: $reportType,
                        termId: $termId,
                        assessmentId: $assessmentId,
                        revisionNumber: $nextRevision,
                        generatedAt: $generatedAt,
                        reportConfig: $config
                    );

                    // 6. Render HTML view
                    $viewName = $reportType === 'final' ? 'reports.pdf.final-report' : 'reports.pdf.term-report';
                    $html = view($viewName, ['payload' => $payload])->render();

                    // 7. Stage PDF in temporary directory
                    $uuid = (string) Str::uuid();
                    $stagingRelative = "temp/reports/{$uuid}.pdf";
                    $stagingPath = storage_path("app/private/{$stagingRelative}");
                    File::ensureDirectoryExists(dirname($stagingPath));

                    $pdfBytes = $this->pdfGenerator->generatePdfFromHtml($html);
                    File::put($stagingPath, $pdfBytes);

                    if (! File::exists($stagingPath) || File::size($stagingPath) === 0) {
                        throw new \RuntimeException('PDF generation produced an empty or missing file.');
                    }

                    // 8. Promote to permanent path
                    $filename = $this->buildHumanReadableFilename($sar, $reportType, $termId, $assessmentId, $config);
                    $permanentRelative = "reports/{$sar->academic_year_id}/{$sar->class_id}/{$sar->section_id}/{$sarId}/{$reportType}/rev_{$nextRevision}/{$filename}";
                    $permanentPath = storage_path("app/private/{$permanentRelative}");
                    File::ensureDirectoryExists(dirname($permanentPath));

                    File::copy($stagingPath, $permanentPath);
                    File::delete($stagingPath);
                    $stagingPath = null;

                    // 9. Persist generated_reports row
                    $report = GeneratedReport::create([
                        'student_academic_record_id' => $sarId,
                        'report_type' => $reportType,
                        'term_id' => $termId,
                        'assessment_id' => $assessmentId,
                        'revision_number' => $nextRevision,
                        'file_path' => $permanentRelative,
                        'generated_by_user_id' => $user->id,
                        'generated_at' => $generatedAt,
                    ]);

                    // 10. Audit log
                    $this->auditService->logDomainAction(
                        userId: $user->id,
                        action: 'REPORT_GENERATED',
                        entityType: 'generated_reports',
                        entityId: $report->id,
                        beforeData: null,
                        afterData: [
                            'student_id' => $sar->student_id,
                            'student_academic_record_id' => $sarId,
                            'report_type' => $reportType,
                            'term_id' => $termId,
                            'assessment_id' => $assessmentId,
                            'revision_number' => $nextRevision,
                            'file_path' => $permanentRelative,
                            'report_configuration_id' => $config->id,
                            'report_configuration_name' => $config->name,
                        ],
                        description: "Generated {$reportType} report card (Revision {$nextRevision}) for student {$sar->student?->admission_number}"
                    );

                    return $report;
                });
            } catch (QueryException $e) {
                // Cleanup staged/permanent files on failure
                $this->cleanupFiles($stagingPath, $permanentPath);

                // SQLSTATE 23505 = unique_violation
                if ($e->getCode() === '23505' && $attempt < $maxAttempts) {
                    usleep(50000 * $attempt); // 50ms, 100ms
                    continue;
                }

                throw $e;
            } catch (\Throwable $e) {
                $this->cleanupFiles($stagingPath, $permanentPath);
                throw $e;
            }
        }

        throw new \RuntimeException('Failed to allocate concurrent report revision after multiple attempts.');
    }

    /**
     * Validate report parameters against student and academic year context.
     */
    protected function validateReportContext(
        StudentAcademicRecord $sar,
        string $reportType,
        ?int $termId,
        ?int $assessmentId
    ): void {
        if (! in_array($reportType, ['term', 'final', 'exam'], true)) {
            throw new UnprocessableEntityHttpException("Invalid report type: {$reportType}.");
        }

        if ($reportType === 'term') {
            if ($termId === null) {
                throw new UnprocessableEntityHttpException('A valid term_id is required for term reports.');
            }

            $term = Term::find($termId);
            if (! $term || (int) $term->academic_year_id !== (int) $sar->academic_year_id) {
                throw new UnprocessableEntityHttpException('The selected term does not belong to the student academic year.');
            }
        }

        if ($reportType === 'final') {
            if ($termId !== null) {
                throw new UnprocessableEntityHttpException('term_id must be null for final reports.');
            }
        }
    }

    /**
     * Clean up staging and newly created permanent files if transaction failed.
     */
    protected function cleanupFiles(?string $stagingPath, ?string $permanentPath): void
    {
        if ($stagingPath && File::exists($stagingPath)) {
            File::delete($stagingPath);
        }

        if ($permanentPath && File::exists($permanentPath)) {
            // Check if permanentPath is tracked in DB. If not, delete it.
            $basePrivate = rtrim(str_replace('\\', '/', storage_path('app/private')), '/');
            $cleanPermanent = str_replace('\\', '/', $permanentPath);
            $relativePath = ltrim(str_replace($basePrivate, '', $cleanPermanent), '/');
            $existsInDb = GeneratedReport::where('file_path', $relativePath)->exists();
            if (! $existsInDb) {
                File::delete($permanentPath);
            }
        }
    }

    /**
     * Build the standardized human-readable report filename:
     * {Student_Name}_{Class}_{Section}_{Admission_Number}_{Report_Name}.pdf
     * Example: Kumar_V_1_A_SVS-006_Annual_Exam.pdf
     */
    public function buildHumanReadableFilename(
        StudentAcademicRecord $sar,
        string|ReportType $reportType,
        ?int $termId = null,
        ?int $assessmentId = null,
        ?ReportConfiguration $config = null
    ): string {
        $sar->loadMissing(['student', 'schoolClass', 'section']);

        $studentName = $this->sanitizeFilenameSegment($sar->student?->student_name ?? 'Student');

        // Clean class name: strip "Class " or "Grade " prefix if present, e.g. "Class 1" -> "1"
        $rawClass = $sar->schoolClass?->name ?? 'Class';
        $cleanedClass = preg_replace('/^(?:Class|Grade)\s+/i', '', $rawClass);
        $className = $this->sanitizeFilenameSegment($cleanedClass ?: $rawClass);

        $sectionName = $this->sanitizeFilenameSegment($sar->section?->name ?? 'Section');
        $admissionNo = $this->sanitizeFilenameSegment($sar->student?->admission_number ?? 'Unknown');

        // Resolve dynamic report name
        $reportTypeEnum = $reportType instanceof ReportType ? $reportType : ReportType::tryFrom($reportType) ?? ReportType::FINAL;
        $reportName = $this->resolveReportName($sar, $reportTypeEnum, $termId, $assessmentId, $config);

        return "{$studentName}_{$className}_{$sectionName}_{$admissionNo}_{$reportName}.pdf";
    }

    /**
     * Safely sanitize a filename segment while preserving alphanumeric, underscores, and hyphens.
     */
    public function sanitizeFilenameSegment(string $value): string
    {
        // 1. Replace any sequence of non-alphanumeric (excluding hyphens) characters with underscore
        $clean = preg_replace('/[^\p{L}\p{N}\-]+/u', '_', trim($value));
        // 2. Collapse multiple consecutive underscores
        $clean = preg_replace('/_+/', '_', $clean);
        // 3. Trim leading/trailing underscores
        $clean = trim($clean, '_');

        return $clean !== '' ? $clean : 'NA';
    }

    /**
     * Dynamically resolve the human-readable report name from authoritative context.
     */
    public function resolveReportName(
        StudentAcademicRecord $sar,
        ReportType $reportType,
        ?int $termId = null,
        ?int $assessmentId = null,
        ?ReportConfiguration $config = null
    ): string {
        if ($assessmentId) {
            $assessment = \App\Models\Assessment::find($assessmentId);
            if ($assessment && ! empty($assessment->name)) {
                return $this->sanitizeFilenameSegment($assessment->name);
            }
        }

        if ($reportType === ReportType::TERM && $termId) {
            $term = Term::find($termId);
            if ($term && ! empty($term->name)) {
                return $this->sanitizeFilenameSegment($term->name);
            }
        }

        if ($reportType === ReportType::FINAL) {
            if (! $config) {
                $config = $this->configResolver->resolveDefault($sar->academic_year_id, ReportType::FINAL);
            }

            if ($config) {
                if (! $config->relationLoaded('assessmentSelections.assessment')) {
                    $config->load('assessmentSelections.assessment');
                }
                $annualAssessment = $config->assessmentSelections
                    ?->filter(fn ($s) => $s->is_displayed && $s->assessment && $s->assessment->term_id === null)
                    ?->sortBy('display_order')
                    ?->first()
                    ?->assessment;

                if ($annualAssessment && ! empty($annualAssessment->name)) {
                    return $this->sanitizeFilenameSegment($annualAssessment->name);
                }

                if (! empty($config->name)) {
                    return $this->sanitizeFilenameSegment($config->name);
                }
            }

            return 'Annual_Exam';
        }

        return $this->sanitizeFilenameSegment(ucwords(str_replace('_', ' ', $reportType->value)));
    }
}
