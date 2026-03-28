<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

class AutoAssignmentService
{
    // Position keywords → max teaching units override (null = use default 18)
    private const POSITION_CAPS = [
        'principal'          => 6,
        'director'           => 3,
        'head teacher'       => 12,
        'master teacher iv'  => 15,
        'master teacher iii' => 15,
        'master teacher ii'  => 18,
        'master teacher i'   => 18,
        'teacher iii'        => 18,
        'teacher ii'         => 18,
        'teacher i'          => 18,
        'science research specialist iii' => 6,
        'science research specialist ii'  => 9,
        'science research specialist i'   => 12,
        'instructor'         => 18,
        'professor'          => 18,
        'associate professor' => 18,
        'assistant professor' => 18,
    ];

    // Default full load
    private const DEFAULT_CAP = 18;

    public function __construct(private readonly LoadComputationService $loads) {}

    /**
     * Preview proposed assignments for all faculty in a term.
     * Returns an array of proposals; does NOT persist anything.
     */
    public function preview(int $termId, ?int $facultyId = null): array
    {
        $term = AcademicTerm::with('schoolYear')->findOrFail($termId);

        // Faculty to process
        $facultyQuery = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->where('status', '!=', 'inactive');

        if ($facultyId) {
            $facultyQuery->where('id', $facultyId);
        }

        $facultyList = $facultyQuery->orderBy('name')->get();

        // Existing assignments for this term (to avoid duplicates)
        $existingByFaculty = LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->get()
            ->groupBy('user_id');

        // All active subjects with specialization tags
        $subjects = Subject::active()->orderBy('grade_level')->orderBy('code')->get();

        $proposals = [];

        foreach ($facultyList as $faculty) {
            $cap          = $this->teachingCap($faculty->position);
            $alreadyUnits = $existingByFaculty->get($faculty->id, collect())
                ->sum('load_units');
            $remaining    = max(0, $cap - $alreadyUnits);

            if ($remaining <= 0) {
                $proposals[] = [
                    'faculty_id'       => $faculty->id,
                    'faculty_name'     => $faculty->name,
                    'position'         => $faculty->position,
                    'specialization'   => $faculty->specialization,
                    'teaching_cap'     => $cap,
                    'already_assigned' => (float) $alreadyUnits,
                    'assignments'      => [],
                    'skipped_reason'   => 'Already at or over teaching cap',
                ];
                continue;
            }

            $matched = $this->matchSubjects($faculty, $subjects, $remaining, $existingByFaculty->get($faculty->id, collect()));

            $proposals[] = [
                'faculty_id'       => $faculty->id,
                'faculty_name'     => $faculty->name,
                'position'         => $faculty->position,
                'specialization'   => $faculty->specialization,
                'teaching_cap'     => $cap,
                'already_assigned' => (float) $alreadyUnits,
                'assignments'      => $matched,
                'skipped_reason'   => empty($matched) ? 'No matching subjects found for specialization' : null,
            ];
        }

        return [
            'term'      => ['id' => $term->id, 'label' => $term->full_label, 'school_year_id' => $term->school_year_id],
            'proposals' => $proposals,
        ];
    }

    /**
     * Apply a subset of the proposed assignments (caller passes selected ones).
     */
    public function apply(int $termId, array $selected, int $createdBy): array
    {
        $term = AcademicTerm::with('schoolYear')->findOrFail($termId);
        $created = 0;
        $skipped = 0;

        foreach ($selected as $item) {
            // Guard: skip if already assigned
            $exists = LoadAssignment::where('academic_term_id', $termId)
                ->where('user_id', $item['faculty_id'])
                ->where('subject_id', $item['subject_id'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $faculty = User::find($item['faculty_id']);
            if (! $faculty) continue;

            $load = $this->findOrCreateLoad($faculty->id, $term->school_year_id, $termId);

            if ($load->is_locked) {
                $skipped++;
                continue;
            }

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

    // ── Private helpers ───────────────────────────────────────────────────────

    private function matchSubjects(User $faculty, Collection $subjects, float $remaining, Collection $existing): array
    {
        $alreadySubjectIds = $existing->pluck('subject_id')->filter()->all();
        $tags = $this->parseTags($faculty->specialization);
        $assigned = [];
        $unitsSoFar = 0;

        // Score and sort subjects by relevance
        $scored = $subjects
            ->filter(fn ($s) => ! in_array($s->id, $alreadySubjectIds))
            ->map(fn ($s) => ['subject' => $s, 'score' => $this->scoreMatch($tags, $s)])
            ->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->values();

        foreach ($scored as $item) {
            $subject = $item['subject'];
            $units   = (float) $subject->load_units;

            if ($unitsSoFar + $units > $remaining + 0.01) continue;

            $assigned[] = [
                'subject_id'   => $subject->id,
                'subject_code' => $subject->code,
                'subject_name' => $subject->name,
                'load_units'   => $units,
                'grade_level'  => $subject->grade_level,
                'match_score'  => $item['score'],
                'section_id'   => null,
            ];

            $unitsSoFar  += $units;
            $alreadySubjectIds[] = $subject->id;

            if ($unitsSoFar >= $remaining - 0.01) break;
        }

        return $assigned;
    }

    /** Score how well a subject matches the faculty's specialization tags (0 = no match). */
    private function scoreMatch(array $facultyTags, Subject $subject): int
    {
        if (empty($facultyTags)) return 0;

        $subjectTags = $this->parseTags($subject->specialization_tags);

        // Also tokenise the subject name and code for soft matching
        $subjectWords = array_unique(array_merge(
            $subjectTags,
            $this->parseTags($subject->name),
            $this->parseTags($subject->code),
        ));

        $score = 0;
        foreach ($facultyTags as $tag) {
            foreach ($subjectWords as $word) {
                if ($word === $tag) {
                    $score += 2; // exact match
                } elseif (str_contains($word, $tag) || str_contains($tag, $word)) {
                    $score += 1; // partial match
                }
            }
        }

        return $score;
    }

    /** Normalise a comma / space / semicolon separated string into lowercase tokens. */
    private function parseTags(?string $raw): array
    {
        if (blank($raw)) return [];
        $parts = preg_split('/[\s,;]+/', mb_strtolower(trim($raw)));
        return array_values(array_filter($parts, fn ($p) => strlen($p) >= 3));
    }

    /** Determine the max teaching units cap based on position title. */
    private function teachingCap(?string $position): float
    {
        if (blank($position)) return self::DEFAULT_CAP;

        $lower = mb_strtolower(trim($position));

        foreach (self::POSITION_CAPS as $keyword => $cap) {
            if (str_contains($lower, $keyword)) {
                return (float) $cap;
            }
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
