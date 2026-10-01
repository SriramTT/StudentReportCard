<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\StoreAssessmentTypeRequest;
use App\Http\Requests\Assessments\UpdateAssessmentTypeRequest;
use App\Models\AssessmentType;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssessmentTypeController extends Controller
{
    public function __construct(
        protected \App\Services\AssessmentTypeService $assessmentTypeService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', AssessmentType::class);

        $assessmentTypes = AssessmentType::withCount('assessments')
            ->orderBy('name', 'asc')
            ->get();

        return view('assessments.types.index', [
            'assessmentTypes' => $assessmentTypes,
        ]);
    }

    public function store(StoreAssessmentTypeRequest $request): RedirectResponse
    {
        Gate::authorize('create', AssessmentType::class);

        $this->assessmentTypeService->createAssessmentType($request->validated(), (int) Auth::id());

        return redirect()->route('assessments.types.index')
            ->with('success', 'Assessment type created successfully.');
    }

    public function update(UpdateAssessmentTypeRequest $request, AssessmentType $assessmentType): RedirectResponse
    {
        Gate::authorize('update', $assessmentType);

        $this->assessmentTypeService->updateAssessmentType($assessmentType, $request->validated(), (int) Auth::id());

        return redirect()->route('assessments.types.index')
            ->with('success', 'Assessment type updated successfully.');
    }

    public function destroy(AssessmentType $assessmentType): RedirectResponse
    {
        Gate::authorize('delete', $assessmentType);

        try {
            $this->assessmentTypeService->deleteAssessmentType($assessmentType, (int) Auth::id());
        } catch (\DomainException $e) {
            return redirect()->route('assessments.types.index')
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('assessments.types.index')
                ->with('error', 'This assessment type cannot be removed because it is referenced by existing assessments.');
        }

        return redirect()->route('assessments.types.index')
            ->with('success', 'Assessment type removed successfully.');
    }
}
