<?php

namespace App\Services\Report\DTOs;

class AssessmentColumnHeader
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $assessmentTypeName,
        public readonly int $displayOrder,
        public readonly ?int $termId = null,
        public readonly ?string $termName = null,
        public readonly bool $isParticipatingInCalculation = false,
        public readonly ?float $maxMarks = null
    ) {}

    public function __get(string $key): mixed
    {
        return match ($key) {
            'assessmentName' => $this->name,
            'assessmentId' => $this->id,
            default => null,
        };
    }

    public function __isset(string $key): bool
    {
        return in_array($key, ['assessmentName', 'assessmentId', 'maxMarks']);
    }
}
