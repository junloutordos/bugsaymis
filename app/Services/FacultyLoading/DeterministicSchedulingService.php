<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\TeacherOfficialTime;
use App\Models\User;

/**
 * DeterministicSchedulingService
 *
 * Constraint-based weekly class-schedule generator. Replaces the genetic
 * algorithm (which only soft-penalised conflicts and emitted best-effort,
 * conflicted results). This service places every teaching session onto the
 * canonical per-grade period grid (see {@see SchedulingConstants}) such that:
 *
 *   • a section is never double-booked (one class per period per section)
 *   • a faculty member is never double-booked (no two classes at overlapping
 *     clock times on the same day, even across different grades)
 *   • placements only ever land on real CLASS periods — flag ceremony, homeroom
 *     / advising, recess, lunch and consultation windows are never used
 *   • Wednesday activity/ALP windows and configured Friday ILA days are
 *     excluded automatically
 *   • each section uses its own fixed room (sections.classroom_id), so room
 *     clashes are structurally impossible for homeroom sections
 *
 * Because placement is constraint-first, the output is conflict-free by
 * construction. When the weekly load exceeds the available periods (e.g. an
 * over-subscribed grade), the surplus sessions are reported as "unplaceable"
 * rather than being force-fit into a conflicting slot.
 */
class DeterministicSchedulingService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** Maximum displacement depth for the relocation (augmenting-path) search. */
    private const RELOCATION_DEPTH = 4;

    /**
     * Number of randomized restart attempts when sessions remain unplaced.
     * The loop exits as soon as a fully-placed (zero-unplaced) schedule is found,
     * so this cost is only paid for genuinely over-subscribed grades.
     */
    private const MAX_RESTARTS = 150;

    /**
     * Once a fully-placed (zero-unplaced) schedule is found, spend this many
     * extra randomized-restart attempts searching for a lower aggregate soft
     * score (fewer idle gaps, better day balance) instead of stopping at the
     * first feasible result. Feasibility always wins — a candidate that drops
     * even one session is never preferred over a lower-quality feasible one.
     */
    private const QUALITY_RESTART_ATTEMPTS = 20;

    /** Minutes an Independent Learning Period session occupies within its period. */
    private const ILP_MINUTES = 30;

    /** Overflow periods are a last resort after every standard period. */
    private const OVERFLOW_PENALTY = 1000000;

    /** @var array<int,array<int,array<string,mixed>>> available slots per grade */
    private array $gridByGrade = [];
    /** @var array<int,array<string,int>> earliest start_min per grade+day (the day's first period) */
    private array $firstPeriodByGrade = [];
    /** @var array<int,array<string,int>> ILP placements so far per section+day (for even weekly spread) */
    private array $sectionIlpDayCount = [];
    /** @var array<int,array<string,array<int,array{0:int,1:int,2:int}>>> */
    private array $sectionBusy = [];
    /** @var array<int,array<string,array<int,array{0:int,1:int,2:int}>>> */
    private array $facultyBusy = [];
    /** @var array<int,array<string,int>> */
    private array $sectionDayCount = [];
    /** @var array<int,array<int,array<string,int>>> */
    private array $sectionSubjDays = [];
    /** @var array<int,array<string,int>> teaching sessions placed so far per faculty+day (soft day-spread) */
    private array $facultyDayCount = [];
    /** Soft score of the slot findBestSlot() most recently returned (paired with its result). */
    private int $lastSlotScore = 0;
    /** @var array<int,?array{s:array<string,mixed>,slot:array<string,mixed>}> */
    private array $placements = [];
    /** @var array<int,array<string,array{start:int,end:int}>> */
    private array $officialTimeByFacultyDay = [];

    /**
     * Generate a conflict-free schedule for the given term.
     *
     * Pass $params['grade_levels'] (e.g. [7]) to regenerate only those grades:
     * requirements are filtered to the scoped grades, and every occupying row
     * outside the scope — other grades' classes (active AND tentative) plus all
     * non-teaching blocks — is seeded as an immovable busy interval, so
     * cross-grade faculty commitments are respected. Within the scoped grades,
     * active rows stay committed as usual; only their tentative class rows are
     * treated as replaceable (the scoped apply deletes exactly those).
     *
     * @return array{
     *   fitness:int, hard_conflicts:int, schedules_generated:int,
     *   schedules:array<int,array<string,mixed>>, conflict_suggestions:array,
     *   unplaceable:array<int,array<string,mixed>>,
     *   section_report:array<int,array<string,mixed>>, warning:?string
     * }
     */
    public function generate(int $schoolYearId, int $termId, array $params = []): array
    {
        $this->officialTimeByFacultyDay = [];
        $gradeLevels = array_values(array_unique(array_map('intval', (array) ($params['grade_levels'] ?? []))));

        $requirements = $this->buildRequirements($schoolYearId, $termId);
        if ($gradeLevels !== []) {
            $requirements = array_values(array_filter(
                $requirements,
                fn ($r) => in_array($r['grade'], $gradeLevels, true)
            ));
        }

        if (empty($requirements)) {
            $scopeLabel = $gradeLevels === [] ? '' : ' for Grade ' . implode(', ', $gradeLevels);

            return [
                'fitness'              => 0,
                'hard_conflicts'       => 0,
                'schedules_generated'  => 0,
                'schedules'            => [],
                'conflict_suggestions' => [],
                'unplaceable'          => [],
                'section_report'       => [],
                'warning'              => "No teaching assignments with a subject and section were found{$scopeLabel} for this term.",
            ];
        }

        // Sections whose tentative class rows this scoped run may regenerate
        // (empty = unscoped run, every tentative row is replaceable).
        $scopedSectionIds = [];
        if ($gradeLevels !== []) {
            $scopedSectionIds = Section::where('school_year_id', $schoolYearId)
                ->whereIn('levelid', $gradeLevels)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // A full AI run replaces tentative rows, but committed rows remain in
        // place. Count their load sessions so we generate only the balance,
        // and seed them as immovable intervals during every placement attempt.
        $requirementIds = array_column($requirements, 'load_assignment_id');
        $committedRows  = ClassSchedule::active()
            ->classes()
            ->where('academic_term_id', $termId)
            ->whereIn('load_assignment_id', $requirementIds)
            ->get(['load_assignment_id', 'section_id', 'session_type']);

        $committedByLoad = $committedRows
            ->groupBy('load_assignment_id')
            ->map(fn ($rows) => [
                'count'     => $rows->count(),
                'ilp_count' => $rows->where('session_type', 'ilp')->count(),
            ]);
        $committedBySection = $committedRows
            ->groupBy('section_id')
            ->map(fn ($rows) => $rows->count())
            ->all();

        $remainingRequirements = [];
        foreach ($requirements as $requirement) {
            $committed = $committedByLoad->get($requirement['load_assignment_id'], [
                'count' => 0, 'ilp_count' => 0,
            ]);
            $requirement['total_sessions_needed'] = $requirement['sessions_needed'];
            $requirement['sessions_needed']       = max(0, $requirement['sessions_needed'] - $committed['count']);
            $requirement['committed_ilp_count']   = $committed['ilp_count'];

            if ($requirement['sessions_needed'] > 0) {
                $remainingRequirements[] = $requirement;
            }
        }

        if (empty($remainingRequirements)) {
            return [
                'fitness'              => 0,
                'hard_conflicts'       => 0,
                'schedules_generated'  => 0,
                'schedules'            => [],
                'conflict_suggestions' => [],
                'unplaceable'          => [],
                'section_report'       => $this->buildSectionReport($requirements, [], $committedBySection),
                'warning'              => null,
            ];
        }

        // Pre-compute the available period grid per grade level (shared by all
        // sections of that grade).
        $this->buildGrids(array_unique(array_column($remainingRequirements, 'grade')));

        // Same-weekday-across-sections: for each grade+subject, decide which
        // weekday each session index lands on — shared by every section of
        // that grade offering the subject (e.g. "Math G7 session 1" is
        // Monday for every G7 section). Electives are exempt — they already
        // use their own cross-section elective windows.
        $subjectDayAssignment = $this->assignSubjectDays($remainingRequirements);

        // Flatten requirements into individual session instances. A subject
        // with has_ilp reserves its LAST weekly session as the Independent
        // Learning Period — only when it has at least 2 sessions, otherwise
        // its only session stays a regular class.
        $baseSessions = [];
        foreach ($remainingRequirements as $req) {
            $ilpEligible = ($req['has_ilp'] ?? false)
                && $req['total_sessions_needed'] >= 2
                && $req['committed_ilp_count'] === 0;
            $forcedDays  = $subjectDayAssignment[$req['grade']][$req['subject_id']] ?? null;
            for ($i = 0; $i < $req['sessions_needed']; $i++) {
                $session = $req;
                $session['session_type'] = ($ilpEligible && $i === $req['sessions_needed'] - 1) ? 'ilp' : 'regular';
                if ($forcedDays !== null && isset($forcedDays[$i])) {
                    $session['forced_day'] = $forcedDays[$i];
                }
                $baseSessions[] = $session;
            }
        }

        // Attempt 0 — deterministic most-constrained-first ordering: place the
        // busiest faculty's sessions earliest so tight calendars are satisfied
        // before the grid fills up.
        $ordered = $baseSessions;
        usort($ordered, function ($a, $b) {
            return [$b['faculty_total'], $b['sessions_needed'], $a['section_id']]
                <=> [$a['faculty_total'], $a['sessions_needed'], $b['section_id']];
        });
        $best = $this->runPlacement($ordered, $schoolYearId, $termId, $scopedSectionIds);

        // Randomized restarts — when a near-fully-packed week leaves feasible
        // sessions unplaced, a different tie-break order often resolves them.
        // PHP's usort is stable, so shuffling first then sorting by the
        // constraint key keeps most-constrained-first while varying ties.
        $attempt = 1;
        for (; $attempt <= self::MAX_RESTARTS && ! empty($best['unplaceable']); $attempt++) {
            mt_srand($attempt * 7919);
            $shuffled = $baseSessions;
            shuffle($shuffled);
            usort($shuffled, fn ($a, $b) =>
                [$b['faculty_total'], $b['sessions_needed']] <=> [$a['faculty_total'], $a['sessions_needed']]);

            $candidate = $this->runPlacement($shuffled, $schoolYearId, $termId, $scopedSectionIds);
            if (count($candidate['unplaceable']) < count($best['unplaceable'])) {
                $best = $candidate;
            }
        }

        // Feasibility is settled (or exhausted) above — never traded away below.
        // If a fully-placed schedule was found, spend a small bounded extra
        // budget searching for a lower aggregate soft score (fewer idle gaps,
        // better day balance) instead of keeping whichever ordering happened
        // to reach zero-unplaced first.
        if (empty($best['unplaceable'])) {
            $qualityCap = $attempt + self::QUALITY_RESTART_ATTEMPTS;
            for (; $attempt <= $qualityCap; $attempt++) {
                mt_srand($attempt * 7919);
                $shuffled = $baseSessions;
                shuffle($shuffled);
                usort($shuffled, fn ($a, $b) =>
                    [$b['faculty_total'], $b['sessions_needed']] <=> [$a['faculty_total'], $a['sessions_needed']]);

                $candidate = $this->runPlacement($shuffled, $schoolYearId, $termId, $scopedSectionIds);
                if (! empty($candidate['unplaceable'])) {
                    continue;
                }
                if ($candidate['quality_score'] < $best['quality_score']) {
                    $best = $candidate;
                }
            }
        }

        $placed       = $best['placed'];
        $unplaceable  = $best['unplaceable'];

        return [
            'fitness'              => -count($unplaceable),
            'hard_conflicts'       => 0,
            'schedules_generated'  => count($placed),
            'schedules'            => $placed,
            'conflict_suggestions' => [],
            'unplaceable'          => $unplaceable,
            'section_report'       => $this->buildSectionReport($requirements, $placed, $committedBySection),
            'warning'              => empty($unplaceable)
                ? null
                : count($unplaceable) . ' session(s) could not be placed (grade likely over-subscribed). See unplaceable report.',
        ];
    }

    /**
     * Incremental pass: place ONLY the still-needed sessions of unplaced teaching
     * load assignments, treating every existing occupying (active + tentative)
     * class_schedules row as an immovable busy interval. Existing rows are never
     * moved or deleted — relocation may only shuffle sessions placed within this
     * same pass. Nothing is persisted; the caller commits the proposals.
     *
     * @param array<int,int>|null $loadAssignmentIds restrict the pass to these
     *        load assignments (null = every unplaced teaching load for the term)
     * @return array{
     *   proposals:array<int,array<string,mixed>>,
     *   failures:array<int,array<string,mixed>>,
     *   summary:array{loads_considered:int,sessions_placed:int,sessions_failed:int}
     * }
     */
    public function placeRemaining(int $schoolYearId, int $termId, ?array $loadAssignmentIds = null): array
    {
        $this->officialTimeByFacultyDay = [];
        $requirements = $this->buildRequirements($schoolYearId, $termId);
        if ($loadAssignmentIds !== null) {
            $ids          = array_map('intval', $loadAssignmentIds);
            $requirements = array_values(array_filter(
                $requirements,
                fn ($r) => in_array($r['load_assignment_id'], $ids, true)
            ));
        }

        $empty = ['proposals' => [], 'failures' => [], 'summary' => [
            'loads_considered' => 0, 'sessions_placed' => 0, 'sessions_failed' => 0,
        ]];
        if (empty($requirements)) {
            return $empty;
        }

        // Already-scheduled session counts per load (mirrors the unplaced-tray
        // math in ClassScheduleController::buildUnplacedLoads()).
        $counts = ClassSchedule::occupying()
            ->whereIn('load_assignment_id', array_column($requirements, 'load_assignment_id'))
            ->selectRaw("load_assignment_id, COUNT(*) as cnt, SUM(session_type = 'ilp') as ilp_cnt")
            ->groupBy('load_assignment_id')
            ->get()
            ->keyBy('load_assignment_id');

        $lockedUserIds = FacultyLoad::where('academic_term_id', $termId)
            ->where('is_locked', true)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->buildGrids(array_unique(array_column($requirements, 'grade')));
        $this->resetState();
        $this->seedBusyFromDb($termId);

        $sessions        = [];
        $failures        = [];
        $loadsConsidered = 0;
        foreach ($requirements as $req) {
            $row         = $counts->get($req['load_assignment_id']);
            $stillNeeded = max(0, $req['sessions_needed'] - (int) ($row->cnt ?? 0));
            if ($stillNeeded === 0) {
                continue;
            }
            $loadsConsidered++;

            if (in_array((int) $req['real_user_id'], $lockedUserIds, true)) {
                $failures[] = $this->describeFailure($req, $stillNeeded, [
                    'reason_code'  => 'faculty_locked',
                    'reason'       => "{$req['faculty_name']}'s faculty load is locked — unlock it before placing sessions.",
                    'can_reassign' => false,
                ]);
                continue;
            }

            // ILP rule: only when the load has never had an ILP session scheduled —
            // the LAST still-needed session becomes the ILP one.
            $ilpEligible = ($req['has_ilp'] ?? false)
                && $req['sessions_needed'] >= 2
                && (int) ($row->ilp_cnt ?? 0) === 0;
            for ($i = 0; $i < $stillNeeded; $i++) {
                $session                 = $req;
                $session['session_type'] = ($ilpEligible && $i === $stillNeeded - 1) ? 'ilp' : 'regular';
                $sessions[]              = $session;
            }
        }

        if (empty($sessions)) {
            return ['proposals' => [], 'failures' => array_values($failures), 'summary' => [
                'loads_considered' => $loadsConsidered,
                'sessions_placed'  => 0,
                'sessions_failed'  => array_sum(array_column($failures, 'sessions_unplaced')),
            ]];
        }

        // Most-constrained-first, same comparator as generate(). No forced_day /
        // assignSubjectDays() here: existing DB rows already fixed each subject's
        // days, so re-imposing the shared-weekday rule against a partially-filled
        // week would only manufacture infeasibility.
        usort($sessions, function ($a, $b) {
            return [$b['faculty_total'], $b['sessions_needed'], $a['section_id']]
                <=> [$a['faculty_total'], $a['sessions_needed'], $b['section_id']];
        });

        $deferred = [];
        foreach ($sessions as $s) {
            $slot = $this->findBestSlot($s);
            if ($slot === null) {
                $deferred[] = $s;
                continue;
            }
            $this->commit($s, $slot, null, $this->lastSlotScore);
        }

        $failedByLoad = [];
        foreach ($deferred as $s) {
            if (! $this->attemptPlace($s, self::RELOCATION_DEPTH)) {
                $failedByLoad[$s['load_assignment_id']][] = $s;
            }
        }

        $proposals = [];
        foreach ($this->placements as $p) {
            if ($p !== null) {
                $rowOut            = $this->toScheduleRow($p['s'], $p['slot'], $schoolYearId, $termId);
                $rowOut['remarks'] = 'Auto-placed (remaining loads)';
                $proposals[]       = $rowOut;
            }
        }

        foreach ($failedByLoad as $failed) {
            // Diagnose off a regular session when the load has one — an ILP
            // session carries an extra constraint that would skew the reason.
            $sample = $failed[0];
            foreach ($failed as $f) {
                if (($f['session_type'] ?? 'regular') !== 'ilp') {
                    $sample = $f;
                    break;
                }
            }
            $failures[] = $this->describeFailure($sample, count($failed), $this->diagnoseFailure($sample));
        }

        return [
            'proposals' => $proposals,
            'failures'  => array_values($failures),
            'summary'   => [
                'loads_considered' => $loadsConsidered,
                'sessions_placed'  => count($proposals),
                'sessions_failed'  => array_sum(array_column($failures, 'sessions_unplaced')),
            ],
        ];
    }

    /**
     * Rank the feasible free slots for the NEXT still-needed session of one load
     * assignment against the current live calendar. Read-only companion to
     * {@see placeRemaining()} for the per-chip suggestions popover.
     *
     * @return array<string,mixed> {still_needed, session_type, classroom_id,
     *         slots:[{day_of_week,start_time,end_time,score}], reason_code?,
     *         reason?, can_reassign?}
     */
    public function suggestSlotsForLoad(int $schoolYearId, int $termId, int $loadAssignmentId, int $limit = 5): array
    {
        $this->officialTimeByFacultyDay = [];
        $requirements = $this->buildRequirements($schoolYearId, $termId);
        $req          = null;
        foreach ($requirements as $r) {
            if ($r['load_assignment_id'] === $loadAssignmentId) {
                $req = $r;
                break;
            }
        }
        if ($req === null) {
            return [
                'still_needed' => 0, 'slots' => [],
                'reason_code'  => 'not_found',
                'reason'       => 'This load assignment is not schedulable for this term.',
                'can_reassign' => false,
            ];
        }

        $counts = ClassSchedule::occupying()
            ->where('load_assignment_id', $loadAssignmentId)
            ->selectRaw("COUNT(*) as cnt, SUM(session_type = 'ilp') as ilp_cnt")
            ->first();
        $stillNeeded = max(0, $req['sessions_needed'] - (int) ($counts->cnt ?? 0));
        if ($stillNeeded === 0) {
            return [
                'still_needed' => 0, 'slots' => [],
                'reason_code'  => 'fully_placed',
                'reason'       => 'This load is already fully placed — refresh the page.',
                'can_reassign' => false,
            ];
        }

        $isLocked = FacultyLoad::where('academic_term_id', $termId)
            ->where('user_id', $req['real_user_id'])
            ->where('is_locked', true)
            ->exists();
        if ($isLocked) {
            return [
                'still_needed' => $stillNeeded, 'slots' => [],
                'reason_code'  => 'faculty_locked',
                'reason'       => "{$req['faculty_name']}'s faculty load is locked — unlock it before placing sessions.",
                'can_reassign' => false,
            ];
        }

        $this->buildGrids([$req['grade']]);
        $this->resetState();
        $this->seedBusyFromDb($termId);

        // The suggestion is for the NEXT session: regular until only the last
        // one remains, which becomes the ILP session when the rule applies.
        $ilpEligible = ($req['has_ilp'] ?? false)
            && $req['sessions_needed'] >= 2
            && (int) ($counts->ilp_cnt ?? 0) === 0;
        $s                 = $req;
        $s['session_type'] = ($ilpEligible && $stillNeeded === 1) ? 'ilp' : 'regular';

        $slots = $this->rankSlots($s, $limit);
        $base  = [
            'still_needed' => $stillNeeded,
            'session_type' => $s['session_type'],
            'classroom_id' => $req['classroom_id'],
            'slots'        => $slots,
        ];

        return empty($slots) ? array_merge($base, $this->diagnoseFailure($s)) : $base;
    }

    /**
     * Run one full placement pass (greedy + relocation) over a session ordering,
     * resetting all scheduling state first. Returns the placed schedule rows and
     * the sessions that could not be placed.
     *
     * @param array<int,array<string,mixed>> $sessions
     * @param array<int,int> $scopedSectionIds grade-scoped run: only these
     *        sections' tentative class rows are replaceable — everything else
     *        occupying (other grades' tentative rows, non-teaching blocks) is
     *        seeded as immovable. Empty = unscoped full-term run.
     * @return array{placed:array<int,array<string,mixed>>,unplaceable:array<int,array<string,mixed>>}
     */
    private function runPlacement(array $sessions, int $schoolYearId, int $termId, array $scopedSectionIds = []): array
    {
        $this->resetState();
        if ($scopedSectionIds === []) {
            $this->seedBusyFromDb($termId, ['active']);
        } else {
            $this->seedBusyFromDb($termId, ['active', 'tentative'], $scopedSectionIds);
        }

        // Pass 1 — greedy placement.
        $deferred = [];
        foreach ($sessions as $s) {
            $slot = $this->findBestSlot($s);
            if ($slot === null) {
                $deferred[] = $s;
                continue;
            }
            $this->commit($s, $slot, null, $this->lastSlotScore);
        }

        // Pass 2 — recursive relocation (bounded augmenting-path search).
        $unplaceable = [];
        foreach ($deferred as $s) {
            if (! $this->attemptPlace($s, self::RELOCATION_DEPTH)) {
                $unplaceable[] = $this->describeSession($s);
            }
        }

        $placed        = [];
        $qualityScore  = 0;
        foreach ($this->placements as $p) {
            if ($p !== null) {
                $placed[]      = $this->toScheduleRow($p['s'], $p['slot'], $schoolYearId, $termId);
                $qualityScore += $p['score'] ?? 0;
            }
        }

        return ['placed' => $placed, 'unplaceable' => $unplaceable, 'quality_score' => $qualityScore];
    }

    // ── Requirements ────────────────────────────────────────────────────────

    /**
     * Build the list of teaching requirements grouped from load_assignments.
     * Synthetic elective sections (ELEC-*) are excluded — they use cross-section
     * elective windows that are handled separately.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildRequirements(int $schoolYearId, int $termId): array
    {
        $assignments = LoadAssignment::with(['subject', 'faculty:id,name'])
            ->where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->get();

        // Sections for this school year, keyed by id (avoids a per-row query and
        // a model relationship dependency).
        $sections = Section::where('school_year_id', $schoolYearId)->get()->keyBy('id');

        // Classroom names, keyed by id (for display in the preview).
        $classroomNames = Classroom::pluck('name', 'id');

        // Faculty total teaching sessions (for most-constrained ordering).
        $facultyTotal = [];

        // Grades whose bell schedule reserves elective windows (and can therefore
        // schedule cross-section elective groups).
        $electiveGrades = $this->gradesWithElectiveWindows();

        $requirements = [];
        foreach ($assignments as $la) {
            $section = $sections->get($la->section_id);
            if (! $section) {
                continue;
            }
            $grade = (int) $section->levelid;
            if ($grade < 7 || $grade > 12) {
                continue;
            }

            $isElectiveSection = str_starts_with((string) $section->sectionname, 'ELEC-');
            // Elective (cross-section) groups are only schedulable for grades that
            // have elective windows; for other grades they remain out of scope.
            if ($isElectiveSection && ! in_array($grade, $electiveGrades, true)) {
                continue;
            }

            $sessions = max(1, (int) round((float) ($la->subject->load_units ?? 1)));

            // TBA / vacant placeholder faculty must never create false conflicts —
            // give each such session its own unique sentinel "faculty".
            $facultyName = $la->faculty?->name ?? 'TBA';
            $isPlaceholder = str_starts_with($facultyName, 'TBA');
            $facultyId = $isPlaceholder ? -((int) $la->id) : (int) $la->user_id;

            if (! $isPlaceholder) {
                $facultyTotal[$facultyId] = ($facultyTotal[$facultyId] ?? 0) + $sessions;
            }

            $requirements[] = [
                'load_assignment_id' => (int) $la->id,
                'section_id'         => (int) $section->id,
                'section_name'       => $section->sectionname,
                'grade'              => $grade,
                'classroom_id'       => $section->classroom_id ? (int) $section->classroom_id : null,
                'classroom_name'     => $section->classroom_id ? ($classroomNames[$section->classroom_id] ?? null) : null,
                'subject_id'         => (int) $la->subject_id,
                'subject_code'       => $la->subject->code,
                'subject_name'       => $la->subject->name,
                'subject_type'       => $la->subject->subject_type,
                'faculty_id'         => $facultyId,
                // The real DB user id (e.g. the "TBA (Vacant)" placeholder account) —
                // $facultyId above is a negative sentinel for placeholders, used only
                // to keep parallel TBA sessions from being treated as the same
                // double-booked faculty during placement. It must never be persisted:
                // user_id is an unsigned FK and a negative value fails on insert.
                'real_user_id'       => (int) $la->user_id,
                'faculty_name'       => $facultyName,
                'sessions_needed'    => $sessions,
                'is_elective'        => $isElectiveSection || $la->subject->subject_type === 'elective',
                'has_ilp'            => (bool) ($la->subject->has_ilp ?? false),
            ];
        }

        // Attach faculty total to each requirement for ordering.
        foreach ($requirements as &$req) {
            $req['faculty_total'] = $facultyTotal[$req['faculty_id']] ?? 0;
        }
        unset($req);

        return $requirements;
    }

    /**
     * Grades whose bell schedule contains elective-labeled periods. Only these
     * grades can schedule cross-section elective groups.
     *
     * @return array<int,int>
     */
    private function gradesWithElectiveWindows(): array
    {
        $grades = [];
        foreach (range(7, 12) as $grade) {
            foreach ($this->buildSlotGrid($grade) as $slot) {
                if ($slot['is_elective']) {
                    $grades[] = $grade;
                    break;
                }
            }
        }
        return $grades;
    }

    /**
     * Assign each (grade, subject)'s weekly session indices to a shared
     * weekday, so every section of that grade offering the subject lands on
     * the same day for a given session index. Runs before per-section period
     * placement, which still independently picks the period/room/faculty
     * slot within the assigned day.
     *
     * Days are chosen greedily, least-used-day-first per grade, so a grade's
     * subjects spread across the week rather than piling onto one day. If a
     * subject needs more sessions than there are available weekdays, later
     * sessions reuse the least-loaded day rather than being left unassigned
     * (every session index still needs a consistent day across sections).
     *
     * @param array<int,array<string,mixed>> $requirements
     * @return array<int,array<int,array<int,string>>> [grade][subject_id][sessionIndex] => day
     */
    private function assignSubjectDays(array $requirements): array
    {
        $groups = [];
        foreach ($requirements as $req) {
            if ($req['is_elective']) {
                continue;
            }
            $grade     = $req['grade'];
            $subjectId = $req['subject_id'];
            $groups[$grade][$subjectId] = max($groups[$grade][$subjectId] ?? 0, $req['sessions_needed']);
        }

        $dayLoad    = [];
        $assignment = [];

        foreach ($groups as $grade => $subjects) {
            $availableDays = array_values(array_unique(array_column($this->gridByGrade[$grade] ?? [], 'day')));
            if (empty($availableDays)) {
                continue;
            }

            // Most-constrained-first: subjects needing the most sessions pick
            // days while the week still has the most room.
            arsort($subjects);

            foreach ($subjects as $subjectId => $sessionsNeeded) {
                $chosenDays = [];
                $usedDays   = [];
                for ($i = 0; $i < $sessionsNeeded; $i++) {
                    $candidates = array_values(array_diff($availableDays, $usedDays)) ?: $availableDays;
                    usort($candidates, fn ($a, $b) => ($dayLoad[$grade][$a] ?? 0) <=> ($dayLoad[$grade][$b] ?? 0));
                    $day = $candidates[0];

                    $chosenDays[] = $day;
                    $usedDays[]   = $day;
                    $dayLoad[$grade][$day] = ($dayLoad[$grade][$day] ?? 0) + 1;
                }
                $assignment[$grade][$subjectId] = $chosenDays;
            }
        }

        return $assignment;
    }

    // ── Shared state helpers ────────────────────────────────────────────────

    /**
     * (Re)build the per-grade slot grids and first-period lookups.
     *
     * @param array<int,int|string> $grades
     */
    private function buildGrids(array $grades): void
    {
        $this->gridByGrade        = [];
        $this->firstPeriodByGrade = [];
        foreach ($grades as $grade) {
            $grade                     = (int) $grade;
            $this->gridByGrade[$grade] = $this->buildSlotGrid($grade);
            foreach ($this->gridByGrade[$grade] as $slot) {
                $day = $slot['day'];
                if (! isset($this->firstPeriodByGrade[$grade][$day])
                    || $slot['start_min'] < $this->firstPeriodByGrade[$grade][$day]) {
                    $this->firstPeriodByGrade[$grade][$day] = $slot['start_min'];
                }
            }
        }
    }

    /** Reset all mutable scheduling state (busy maps, counters, placements). */
    private function resetState(): void
    {
        $this->sectionBusy        = [];
        $this->facultyBusy        = [];
        $this->sectionDayCount    = [];
        $this->sectionSubjDays    = [];
        $this->sectionIlpDayCount = [];
        $this->facultyDayCount    = [];
        $this->placements         = [];
    }

    /**
     * Seed the busy maps from every occupying (active + tentative) row already
     * in class_schedules for the term — classes AND non-teaching blocks both
     * occupy time. Seeded intervals carry the sentinel placement index -1 so
     * the relocation search can recognise them as immovable. Faculty intervals
     * for TBA/placeholder users are skipped (mirrors the negative-sentinel
     * convention: parallel TBA sessions never conflict with each other).
     *
     * $replaceableTentativeSectionIds (grade-scoped runs): tentative CLASS rows
     * belonging to these sections are skipped — they are about to be replaced
     * by the scoped apply, so they must not block the regeneration.
     */
    private function seedBusyFromDb(int $termId, array $statuses = ['active', 'tentative'], array $replaceableTentativeSectionIds = []): void
    {
        $rows = ClassSchedule::whereIn('status', $statuses)
            ->where('academic_term_id', $termId)
            ->get(['id', 'user_id', 'section_id', 'subject_id', 'day_of_week',
                'start_time', 'end_time', 'entry_type', 'session_type', 'status']);

        if ($rows->isEmpty()) {
            return;
        }

        $names = User::whereIn('id', $rows->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');

        foreach ($rows as $row) {
            if ($replaceableTentativeSectionIds !== []
                && $row->status === 'tentative'
                && ($row->entry_type ?? 'class') === 'class'
                && in_array((int) $row->section_id, $replaceableTentativeSectionIds, true)) {
                continue;
            }

            $day   = $row->day_of_week;
            $start = SchedulingConstants::toMinutes(substr((string) $row->start_time, 0, 5));
            $end   = SchedulingConstants::toMinutes(substr((string) $row->end_time, 0, 5));
            if ($end <= $start) {
                continue;
            }

            $isPlaceholderFaculty = str_starts_with((string) ($names[$row->user_id] ?? ''), 'TBA');
            if ($row->section_id) {
                $this->sectionBusy[(int) $row->section_id][$day][] = [$start, $end, -1];
            }
            if ($row->user_id && ! $isPlaceholderFaculty) {
                $this->facultyBusy[(int) $row->user_id][$day][] = [$start, $end, -1];
            }

            // Keep the soft-scoring counters coherent with what is already on
            // the calendar so new placements spread around existing classes.
            if (($row->entry_type ?? 'class') === 'class' && $row->section_id) {
                $sectionId = (int) $row->section_id;
                $this->sectionDayCount[$sectionId][$day] = ($this->sectionDayCount[$sectionId][$day] ?? 0) + 1;
                if ($row->subject_id) {
                    $this->sectionSubjDays[$sectionId][(int) $row->subject_id][$day] =
                        ($this->sectionSubjDays[$sectionId][(int) $row->subject_id][$day] ?? 0) + 1;
                }
                if (($row->session_type ?? 'regular') === 'ilp') {
                    $this->sectionIlpDayCount[$sectionId][$day] = ($this->sectionIlpDayCount[$sectionId][$day] ?? 0) + 1;
                }
                if ($row->user_id && ! $isPlaceholderFaculty) {
                    $this->facultyDayCount[(int) $row->user_id][$day] =
                        ($this->facultyDayCount[(int) $row->user_id][$day] ?? 0) + 1;
                }
            }
        }
    }

    // ── Slot grid ─────────────────────────────────────────────────────────

    /**
     * Build the available regular and ILP-only slots for a grade across the week,
     * applying the Wednesday activity cutoff, the Wednesday wellness block,
     * the fixed Friday Flag Retreat block (16:00 onward, all grades), and
     * Friday ILA (no in-person) for any grade still listed.
     *
     * @return array<int,array{day:string,start:string,end:string,start_min:int,end_min:int}>
     */
    private function buildSlotGrid(int $grade): array
    {
        $slots = [];
        foreach (self::DAYS as $day) {
            foreach (SchedulingConstants::getSchedulableTeachingSlots($grade, $day) as $row) {
                $slots[] = [
                    'day'         => $day,
                    'start'       => $row['start'],
                    'end'         => $row['end'],
                    'start_min'   => SchedulingConstants::toMinutes($row['start']),
                    'end_min'     => SchedulingConstants::toMinutes($row['end']),
                    // Periods the bell schedule reserves for electives — core
                    // (homeroom) subjects avoid these; electives only use these.
                    'is_elective' => str_contains($row['label'] ?? '', 'Elective'),
                    'is_ilp_only'  => ($row['type'] ?? 'CLASS') === 'ILP_ONLY',
                    'is_overflow'  => str_contains($row['label'] ?? '', 'Overflow'),
                ];
            }
        }

        return $slots;
    }

    // ── Placement ─────────────────────────────────────────────────────────

    /**
     * Find the best free slot for a session, or null if none is available.
     * "Best" minimises a soft penalty that spreads a section's classes across
     * days and avoids placing the same subject twice on one day.
     *
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $reserved time windows reserved by an
     *        in-progress relocation chain that this placement must not overlap
     */
    private function findBestSlot(array $s, array $reserved = []): ?array
    {
        $best      = null;
        $bestScore = PHP_INT_MAX;
        $isIlp     = ($s['session_type'] ?? 'regular') === 'ilp';
        $forcedDay = $s['forced_day'] ?? null;

        foreach ($this->gridByGrade[$s['grade']] ?? [] as $slot) {
            $day = $slot['day'];
            $occupiedSlot = $this->occupiedSlot($s, $slot);

            // Same-weekday-across-sections: this subject's session index is
            // fixed to one shared weekday for every section of this grade.
            if ($forcedDay !== null && $day !== $forcedDay) {
                continue;
            }

            // Electives only use elective windows; core subjects only use the rest.
            if (($slot['is_elective'] ?? false) !== ($s['is_elective'] ?? false)) {
                continue;
            }
            if (($slot['is_ilp_only'] ?? false) && ! $isIlp) {
                continue;
            }
            if ($this->overlapsReserved($occupiedSlot, $reserved)) {
                continue;
            }
            // ILP sessions must never land on the day's first period — students
            // should start the day with a real class, not independent study.
            if ($isIlp && $slot['start_min'] === ($this->firstPeriodByGrade[$s['grade']][$day] ?? null)) {
                continue;
            }

            // HARD: section free, faculty free at this time.
            if (! $this->officialTimeAllows($s, $occupiedSlot)) {
                continue;
            }
            if ($this->sectionBusyAt($s['section_id'], $occupiedSlot)) {
                continue;
            }
            if ($this->facultyBusyAt($s['faculty_id'], $occupiedSlot)) {
                continue;
            }

            // SOFT scoring (lower is better).
            $score = $this->computeSlotScore($s, $slot, $occupiedSlot, $isIlp);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best      = $slot;
            }
        }

        $this->lastSlotScore = $best !== null ? $bestScore : 0;

        return $best;
    }

    /**
     * Soft penalty for placing session $s at $slot (lower is better). Shared by
     * {@see findBestSlot()}, {@see rankSlots()} and the relocation search in
     * {@see attemptPlace()} so every placement's score is computed the same way.
     *
     * @param array<string,mixed> $s
     * @param array<string,mixed> $slot the grid slot being scored
     * @param array<string,mixed> $occupiedSlot the actual occupied interval (ILP-truncated)
     */
    private function computeSlotScore(array $s, array $slot, array $occupiedSlot, bool $isIlp): int
    {
        $day   = $slot['day'];
        $score = 0;
        $score += ($slot['is_overflow'] ?? false) ? self::OVERFLOW_PENALTY : 0;
        // Strongly avoid the same subject twice on the same day for a section.
        $score += ($this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day] ?? 0) * 10000;
        if ($isIlp) {
            // Spread a section's ILP sessions evenly across the week — this
            // outweighs the generic day-spread preference below.
            $score += ($this->sectionIlpDayCount[$s['section_id']][$day] ?? 0) * 1000;
        }
        // Spread a faculty's OWN classes evenly across the week too, not
        // just the section's.
        $score += ($this->facultyDayCount[$s['faculty_id']][$day] ?? 0) * 300;
        // Spread a section's classes evenly across the week.
        $score += ($this->sectionDayCount[$s['section_id']][$day] ?? 0) * 100;
        // Nudge cognitively-heavy subjects (Math/Science/CS) away from the
        // afternoon fatigue window.
        if (! $isIlp && $slot['start_min'] >= SchedulingConstants::PM_START_MIN
            && $this->isHeavySubject($s['subject_code'] ?? '')) {
            $score += 50;
        }
        // Fill idle gaps in the faculty's own day without exceeding the
        // fatigue-streak guideline.
        $score += $this->facultyDayGapPenalty($s['faculty_id'], $day, $occupiedSlot);
        // Slight preference for earlier days/times for determinism + compactness.
        $score += array_search($day, self::DAYS, true) * 2;
        $score += intdiv($slot['start_min'], 30);

        return $score;
    }

    /**
     * Collect every feasible free slot for a session, ranked best-first by the
     * same soft score {@see findBestSlot()} uses. The filter block is kept as a
     * deliberate copy — findBestSlot() is the generator's hot path and stays
     * single-result. ILP sessions report their truncated 30-minute end time.
     *
     * @param array<string,mixed> $s
     * @return array<int,array{day_of_week:string,start_time:string,end_time:string,score:int}>
     */
    private function rankSlots(array $s, int $limit): array
    {
        $isIlp = ($s['session_type'] ?? 'regular') === 'ilp';
        $found = [];

        foreach ($this->gridByGrade[$s['grade']] ?? [] as $slot) {
            $day = $slot['day'];
            $occupiedSlot = $this->occupiedSlot($s, $slot);

            if (($slot['is_elective'] ?? false) !== ($s['is_elective'] ?? false)) {
                continue;
            }
            if (($slot['is_ilp_only'] ?? false) && ! $isIlp) {
                continue;
            }
            if ($isIlp && $slot['start_min'] === ($this->firstPeriodByGrade[$s['grade']][$day] ?? null)) {
                continue;
            }
            if (! $this->officialTimeAllows($s, $occupiedSlot)) {
                continue;
            }
            if ($this->sectionBusyAt($s['section_id'], $occupiedSlot)) {
                continue;
            }
            if ($this->facultyBusyAt($s['faculty_id'], $occupiedSlot)) {
                continue;
            }

            $score = $this->computeSlotScore($s, $slot, $occupiedSlot, $isIlp);

            $endMin  = $isIlp ? min($slot['end_min'], $slot['start_min'] + self::ILP_MINUTES) : $slot['end_min'];
            $found[] = [
                'day_of_week' => $day,
                'start_time'  => $slot['start'],
                'end_time'    => SchedulingConstants::fromMinutes($endMin),
                'score'       => $score,
            ];
        }

        usort($found, fn ($a, $b) => $a['score'] <=> $b['score']);

        return array_slice($found, 0, max(1, $limit));
    }

    /**
     * Place a session, displacing blocking classes of the same faculty and
     * recursively re-placing them, up to a bounded depth. This is a bounded
     * augmenting-path search that recovers feasible-but-tight placements that
     * pure greedy misses. Leaves the scheduling state unchanged on failure.
     *
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $reserved time windows reserved by
     *        the relocation chain so far (this placement must avoid them)
     */
    private function attemptPlace(array $s, int $depth, array $reserved = []): bool
    {
        // Direct placement onto a slot that is free for both section and faculty.
        $slot = $this->findBestSlot($s, $reserved);
        if ($slot !== null) {
            $this->commit($s, $slot, null, $this->lastSlotScore);
            return true;
        }

        if ($depth <= 0) {
            return false;
        }

        $isIlp     = ($s['session_type'] ?? 'regular') === 'ilp';
        $forcedDay = $s['forced_day'] ?? null;

        foreach ($this->gridByGrade[$s['grade']] ?? [] as $slot) {
            $occupiedSlot = $this->occupiedSlot($s, $slot);
            if ($forcedDay !== null && $slot['day'] !== $forcedDay) {
                continue;
            }
            // Electives only use elective windows; core subjects only use the rest.
            if (($slot['is_elective'] ?? false) !== ($s['is_elective'] ?? false)) {
                continue;
            }
            if (($slot['is_ilp_only'] ?? false) && ! $isIlp) {
                continue;
            }
            if ($this->overlapsReserved($occupiedSlot, $reserved)) {
                continue;
            }
            if ($isIlp && $slot['start_min'] === ($this->firstPeriodByGrade[$s['grade']][$slot['day']] ?? null)) {
                continue;
            }
            // Section must be free here; only the faculty is (singly) blocked.
            if (! $this->officialTimeAllows($s, $occupiedSlot)) {
                continue;
            }
            if ($this->sectionBusyAt($s['section_id'], $occupiedSlot)) {
                continue;
            }
            $blockers = $this->facultyBlockersAt($s['faculty_id'], $occupiedSlot);
            if (count($blockers) !== 1) {
                continue;
            }

            $blockIdx = $blockers[0];
            // A DB-seeded busy interval (sentinel index -1) is immovable — only
            // sessions placed within this pass may be relocated.
            if ($blockIdx < 0) {
                continue;
            }
            $blocked  = $this->placements[$blockIdx];
            $score    = $this->computeSlotScore($s, $slot, $occupiedSlot, $isIlp);

            // Free the blocker and try to re-place it elsewhere — it (and any
            // class it displaces in turn) must avoid every slot this chain has
            // reserved, including the one we are reserving now for $s.
            $this->uncommit($blockIdx);
            if ($this->attemptPlace($blocked['s'], $depth - 1, array_merge($reserved, [$occupiedSlot]))) {
                $this->commit($s, $slot, null, $score);
                return true;
            }

            // Restore the blocker at its original slot/index and keep searching.
            $this->commit($blocked['s'], $blocked['slot'], $blockIdx, $blocked['score'] ?? 0);
        }

        return false;
    }

    /**
     * True if a slot overlaps any reserved time window (same day).
     *
     * @param array<string,mixed> $slot
     * @param array<int,array<string,mixed>> $reserved
     */
    private function overlapsReserved(array $slot, array $reserved): bool
    {
        foreach ($reserved as $r) {
            if ($slot['day'] === $r['day']
                && $slot['start_min'] < $r['end_min']
                && $slot['end_min'] > $r['start_min']) {
                return true;
            }
        }
        return false;
    }

    /** Return the actual occupied interval; ILP uses only the first 30 minutes. */
    private function occupiedSlot(array $session, array $slot): array
    {
        if (($session['session_type'] ?? 'regular') !== 'ilp') {
            return $slot;
        }

        $slot['end_min'] = min($slot['end_min'], $slot['start_min'] + self::ILP_MINUTES);
        $slot['end']     = SchedulingConstants::fromMinutes($slot['end_min']);

        return $slot;
    }

    /** Enforce the teacher's per-day official time, with the standard early shift fallback. */
    private function officialTimeAllows(array $session, array $slot): bool
    {
        $facultyId = (int) $session['faculty_id'];
        if ($facultyId < 0) {
            return true;
        }

        $day = $slot['day'];
        if (! isset($this->officialTimeByFacultyDay[$facultyId][$day])) {
            $official = TeacherOfficialTime::where('user_id', $facultyId)
                ->where('day_of_week', $day)
                ->first(['start_time', 'end_time']);

            $this->officialTimeByFacultyDay[$facultyId][$day] = [
                'start' => SchedulingConstants::toMinutes($official
                    ? substr((string) $official->start_time, 0, 5)
                    : SchedulingConstants::SHIFT_EARLY['start']),
                'end' => SchedulingConstants::toMinutes($official
                    ? substr((string) $official->end_time, 0, 5)
                    : SchedulingConstants::SHIFT_EARLY['end']),
            ];
        }

        $window = $this->officialTimeByFacultyDay[$facultyId][$day];

        return $slot['start_min'] >= $window['start'] && $slot['end_min'] <= $window['end'];
    }

    /**
     * Commit a placement into the scheduling state. Returns the placement index.
     *
     * @param array<string,mixed> $s
     * @param array<string,mixed> $slot
     */
    private function commit(array $s, array $slot, ?int $reuseIdx = null, ?int $score = null): int
    {
        $idx = $reuseIdx ?? count($this->placements);
        $this->placements[$idx] = ['s' => $s, 'slot' => $slot, 'score' => $score ?? 0];

        $day   = $slot['day'];
        $isIlp = ($s['session_type'] ?? 'regular') === 'ilp';
        // An ILP session only occupies the first 30 minutes of its period —
        // the remaining time is freed up for other bookings.
        $busyEndMin = $isIlp ? min($slot['end_min'], $slot['start_min'] + self::ILP_MINUTES) : $slot['end_min'];

        $this->sectionBusy[$s['section_id']][$day][] = [$slot['start_min'], $busyEndMin, $idx];
        $this->facultyBusy[$s['faculty_id']][$day][] = [$slot['start_min'], $busyEndMin, $idx];
        $this->sectionDayCount[$s['section_id']][$day] = ($this->sectionDayCount[$s['section_id']][$day] ?? 0) + 1;
        $this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day] =
            ($this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day] ?? 0) + 1;
        if ($isIlp) {
            $this->sectionIlpDayCount[$s['section_id']][$day] = ($this->sectionIlpDayCount[$s['section_id']][$day] ?? 0) + 1;
        }
        if ($s['faculty_id'] >= 0) {
            $this->facultyDayCount[$s['faculty_id']][$day] = ($this->facultyDayCount[$s['faculty_id']][$day] ?? 0) + 1;
        }

        return $idx;
    }

    /** Remove a committed placement from the scheduling state. */
    private function uncommit(int $idx): void
    {
        $p    = $this->placements[$idx];
        $s    = $p['s'];
        $day  = $p['slot']['day'];

        $this->sectionBusy[$s['section_id']][$day] = $this->dropByIdx($this->sectionBusy[$s['section_id']][$day], $idx);
        $this->facultyBusy[$s['faculty_id']][$day] = $this->dropByIdx($this->facultyBusy[$s['faculty_id']][$day], $idx);
        $this->sectionDayCount[$s['section_id']][$day]--;
        $this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day]--;
        if (($s['session_type'] ?? 'regular') === 'ilp') {
            $this->sectionIlpDayCount[$s['section_id']][$day]--;
        }
        if ($s['faculty_id'] >= 0) {
            $this->facultyDayCount[$s['faculty_id']][$day]--;
        }

        $this->placements[$idx] = null;
    }

    /** @param array<int,array{0:int,1:int,2:int}> $intervals */
    private function dropByIdx(array $intervals, int $idx): array
    {
        return array_values(array_filter($intervals, fn ($iv) => $iv[2] !== $idx));
    }

    /** @param array<string,mixed> $slot */
    private function sectionBusyAt(int $sectionId, array $slot): bool
    {
        foreach ($this->sectionBusy[$sectionId][$slot['day']] ?? [] as [$start, $end]) {
            if ($slot['start_min'] < $end && $slot['end_min'] > $start) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $slot */
    private function facultyBusyAt(int $facultyId, array $slot): bool
    {
        foreach ($this->facultyBusy[$facultyId][$slot['day']] ?? [] as [$start, $end]) {
            if ($slot['start_min'] < $end && $slot['end_min'] > $start) {
                return true;
            }
        }
        return false;
    }

    /**
     * Soft penalty from the faculty's OWN day, evaluated against a candidate
     * slot: rewards filling idle gaps between a teacher's other classes that
     * day, but flips to a mild penalty once the resulting contiguous run
     * would exceed the fatigue guideline (SchedulingConstants::MAX_STREAK_MIN)
     * — so the generator doesn't over-pack a teacher into one long block just
     * to look "compact". TBA/placeholder sentinels (negative faculty id) have
     * no real person's day to optimize, so they score 0.
     *
     * @param array<string,mixed> $slot the occupied interval being scored (start_min/end_min)
     */
    private function facultyDayGapPenalty(int $facultyId, string $day, array $slot): int
    {
        if ($facultyId < 0) {
            return 0;
        }

        $intervals = [[$slot['start_min'], $slot['end_min']]];
        foreach ($this->facultyBusy[$facultyId][$day] ?? [] as [$start, $end]) {
            $intervals[] = [$start, $end];
        }
        if (count($intervals) < 2) {
            return 0;
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $runStart   = $intervals[0][0];
        $runEnd     = $intervals[0][1];
        $longestRun = 0;
        $idleMin    = 0;
        foreach (array_slice($intervals, 1) as [$start, $end]) {
            $gap = $start - $runEnd;
            if ($gap <= SchedulingConstants::STREAK_JOIN_GAP_MIN) {
                $runEnd = max($runEnd, $end);
                continue;
            }
            $longestRun = max($longestRun, $runEnd - $runStart);
            $idleMin   += $gap;
            $runStart   = $start;
            $runEnd     = $end;
        }
        $longestRun = max($longestRun, $runEnd - $runStart);

        return $longestRun > SchedulingConstants::MAX_STREAK_MIN
            ? 20
            : intdiv($idleMin, 15);
    }

    /** True if a subject code matches one of the cognitively-heavy prefixes. */
    private function isHeavySubject(string $subjectCode): bool
    {
        $code = strtoupper($subjectCode);
        foreach (SchedulingConstants::HEAVY_SUBJECT_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Placement indices of a faculty's classes overlapping the given slot.
     *
     * @param array<string,mixed> $slot
     * @return array<int,int>
     */
    private function facultyBlockersAt(int $facultyId, array $slot): array
    {
        $hits = [];
        foreach ($this->facultyBusy[$facultyId][$slot['day']] ?? [] as [$start, $end, $idx]) {
            if ($slot['start_min'] < $end && $slot['end_min'] > $start) {
                $hits[] = $idx;
            }
        }
        return $hits;
    }

    // ── Output shaping ──────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $s
     * @param array<string,mixed> $slot
     * @return array<string,mixed>
     */
    private function toScheduleRow(array $s, array $slot, int $schoolYearId, int $termId): array
    {
        $sessionType = $s['session_type'] ?? 'regular';
        $isIlp       = $sessionType === 'ilp';
        $endMin      = $isIlp ? min($slot['end_min'], $slot['start_min'] + self::ILP_MINUTES) : $slot['end_min'];

        return [
            'load_assignment_id' => $s['load_assignment_id'],
            'user_id'            => $s['real_user_id'],
            'subject_id'         => $s['subject_id'],
            'section_id'         => $s['section_id'],
            'classroom_id'       => $s['classroom_id'],
            'school_year_id'     => $schoolYearId,
            'academic_term_id'   => $termId,
            'session_type'       => $sessionType,
            'day_of_week'        => $slot['day'],
            'start_time'         => $slot['start'] . ':00',
            'end_time'           => SchedulingConstants::fromMinutes($endMin) . ':00',
            'status'             => 'tentative',
            'remarks'            => 'AI-generated schedule',
            // Display-only metadata (underscore-prefixed; ignored by the
            // controller's explicit column mapping on apply()).
            '_section_name'      => $s['section_name'],
            '_subject_code'      => $s['subject_code'],
            '_subject_name'      => $s['subject_name'],
            '_faculty_name'      => $s['faculty_name'],
            '_classroom_name'    => $s['classroom_name'],
        ];
    }

    /**
     * Work out WHY a session could not be placed, against the current (post-pass)
     * scheduling state. Ordered from structural to situational.
     *
     * @param array<string,mixed> $s
     * @return array{reason_code:string,reason:string,can_reassign:bool}
     */
    private function diagnoseFailure(array $s): array
    {
        $grade = $s['grade'];
        $grid  = $this->gridByGrade[$grade] ?? [];
        $isIlp = ($s['session_type'] ?? 'regular') === 'ilp';

        if (($s['is_elective'] ?? false)
            && empty(array_filter($grid, fn ($slot) => $slot['is_elective'] ?? false))) {
            return [
                'reason_code'  => 'no_elective_window',
                'reason'       => "Grade {$grade}'s bell schedule has no elective window — this elective cannot be scheduled for this section.",
                'can_reassign' => false,
            ];
        }

        $sectionFree    = 0;
        $facultyBlocked = 0;
        foreach ($grid as $slot) {
            if (($slot['is_elective'] ?? false) !== ($s['is_elective'] ?? false)) {
                continue;
            }
            if (($slot['is_ilp_only'] ?? false) && ! $isIlp) {
                continue;
            }
            if ($isIlp && $slot['start_min'] === ($this->firstPeriodByGrade[$grade][$slot['day']] ?? null)) {
                continue;
            }
            $occupiedSlot = $this->occupiedSlot($s, $slot);
            if (! $this->officialTimeAllows($s, $occupiedSlot)) {
                continue;
            }
            if ($this->sectionBusyAt($s['section_id'], $occupiedSlot)) {
                continue;
            }
            $sectionFree++;
            if ($this->facultyBusyAt($s['faculty_id'], $occupiedSlot)) {
                $facultyBlocked++;
            }
        }

        if ($sectionFree === 0) {
            $capacity = count($grid);

            return [
                'reason_code'  => 'section_full',
                'reason'       => "Section {$s['section_name']} has no free class period; all {$capacity} legal weekly periods are occupied.",
                'can_reassign' => false,
            ];
        }
        if ($facultyBlocked === $sectionFree) {
            return [
                'reason_code'  => 'faculty_busy_everywhere',
                'reason'       => "{$sectionFree} free period(s) remain for {$s['section_name']}, but {$s['faculty_name']} is already booked at every one — reassigning to another faculty would let it fit.",
                'can_reassign' => true,
            ];
        }

        return [
            'reason_code'  => 'no_feasible_slot',
            'reason'       => 'No feasible slot found even after relocation attempts.',
            'can_reassign' => true,
        ];
    }

    /**
     * Shape one failure entry for the placeRemaining() report.
     *
     * @param array<string,mixed> $req requirement or session for the load
     * @param array{reason_code:string,reason:string,can_reassign:bool} $diagnosis
     * @return array<string,mixed>
     */
    private function describeFailure(array $req, int $sessionsUnplaced, array $diagnosis): array
    {
        return [
            'load_assignment_id' => $req['load_assignment_id'],
            'subject_code'       => $req['subject_code'],
            'subject_name'       => $req['subject_name'],
            'section_id'         => $req['section_id'],
            'section_name'       => $req['section_name'],
            'grade'              => $req['grade'],
            'faculty_id'         => $req['real_user_id'],
            'faculty_name'       => $req['faculty_name'],
            'sessions_unplaced'  => $sessionsUnplaced,
            'reason_code'        => $diagnosis['reason_code'],
            'reason'             => $diagnosis['reason'],
            'can_reassign'       => $diagnosis['can_reassign'],
        ];
    }

    /** @param array<string,mixed> $s */
    private function describeSession(array $s): array
    {
        return [
            'section_id'   => $s['section_id'],
            'section_name' => $s['section_name'],
            'grade'        => $s['grade'],
            'subject_code' => $s['subject_code'],
            'subject_name' => $s['subject_name'],
            'faculty_name' => $s['faculty_name'],
        ];
    }

    /**
     * Per-section summary of needed vs placed sessions (for the UI report).
     *
     * @param array<int,array<string,mixed>> $requirements
     * @param array<int,array<string,mixed>> $placed
     * @param array<int,int> $committedBySection
     * @return array<int,array<string,mixed>>
     */
    private function buildSectionReport(array $requirements, array $placed, array $committedBySection = []): array
    {
        $needed = [];
        $names  = [];
        foreach ($requirements as $req) {
            $needed[$req['section_id']] = ($needed[$req['section_id']] ?? 0) + $req['sessions_needed'];
            $names[$req['section_id']]  = ['name' => $req['section_name'], 'grade' => $req['grade']];
        }

        $placedCount = $committedBySection;
        foreach ($placed as $row) {
            $placedCount[$row['section_id']] = ($placedCount[$row['section_id']] ?? 0) + 1;
        }

        $report = [];
        foreach ($needed as $sectionId => $need) {
            $report[] = [
                'section_id'   => $sectionId,
                'section_name' => $names[$sectionId]['name'],
                'grade'        => $names[$sectionId]['grade'],
                'needed'       => $need,
                'placed'       => $placedCount[$sectionId] ?? 0,
                'unplaced'     => max(0, $need - ($placedCount[$sectionId] ?? 0)),
            ];
        }

        usort($report, fn ($a, $b) => [$a['grade'], $a['section_name']] <=> [$b['grade'], $b['section_name']]);

        return $report;
    }
}
