<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Services\FacultyLoading\ScheduleAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Schedule Balance Analytics — read-only timetable-quality dashboard.
 * All computation lives in ScheduleAnalyticsService; this controller only
 * resolves the term and shapes the Inertia/JSON envelope.
 */
class ScheduleAnalyticsController extends Controller
{
    public function __construct(private readonly ScheduleAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'label'      => $t->full_label,
                'is_current' => $t->is_current,
            ]);

        $defaultTerm = $terms->firstWhere('is_current', true) ?? $terms->first();
        $termId = (int) ($request->input('term_id') ?: ($defaultTerm['id'] ?? 0));

        return Inertia::render('FacultyLoading/ScheduleAnalytics/Index', [
            'terms'  => $terms,
            'termId' => $termId ?: null,
            'report' => $termId ? $this->analytics->analyzeTerm($termId) : null,
        ]);
    }

    /** JSON endpoint for in-page term switching. */
    public function data(Request $request): JsonResponse
    {
        $data = $request->validate(['term_id' => 'required|integer|exists:academic_terms,id']);

        return response()->json($this->analytics->analyzeTerm((int) $data['term_id']));
    }
}
