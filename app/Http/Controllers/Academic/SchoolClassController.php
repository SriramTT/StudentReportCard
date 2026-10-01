<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSchoolClassRequest;
use App\Http\Requests\Academic\UpdateSchoolClassRequest;
use App\Models\SchoolClass;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $status = $request->query('status', 'all');
        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $query = SchoolClass::withCount('sections');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $classes = $query->orderBy('name', 'asc')
            ->get()
            ->sortBy('name', SORT_NATURAL)
            ->values();

        return view('academic.classes.index', [
            'classes' => $classes,
            'selectedStatus' => $status,
        ]);
    }

    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        Gate::authorize('create', SchoolClass::class);

        $schoolClass = SchoolClass::create([
            'name' => trim($request->validated('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_CLASS',
            entityType: 'classes',
            entityId: $schoolClass->id,
            beforeData: null,
            afterData: $schoolClass->only(['name', 'is_active']),
            description: "Created class: {$schoolClass->name}"
        );

        return redirect()->route('classes.index')
            ->with('success', 'Class created successfully.');
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        Gate::authorize('update', $schoolClass);

        $beforeData = $schoolClass->only(['name', 'is_active']);

        $schoolClass->update([
            'name' => trim($request->validated('name')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $afterData = $schoolClass->fresh()->only(['name', 'is_active']);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'UPDATE_CLASS',
            entityType: 'classes',
            entityId: $schoolClass->id,
            beforeData: $beforeData,
            afterData: $afterData,
            description: "Updated class: {$schoolClass->name}"
        );

        return redirect()->route('classes.index')
            ->with('success', 'Class updated successfully.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        Gate::authorize('delete', $schoolClass);

        if (
            $schoolClass->sections()->exists() ||
            $schoolClass->classSubjects()->exists() ||
            $schoolClass->academicRecords()->exists() ||
            $schoolClass->teacherAssignments()->exists() ||
            $schoolClass->calculationSettings()->exists()
        ) {
            return redirect()->route('classes.index')
                ->with('error', 'This class cannot be removed because it has associated sections, subjects, or student records. Deactivate it instead.');
        }

        try {
            DB::transaction(function () use ($schoolClass) {
                $beforeData = [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'is_active' => $schoolClass->is_active,
                ];

                $schoolClass->delete();

                $this->auditService->logDomainAction(
                    userId: Auth::id(),
                    action: 'DELETE_CLASS',
                    entityType: 'classes',
                    entityId: $beforeData['id'],
                    beforeData: $beforeData,
                    afterData: null,
                    description: "Permanently removed class: {$beforeData['name']}"
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('classes.index')
                ->with('error', 'This class cannot be removed because it is referenced by other academic records.');
        }

        return redirect()->route('classes.index')
            ->with('success', 'Class removed successfully.');
    }
}
