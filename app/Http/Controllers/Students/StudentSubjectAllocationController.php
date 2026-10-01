<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\UpdateStudentSubjectAllocationRequest;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Services\StudentSubjectAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentSubjectAllocationController extends Controller
{
    public function __construct(
        protected StudentSubjectAllocationService $allocationService
    ) {}

    /**
     * Show the subject allocation management view for a student academic placement.
     */
    public function edit(StudentAcademicRecord $studentAcademicRecord): View
    {
        $student = $studentAcademicRecord->student;
        Gate::authorize('updateAllocation', $student);

        $studentAcademicRecord->load(['academicYear', 'schoolClass', 'section']);

        $availableClassSubjects = $this->allocationService->getAvailableClassSubjects($studentAcademicRecord);

        $allocations = $studentAcademicRecord->subjectAllocations()
            ->with(['marks', 'classSubject.subject'])
            ->get()
            ->keyBy('class_subject_id');

        return view('students.allocations', [
            'record' => $studentAcademicRecord,
            'student' => $student,
            'availableClassSubjects' => $availableClassSubjects,
            'allocations' => $allocations,
        ]);
    }

    /**
     * Update subject allocations for the student academic placement.
     */
    public function update(
        UpdateStudentSubjectAllocationRequest $request,
        StudentAcademicRecord $studentAcademicRecord
    ): RedirectResponse {
        $student = $studentAcademicRecord->student;
        Gate::authorize('updateAllocation', $student);

        $classSubjectIds = array_map('intval', $request->input('class_subject_ids', []));

        $this->allocationService->updateStudentAllocations(
            $studentAcademicRecord,
            $classSubjectIds,
            (int) Auth::id()
        );

        return redirect()->route('students.show', $student)
            ->with('success', "Subject allocations successfully updated for {$student->student_name} ({$student->admission_number}).");
    }
}
