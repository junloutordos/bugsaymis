<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ScheduleVersion;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ScheduleVersionService
 *
 * Lets CID checkpoint the CURRENT tentative schedule for a term — whatever mix
 * of AI-generated and manually plotted/drag-and-dropped rows is live right
 * now — under a label, then later compare it against another saved version
 * (or against whatever is live at that moment) and restore it if a later
 * regenerate/edit turns out worse.
 *
 * Independent of, and coexists with, AiScheduleJob (single-run AI undo) and
 * ClassScheduleApprovalBatch (the formal CID→OCD submission snapshot) — both
 * already snapshot schedule state for their own narrower purposes; this is
 * the general on-demand "save my draft" mechanism neither provides.
 */
class ScheduleVersionService
{
    private const COLUMNS = [
        'id', 'load_assignment_id', 'user_id', 'subject_id', 'section_id', 'classroom_id',
        'school_year_id', 'academic_term_id', 'entry_type', 'session_type', 'title', 'category',
        'day_of_week', 'start_time', 'end_time', 'status', 'remarks',
    ];

    /** Snapshot every tentative class row for the term under a new labeled version. */
    public function save(int $termId, string $label, ?string $notes, ?int $userId): ScheduleVersion
    {
        $snapshot = $this->currentSnapshot($termId);

        return ScheduleVersion::create([
            'academic_term_id'  => $termId,
            'label'             => $label,
            'notes'             => $notes,
            'schedule_snapshot' => $snapshot,
            'session_count'     => count($snapshot),
            'created_by'        => $userId,
        ]);
    }

    /**
     * Replace every current tentative class row for the term with this
     * version's snapshot. Destructive for anything tentative that isn't
     * itself captured in some saved version — callers must warn accordingly.
     */
    public function restore(ScheduleVersion $version): void
    {
        if (ClassScheduleApprovalService::termIsLocked($version->academic_term_id)) {
            throw new \RuntimeException('This term schedule is locked for OCD approval.');
        }

        DB::transaction(function () use ($version) {
            ClassSchedule::where('academic_term_id', $version->academic_term_id)
                ->where('status', 'tentative')
                ->delete();

            $rows = array_map(function ($row) {
                unset($row['id']);
                return $row;
            }, $version->schedule_snapshot);

            if (! empty($rows)) {
                ClassSchedule::insert($rows);
            }

            $version->update(['restored_at' => now()]);
        });
    }

    /**
     * Compare version $a against version $b, or against the term's current
     * live tentative state when $b is null.
     *
     * @return array{summary:array,diff:array<int,array<string,mixed>>}
     */
    public function compare(ScheduleVersion $a, ?ScheduleVersion $b, int $termId): array
    {
        $rowsA = $a->schedule_snapshot;
        $rowsB = $b?->schedule_snapshot ?? $this->currentSnapshot($termId);

        return [
            'summary' => [
                'a' => $this->summarize($rowsA),
                'b' => $this->summarize($rowsB),
            ],
            'diff' => $this->diffByAssignment($rowsA, $rowsB),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    private function currentSnapshot(int $termId): array
    {
        return ClassSchedule::where('academic_term_id', $termId)
            ->where('status', 'tentative')
            ->classes()
            ->orderBy('id')
            ->get(self::COLUMNS)
            ->map(fn ($row) => $row->getAttributes())
            ->values()
            ->all();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function summarize(array $rows): array
    {
        $bySection = [];
        foreach ($rows as $row) {
            $sectionId = $row['section_id'] ?? null;
            if ($sectionId === null) {
                continue;
            }
            $bySection[$sectionId] = ($bySection[$sectionId] ?? 0) + 1;
        }

        return [
            'total_sessions' => count($rows),
            'sections_used'  => count($bySection),
        ];
    }

    /**
     * Group both sides by load_assignment_id (stable across regenerations,
     * unlike row ids) and diff each assignment's set of day/time slots.
     *
     * @param array<int,array<string,mixed>> $rowsA
     * @param array<int,array<string,mixed>> $rowsB
     * @return array<int,array<string,mixed>>
     */
    private function diffByAssignment(array $rowsA, array $rowsB): array
    {
        $slotsA = $this->slotsByAssignment($rowsA);
        $slotsB = $this->slotsByAssignment($rowsB);

        $assignmentIds = array_unique(array_merge(array_keys($slotsA), array_keys($slotsB)));
        $labels        = $this->assignmentLabels($assignmentIds, $rowsA, $rowsB);

        $diff = [];
        foreach ($assignmentIds as $loadAssignmentId) {
            $a = $slotsA[$loadAssignmentId] ?? [];
            $b = $slotsB[$loadAssignmentId] ?? [];

            $removed = array_values(array_diff($a, $b));
            $added   = array_values(array_diff($b, $a));

            if (empty($removed) && empty($added)) {
                continue; // unchanged — not worth surfacing
            }

            $diff[] = [
                'load_assignment_id' => $loadAssignmentId,
                'label'              => $labels[$loadAssignmentId] ?? "Load assignment #{$loadAssignmentId}",
                'removed'            => $removed,
                'added'              => $added,
            ];
        }

        usort($diff, fn ($x, $y) => $x['label'] <=> $y['label']);

        return $diff;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<int,string>> load_assignment_id => ["Monday|08:00:00-09:00:00", ...]
     */
    private function slotsByAssignment(array $rows): array
    {
        $slots = [];
        foreach ($rows as $row) {
            $loadAssignmentId = $row['load_assignment_id'] ?? null;
            if ($loadAssignmentId === null) {
                continue; // non-teaching blocks carry no load assignment — out of scope for this diff
            }
            $slots[$loadAssignmentId][] = "{$row['day_of_week']}|{$row['start_time']}-{$row['end_time']}";
        }

        return $slots;
    }

    /**
     * Resolve a human-readable "Subject — Section (Faculty)" label per
     * load_assignment_id, from whichever side actually has a row for it.
     *
     * @param array<int,int> $assignmentIds
     * @param array<int,array<string,mixed>> $rowsA
     * @param array<int,array<string,mixed>> $rowsB
     * @return array<int,string>
     */
    private function assignmentLabels(array $assignmentIds, array $rowsA, array $rowsB): array
    {
        $sample = [];
        foreach (array_merge($rowsA, $rowsB) as $row) {
            $id = $row['load_assignment_id'] ?? null;
            if ($id !== null && ! isset($sample[$id])) {
                $sample[$id] = $row;
            }
        }

        $subjectIds = array_unique(array_filter(array_column($sample, 'subject_id')));
        $sectionIds = array_unique(array_filter(array_column($sample, 'section_id')));
        $userIds    = array_unique(array_filter(array_column($sample, 'user_id')));

        $subjectNames = Subject::whereIn('id', $subjectIds)->pluck('name', 'id');
        $sectionNames = Section::whereIn('id', $sectionIds)->get(['id', 'sectionname', 'levelid'])
            ->mapWithKeys(fn ($s) => [$s->id => "G{$s->levelid} {$s->sectionname}"]);
        $facultyNames = User::whereIn('id', $userIds)->pluck('name', 'id');

        $labels = [];
        foreach ($assignmentIds as $id) {
            $row = $sample[$id] ?? null;
            if ($row === null) {
                continue;
            }
            $subject = $subjectNames[$row['subject_id'] ?? null] ?? 'Unknown subject';
            $section = $sectionNames[$row['section_id'] ?? null] ?? 'Unknown section';
            $faculty = $facultyNames[$row['user_id'] ?? null] ?? 'TBA';
            $labels[$id] = "{$subject} — {$section} ({$faculty})";
        }

        return $labels;
    }
}
