<?php

namespace App\Http\Controllers\Academic;

use App\Enums\AcademicYearStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSectionRequest;
use App\Http\Requests\Academic\UpdateSectionRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AssessmentApplicabilityService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected AssessmentApplicabilityService $applicabilityService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Section::class);

        $activeYears = AcademicYear::query()
            ->where('is_current', true)
            ->where('status', AcademicYearStatus::OPEN)
            ->get();

        $activeYearError = null;
        $activeYear = null;
        if ($activeYears->isEmpty()) {
            $activeYearError = 'No active and open academic year is currently configured. An active academic year is required to manage sections. Please configure one in Academic Years.';
        } elseif ($activeYears->count() > 1) {
            $activeYearError = 'Multiple academic years are marked as current and open. Exactly one academic year must be designated as current and open.';
        } else {
            $activeYear = $activeYears->first();
        }

        // Active classes sorted naturally for dropdowns and display
        $classes = SchoolClass::where('is_active', true)->get()->sortBy('name', SORT_NATURAL)->values();

        $selectedClassId = $request->query('class_id');
        $selectedSectionName = $request->query('section_name');

        $availableSectionNames = collect();
        $groupedSections = collect();

        if ($activeYear) {
            // Distinct section names for filter dropdown scoped to active academic year
            $sectionNamesQuery = Section::where('academic_year_id', $activeYear->id);
            if ($selectedClassId) {
                $sectionNamesQuery->where('class_id', $selectedClassId);
            }
            $availableSectionNames = $sectionNamesQuery->distinct()
                ->pluck('name')
                ->sort(SORT_NATURAL)
                ->values();

            // Clear selected section name if stale/invalid for the selected class
            if ($selectedSectionName && ! $availableSectionNames->contains($selectedSectionName)) {
                $selectedSectionName = null;
            }

            // Query sections in active academic year
            $query = Section::with(['schoolClass'])
                ->withCount(['academicRecords', 'classSubjects', 'teacherAssignments'])
                ->where('academic_year_id', $activeYear->id);

            if ($selectedClassId) {
                $query->where('class_id', $selectedClassId);
            }
            if ($selectedSectionName) {
                $query->where('name', $selectedSectionName);
            }

            $sections = $query->get();

            // Group by class_id
            $groupedSections = $sections->groupBy('class_id')->map(function ($items) {
                $first = $items->first();
                $sortedItems = $items->sortBy('name', SORT_NATURAL)->values();
                $activeCount = $sortedItems->where('is_active', true)->count();
                $inactiveCount = $sortedItems->where('is_active', false)->count();
                $status = ($inactiveCount === 0) ? 'active' : (($activeCount === 0) ? 'inactive' : 'mixed');

                return (object) [
                    'class_id' => $first->class_id,
                    'schoolClass' => $first->schoolClass,
                    'class_name' => $first->schoolClass?->name,
                    'status' => $status,
                    'active_count' => $activeCount,
                    'inactive_count' => $inactiveCount,
                    'total_count' => $sortedItems->count(),
                    'sections' => $sortedItems,
                ];
            });

            // Natural ascending sort on class name (Class 1, Class 2, ... Class 10)
            $groupedSections = $groupedSections->sortBy(function ($group) {
                return $group->class_name;
            }, SORT_NATURAL)->values();
        }

        return view('academic.sections.index', [
            'groupedSections' => $groupedSections,
            'classes' => $classes,
            'availableSectionNames' => $availableSectionNames,
            'selectedClassId' => $selectedClassId ? (int) $selectedClassId : null,
            'selectedSectionName' => $selectedSectionName,
            'activeYear' => $activeYear,
            'activeYearError' => $activeYearError,
            'academicYears' => $activeYears,
            'selectedYearId' => $activeYear?->id,
        ]);
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Section::class);

        $academicYearId = (int) $request->validated('academic_year_id');
        $isActive = $request->boolean('is_active', true);

        return DB::transaction(function () use ($request, $academicYearId, $isActive) {
            $classId = $request->validated('class_id');
            $className = $request->validated('class_name');

            if ($classId) {
                $schoolClass = SchoolClass::findOrFail($classId);
            } else {
                $trimmedClassName = trim((string) $className);
                $schoolClass = SchoolClass::whereRaw('LOWER(name) = ?', [strtolower($trimmedClassName)])->first();

                if (! $schoolClass) {
                    $schoolClass = SchoolClass::create([
                        'name' => $trimmedClassName,
                        'is_active' => true,
                    ]);

                    $this->auditService->logDomainAction(
                        userId: Auth::id(),
                        action: 'CREATE_CLASS',
                        entityType: 'classes',
                        entityId: $schoolClass->id,
                        beforeData: null,
                        afterData: $schoolClass->only(['id', 'name', 'is_active']),
                        description: "Created class {$schoolClass->name} via consolidated Class/Section workflow"
                    );
                }
            }

            // Collect section names
            $sectionNames = [];
            if ($request->filled('section_names') && is_array($request->input('section_names'))) {
                $sectionNames = array_values(array_filter(array_map('trim', $request->input('section_names')), fn($v) => $v !== ''));
            } elseif ($request->filled('name')) {
                $sectionNames = [trim((string) $request->input('name'))];
            }

            if (empty($sectionNames)) {
                $sectionNames = ['A'];
            }

            $createdCount = 0;
            foreach ($sectionNames as $secName) {
                $section = Section::create([
                    'academic_year_id' => $academicYearId,
                    'class_id' => $schoolClass->id,
                    'name' => $secName,
                    'is_active' => $isActive,
                ]);

                $this->auditService->logDomainAction(
                    userId: Auth::id(),
                    action: 'CREATE_SECTION',
                    entityType: 'sections',
                    entityId: $section->id,
                    beforeData: null,
                    afterData: $section->only(['academic_year_id', 'class_id', 'name', 'is_active']),
                    description: "Created section {$section->name} for class {$schoolClass->name}"
                );

                $this->applicabilityService->propagateApplicabilitiesToNewSection($section);

                $createdCount++;
            }

            $message = $createdCount > 1 
                ? "Class '{$schoolClass->name}' and {$createdCount} sections created successfully."
                : "Section created successfully for '{$schoolClass->name}'.";

            return redirect()->route('sections.index')
                ->with('success', $message);
        });
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);

        $beforeData = $section->only(['academic_year_id', 'class_id', 'name', 'is_active']);

        $section->update([
            'name' => trim($request->validated('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $afterData = $section->fresh()->only(['academic_year_id', 'class_id', 'name', 'is_active']);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'UPDATE_SECTION',
            entityType: 'sections',
            entityId: $section->id,
            beforeData: $beforeData,
            afterData: $afterData,
            description: "Updated section {$section->name}"
        );

        return redirect()->route('sections.index', array_filter([
            'class_id' => $request->query('class_id', $section->class_id),
            'section_name' => $request->query('section_name'),
        ]))->with('success', 'Section updated successfully.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        Gate::authorize('delete', $section);

        $redirectParams = array_filter([
            'academic_year_id' => request()->query('academic_year_id', $section->academic_year_id),
            'class_id' => request()->query('class_id', $section->class_id),
            'section_name' => request()->query('section_name'),
        ]);

        if (
            $section->academicRecords()->exists() ||
            $section->classSubjects()->exists() ||
            $section->teacherAssignments()->exists()
        ) {
            return redirect()->route('sections.index', $redirectParams)
                ->with('error', 'This section cannot be removed because it contains enrolled students, subject allocations, or teacher assignments. Deactivate it instead.');
        }

        try {
            DB::transaction(function () use ($section) {
                $beforeData = [
                    'id' => $section->id,
                    'academic_year_id' => $section->academic_year_id,
                    'class_id' => $section->class_id,
                    'name' => $section->name,
                    'is_active' => $section->is_active,
                ];

                $section->delete();

                $this->auditService->logDomainAction(
                    userId: Auth::id(),
                    action: 'DELETE_SECTION',
                    entityType: 'sections',
                    entityId: $beforeData['id'],
                    beforeData: $beforeData,
                    afterData: null,
                    description: "Permanently removed section {$beforeData['name']} from class ID {$beforeData['class_id']}"
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('sections.index', $redirectParams)
                ->with('error', 'This section cannot be removed because it is referenced by other academic records.');
        }

        return redirect()->route('sections.index', $redirectParams)
            ->with('success', 'Section removed successfully.');
    }
}
