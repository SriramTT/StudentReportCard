<?php

namespace App\Services\Report\DTOs;

class SubjectReportRow
{
    /**
     * @param array<int, array{
     *     assessment_id: int,
     *     mark_value: ?float,
     *     result_status: string,
     *     display_mark: string,
     *     maximum_marks: float,
     *     is_participating: bool
     * }> $marks Keyed by assessment_id
     * @param array<int, array{
     *     term_id: int,
     *     term_name: string,
     *     percentage: ?float,
     *     formatted_percentage: string,
     *     status: string,
     *     is_complete: bool
     * }> $termSummaries Keyed by term_id for final reports
     */
    public function __construct(
        public readonly int $classSubjectId,
        public readonly int $subjectId,
        public readonly string $subjectName,
        public readonly ?string $subjectCode = null,
        public readonly array $marks = [],
        public readonly ?float $termPercentage = null,
        public readonly string $formattedTermPercentage = 'N/A',
        public readonly string $termStatus = 'N/A',
        public readonly bool $isComplete = true,
        public readonly array $termSummaries = []
    ) {}

    public function __get(string $key): mixed
    {
        return match ($key) {
            'assessmentMarks' => $this->marks,
            'percentage' => $this->termPercentage,
            'result' => strtoupper($this->termStatus),
            default => null,
        };
    }

    public function __isset(string $key): bool
    {
        return in_array($key, ['assessmentMarks', 'percentage', 'result']);
    }
}
