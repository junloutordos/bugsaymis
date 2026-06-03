<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\Registrar\StudentPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(
        private readonly StudentPromotionService $promotionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('students.promotion.process');

        $currentId    = SchoolYear::where('is_current', true)->value('id');
        $schoolYearId = (int) $request->input('school_year_id', $currentId);

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        return Inertia::render('Registrar/Promotion/Index', [
            'schoolYears'        => $schoolYears,
            'selectedSchoolYear' => $schoolYearId,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('students.promotion.process');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id'));

        $rows = $this->promotionService->preview($schoolYearId);

        return response()->json(['rows' => $rows]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('students.promotion.process');

        $data = $request->validate([
            'school_year_id'      => ['required', 'integer', 'exists:school_years,id'],
            'next_school_year_id' => ['required', 'integer', 'exists:school_years,id',
                'different:school_year_id'],
            'overrides'           => ['nullable', 'array'],
            'overrides.*'         => ['required', 'string', 'in:Promoted,Retained,Excluded,Transferred,Dropped'],
        ]);

        $result = $this->promotionService->confirm(
            $data['school_year_id'],
            $data['next_school_year_id'],
            $data['overrides'] ?? []
        );

        $msg = "Promotion complete — "
            . "{$result['promoted']} promoted, "
            . "{$result['retained']} retained, "
            . "{$result['excluded']} excluded.";

        return back()->with('success', $msg);
    }
}
