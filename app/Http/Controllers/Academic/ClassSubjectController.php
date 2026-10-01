<?php

namespace App\Http\Controllers\Academic;

use App\Enums\AcademicYearStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreClassSubjectRequest;
use App\Http\Requests\Academic\UpdateClassSubjectRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Services\ClassSubjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassSubjectController extends Controller
{
    public function __construct(
        protected ClassSubjectService $classSubjectService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ClassSubject::class);

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::getNaturallySorted(true);
        $subjects = Subject::where('is_active', true)->orderBy('name', 'asc')->get();

        $activeYear = AcademicYear::query()
            ->where('is_current', true)
            ->where('status', AcademicYearStatus::OPEN)
            ->first();

        $selectedYearId = $request->query('academic_year_id', $activeYear?->id ?? $academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);
        $selectedClassId = $request->query('class_id');
        $selectedSectionId = $request->query('section_id');
        $selectedStatus = $request->query('status', 'all');

        // Listing section filter options: dynamically scoped to selected listing class if provided
        $listingSectionsQuery = Section::with('schoolClass')->where('is_active', true);
        if ($selectedYearId) {
            $listingSectionsQuery->where('academic_year_id', $selectedYearId);
        }
        if ($selectedClassId) {
            $listingSectionsQuery->where('class_id', $selectedClassId);
        }
        $listingSections = $listingSectionsQuery->orderBy('name', 'asc')->get();

        // Mapping form sections: all active sections for the academic year with class details
        $mappingSectionsQuery = Section::with('schoolClass')->where('is_active', true);
        if ($selectedYearId) {
            $mappingSectionsQuery->where('academic_year_id', $selectedYearId);
        }
        $mappingSections = $mappingSectionsQuery->orderBy('name', 'asc')->get();

        // Deterministic ordering: Class ascending, Section ascending (class-wide first), Subject ascending
        $query = ClassSubject::with(['academicYear', 'schoolClass', 'section.schoolClass', 'subject'])
            ->withCount(['marks', 'studentSubjectAllocations', 'assessmentApplicabilities'])
            ->join('classes', 'class_subjects.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'class_subjects.section_id', '=', 'sections.id')
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->select('class_subjects.*');

        if ($selectedYearId) {
            $query->where('class_subjects.academic_year_id', $selectedYearId);
        }
        if ($selectedClassId) {
            $query->where('class_subjects.class_id', $selectedClassId);
        }
        if ($selectedSectionId) {
            if ($selectedSectionId === 'all_sections') {
                $query->whereNull('class_subjects.section_id');
            } else {
                $query->where('class_subjects.section_id', (int) $selectedSectionId);
            }
        }
        if ($selectedStatus === 'active') {
            $query->where('class_subjects.is_active', true);
        } elseif ($selectedStatus === 'inactive') {
            $query->where('class_subjects.is_active', false);
        }

        $classSubjects = $query->orderBy('classes.name', 'asc')
            ->orderByRaw('CASE WHEN class_subjects.section_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sections.name', 'asc')
            ->orderBy('subjects.name', 'asc')
            ->get();

        // Group into Class + Section groups for expandable table
        $groupedMappings = $classSubjects->groupBy(function ($cs) {
            return $cs->class_id . '_' . ($cs->section_id ?? 'null');
        })->map(function ($items) {
            $first = $items->first();
            $activeCount = $items->where('is_active', true)->count();
            $inactiveCount = $items->where('is_active', false)->count();
            $status = ($inactiveCount === 0) ? 'active' : (($activeCount === 0) ? 'inactive' : 'mixed');

            return (object) [
                'group_key' => $first->class_id . '_' . ($first->section_id ?? 'null'),
                'class_id' => $first->class_id,
                'section_id' => $first->section_id,
                'academic_year_id' => $first->academic_year_id,
                'schoolClass' => $first->schoolClass,
                'section' => $first->section,
                'is_legacy_null' => $first->section_id === null,
                'status' => $status,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'total_count' => $items->count(),
                'mappings' => $items,
            ];
        })->sortBy(function ($group) {
            return $group->schoolClass?->name ?? '';
        }, SORT_NATURAL)->values();

        return view('academic.class-subjects.index', [
            'classSubjects' => $classSubjects,
            'groupedMappings' => $groupedMappings,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'listingSections' => $listingSections,
            'mappingSections' => $mappingSections,
            'subjects' => $subjects,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
            'selectedClassId' => $selectedClassId ? (int) $selectedClassId : null,
            'selectedSectionId' => $selectedSectionId,
            'selectedStatus' => $selectedStatus,
            'activeYear' => $activeYear,
        ]);
    }

    public function store(StoreClassSubjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', ClassSubject::class);

        $result = $this->classSubjectService->mapSubjectsToClass($request->validated());

        $created = $result['created'];
        $skipped = $result['skipped'];
        $message = "Mapped {$created} class subject mapping(s) successfully.";
        if ($skipped > 0) {
            $message .= " ({$skipped} already existing mapping(s) skipped).";
        }

        return redirect()->route('class_subjects.index')
            ->with('success', $message);
    }

    public function update(UpdateClassSubjectRequest $request, ClassSubject $classSubject): RedirectResponse
    {
        Gate::authorize('update', $classSubject);

        $this->classSubjectService->updateClassSubject($classSubject, $request->validated());

        return redirect()->route('class_subjects.index', [
            'academic_year_id' => $classSubject->academic_year_id,
            'class_id' => $classSubject->class_id,
        ])->with('success', 'Class subject updated successfully.');
    }

    public function destroy(ClassSubject $classSubject): RedirectResponse
    {
        Gate::authorize('delete', $classSubject);

        $redirectParams = [
            'academic_year_id' => $classSubject->academic_year_id,
            'class_id' => $classSubject->class_id,
        ];

        try {
            $this->classSubjectService->deleteClassSubject($classSubject);
        } catch (\DomainException $e) {
            return redirect()->route('class_subjects.index', $redirectParams)
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('class_subjects.index', $redirectParams)
                ->with('error', 'This class-subject mapping cannot be removed because it is referenced by other academic records.');
        }

        return redirect()->route('class_subjects.index', $redirectParams)
            ->with('success', 'Class-subject mapping removed successfully.');
    }

    /**
     * Batch toggle active status for an entire class-section group.
     */
    public function updateGroupStatus(Request $request): RedirectResponse
    {
        Gate::authorize('create', ClassSubject::class);

        $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $classId = (int) $request->input('class_id');
        $sectionId = ! empty($request->input('section_id')) && $request->input('section_id') !== 'null' ? (int) $request->input('section_id') : null;
        $academicYearId = (int) $request->input('academic_year_id');
        $isActive = (bool) $request->input('is_active');

        $count = $this->classSubjectService->updateGroupStatus($classId, $sectionId, $academicYearId, $isActive);

        $actionWord = $isActive ? 'activated' : 'deactivated';
        return redirect()->route('class_subjects.index', [
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
        ])->with('success', "Successfully {$actionWord} {$count} subject mapping(s) for the selected group.");
    }

    /**
     * Remove all mappings for a class-section group if completely unreferenced.
     */
    public function destroyGroup(Request $request): RedirectResponse
    {
        Gate::authorize('delete', new ClassSubject());

        $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        $classId = (int) $request->input('class_id');
        $sectionId = ! empty($request->input('section_id')) && $request->input('section_id') !== 'null' ? (int) $request->input('section_id') : null;
        $academicYearId = (int) $request->input('academic_year_id');

        $redirectParams = [
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
        ];

        try {
            $count = $this->classSubjectService->deleteGroupMappings($classId, $sectionId, $academicYearId);
        } catch (\DomainException $e) {
            return redirect()->route('class_subjects.index', $redirectParams)
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('class_subjects.index', $redirectParams)
                ->with('error', 'Cannot remove group mappings because one or more subjects are referenced by other records.');
        }

        return redirect()->route('class_subjects.index', $redirectParams)
            ->with('success', "Successfully removed {$count} class subject mapping(s) for the selected group.");
    }

    /**
     * Synchronize subjects for a class-section group from the Edit modal.
     */
    public function syncGroup(Request $request): RedirectResponse
    {
        Gate::authorize('update', ClassSubject::class);

        $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ]);

        $classId = (int) $request->input('class_id');
        $sectionId = ! empty($request->input('section_id')) && $request->input('section_id') !== 'null' ? (int) $request->input('section_id') : null;
        $academicYearId = (int) $request->input('academic_year_id');
        $subjectIds = array_map('intval', (array) $request->input('subject_ids', []));

        $redirectParams = [
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
        ];

        try {
            $result = $this->classSubjectService->syncGroupSubjects($classId, $sectionId, $academicYearId, $subjectIds);
        } catch (ValidationException $e) {
            return redirect()->route('class_subjects.index', $redirectParams)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\DomainException $e) {
            return redirect()->route('class_subjects.index', $redirectParams)
                ->with('error', $e->getMessage())
                ->withInput();
        }

        $parts = [];
        if ($result['added'] > 0) {
            $parts[] = "{$result['added']} added";
        }
        if ($result['reactivated'] > 0) {
            $parts[] = "{$result['reactivated']} reactivated";
        }
        if ($result['removed'] > 0) {
            $parts[] = "{$result['removed']} removed";
        }
        if ($result['deactivated'] > 0) {
            $parts[] = "{$result['deactivated']} deactivated";
        }
        if ($result['retained'] > 0) {
            $parts[] = "{$result['retained']} retained";
        }

        $summary = ! empty($parts) ? implode(', ', $parts) : 'no changes made';
        $msg = "Group subjects updated: {$summary}.";

        return redirect()->route('class_subjects.index', $redirectParams)
            ->with('success', $msg);
    }
}
