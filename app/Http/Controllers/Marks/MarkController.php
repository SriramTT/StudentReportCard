<?php

namespace App\Http\Controllers\Marks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marks\BatchSaveMarksRequest;
use App\Models\Mark;
use App\Services\MarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MarkController extends Controller
{
    public function __construct(
        protected MarkService $markService
    ) {}

    /**
     * Display the contextual mark entry screen and roster.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Mark::class);

        $context = $this->markService->loadMarkContext($request->user(), $request->query());

        $roster = collect();
        if (
            $context['selectedYearId'] &&
            $context['selectedClassId'] &&
            $context['selectedSectionId'] &&
            $context['targetClassSubject'] &&
            $context['targetApplicability']
        ) {
            $roster = $this->markService->getMarkEntryRoster(
                $context['selectedYearId'],
                $context['selectedClassId'],
                $context['selectedSectionId'],
                $context['targetClassSubject']->id,
                $context['targetApplicability']->id
            );
        }

        return view('marks.index', array_merge($context, [
            'roster' => $roster,
        ]));
    }

    /**
     * Transactionally save a batch of marks.
     */
    public function batchSave(BatchSaveMarksRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('batchSave', [
            Mark::class,
            (int) $request->input('academic_year_id'),
            (int) $request->input('class_id'),
            (int) $request->input('section_id'),
            (int) $request->input('subject_id'),
        ]);

        $result = $this->markService->saveMarks($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully saved {$result['saved']} mark(s).",
                'saved' => $result['saved'],
                'unchanged' => $result['unchanged'],
            ]);
        }

        return redirect()->route('marks.index', [
            'academic_year_id' => $request->input('academic_year_id'),
            'class_id' => $request->input('class_id'),
            'section_id' => $request->input('section_id'),
            'subject_id' => $request->input('subject_id'),
            'assessment_id' => $request->input('assessment_id'),
        ])->with('success', "Successfully saved {$result['saved']} mark(s).");
    }
}
