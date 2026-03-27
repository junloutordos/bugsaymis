<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\AutoAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoAssignmentController extends Controller
{
    public function __construct(private readonly AutoAssignmentService $service) {}

    /** GET /faculty-loading/auto-assign/preview?academic_term_id=X&faculty_id=Y */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id',
            'faculty_id'       => 'nullable|exists:users,id',
        ]);

        $result = $this->service->preview(
            (int) $request->academic_term_id,
            $request->faculty_id ? (int) $request->faculty_id : null,
        );

        return response()->json($result);
    }

    /** POST /faculty-loading/auto-assign/apply */
    public function apply(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $request->validate([
            'academic_term_id'          => 'required|exists:academic_terms,id',
            'selected'                  => 'required|array|min:1',
            'selected.*.faculty_id'     => 'required|exists:users,id',
            'selected.*.subject_id'     => 'required|exists:subjects,id',
            'selected.*.load_units'     => 'required|numeric|min:0.5',
            'selected.*.section_id'     => 'nullable|integer',
        ]);

        $result = $this->service->apply(
            (int) $request->academic_term_id,
            $request->selected,
            Auth::id(),
        );

        return response()->json([
            'message' => "Created {$result['created']} assignment(s)" .
                ($result['skipped'] ? ", skipped {$result['skipped']} (already assigned or locked)." : '.'),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);
    }
}
