<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\LoadAssignmentVersion;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * LoadAssignmentVersionService
 *
 * Save/compare/restore labeled checkpoints of a term's load_assignments —
 * who's assigned to teach what, plus research/admin/cocurricular/committee
 * assignments. Independent of ScheduleVersionService, which covers WHEN a
 * class meets (class_schedules) — this covers WHO holds the assignment.
 *
 * Restore reconciles in place rather than delete+recreate: class_schedules
 * .load_assignment_id is a real FK, so replacing rows wholesale would
 * silently orphan any schedule linked to an assignment that changed since
 * the snapshot. Matching keys let restore tell "same real-world assignment"
 * apart from "genuinely different assignment" — see matchKey().
 */
class LoadAssignmentVersionService
{
    private const COLUMNS = [
        'id', 'faculty_load_id', 'user_id', 'school_year_id', 'academic_term_id',
        'assignment_type', 'subject_id', 'section_id', 'load_units',
        'is_overridden', 'description', 'designation_id', 'created_by',
    ];

    /** Per-call cache: user_id => is their FacultyLoad locked for this term. */
    private array $lockCache = [];

    public function __construct(private readonly LoadComputationService $loadService) {}

    /** Snapshot every load_assignments row for the term under a new labeled version. */
    public function save(int $termId, string $label, ?string $notes, ?int $userId): LoadAssignmentVersion
    {
        $snapshot = $this->currentSnapshot($termId);

        return LoadAssignmentVersion::create([
            'academic_term_id'    => $termId,
            'label'               => $label,
            'notes'               => $notes,
            'assignment_snapshot' => $snapshot,
            'assignment_count'    => count($snapshot),
            'created_by'          => $userId,
        ]);
    }

    /**
     * Reconcile the term's live load_assignments to match the version's
     * snapshot, in place where possible. Faculty whose FacultyLoad is
     * currently locked are skipped entirely (their assignments are left
     * untouched) rather than failing the whole restore.
     *
     * @return array{restored:int, skipped_locked:array<int,string>}
     */
    public function restore(LoadAssignmentVersion $version): array
    {
        $this->lockCache = [];
        $termId = $version->academic_term_id;

        $snapshotByKey = [];
        foreach ($version->assignment_snapshot as $row) {
            $snapshotByKey[$this->matchKey($row)] = $row;
        }

        $liveRows = LoadAssignment::where('academic_term_id', $termId)->get();
        $liveByKey = [];
        foreach ($liveRows as $row) {
            $liveByKey[$this->matchKey($row->getAttributes())] = $row;
        }

        $keys = array_unique(array_merge(array_keys($snapshotByKey), array_keys($liveByKey)));

        $restored = 0;
        $skippedUserIds = [];
        $touchedFacultyLoadIds = [];

        DB::transaction(function () use (
            $keys, $snapshotByKey, $liveByKey, $termId, $version,
            &$restored, &$skippedUserIds, &$touchedFacultyLoadIds
        ) {
            foreach ($keys as $key) {
                $snap = $snapshotByKey[$key] ?? null;
                $live = $liveByKey[$key] ?? null;

                if ($snap !== null && $live !== null) {
                    // Present on both sides — same real-world assignment.
                    if ((int) $live->user_id === (int) $snap['user_id']) {
                        if ($this->isLocked((int) $live->user_id, $termId)) {
                            $skippedUserIds[(int) $live->user_id] = true;
                            continue;
                        }
                        if ($this->fieldsDiffer($live, $snap)) {
                            $live->update($this->updatableFields($snap));
                            $touchedFacultyLoadIds[$live->faculty_load_id] = true;
                            $restored++;
                        }
                        continue;
                    }

                    // Slot changed hands since the snapshot — move it back,
                    // mirroring LoadBalancingService::applyTransfer().
                    if ($this->isLocked((int) $live->user_id, $termId) || $this->isLocked((int) $snap['user_id'], $termId)) {
                        $skippedUserIds[(int) $live->user_id] = true;
                        $skippedUserIds[(int) $snap['user_id']] = true;
                        continue;
                    }
                    $targetLoad = $this->loadService->findOrCreateFacultyLoad(
                        (int) $snap['user_id'], (int) $snap['school_year_id'], $termId
                    );
                    $touchedFacultyLoadIds[$live->faculty_load_id] = true;
                    $touchedFacultyLoadIds[$targetLoad->id] = true;

                    $live->update(array_merge($this->updatableFields($snap), [
                        'user_id'         => $snap['user_id'],
                        'faculty_load_id' => $targetLoad->id,
                    ]));
                    ClassSchedule::where('load_assignment_id', $live->id)
                        ->update(['user_id' => $snap['user_id']]);
                    $restored++;
                    continue;
                }

                if ($snap !== null) {
                    // Existed at snapshot time, doesn't now — recreate it.
                    if ($this->isLocked((int) $snap['user_id'], $termId)) {
                        $skippedUserIds[(int) $snap['user_id']] = true;
                        continue;
                    }
                    $load = $this->loadService->findOrCreateFacultyLoad(
                        (int) $snap['user_id'], (int) $snap['school_year_id'], $termId
                    );
                    $touchedFacultyLoadIds[$load->id] = true;

                    LoadAssignment::create(array_merge($this->updatableFields($snap), [
                        'faculty_load_id'  => $load->id,
                        'user_id'          => $snap['user_id'],
                        'school_year_id'   => $snap['school_year_id'],
                        'academic_term_id' => $termId,
                        'assignment_type'  => $snap['assignment_type'],
                        'created_by'       => $snap['created_by'] ?? null,
                    ]));
                    $restored++;
                    continue;
                }

                // Only live now — didn't exist at snapshot time — remove it.
                if ($this->isLocked((int) $live->user_id, $termId)) {
                    $skippedUserIds[(int) $live->user_id] = true;
                    continue;
                }
                $touchedFacultyLoadIds[$live->faculty_load_id] = true;
                $live->delete();
                $restored++;
            }

            foreach (array_keys($touchedFacultyLoadIds) as $facultyLoadId) {
                $load = FacultyLoad::find($facultyLoadId);
                if ($load) {
                    $this->loadService->syncLoad($load);
                }
            }

            $version->update(['restored_at' => now()]);
        });

        $skippedNames = $skippedUserIds === []
            ? []
            : User::whereIn('id', array_keys($skippedUserIds))->pluck('name')->values()->all();

        return ['restored' => $restored, 'skipped_locked' => $skippedNames];
    }

