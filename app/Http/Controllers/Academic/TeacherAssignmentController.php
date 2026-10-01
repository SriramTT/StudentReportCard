<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreTeacherAssignmentRequest;
use App\Http\Requests\Academic\UpdateTeacherAssignmentRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherAssignmentController extends Controller
{
    public function __construct(
        protected TeacherAssignmentService $teacherAssignmentService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TeacherAssignment::class);

        $query = TeacherAssignment::query()
            ->with(['user.role', 'academicYear', 'schoolClass', 'section', 'subject'])
            ->orderByDesc('id');

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('assignment_type')) {
            $assignmentType = $request->query('assignment_type');
            if ($assignmentType === 'class_teacher') {
                $query->whereNull('subject_id');
            } elseif ($assignmentType === 'subject_teacher') {
                $query->whereNotNull('subject_id');
            }
        }

        $academicYears = AcademicYear::query()->orderByDesc('id')->get();
        $currentAcademicYears = AcademicYear::query()
            ->where('is_current', true)
            ->where('status', \App\Enums\AcademicYearStatus::OPEN)
            ->get();
        $currentAcademicYear = $currentAcademicYears->count() === 1 ? $currentAcademicYears->first() : null;

        $targetYearId = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : ($currentAcademicYear?->id ?? null);

        $selectedClassId = $request->query('class_id');
        $selectedSectionId = $request->query('section_id');

        $filterSections = collect();
        if ($selectedClassId && $targetYearId) {
            $filterSections = Section::query()
                ->where('class_id', (int) $selectedClassId)
                ->where('academic_year_id', $targetYearId)
                ->orderBy('name')
                ->get();
        }

        if ($request->filled('class_id')) {
            $classId = (int) $request->query('class_id');
            $query->where('class_id', $classId);

            if ($request->filled('section_id')) {
                $sectionId = (int) $request->query('section_id');
                // Server-side validation: ensure section belongs to selected class and applicable year scope
                $isSectionValid = $filterSections->contains('id', $sectionId);
                if ($isSectionValid) {
                    $query->where('section_id', $sectionId);
                } else {
                    $selectedSectionId = null;
                }
            }
        } else {
            $selectedSectionId = null;
        }

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $assignments = $query->paginate(20)->withQueryString();

        $classes = SchoolClass::getNaturallySorted(false);
        $sections = Section::query()->with('schoolClass')->orderBy('name')->get();
        $subjects = Subject::query()->orderBy('name')->get();
        $teachers = User::query()
            ->with('role')
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['Subject Teacher', 'Class Teacher']))
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();
        $roles = Role::query()->whereIn('name', ['Subject Teacher', 'Class Teacher'])->get();
        $classSubjects = ClassSubject::query()
            ->where('is_active', true)
            ->with(['subject', 'schoolClass', 'section'])
            ->get();

        return view('academic.teacher-assignments.index', compact(
            'assignments',
            'academicYears',
            'currentAcademicYear',
            'targetYearId',
            'selectedClassId',
            'selectedSectionId',
            'filterSections',
            'classes',
            'sections',
            'subjects',
            'teachers',
            'roles',
            'classSubjects'
        ));
    }

    public function store(StoreTeacherAssignmentRequest $request): RedirectResponse
    {
        $this->teacherAssignmentService->createAssignment($request->validated(), (int) Auth::id());

        return redirect()->route('teacher_assignments.index')
            ->with('success', 'Teacher assignment created successfully.');
    }

    public function update(UpdateTeacherAssignmentRequest $request, TeacherAssignment $teacherAssignment): RedirectResponse
    {
        $this->teacherAssignmentService->updateAssignment($teacherAssignment, $request->validated(), (int) Auth::id());

        return redirect()->route('teacher_assignments.index')
            ->with('success', 'Teacher assignment updated successfully.');
    }

    public function activate(Request $request, TeacherAssignment $teacherAssignment): RedirectResponse
    {
        Gate::authorize('activate', $teacherAssignment);

        $this->teacherAssignmentService->activateAssignment($teacherAssignment, (int) Auth::id());

        return redirect()->route('teacher_assignments.index')
            ->with('success', 'Teacher assignment activated successfully.');
    }

    public function deactivate(Request $request, TeacherAssignment $teacherAssignment): RedirectResponse
    {
        Gate::authorize('deactivate', $teacherAssignment);

        $this->teacherAssignmentService->deactivateAssignment($teacherAssignment, (int) Auth::id());

        return redirect()->route('teacher_assignments.index')
            ->with('success', 'Teacher assignment deactivated successfully.');
    }

    public function destroy(TeacherAssignment $teacherAssignment): RedirectResponse
    {
        Gate::authorize('delete', $teacherAssignment);

        try {
            $this->teacherAssignmentService->deleteAssignment($teacherAssignment, (int) Auth::id());
        } catch (\DomainException $e) {
            return redirect()->route('teacher_assignments.index')
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('teacher_assignments.index')
                ->with('error', 'This assignment cannot be removed because it is referenced by other records.');
        }

        return redirect()->route('teacher_assignments.index')
            ->with('success', 'Teacher assignment removed successfully.');
    }
}
