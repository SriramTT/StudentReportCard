<?php

namespace App\Services\Calculation\DTOs;

readonly class SubjectTermResult
{
    /**
     * @param int $subjectId
     * @param string $subjectName
     * @param array<int, array<string, mixed>> $participatingAssessments
     * @param float|null $obtainedMarks Total obtained marks across participating assessments
     * @param float|null $maximumMarks Total contextual maximum marks across participating assessments
     * @param float|null $percentage Calculated percentage rounded to 2 decimal places (null if incomplete or unavailable)
     * @param string $formattedPercentage E.g., '85.00%' or 'N/A'
     * @param bool $isComplete False if any required participating mark is blank
     * @param bool $isAvailable True if participating assessments exist with maximum marks > 0
     */
    public function __construct(
        public int $subjectId,
        public string $subjectName,
        public array $participatingAssessments,
        public ?float $obtainedMarks,
        public ?float $maximumMarks,
        public ?float $percentage,
        public string $formattedPercentage,
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
            'subject_id' => $this->subjectId,
            'subject_name' => $this->subjectName,
            'participating_assessments' => $this->participatingAssessments,
            'obtained_marks' => $this->obtainedMarks,
            'maximum_marks' => $this->maximumMarks,
            'percentage' => $this->percentage,
            'formatted_percentage' => $this->formattedPercentage,
            'is_complete' => $this->isComplete,
            'is_available' => $this->isAvailable,
        ];
    }
}
