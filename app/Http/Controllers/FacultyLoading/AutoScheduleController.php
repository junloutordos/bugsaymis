<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AiScheduleJob;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use App\Services\FacultyLoading\ConflictDetectionService;
use App\Services\FacultyLoading\DeterministicSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AutoScheduleController extends Controller
{
    public function __construct(
        private readonly DeterministicSchedulingService $scheduler,
        private readonly ConflictDetectionService $conflicts,
    ) {}

    // ── Inertia Page ──────────────────────────────────────────────────────

    /**
     * Render the Auto Schedule generator page.
     * Passes school years + their terms so the UI can populate selectors.
     */
    public function index(): Response
    {
        $this->authorize('faculty_loading.manage');

        $schoolYears = SchoolYear::with('terms')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($sy) => [
                'id' => $sy->id,
                'name' => $sy->name,
                'is_current' => $sy->is_current,
                'terms' => $sy->terms->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'label' => $t->label ?? $t->name,
                    'is_current' => $t->is_current,
                ])->values(),
            ]);

        // Recent jobs (last 10) for the history panel
        $recentJobs = AiScheduleJob::with('academicTerm', 'createdBy:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($j) => $this->serializeJob($j, false));

        return Inertia::render('FacultyLoading/AutoSchedule/Index', [
            'schoolYears' => $schoolYears,
            'recentJobs' => $recentJobs,
        ]);
    }

    // ── API: Run scheduler ──────────────────────────────────────────────────

    /**
     * Run the deterministic constraint-based scheduler synchronously and store
     * the result. The generated schedule is conflict-free by construction; any
     * sessions that could not be placed (e.g. an over-subscribed grade) are
     * returned in `unplaceable` alongside a per-section coverage report.
     *
     * POST /faculty-loading/auto-schedule/generate
     * Body: {
     *   school_year_id:   int (required)
     *   academic_term_id: int (required)
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'school_year_id' => 'required|integer',
            'academic_term_id' => 'required|integer',
        ]);

        // Create a job record
        $job = AiScheduleJob::create([
            'school_year_id' => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'status' => 'running',
            'parameters' => ['engine' => 'deterministic'],
            'started_at' => now(),
            'created_by' => Auth::id(),
        ]);

        try {
            $result = $this->scheduler->generate(
                schoolYearId: (int) $data['school_year_id'],
                termId: (int) $data['academic_term_id'],
            );

            $job->update([
                'status' => 'completed',
                'fitness_score' => $result['fitness'],
                'hard_conflicts' => $result['hard_conflicts'],
                'schedules_generated' => $result['schedules_generated'],
                'generated_schedules' => $result['schedules'],
                'completed_at' => now(),
            ]);

            return response()->json([
                'job' => $this->serializeJob($job->fresh(), true),
                'conflict_suggestions' => $result['conflict_suggestions'] ?? [],
                'unplaceable' => $result['unplaceable'] ?? [],
                'section_report' => $result['section_report'] ?? [],
                'warning' => $result['warning'] ?? null,
            ]);

        } catch (Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Schedule generation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    // ── API: Apply generated schedules ────────────────────────────────────

    /**
     * Save a completed job's generated schedules into class_schedules
     * as 'tentative' rows (existing tentative rows for the term are replaced).
     *
     * POST /faculty-loading/auto-schedule/jobs/{job}/apply
     */
    public function apply(Request $request, AiScheduleJob $aiScheduleJob): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        if (ClassScheduleApprovalService::termIsLocked((int) $aiScheduleJob->academic_term_id)) {
            return response()->json(['message' => 'This term schedule is locked for OCD approval.'], 423);
        }

        if (! $aiScheduleJob->isCompleted()) {
            return response()->json(['message' => 'Job is not in a completed state.'], 422);
        }

        // Allow the client to supply locally-patched schedules (e.g. after applying
        // conflict-resolution suggestions).  Fall back to the stored schedules.
        $schedules = $request->input('schedules') ?? $aiScheduleJob->generated_schedules;
        if (empty($schedules)) {
            return response()->json(['message' => 'No schedules to apply.'], 422);
        }

        // Block the apply when the batch conflicts with already-committed (active)
        // schedules, or contains internal faculty/room/section overlaps of its own —
        // the GA's own fitness score is a soft heuristic, not a guarantee.
        $conflictMessages = $this->detectApplyConflicts($schedules, (int) $aiScheduleJob->academic_term_id);
        if (! empty($conflictMessages)) {
            return response()->json([
                'message' => count($conflictMessages).' conflict(s) detected — resolve before applying.',
                'conflicts' => $conflictMessages,
            ], 422);
        }

        DB::transaction(function () use ($aiScheduleJob, $schedules) {
            // Remove existing tentative schedules for this term
            ClassSchedule::where('academic_term_id', $aiScheduleJob->academic_term_id)
                ->where('status', 'tentative')
                ->delete();

            // Insert new tentative schedules, stripping preview metadata
            $now = now();
            $rows = array_map(function ($s) use ($now) {
                return [
                    'load_assignment_id' => $s['load_assignment_id'],
                    'user_id' => $s['user_id'],
                    'subject_id' => $s['subject_id'],
                    'section_id' => $s['section_id'],
                    'classroom_id' => $s['classroom_id'],
                    'school_year_id' => $s['school_year_id'],
                    'academic_term_id' => $s['academic_term_id'],
                    'session_type' => $s['session_type'] ?? 'regular',
                    'day_of_week' => $s['day_of_week'],
                    'start_time' => $s['start_time'],
                    'end_time' => $s['end_time'],
                    'status' => 'tentative',
                    'remarks' => $s['remarks'] ?? 'AI-generated schedule',
                    'created_by' => Auth::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $schedules);

            ClassSchedule::insert($rows);
        });

        return response()->json([
            'message' => count($schedules).' schedules saved as tentative successfully.',
        ]);
    }

    /**
     * Check a proposed schedule batch for:
     *   1. Faculty/room/section overlap against already-committed ACTIVE schedules
     *      for the term (tentative rows are excluded — this batch is about to
     *      replace them, so comparing against them would produce false positives).
     *   2. Faculty/room/section overlap WITHIN the batch itself — the GA's fitness
     *      score only penalizes this, it doesn't guarantee zero conflicts.
     *
     * @return string[] Human-readable conflict descriptions (empty = clean)
     */
    private function detectApplyConflicts(array $schedules, int $termId): array
    {
        $messages = [];
        $axes = ['user_id' => 'Faculty', 'classroom_id' => 'Room', 'section_id' => 'Section'];

        // 1. Against already-active (committed) schedules
        foreach ($schedules as $s) {
            foreach ($axes as $column => $label) {
                $value = $s[$column] ?? null;
                if (! $value) {
                    continue;
                }

                $exists = ClassSchedule::active()
                    ->where($column, $value)
                    ->where('day_of_week', $s['day_of_week'])
                    ->where('academic_term_id', $termId)
                    ->where('start_time', '<', $s['end_time'])
                    ->where('end_time', '>', $s['start_time'])
                    ->exists();

                if ($exists) {
                    $messages[] = "{$label} conflict: {$s['day_of_week']} {$s['start_time']}–{$s['end_time']} "
                        .'overlaps an already-active (committed) schedule.';
                }
            }
        }

        // 2. Internal self-consistency within the proposed batch
        $n = count($schedules);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $schedules[$i];
                $b = $schedules[$j];

                if (($a['day_of_week'] ?? null) !== ($b['day_of_week'] ?? null)) {
                    continue;
                }
                if (! $this->conflicts->timesOverlap($a['start_time'], $a['end_time'], $b['start_time'], $b['end_time'])) {
                    continue;
                }

                foreach ($axes as $column => $label) {
                    if (! empty($a[$column]) && ($a[$column] ?? null) === ($b[$column] ?? null)) {
                        $messages[] = "{$label} conflict within generated batch: {$a['day_of_week']} "
                            ."{$a['start_time']}–{$a['end_time']} overlaps {$b['start_time']}–{$b['end_time']}.";
                    }
                }
            }
        }

        return array_values(array_unique($messages));
    }

    // ── API: Job History ──────────────────────────────────────────────────

    /**
     * List past jobs for a term.
     *
     * GET /faculty-loading/auto-schedule/jobs?academic_term_id=X
     */
    public function jobs(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $query = AiScheduleJob::with('createdBy:id,name', 'academicTerm')
            ->orderByDesc('created_at');

        if ($request->filled('academic_term_id')) {
            $query->where('academic_term_id', $request->integer('academic_term_id'));
        }

        $jobs = $query->limit(20)->get()->map(fn ($j) => $this->serializeJob($j, false));

        return response()->json($jobs);
    }

    // ── API: Show single job (with full schedules) ────────────────────────

    /**
     * GET /faculty-loading/auto-schedule/jobs/{job}
     */
    public function showJob(AiScheduleJob $aiScheduleJob): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        return response()->json($this->serializeJob($aiScheduleJob, true));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function serializeJob(AiScheduleJob $job, bool $includeSchedules): array
    {
        return [
            'id' => $job->id,
            'school_year_id' => $job->school_year_id,
            'academic_term_id' => $job->academic_term_id,
            'academic_term_label' => $job->academicTerm?->name ?? '—',
            'status' => $job->status,
            'parameters' => $job->parameters,
            'fitness_score' => $job->fitness_score,
            'hard_conflicts' => $job->hard_conflicts,
            'schedules_generated' => $job->schedules_generated,
            'error_message' => $job->error_message,
            'duration_seconds' => $job->duration_seconds,
            'created_by_name' => $job->createdBy?->name ?? '—',
            'created_at' => $job->created_at?->toISOString(),
            'completed_at' => $job->completed_at?->toISOString(),
            'schedules' => $includeSchedules ? ($job->generated_schedules ?? []) : [],
        ];
    }
}
