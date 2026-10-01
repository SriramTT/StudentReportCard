<?php

namespace App\Services\Calculation\Contracts;

use App\Models\Assessment;
use App\Models\Term;

interface CalculationParticipationResolverInterface
{
    /**
     * Determine whether an assessment contributes marks towards the term percentage calculation
     * for the specified target term.
     *
     * In accordance with BRD V1.3 (BR-028) and DEC-021, only assessments of type 'Term Exam'
     * belonging to the target term participate in the term percentage.
     */
    public function participatesInTermCalculation(Assessment $assessment, Term $targetTerm): bool;
}
