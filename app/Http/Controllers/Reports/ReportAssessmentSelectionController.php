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

    public function destroy(
        ReportConfiguration $reportConfiguration,
        ReportAssessmentSelection $selection
    ): RedirectResponse {
        Gate::authorize('update', $reportConfiguration);

        if ($selection->report_configuration_id !== $reportConfiguration->id) {
            abort(404);
        }

        $this->reportConfigService->removeAssessmentSelection($selection);

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $reportConfiguration->academic_year_id])
            ->with('success', 'Assessment removed from report configuration.');
    }

    public function reorder(
        \App\Http\Requests\Reports\ReorderReportAssessmentSelectionsRequest $request,
        ReportConfiguration $reportConfiguration
    ): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse {
        Gate::authorize('update', $reportConfiguration);

        $this->reportConfigService->reorderAssessmentSelections(
            $reportConfiguration,
            $request->validated()['selection_ids']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Assessment display order updated successfully.',
            ]);
        }

        return redirect()->route('reports.configurations.index', ['academic_year_id' => $reportConfiguration->academic_year_id])
            ->with('success', 'Assessment display order updated successfully.');
    }
}
