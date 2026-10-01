<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GenerateReportRequest;
use App\Models\AcademicYear;
use App\Models\GeneratedReport;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentAcademicRecord;
use App\Models\Term;
use App\Services\Report\Exceptions\IncompleteMarksheetException;
use App\Services\Report\ReportCompletionService;
use App\Services\Report\ReportConfigurationResolver;
use App\Services\Report\ReportGenerationService;
use App\Services\TeacherAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReportGenerationController extends Controller
{
    public function __construct(
        protected ReportGenerationService $generationService,
        protected ReportCompletionService $completionService,
        protected TeacherAuthorizationService $teacherAuth,
        protected ReportConfigurationResolver $configResolver
    ) {}

    /**
     * Display the report card generation dashboard and student roster.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GeneratedReport::class);

        $user = Auth::user();
        $isAdminOrOffice = $user->isAdmin() || $user->isOfficeStaff();

        // 1. Academic Years
        $academicYears = AcademicYear::query()->orderBy('start_date', 'desc')->get();
        $selectedYearId = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : ($academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);

        // Fetch active assignments for teacher in this year
        $activeAssignments = (! $isAdminOrOffice && $selectedYearId)
            ? $this->teacherAuth->getActiveAssignments($user, $selectedYearId)
            : collect();

        // 2. Classes
        if ($isAdminOrOffice) {
            $availableClasses = SchoolClass::getNaturallySorted(true);
        } else {
            $classTeacherAssignments = $activeAssignments->filter(fn ($a) => $a->isClassTeacher());
            $classIds = $classTeacherAssignments->pluck('class_id')->unique()->filter()->values();
            $availableClasses = SchoolClass::getNaturallySorted(true, $classIds->all());
        }

        $selectedClassId = $request->filled('class_id')
            ? (int) $request->query('class_id')
            : ($availableClasses->first()?->id);

        if ($selectedClassId && ! $availableClasses->contains('id', $selectedClassId)) {
            $selectedClassId = $availableClasses->first()?->id;
        }

        // 3. Sections
        $availableSections = collect();
        if ($selectedClassId && $selectedYearId) {
            if ($isAdminOrOffice) {
                $availableSections = Section::query()
                    ->where('academic_year_id', $selectedYearId)
                    ->where('class_id', $selectedClassId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            } else {
                $sectionIds = $activeAssignments->filter(fn ($a) => $a->isClassTeacher() && (int) $a->class_id === $selectedClassId)
                    ->pluck('section_id')
                    ->unique()
                    ->filter()
                    ->values();

                $availableSections = Section::query()
                    ->where('academic_year_id', $selectedYearId)
                    ->where('class_id', $selectedClassId)
                    ->whereIn('id', $sectionIds)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
        }

        $selectedSectionId = $request->filled('section_id')
            ? (int) $request->query('section_id')
            : ($availableSections->first()?->id);

        if ($selectedSectionId && ! $availableSections->contains('id', $selectedSectionId)) {
            $selectedSectionId = $availableSections->first()?->id;
        }

        // 4. Report Type (term vs final vs exam vs mid_term vs custom)
        $selectedReportType = $request->query('report_type', 'term');
        if (! in_array($selectedReportType, ['term', 'final', 'exam', 'mid_term', 'custom'], true)) {
            $selectedReportType = 'term';
        }

        // 5. Terms
        $availableTerms = collect();
        if ($selectedYearId) {
            $availableTerms = Term::query()
                ->where('academic_year_id', $selectedYearId)
                ->orderBy('sequence_no')
                ->get();
        }

        $selectedTermId = $request->filled('term_id')
            ? (int) $request->query('term_id')
            : ($availableTerms->first()?->id);

        if ($selectedTermId && ! $availableTerms->contains('id', $selectedTermId)) {
            $selectedTermId = $availableTerms->first()?->id;
        }

        // 6. Report Configurations
        $availableConfigs = collect();
        if ($selectedYearId) {
            $availableConfigs = $this->configResolver->getAvailableConfigurations(
                $selectedYearId,
                $selectedReportType,
                $selectedClassId,
                $selectedSectionId
            );
        }

        $selectedConfigId = null;
        if ($request->filled('report_configuration_id')) {
            $reqConfigId = (int) $request->query('report_configuration_id');
            if ($availableConfigs->contains('id', $reqConfigId)) {
                $selectedConfigId = $reqConfigId;
            }
        }

        // Preselect if exactly one configuration is available and none chosen yet
        if ($selectedConfigId === null && $availableConfigs->count() === 1) {
            $selectedConfigId = $availableConfigs->first()?->id;
        }

        $selectedConfig = $selectedConfigId ? $availableConfigs->firstWhere('id', $selectedConfigId) : null;

        // 7. Student Roster & Completion Evaluation
        $students = collect();
        $canGenerate = false;

        if ($selectedYearId && $selectedClassId && $selectedSectionId) {
            $canGenerate = $this->teacherAuth->userCanGenerateReport($user, $selectedYearId, $selectedClassId, $selectedSectionId);

            $dbReportType = in_array($selectedReportType, ['mid_term', 'custom'], true) ? 'exam' : $selectedReportType;

            $students = StudentAcademicRecord::with([
                'student',
                'generatedReports' => function ($query) use ($dbReportType, $selectedTermId) {
                    $query->where('report_type', $dbReportType);
                    if ($dbReportType === 'term' && $selectedTermId) {
                        $query->where('term_id', $selectedTermId);
                    }
                    $query->orderBy('revision_number', 'desc')
                        ->with('generatedByUser');
                },
            ])
                ->where('academic_year_id', $selectedYearId)
                ->where('class_id', $selectedClassId)
                ->where('section_id', $selectedSectionId)
                ->where('status', \App\Enums\StudentPlacementStatus::ACTIVE)
                ->orderBy('roll_number')
                ->get()
                ->map(function ($sar) use ($selectedReportType, $selectedTermId, $selectedConfig) {
                    $effectiveTermId = $selectedReportType === 'term' ? $selectedTermId : null;
                    $isComplete = false;
                    $missingAssessments = [];

                    if ($selectedConfig && ($selectedReportType === 'final' || ($selectedReportType === 'term' && $effectiveTermId) || in_array($selectedReportType, ['exam', 'mid_term', 'custom'], true))) {
                        $isComplete = $this->completionService->isComplete($sar->id, $selectedReportType, $effectiveTermId, $selectedConfig);
                        if (! $isComplete) {
                            $missingAssessments = $this->completionService->getMissingAssessments($sar->id, $selectedReportType, $effectiveTermId, $selectedConfig);
                        }
                    } elseif (! $selectedConfig) {
                        $missingAssessments = ['No active report configuration is available for this report context. Configure a report layout before generating.'];
                    }

                    $sar->setAttribute('is_complete', $isComplete);
                    $sar->setAttribute('missing_assessments', $missingAssessments);

                    return $sar;
                });
        }

        return view('reports.index', compact(
            'academicYears',
            'selectedYearId',
            'availableClasses',
            'selectedClassId',
            'availableSections',
            'selectedSectionId',
            'availableTerms',
            'selectedTermId',
            'selectedReportType',
            'availableConfigs',
            'selectedConfigId',
            'selectedConfig',
            'students',
            'canGenerate'
        ));
    }

    /**
     * Preview report card HTML without persisting or incrementing revision.
     */
    public function preview(GenerateReportRequest $request): Response
    {
        $validated = $request->validated();
        $sar = StudentAcademicRecord::findOrFail($validated['student_academic_record_id']);

        Gate::authorize('generate', [GeneratedReport::class, $sar->academic_year_id, $sar->class_id, $sar->section_id]);

        $internalReportType = in_array($validated['report_type'], ['mid_term', 'custom'], true) ? 'exam' : $validated['report_type'];
        $termId = $internalReportType === 'term' ? (isset($validated['term_id']) ? (int) $validated['term_id'] : null) : null;
        $assessmentId = $internalReportType === 'exam'
            ? (isset($validated['assessment_id']) ? (int) $validated['assessment_id'] : null)
            : null;

        try {
            $html = $this->generationService->preview(
                sarId: (int) $validated['student_academic_record_id'],
                reportType: $internalReportType,
                termId: $termId,
                assessmentId: $assessmentId,
                reportConfigurationId: (int) $validated['report_configuration_id']
            );

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (IncompleteMarksheetException $e) {
            $missingList = implode('', array_map(fn ($item) => "<li>" . e($item) . "</li>", $e->missingSubjects));
            $errorHtml = "<div style='font-family: sans-serif; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>"
                . "<h3 style='margin-top:0;'>Report Generation Blocked: Incomplete Marksheet</h3>"
                . "<p>" . e($e->getMessage()) . "</p>"
                . "<ul>{$missingList}</ul>"
                . "</div>";

            return response($errorHtml, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (UnprocessableEntityHttpException $e) {
            $errorHtml = "<div style='font-family: sans-serif; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>"
                . "<h3 style='margin-top:0;'>Report Generation Blocked: Configuration Error</h3>"
                . "<p>" . e($e->getMessage()) . "</p>"
                . "</div>";

            return response($errorHtml, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
    }

    /**
     * Handle generation of a formal report PDF revision.
     */
    public function generate(GenerateReportRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $sar = StudentAcademicRecord::findOrFail($validated['student_academic_record_id']);

        Gate::authorize('generate', [GeneratedReport::class, $sar->academic_year_id, $sar->class_id, $sar->section_id]);

        $internalReportType = in_array($validated['report_type'], ['mid_term', 'custom'], true) ? 'exam' : $validated['report_type'];
        $termId = $internalReportType === 'term' ? (isset($validated['term_id']) ? (int) $validated['term_id'] : null) : null;
        $assessmentId = $internalReportType === 'exam'
            ? (isset($validated['assessment_id']) ? (int) $validated['assessment_id'] : null)
            : null;

        try {
            $report = $this->generationService->generate(
                sarId: (int) $validated['student_academic_record_id'],
                reportType: $internalReportType,
                termId: $termId,
                assessmentId: $assessmentId,
                user: Auth::user(),
                reportConfigurationId: (int) $validated['report_configuration_id']
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'report_id' => $report->id,
                    'revision' => $report->revision_number,
                    'download_url' => route('reports.download', $report),
                    'message' => "Successfully generated {$report->report_type->value} report (Revision {$report->revision_number}).",
                ]);
            }

            return redirect()->back()->with('success', "Report card generated successfully (Revision {$report->revision_number}).");
        } catch (IncompleteMarksheetException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'missing' => $e->missingSubjects,
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['error' => $e->getMessage()])
                ->with('missing_assessments', $e->missingSubjects)
                ->withInput();
        } catch (UnprocessableEntityHttpException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }
}
