<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\StoreAssessmentRequest;
use App\Http\Requests\Assessments\UpdateAssessmentRequest;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Term;
use App\Services\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        protected AssessmentService $assessmentService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Assessment::class);

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $selectedYearId = $request->query('academic_year_id', $academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);

        $terms = Term::query();
        if ($selectedYearId) {
            $terms->where('academic_year_id', $selectedYearId);
        }
        $availableTerms = $terms->orderBy('sequence_no', 'asc')->get();
        $assessmentTypes = AssessmentType::where('is_active', true)->orderBy('name', 'asc')->get();

        // --- Display filters (Type, Term, Status) ---
        // These are optional, user-facing filters. Values are validated before use.
        $filterTypeId = $request->query('assessment_type_id');
        $filterTermId = $request->query('term_id');   // 'none' = whereNull term_id; integer = specific term
        $filterStatus = $request->query('status');

        // Sanitise: only accept integer-like assessment_type_id values
        if ($filterTypeId !== null && $filterTypeId !== '' && ! ctype_digit((string) $filterTypeId)) {
            $filterTypeId = null;
        }

        // Sanitise: term_id must be 'none' or a positive integer string
        if ($filterTermId !== null && $filterTermId !== '' && $filterTermId !== 'none' && ! ctype_digit((string) $filterTermId)) {
            $filterTermId = null;
        }

        // Sanitise: status must be a known AssessmentStatus enum value
        if ($filterStatus !== null && $filterStatus !== '' && ! in_array($filterStatus, ['active', 'inactive'], true)) {
            $filterStatus = null;
        }

        $query = Assessment::with(['academicYear', 'term', 'assessmentType'])->withCount('applicabilities');

        // Internal academic-year scoping — preserved; not exposed as a user-facing filter.
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }

        // Assessment Type filter
        if ($filterTypeId !== null && $filterTypeId !== '') {
            $query->where('assessment_type_id', (int) $filterTypeId);
        }

        // Term filter
        // 'none' selects assessments with no assigned term (cross-term / final).
        // An integer string selects assessments for a specific term.
        if ($filterTermId === 'none') {
            $query->whereNull('term_id');
        } elseif ($filterTermId !== null && $filterTermId !== '') {
            $query->where('term_id', (int) $filterTermId);
        }

        // Status filter — queries the status column using the AssessmentStatus enum string values.
        if ($filterStatus !== null && $filterStatus !== '') {
            $query->where('status', $filterStatus);
        }

        // Ordering: Default unfiltered listing shows newest created assessments first (created_at DESC, id DESC).
        // When user-facing filters are active, preserve existing date-based descending order.
        $hasUserFilters = ($filterTypeId !== null && $filterTypeId !== '')
            || ($filterTermId !== null && $filterTermId !== '')
            || ($filterStatus !== null && $filterStatus !== '');

        if ($hasUserFilters) {
            $query->orderByRaw('assessment_date DESC NULLS LAST')
                ->orderBy('id', 'desc');
        } else {
            $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
        }

        $assessments = $query->get();

        return view('assessments.index', [
            'assessments'     => $assessments,
            'academicYears'   => $academicYears,
            'availableTerms'  => $availableTerms,
            'assessmentTypes' => $assessmentTypes,
            'selectedYearId'  => $selectedYearId ? (int) $selectedYearId : null,
            'filterTypeId'    => $filterTypeId !== null && $filterTypeId !== '' ? (int) $filterTypeId : null,
            'filterTermId'    => $filterTermId ?: null,
            'filterStatus'    => $filterStatus ?: null,
        ]);
    }

    public function store(StoreAssessmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Assessment::class);

        $assessment = $this->assessmentService->createAssessment($request->validated());

        return redirect()->route('assessments.index', ['academic_year_id' => $assessment->academic_year_id])
            ->with('success', 'Assessment created successfully.');
    }

    public function update(UpdateAssessmentRequest $request, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('update', $assessment);

        $this->assessmentService->updateAssessment($assessment, $request->validated());

        return redirect()->route('assessments.index', ['academic_year_id' => $assessment->academic_year_id])
            ->with('success', 'Assessment updated successfully.');
    }

    public function destroy(Assessment $assessment): RedirectResponse
    {
        Gate::authorize('delete', $assessment);

        $academicYearId = $assessment->academic_year_id;

        try {
            $this->assessmentService->deleteAssessment($assessment);
        } catch (\DomainException $e) {
            return redirect()->route('assessments.index', ['academic_year_id' => $academicYearId])
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('assessments.index', ['academic_year_id' => $academicYearId])
                ->with('error', 'This assessment cannot be removed because it is referenced by other academic records.');
        }

        return redirect()->route('assessments.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'Assessment removed successfully.');
    }
}
