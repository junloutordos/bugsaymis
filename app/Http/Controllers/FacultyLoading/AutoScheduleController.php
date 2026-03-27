<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AiScheduleJob;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\GeneticSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AutoScheduleController extends Controller
{
    public function __construct(private readonly GeneticSchedulingService $ga) {}

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
                'id'         => $sy->id,
                'name'       => $sy->name,
                'is_current' => $sy->is_current,
                'terms'      => $sy->terms->map(fn ($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'label'      => $t->label ?? $t->name,
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
            'recentJobs'  => $recentJobs,
        ]);
    }

    // ── API: Run GA ───────────────────────────────────────────────────────

    /**
     * Execute the Genetic Algorithm synchronously and store the result.
     *
     * POST /faculty-loading/auto-schedule/generate
     * Body: {
     *   school_year_id:  int  (required)
     *   academic_term_id: int  (required)
     *   population_size:  int   (optional, 10–100)
     *   mutation_rate:    float (optional, 0.01–0.30)
     *   max_generations:  int   (optional, 20–500)
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'school_year_id'   => 'required|integer',
            'academic_term_id' => 'required|integer',
            'population_size'  => 'nullable|integer|min:10|max:100',
            'mutation_rate'    => 'nullable|numeric|min:0.01|max:0.30',
            'max_generations'  => 'nullable|integer|min:20|max:500',
        ]);

        $params = array_filter([
            'population_size'  => $data['population_size']  ?? null,
            'mutation_rate'    => $data['mutation_rate']    ?? null,
            'max_generations'  => $data['max_generations']  ?? null,
        ], fn ($v) => $v !== null);

        // Create a job record
        $job = AiScheduleJob::create([
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'status'           => 'running',
            'parameters'       => $params + [
                'population_size'  => $params['population_size']  ?? 30,
                'mutation_rate'    => $params['mutation_rate']    ?? 0.05,
                'max_generations'  => $params['max_generations']  ?? 100,
            ],
            'started_at' => now(),
            'created_by' => Auth::id(),
        ]);

        try {
            $result = $this->ga->generate(
                schoolYearId: (int) $data['school_year_id'],
                termId:       (int) $data['academic_term_id'],
                params:       $params,
            );

            $job->update([
                'status'              => 'completed',
                'fitness_score'       => $result['fitness'],
                'hard_conflicts'      => $result['hard_conflicts'],
                'schedules_generated' => $result['schedules_generated'],
                'generated_schedules' => $result['schedules'],
                'completed_at'        => now(),
            ]);

            return response()->json([
                'job'     => $this->serializeJob($job->fresh(), true),
                'warning' => $result['warning'] ?? null,
            ]);

        } catch (Throwable $e) {
            $job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            return response()->json([
                'message' => 'Schedule generation failed: ' . $e->getMessage(),
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

        if (! $aiScheduleJob->isCompleted()) {
            return response()->json(['message' => 'Job is not in a completed state.'], 422);
        }

        $schedules = $aiScheduleJob->generated_schedules;
        if (empty($schedules)) {
            return response()->json(['message' => 'No schedules to apply.'], 422);
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
                    'user_id'            => $s['user_id'],
                    'subject_id'         => $s['subject_id'],
                    'section_id'         => $s['section_id'],
                    'classroom_id'       => $s['classroom_id'],
                    'school_year_id'     => $s['school_year_id'],
                    'academic_term_id'   => $s['academic_term_id'],
                    'day_of_week'        => $s['day_of_week'],
                    'start_time'         => $s['start_time'],
                    'end_time'           => $s['end_time'],
                    'status'             => 'tentative',
                    'remarks'            => $s['remarks'] ?? 'AI-generated schedule',
                    'created_by'         => Auth::id(),
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }, $schedules);

            ClassSchedule::insert($rows);
        });

        return response()->json([
            'message' => count($schedules) . ' schedules saved as tentative successfully.',
        ]);
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
            'id'                  => $job->id,
            'school_year_id'      => $job->school_year_id,
            'academic_term_id'    => $job->academic_term_id,
            'academic_term_label' => $job->academicTerm?->name ?? '—',
            'status'              => $job->status,
            'parameters'          => $job->parameters,
            'fitness_score'       => $job->fitness_score,
            'hard_conflicts'      => $job->hard_conflicts,
            'schedules_generated' => $job->schedules_generated,
            'error_message'       => $job->error_message,
            'duration_seconds'    => $job->duration_seconds,
            'created_by_name'     => $job->createdBy?->name ?? '—',
            'created_at'          => $job->created_at?->toISOString(),
            'completed_at'        => $job->completed_at?->toISOString(),
            'schedules'           => $includeSchedules ? ($job->generated_schedules ?? []) : [],
        ];
    }
}
