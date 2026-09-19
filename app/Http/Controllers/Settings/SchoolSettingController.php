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

        return view('settings.school', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update the singleton school settings.
     */
    public function update(UpdateSchoolSettingRequest $request): RedirectResponse
    {
        $settings = $this->schoolSettingService->getSettings();
        Gate::authorize('update', $settings);

        $this->schoolSettingService->updateSettings($request->validated());

        return redirect()->route('admin.school_settings.edit')
            ->with('success', 'School settings updated successfully.');
    }
}
