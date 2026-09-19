<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportConfigurationRequest;
use App\Http\Requests\Reports\UpdateReportConfigurationRequest;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ReportConfiguration;
use App\Services\ReportConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportConfigurationController extends Controller
{
    public function __construct(
        protected ReportConfigurationService $reportConfigService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ReportConfiguration::class);

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $selectedYearId = $request->query('academic_year_id');

        $query = ReportConfiguration::with(['academicYear', 'assessmentSelections.assessment']);
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }
        $configurations = $query->orderBy('name', 'asc')->get();

        // Assessments available for selection based on selected year
        $assessments = Assessment::where('status', 'active');
        if ($selectedYearId) {
            $assessments->where('academic_year_id', $selectedYearId);
        }
        $availableAssessments = $assessments->orderBy('name', 'asc')->get();

        return view('reports.configurations.index', [
            'configurations' => $configurations,
            'academicYears' => $academicYears,
            'availableAssessments' => $availableAssessments,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
        ]);
    }

    public function store(StoreReportConfigurationRequest $request): RedirectResponse
    {
        Gate::authorize('create', ReportConfiguration::class);

        $config = $this->reportConfigService->createReportConfiguration($request->validated());

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $config->academic_year_id])
            ->with('success', 'Report configuration created successfully.');
    }

    public function update(UpdateReportConfigurationRequest $request, ReportConfiguration $reportConfiguration): RedirectResponse
    {
        Gate::authorize('update', $reportConfiguration);

        $this->reportConfigService->updateReportConfiguration($reportConfiguration, $request->validated());

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $reportConfiguration->academic_year_id])
            ->with('success', 'Report configuration updated successfully.');
    }
}
