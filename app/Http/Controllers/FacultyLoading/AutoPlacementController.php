<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use App\Services\FacultyLoading\DeterministicSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Incremental auto-placement for the "Unplaced Subjects" tray on the class
 * schedules calendar. Unlike the full AI generator (AutoScheduleController),
 * this is strictly ADDITIVE: existing schedules — active AND tentative — are
 * treated as immovable and are never deleted or replaced. Only the missing
 * sessions of unplaced teaching loads are proposed and, on commit, inserted
 * as new tentative rows.
 */
class AutoPlacementController extends Controller
{
    public function __construct(
        private readonly DeterministicSchedulingService $scheduler,
    ) {}

    /**
     * Compute (but do not save) placements for the unplaced loads in scope.
     *
     * POST /faculty-loading/schedules/auto-place/preview
     * Body: { academic_term_id: int, section_id?: int, faculty_id?: int }
     * The optional filters mirror the calendar page's own filters so the pass
     * covers exactly the chips the user is looking at.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => 'required|integer|exists:academic_terms,id',
            'section_id' => 'nullable|integer',
            'faculty_id' => 'nullable|integer',
        ]);

        $termId = (int) $data['academic_term_id'];
        if (ClassScheduleApprovalService::termIsLocked($termId)) {
            return response()->json(['message' => 'This term schedule is locked for OCD approval.'], 423);
        }
        $schoolYearId = (int) AcademicTerm::findOrFail($termId)->school_year_id;

        // Scope to the filtered section/faculty when the page is filtered —
        // same where-clauses as ClassScheduleController::buildUnplacedLoads().
        $loadAssignmentIds = null;
        if (! empty($data['section_id']) || ! empty($data['faculty_id'])) {
            $loadAssignmentIds = LoadAssignment::where('academic_term_id', $termId)
                ->where('assignment_type', 'teaching')
                ->whereNotNull('subject_id')
                ->whereNotNull('section_id')
                ->when($data['section_id'] ?? null, fn ($q, $v) => $q->where('section_id', $v))
                ->when($data['faculty_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
                ->pluck('id')
                ->all();
        }

        $result = $this->scheduler->placeRemaining($schoolYearId, $termId, $loadAssignmentIds);

        return response()->json($result);
    }

    /**
     * Save a previously previewed batch as tentative rows. Re-checks conflicts
     * and per-load still-needed counts first — the calendar may have changed
     * between preview and commit.
     *
     * POST /faculty-loading/schedules/auto-place/commit
     * Body: { academic_term_id: int, schedules: array }
     */
    public function commit(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => 'required|integer|exists:academic_terms,id',
            'schedules' => 'required|array|min:1',
            'schedules.*.load_assignment_id' => 'required|integer|exists:load_assignments,id',
            'schedules.*.user_id' => 'required|integer',
            'schedules.*.subject_id' => 'required|integer',
            'schedules.*.section_id' => 'required|integer',
            'schedules.*.classroom_id' => 'nullable|integer',
            'schedules.*.session_type' => 'nullable|in:regular,ilp',
            'schedules.*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'schedules.*.start_time' => 'required|string',
            'schedules.*.end_time' => 'required|string',
        ]);

        $termId = (int) $data['academic_term_id'];
        if (ClassScheduleApprovalService::termIsLocked($termId)) {
            return response()->json(['message' => 'This term schedule is locked for OCD approval.'], 423);
        }
        $schoolYearId = (int) AcademicTerm::findOrFail($termId)->school_year_id;
        $schedules = $data['schedules'];

        $conflicts = $this->detectCommitConflicts($schedules, $termId);
        if (! empty($conflicts)) {
            return response()->json([
                'message' => count($conflicts).' conflict(s) detected — the calendar changed since the preview. Refresh and try again.',
                'conflicts' => $conflicts,
            ], 422);
        }

        // Over-placement guard: never exceed a load's still-needed session count
        // (protects against a stale preview and against double-submits).
        $byLoad = [];
        foreach ($schedules as $s) {
            $byLoad[(int) $s['load_assignment_id']] = ($byLoad[(int) $s['load_assignment_id']] ?? 0) + 1;
        }
        $loads = LoadAssignment::with('subject:id,load_units')
            ->whereIn('id', array_keys($byLoad))
            ->get()
            ->keyBy('id');
        $scheduledCounts = ClassSchedule::occupying()
            ->whereIn('load_assignment_id', array_keys($byLoad))
            ->selectRaw('load_assignment_id, COUNT(*) as cnt')
            ->groupBy('load_assignment_id')
            ->pluck('cnt', 'load_assignment_id');
        foreach ($byLoad as $loadId => $proposedCount) {
            $load = $loads->get($loadId);
            if (! $load) {
                continue;
            }
            $required = max(1, (int) round((float) ($load->subject->load_units ?? 1)));
            $stillNeeded = max(0, $required - (int) ($scheduledCounts[$loadId] ?? 0));
            if ($proposedCount > $stillNeeded) {
                return response()->json([
                    'message' => 'Some loads were already placed since the preview — refresh and run the preview again.',
                ], 422);
            }
        }

        DB::transaction(function () use ($schedules, $termId, $schoolYearId) {
            $now = now();
            $rows = array_map(fn ($s) => [
                'load_assignment_id' => $s['load_assignment_id'],
                'user_id' => $s['user_id'],
                'subject_id' => $s['subject_id'],
                'section_id' => $s['section_id'],
                'classroom_id' => $s['classroom_id'] ?? null,
                // Term + school year come from the validated top-level term, not
                // the client rows — a stale/patched row can't cross terms.
                'school_year_id' => $schoolYearId,
                'academic_term_id' => $termId,
                'session_type' => $s['session_type'] ?? 'regular',
                'day_of_week' => $s['day_of_week'],
                'start_time' => $s['start_time'],
                'end_time' => $s['end_time'],
                'status' => 'tentative',
                'remarks' => 'Auto-placed (remaining loads)',
                'created_by' => Auth::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ], $schedules);

            ClassSchedule::insert($rows);
        });

        return response()->json([
            'message' => count($schedules).' session(s) saved as tentative.',
            'created' => count($schedules),
        ]);
    }

    /**
     * Feasible free slots for one unplaced load's next session — powers the
     * per-chip suggestions popover.
     *
     * POST /faculty-loading/schedules/auto-place/suggestions
     * Body: { academic_term_id: int, load_assignment_id: int, limit?: int }
     */
    public function suggestions(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => 'required|integer|exists:academic_terms,id',
            'load_assignment_id' => 'required|integer|exists:load_assignments,id',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $termId = (int) $data['academic_term_id'];
        $schoolYearId = (int) AcademicTerm::findOrFail($termId)->school_year_id;

        $result = $this->scheduler->suggestSlotsForLoad(
            $schoolYearId,
            $termId,
            (int) $data['load_assignment_id'],
            (int) ($data['limit'] ?? 5),
        );

        return response()->json($result);
    }

    /**
     * Check a proposed ADDITIVE batch for overlaps against every occupying row
     * (active AND tentative — unlike the full-regeneration apply, nothing is
     * being replaced here, so tentative rows are real obstacles) plus overlaps
     * within the batch itself. The faculty axis is skipped for TBA/placeholder
     * users — parallel TBA sessions are not real double-bookings.
     *
     * @param  array<int,array<string,mixed>>  $schedules
     * @return string[] Human-readable conflict descriptions (empty = clean)
     */
    private function detectCommitConflicts(array $schedules, int $termId): array
    {
        $messages = [];
        $axes = ['user_id' => 'Faculty', 'classroom_id' => 'Room', 'section_id' => 'Section'];

        $tbaUserIds = User::whereIn('id', array_unique(array_column($schedules, 'user_id')))
            ->where('name', 'like', 'TBA%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // 1. Against every occupying (active + tentative) schedule in the DB.
        foreach ($schedules as $s) {
            foreach ($axes as $column => $label) {
                $value = $s[$column] ?? null;
                if (! $value) {
                    continue;
                }
                if ($column === 'user_id' && in_array((int) $value, $tbaUserIds, true)) {
                    continue;
                }

                $exists = ClassSchedule::occupying()
                    ->where($column, $value)
                    ->where('day_of_week', $s['day_of_week'])
                    ->where('academic_term_id', $termId)
                    ->where('start_time', '<', $s['end_time'])
                    ->where('end_time', '>', $s['start_time'])
                    ->exists();

                if ($exists) {
                    $messages[] = "{$label} conflict: {$s['day_of_week']} {$s['start_time']}–{$s['end_time']} "
                        .'overlaps an existing schedule.';
                }
            }
        }

        // 2. Within the batch itself.
        $n = count($schedules);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $schedules[$i];
                $b = $schedules[$j];
                if ($a['day_of_week'] !== $b['day_of_week']
                    || ! ($a['start_time'] < $b['end_time'] && $a['end_time'] > $b['start_time'])) {
                    continue;
                }
                foreach ($axes as $column => $label) {
                    $value = $a[$column] ?? null;
                    if (! $value || $value != ($b[$column] ?? null)) {
                        continue;
                    }
                    if ($column === 'user_id' && in_array((int) $value, $tbaUserIds, true)) {
                        continue;
                    }
                    $messages[] = "{$label} conflict within the batch: {$a['day_of_week']} "
                        ."{$a['start_time']}–{$a['end_time']} overlaps {$b['start_time']}–{$b['end_time']}.";
                }
            }
        }

        return array_values(array_unique($messages));
    }
}
