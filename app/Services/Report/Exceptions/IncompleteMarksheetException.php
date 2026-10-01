<?php

namespace App\Services\Report\Exceptions;

use RuntimeException;

class IncompleteMarksheetException extends RuntimeException
{
    /**
     * @param array<int, string> $missingSubjects
     */
    public function __construct(
        public readonly array $missingSubjects,
        public readonly ?string $studentName = null,
        public readonly ?string $admissionNumber = null,
        public readonly ?int $rollNumber = null,
        string $message = 'Cannot generate report card: Marksheet is incomplete. Required marks are missing.'
    ) {
        parent::__construct($message);
    }
}