    /**
     * Compare version $a against version $b, or against the term's current
     * live assignments when $b is null.
     *
     * @return array{summary:array,diff:array<int,array<string,mixed>>}
     */
    public function compare(LoadAssignmentVersion $a, ?LoadAssignmentVersion $b, int $termId): array
    {
        $rowsA = $a->assignment_snapshot;
        $rowsB = $b?->assignment_snapshot ?? $this->currentSnapshot($termId);

        return [
            'summary' => [
                'a' => $this->summarize($rowsA),
                'b' => $this->summarize($rowsB),
            ],
            'diff' => $this->diff($rowsA, $rowsB),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    private function currentSnapshot(int $termId): array
    {
        return LoadAssignment::where('academic_term_id', $termId)
            ->orderBy('id')
            ->get(self::COLUMNS)
            ->map(fn ($row) => $row->getAttributes())
            ->values()
            ->all();
    }

    /**
     * Teaching assignments key on the DB's own "one faculty per subject+
     * section+term" slot, independent of who holds it — this is what lets
     * restore detect "this slot changed hands" as a single move rather than
     * an unrelated delete+create pair. Every other type has no such shared
     * slot concept, so they key per-faculty instead.
     *
     * @param array<string,mixed>|LoadAssignment $row
     */
    private function matchKey(array $row): string
    {
        if ($row['assignment_type'] === 'teaching' && $row['subject_id'] && $row['section_id']) {
            return "teaching|{$row['subject_id']}|{$row['section_id']}";
        }

        return implode('|', [
            $row['assignment_type'], $row['user_id'], $row['designation_id'] ?? '', $row['description'] ?? '',
        ]);
    }

    private function isLocked(int $userId, int $termId): bool
    {
        if (! isset($this->lockCache[$userId])) {
            $this->lockCache[$userId] = FacultyLoad::where('user_id', $userId)
                ->where('academic_term_id', $termId)
                ->where('is_locked', true)
                ->exists();
        }

        return $this->lockCache[$userId];
    }

    /** @param array<string,mixed> $snap */
    private function fieldsDiffer(LoadAssignment $live, array $snap): bool
    {
        foreach (['load_units', 'description', 'is_overridden', 'designation_id', 'subject_id', 'section_id'] as $field) {
            if ($this->normalize($live->{$field} ?? null) !== $this->normalize($snap[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** Canonical string form for comparing a live (cast) value against a raw snapshot value. */
    private function normalize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /** @param array<string,mixed> $snap */
    private function updatableFields(array $snap): array
    {
        return [
            'subject_id'      => $snap['subject_id'] ?? null,
            'section_id'      => $snap['section_id'] ?? null,
            'load_units'      => $snap['load_units'],
            'description'     => $snap['description'] ?? null,
            'is_overridden'   => $snap['is_overridden'] ?? false,
            'designation_id'  => $snap['designation_id'] ?? null,
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function summarize(array $rows): array
    {
        $byType = [];
        foreach ($rows as $row) {
            $type = $row['assignment_type'];
            $byType[$type]['count'] = ($byType[$type]['count'] ?? 0) + 1;
            $byType[$type]['units'] = round(($byType[$type]['units'] ?? 0) + (float) $row['load_units'], 2);
        }

        return [
            'total_assignments' => count($rows),
            'total_units'       => round(array_sum(array_column($rows, 'load_units')), 2),
            'by_type'           => $byType,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rowsA
     * @param array<int,array<string,mixed>> $rowsB
     * @return array<int,array<string,mixed>>
     */
    private function diff(array $rowsA, array $rowsB): array
    {
        $byKeyA = [];
        foreach ($rowsA as $row) {
            $byKeyA[$this->matchKey($row)] = $row;
        }
        $byKeyB = [];
        foreach ($rowsB as $row) {
            $byKeyB[$this->matchKey($row)] = $row;
        }

        $keys = array_unique(array_merge(array_keys($byKeyA), array_keys($byKeyB)));
        $labels = $this->labelsFor(array_merge($rowsA, $rowsB));

        $diff = [];
        foreach ($keys as $key) {
            $a = $byKeyA[$key] ?? null;
            $b = $byKeyB[$key] ?? null;
            $label = $labels[$key] ?? $key;

            if ($a === null) {
                $diff[] = ['key' => $key, 'label' => $label, 'status' => 'added', 'to' => $this->describe($b, $labels)];
                continue;
            }
            if ($b === null) {
                $diff[] = ['key' => $key, 'label' => $label, 'status' => 'removed', 'from' => $this->describe($a, $labels)];
                continue;
            }
            if ($this->rowsDiffer($a, $b)) {
                $diff[] = [
                    'key' => $key, 'label' => $label, 'status' => 'changed',
                    'from' => $this->describe($a, $labels), 'to' => $this->describe($b, $labels),
                ];
            }
        }

        usort($diff, fn ($x, $y) => $x['label'] <=> $y['label']);

        return $diff;
    }

    /** @param array<string,mixed> $a */
    private function rowsDiffer(array $a, array $b): bool
    {
        foreach (['user_id', 'load_units', 'designation_id', 'description'] as $field) {
            if ($this->normalize($a[$field] ?? null) !== $this->normalize($b[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,string> $labels
     */
    private function describe(array $row, array $labels): array
    {
        return [
            'faculty_name' => $labels['__user_'.$row['user_id']] ?? "User #{$row['user_id']}",
            'load_units'   => (float) $row['load_units'],
        ];
    }

    /**
     * Resolve subject/section/faculty/designation labels for every row seen
     * on either side, keyed both by matchKey (for the diff row's own label)
     * and by "__user_{id}" (for describe()'s per-side faculty name).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,string>
     */
    private function labelsFor(array $rows): array
    {
        $subjectIds     = array_unique(array_filter(array_column($rows, 'subject_id')));
        $sectionIds     = array_unique(array_filter(array_column($rows, 'section_id')));
        $userIds        = array_unique(array_filter(array_column($rows, 'user_id')));
        $designationIds = array_unique(array_filter(array_column($rows, 'designation_id')));

        $subjectNames     = Subject::whereIn('id', $subjectIds)->pluck('name', 'id');
        $sectionNames     = Section::whereIn('id', $sectionIds)->get(['id', 'sectionname', 'levelid'])
            ->mapWithKeys(fn ($s) => [$s->id => "G{$s->levelid} {$s->sectionname}"]);
        $facultyNames     = User::whereIn('id', $userIds)->pluck('name', 'id');
        $designationNames = Designation::whereIn('id', $designationIds)->pluck('name', 'id');

        $labels = [];
        foreach ($userIds as $id) {
            $labels['__user_'.$id] = $facultyNames[$id] ?? "User #{$id}";
        }

        foreach ($rows as $row) {
            $key = $this->matchKey($row);
            if (isset($labels[$key])) {
                continue;
            }
            if ($row['assignment_type'] === 'teaching' && $row['subject_id'] && $row['section_id']) {
                $subject = $subjectNames[$row['subject_id']] ?? 'Unknown subject';
                $section = $sectionNames[$row['section_id']] ?? 'Unknown section';
                $labels[$key] = "{$subject} — {$section}";
            } else {
                $faculty = $facultyNames[$row['user_id']] ?? 'Unknown faculty';
                $descriptor = $row['designation_id']
                    ? ($designationNames[$row['designation_id']] ?? 'Designation')
                    : ($row['description'] ?? ucfirst($row['assignment_type']));
                $labels[$key] = "{$faculty} — {$descriptor}";
            }
        }

        return $labels;
    }
}
