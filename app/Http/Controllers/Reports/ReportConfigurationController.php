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
        $selectedReportType = $request->query('report_type');

        $query = ReportConfiguration::with([
            'academicYear',
            'assessmentSelections' => fn ($q) => $q->orderBy('display_order', 'asc')->with(['assessment.academicYear', 'assessment.term', 'assessment.assessmentType']),
        ]);

        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }

        if ($selectedReportType && $selectedReportType !== 'all') {
            match ($selectedReportType) {
                'term' => $query->where('report_type', \App\Enums\ReportType::TERM),
                'final' => $query->where('report_type', \App\Enums\ReportType::FINAL),
                'mid_term' => $query->where('report_type', \App\Enums\ReportType::EXAM)
                    ->whereRaw("(configuration_data->>'subtype') = 'mid_term'"),
                'custom' => $query->where('report_type', \App\Enums\ReportType::EXAM)
                    ->whereRaw("((configuration_data->>'subtype') = 'custom' OR (configuration_data->>'subtype') IS NULL)"),
                default => null,
            };
        }

        $configurations = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Assessments available for selection based on selected year
        $assessments = Assessment::with(['academicYear', 'term', 'assessmentType'])->where('status', 'active');
        if ($selectedYearId) {
            $assessments->where('academic_year_id', $selectedYearId);
        }
        $availableAssessments = $assessments->orderBy('name', 'asc')->get();

        return view('reports.configurations.index', [
            'configurations' => $configurations,
            'academicYears' => $academicYears,
            'availableAssessments' => $availableAssessments,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
            'selectedReportType' => $selectedReportType ?: 'all',
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

    public function destroy(ReportConfiguration $reportConfiguration): RedirectResponse
    {
        Gate::authorize('delete', $reportConfiguration);

        $academicYearId = $reportConfiguration->academic_year_id;

        try {
            $this->reportConfigService->deleteReportConfiguration($reportConfiguration);
        } catch (\DomainException $e) {
            return redirect()->route('reports.configurations.index', ['academic_year_id' => $academicYearId])
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('reports.configurations.index', ['academic_year_id' => $academicYearId])
                ->with('error', 'This report configuration cannot be removed because it is referenced by other records.');
        }

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'Report configuration removed successfully.');
    }
}
