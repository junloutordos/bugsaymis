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

        // --- Existing DB assignments for the term ---
        $existingByFaculty = LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
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

        // Set of "subject_id:section_id" pairs already assigned in the DB
        $takenPairs = [];
        LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('section_id')
            ->whereNotNull('subject_id')
            ->select('subject_id', 'section_id')
            ->get()
            ->each(fn ($a) => $takenPairs["{$a->subject_id}:{$a->section_id}"] = true);

        // Count of distinct subjects already assigned per section in the DB
        $sectionLoad = [];
        LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('section_id')
            ->selectRaw('section_id, COUNT(DISTINCT subject_id) as cnt')
            ->groupBy('section_id')
            ->get()
            ->each(fn ($r) => $sectionLoad[(int) $r->section_id] = (int) $r->cnt);

        // --- Build proposals ---
        $proposals = [];

        foreach ($facultyList as $faculty) {
            $cap          = $this->teachingCap($faculty->position);
            $alreadyUnits = (float) $existingByFaculty->get($faculty->id, collect())->sum('load_units');
            $remaining    = max(0, $cap - $alreadyUnits);

            if ($remaining <= 0) {
                $proposals[] = [
                    'faculty_id'       => $faculty->id,
                    'faculty_name'     => $faculty->name,
                    'position'         => $faculty->position,
                    'specialization'   => $faculty->specialization,
                    'teaching_cap'     => $cap,
                    'already_assigned' => $alreadyUnits,
                    'assignments'      => [],
                    'skipped_reason'   => 'Already at or over teaching cap',
                ];
                continue;
            }

            $matched = $this->matchSubjects(
                $faculty,
                $subjects,
                $remaining,
                $existingByFaculty->get($faculty->id, collect()),
                $sectionsByGrade,
                $takenPairs,   // passed by reference — updated as assignments are made
                $sectionLoad,  // passed by reference — updated as assignments are made
            );

            $proposals[] = [
                'faculty_id'       => $faculty->id,
                'faculty_name'     => $faculty->name,
                'position'         => $faculty->position,
                'specialization'   => $faculty->specialization,
                'teaching_cap'     => $cap,
                'already_assigned' => $alreadyUnits,
                'assignments'      => $matched,
                'skipped_reason'   => empty($matched) ? 'No matching subjects found for specialization' : null,
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
            'proposals' => $proposals,
            'sections'  => $allSections,
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
            // Skip duplicates
            $exists = LoadAssignment::where('academic_term_id', $termId)
                ->where('user_id', $item['faculty_id'])
                ->where('subject_id', $item['subject_id'])
                ->exists();

            if ($exists) { $skipped++; continue; }

            $faculty = User::find($item['faculty_id']);
            if (! $faculty) continue;

            $load = $this->findOrCreateLoad($faculty->id, $term->school_year_id, $termId);

            if ($load->is_locked) { $skipped++; continue; }

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

            $this->loads->syncLoad($load);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Score and greedily fill a faculty's remaining load with matching subjects,
     * auto-assigning the best available section to each.
     *
     * $takenPairs and $sectionLoad are passed by reference so the state is shared
     * across all faculty in the same preview run (prevents two faculty from being
     * assigned the exact same subject+section combination).
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

        // Score all candidate subjects
        $scored = $subjects
            ->filter(fn ($s) => ! in_array($s->id, $alreadySubjectIds))
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

            // Skip if it would exceed the remaining cap
            if ($unitsSoFar + $units > $remaining + 0.01) continue;

            // Find the best section for this subject
            $section = $this->bestSection($subject, $sectionsByGrade, $takenPairs, $sectionLoad);

            $assigned[] = [
                'subject_id'    => $subject->id,
                'subject_code'  => $subject->code,
                'subject_name'  => $subject->name,
                'load_units'    => $units,
                'grade_level'   => $subject->grade_level,
                'match_score'   => $item['score'],
                'section_id'    => $section['id'],
                'section_label' => $section['label'],
                'section_auto'  => $section['auto'],
            ];

            // Update shared tracking so subsequent assignments don't conflict
            if ($section['id']) {
                $takenPairs["{$subject->id}:{$section['id']}"] = true;
                $sectionLoad[$section['id']] = ($sectionLoad[$section['id']] ?? 0) + 1;
            }

            $unitsSoFar          += $units;
            $alreadySubjectIds[]  = $subject->id;

            if ($unitsSoFar >= $remaining - 0.01) break;
        }

        return $assigned;
    }

    /**
     * Pick the best available section for a subject.
     *
     * Rules (in priority order):
     *  1. Section grade level must match the subject's grade level.
     *  2. The subject must not already be assigned to that section (avoids
     *     the same subject being taught twice in the same class by two teachers).
     *  3. Among valid candidates, prefer the section with the fewest existing
     *     subject assignments (load-balance across sections).
     */
    private function bestSection(
        Subject    $subject,
        Collection $sectionsByGrade,
        array      $takenPairs,
        array      $sectionLoad,
    ): array
    {
        $grade = $subject->grade_level;

        // Cross-grade / elective subjects (grade_level = 0): skip auto-assignment
        if ($grade === 0) {
            return ['id' => null, 'label' => null, 'auto' => false];
        }

        $candidates = $sectionsByGrade
            ->get($grade, collect())
            ->filter(fn ($s) => ! isset($takenPairs["{$subject->id}:{$s->id}"]));

        if ($candidates->isEmpty()) {
            return ['id' => null, 'label' => null, 'auto' => false];
        }

        // Pick the least-loaded section
        $best = $candidates
            ->sortBy(fn ($s) => $sectionLoad[$s->id] ?? 0)
            ->first();

        return [
            'id'    => $best->id,
            'label' => "Grade {$best->levelid} — {$best->sectionname}",
            'auto'  => true,
        ];
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
