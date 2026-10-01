<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreAcademicYearRequest;
use App\Http\Requests\Academic\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function __construct(
        protected AcademicYearService $academicYearService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        $academicYears = AcademicYear::withCount('terms')
            ->orderBy('start_date', 'desc')
            ->get();

        return view('academic.years.index', [
            'academicYears' => $academicYears,
        ]);
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        Gate::authorize('create', AcademicYear::class);

        $this->academicYearService->createAcademicYear($request->validated());

        return redirect()->route('academic_years.index')
            ->with('success', 'Academic year created successfully.');
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        Gate::authorize('update', $academicYear);

        $this->academicYearService->updateAcademicYear($academicYear, $request->validated());

        return redirect()->route('academic_years.index')
            ->with('success', 'Academic year updated successfully.');
    }

    public function close(AcademicYear $academicYear): RedirectResponse
    {
        Gate::authorize('close', $academicYear);

        $this->academicYearService->closeYear($academicYear);

        return redirect()->route('academic_years.index')
            ->with('success', 'Academic year closed successfully.');
    }

    public function reopen(AcademicYear $academicYear): RedirectResponse
    {
        Gate::authorize('reopen', $academicYear);

        $this->academicYearService->reopenYear($academicYear);

        return redirect()->route('academic_years.index')
            ->with('success', 'Academic year reopened successfully.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        Gate::authorize('delete', $academicYear);

        try {
            $this->academicYearService->deleteAcademicYear($academicYear);
        } catch (\DomainException $e) {
            return redirect()->route('academic_years.index')
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('academic_years.index')
                ->with('error', 'This academic year cannot be removed because it is referenced by existing academic data.');
        }

        return redirect()->route('academic_years.index')
            ->with('success', 'Academic year removed successfully.');
    }
}
