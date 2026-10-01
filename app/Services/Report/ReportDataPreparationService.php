<?php

namespace App\Services\Report;

use App\Enums\AssessmentStatus;
use App\Enums\MarkResultStatus;
use App\Enums\ReportType;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Models\StudentAcademicRecord;
use App\Models\Term;
use App\Services\Attendance\AttendanceService;
use App\Services\Calculation\CalculationService;
use App\Services\Report\DTOs\AssessmentColumnHeader;
use App\Services\Report\DTOs\ReportDataPayload;
use App\Services\Report\DTOs\SubjectReportRow;
use App\Services\SchoolSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ReportDataPreparationService
{
    public function __construct(
        protected CalculationService $calculationService,
        protected AttendanceService $attendanceService,
        protected SchoolSettingService $schoolSettingService,
        protected ReportConfigurationResolver $configResolver,
        protected \App\Services\Calculation\Contracts\CalculationParticipationResolverInterface $participationResolver
    ) {}

    /**
     * Resolve the active ReportConfiguration for the given academic year and report type.
     */
    public function resolveConfiguration(?int $academicYearId, ReportType $reportType, ?int $configId = null): ?ReportConfiguration
    {
        if ($configId) {
            return $this->configResolver->resolve($configId, $academicYearId, $reportType);
        }

        return $this->configResolver->resolveDefault($academicYearId, $reportType);
    }

    /**
     * Convenience method preparing ReportDataPayload by SAR ID and primitive parameters.
     */
    public function prepare(
        int $sarId,
        string $reportType,
        ?int $termId = null,
        ?int $assessmentId = null,
        int $revisionNumber = 1,
        ?CarbonImmutable $generatedAt = null,
        ?ReportConfiguration $reportConfig = null,
        ?int $reportConfigurationId = null
    ): ReportDataPayload {
        $sar = StudentAcademicRecord::with([
            'student',
            'schoolClass',
            'section',
            'academicYear',
            'subjectAllocations.classSubject.subject',
        ])->findOrFail($sarId);

        $reportTypeEnum = ReportType::from($reportType);
        $term = $termId ? Term::find($termId) : null;

        if (! $reportConfig && $reportConfigurationId) {
            $reportConfig = $this->resolveConfiguration($sar->academic_year_id, $reportTypeEnum, $reportConfigurationId);
        }

        return $this->prepareReportData(
            sar: $sar,
            reportType: $reportTypeEnum,
            term: $term,
            revisionNumber: $revisionNumber,
            generatedAt: $generatedAt,
            reportConfig: $reportConfig
        );
    }

    /**
     * Prepare a fully hydrated, immutable ReportDataPayload DTO.
     */
    public function prepareReportData(
        StudentAcademicRecord $sar,
        ReportType $reportType,
        ?Term $term = null,
        ?int $revisionNumber = 1,
        ?CarbonImmutable $generatedAt = null,
        ?ReportConfiguration $reportConfig = null
    ): ReportDataPayload {
        $generatedAt = $generatedAt ?? CarbonImmutable::now();

        // Ensure placement relationships are loaded
        if (! $sar->relationLoaded('student')) {
            $sar->load('student');
        }
        if (! $sar->relationLoaded('schoolClass')) {
            $sar->load('schoolClass');
        }
        if (! $sar->relationLoaded('section')) {
            $sar->load('section');
        }
        if (! $sar->relationLoaded('academicYear')) {
            $sar->load('academicYear');
        }
        if (! $sar->relationLoaded('subjectAllocations.classSubject.subject')) {
            $sar->load('subjectAllocations.classSubject.subject');
        }

        $schoolSettings = $this->schoolSettingService->getSettings();
        $passMarkThreshold = (float) $schoolSettings->pass_mark;

        // Resolve logo base64
        $logoDataUri = $this->resolveLogoDataUri($schoolSettings->school_logo_path);

        // Resolve signature data URIs from private filesystem storage
        $classTeacherSigDataUri = $this->resolveClassTeacherSignatureDataUri($sar);
        $principalSigDataUri = $this->schoolSettingService->getSignatureDataUri('principal');

        if (! $reportConfig) {
            $reportConfig = $this->resolveConfiguration($sar->academic_year_id, $reportType);
        }

        if (! $reportConfig) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                'No active report configuration is available for this report context. Configure a report layout before generating.'
            );
        }

        $configData = $reportConfig->configuration_data ?? [
            'show_term_results' => true,
            'show_assessment_details' => false,
            'show_attendance' => true,
            'show_pass_mark' => true,
        ];

        return match ($reportType) {
            ReportType::TERM => $this->prepareTermReport(
                $sar,
                $term,
                $reportConfig,
                $configData,
                $schoolSettings->school_name,
                $logoDataUri,
                $passMarkThreshold,
                $revisionNumber,
                $generatedAt,
                $classTeacherSigDataUri,
                $principalSigDataUri
            ),
            ReportType::FINAL => $this->prepareFinalReport(
                $sar,
                $reportConfig,
                $configData,
                $schoolSettings->school_name,
                $logoDataUri,
                $passMarkThreshold,
                $revisionNumber,
                $generatedAt,
                $classTeacherSigDataUri,
                $principalSigDataUri
            ),
            ReportType::EXAM => $this->prepareExamReport(
                $sar,
                $reportConfig,
                $configData,
                $schoolSettings->school_name,
                $logoDataUri,
                $passMarkThreshold,
                $revisionNumber,
                $generatedAt,
                $classTeacherSigDataUri,
                $principalSigDataUri
            ),
        };
    }

    /**
     * Prepare Term Report Payload.
     */
    protected function prepareTermReport(
        StudentAcademicRecord $sar,
        Term $term,
        ReportConfiguration $reportConfig,
        array $configData,
        string $schoolName,
        ?string $logoDataUri,
        float $passMarkThreshold,
        int $revisionNumber,
        CarbonImmutable $generatedAt,
        ?string $classTeacherSignatureDataUri = null,
        ?string $principalSignatureDataUri = null
    ): ReportDataPayload {
        $academicYear = $sar->academicYear;
        $schoolClass = $sar->schoolClass;
        $section = $sar->section;
        $student = $sar->student;

        // 1. Resolve displayed assessments for this term strictly from the ReportConfiguration
        $assessmentColumns = [];
        $displayedAssessments = $this->configResolver->getDisplayedAssessmentsForTerm($reportConfig, $term->id);

        foreach ($displayedAssessments as $idx => $asmt) {
            $isTermExam = $this->participationResolver->participatesInTermCalculation($asmt, $term);
            $assessmentColumns[] = new AssessmentColumnHeader(
                id: $asmt->id,
                name: $asmt->name,
                assessmentTypeName: $asmt->assessmentType?->name ?? 'Assessment',
                displayOrder: $idx + 1,
                termId: $asmt->term_id ?: $term->id,
                termName: $asmt->term?->name ?? $term->name,
                isParticipatingInCalculation: $isTermExam
            );
        }

        // 2. Fetch student subject allocations - strictly active and matching current academic context
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
        })->unique(fn ($a) => $a->classSubject?->subject_id)->values();
        $classSubjectIds = $activeAllocations->pluck('class_subject_id')->unique()->values();

        // 3. Applicability maximum marks
        $applicabilities = AssessmentApplicability::query()
            ->whereIn('assessment_id', $displayedAssessments->pluck('id'))
            ->whereIn('class_subject_id', $classSubjectIds)
            ->where('is_active', true)
            ->get();

        // 4. Preload marks for this student placement
        $marks = Mark::query()
            ->where('student_academic_record_id', $sar->id)
            ->whereIn('assessment_applicability_id', $applicabilities->pluck('id'))
            ->get()
            ->keyBy('assessment_applicability_id');

        // 5. Authoritative term calculation from Phase 12 CalculationService
        $calcStudentResult = $this->calculationService->calculateStudentTerm(
            $sar,
            $term
        );

        $subjectRows = [];
        $hasAnyFail = false;
        $hasAnyIncomplete = false;

        foreach ($activeAllocations as $alloc) {
            $cs = $alloc->classSubject;
            if (! $cs) {
                continue;
            }

            // Historical subject snapshot per BR-047
            $subjectName = $cs->subject_name_snapshot ?: ($cs->subject?->name ?? 'Subject');

            $subjectMarks = [];
            foreach ($displayedAssessments as $asmt) {
                $app = $applicabilities->where('class_subject_id', $cs->id)
                    ->where('assessment_id', $asmt->id)
                    ->first();

                $maxMarks = (float) ($app?->maximum_marks ?? 100.00);
                $isTermExam = $this->participationResolver->participatesInTermCalculation($asmt, $term);

                $mark = $app ? $marks->get($app->id) : null;

                $markVal = null;
                $displayMark = '—';
                $status = 'blank';

                if ($mark) {
                    $status = $mark->result_status instanceof \BackedEnum ? $mark->result_status->value : (string) $mark->result_status;
                    if ($status === 'numeric') {
                        $markVal = (float) $mark->mark_value;
                        $displayMark = number_format($markVal, 2, '.', '');
                    } elseif ($status === 'absent') {
                        $markVal = 0.00;
                        $displayMark = 'A';
                    }
                }

                $subjectMarks[$asmt->id] = [
                    'assessment_id' => $asmt->id,
                    'mark_value' => $markVal,
                    'result_status' => $status,
                    'display_mark' => $displayMark,
                    'maximum_marks' => $maxMarks,
                    'is_participating' => $isTermExam,
                ];
            }

            // Find calculated subject term result
            $calcSubj = collect($calcStudentResult->subjectResults)
                ->firstWhere('subjectId', $cs->subject_id);

            $termPct = $calcSubj?->percentage;
            $formattedPct = $termPct !== null ? number_format((float) $termPct, 2, '.', '') . '%' : 'N/A';

            // Pass/Fail evaluation based on authoritative CalculationService
            $subjectStatus = $this->calculationService->evaluatePassFailStatus($termPct, $passMarkThreshold);
            if ($subjectStatus === 'FAIL') {
                $hasAnyFail = true;
            } elseif ($subjectStatus === 'N/A') {
                $hasAnyIncomplete = true;
            }

            $subjectRows[] = new SubjectReportRow(
                classSubjectId: $cs->id,
                subjectId: $cs->subject_id,
                subjectName: $subjectName,
                subjectCode: $cs->subject?->code,
                marks: $subjectMarks,
                termPercentage: $termPct,
                formattedTermPercentage: $formattedPct,
                termStatus: $subjectStatus,
                isComplete: $calcSubj?->isComplete ?? false
            );
        }

        // 6. Attendance
        $attRecord = Attendance::query()
            ->where('student_academic_record_id', $sar->id)
            ->where('term_id', $term->id)
            ->first();

        $daysAttended = $attRecord?->days_attended;
        $totalWorkingDays = $attRecord?->total_working_days;
        $attCalc = $this->attendanceService->calculateAttendance(
            $daysAttended ?? 0,
            $totalWorkingDays ?? 0
        );

        if ($hasAnyFail) {
            $overallResult = 'FAIL';
        } elseif ($hasAnyIncomplete) {
            $overallResult = 'Incomplete';
        } else {
            $overallResult = 'PASS';
        }

        // 7. Assessment column totals for summary row
        $assessmentColumnTotals = [];
        foreach ($displayedAssessments as $asmt) {
            $colObtained = 0.00;
            $colMax = 0.00;
            $hasAnyNumeric = false;
            $hasAnyBlank = false;

            foreach ($subjectRows as $sRow) {
                $m = $sRow->marks[$asmt->id] ?? null;
                if ($m) {
                    $colMax += (float) ($m['maximum_marks'] ?? 100.00);
                    if ($m['result_status'] === 'numeric') {
                        $colObtained += (float) $m['mark_value'];
                        $hasAnyNumeric = true;
                    } elseif ($m['result_status'] === 'blank') {
                        $hasAnyBlank = true;
                    }
                }
            }

            $assessmentColumnTotals[$asmt->id] = [
                'obtained' => round($colObtained, 2),
                'maximum' => round($colMax, 2),
                'formatted_obtained' => number_format($colObtained, 2, '.', ''),
                'formatted_maximum' => number_format($colMax, 2, '.', ''),
                'display' => number_format($colObtained, 2, '.', '') . ' / ' . number_format($colMax, 2, '.', ''),
                'has_blank' => $hasAnyBlank,
            ];
        }

        return new ReportDataPayload(
            reportType: ReportType::TERM,
            reportTitle: "OFFICIAL STUDENT REPORT CARD — {$term->name}",
            academicYearId: $academicYear->id,
            academicYearName: $academicYear->name,
            termId: $term->id,
            termName: $term->name,
            studentId: $student->id,
            studentAcademicRecordId: $sar->id,
            studentName: $student->student_name,
            admissionNumber: $student->admission_number,
            className: $schoolClass->name,
            sectionName: $section->name,
            rollNumber: $sar->roll_number,
            assessmentColumns: $assessmentColumns,
            subjectRows: $subjectRows,
            terms: [],
            attendanceDaysAttended: $daysAttended,
            attendanceTotalWorkingDays: $totalWorkingDays,
            attendancePercentage: $attCalc->percentage,
            attendanceFormatted: $attCalc->formattedPercentage,
            termAttendances: [],
            passMarkThreshold: $passMarkThreshold,
            overallResult: $overallResult,
            schoolName: $schoolName,
            schoolLogoDataUri: $logoDataUri,
            classTeacherSignatureDataUri: $classTeacherSignatureDataUri,
            principalSignatureDataUri: $principalSignatureDataUri,
            revisionNumber: $revisionNumber,
            generatedAt: $generatedAt->format('Y-m-d H:i:s'),
            configurationData: $configData,
            totalObtainedMarks: $calcStudentResult->totalObtainedMarks,
            totalMaximumMarks: $calcStudentResult->totalMaximumMarks,
            overallPercentage: $calcStudentResult->overallPercentage,
            formattedOverallPercentage: $calcStudentResult->formattedOverallPercentage,
            assessmentColumnTotals: $assessmentColumnTotals
        );
    }

    /**
     * Prepare Final Report Payload across dynamic N terms.
     */
    protected function prepareFinalReport(
        StudentAcademicRecord $sar,
        ?ReportConfiguration $reportConfig,
        array $configData,
        string $schoolName,
        ?string $logoDataUri,
        float $passMarkThreshold,
        int $revisionNumber,
        CarbonImmutable $generatedAt,
        ?string $classTeacherSignatureDataUri = null,
        ?string $principalSignatureDataUri = null
    ): ReportDataPayload {
        $academicYear = $sar->academicYear;
        $schoolClass = $sar->schoolClass;
        $section = $sar->section;
        $student = $sar->student;

        // 1. Dynamic terms in sequence order (BR-033, BR-035, BR-062)
        $terms = Term::query()
            ->where('academic_year_id', $sar->academic_year_id)
            ->orderBy('sequence_no')
            ->get();

        $termsPayload = $terms->map(fn (Term $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'sequence_no' => $t->sequence_no,
        ])->toArray();

        // 2. Active allocations strictly for current academic context
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

        // Preload calculation results per term
        $termCalcResults = [];
        $termAttendances = [];
        $allPassedAcrossAllTerms = true;

        foreach ($terms as $t) {
            $calc = $this->calculationService->calculateStudentTerm(
                $sar,
                $t
            );
            $termCalcResults[$t->id] = $calc;

            $att = Attendance::query()
                ->where('student_academic_record_id', $sar->id)
                ->where('term_id', $t->id)
                ->first();

            $daysAtt = $att?->days_attended;
            $totalDays = $att?->total_working_days;
            $attCalc = $this->attendanceService->calculateAttendance($daysAtt ?? 0, $totalDays ?? 0);

            $termAttendances[] = [
                'term_id' => $t->id,
                'term_name' => $t->name,
                'days_attended' => $daysAtt,
                'total_working_days' => $totalDays,
                'percentage' => $attCalc->percentage,
                'formatted' => $attCalc->formattedPercentage,
                'formatted_percentage' => $attCalc->formattedPercentage,
            ];
        }

        // 3. Build subject rows with term summaries
        $subjectRows = [];

        foreach ($activeAllocations as $alloc) {
            $cs = $alloc->classSubject;
            if (! $cs) {
                continue;
            }

            $subjectName = $cs->subject_name_snapshot ?: ($cs->subject?->name ?? 'Subject');
            $termSummaries = [];

            foreach ($terms as $t) {
                $calcStudent = $termCalcResults[$t->id] ?? null;
                $calcSubj = collect($calcStudent?->subjectResults)->firstWhere('subjectId', $cs->subject_id);

                $termPct = $calcSubj?->percentage;
                $formattedPct = $termPct !== null ? number_format((float) $termPct, 2, '.', '') . '%' : 'N/A';

                $status = $this->calculationService->evaluatePassFailStatus($termPct, $passMarkThreshold);
                if ($status !== 'PASS') {
                    $allPassedAcrossAllTerms = false;
                }

                $termSummaries[$t->id] = [
                    'term_id' => $t->id,
                    'term_name' => $t->name,
                    'percentage' => $termPct,
                    'formatted_percentage' => $formattedPct,
                    'status' => $status,
                    'result' => $status,
                    'is_complete' => $calcSubj?->isComplete ?? false,
                ];
            }

            $subjectRows[] = new SubjectReportRow(
                classSubjectId: $cs->id,
                subjectId: $cs->subject_id,
                subjectName: $subjectName,
                subjectCode: $cs->subject?->code,
                marks: [],
                termPercentage: null,
                formattedTermPercentage: 'N/A',
                termStatus: 'N/A',
                isComplete: true,
                termSummaries: $termSummaries
            );
        }

        // 4. Resolve configured non-term assessment(s) (e.g. Annual Exam where term_id IS NULL)
        $annualExamData = null;
        if ($reportConfig) {
            if (! $reportConfig->relationLoaded('assessmentSelections.assessment')) {
                $reportConfig->load('assessmentSelections.assessment');
            }
            $nonTermSelections = $reportConfig->assessmentSelections
                ->filter(fn ($s) => $s->is_displayed && $s->assessment && $s->assessment->term_id === null)
                ->sortBy('display_order')
                ->values();

            if ($nonTermSelections->isNotEmpty()) {
                $annualAssessments = $nonTermSelections->map(fn ($s) => $s->assessment)->values();
                $annualAssessmentIds = $annualAssessments->pluck('id');

                $annualApplicabilities = AssessmentApplicability::query()
                    ->whereIn('assessment_id', $annualAssessmentIds)
                    ->whereIn('class_subject_id', $activeAllocations->pluck('class_subject_id')->unique())
                    ->where('is_active', true)
                    ->with(['assessment.assessmentType'])
                    ->get();

                $annualMarks = Mark::query()
                    ->where('student_academic_record_id', $sar->id)
                    ->whereIn('assessment_applicability_id', $annualApplicabilities->pluck('id'))
                    ->get()
                    ->keyBy('assessment_applicability_id');

                $calcSetting = $this->calculationService->resolveCalculationSetting($sar->academic_year_id, $sar->class_id);

                $annualClassSubjectIds = $annualApplicabilities->pluck('class_subject_id')->unique()->all();
                $annualAllocations = $activeAllocations->filter(
                    fn ($alloc) => in_array($alloc->class_subject_id, $annualClassSubjectIds, true)
                )->values();

                $annualRows = [];
                $annualTotalObtained = 0.00;
                $annualTotalMax = 0.00;
                $annualCalcItems = [];
                $annualHasAnyFail = false;
                $annualHasAnyIncomplete = false;

                foreach ($annualAllocations as $alloc) {
                    $cs = $alloc->classSubject;
                    if (! $cs) {
                        continue;
                    }

                    $subjectName = $cs->subject_name_snapshot ?: ($cs->subject?->name ?? 'Subject');
                    $subjApps = $annualApplicabilities->where('class_subject_id', $cs->id);

                    $app = $subjApps->first();
                    $mark = $app ? $annualMarks->get($app->id) : null;
                    $markStatus = $mark?->result_status instanceof \BackedEnum
                        ? $mark->result_status->value
                        : (string) ($mark?->result_status ?? 'blank');

                    $displayMark = match ($markStatus) {
                        'numeric' => number_format((float) $mark->mark_value, 2, '.', ''),
                        'absent' => 'A',
                        default => '—',
                    };

                    $calcSubj = $this->calculationService->evaluateSubjectAssessments(
                        $cs->subject,
                        $subjApps,
                        $annualMarks,
                        $calcSetting->calculation_method
                    );

                    $pct = $calcSubj->percentage;
                    $status = $this->calculationService->evaluatePassFailStatus($pct, $passMarkThreshold);

                    if ($status === 'FAIL') {
                        $annualHasAnyFail = true;
                    } elseif ($status === 'N/A' || ! $calcSubj->isComplete || $markStatus === 'blank') {
                        $annualHasAnyIncomplete = true;
                    }

                    if ($calcSubj->obtainedMarks !== null) {
                        $annualTotalObtained += $calcSubj->obtainedMarks;
                    }
                    if ($calcSubj->maximumMarks !== null) {
                        $annualTotalMax += $calcSubj->maximumMarks;
                    }

                    $annualCalcItems[] = [
                        'maximum_marks' => $calcSubj->maximumMarks ?? 0.00,
                        'obtained_marks' => $calcSubj->obtainedMarks,
                        'result_status' => $markStatus,
                    ];

                    $annualRows[] = [
                        'subject_id' => $cs->subject_id,
                        'subject_name' => $subjectName,
                        'subject_code' => $cs->subject?->code,
                        'obtained_marks' => $calcSubj->obtainedMarks,
                        'maximum_marks' => $calcSubj->maximumMarks,
                        'display_mark' => $displayMark,
                        'percentage' => $pct,
                        'formatted_percentage' => $calcSubj->formattedPercentage,
                        'status' => $status,
                        'result' => $status,
                        'is_complete' => $calcSubj->isComplete,
                    ];
                }

                $annualCalcResult = match ($calcSetting->calculation_method) {
                    \App\Enums\CalculationMethod::AVERAGE_PERCENTAGE => $this->calculationService->calculateMethod1($annualCalcItems),
                    \App\Enums\CalculationMethod::COMBINED_MARKS => $this->calculationService->calculateMethod2($annualCalcItems),
                };

                $annualTotalPct = $annualCalcResult['percentage'];
                $annualFormattedPct = $annualCalcResult['formatted_percentage'];

                if ($annualHasAnyIncomplete) {
                    $annualAggregateResult = 'Incomplete';
                } elseif ($annualHasAnyFail) {
                    $annualAggregateResult = 'FAIL';
                } else {
                    $annualAggregateResult = 'PASS';
                }

                $primaryAssessment = $annualAssessments->first();
                $annualExamData = [
                    'assessment_id' => $primaryAssessment->id,
                    'assessment_name' => $primaryAssessment->name,
                    'rows' => $annualRows,
                    'total_obtained_marks' => round($annualTotalObtained, 2),
                    'total_maximum_marks' => round($annualTotalMax, 2),
                    'formatted_total_marks' => number_format($annualTotalObtained, 2, '.', '') . ' / ' . number_format($annualTotalMax, 2, '.', ''),
                    'percentage' => $annualTotalPct,
                    'formatted_percentage' => $annualFormattedPct,
                    'status' => $annualAggregateResult,
                    'result' => $annualAggregateResult,
                ];
            }
        }

        $overallResult = $annualExamData !== null
            ? $annualExamData['result']
            : ($allPassedAcrossAllTerms ? 'PASS' : 'FAIL');

        return new ReportDataPayload(
            reportType: ReportType::FINAL,
            reportTitle: 'ANNUAL COMPREHENSIVE REPORT CARD',
            academicYearId: $academicYear->id,
            academicYearName: $academicYear->name,
            termId: null,
            termName: null,
            studentId: $student->id,
            studentAcademicRecordId: $sar->id,
            studentName: $student->student_name,
            admissionNumber: $student->admission_number,
            className: $schoolClass->name,
            sectionName: $section->name,
            rollNumber: $sar->roll_number,
            assessmentColumns: [],
            subjectRows: $subjectRows,
            terms: $termsPayload,
            attendanceDaysAttended: null,
            attendanceTotalWorkingDays: null,
            attendancePercentage: null,
            attendanceFormatted: 'N/A',
            termAttendances: $termAttendances,
            passMarkThreshold: $passMarkThreshold,
            overallResult: $overallResult,
            schoolName: $schoolName,
            schoolLogoDataUri: $logoDataUri,
            classTeacherSignatureDataUri: $classTeacherSignatureDataUri,
            principalSignatureDataUri: $principalSignatureDataUri,
            revisionNumber: $revisionNumber,
            generatedAt: $generatedAt->format('Y-m-d H:i:s'),
            configurationData: $configData,
            totalObtainedMarks: null,
            totalMaximumMarks: null,
            overallPercentage: null,
            formattedOverallPercentage: 'N/A',
            assessmentColumnTotals: [],
            annualExamData: $annualExamData
        );
    }

    /**
     * Prepare Exam (Mid Term Assessment) Report Payload.
     */
    protected function prepareExamReport(
        StudentAcademicRecord $sar,
        ReportConfiguration $reportConfig,
        array $configData,
        string $schoolName,
        ?string $logoDataUri,
        float $passMarkThreshold,
        int $revisionNumber,
        CarbonImmutable $generatedAt,
        ?string $classTeacherSignatureDataUri = null,
        ?string $principalSignatureDataUri = null
    ): ReportDataPayload {
        $academicYear = $sar->academicYear;
        $schoolClass = $sar->schoolClass;
        $section = $sar->section;
        $student = $sar->student;

        // 1. Resolve displayed assessments strictly from ReportConfiguration
        if (! $reportConfig->relationLoaded('assessmentSelections.assessment.assessmentType') || ! $reportConfig->relationLoaded('assessmentSelections.assessment.term')) {
            $reportConfig->load(['assessmentSelections.assessment.assessmentType', 'assessmentSelections.assessment.term']);
        }

        $displayedSelections = $reportConfig->assessmentSelections
            ->where('is_displayed', true)
            ->sortBy('display_order');

        if ($displayedSelections->isEmpty()) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                'The selected report configuration has no displayed assessments configured.'
            );
        }

        $displayedAssessments = $displayedSelections->map(fn ($s) => $s->assessment)->filter()->values();

        $assessmentColumns = [];
        foreach ($displayedAssessments as $idx => $asmt) {
            $assessmentColumns[] = new AssessmentColumnHeader(
                id: $asmt->id,
                name: $asmt->name,
                assessmentTypeName: $asmt->assessmentType?->name ?? 'Assessment',
                displayOrder: $idx + 1,
                termId: $asmt->term_id,
                termName: $asmt->term?->name ?? '',
                isParticipatingInCalculation: true
            );
        }

        // 2. Student active allocations
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
        $classSubjectIds = $activeAllocations->pluck('class_subject_id')->unique()->values();

        // 3. Applicability
        $applicabilities = AssessmentApplicability::query()
            ->whereIn('assessment_id', $displayedAssessments->pluck('id'))
            ->whereIn('class_subject_id', $classSubjectIds)
            ->where('is_active', true)
            ->with(['assessment.assessmentType'])
            ->get();

        // 3b. Restrict subject roster strictly to subjects having active applicability for at least one displayed assessment
        $applicableClassSubjectIds = $applicabilities->pluck('class_subject_id')->unique()->all();
        $reportAllocations = $activeAllocations->filter(
            fn ($a) => in_array($a->class_subject_id, $applicableClassSubjectIds, true)
        )->values();

        // 4. Marks
        $marks = Mark::query()
            ->where('student_academic_record_id', $sar->id)
            ->whereIn('assessment_applicability_id', $applicabilities->pluck('id'))
            ->get()
            ->keyBy('assessment_applicability_id');

        // 5. Calculation setting
        $calcSetting = $this->calculationService->resolveCalculationSetting($sar->academic_year_id, $sar->class_id);

        $subjectRows = [];
        $hasAnyFail = false;
        $hasAnyIncomplete = false;
        $totalObtained = 0.00;
        $totalMax = 0.00;
        $subjectPercentages = [];
        $subjectCalcItems = [];

        foreach ($reportAllocations as $alloc) {
            $cs = $alloc->classSubject;
            if (! $cs) {
                continue;
            }

            $subjectName = $cs->subject_name_snapshot ?: ($cs->subject?->name ?? 'Subject');
            $subjectApplicabilities = $applicabilities->where('class_subject_id', $cs->id);

            $subjectMarks = [];
            foreach ($displayedAssessments as $asmt) {
                $app = $subjectApplicabilities->firstWhere('assessment_id', $asmt->id);
                if (! $app) {
                    $subjectMarks[$asmt->id] = [
                        'assessment_id' => $asmt->id,
                        'mark_value' => null,
                        'result_status' => 'na',
                        'display_mark' => '—',
                        'maximum_marks' => null,
                        'is_participating' => false,
                    ];
                    continue;
                }

                $maxMarks = (float) $app->maximum_marks;
                $mark = $marks->get($app->id);

                $markVal = null;
                $displayMark = '—';
                $status = 'blank';

                if ($mark) {
                    $status = $mark->result_status instanceof \BackedEnum ? $mark->result_status->value : (string) $mark->result_status;
                    if ($status === 'numeric') {
                        $markVal = (float) $mark->mark_value;
                        $displayMark = number_format($markVal, 2, '.', '');
                    } elseif ($status === 'absent') {
                        $markVal = 0.00;
                        $displayMark = 'A';
                    }
                }

                $subjectMarks[$asmt->id] = [
                    'assessment_id' => $asmt->id,
                    'mark_value' => $markVal,
                    'result_status' => $status,
                    'display_mark' => $displayMark,
                    'maximum_marks' => $maxMarks,
                    'is_participating' => true,
                ];
            }

            // Calculation authority evaluation
            $calcSubj = $this->calculationService->evaluateSubjectAssessments(
                $cs->subject,
                $subjectApplicabilities,
                $marks,
                $calcSetting->calculation_method
            );

            $termPct = $calcSubj->percentage;
            $formattedPct = $calcSubj->formattedPercentage;
            $subjectStatus = $this->calculationService->evaluatePassFailStatus($termPct, $passMarkThreshold);

            if ($subjectStatus === 'FAIL') {
                $hasAnyFail = true;
            } elseif ($subjectStatus === 'N/A' || ! $calcSubj->isComplete) {
                $hasAnyIncomplete = true;
            }

            if ($calcSubj->obtainedMarks !== null) {
                $totalObtained += $calcSubj->obtainedMarks;
            }
            if ($calcSubj->maximumMarks !== null) {
                $totalMax += $calcSubj->maximumMarks;
            }
            if ($termPct !== null) {
                $subjectPercentages[] = $termPct;
            }

            $subjectCalcItems[] = [
                'maximum_marks' => $calcSubj->maximumMarks ?? 0.00,
                'obtained_marks' => $calcSubj->obtainedMarks,
                'result_status' => $calcSubj->isComplete
                    ? ($calcSubj->obtainedMarks !== null ? 'numeric' : 'absent')
                    : 'blank',
            ];

            $subjectRows[] = new SubjectReportRow(
                classSubjectId: $cs->id,
                subjectId: $cs->subject_id,
                subjectName: $subjectName,
                subjectCode: $cs->subject?->code,
                marks: $subjectMarks,
                termPercentage: $termPct,
                formattedTermPercentage: $formattedPct,
                termStatus: $subjectStatus,
                isComplete: $calcSubj->isComplete
            );
        }

        // Column totals
        $assessmentColumnTotals = [];
        foreach ($displayedAssessments as $asmt) {
            $colObtained = 0.00;
            $colMax = 0.00;
            $hasAnyBlank = false;

            foreach ($subjectRows as $sRow) {
                $m = $sRow->marks[$asmt->id] ?? null;
                if ($m && ! empty($m['is_participating']) && $m['maximum_marks'] !== null) {
                    $colMax += (float) $m['maximum_marks'];
                    if ($m['result_status'] === 'numeric') {
                        $colObtained += (float) $m['mark_value'];
                    } elseif ($m['result_status'] === 'blank') {
                        $hasAnyBlank = true;
                    }
                }
            }

            $assessmentColumnTotals[$asmt->id] = [
                'obtained' => round($colObtained, 2),
                'maximum' => round($colMax, 2),
                'formatted_obtained' => number_format($colObtained, 2, '.', ''),
                'formatted_maximum' => number_format($colMax, 2, '.', ''),
                'display' => number_format($colObtained, 2, '.', '') . ' / ' . number_format($colMax, 2, '.', ''),
                'has_blank' => $hasAnyBlank,
            ];
        }

        if ($hasAnyIncomplete) {
            $overallResult = 'Incomplete';
        } elseif ($hasAnyFail) {
            $overallResult = 'FAIL';
        } else {
            $overallResult = 'PASS';
        }

        // Overall percentage using configured method via CalculationService
        $overallPercentage = null;
        $formattedOverall = 'N/A';
        if (! $hasAnyIncomplete && ! empty($subjectCalcItems)) {
            $calcResult = match ($calcSetting->calculation_method) {
                \App\Enums\CalculationMethod::AVERAGE_PERCENTAGE => $this->calculationService->calculateMethod1($subjectCalcItems),
                \App\Enums\CalculationMethod::COMBINED_MARKS => $this->calculationService->calculateMethod2($subjectCalcItems),
            };
            $overallPercentage = $calcResult['percentage'];
            $formattedOverall = $calcResult['formatted_percentage'];
        }

        return new ReportDataPayload(
            reportType: ReportType::EXAM,
            reportTitle: "OFFICIAL STUDENT REPORT CARD — {$reportConfig->name}",
            academicYearId: $academicYear->id,
            academicYearName: $academicYear->name,
            termId: null,
            termName: null,
            studentId: $student->id,
            studentAcademicRecordId: $sar->id,
            studentName: $student->student_name,
            admissionNumber: $student->admission_number,
            className: $schoolClass->name,
            sectionName: $section->name,
            rollNumber: $sar->roll_number,
            assessmentColumns: $assessmentColumns,
            subjectRows: $subjectRows,
            terms: [],
            attendanceDaysAttended: null,
            attendanceTotalWorkingDays: null,
            attendancePercentage: null,
            attendanceFormatted: 'N/A',
            termAttendances: [],
            passMarkThreshold: $passMarkThreshold,
            overallResult: $overallResult,
            schoolName: $schoolName,
            schoolLogoDataUri: $logoDataUri,
            classTeacherSignatureDataUri: $classTeacherSignatureDataUri,
            principalSignatureDataUri: $principalSignatureDataUri,
            revisionNumber: $revisionNumber,
            generatedAt: $generatedAt->format('Y-m-d H:i:s'),
            configurationData: $configData,
            totalObtainedMarks: round($totalObtained, 2),
            totalMaximumMarks: round($totalMax, 2),
            overallPercentage: $overallPercentage,
            formattedOverallPercentage: $formattedOverall,
            assessmentColumnTotals: $assessmentColumnTotals
        );
    }

    /**
     * Resolve school logo image into inline Base64 data URI.
     */
    protected function resolveLogoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($logoPath)) {
            $fullPath = $disk->path($logoPath);
            if (file_exists($fullPath)) {
                $mime = File::mimeType($fullPath) ?: 'image/png';
                $content = base64_encode(File::get($fullPath));
                return "data:{$mime};base64,{$content}";
            }
        }

        return null;
    }

    /**
     * Resolve contextual Class Teacher signature data URI for a given StudentAcademicRecord.
     */
    protected function resolveClassTeacherSignatureDataUri(StudentAcademicRecord $sar): ?string
    {
        // Find active Class Teacher assignment for student's classroom placement
        $assignment = \App\Models\TeacherAssignment::query()
            ->where('academic_year_id', $sar->academic_year_id)
            ->where('class_id', $sar->class_id)
            ->where('section_id', $sar->section_id)
            ->where('assignment_type', \App\Enums\TeacherAssignmentType::CLASS_TEACHER)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->first();

        if (! $assignment || ! $assignment->user_id) {
            return null;
        }

        return $this->schoolSettingService->getTeacherSignatureDataUri($assignment->user_id);
    }
}
