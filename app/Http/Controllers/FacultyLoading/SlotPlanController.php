<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Services\FacultyLoading\ScheduleGeneratorService;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SlotPlanController
 *
 * Phase 13 — Integrates ScheduleGeneratorService into the web interface.
 *
 * Provides a deterministic, rule-based slot plan preview for a single grade.
 * Unlike the GA-based AutoScheduleController, this controller uses the
 * constraint-first placement pipeline (Phases 7–11) and produces an
 * immediate, conflict-free slot plan without stochastic search.
 *
 * Routes:
 *   GET  /faculty-loading/auto-schedule/slot-plan          → index (Inertia page)
 *   POST /faculty-loading/auto-schedule/slot-plan/preview  → preview (JSON)
 */
class SlotPlanController extends Controller
{
    public function __construct(
        private readonly ScheduleGeneratorService $generator
    ) {}

    // =========================================================================
    // Inertia page
    // =========================================================================

    /**
     * Render the Slot Plan generator page.
     *
     * Passes:
     *   schoolYears  — for the term selector (same shape as AutoScheduleController)
     *   grades       — [{value:int, label:string}] for the grade selector
     *   subjectTypes — mapping used by the UI to explain type categorisation
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

        $grades = collect(range(7, 12))->map(fn ($g) => [
            'value' => $g,
            'label' => "Grade {$g}",
        ])->values();

        return Inertia::render('FacultyLoading/AutoSchedule/SlotPlan', [
            'schoolYears' => $schoolYears,
            'grades'      => $grades,
        ]);
    }

    // =========================================================================
    // Preview endpoint
    // =========================================================================

    /**
     * Generate a slot plan preview for a grade.
     *
     * POST body:
     * {
     *   grade:            int       (7–12, required)
     *   academic_term_id: int|null  (optional — used only for display context)
     *   subject_type_map: object    (optional — override type mapping per subject_type value)
     * }
     *
     * Returns:
     * {
     *   plan: GenerationResult,   // see ScheduleGeneratorService docblock
     *   subjects_loaded: int,     // number of active subjects found for this grade
     *   validation: { passes, violations },
     *   anti_collision: { passes, violations },
     * }
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'grade'            => 'required|integer|min:7|max:12',
            'academic_term_id' => 'nullable|integer',
        ]);

        $grade = (int) $data['grade'];

        // Load active subjects for this grade from the DB
        $dbSubjects = Subject::active()
            ->forGrade($grade)
            ->orderBy('name')
            ->get();

        // Map DB subject_type → generator type ('core' | 'elective' | 'science_core')
        $subjects = $dbSubjects->map(fn ($s) => [
            'name'             => $s->name,
            'type'             => $this->mapSubjectType($s->subject_type, $grade),
            'sessions_per_week'=> max(1, (int) $s->sessions_per_week),
        ])->values()->all();

        $sectionNames = SchedulingConstants::GRADE_SECTIONS[$grade]
            ?? array_values(SchedulingConstants::GRADE_SECTIONS)[0];

        // Run the deterministic slot plan generator
        $plan       = $this->generator->generateSlotPlan($grade, $subjects, $sectionNames);
        $validation = $this->generator->validateResult($plan);
        $antiCol    = $this->generator->checkCrossSection($plan);

        return response()->json([
            'plan'             => $plan,
            'subjects_loaded'  => count($subjects),
            'validation'       => $validation,
            'anti_collision'   => $antiCol,
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Map the DB subject_type enum value to the generator's type string.
     *
     * DB enum: lecture | laboratory | lecture_lab | research | elective
     * Generator types: core | elective | science_core
     *
     * Mapping rules:
     *   elective                → 'elective'
     *   laboratory (G11/G12)    → 'science_core'  (lab slots in SHS = Science Core)
     *   everything else         → 'core'
     */
    private function mapSubjectType(string $dbType, int $grade): string
    {
        if ($dbType === 'elective') {
            return 'elective';
        }

        if ($dbType === 'laboratory' && $grade >= 11) {
            return 'science_core';
        }

        return 'core';
    }
}
