<?php

namespace App\Services\Calculation;

use App\Enums\CalculationMethod;
use App\Enums\MarkResultStatus;
use App\Enums\StudentPlacementStatus;
use App\Models\AssessmentApplicability;
use App\Models\CalculationSetting;
use App\Models\ClassSubject;
use App\Models\Mark;
use App\Models\StudentAcademicRecord;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Calculation\Contracts\CalculationParticipationResolverInterface;
use App\Services\Calculation\DTOs\ClassroomTermResult;
use App\Services\Calculation\DTOs\StudentTermResult;
use App\Services\Calculation\DTOs\SubjectTermResult;
use App\Services\Calculation\Exceptions\CalculationSettingMissingException;
use Illuminate\Support\Collection;

class CalculationService
{
    public function __construct(
        protected CalculationParticipationResolverInterface $participationResolver
    ) {}

    /**
     * Resolve the configured calculation setting for an academic year and class.
     * Throws an explicit exception if no setting is configured (no silent fallback).
     *
     * @throws CalculationSettingMissingException
     */
    public function resolveCalculationSetting(int $academicYearId, int $classId): CalculationSetting
    {
        $setting = CalculationSetting::query()
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->first();

        if ($setting === null) {
            throw new CalculationSettingMissingException($academicYearId, $classId);
        }

        return $setting;
    }

    /**
     * Authoritative evaluation of a subject across a set of assessment applicabilities and marks.
     * Reuses the existing subject evaluation pipeline (Method 1 / Method 2, absent/blank rules, rounding).
     *
     * @param Collection<int, AssessmentApplicability> $applicabilities
     * @param Collection<int, Mark> $marksByApplicabilityId
     */
    public function evaluateSubjectAssessments(
        Subject $subject,
        Collection $applicabilities,
        Collection $marksByApplicabilityId,
        CalculationMethod $method
    ): SubjectTermResult {
        return $this->evaluateSubjectFromPreloaded(
            $subject,
            $applicabilities,
            $marksByApplicabilityId,
            $method
        );
    }

    /**
     * Determine PASS/FAIL result status based on percentage and pass mark threshold.
     */
    public function evaluatePassFailStatus(?float $percentage, float $passMarkThreshold): string
    {
        if ($percentage === null) {
            return 'N/A';
        }

        return $percentage >= $passMarkThreshold ? 'PASS' : 'FAIL';
    }

