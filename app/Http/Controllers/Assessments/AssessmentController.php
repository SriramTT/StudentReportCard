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

        $terms = Term::where('is_active', true);
        if ($selectedYearId) {
            $terms->where('academic_year_id', $selectedYearId);
        }
        $availableTerms = $terms->orderBy('sequence_no', 'asc')->get();
        $assessmentTypes = AssessmentType::where('is_active', true)->orderBy('name', 'asc')->get();

        $query = Assessment::with(['academicYear', 'term', 'assessmentType'])->withCount('applicabilities');
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }
        $assessments = $query->orderBy('academic_year_id', 'desc')
            ->orderBy('assessment_date', 'desc')
            ->get();

        return view('assessments.index', [
            'assessments' => $assessments,
            'academicYears' => $academicYears,
            'availableTerms' => $availableTerms,
            'assessmentTypes' => $assessmentTypes,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
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
}
