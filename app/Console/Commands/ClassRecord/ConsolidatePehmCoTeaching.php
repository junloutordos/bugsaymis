<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\Subject;
use Illuminate\Console\Command;

class ConsolidatePehmCoTeaching extends Command
{
    protected $signature = 'class-record:consolidate-pehm-coteaching {--commit : Actually write changes; without this flag the command only reports what it would do}';

    protected $description = 'One-off data fix: split shared PEHM grading-option templates per grade level and consolidate each section\'s separate PEHM class records into one shared co-teacher record.';

    public function handle(): int
    {
        $this->info($this->option('commit') ? 'RUNNING IN COMMIT MODE — writes will be made.' : 'DRY RUN — no writes will be made. Pass --commit to apply.');

        // Populated by later tasks.
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

        $topLevel = $sourceTemplate->categories()->whereNull('parent_id')->get();
        foreach ($topLevel as $parent) {
            $newParent = $this->cloneCategory($parent, $clone, null, $subjectsByRole, $commit);
            foreach ($parent->children as $child) {
                $this->cloneCategory($child, $clone, $newParent, $subjectsByRole, $commit);
            }
        }

        return $clone;
    }

    private function cloneCategory(GradingCategory $source, GradingOption $newOption, ?GradingCategory $newParent, array $subjectsByRole, bool $commit): GradingCategory
    {
        $role = $this->roleForCategory($source);
        $new = $source->replicate(['id', 'created_at', 'updated_at']);
        $new->grading_option_id = $commit ? $newOption->id : -1;
        $new->parent_id = $newParent?->id;
        $new->subject_id = ($role !== null && isset($subjectsByRole[$role])) ? $subjectsByRole[$role] : null;

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
}
