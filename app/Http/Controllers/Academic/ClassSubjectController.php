<?php

namespace App\Http\Controllers\Academic;

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
        $classes = SchoolClass::where('is_active', true)->orderBy('name', 'asc')->get();
        $subjects = Subject::where('is_active', true)->orderBy('name', 'asc')->get();

        $selectedYearId = $request->query('academic_year_id', $academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);
        $selectedClassId = $request->query('class_id');

        $sections = Section::where('is_active', true);
        if ($selectedYearId) {
            $sections->where('academic_year_id', $selectedYearId);
        }
        if ($selectedClassId) {
            $sections->where('class_id', $selectedClassId);
        }
        $availableSections = $sections->orderBy('name', 'asc')->get();

        $query = ClassSubject::with(['academicYear', 'schoolClass', 'section', 'subject']);
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }
        if ($selectedClassId) {
            $query->where('class_id', $selectedClassId);
        }
        $classSubjects = $query->orderBy('academic_year_id', 'desc')
            ->orderBy('class_id', 'asc')
            ->get();

        return view('academic.class-subjects.index', [
            'classSubjects' => $classSubjects,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'availableSections' => $availableSections,
            'subjects' => $subjects,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
            'selectedClassId' => $selectedClassId ? (int) $selectedClassId : null,
        ]);
    }

    public function store(StoreClassSubjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', ClassSubject::class);

        $classSubject = $this->classSubjectService->createClassSubject($request->validated());

        return redirect()->route('class_subjects.index', [
            'academic_year_id' => $classSubject->academic_year_id,
            'class_id' => $classSubject->class_id,
        ])->with('success', 'Class subject mapped successfully with snapshot.');
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
}