    /**
     * Calculate term results for a specific student academic record and target term.
     */
    public function calculateStudentTerm(StudentAcademicRecord $sar, Term $targetTerm): StudentTermResult
    {
        $sar->loadMissing([
            'student',
            'subjectAllocations' => function ($q) {
                $q->with(['classSubject.subject']);
            },
        ]);

        $setting = $this->resolveCalculationSetting($sar->academic_year_id, $sar->class_id);
        $method = $setting->calculation_method;

        // Collect all valid active class_subject_ids for this student
        $validAllocations = $sar->subjectAllocations->filter(function ($allocation) use ($sar) {
            if (! $allocation->is_active) {
                return false;
            }
            $cs = $allocation->classSubject;
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

        $classSubjectIds = $validAllocations
            ->pluck('class_subject_id')
            ->unique()
            ->values()
            ->all();

        // Eager load applicable assessments for target term
        $applicabilities = AssessmentApplicability::query()
            ->whereIn('class_subject_id', $classSubjectIds)
            ->where('is_active', true)
            ->whereHas('assessment', function ($q) use ($targetTerm) {
                $q->where('term_id', $targetTerm->id);
            })
            ->with(['assessment.assessmentType', 'classSubject'])
            ->get()
            ->filter(fn (AssessmentApplicability $app) => $this->participationResolver->participatesInTermCalculation($app->assessment, $targetTerm))
            ->values();

        // Eager load marks for this student placement
        $marks = Mark::query()
            ->where('student_academic_record_id', $sar->id)
            ->whereIn('assessment_applicability_id', $applicabilities->pluck('id'))
            ->get()
            ->keyBy('assessment_applicability_id');

        $subjectResults = [];
        $totalObtainedMarks = 0.00;
        $totalMaximumMarks = 0.00;
        $allSubjectsComplete = true;
        $hasAnyParticipatingAssessments = false;
        $allParticipatingAssessmentsAbsent = true;

        foreach ($validAllocations as $allocation) {
            $classSubject = $allocation->classSubject;
            if ($classSubject === null || $classSubject->subject === null) {
                continue;
            }

            $subject = $classSubject->subject;
            $subjectApplicabilities = $applicabilities->where('class_subject_id', $classSubject->id);

            $subjectResult = $this->evaluateSubjectFromPreloaded(
                $subject,
                $subjectApplicabilities,
                $marks,
                $method
            );

            $subjectResults[] = $subjectResult;

            if ($subjectResult->isAvailable) {
                $hasAnyParticipatingAssessments = true;
                $totalObtainedMarks += $subjectResult->obtainedMarks ?? 0.00;
                $totalMaximumMarks += $subjectResult->maximumMarks ?? 0.00;

                // Check if this subject had any non-absent marks
                foreach ($subjectResult->participatingAssessments as $pa) {
                    if ($pa['result_status'] !== MarkResultStatus::ABSENT->value) {
                        $allParticipatingAssessmentsAbsent = false;
                    }
                }
            }

            if (! $subjectResult->isComplete) {
                $allSubjectsComplete = false;
            }
        }

        // Compute overall student term metrics
        if (! $hasAnyParticipatingAssessments || $totalMaximumMarks <= 0) {
            return new StudentTermResult(
                studentAcademicRecordId: $sar->id,
                studentId: $sar->student_id,
                rollNumber: $sar->roll_number,
                studentName: $sar->student?->student_name ?? '—',
                calculationMethod: $method,
                subjectResults: $subjectResults,
                totalObtainedMarks: null,
                totalMaximumMarks: null,
                overallPercentage: null,
                formattedOverallPercentage: 'N/A',
                isComplete: $allSubjectsComplete && $hasAnyParticipatingAssessments,
                isAvailable: false
            );
        }

        if (! $allSubjectsComplete) {
            return new StudentTermResult(
                studentAcademicRecordId: $sar->id,
                studentId: $sar->student_id,
                rollNumber: $sar->roll_number,
                studentName: $sar->student?->student_name ?? '—',
                calculationMethod: $method,
                subjectResults: $subjectResults,
                totalObtainedMarks: $totalObtainedMarks,
                totalMaximumMarks: $totalMaximumMarks,
                overallPercentage: null,
                formattedOverallPercentage: 'Incomplete',
                isComplete: false,
                isAvailable: true
            );
        }

        // All-absent rule across all participating assessments: 0.00% complete
        if ($allParticipatingAssessmentsAbsent) {
            return new StudentTermResult(
                studentAcademicRecordId: $sar->id,
                studentId: $sar->student_id,
                rollNumber: $sar->roll_number,
                studentName: $sar->student?->student_name ?? '—',
                calculationMethod: $method,
                subjectResults: $subjectResults,
                totalObtainedMarks: 0.00,
                totalMaximumMarks: $totalMaximumMarks,
                overallPercentage: 0.00,
                formattedOverallPercentage: '0.00%',
                isComplete: true,
                isAvailable: true
            );
        }

        // Calculate Overall Term Percentage according to the configured method
        $overallPercentage = match ($method) {
            CalculationMethod::AVERAGE_PERCENTAGE => $this->calculateOverallMethod1($subjectResults),
            CalculationMethod::COMBINED_MARKS => $this->calculateOverallMethod2($totalObtainedMarks, $totalMaximumMarks),
        };

        $formattedOverall = $overallPercentage !== null
            ? number_format($overallPercentage, 2, '.', '') . '%'
            : 'N/A';

        return new StudentTermResult(
            studentAcademicRecordId: $sar->id,
            studentId: $sar->student_id,
            rollNumber: $sar->roll_number,
            studentName: $sar->student?->student_name ?? '—',
            calculationMethod: $method,
            subjectResults: $subjectResults,
            totalObtainedMarks: round($totalObtainedMarks, 2),
            totalMaximumMarks: round($totalMaximumMarks, 2),
            overallPercentage: $overallPercentage,
            formattedOverallPercentage: $formattedOverall,
            isComplete: true,
            isAvailable: true
        );
    }

    /**
     * Calculate term results for an entire classroom roster in a single set-based pass without N+1 queries.
     */
    public function calculateClassroomTerm(int $academicYearId, int $classId, int $sectionId, int $termId): ClassroomTermResult
    {
        $setting = $this->resolveCalculationSetting($academicYearId, $classId);
        $method = $setting->calculation_method;

        $targetTerm = Term::query()
            ->where('academic_year_id', $academicYearId)
            ->where('id', $termId)
            ->firstOrFail();

        // 1. Fetch active students in section
        $sars = StudentAcademicRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('status', StudentPlacementStatus::ACTIVE)
            ->with([
                'student',
                'subjectAllocations.classSubject.subject',
            ])
            ->orderBy('roll_number')
            ->get();

        if ($sars->isEmpty()) {
            return new ClassroomTermResult(
                academicYearId: $academicYearId,
                classId: $classId,
                sectionId: $sectionId,
                termId: $termId,
                calculationMethod: $method,
                studentResults: [],
                totalStudents: 0,
                completeStudents: 0,
                incompleteStudents: 0
            );
        }

        // 2. Collect all valid active class subjects in this classroom
        $validAllocationsBySar = [];
        $allValidClassSubjectIds = [];
        foreach ($sars as $sar) {
            $validAllocs = $sar->subjectAllocations->filter(function ($allocation) use ($sar) {
                if (! $allocation->is_active) {
                    return false;
                }
                $cs = $allocation->classSubject;
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
            $validAllocationsBySar[$sar->id] = $validAllocs;
            foreach ($validAllocs as $va) {
                $allValidClassSubjectIds[] = $va->class_subject_id;
            }
        }
        $classSubjectIds = array_values(array_unique($allValidClassSubjectIds));

        // 3. Eager load all participating assessment applicabilities for target term
        $applicabilities = AssessmentApplicability::query()
            ->whereIn('class_subject_id', $classSubjectIds)
            ->where('is_active', true)
            ->whereHas('assessment', function ($q) use ($targetTerm) {
                $q->where('term_id', $targetTerm->id);
            })
            ->with(['assessment.assessmentType', 'classSubject'])
            ->get()
            ->filter(fn (AssessmentApplicability $app) => $this->participationResolver->participatesInTermCalculation($app->assessment, $targetTerm))
            ->values();

        // 4. Batch load all marks for these students and applicabilities
        $allMarks = Mark::query()
            ->whereIn('student_academic_record_id', $sars->pluck('id'))
            ->whereIn('assessment_applicability_id', $applicabilities->pluck('id'))
            ->get()
            ->groupBy('student_academic_record_id');

        $studentResults = [];
        $completeCount = 0;
        $incompleteCount = 0;

        foreach ($sars as $sar) {
            $studentMarks = ($allMarks->get($sar->id) ?? collect())->keyBy('assessment_applicability_id');

            $subjectResults = [];
            $totalObtainedMarks = 0.00;
            $totalMaximumMarks = 0.00;
            $allSubjectsComplete = true;
            $hasAnyParticipating = false;
            $allAbsent = true;

            $sarAllocs = $validAllocationsBySar[$sar->id] ?? collect();
            foreach ($sarAllocs as $allocation) {
                $classSubject = $allocation->classSubject;
                if ($classSubject === null || $classSubject->subject === null) {
                    continue;
                }

                $subject = $classSubject->subject;
                $subjectApps = $applicabilities->where('class_subject_id', $classSubject->id);

                $subjectRes = $this->evaluateSubjectFromPreloaded(
                    $subject,
                    $subjectApps,
                    $studentMarks,
                    $method
                );

                $subjectResults[] = $subjectRes;

                if ($subjectRes->isAvailable) {
                    $hasAnyParticipating = true;
                    $totalObtainedMarks += $subjectRes->obtainedMarks ?? 0.00;
                    $totalMaximumMarks += $subjectRes->maximumMarks ?? 0.00;

                    foreach ($subjectRes->participatingAssessments as $pa) {
                        if ($pa['result_status'] !== MarkResultStatus::ABSENT->value) {
                            $allAbsent = false;
                        }
                    }
                }

                if (! $subjectRes->isComplete) {
                    $allSubjectsComplete = false;
                }
            }

            if (! $hasAnyParticipating || $totalMaximumMarks <= 0) {
                $incompleteCount++;
                $studentResults[] = new StudentTermResult(
                    studentAcademicRecordId: $sar->id,
                    studentId: $sar->student_id,
                    rollNumber: $sar->roll_number,
                    studentName: $sar->student?->student_name ?? '—',
                    calculationMethod: $method,
                    subjectResults: $subjectResults,
                    totalObtainedMarks: null,
                    totalMaximumMarks: null,
                    overallPercentage: null,
                    formattedOverallPercentage: 'N/A',
                    isComplete: false,
                    isAvailable: false
                );
                continue;
            }

            if (! $allSubjectsComplete) {
                $incompleteCount++;
                $studentResults[] = new StudentTermResult(
                    studentAcademicRecordId: $sar->id,
                    studentId: $sar->student_id,
                    rollNumber: $sar->roll_number,
                    studentName: $sar->student?->student_name ?? '—',
                    calculationMethod: $method,
                    subjectResults: $subjectResults,
                    totalObtainedMarks: $totalObtainedMarks,
                    totalMaximumMarks: $totalMaximumMarks,
                    overallPercentage: null,
                    formattedOverallPercentage: 'Incomplete',
                    isComplete: false,
                    isAvailable: true
                );
                continue;
            }

            // All-absent case
            if ($allAbsent) {
                $completeCount++;
                $studentResults[] = new StudentTermResult(
                    studentAcademicRecordId: $sar->id,
                    studentId: $sar->student_id,
                    rollNumber: $sar->roll_number,
                    studentName: $sar->student?->student_name ?? '—',
                    calculationMethod: $method,
                    subjectResults: $subjectResults,
                    totalObtainedMarks: 0.00,
                    totalMaximumMarks: $totalMaximumMarks,
                    overallPercentage: 0.00,
                    formattedOverallPercentage: '0.00%',
                    isComplete: true,
                    isAvailable: true
                );
                continue;
            }

            $overallPct = match ($method) {
                CalculationMethod::AVERAGE_PERCENTAGE => $this->calculateOverallMethod1($subjectResults),
                CalculationMethod::COMBINED_MARKS => $this->calculateOverallMethod2($totalObtainedMarks, $totalMaximumMarks),
            };

            $formattedPct = $overallPct !== null
                ? number_format($overallPct, 2, '.', '') . '%'
                : 'N/A';

            $completeCount++;
            $studentResults[] = new StudentTermResult(
                studentAcademicRecordId: $sar->id,
                studentId: $sar->student_id,
                rollNumber: $sar->roll_number,
                studentName: $sar->student?->student_name ?? '—',
                calculationMethod: $method,
                subjectResults: $subjectResults,
                totalObtainedMarks: round($totalObtainedMarks, 2),
                totalMaximumMarks: round($totalMaximumMarks, 2),
                overallPercentage: $overallPct,
                formattedOverallPercentage: $formattedPct,
                isComplete: true,
                isAvailable: true
            );
        }

        return new ClassroomTermResult(
            academicYearId: $academicYearId,
            classId: $classId,
            sectionId: $sectionId,
            termId: $termId,
            calculationMethod: $method,
            studentResults: $studentResults,
            totalStudents: count($studentResults),
            completeStudents: $completeCount,
            incompleteStudents: $incompleteCount
        );
    }

    /**
     * Evaluate a subject's term result from preloaded applicabilities and marks.
     *
     * @param Collection<int, AssessmentApplicability> $applicabilities
     * @param Collection<int, Mark> $marksByApplicabilityId
     */
    protected function evaluateSubjectFromPreloaded(
        Subject $subject,
        Collection $applicabilities,
        Collection $marksByApplicabilityId,
        CalculationMethod $method
    ): SubjectTermResult {
        if ($applicabilities->isEmpty()) {
            return new SubjectTermResult(
                subjectId: $subject->id,
                subjectName: $subject->name,
                participatingAssessments: [],
                obtainedMarks: null,
                maximumMarks: null,
                percentage: null,
                formattedPercentage: 'N/A',
                isComplete: true,
                isAvailable: false
            );
        }

        $participatingDetails = [];
        $allMarksComplete = true;
        $allAbsent = true;

        foreach ($applicabilities as $app) {
            $assessment = $app->assessment;
            $maxMarks = (float) $app->maximum_marks;
            $mark = $marksByApplicabilityId->get($app->id);

            $status = $mark?->result_status ?? MarkResultStatus::BLANK;
            $val = $mark?->mark_value !== null ? (float) $mark->mark_value : null;

            if ($status === MarkResultStatus::BLANK) {
                $allMarksComplete = false;
                $allAbsent = false;
                $obtainedForCalc = null;
                $asstPct = null;
            } elseif ($status === MarkResultStatus::ABSENT) {
                $obtainedForCalc = 0.00;
                $asstPct = 0.00;
            } else { // NUMERIC
                $allAbsent = false;
                $obtainedForCalc = $val ?? 0.00;
                $asstPct = $maxMarks > 0 ? round(($obtainedForCalc / $maxMarks) * 100, 4) : 0.00;
            }

            $participatingDetails[] = [
                'assessment_id' => $assessment->id,
                'name' => $assessment->name,
                'maximum_marks' => $maxMarks,
                'result_status' => $status->value,
                'mark_value' => $val,
                'obtained_marks' => $obtainedForCalc,
                'assessment_percentage' => $asstPct,
            ];
        }

        $totalMax = array_sum(array_column($participatingDetails, 'maximum_marks'));

        if (! $allMarksComplete) {
            return new SubjectTermResult(
                subjectId: $subject->id,
                subjectName: $subject->name,
                participatingAssessments: $participatingDetails,
                obtainedMarks: null,
                maximumMarks: $totalMax,
                percentage: null,
                formattedPercentage: 'Incomplete',
                isComplete: false,
                isAvailable: true
            );
        }

        // All-absent rule (BR-013): percentage is 0.00%
        if ($allAbsent) {
            return new SubjectTermResult(
                subjectId: $subject->id,
                subjectName: $subject->name,
                participatingAssessments: $participatingDetails,
                obtainedMarks: 0.00,
                maximumMarks: $totalMax,
                percentage: 0.00,
                formattedPercentage: '0.00%',
                isComplete: true,
                isAvailable: true
            );
        }

        // Execute configured formula
        $calcResult = match ($method) {
            CalculationMethod::AVERAGE_PERCENTAGE => $this->calculateMethod1($participatingDetails),
            CalculationMethod::COMBINED_MARKS => $this->calculateMethod2($participatingDetails),
        };

        return new SubjectTermResult(
            subjectId: $subject->id,
            subjectName: $subject->name,
            participatingAssessments: $participatingDetails,
            obtainedMarks: $calcResult['obtained_marks'],
            maximumMarks: $calcResult['maximum_marks'],
            percentage: $calcResult['percentage'],
            formattedPercentage: $calcResult['formatted_percentage'],
            isComplete: true,
            isAvailable: $calcResult['is_available']
        );
    }

    /**
     * Method 1: Equal Average of Included Assessment Percentages.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array{obtained_marks: float|null, maximum_marks: float|null, percentage: float|null, formatted_percentage: string, is_available: bool}
     */
    public function calculateMethod1(array $items): array
    {
        if (empty($items)) {
            return [
                'obtained_marks' => null,
                'maximum_marks' => null,
                'percentage' => null,
                'formatted_percentage' => 'N/A',
                'is_available' => false,
            ];
        }

        $percentages = [];
        $totalObtained = 0.00;
        $totalMax = 0.00;

        foreach ($items as $item) {
            $max = (float) $item['maximum_marks'];
            $status = $item['result_status'] ?? MarkResultStatus::NUMERIC->value;

            if ($status === MarkResultStatus::BLANK->value) {
                return [
                    'obtained_marks' => null,
                    'maximum_marks' => null,
                    'percentage' => null,
                    'formatted_percentage' => 'Incomplete',
                    'is_available' => true,
                ];
            }

            if ($status === MarkResultStatus::ABSENT->value) {
                $obtained = 0.00;
            } else {
                $obtained = (float) ($item['obtained_marks'] ?? $item['mark_value'] ?? 0.00);
            }

            $totalObtained += $obtained;
            $totalMax += $max;

            if ($max > 0) {
                $percentages[] = ($obtained / $max) * 100;
            } else {
                $percentages[] = 0.00;
            }
        }

        if (empty($percentages)) {
            return [
                'obtained_marks' => 0.00,
                'maximum_marks' => 0.00,
                'percentage' => null,
                'formatted_percentage' => 'N/A',
                'is_available' => false,
            ];
        }

        $average = array_sum($percentages) / count($percentages);
        $rounded = round($average, 2);

        return [
            'obtained_marks' => round($totalObtained, 2),
            'maximum_marks' => round($totalMax, 2),
            'percentage' => $rounded,
            'formatted_percentage' => number_format($rounded, 2, '.', '') . '%',
            'is_available' => true,
        ];
    }

    /**
     * Method 2: Sum Obtained / Sum Maximum * 100.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array{obtained_marks: float|null, maximum_marks: float|null, percentage: float|null, formatted_percentage: string, is_available: bool}
     */
    public function calculateMethod2(array $items): array
    {
        if (empty($items)) {
            return [
                'obtained_marks' => null,
                'maximum_marks' => null,
                'percentage' => null,
                'formatted_percentage' => 'N/A',
                'is_available' => false,
            ];
        }

        $totalObtained = 0.00;
        $totalMax = 0.00;

        foreach ($items as $item) {
            $max = (float) $item['maximum_marks'];
            $status = $item['result_status'] ?? MarkResultStatus::NUMERIC->value;

            if ($status === MarkResultStatus::BLANK->value) {
                return [
                    'obtained_marks' => null,
                    'maximum_marks' => null,
                    'percentage' => null,
                    'formatted_percentage' => 'Incomplete',
                    'is_available' => true,
                ];
            }

            if ($status === MarkResultStatus::ABSENT->value) {
                $obtained = 0.00;
            } else {
                $obtained = (float) ($item['obtained_marks'] ?? $item['mark_value'] ?? 0.00);
            }

            $totalObtained += $obtained;
            $totalMax += $max;
        }

        if ($totalMax <= 0) {
            return [
                'obtained_marks' => round($totalObtained, 2),
                'maximum_marks' => round($totalMax, 2),
                'percentage' => null,
                'formatted_percentage' => 'N/A',
                'is_available' => false,
            ];
        }

        $pct = ($totalObtained / $totalMax) * 100;
        $rounded = round($pct, 2);

        return [
            'obtained_marks' => round($totalObtained, 2),
            'maximum_marks' => round($totalMax, 2),
            'percentage' => $rounded,
            'formatted_percentage' => number_format($rounded, 2, '.', '') . '%',
            'is_available' => true,
        ];
    }

    /**
     * Compute overall term percentage across subjects under Method 1.
     *
     * @param array<int, SubjectTermResult> $subjectResults
     */
    protected function calculateOverallMethod1(array $subjectResults): ?float
    {
        $validPercentages = [];

        foreach ($subjectResults as $result) {
            if ($result->isAvailable && $result->percentage !== null) {
                $validPercentages[] = $result->percentage;
            }
        }

        if (empty($validPercentages)) {
            return null;
        }

        return round(array_sum($validPercentages) / count($validPercentages), 2);
    }

    /**
     * Compute overall term percentage across subjects under Method 2.
     */
    protected function calculateOverallMethod2(float $totalObtained, float $totalMax): ?float
    {
        if ($totalMax <= 0) {
            return null;
        }

        return round(($totalObtained / $totalMax) * 100, 2);
    }
}
