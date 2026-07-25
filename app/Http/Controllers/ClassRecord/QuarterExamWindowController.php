<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\QuarterExamWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuarterExamWindowController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /**
     * GET /quarter-exam-windows?school_year_id=
     * List the configured exam windows for one school year (all 4 quarters, sparse).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_year_id' => 'required|integer|exists:school_years,id',
        ]);

        return response()->json(
            QuarterExamWindow::where('school_year_id', $validated['school_year_id'])
                ->orderBy('quarter')
                ->get()
        );
    }

    /**
     * PUT /quarter-exam-windows
     * Upsert the exam window for one (school year, quarter) pair.
     */
    public function upsert(Request $request): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can configure quarter exam windows.');

        $validated = $request->validate([
            'school_year_id' => 'required|integer|exists:school_years,id',
            'quarter'        => 'required|integer|min:1|max:4',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        $window = QuarterExamWindow::updateOrCreate(
            ['school_year_id' => $validated['school_year_id'], 'quarter' => $validated['quarter']],
            ['start_date' => $validated['start_date'], 'end_date' => $validated['end_date']],
        );

        return response()->json([
            'message' => "Quarter {$validated['quarter']} exam window saved.",
            'data'    => $window,
        ]);
    }

    /**
     * DELETE /quarter-exam-windows/{quarterExamWindow}
     */
    public function destroy(QuarterExamWindow $quarterExamWindow): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can configure quarter exam windows.');

        $quarterExamWindow->delete();

        return response()->json(['message' => 'Exam window removed.']);
    }
}
