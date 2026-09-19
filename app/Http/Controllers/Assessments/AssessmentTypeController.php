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
        protected AuditService $auditService
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

        $assessmentType = AssessmentType::create([
            'name' => trim($request->validated('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_ASSESSMENT_TYPE',
            entityType: 'assessment_types',
            entityId: $assessmentType->id,
            beforeData: null,
            afterData: $assessmentType->only(['name', 'is_active']),
            description: "Created assessment type: {$assessmentType->name}"
        );

        return redirect()->route('assessments.types.index')
            ->with('success', 'Assessment type created successfully.');
    }

    public function update(UpdateAssessmentTypeRequest $request, AssessmentType $assessmentType): RedirectResponse
    {
        Gate::authorize('update', $assessmentType);

        $beforeData = $assessmentType->only(['name', 'is_active']);

        $assessmentType->update([
            'name' => trim($request->validated('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $afterData = $assessmentType->fresh()->only(['name', 'is_active']);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'UPDATE_ASSESSMENT_TYPE',
            entityType: 'assessment_types',
            entityId: $assessmentType->id,
            beforeData: $beforeData,
            afterData: $afterData,
            description: "Updated assessment type: {$assessmentType->name}"
        );

        return redirect()->route('assessments.types.index')
            ->with('success', 'Assessment type updated successfully.');
    }
}
