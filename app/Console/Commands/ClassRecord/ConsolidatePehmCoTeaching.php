<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordTeacher;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ConsolidatePehmCoTeaching extends Command
{
    protected $signature = 'class-record:consolidate-pehm-coteaching {--commit : Actually write changes; without this flag the command only reports what it would do}';

    protected $description = 'One-off data fix: split shared PEHM grading-option templates per grade level and consolidate each section\'s separate PEHM class records into one shared co-teacher record.';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $this->info($commit ? 'RUNNING IN COMMIT MODE — writes will be made.' : 'DRY RUN — no writes will be made. Pass --commit to apply.');

        $schoolYear = SchoolYear::where('is_current', true)->first();
        if (! $schoolYear) {
            $this->error('No current school year found.');

            return self::FAILURE;
        }

        $subjectsByGrade = $this->subjectsByGradeAndRole();
        $pehmSubjectIds = array_merge(...array_map('array_values', $subjectsByGrade));

        // Idempotency: a "PEHM 1-3 (Grade N)" clone from a prior run already
        // exists — reuse it instead of cloning again.
        $sourceTemplate = GradingOption::where('name', 'PEHM 1-3')->first();
        $grade10Template = GradingOption::where('name', 'PEHM 4 Final')->first();

        $bySection = ClassRecord::whereIn('subject_id', $pehmSubjectIds)
            ->where('school_year_id', $schoolYear->id)
            ->where('status', '<>', 'archived')
            ->with('subject')
            ->get()
            ->groupBy('section_id');

        // Full set of grade-10 class record ids across every section in this
        // run, computed up front — assertOptionOnlyUsedByPehm needs the
        // COMPLETE expected set on every call, not just the current
        // section's, or the 2nd+ grade-10 section would false-positive
        // against the 1st section's already-processed records.
        $grade10RecordIds = $bySection->flatten()
            ->filter(fn ($r) => $r->subject?->grade_level === 10)
            ->pluck('id');

        $totalPivotRows = 0;
        $totalReparented = 0;
        $allUnmatched = [];

        foreach ($bySection as $sectionId => $sectionRecords) {
            if ($sectionRecords->count() < 2) {
                $this->line("Section {$sectionId}: only ".$sectionRecords->count().' PEHM record, nothing to consolidate.');

                continue;
            }

            $gradeLevel = $sectionRecords->first()->subject->grade_level;
            $subjectsByRole = $subjectsByGrade[$gradeLevel] ?? [];

            if ($gradeLevel === 10) {
                if ($grade10Template === null) {
                    $this->warn("Section {$sectionId}: no 'PEHM 4 Final' template found, skipping.");

                    continue;
                }
                if ($commit) {
                    $this->assertOptionOnlyUsedByPehm($grade10Template, $grade10RecordIds);
                }
                $targetOption = $this->resolveTargetOptionForGrade($grade10Template, 10, $subjectsByRole, $commit, cloneEvenIfSingleGrade: false);
            } else {
                $existingClone = GradingOption::where('name', "PEHM 1-3 (Grade {$gradeLevel})")->first();
                if ($existingClone) {
                    $targetOption = $existingClone->load('categories');
                } elseif ($sourceTemplate) {
                    $targetOption = $this->resolveTargetOptionForGrade($sourceTemplate, $gradeLevel, $subjectsByRole, $commit);
                } else {
                    $this->warn("Section {$sectionId}: no 'PEHM 1-3' template found, skipping.");

                    continue;
                }
            }

            $report = $this->consolidateSection($sectionRecords, $targetOption, $commit);

            $this->line("Section {$sectionId} (Grade {$gradeLevel}): canonical=cr{$report['canonical_id']}, archived=[".implode(',', $report['archived_ids'])."], pivot_rows={$report['pivot_rows_created']}, reparented={$report['assessments_reparented']}");

            $totalPivotRows += $report['pivot_rows_created'];
            $totalReparented += $report['assessments_reparented'];
            $allUnmatched = array_merge($allUnmatched, $report['unmatched_leaves']);
        }

        $this->newLine();
        $this->info("Totals: {$totalPivotRows} co-teacher pivot rows, {$totalReparented} assessments re-parented.");

        if (! empty($allUnmatched)) {
            $this->warn('Unmatched leaves needing manual attention:');
            foreach ($allUnmatched as $line) {
                $this->warn("  - {$line}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Detects which PEHM subject (PE/Health/Music) a leaf grading category
     * represents, by matching the literal "(PE)"/"(Health)"/"(Music)"
     * substring every real production leaf name carries. Returns null
     * (never a guess) for parent/summary categories or anything unmatched.
     */
    public function roleForCategory(GradingCategory $category): ?string
    {
        return match (true) {
            str_contains($category->name, '(PE)') => 'PE',
            str_contains($category->name, '(Health)') => 'Health',
            str_contains($category->name, '(Music)') => 'Music',
            default => null,
        };
    }

    /** "SA 1" -> "SA", "FA 2" -> "FA", "SA" -> "SA". */
    public function stripCode(string $code): string
    {
        return trim(preg_replace('/\s*\d+$/', '', $code));
    }

    /**
     * @return array<int,array<string,int>> grade_level => ['PE' => subject_id, 'Health' => ..., 'Music' => ...]
     */
    public function subjectsByGradeAndRole(): array
    {
        $subjects = Subject::where('subject_group', 'PEHM')->get(['id', 'name', 'grade_level']);

        $map = [];
        foreach ($subjects as $subject) {
            $role = match (true) {
                str_starts_with($subject->name, 'Health') => 'Health',
                str_starts_with($subject->name, 'Music') => 'Music',
                str_starts_with($subject->name, 'Physical Education') => 'PE',
                default => null,
            };

            if ($role === null) {
                continue;
            }

            $map[$subject->grade_level][$role] = $subject->id;
        }

        return $map;
    }

    /**
     * For grade levels that share a template with other grade levels (7-9,
     * all currently on "PEHM 1-3"), clone the template into a grade-specific
     * copy with every matched leaf's subject_id correctly set — the shared
     * original is never mutated, since other grade levels' (not-yet-processed)
     * records may still reference it during the same run.
     *
     * For a grade level that is the ONLY consumer of a template (Grade 10 /
     * "PEHM 4 Final", after repointing away the "PEHM 4" stragglers earlier in
     * the run), mutate the template's leaves in place — no clone needed, and
     * doing so avoids leaving an orphaned duplicate option behind.
     *
     * @param  array<string,int>  $subjectsByRole  ['PE' => id, 'Health' => id, 'Music' => id] for this grade level
     */
    public function resolveTargetOptionForGrade(
        GradingOption $sourceTemplate,
        int $gradeLevel,
        array $subjectsByRole,
        bool $commit,
        bool $cloneEvenIfSingleGrade = true,
    ): GradingOption {
        if (! $cloneEvenIfSingleGrade) {
            foreach ($sourceTemplate->categories as $category) {
                $role = $this->roleForCategory($category);
                if ($role === null || ! isset($subjectsByRole[$role])) {
                    continue;
                }
                $category->subject_id = $subjectsByRole[$role];
                if ($commit) {
                    $category->save();
                }
            }

            return $sourceTemplate;
        }

        $clone = $sourceTemplate->replicate(['id', 'created_at', 'updated_at']);
        $clone->name = "{$sourceTemplate->name} (Grade {$gradeLevel})";

        if ($commit) {
            $clone->save();
        } else {
            $clone->id = -1; // sentinel: never persisted, dry-run display only
        }

        // Every created leaf/parent (persisted or not) is collected here and
        // attached to $clone's "categories" relation in-memory below —
        // required so consolidateSection()'s $targetOption->categories read
        // works correctly even for a dry-run clone that was never actually
        // saved (a lazy DB load of grading_option_id = -1 would return
        // nothing and make every assessment look "unmatched").
        $allCreated = collect();

        // Dry-run sentinel id counter (starts at -2 since the option clone
        // itself already uses -1). Each cloned LEAF must get its own
        // distinct non-null id — a null id would make
        // `$targetIndex[$key] ?? null` in consolidateSection() unable to
        // distinguish "key exists, id is null" from "key missing", causing
        // every dry-run match to silently report as unmatched.
        $sentinelId = -2;

        $topLevel = $sourceTemplate->categories()->whereNull('parent_id')->get();
        foreach ($topLevel as $parent) {
            $newParent = $this->cloneCategory($parent, $clone, null, $subjectsByRole, $commit, $sentinelId);
            $allCreated->push($newParent);
            foreach ($parent->children as $child) {
                $allCreated->push($this->cloneCategory($child, $clone, $newParent, $subjectsByRole, $commit, $sentinelId));
            }
        }

        $clone->setRelation('categories', $allCreated);

        return $clone;
    }

    private function cloneCategory(GradingCategory $source, GradingOption $newOption, ?GradingCategory $newParent, array $subjectsByRole, bool $commit, int &$sentinelId): GradingCategory
    {
        $role = $this->roleForCategory($source);
        $new = $source->replicate(['id', 'created_at', 'updated_at']);
        $new->grading_option_id = $commit ? $newOption->id : -1;
        $new->parent_id = $newParent?->id;
        $new->subject_id = ($role !== null && isset($subjectsByRole[$role])) ? $subjectsByRole[$role] : null;

        if (! $commit) {
            $new->id = $sentinelId--;
        }

        if ($commit) {
            $new->save();
        }

        return $new;
    }

    /**
     * Refuses to proceed if a shared option we're about to mutate in place
     * (the grade-10 "no clone" path) is referenced by any class record OUTSIDE
     * the expected set — protects against silently corrupting an unrelated
     * module's use of the same GradingOption row.
     */
    public function assertOptionOnlyUsedByPehm(GradingOption $option, iterable $expectedClassRecordIds): void
    {
        $expected = collect($expectedClassRecordIds)->map(fn ($id) => (int) $id)->all();

        $unexpected = ClassRecord::where('grading_option_id', $option->id)
            ->where('status', '<>', 'archived')
            ->whereNotIn('id', $expected)
            ->pluck('id');

        if ($unexpected->isNotEmpty()) {
            throw new \RuntimeException(
                "Refusing to mutate grading option #{$option->id} ({$option->name}) in place — ".
                "it is referenced by unexpected class record(s): {$unexpected->implode(', ')}. ".
                'Investigate before proceeding; this option may be in use outside the PEHM consolidation scope.'
            );
        }
    }

    /**
     * @param  Collection<int,ClassRecord>  $records  every non-archived PEHM record for one section (2-3 rows)
     * @return array{canonical_id:int,archived_ids:array<int>,pivot_rows_created:int,assessments_reparented:int,unmatched_leaves:array<string>}
     */
    public function consolidateSection(Collection $records, GradingOption $targetOption, bool $commit): array
    {
        $canonical = $records->sortBy('id')->first();
        $others = $records->reject(fn ($r) => $r->id === $canonical->id);

        // Build the target option's role/strippedCode -> new-leaf-id index.
        $targetIndex = [];
        foreach ($targetOption->categories as $leaf) {
            $role = $this->roleForCategory($leaf);
            if ($role === null) {
                continue;
            }
            $targetIndex[$role.'|'.$this->stripCode($leaf->code)] = $leaf->id;
        }

        $report = [
            'canonical_id' => $canonical->id,
            'archived_ids' => [],
            'pivot_rows_created' => 0,
            'assessments_reparented' => 0,
            'unmatched_leaves' => [],
        ];

        $run = function () use ($records, $canonical, $others, $targetIndex, $commit, $targetOption, &$report) {
            // Every teacher on every record in this section gets an explicit
            // pivot row on the canonical record — including the canonical
            // record's own original teacher. teacherIdsFor() falls back to
            // teacher_id ONLY when coTeachers is entirely empty; once we add
            // any pivot row, the canonical teacher needs one too or they lose
            // their own edit access.
            foreach ($records as $record) {
                $exists = ClassRecordTeacher::where('class_record_id', $canonical->id)
                    ->where('subject_id', $record->subject_id)
                    ->where('user_id', $record->teacher_id)
                    ->exists();

                if (! $exists) {
                    if ($commit) {
                        ClassRecordTeacher::create([
                            'class_record_id' => $canonical->id,
                            'subject_id' => $record->subject_id,
                            'user_id' => $record->teacher_id,
                            'is_primary' => $record->id === $canonical->id,
                        ]);
                    }
                    $report['pivot_rows_created']++;
                }
            }

            if ($commit) {
                $canonical->grading_option_id = $targetOption->id;
                $canonical->save();
            }

            // Re-parent every record's assessments onto the target option's
            // matching leaf — including the canonical record's OWN
            // pre-existing assessments, which still point at leaves under
            // its old grading_option_id now that grading_option_id has been
            // swapped to $targetOption above. Non-canonical records'
            // assessments additionally move onto the canonical record's
            // matching quarter (creating it if needed) and the record itself
            // gets archived once its assessments are moved.
            foreach ($records as $record) {
                $isCanonical = $record->id === $canonical->id;
                $quarters = ClassRecordQuarter::where('class_record_id', $record->id)->get();

                foreach ($quarters as $quarter) {
                    $assessments = ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)
                        ->with('gradingCategory')
                        ->get();

                    if ($assessments->isEmpty()) {
                        continue;
                    }

                    $canonicalQuarter = $isCanonical
                        ? $quarter
                        : ($commit
                            ? ClassRecordQuarter::firstOrCreate(['class_record_id' => $canonical->id, 'quarter' => $quarter->quarter])
                            : (ClassRecordQuarter::where('class_record_id', $canonical->id)->where('quarter', $quarter->quarter)->first()
                                ?? new ClassRecordQuarter(['class_record_id' => $canonical->id, 'quarter' => $quarter->quarter])));

                    foreach ($assessments as $assessment) {
                        $oldLeaf = $assessment->gradingCategory;
                        $role = $oldLeaf ? $this->roleForCategory($oldLeaf) : null;
                        $key = $role !== null ? $role.'|'.$this->stripCode($oldLeaf->code) : null;
                        $newLeafId = $key !== null ? ($targetIndex[$key] ?? null) : null;

                        if ($newLeafId === null) {
                            $report['unmatched_leaves'][] = "Assessment '{$assessment->title}' (id {$assessment->id}, old leaf '".($oldLeaf->name ?? 'unknown')."') on class record #{$record->id} — no matching leaf found on target option #{$targetOption->id}, left in place.";

                            continue;
                        }

                        if ($commit) {
                            $assessment->class_record_quarter_id = $canonicalQuarter->id;
                            $assessment->grading_category_id = $newLeafId;
                            $assessment->save();
                        }
                        $report['assessments_reparented']++;
                    }
                }
            }

            foreach ($others as $other) {
                if ($commit) {
                    $other->status = 'archived';
                    $other->save();
                }
                $report['archived_ids'][] = $other->id;
            }
        };

        if ($commit) {
            DB::transaction($run);
        } else {
            $run();
        }

        return $report;
    }
}
