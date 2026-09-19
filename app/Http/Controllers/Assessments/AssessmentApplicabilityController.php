<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\StoreAssessmentApplicabilityRequest;
use App\Http\Requests\Assessments\UpdateAssessmentApplicabilityRequest;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ClassSubject;
use App\Services\AssessmentApplicabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssessmentApplicabilityController extends Controller
{
    public function __construct(
        protected AssessmentApplicabilityService $applicabilityService
    ) {}

    public function index(Assessment $assessment): View
    {
        Gate::authorize('viewAny', AssessmentApplicability::class);

        $applicabilities = AssessmentApplicability::where('assessment_id', $assessment->id)
            ->with(['classSubject.schoolClass', 'classSubject.section', 'classSubject.subject'])
            ->get();

        // Available class subjects for this assessment's academic year
        $existingClassSubjectIds = $applicabilities->pluck('class_subject_id')->toArray();
        $availableClassSubjects = ClassSubject::where('academic_year_id', $assessment->academic_year_id)
            ->where('is_active', true)
            ->whereNotIn('id', $existingClassSubjectIds)
            ->with(['schoolClass', 'section', 'subject'])
            ->get();

        return view('assessments.applicability.index', [
            'assessment' => $assessment,
            'applicabilities' => $applicabilities,
            'availableClassSubjects' => $availableClassSubjects,
        ]);
    }

    public function store(StoreAssessmentApplicabilityRequest $request, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('create', AssessmentApplicability::class);

        $this->applicabilityService->createApplicability($assessment, $request->validated());

        return redirect()->route('assessments.applicability.index', $assessment)
            ->with('success', 'Assessment applicability mapped successfully.');
    }

    public function update(
        UpdateAssessmentApplicabilityRequest $request,
        Assessment $assessment,
        AssessmentApplicability $applicability
    ): RedirectResponse {
        Gate::authorize('update', $applicability);

        $this->applicabilityService->updateApplicability($applicability, $request->validated());

        return redirect()->route('assessments.applicability.index', $assessment)
            ->with('success', 'Applicability updated successfully.');
    }
}
