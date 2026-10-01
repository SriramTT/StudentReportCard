<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ReorderTermsRequest;
use App\Http\Requests\Academic\StoreTermRequest;
use App\Http\Requests\Academic\UpdateTermRequest;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $academicYearId = (int) $request->validated('academic_year_id');
        $sequenceNo = $request->validated('sequence_no');

        if ($sequenceNo === null) {
            $sequenceNo = (Term::where('academic_year_id', $academicYearId)->max('sequence_no') ?? 0) + 1;
        }

        $term = Term::create([
            'academic_year_id' => $academicYearId,
            'name' => trim($request->validated('name')),
            'sequence_no' => (int) $sequenceNo,
            'is_active' => false,
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_TERM',
            entityType: 'terms',
            entityId: $term->id,
            beforeData: null,
            afterData: $term->only(['academic_year_id', 'name', 'sequence_no']),
            description: "Created term {$term->name} (Sequence: {$term->sequence_no})"
        );

        return redirect()->route('terms.index', ['academic_year_id' => $term->academic_year_id])
            ->with('success', 'Term created successfully.');
    }

    public function update(UpdateTermRequest $request, Term $term): RedirectResponse
    {
        Gate::authorize('update', $term);

        $beforeData = $term->only(['academic_year_id', 'name', 'sequence_no']);
        $sequenceNo = $request->validated('sequence_no') ?? $term->sequence_no;

        $term->update([
            'name' => trim($request->validated('name')),
            'sequence_no' => (int) $sequenceNo,
            // is_active is intentionally not written — preserved as-is in DB
        ]);

        $afterData = $term->fresh()->only(['academic_year_id', 'name', 'sequence_no']);

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

    public function reorder(ReorderTermsRequest $request): JsonResponse|RedirectResponse
    {
        $academicYearId = (int) $request->validated('academic_year_id');
        $termIds = $request->validated('term_ids');

        $matchingCount = Term::where('academic_year_id', $academicYearId)
            ->whereIn('id', $termIds)
            ->count();

        if ($matchingCount !== count($termIds)) {
            throw ValidationException::withMessages([
                'term_ids' => ['All terms must belong to the specified academic year.'],
            ]);
        }

        DB::transaction(function () use ($academicYearId, $termIds) {
            // Use high offset to avoid unique constraint violations during reordering
            Term::where('academic_year_id', $academicYearId)
                ->whereIn('id', $termIds)
                ->update(['sequence_no' => DB::raw('sequence_no + 10000')]);

            foreach ($termIds as $position => $termId) {
                Term::where('id', $termId)->update(['sequence_no' => $position + 1]);
            }
        });

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'REORDER_TERMS',
            entityType: 'terms',
            entityId: $academicYearId,
            beforeData: null,
            afterData: ['term_ids' => $termIds],
            description: "Reordered terms for academic year ID {$academicYearId}"
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Terms order saved successfully.',
            ]);
        }

        return redirect()->route('terms.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'Terms order saved successfully.');
    }

    public function destroy(Term $term): RedirectResponse
    {
        Gate::authorize('delete', $term);

        $academicYearId = $term->academic_year_id;

        if (
            $term->assessments()->exists() ||
            $term->attendance()->exists() ||
            $term->generatedReports()->exists()
        ) {
            return redirect()->route('terms.index', ['academic_year_id' => $academicYearId])
                ->with('error', 'This term cannot be removed because it is referenced by existing assessments, attendance records, or report cards. Deactivate it instead.');
        }

        try {
            DB::transaction(function () use ($term) {
                $beforeData = [
                    'id' => $term->id,
                    'academic_year_id' => $term->academic_year_id,
                    'name' => $term->name,
                    'sequence_no' => $term->sequence_no,
                    'is_active' => $term->is_active,
                ];

                $term->delete();

                $this->auditService->logDomainAction(
                    userId: Auth::id(),
                    action: 'DELETE_TERM',
                    entityType: 'terms',
                    entityId: $beforeData['id'],
                    beforeData: $beforeData,
                    afterData: null,
                    description: "Permanently removed term: {$beforeData['name']}"
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('terms.index', ['academic_year_id' => $academicYearId])
                ->with('error', 'This term cannot be removed because it is referenced by other academic records.');
        }

        return redirect()->route('terms.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'Term removed successfully.');
    }
}
