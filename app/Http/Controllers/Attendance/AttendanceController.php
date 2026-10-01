<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BatchSaveAttendanceRequest;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Display the term attendance roster and entry grid.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Attendance::class);

        $context = $this->attendanceService->loadAttendanceContext(
            user: Auth::user(),
            filters: $request->query()
        );

        return view('attendance.index', $context);
    }

    /**
     * Handle batch submission of student attendance records.
     */
    public function batchSave(BatchSaveAttendanceRequest $request): RedirectResponse
    {
        Gate::authorize('create', Attendance::class);

        $savedCount = $this->attendanceService->batchSaveAttendance(
            user: Auth::user(),
            data: $request->validated()
        );

        return redirect()->route('attendance.index', [
            'academic_year_id' => $request->validated('academic_year_id'),
            'class_id' => $request->validated('class_id'),
            'section_id' => $request->validated('section_id'),
            'term_id' => $request->validated('term_id'),
        ])->with('success', "Successfully saved attendance records for {$savedCount} student(s).");
    }
}
