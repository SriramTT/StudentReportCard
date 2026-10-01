<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSchoolSettingRequest;
use App\Models\SchoolSetting;
use App\Services\SchoolSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SchoolSettingController extends Controller
{
    public function __construct(
        protected SchoolSettingService $schoolSettingService
    ) {}

    /**
     * Show the edit form for the singleton school settings.
     */
    public function edit(): View
    {
        $settings = $this->schoolSettingService->getSettings();
        Gate::authorize('view', $settings);

        $today = now()->toDateString();

        $classTeachers = \App\Models\User::query()
            ->whereHas('teacherAssignments', function ($q) use ($today) {
                $q->where('assignment_type', \App\Enums\TeacherAssignmentType::CLASS_TEACHER)
                  ->whereNull('subject_id')
                  ->where('is_active', true)
                  ->where('effective_from', '<=', $today)
                  ->where(function ($dateQ) use ($today) {
                      $dateQ->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $today);
                  });
            })
            ->with([
                'teacherAssignments' => function ($q) use ($today) {
                    $q->where('assignment_type', \App\Enums\TeacherAssignmentType::CLASS_TEACHER)
                      ->whereNull('subject_id')
                      ->where('is_active', true)
                      ->where('effective_from', '<=', $today)
                      ->where(function ($dateQ) use ($today) {
                          $dateQ->whereNull('effective_to')
                                ->orWhere('effective_to', '>=', $today);
                      })
                      ->with(['schoolClass', 'section', 'academicYear']);
                },
            ])
            ->orderBy('display_name')
            ->get();

        foreach ($classTeachers as $teacher) {
            $teacher->signatureUri = $this->schoolSettingService->getTeacherSignatureDataUri($teacher->id);
            $classrooms = $teacher->teacherAssignments->map(function ($a) {
                $cls = $a->schoolClass?->name ?? 'Class';
                $sec = $a->section?->name ?? 'Sec';
                $ay = $a->academicYear?->name ?? '';
                return "{$cls}-{$sec}" . ($ay ? " ({$ay})" : '');
            })->unique()->values()->all();
            $teacher->assignedClassroomsDisplay = ! empty($classrooms) ? implode(', ', $classrooms) : 'No active assignment';
        }

        return view('settings.school', [
            'settings' => $settings,
            'classTeachers' => $classTeachers,
            'principalSignatureUri' => $this->schoolSettingService->getSignatureDataUri('principal'),
        ]);
    }

    /**
     * Update the singleton school settings.
     */
    public function update(UpdateSchoolSettingRequest $request): RedirectResponse
    {
        $settings = $this->schoolSettingService->getSettings();
        Gate::authorize('update', $settings);

        $data = $request->validated();
        if ($request->hasFile('school_logo')) {
            $data['school_logo'] = $request->file('school_logo');
        }
        if ($request->boolean('remove_school_logo')) {
            $data['remove_school_logo'] = true;
        }

        // Handle Principal signature
        if ($request->boolean('remove_principal_signature')) {
            $this->schoolSettingService->deleteSignature('principal');
        } elseif ($request->hasFile('principal_signature')) {
            $this->schoolSettingService->storeSignature('principal', $request->file('principal_signature'));
        }

        // Handle contextual Class Teacher signatures removal
        if ($request->has('remove_teacher_signatures')) {
            foreach ($request->input('remove_teacher_signatures', []) as $removeTeacherId) {
                $this->schoolSettingService->deleteTeacherSignature((int) $removeTeacherId);
            }
        }

        // Handle contextual Class Teacher signatures upload
        if ($request->hasFile('teacher_signatures')) {
            foreach ($request->file('teacher_signatures') as $teacherId => $file) {
                if ($file && $file->isValid()) {
                    $this->schoolSettingService->storeTeacherSignature((int) $teacherId, $file);
                }
            }
        }

        $this->schoolSettingService->updateSettings($data);

        return redirect()->route('admin.school_settings.edit')
            ->with('success', 'School settings updated successfully.');
    }
}
