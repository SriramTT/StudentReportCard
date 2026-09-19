<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportAssessmentSelectionRequest;
use App\Http\Requests\Reports\UpdateReportAssessmentSelectionRequest;
use App\Models\ReportAssessmentSelection;
use App\Models\ReportConfiguration;
use App\Services\ReportConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReportAssessmentSelectionController extends Controller
{
    public function __construct(
        protected ReportConfigurationService $reportConfigService
    ) {}

    public function store(
        StoreReportAssessmentSelectionRequest $request,
        ReportConfiguration $reportConfiguration
    ): RedirectResponse {
        Gate::authorize('update', $reportConfiguration);

        $this->reportConfigService->addAssessmentSelection($reportConfiguration, $request->validated());

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $reportConfiguration->academic_year_id])
            ->with('success', 'Assessment added to report layout.');
    }

    public function update(
        UpdateReportAssessmentSelectionRequest $request,
        ReportConfiguration $reportConfiguration,
        ReportAssessmentSelection $selection
    ): RedirectResponse {
        Gate::authorize('update', $reportConfiguration);

        $this->reportConfigService->updateAssessmentSelection($selection, $request->validated());

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $reportConfiguration->academic_year_id])
            ->with('success', 'Report assessment selection updated.');
    }
}
