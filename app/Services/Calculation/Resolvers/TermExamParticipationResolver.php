<?php

namespace App\Services\Calculation\Resolvers;

use App\Models\Assessment;
use App\Models\Term;
use App\Services\Calculation\Contracts\CalculationParticipationResolverInterface;

class TermExamParticipationResolver implements CalculationParticipationResolverInterface
{
    /**
     * Authoritative Term Exam Participation Rule:
     * Under BRD V1.3 (BR-028) and DEC-021, ONLY assessments of type 'Term Exam'
     * that belong to the specified target term contribute to the term percentage calculation.
     *
     * Class Tests, Unit Tests, Weekly Tests, or assessments belonging to other terms do NOT participate.
     */
    public function participatesInTermCalculation(Assessment $assessment, Term $targetTerm): bool
    {
        // 1. Verify assessment belongs to the explicit target term (historical or active)
        if ((int) $assessment->term_id !== (int) $targetTerm->id) {
            return false;
        }

        // 2. Resolve Assessment Type
        if (! $assessment->relationLoaded('assessmentType')) {
            $assessment->load('assessmentType');
        }

        $typeName = trim($assessment->assessmentType?->name ?? '');
        $asmtName = trim($assessment->name ?? '');

        // 3. Exact case-insensitive match on 'Term Exam'
        if (strcasecmp($typeName, 'Term Exam') === 0) {
            return true;
        }

        // Extract target term number if present (e.g., 'Term 1' => 1)
        $targetTermNumber = null;
        if (preg_match('/(\d+)/', $targetTerm->name, $targetMatches)) {
            $targetTermNumber = (int) $targetMatches[1];
        }

        // 4. Term-number-aware assessment type names such as 'Term 1', 'Term 2', 'Term Exam 1', etc.
        if (preg_match('/^term(\s*exam)?\s*(\d+)$/i', $typeName, $typeMatches)) {
            $typeTermNumber = (int) $typeMatches[2];
            if ($targetTermNumber !== null) {
                return $typeTermNumber === $targetTermNumber;
            }
            return true;
        }

        // 5. Assessment names such as 'Term Exam-1', 'Term Exam 1', 'Term Exam-2', 'Term Exam 2'
        if (preg_match('/^term\s*exam\D*(\d+)$/i', $asmtName, $asmtMatches)) {
            $asmtTermNumber = (int) $asmtMatches[1];
            if ($targetTermNumber !== null) {
                return $asmtTermNumber === $targetTermNumber;
            }
            return true;
        }

        return false;
    }
}
