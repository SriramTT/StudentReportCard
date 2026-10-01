<?php

namespace App\Services\Calculation\Exceptions;

use RuntimeException;

class CalculationSettingMissingException extends RuntimeException
{
    public function __construct(int $academicYearId, int $classId)
    {
        parent::__construct(
            "Calculation setting is not configured for Academic Year ID {$academicYearId} and Class ID {$classId}. " .
            "An administrator or office staff must configure a calculation method (average_percentage or combined_marks) before term calculations can be performed."
        );
    }
}
