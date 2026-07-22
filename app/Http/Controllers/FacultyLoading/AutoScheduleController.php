<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Jobs\SyncComputerLabBookings;
use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AiScheduleJob;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use App\Services\FacultyLoading\ClassScheduleScopeLockService;
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
        private readonly ClassScheduleScopeLockService $scopeLocks,
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
     *   grade_levels:     int[] (optional — e.g. [7] regenerates only Grade 7;
     *                     empty/omitted = all grades, the original behavior)
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'school_year_id' => 'required|integer',
            'academic_term_id' => 'required|integer',
            'grade_levels' => 'nullable|array',
            'grade_levels.*' => 'integer|between:7,12',
        ]);

        $gradeLevels = array_values(array_unique(array_map('intval', (array) ($data['grade_levels'] ?? []))));
        sort($gradeLevels);

        // Create a job record — the grade scope is stored on the job so apply()
        // and restore() use the server-side scope, never a client-supplied one.
        $job = AiScheduleJob::create([
            'school_year_id' => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'status' => 'running',
            'parameters' => ['engine' => 'deterministic', 'grade_levels' => $gradeLevels],
            'started_at' => now(),
            'created_by' => Auth::id(),
        ]);

        try {
            $result = $this->scheduler->generate(
                schoolYearId: (int) $data['school_year_id'],
                termId: (int) $data['academic_term_id'],
                params: ['grade_levels' => $gradeLevels],
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
     * as 'tentative' rows. Unscoped jobs replace every tentative row for the
     * term (original behavior); grade-scoped jobs replace only the scoped
     * grades' tentative CLASS rows — other grades' tentative schedules and all
     * non-teaching blocks survive. The replaced rows are snapshotted onto the
     * job (replaced_schedules) so the apply can be rolled back via restore().
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

        $gradeLevels      = $this->jobGradeLevels($aiScheduleJob);
        $scopedSectionIds = $this->scopedSectionIds($aiScheduleJob, $gradeLevels);

        // A scoped batch must only contain rows for the scoped sections —
        // anything else would be inserted without its old counterpart deleted.
        if ($gradeLevels !== []) {
            $outOfScope = array_filter(
                $schedules,
                fn ($s) => ! in_array((int) ($s['section_id'] ?? 0), $scopedSectionIds, true)
            );
            if (! empty($outOfScope)) {
                return response()->json([
                    'message' => 'This job is scoped to Grade '.implode(', ', $gradeLevels).' — '
                        .count($outOfScope).' schedule(s) in the batch belong to other sections.',
                ], 422);
            }
        }

        // Block the apply when the batch conflicts with schedules it will NOT
        // replace (active rows; for scoped jobs also surviving tentative rows),
        // or contains internal faculty/room/section overlaps of its own.
        $conflictMessages = $this->detectApplyConflicts(
            $schedules,
            (int) $aiScheduleJob->academic_term_id,
            $gradeLevels !== [],
            $scopedSectionIds,
        );
        if (! empty($conflictMessages)) {
            return response()->json([
                'message' => count($conflictMessages).' conflict(s) detected — resolve before applying.',
                'conflicts' => $conflictMessages,
            ], 422);
        }

        DB::transaction(function () use ($aiScheduleJob, $schedules, $gradeLevels, $scopedSectionIds) {
            $term = AcademicTerm::whereKey($aiScheduleJob->academic_term_id)->lockForUpdate()->firstOrFail();
            if ($gradeLevels === []) {
                $this->scopeLocks->assertScopeUnlocked($term, 'whole');
            } else {
                foreach ($gradeLevels as $grade) {
                    $this->scopeLocks->assertScopeUnlocked($term, 'grade', (int) $grade);
                }
            }
            // Remove the tentative schedules this job replaces — snapshotting
            // them first so the apply can be undone (restore endpoint).
            $deleteQuery = ClassSchedule::where('academic_term_id', $aiScheduleJob->academic_term_id)
                ->where('status', 'tentative');
            if ($gradeLevels !== []) {
                $deleteQuery->where('entry_type', 'class')
                    ->whereIn('section_id', $scopedSectionIds);
            }

            $replaced = (clone $deleteQuery)->get()
                ->map(fn ($row) => $row->getAttributes())
                ->all();
            $aiScheduleJob->update(['replaced_schedules' => $replaced, 'restored_at' => null]);

            $deleteQuery->delete();

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

        SyncComputerLabBookings::dispatch((int) $aiScheduleJob->academic_term_id);

        return response()->json([
            'message' => count($schedules).' schedules saved as tentative successfully.',
        ]);
    }

    /**
     * Roll back an applied job: delete the tentative class schedules in the
     * job's scope and re-insert the snapshot taken when it was applied.
     *
     * POST /faculty-loading/auto-schedule/jobs/{job}/restore
     */
    public function restore(AiScheduleJob $aiScheduleJob): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        if (ClassScheduleApprovalService::termIsLocked((int) $aiScheduleJob->academic_term_id)) {
            return response()->json(['message' => 'This term schedule is locked for OCD approval.'], 423);
        }

        $snapshot = $aiScheduleJob->replaced_schedules;
        if ($snapshot === null) {
            return response()->json(['message' => 'This job has no snapshot — it was never applied.'], 422);
        }

        $gradeLevels      = $this->jobGradeLevels($aiScheduleJob);
        $scopedSectionIds = $this->scopedSectionIds($aiScheduleJob, $gradeLevels);

        DB::transaction(function () use ($aiScheduleJob, $snapshot, $gradeLevels, $scopedSectionIds) {
            $term = AcademicTerm::whereKey($aiScheduleJob->academic_term_id)->lockForUpdate()->firstOrFail();
            if ($gradeLevels === []) {
                $this->scopeLocks->assertScopeUnlocked($term, 'whole');
            } else {
                foreach ($gradeLevels as $grade) {
                    $this->scopeLocks->assertScopeUnlocked($term, 'grade', (int) $grade);
                }
            }
            // Delete what the apply inserted (plus any tentative edits made in
            // its scope since) — same scope as the apply's own delete.
            $deleteQuery = ClassSchedule::where('academic_term_id', $aiScheduleJob->academic_term_id)
                ->where('status', 'tentative');
            if ($gradeLevels !== []) {
                $deleteQuery->where('entry_type', 'class')
                    ->whereIn('section_id', $scopedSectionIds);
            }
            $deleteQuery->delete();

            // Re-insert the snapshot verbatim (fresh primary keys).
            $rows = array_map(function ($row) {
                unset($row['id']);
                return $row;
            }, $snapshot);
            if (! empty($rows)) {
                ClassSchedule::insert($rows);
            }

            $aiScheduleJob->update(['restored_at' => now()]);
        });

        SyncComputerLabBookings::dispatch((int) $aiScheduleJob->academic_term_id);

        return response()->json([
            'message' => count($snapshot).' schedule(s) restored from the pre-apply snapshot.',
        ]);
    }

    /** Grade scope stored on a job at generation time ([] = unscoped). */
    private function jobGradeLevels(AiScheduleJob $job): array
    {
        $grades = array_values(array_unique(array_map(
            'intval',
            (array) (($job->parameters ?? [])['grade_levels'] ?? [])
        )));
        sort($grades);

        return $grades;
    }

    /**
     * Section ids covered by a job's grade scope (its school year's sections
     * at those grade levels, including ELEC-* synthetics). Empty when unscoped.
     *
     * @return array<int,int>
     */
    private function scopedSectionIds(AiScheduleJob $job, array $gradeLevels): array
    {
        if ($gradeLevels === []) {
            return [];
        }

        return Section::where('school_year_id', $job->school_year_id)
            ->whereIn('levelid', $gradeLevels)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Check a proposed schedule batch for:
     *   1. Faculty/room/section overlap against schedules the apply will NOT
     *      replace. Unscoped: ACTIVE rows only (every tentative row is about to
     *      be replaced, so comparing against them would produce false positives).
     *      Scoped: all occupying rows except the scoped sections' tentative
     *      class rows — other grades' tentative schedules survive the apply and
     *      must be conflict-checked.
     *   2. Faculty/room/section overlap WITHIN the batch itself — the GA's fitness
     *      score only penalizes this, it doesn't guarantee zero conflicts.
     *
     * @return string[] Human-readable conflict descriptions (empty = clean)
     */
    private function detectApplyConflicts(array $schedules, int $termId, bool $scoped = false, array $scopedSectionIds = []): array
    {
        $messages = [];
        $axes = ['user_id' => 'Faculty', 'classroom_id' => 'Room', 'section_id' => 'Section'];

        // 1. Against schedules that will survive the apply
        foreach ($schedules as $s) {
            foreach ($axes as $column => $label) {
                $value = $s[$column] ?? null;
                if (! $value) {
                    continue;
                }

                $query = ClassSchedule::query()
                    ->where($column, $value)
                    ->where('day_of_week', $s['day_of_week'])
                    ->where('academic_term_id', $termId)
                    ->where('start_time', '<', $s['end_time'])
                    ->where('end_time', '>', $s['start_time']);

                if ($scoped) {
                    $query->occupying()->whereNot(fn ($w) => $w
                        ->where('status', 'tentative')
                        ->where('entry_type', 'class')
                        ->whereIn('section_id', $scopedSectionIds));
                } else {
                    $query->active();
                }

                if ($query->exists()) {
                    $messages[] = "{$label} conflict: {$s['day_of_week']} {$s['start_time']}–{$s['end_time']} "
                        .'overlaps an existing schedule that this apply will not replace.';
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
            'grade_levels' => $this->jobGradeLevels($job),
            'has_snapshot' => $job->replaced_schedules !== null,
            'restored_at' => $job->restored_at?->toISOString(),
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
