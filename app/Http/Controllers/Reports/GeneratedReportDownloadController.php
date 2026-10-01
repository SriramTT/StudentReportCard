<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use App\Services\Report\ReportGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GeneratedReportDownloadController extends Controller
{
    public function __construct(
        protected ReportGenerationService $reportGenService
    ) {}

    /**
     * Authorize and stream a historical generated report PDF.
     */
    public function download(Request $request, GeneratedReport $generatedReport): BinaryFileResponse
    {
        // 1. Authorize via ReportPolicy@download with full IDOR protection
        Gate::authorize('download', $generatedReport);

        // 2. Resolve private file path
        $absolutePath = storage_path('app/private/' . $generatedReport->file_path);

        if (! File::exists($absolutePath)) {
            abort(404, 'Report PDF file was not found in private storage.');
        }

        // 3. Construct clean human-readable filename:
        // {Student_Name}_{Class}_{Section}_{Admission_Number}_{Report_Name}.pdf
        $generatedReport->loadMissing([
            'studentAcademicRecord.student',
            'studentAcademicRecord.schoolClass',
            'studentAcademicRecord.section',
            'studentAcademicRecord.academicYear',
            'term',
            'assessment',
        ]);

        $sar = $generatedReport->studentAcademicRecord;
        $downloadFilename = $sar
            ? $this->reportGenService->buildHumanReadableFilename(
                $sar,
                $generatedReport->report_type,
                $generatedReport->term_id,
                $generatedReport->assessment_id
            )
            : "report_{$generatedReport->id}.pdf";

        return response()->download(
            file: $absolutePath,
            name: $downloadFilename,
            headers: [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
