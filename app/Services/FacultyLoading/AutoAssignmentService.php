<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

class AutoAssignmentService
{
    // Position keywords → max teaching units cap
    private const POSITION_CAPS = [
        'principal'                       => 6,
        'director'                        => 3,
        'head teacher'                    => 12,
        'master teacher iv'               => 15,
        'master teacher iii'              => 15,
        'master teacher ii'               => 18,
        'master teacher i'                => 18,
        'teacher iii'                     => 18,
        'teacher ii'                      => 18,
        'teacher i'                       => 18,
        'science research specialist iii' => 6,
        'science research specialist ii'  => 9,
        'science research specialist i'   => 12,
        'instructor'                      => 18,
        'professor'                       => 18,
        'associate professor'             => 18,
        'assistant professor'             => 18,
    ];

    private const DEFAULT_CAP = 18;

    public function __construct(private readonly LoadComputationService $loads) {}

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Preview proposed assignments for all (or one) faculty in a term.
     * Auto-assigns sections by grade level. Does NOT persist anything.
     */
    public function preview(int $termId, ?int $facultyId = null): array
    {
        $term = AcademicTerm::with('schoolYear')->findOrFail($termId);

        // --- Faculty ---
        $facultyQuery = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->where('status', '!=', 'inactive')
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'));
        if ($facultyId) {
            $facultyQuery->where('id', $facultyId);
        }
        $facultyList = $facultyQuery->orderBy('name')->get();

        // --- All existing DB assignments for the term (all types) ---
        $existingByFaculty = LoadAssignment::where('academic_term_id', $termId)
            ->get()
            ->groupBy('user_id');

        // --- Subjects (active, ordered by grade then code) ---
        $subjects = Subject::active()
            ->orderBy('grade_level')
            ->orderBy('code')
            ->get();

        // --- Sections for this school year, grouped by grade level ---
        $sectionsByGrade = Section::active()
            ->forSchoolYear($term->school_year_id)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get()
            ->groupBy('levelid');

        // --- Shared tracking state ---

        // Set of "subject_id:section_id" pairs already assigned in the DB (section-based subjects)
        // Elective subjects already assigned are tracked separately as "elec:{subject_id}"
        $takenPairs = [];
        LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('section_id')
            ->whereNotNull('subject_id')
            ->select('subject_id', 'section_id')
            ->get()
            ->each(fn ($a) => $takenPairs["{$a->subject_id}:{$a->section_id}"] = true);

        // Track electives (no section) already assigned in the DB
        $electiveIds = $subjects->where('subject_type', 'elective')->pluck('id')->all();
        if (! empty($electiveIds)) {
            LoadAssignment::where('academic_term_id', $termId)
                ->where('assignment_type', 'teaching')
                ->whereNull('section_id')
                ->whereIn('subject_id', $electiveIds)
                ->pluck('subject_id')
                ->unique()
                ->each(fn ($id) => $takenPairs["elec:{$id}"] = true);
        }

        // Count of distinct subjects already assigned per section in the DB
        $sectionLoad = [];
        LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('section_id')
            ->selectRaw('section_id, COUNT(DISTINCT subject_id) as cnt')
            ->groupBy('section_id')
            ->get()
            ->each(fn ($r) => $sectionLoad[(int) $r->section_id] = (int) $r->cnt);

        // --- Coverage gaps: detect uncovered section×subject slots BEFORE proposals ---
        $coverageGaps = $this->buildCoverageGaps($subjects, $sectionsByGrade, $takenPairs);

        // --- Build proposals ---
        $proposals = [];

        foreach ($facultyList as $faculty) {
            $allExisting      = $existingByFaculty->get($faculty->id, collect());
            $teachingExisting = $allExisting->where('assignment_type', 'teaching');

            $teachingCap      = $this->teachingCap($faculty->position);
            $totalExisting    = (float) $allExisting->sum('load_units');
            $teachingExistingU = (float) $teachingExisting->sum('load_units');

            // Remaining teaching capacity is the tighter of:
            //   (a) position teaching cap minus existing teaching load
            //   (b) full-load threshold minus ALL existing loads (teaching + admin + research + etc.)
            $remainingByTeachingCap = max(0, $teachingCap - $teachingExistingU);
            $remainingByTotalCap    = max(0, LoadComputationService::FULL_LOAD_THRESHOLD - $totalExisting);
            $remaining              = min($remainingByTeachingCap, $remainingByTotalCap);

            // Breakdown of non-teaching loads for display
            $nonTeachingBreakdown = $allExisting
                ->where('assignment_type', '!=', 'teaching')
                ->groupBy('assignment_type')
                ->map(fn ($items) => (float) $items->sum('load_units'))
                ->toArray();

            if ($remaining <= 0) {
                $proposals[] = [
                    'faculty_id'            => $faculty->id,
                    'faculty_name'          => $faculty->name,
                    'position'              => $faculty->position,
                    'specialization'        => $faculty->specialization,
                    'teaching_cap'          => $teachingCap,
                    'total_cap'             => LoadComputationService::FULL_LOAD_THRESHOLD,
                    'already_teaching'      => $teachingExistingU,
                    'already_total'         => $totalExisting,
                    'non_teaching_loads'    => $nonTeachingBreakdown,
                    'assignments'           => [],
                    'skipped_reason'        => 'No remaining capacity (teaching or total load cap reached)',
                ];
                continue;
            }

            $matched = $this->matchSubjects(
                $faculty,
                $subjects,
                $remaining,
                $teachingExisting,     // pass only teaching assignments for subject dedup
                $sectionsByGrade,
                $takenPairs,
                $sectionLoad,
            );

            $proposals[] = [
                'faculty_id'         => $faculty->id,
                'faculty_name'       => $faculty->name,
                'position'           => $faculty->position,
                'specialization'     => $faculty->specialization,
                'teaching_cap'       => $teachingCap,
                'total_cap'          => LoadComputationService::FULL_LOAD_THRESHOLD,
                'already_teaching'   => $teachingExistingU,
                'already_total'      => $totalExisting,
                'non_teaching_loads' => $nonTeachingBreakdown,
                'assignments'        => $matched,
                'skipped_reason'     => empty($matched) ? 'No matching subjects found for specialization' : null,
            ];
        }

        // --- All sections (for manual override dropdowns in UI) ---
        $allSections = Section::active()
            ->forSchoolYear($term->school_year_id)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'levelid', 'sectionname', 'section_code'])
            ->map(fn ($s) => [
                'id'    => $s->id,
                'grade' => (int) $s->levelid,
                'label' => "Grade {$s->levelid} — {$s->sectionname}",
                'code'  => $s->section_code,
            ])
            ->values();

        return [
            'term'      => [
                'id'             => $term->id,
                'label'          => $term->full_label,
                'school_year_id' => $term->school_year_id,
            ],
            'proposals'      => $proposals,
            'sections'       => $allSections,
            'coverage_gaps'  => $coverageGaps,
        ];
    }

    /**
     * Persist a subset of previewed assignments.
     */
    public function apply(int $termId, array $selected, int $createdBy): array
    {
        $term    = AcademicTerm::with('schoolYear')->findOrFail($termId);
        $created = 0;
        $skipped = 0;

        foreach ($selected as $item) {
            // Skip duplicates — check by faculty + subject + section
            $sectionId = $item['section_id'] ?? null;
            $exists = LoadAssignment::where('academic_term_id', $termId)
                ->where('user_id', $item['faculty_id'])
                ->where('subject_id', $item['subject_id'])
                ->where(fn ($q) => $sectionId === null
                    ? $q->whereNull('section_id')
                    : $q->where('section_id', $sectionId)
                )
                ->exists();

            if ($exists) { $skipped++; continue; }

            // The DB enforces one teacher per (term, subject, section) via uq_la_teaching_slot —
            // check that slot isn't already held by a *different* faculty (stale preview / concurrent apply).
            if ($sectionId !== null) {
                $slotTaken = LoadAssignment::where('academic_term_id', $termId)
                    ->where('subject_id', $item['subject_id'])
                    ->where('section_id', $sectionId)
                    ->exists();
                if ($slotTaken) { $skipped++; continue; }
            }

            $faculty = User::find($item['faculty_id']);
            if (! $faculty) continue;

            $load = $this->findOrCreateLoad($faculty->id, $term->school_year_id, $termId);

            if ($load->is_locked) { $skipped++; continue; }

            try {
                LoadAssignment::create([
                    'faculty_load_id'  => $load->id,
                    'user_id'          => $item['faculty_id'],
                    'school_year_id'   => $term->school_year_id,
                    'academic_term_id' => $termId,
                    'assignment_type'  => 'teaching',
                    'subject_id'       => $item['subject_id'],
                    'section_id'       => $item['section_id'] ?? null,
                    'load_units'       => $item['load_units'],
                    'description'      => null,
                    'created_by'       => $createdBy,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Race: another apply() claimed this slot between our check and the insert.
                $skipped++;
                continue;
            }

            $this->loads->syncLoad($load);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Score and greedily fill a faculty's remaining load with matching subjects.
     *
     * When a subject is matched, the faculty is assigned to ALL uncovered sections
     * at that grade level — not just one. This reflects real-world practice where
     * one teacher handles a subject across all sections of a year level.
     *
     * $takenPairs and $sectionLoad are passed by reference so state is shared
     * across all faculty (prevents two faculty being assigned the same subject+section).
     */
    private function matchSubjects(
        User       $faculty,
        Collection $subjects,
        float      $remaining,
        Collection $existing,
        Collection $sectionsByGrade,
        array      &$takenPairs,
        array      &$sectionLoad,
    ): array
    {
        $alreadySubjectIds = $existing->pluck('subject_id')->filter()->all();
        $facultyTags       = $this->parseTags($faculty->specialization);
        $assigned          = [];
        $unitsSoFar        = 0.0;

        // Score candidate subjects — only those with at least one uncovered section slot
        $scored = $subjects
            ->filter(fn ($s) => ! in_array($s->id, $alreadySubjectIds))
            ->filter(fn ($s) => $this->hasUncoveredSection($s, $sectionsByGrade, $takenPairs))
            ->map(fn ($s) => [
                'subject' => $s,
                'score'   => $this->scoreMatch($facultyTags, $s),
            ])
            ->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->values();

        foreach ($scored as $item) {
            $subject = $item['subject'];
            $units   = (float) $subject->load_units;
            $grade   = (int) $subject->grade_level;

            if ($grade === 0 || $subject->subject_type === 'elective') {
                // Elective or cross-grade subject: single assignment with no section
                // (offered to self-selecting students across all sections)
                if ($unitsSoFar + $units > $remaining + 0.01) continue;

                $gradeLabel = $grade === 0 ? 'G11 & G12' : "Grade {$grade}";

                $assigned[] = [
                    'subject_id'    => $subject->id,
                    'subject_code'  => $subject->code,
                    'subject_name'  => $subject->name,
                    'load_units'    => $units,
                    'grade_level'   => $grade,
                    'match_score'   => $item['score'],
                    'section_id'    => null,
                    'section_label' => "Elective — {$gradeLabel} (across sections)",
                    'section_auto'  => false,
                ];
                $takenPairs["elec:{$subject->id}"] = true;
                $unitsSoFar         += $units;
                $alreadySubjectIds[] = $subject->id;
            } else {
                // Graded subject: assign to every uncovered section at this grade level
                $uncovered = $this->uncoveredSectionsForSubject($subject, $sectionsByGrade, $takenPairs);
                if (empty($uncovered)) continue;

                // Trim to however many sections fit within the remaining cap
                $maxFit   = (int) floor(($remaining - $unitsSoFar + 0.01) / $units);
                if ($maxFit === 0) continue;
                $toAssign = array_slice($uncovered, 0, $maxFit);

                foreach ($toAssign as $section) {
                    $assigned[] = [
                        'subject_id'    => $subject->id,
                        'subject_code'  => $subject->code,
                        'subject_name'  => $subject->name,
                        'load_units'    => $units,
                        'grade_level'   => $grade,
                        'match_score'   => $item['score'],
                        'section_id'    => $section->id,
                        'section_label' => "Grade {$section->levelid} — {$section->sectionname}",
                        'section_auto'  => true,
                    ];
                    $takenPairs["{$subject->id}:{$section->id}"] = true;
                    $sectionLoad[$section->id] = ($sectionLoad[$section->id] ?? 0) + 1;
                    $unitsSoFar += $units;
                }
                $alreadySubjectIds[] = $subject->id;
            }

            if ($unitsSoFar >= $remaining - 0.01) break;
        }

        return $assigned;
    }

    /**
     * Return all uncovered sections for a regular graded subject.
     * Elective subjects and cross-grade subjects (grade=0) never have sections.
     */
    private function uncoveredSectionsForSubject(Subject $subject, Collection $sectionsByGrade, array $takenPairs): array
    {
        $grade = (int) $subject->grade_level;

        if ($grade === 0 || $subject->subject_type === 'elective') {
            return []; // handled separately as a single no-section assignment
        }

        return $sectionsByGrade
            ->get($grade, collect())
            ->filter(fn ($s) => ! isset($takenPairs["{$subject->id}:{$s->id}"]))
            ->values()
            ->all();
    }

    /**
     * Score how well a subject matches the faculty's specialization tags.
     * Exact tag match = 2 pts, partial/substring match = 1 pt.
     * Also tokenises the subject name and code for soft matching.
     */
    private function scoreMatch(array $facultyTags, Subject $subject): int
    {
        if (empty($facultyTags)) return 0;

        $subjectWords = array_unique(array_merge(
            $this->parseTags($subject->specialization_tags),
            $this->parseTags($subject->name),
            $this->parseTags($subject->code),
        ));

        $score = 0;
        foreach ($facultyTags as $tag) {
            foreach ($subjectWords as $word) {
                if ($word === $tag) {
                    $score += 2;
                } elseif (str_contains($word, $tag) || str_contains($tag, $word)) {
                    $score += 1;
                }
            }
        }

        return $score;
    }

    /** Normalise a comma/space/semicolon-separated string into lowercase tokens (≥3 chars). */
    private function parseTags(?string $raw): array
    {
        if (blank($raw)) return [];
        $parts = preg_split('/[\s,;]+/', mb_strtolower(trim($raw)));
        return array_values(array_filter($parts, fn ($p) => strlen($p) >= 3));
    }

    /** Determine the max teaching-units cap from a position title. */
    private function teachingCap(?string $position): float
    {
        if (blank($position)) return self::DEFAULT_CAP;
        $lower = mb_strtolower(trim($position));
        foreach (self::POSITION_CAPS as $keyword => $cap) {
            if (str_contains($lower, $keyword)) return (float) $cap;
        }
        return self::DEFAULT_CAP;
    }

    /**
     * Returns true if the subject has at least one uncovered slot.
     *
     * - Elective subjects (any subject_type='elective') and cross-grade subjects
     *   (grade_level=0) have a single slot tracked as "elec:{id}" — uncovered
     *   when that key is absent from takenPairs.
     * - Regular graded subjects are uncovered when at least one section at their
     *   grade level has no assignment yet.
     */
    private function hasUncoveredSection(Subject $subject, Collection $sectionsByGrade, array $takenPairs): bool
    {
        $grade = $subject->grade_level;

        if ($grade === 0 || $subject->subject_type === 'elective') {
            return ! isset($takenPairs["elec:{$subject->id}"]);
        }

        $sections = $sectionsByGrade->get($grade, collect());

        if ($sections->isEmpty()) {
            return false;
        }

        foreach ($sections as $section) {
            if (! isset($takenPairs["{$subject->id}:{$section->id}"])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the full list of uncovered subject slots for the current term.
     *
     * - Regular graded subjects → one entry per section that has no assignment yet.
     * - Elective subjects (any grade) → one entry with section_id=null if not yet assigned.
     * - Cross-grade subjects (grade_level=0) → same as electives.
     */
    private function buildCoverageGaps(Collection $subjects, Collection $sectionsByGrade, array $takenPairs): array
    {
        $gaps = [];

        // --- Section-based gaps (regular graded, non-elective subjects) ---
        foreach ($sectionsByGrade as $grade => $sections) {
            $gradeSubjects = $subjects->filter(
                fn ($s) => $s->grade_level === (int) $grade && $s->subject_type !== 'elective'
            );

            foreach ($sections as $section) {
                foreach ($gradeSubjects as $subject) {
                    if (! isset($takenPairs["{$subject->id}:{$section->id}"])) {
                        $gaps[] = [
                            'grade'        => (int) $grade,
                            'section_id'   => $section->id,
                            'section_name' => $section->sectionname,
                            'subject_id'   => $subject->id,
                            'subject_code' => $subject->code,
                            'subject_name' => $subject->name,
                            'is_elective'  => false,
                        ];
                    }
                }
            }
        }

        // --- Elective gaps (no section — offered across all sections) ---
        $electives = $subjects->filter(fn ($s) => $s->subject_type === 'elective' || $s->grade_level === 0);
        foreach ($electives as $subject) {
            if (! isset($takenPairs["elec:{$subject->id}"])) {
                $gradeLabel = $subject->grade_level === 0 ? 'G11 & G12' : "Grade {$subject->grade_level}";
                $gaps[] = [
                    'grade'        => (int) $subject->grade_level,
                    'section_id'   => null,
                    'section_name' => "Across sections ({$gradeLabel})",
                    'subject_id'   => $subject->id,
                    'subject_code' => $subject->code,
                    'subject_name' => $subject->name,
                    'is_elective'  => true,
                ];
            }
        }

        return $gaps;
    }

    private function findOrCreateLoad(int $userId, int $schoolYearId, int $termId)
    {
        return \App\Models\FacultyLoading\FacultyLoad::firstOrCreate(
            ['user_id' => $userId, 'academic_term_id' => $termId],
            [
                'school_year_id'      => $schoolYearId,
                'teaching_units'      => 0,
                'research_units'      => 0,
                'admin_units'         => 0,
                'cocurricular_units'  => 0,
                'committee_units'     => 0,
                'total_units'         => 0,
                'full_load_threshold' => LoadComputationService::FULL_LOAD_THRESHOLD,
                'load_status'         => 'underload',
                'overload_approved'   => false,
            ]
        );
    }
}
