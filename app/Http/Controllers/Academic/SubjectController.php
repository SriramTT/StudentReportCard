<?php

namespace App\Http\Controllers\Academic;

use App\Enums\SubjectCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Subject::class);

        $subjects = Subject::orderBy('category', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return view('academic.subjects.index', [
            'subjects' => $subjects,
        ]);
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Subject::class);

        $subject = Subject::create([
            'name' => trim($request->validated('name')),
            'code' => strtoupper(trim($request->validated('code'))),
            'category' => SubjectCategory::from($request->validated('category')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'CREATE_SUBJECT',
            entityType: 'subjects',
            entityId: $subject->id,
            beforeData: null,
            afterData: $subject->only(['name', 'code', 'category', 'is_active']),
            description: "Created subject {$subject->name} ({$subject->code})"
        );

        return redirect()->route('subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        Gate::authorize('update', $subject);

        $beforeData = $subject->only(['name', 'code', 'category', 'is_active']);

        $subject->update([
            'name' => trim($request->validated('name')),
            'code' => strtoupper(trim($request->validated('code'))),
            'category' => SubjectCategory::from($request->validated('category')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $afterData = $subject->fresh()->only(['name', 'code', 'category', 'is_active']);

        $this->auditService->logDomainAction(
            userId: Auth::id(),
            action: 'UPDATE_SUBJECT',
            entityType: 'subjects',
            entityId: $subject->id,
            beforeData: $beforeData,
            afterData: $afterData,
            description: "Updated subject {$subject->name} ({$subject->code})"
        );

        return redirect()->route('subjects.index')
            ->with('success', 'Subject updated successfully.');
    }
}
