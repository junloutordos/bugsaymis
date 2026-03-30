<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\LoadBalancingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LoadBalancingController extends Controller
{
    public function __construct(private readonly LoadBalancingService $balancer) {}

    // ── Inertia Page ──────────────────────────────────────────────────────

    /**
     * Load Balancing Optimization dashboard.
     */
    public function index(): Response
    {
        $this->authorize('faculty_loading.manage');

        $schoolYears = SchoolYear::with('terms')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($sy) => [
                'id'         => $sy->id,
                'name'       => $sy->name,
                'is_current' => $sy->is_current,
                'terms'      => $sy->terms->map(fn ($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'is_current' => $t->is_current,
                ])->values(),
            ]);

        return Inertia::render('FacultyLoading/LoadBalance/Index', [
            'schoolYears' => $schoolYears,
        ]);
    }

    // ── API: Analyse ──────────────────────────────────────────────────────

    /**
     * Return full workload analysis for a term.
     *
     * GET /faculty-loading/load-balance/analysis?academic_term_id=X
     */
    public function analysis(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $request->validate(['academic_term_id' => 'required|integer']);

        try {
            $data = $this->balancer->analyse((int) $request->query('academic_term_id'));
            return response()->json($data);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── API: Suggest ──────────────────────────────────────────────────────

    /**
     * Generate rebalancing suggestions for a term.
     *
     * POST /faculty-loading/load-balance/suggest
     * Body: { academic_term_id: int }
     */
    public function suggest(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate(['academic_term_id' => 'required|integer']);

        try {
            $suggestions = $this->balancer->suggest((int) $data['academic_term_id']);
            return response()->json(['suggestions' => $suggestions]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── API: Apply ────────────────────────────────────────────────────────

    /**
     * Apply a rebalancing suggestion.
     *
     * POST /faculty-loading/load-balance/apply
     * Body: {
     *   type:               'transfer' | 'swap'
     *
     *   -- For transfer:
     *   load_assignment_id: int
     *   to_user_id:         int
     *
     *   -- For swap:
     *   assignment_a_id:    int
     *   assignment_b_id:    int
     * }
     */
    public function apply(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $type = $request->input('type');

        try {
            if ($type === 'transfer') {
                $data = $request->validate([
                    'load_assignment_id' => 'required|integer',
                    'to_user_id'         => 'required|integer',
                ]);

                $result = $this->balancer->applyTransfer(
                    (int) $data['load_assignment_id'],
                    (int) $data['to_user_id']
                );

            } elseif ($type === 'swap') {
                $data = $request->validate([
                    'assignment_a_id' => 'required|integer',
                    'assignment_b_id' => 'required|integer',
                ]);

                $result = $this->balancer->applySwap(
                    (int) $data['assignment_a_id'],
                    (int) $data['assignment_b_id']
                );

            } else {
                return response()->json(['message' => "Unknown suggestion type: '{$type}'."], 422);
            }

            return response()->json($result);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
