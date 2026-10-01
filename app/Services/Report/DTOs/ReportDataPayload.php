<?php

namespace App\Services\Report\DTOs;

use App\Enums\ReportType;

class ReportDataPayload
{
    /**
     * @param array<int, AssessmentColumnHeader> $assessmentColumns
     * @param array<int, SubjectReportRow> $subjectRows
     * @param array<int, array{id: int, name: string, sequence_no: int}> $terms
     * @param array<int, array{
     *     term_id: int,
     *     term_name: string,
     *     days_attended: ?int,
     *     total_working_days: ?int,
     *     percentage: ?float,
     *     formatted: string
     * }> $termAttendances
     * @param array<string, mixed> $configurationData
     */
    public readonly ?string $schoolLogoBase64;

    public function __construct(
        public readonly ReportType $reportType,
        public readonly string $reportTitle,
        public readonly int $academicYearId,
        public readonly string $academicYearName,
        public readonly ?int $termId,
        public readonly ?string $termName,
        public readonly int $studentId,
        public readonly int $studentAcademicRecordId,
        public readonly string $studentName,
        public readonly string $admissionNumber,
        public readonly string $className,
        public readonly string $sectionName,
        public readonly int $rollNumber,
        public readonly array $assessmentColumns,
        public readonly array $subjectRows,
        public readonly array $terms = [],
        public readonly ?int $attendanceDaysAttended = null,
        public readonly ?int $attendanceTotalWorkingDays = null,
        public readonly ?float $attendancePercentage = null,
        public readonly string $attendanceFormatted = 'N/A',
        public readonly array $termAttendances = [],
        public readonly float $passMarkThreshold = 35.00,
        public readonly string $overallResult = 'Pass',
        public readonly string $schoolName = '',
        public readonly ?string $schoolLogoDataUri = null,
        ?string $schoolLogoBase64 = null,
        public readonly ?string $classTeacherSignatureDataUri = null,
        public readonly ?string $principalSignatureDataUri = null,
        public readonly int $revisionNumber = 1,
        public readonly string $generatedAt = '',
        public readonly array $configurationData = [],
        public readonly ?float $totalObtainedMarks = null,
        public readonly ?float $totalMaximumMarks = null,
        public readonly ?float $overallPercentage = null,
        public readonly string $formattedOverallPercentage = 'N/A',
        public readonly array $assessmentColumnTotals = [],
        public readonly ?array $annualExamData = null
    ) {
        $this->schoolLogoBase64 = $schoolLogoBase64 ?? $this->schoolLogoDataUri;
    }

    public function __get(string $key): mixed
    {
        if ($key === 'attendance') {
            if ($this->attendanceDaysAttended === null && $this->attendanceTotalWorkingDays === null) {
                if ($this->reportType === ReportType::EXAM) {
                    return [
                        'days_attended' => 'N/A',
                        'total_working_days' => 'N/A',
                        'percentage' => null,
                        'formatted_percentage' => 'N/A',
                        'formatted' => 'N/A',
                    ];
                }
                return null;
            }
            return [
                'days_attended' => $this->attendanceDaysAttended,
                'total_working_days' => $this->attendanceTotalWorkingDays,
                'percentage' => $this->attendancePercentage,
                'formatted_percentage' => $this->attendanceFormatted,
                'formatted' => $this->attendanceFormatted,
            ];
        }

        return null;
    }

    public function __isset(string $key): bool
    {
        if ($key === 'attendance') {
            return $this->attendanceDaysAttended !== null 
                || $this->attendanceTotalWorkingDays !== null
                || $this->reportType === ReportType::EXAM;
        }

        return false;
    }
}
