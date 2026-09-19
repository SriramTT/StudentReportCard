<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreTermRequest;
use App\Http\Requests\Academic\UpdateTermRequest;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TermController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Term::class);

        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        $selectedYearId = $request->query('academic_year_id', $academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);

        $query = Term::with('academicYear')->orderBy('sequence_no', 'asc');
        if ($selectedYearId) {
            $query->where('academic_year_id', $selectedYearId);
        }
        $terms = $query->get();

        return view('academic.terms.index', [
            'terms' => $terms,
            'academicYears' => $academicYears,
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
        ]);
    }

    public function store(StoreTermRequest $request): RedirectResponse
    {
        Gate::authorize('create', Term::class);

        $term = Term::create([
            'academic_year_id' => (int) $request->validated('academic_year_id'),
            'name' => trim($request->validated('name')),
            'sequence_no' => (int) $request->validated('sequence_no'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_TERM',
            entityType: 'terms',
            entityId: $term->id,
            beforeData: null,
            afterData: $term->only(['academic_year_id', 'name', 'sequence_no', 'is_active']),
            description: "Created term {$term->name} (Sequence: {$term->sequence_no})"
        );

        return redirect()->route('terms.index', ['academic_year_id' => $term->academic_year_id])
            ->with('success', 'Term created successfully.');
    }

    public function update(UpdateTermRequest $request, Term $term): RedirectResponse
    {
        Gate::authorize('update', $term);

        $beforeData = $term->only(['academic_year_id', 'name', 'sequence_no', 'is_active']);

        $term->update([
            'name' => trim($request->validated('name')),
            'sequence_no' => (int) $request->validated('sequence_no'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $afterData = $term->fresh()->only(['academic_year_id', 'name', 'sequence_no', 'is_active']);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'UPDATE_TERM',
            entityType: 'terms',
            entityId: $term->id,
            beforeData: $beforeData,
            afterData: $afterData,
            description: "Updated term {$term->name}"
        );

        return redirect()->route('terms.index', ['academic_year_id' => $term->academic_year_id])
            ->with('success', 'Term updated successfully.');
    }
}
