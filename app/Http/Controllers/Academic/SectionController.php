<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSectionRequest;
use App\Http\Requests\Academic\UpdateSectionRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Section::class);

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::where('is_active', true)->orderBy('name', 'asc')->get();

        $selectedYearId = $request->query('academic_year_id', $academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);
        $selectedClassId = $request->query('class_id');

        $query = Section::with(['academicYear', 'schoolClass'])->orderBy('name', 'asc');
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }
        if ($selectedClassId) {
            $query->where('class_id', $selectedClassId);
        }
        $sections = $query->get();

        return view('academic.sections.index', [
            'sections' => $sections,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
            'selectedClassId' => $selectedClassId ? (int) $selectedClassId : null,
        ]);
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Section::class);

        $section = Section::create([
            'academic_year_id' => (int) $request->validated('academic_year_id'),
            'class_id' => (int) $request->validated('class_id'),
            'name' => trim($request->validated('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_SECTION',
            entityType: 'sections',
            entityId: $section->id,
            beforeData: null,
            afterData: $section->only(['academic_year_id', 'class_id', 'name', 'is_active']),
            description: "Created section {$section->name} for class ID {$section->class_id}"
        );

        return redirect()->route('sections.index', [
            'academic_year_id' => $section->academic_year_id,
            'class_id' => $section->class_id,
        ])->with('success', 'Section created successfully.');
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

        return redirect()->route('sections.index', [
            'academic_year_id' => $section->academic_year_id,
            'class_id' => $section->class_id,
        ])->with('success', 'Section updated successfully.');
    }
}
