<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\StoreAssessmentApplicabilityRequest;
use App\Http\Requests\Assessments\UpdateAssessmentApplicabilityRequest;
use App\Models\Assessment;
use App\Models\AssessmentApplicability;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Services\AssessmentApplicabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssessmentApplicabilityController extends Controller
{
    public function __construct(
        protected AssessmentApplicabilityService $applicabilityService
    ) {}

    public function index(Request $request, Assessment $assessment): View
    {
        Gate::authorize('viewAny', AssessmentApplicability::class);

        $assessment->loadMissing(['academicYear', 'assessmentType', 'term']);

        // CRITICAL: Query all existing class_subject_ids for this assessment independently
        // of any table filters so the Add panel never leaks already-mapped subjects.
        $existingClassSubjectIds = AssessmentApplicability::where('assessment_id', $assessment->id)
            ->pluck('class_subject_id')
            ->toArray();

        // Available class subjects for this assessment's academic year
        $availableClassSubjects = ClassSubject::where('class_subjects.academic_year_id', $assessment->academic_year_id)
            ->where('class_subjects.is_active', true)
            ->whereNotIn('class_subjects.id', $existingClassSubjectIds)
            ->with(['schoolClass', 'section', 'subject'])
            ->join('classes', 'class_subjects.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'class_subjects.section_id', '=', 'sections.id')
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->orderBy('classes.name', 'asc')
            ->orderByRaw('sections.name ASC NULLS FIRST')
            ->orderBy('subjects.name', 'asc')
            ->select('class_subjects.*')
            ->get();

        // Filter parameters
        $selectedClassId = $request->query('class_id');
        $selectedSectionId = $request->query('section_id');
        $selectedSubjectId = $request->query('subject_id');
        $selectedStatus = $request->query('status', 'all');

        // Master filter options
        $filterClasses = SchoolClass::getNaturallySorted(true);
        $filterSubjects = Subject::where('is_active', true)->orderBy('name', 'asc')->get();

        // Sections scoped to the assessment's academic year and selected class if present
        $sectionsQuery = Section::with('schoolClass')
            ->where('academic_year_id', $assessment->academic_year_id)
            ->where('is_active', true);
        if ($selectedClassId) {
            $sectionsQuery->where('class_id', (int) $selectedClassId);
        }
        $filterSections = $sectionsQuery->orderBy('name', 'asc')->get();

        // Reset section selection if it does not belong to the selected class
        if ($selectedClassId && $selectedSectionId && $selectedSectionId !== 'all_sections') {
            if (! $filterSections->contains('id', (int) $selectedSectionId)) {
                $selectedSectionId = null;
            }
        }

        // Query applicability rows with deterministic ordering and optional filters
        $query = AssessmentApplicability::where('assessment_applicability.assessment_id', $assessment->id)
            ->join('class_subjects', 'assessment_applicability.class_subject_id', '=', 'class_subjects.id')
            ->join('classes', 'class_subjects.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'class_subjects.section_id', '=', 'sections.id')
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->with(['classSubject.schoolClass', 'classSubject.section', 'classSubject.subject'])
            ->withCount('marks')
            ->select('assessment_applicability.*');

        if ($selectedClassId) {
            $query->where('class_subjects.class_id', (int) $selectedClassId);
        }

        if ($selectedSectionId) {
            if ($selectedSectionId === 'all_sections') {
                $query->whereNull('class_subjects.section_id');
            } else {
                $query->where('class_subjects.section_id', (int) $selectedSectionId);
            }
        }

        if ($selectedSubjectId) {
            $query->where('class_subjects.subject_id', (int) $selectedSubjectId);
        }

        if ($selectedStatus && $selectedStatus !== 'all') {
            if ($selectedStatus === 'active') {
                $query->where('assessment_applicability.is_active', true);
            } elseif ($selectedStatus === 'inactive') {
                $query->where('assessment_applicability.is_active', false);
            }
        }

        $applicabilities = $query
            ->orderBy('classes.name', 'asc')
            ->orderByRaw('sections.name ASC NULLS FIRST')
            ->orderBy('subjects.name', 'asc')
            ->get();

        $availableClassesWithSubjects = SchoolClass::where('is_active', true)
            ->with(['sections' => fn($q) => $q->where('academic_year_id', $assessment->academic_year_id)->where('is_active', true)])
            ->get()
            ->sortBy('name', SORT_NATURAL)
            ->values()
            ->map(function ($cls) use ($assessment) {
                $subjects = ClassSubject::where('academic_year_id', $assessment->academic_year_id)
                    ->where('class_id', $cls->id)
                    ->where('is_active', true)
                    ->with('subject')
                    ->get()
                    ->pluck('subject')
                    ->unique('id')
                    ->values();

                return (object) [
                    'id' => $cls->id,
                    'name' => $cls->name,
                    'sections' => $cls->sections->pluck('name')->values()->all(),
                    'subjects' => $subjects,
                ];
            });

        return view('assessments.applicability.index', [
            'assessment' => $assessment,
            'applicabilities' => $applicabilities,
            'availableClassSubjects' => $availableClassSubjects,
            'availableClassesWithSubjects' => $availableClassesWithSubjects,
            'filterClasses' => $filterClasses,
            'filterSections' => $filterSections,
            'filterSubjects' => $filterSubjects,
            'selectedClassId' => $selectedClassId,
            'selectedSectionId' => $selectedSectionId,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function store(StoreAssessmentApplicabilityRequest $request, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('create', AssessmentApplicability::class);

        $created = $this->applicabilityService->createApplicabilities($assessment, $request->validated());
        $count = $created->count();
        $message = $count === 1
            ? 'Assessment applicability mapped successfully.'
            : "Assessment applicability mapped successfully for {$count} class subjects.";

        return redirect()->route('assessments.applicability.index', $assessment)
            ->with('success', $message);
    }

    public function update(
        UpdateAssessmentApplicabilityRequest $request,
        Assessment $assessment,
        AssessmentApplicability $applicability
    ): RedirectResponse {
        Gate::authorize('update', $applicability);

        $this->applicabilityService->updateApplicability($applicability, $request->validated());

        return redirect()->back(fallback: route('assessments.applicability.index', $assessment))
            ->with('success', 'Applicability updated successfully.');
    }

    public function destroy(Assessment $assessment, AssessmentApplicability $applicability): RedirectResponse
    {
        Gate::authorize('delete', $applicability);

        if ($applicability->assessment_id !== $assessment->id) {
            abort(404);
        }

        try {
            $this->applicabilityService->deleteApplicability($applicability);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back(fallback: route('assessments.applicability.index', $assessment))
                ->withErrors($e->validator);
        }

        return redirect()->back(fallback: route('assessments.applicability.index', $assessment))
            ->with('success', 'Assessment applicability removed successfully.');
    }
}

