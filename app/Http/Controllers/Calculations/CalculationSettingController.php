<?php

namespace App\Http\Controllers\Calculations;

use App\Enums\CalculationMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calculations\StoreCalculationSettingRequest;
use App\Http\Requests\Calculations\UpdateCalculationSettingRequest;
use App\Models\AcademicYear;
use App\Models\CalculationSetting;
use App\Models\SchoolClass;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CalculationSettingController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CalculationSetting::class);

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $classes = SchoolClass::getNaturallySorted(true);

        $selectedYearId = $request->query('academic_year_id', $academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);

        $query = CalculationSetting::with(['academicYear', 'schoolClass']);
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }
        $settings = $query->get()->sortBy(fn ($s) => $s->schoolClass?->name ?? '', SORT_NATURAL)->values();

        return view('calculations.settings', [
            'settings' => $settings,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
        ]);
    }

    public function store(StoreCalculationSettingRequest $request): RedirectResponse
    {
        Gate::authorize('create', CalculationSetting::class);

        $setting = CalculationSetting::create([
            'academic_year_id' => (int) $request->validated('academic_year_id'),
            'class_id' => (int) $request->validated('class_id'),
            'calculation_method' => CalculationMethod::from($request->validated('calculation_method')),
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_CALCULATION_SETTING',
            entityType: 'calculation_settings',
            entityId: $setting->id,
            beforeData: null,
            afterData: $setting->only(['academic_year_id', 'class_id', 'calculation_method']),
            description: "Set calculation method {$setting->calculation_method->value} for class ID {$setting->class_id}"
        );

        return redirect()->route('calculations.settings.index', ['academic_year_id' => $setting->academic_year_id])
            ->with('success', 'Calculation setting configured successfully.');
    }

    public function update(UpdateCalculationSettingRequest $request, CalculationSetting $calculationSetting): RedirectResponse
    {
        Gate::authorize('update', $calculationSetting);

        $beforeData = $calculationSetting->only(['academic_year_id', 'class_id', 'calculation_method']);

        $calculationSetting->update([
            'calculation_method' => CalculationMethod::from($request->validated('calculation_method')),
        ]);

        $afterData = $calculationSetting->fresh()->only(['academic_year_id', 'class_id', 'calculation_method']);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'UPDATE_CALCULATION_SETTING',
            entityType: 'calculation_settings',
            entityId: $calculationSetting->id,
            beforeData: $beforeData,
            afterData: $afterData,
            description: "Updated calculation method to {$calculationSetting->calculation_method->value}"
        );

        return redirect()->route('calculations.settings.index', ['academic_year_id' => $calculationSetting->academic_year_id])
            ->with('success', 'Calculation setting updated successfully.');
    }

    public function destroy(CalculationSetting $calculationSetting): RedirectResponse
    {
        Gate::authorize('delete', $calculationSetting);

        $academicYearId = $calculationSetting->academic_year_id;
        $beforeData = $calculationSetting->only(['academic_year_id', 'class_id', 'calculation_method']);
        $settingId = $calculationSetting->id;

        $calculationSetting->delete();

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'DELETE_CALCULATION_SETTING',
            entityType: 'calculation_settings',
            entityId: $settingId,
            beforeData: $beforeData,
            afterData: null,
            description: "Deleted calculation setting for class ID {$beforeData['class_id']}"
        );

        return redirect()->route('calculations.settings.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'Calculation setting deleted successfully.');
    }
}
