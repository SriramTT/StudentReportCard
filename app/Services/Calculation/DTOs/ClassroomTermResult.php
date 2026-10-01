<?php

namespace App\Services\Calculation\DTOs;

use App\Enums\CalculationMethod;

readonly class ClassroomTermResult
{
    /**
     * @param int $academicYearId
     * @param int $classId
     * @param int $sectionId
     * @param int $termId
     * @param CalculationMethod $calculationMethod
     * @param array<int, StudentTermResult> $studentResults
     * @param int $totalStudents
     * @param int $completeStudents
     * @param int $incompleteStudents
     */
    public function __construct(
        public int $academicYearId,
        public int $classId,
        public int $sectionId,
        public int $termId,
        public CalculationMethod $calculationMethod,
        public array $studentResults,
        public int $totalStudents,
        public int $completeStudents,
        public int $incompleteStudents
    ) {}

    /**
     * Convert DTO to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'section_id' => $this->sectionId,
            'term_id' => $this->termId,
            'calculation_method' => $this->calculationMethod->value,
            'student_results' => array_map(fn (StudentTermResult $r) => $r->toArray(), $this->studentResults),
            'total_students' => $this->totalStudents,
            'complete_students' => $this->completeStudents,
            'incomplete_students' => $this->incompleteStudents,
        ];
    }
}
