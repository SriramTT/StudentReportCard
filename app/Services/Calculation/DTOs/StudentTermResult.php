<?php

namespace App\Services\Calculation\DTOs;

use App\Enums\CalculationMethod;

readonly class StudentTermResult
{
    /**
     * @param int $studentAcademicRecordId
     * @param int $studentId
     * @param string|null $rollNumber
     * @param string $studentName
     * @param CalculationMethod $calculationMethod
     * @param array<int, SubjectTermResult> $subjectResults
     * @param float|null $totalObtainedMarks
     * @param float|null $totalMaximumMarks
     * @param float|null $overallPercentage
     * @param string $formattedOverallPercentage
     * @param bool $isComplete False if any required subject is incomplete
     * @param bool $isAvailable True if calculation was possible
     */
    public function __construct(
        public int $studentAcademicRecordId,
        public int $studentId,
        public ?string $rollNumber,
        public string $studentName,
        public CalculationMethod $calculationMethod,
        public array $subjectResults,
        public ?float $totalObtainedMarks,
        public ?float $totalMaximumMarks,
        public ?float $overallPercentage,
        public string $formattedOverallPercentage,
        public bool $isComplete,
        public bool $isAvailable
    ) {}

    /**
     * Convert DTO to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_academic_record_id' => $this->studentAcademicRecordId,
            'student_id' => $this->studentId,
            'roll_number' => $this->rollNumber,
            'student_name' => $this->studentName,
            'calculation_method' => $this->calculationMethod->value,
            'subject_results' => array_map(fn (SubjectTermResult $r) => $r->toArray(), $this->subjectResults),
            'total_obtained_marks' => $this->totalObtainedMarks,
            'total_maximum_marks' => $this->totalMaximumMarks,
            'overall_percentage' => $this->overallPercentage,
            'formatted_overall_percentage' => $this->formattedOverallPercentage,
            'is_complete' => $this->isComplete,
            'is_available' => $this->isAvailable,
        ];
    }
}
