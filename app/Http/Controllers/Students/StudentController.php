<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\StudentImportRequest;
use App\Http\Requests\Students\TransferStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentImportService;
use App\Services\StudentPlacementService;
use App\Services\StudentService;
use App\Services\TeacherAuthorizationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        protected StudentService $studentService,
        protected StudentPlacementService $studentPlacementService,
        protected StudentImportService $studentImportService,
        protected TeacherAuthorizationService $teacherAuth
    ) {}

    /**
     * Display student directory with search and academic placement filters.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $user = $request->user();
        $filters = $request->only(['search', 'academic_year_id', 'class_id', 'section_id', 'status']);
        $selectedYearId = !empty($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null;

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $classes = $this->teacherAuth->getAuthorizedClasses($user, $selectedYearId);
        $sections = $this->teacherAuth->getAuthorizedSections($user, $selectedYearId);

        // Sanitize filter selections for teachers so unauthorized filter inputs cannot linger or be forged
        if (! $user->isAdmin() && ! $user->isOfficeStaff()) {
            $authorizedClassIds = $classes->pluck('id')->all();
            if (!empty($filters['class_id']) && !in_array((int) $filters['class_id'], $authorizedClassIds, true)) {
                $filters['class_id'] = null;
            }

            $authorizedSectionIds = $sections->pluck('id')->all();
            if (!empty($filters['section_id']) && !in_array((int) $filters['section_id'], $authorizedSectionIds, true)) {
                $filters['section_id'] = null;
            }

            if (!empty($filters['class_id']) && !empty($filters['section_id'])) {
                if (!$this->teacherAuth->isAuthorizedClassSectionCombination($user, (int) $filters['class_id'], (int) $filters['section_id'], $selectedYearId)) {
                    $filters['section_id'] = null;
                }
            }
        }

        $students = $this->studentService->getPaginatedStudents($filters, 25, $user);

        return view('students.index', [
            'students' => $students,
            'filters' => $filters,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    /**
     * Show form for creating a new student with initial placement.
     */
    public function create(): View
    {
        Gate::authorize('create', Student::class);

        $academicYears = AcademicYear::where('status', 'open')->orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::getNaturallySorted(false);
        $sections = Section::with('schoolClass')->orderBy('name', 'asc')->get();

        return view('students.create', [
            'academicYears' => $academicYears,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    /**
     * Store newly created student and initial placement.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Student::class);

        try {
            $student = $this->studentService->createStudent($request->validated(), (int) Auth::id());
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'uk_sar_active_year_class_section_roll')) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['roll_number' => 'This roll number is already assigned to an active student in the selected class and section.']);
            }
            throw $e;
        }

        return redirect()->route('students.show', $student)
            ->with('success', "Student {$student->student_name} ({$student->admission_number}) created successfully.");
    }

    /**
     * Display student profile, prominent admission number, and placement history.
     */
    public function show(Student $student): View
    {
        Gate::authorize('view', $student);

        $studentWithHistory = $this->studentService->getStudentWithPlacementHistory($student);

        return view('students.show', [
            'student' => $studentWithHistory,
        ]);
    }

    /**
     * Show form to edit student name (admission number is read-only).
     */
    public function edit(Request $request, Student $student): View
    {
        Gate::authorize('update', $student);

        $returnUrl = $this->validateReturnUrl($request->input('return_url', $request->query('return_url')), $student);

        return view('students.edit', [
            'student' => $student,
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * Update student editable fields (student_name).
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $this->studentService->updateStudent($student, $request->validated(), (int) Auth::id());

        $returnUrl = $this->validateReturnUrl($request->input('return_url'), $student);

        return redirect($returnUrl)
            ->with('success', 'Student details updated successfully.');
    }

    /**
     * Remove an unused student permanently.
     */
    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        try {
            $this->studentService->deleteStudent($student, (int) Auth::id());
        } catch (\DomainException $e) {
            return redirect()->route('students.index')
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('students.index')
                ->with('error', 'This student cannot be removed because academic records are associated with this profile.');
        }

        return redirect()->route('students.index')
            ->with('success', "Student {$student->admission_number} removed successfully.");
    }

    /**
     * Show form for internal transfer.
     */
    public function transferForm(Request $request, Student $student): View
    {
        Gate::authorize('transfer', $student);

        $studentWithHistory = $this->studentService->getStudentWithPlacementHistory($student);
        $academicYears = AcademicYear::where('status', 'open')->orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::getNaturallySorted(false);
        $sections = Section::with('schoolClass')->orderBy('name', 'asc')->get();
        $returnUrl = $this->validateReturnUrl($request->input('return_url', $request->query('return_url')), $student);

        return view('students.transfer', [
            'student' => $studentWithHistory,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'sections' => $sections,
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * Process internal transfer.
     */
    public function transfer(TransferStudentRequest $request, Student $student): RedirectResponse
    {
        Gate::authorize('transfer', $student);

        $this->studentPlacementService->transferStudent($student, $request->validated(), (int) Auth::id());

        $returnUrl = $this->validateReturnUrl($request->input('return_url'), $student);

        return redirect($returnUrl)
            ->with('success', "Student {$student->admission_number} successfully transferred to new placement.");
    }

    /**
     * Validate that a return URL strictly matches an authorized internal student workflow destination:
     * - students.index (/students with optional query parameters)
     * - students.show for this specific student (/students/{id})
     *
     * Strictly rejects foreign domains, protocol-relative URLs, javascript: URLs, and other routes.
     */
    protected function validateReturnUrl(?string $returnUrl, Student $student): string
    {
        $fallback = route('students.show', $student);

        if (empty($returnUrl) || ! is_string($returnUrl)) {
            return $fallback;
        }

        // Fast rejection: control characters or obvious attacks
        if (preg_match('/[\x00-\x1F\x7F]/', $returnUrl) || str_starts_with($returnUrl, '//')) {
            return $fallback;
        }

        $appUrl = url('/');
        $parsedApp = parse_url($appUrl);
        $parsed = parse_url($returnUrl);

        if ($parsed === false) {
            return $fallback;
        }

        // If host is specified, it MUST match application host and port exactly
        if (isset($parsed['host'])) {
            $appHost = $parsedApp['host'] ?? request()->getHost();
            if (strcasecmp($parsed['host'], $appHost) !== 0) {
                return $fallback;
            }
            if (isset($parsed['scheme']) && ! in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                return $fallback;
            }
        }

        $path = $parsed['path'] ?? '';

        // If absolute URL, strip base path prefix if any
        if (isset($parsed['host'])) {
            $basePath = $parsedApp['path'] ?? '';
            if (! empty($basePath) && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
        }

        // Normalize path
        $path = '/' . ltrim($path, '/');

        // Allowlist exact student workflow destinations:
        // 1. Directory: '/students'
        // 2. Profile for THIS student: '/students/' . $student->id
        $isIndex = ($path === '/students');
        $isShow = ($path === '/students/' . $student->id);

        if (! $isIndex && ! $isShow) {
            return $fallback;
        }

        $safeUrl = $path;
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            $safeUrl .= '?' . $parsed['query'];
        }

        return $safeUrl;
    }

    /**
     * Show CSV import form.
     */
    public function importForm(): View
    {
        Gate::authorize('import', Student::class);

        $academicYears = AcademicYear::where('status', 'open')->orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::getNaturallySorted(false);
        $sections = Section::with('schoolClass')->orderBy('name', 'asc')->get();

        return view('students.import', [
            'academicYears' => $academicYears,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    /**
     * Process CSV upload and import students.
     */
    public function import(StudentImportRequest $request): View
    {
        Gate::authorize('import', Student::class);

        $results = $this->studentImportService->import(
            $request->file('file'),
            (int) $request->input('academic_year_id'),
            (int) $request->input('class_id'),
            (int) $request->input('section_id'),
            (int) Auth::id()
        );

        $academicYear = AcademicYear::find($request->input('academic_year_id'));
        $schoolClass = SchoolClass::find($request->input('class_id'));
        $section = Section::find($request->input('section_id'));

        return view('students.import_results', [
            'results' => $results,
            'academicYear' => $academicYear,
            'schoolClass' => $schoolClass,
            'section' => $section,
        ]);
    }

    /**
     * Download CSV template for import.
     */
    public function template(): Response
    {
        Gate::authorize('import', Student::class);

        $content = $this->studentImportService->generateTemplate();

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ]);
    }
}
