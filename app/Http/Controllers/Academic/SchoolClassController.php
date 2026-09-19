<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSchoolClassRequest;
use App\Http\Requests\Academic\UpdateSchoolClassRequest;
use App\Models\SchoolClass;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $classes = SchoolClass::withCount('sections')
            ->orderBy('name', 'asc')
            ->get();

        return view('academic.classes.index', [
            'classes' => $classes,
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
}
