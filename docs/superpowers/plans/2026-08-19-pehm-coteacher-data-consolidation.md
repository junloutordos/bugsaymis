# PEHM Co-Teacher Data Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and run a one-off, dry-run-capable Artisan command that fixes the real root cause of "PEHM co-teacher can't plot assessments / calendar greyed out" in production — not a code bug (already fixed 2026-08-12, `3bc71725`, verified deployed and passing) but incomplete production data from the 2026-08-07 PEHM co-teacher rollout that was structurally impossible to finish as originally planned.

**Architecture:** No application code changes. A single new Artisan command (`class-record:consolidate-pehm-coteaching`) performs two data operations per current-SY PEHM class record group: (1) resolve the shared/ambiguous `GradingOption` templates into grade-level-correct ones with real `subject_id` values on every leaf category, and (2) consolidate each section's 2-3 separate individual class records into one shared record with proper `class_record_teachers` pivot rows, re-parenting existing assessment definitions (dates/titles, no scores exist yet) onto the correct new leaf categories. Defaults to `--dry-run` reporting; only `--commit` writes, and every section's writes are wrapped in its own `DB::transaction()`. Nothing is ever hard-deleted — superseded records get `status = 'archived'`.

**Tech Stack:** Laravel 12 / PHP 8.4, Eloquent, Pest/PHPUnit (`RefreshDatabase`), existing `App\Models\ClassRecord\*` models.

**Spec:** No separate spec doc — this plan itself documents the investigated root cause and the approved decisions (see "Background" below). Read this whole document before starting; there is no other design doc.

## Background (read first — this is the investigation, not boilerplate)

**Reported symptom:** In the Class Record / Weekly Assessment Tracker (WAT) modules, on a shared PEHM (PE/Health/Music) class record, a co-teacher cannot plot their own assessments — the calendar/date picker greys out their real scheduled class days, and only the record's original creator appears able to create/plot anything.

**This exact code-level bug was already fixed and deployed** in commit `3bc71725` ("fix(class-record): scope PEHM co-teacher schedule check to their own subject", 2026-08-12), confirmed an ancestor of every deploy since (latest checked: run `32115804790`, 2026-08-18). All 20 tests in `tests/Feature/ClassRecord/ClassRecordPehmCoTeachingTest.php` pass on current `main`, including the two that cover this exact scenario. **No application code needs to change.**

**Real root cause, confirmed via read-only production queries (2026-08-18/19):**

1. `grading_categories.subject_id` is a literal FK to one specific grade-level `subjects.id` row (see `app/Models/ClassRecord/GradingCategory.php:14-56`, migration `2026_07_30_083911_add_subject_id_to_grading_categories.php`). It is read directly — never re-derived by `subject_group` or name — by `GradingCategory::canEditOn()` (`app/Models/ClassRecord/GradingCategory.php:70-73`), `ClassRecord::canEdit()`/`teacherIdsFor()` (`app/Models/ClassRecord/ClassRecord.php:120-170`), `ClassRecordAssessmentController::upsert()` (lines 238-254, 428-446, 826-892, 1093), and `AssessmentPlottingService` (lines 98-124, 255-274). But `grading_categories.grading_option_id` is a **shared template row** reused across every grade level that teaches PEHM (confirmed: `GradingOption::categories()` is a plain `hasMany` keyed on `grading_option_id`, not cloned per class record — `GradingOptionController::updateCategories()`/`syncCategories()` mutates the one shared row that every class record using that option sees). A single literal `subject_id` value can only ever be correct for one grade level. This is why the 2026-08-07 handoff asking Academic Affairs to manually fill in `subject_id` was never completed — there is no single correct value to fill in as originally specified.

2. **None of the 16 current-SY PEHM sections have ever been consolidated.** Every section still has 2-3 separate individual `class_records` rows (one per subject-teacher) instead of one shared record. Only 7 of 71 non-archived PEHM class records have any `class_record_teachers` pivot row at all.

3. **Grading-option assignment is itself inconsistent.** Four distinct `GradingOption` templates are actually in use: "PEHM 1-3" (id 64), "PEHM 3" (id 70), "PEHM 4" (id 66), "PEHM 4 Final" (id 71) — not applied uniformly even within one grade level (Grade 9: 3 sections on "PEHM 1-3", 1 section — 264 — on "PEHM 3"; Grade 10: 8 records on "PEHM 4", 4 on "PEHM 4 Final"). Section 264 (Grade 9) additionally has **no Health teacher/class record at all**.

**Risk assessment (confirmed via prod query):** 0 non-null `class_record_scores` exist across all 71 records — **no real grades to lose**. 87 `class_record_assessments` (dates/titles, ungraded) and 1254 `class_record_students` roster rows exist and must be preserved, not discarded. 24 of the 95 total PEHM records are already `status = 'archived'` (leave untouched — already excluded from every query below via `status <> 'archived'`).

**Decisions approved by the user (2026-08-18):**
- Grade 9 standardizes on **"PEHM 1-3"** (id 64) — the option already used by 3 of 4 sections. Section 264 (currently on "PEHM 3", id 70) gets repointed to it.
- Grade 10 standardizes on **"PEHM 4 Final"** (id 71) — the 8 records currently on "PEHM 4" (id 66) get repointed to it.
- Grade 7 and Grade 8 already have one consistent option each (both currently "PEHM 1-3", id 64) — no repointing needed there, only cloning + subject_id fill.
- Section 264's missing Health teacher: **consolidate what exists** (Music + PE only), leave Health unassigned, and flag it in the dry-run report as an open item for Academic Affairs — do not fabricate a teacher assignment.
- Execution: **build the dry-run command first**, review its real-production output together, and only then get separate explicit approval to run `--commit`. Task 6 in this plan (the actual `--commit` run) must not be executed without that fresh go-ahead — it is a distinct approval gate from "the plan is approved."

**The four grade levels and their PEHM subjects** (all `subject_group = 'PEHM'`, confirmed via prod query):

| Grade | Health id | Music id | PE id |
|---|---|---|---|
| 7  | 64  | 67  | 68  |
| 8  | 78  | 81  | 82  |
| 9  | 91  | 93  | 94  |
| 10 | 104 | 106 | 107 |

**Role detection:** every PEHM leaf `GradingCategory.name` observed in production contains a literal `(PE)`, `(Health)`, or `(Music)` substring (e.g. `"SA (PE)"`, `"FA (Health)"`, `"AA (Music)"`). This is the role-matching signal — never inferred from `code` alone, and never guessed when absent (see Task 2).

**Current per-section production state** (non-archived, current SY 2026-2027, `school_year_id = 13`):

| Section | Grade | Records (subject: class_record id, teacher id) |
|---|---|---|
| 256-259 | 7  | Health: cr(295/297/298/299), t27 · Music: cr(240/241/242/243), t32 · PE: cr(294/300/301/302), t27 |
| 260-263 | 8  | Health: cr(260/261/262/263), t56 · Music: cr(272/273/274/275), t32 · PE: cr(296/303/304/305), t27 |
| 265-267 | 9  | Health: cr(269/270/271), t56 · Music: cr(276/277/278), t32 · PE: cr(265/266/267), t56 |
| 264 | 9 | Music: cr335, t32 · PE: cr333, t56 · **Health: none** |
| 268-271 | 10 | Health: cr(306/307/308/309), t9 · Music: cr(1099/1100/1101/1102), t32 · PE: cr(290/291/292/293), t56 |

Note Grade 9 (265-267): the same teacher (t56) teaches both Health and PE — a real 2-person share, not 3. This is fine; the pivot design handles any subject/teacher combination, it doesn't assume exactly 3.

## Global Constraints

- No `FormData`/`multipart/form-data` — not applicable, this task has no file uploads.
- No `Storage::disk('public')` — not applicable.
- Never use `new DateTime()` with Eloquent date-cast attributes — use `Carbon::parse($value)->format('Y-m-d')` if any date manipulation is needed.
- Soft-delete convention: `status <> 'archived'`, never a hard `delete()`.
- Never `git add -A`/`.` — stage files by name.
- Migrations (if any needed) must be additive/backward-compatible per the blue-green deploy rule — this plan adds no migrations (no schema change needed; `grading_categories.subject_id` and `class_record_teachers` already exist).
- `php artisan` runs via `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan ..."` in dev (per CLAUDE.md); production runs via ECS exec (`aws ecs execute-command ... --command "php /var/www/artisan ..."`).

---

## File Structure

- Create: `app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php` — the command, all logic lives here (single responsibility: this one data-consolidation operation; not a generic reusable service, so no separate Service class is warranted).
- Create: `tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php` — full TDD coverage against a hand-built fixture that mirrors the production shape (multiple grade levels, multiple option names, the section-264 gap).
- No other files change.

---

### Task 1: Command skeleton, role/subject resolution helpers, dry-run scaffolding

**Files:**
- Create: `app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php`
- Test: `tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php`

**Interfaces:**
- Produces: `ConsolidatePehmCoTeaching::roleForCategory(GradingCategory $cat): ?string` (returns `'PE'|'Health'|'Music'|null`), `ConsolidatePehmCoTeaching::stripCode(string $code): string` (e.g. `"SA 1"` → `"SA"`), `ConsolidatePehmCoTeaching::subjectsByGradeAndRole(): array<int,array<string,int>>` (e.g. `[7 => ['PE' => 68, 'Health' => 64, 'Music' => 67], ...]`). Later tasks call all three.

- [ ] **Step 1: Write the failing test for role/subject resolution**

```php
<?php

use App\Console\Commands\ClassRecord\ConsolidatePehmCoTeaching;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\Subject;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makePehmSubjects(): void
{
    Subject::create(['id' => 64, 'name' => 'Health 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    Subject::create(['id' => 67, 'name' => 'Music 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    Subject::create(['id' => 68, 'name' => 'Physical Education 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    Subject::create(['id' => 78, 'name' => 'Health 2', 'subject_group' => 'PEHM', 'grade_level' => 8, 'subject_type' => 'lecture']);
    Subject::create(['id' => 81, 'name' => 'Music 2', 'subject_group' => 'PEHM', 'grade_level' => 8, 'subject_type' => 'lecture']);
    Subject::create(['id' => 82, 'name' => 'Physical Education 2', 'subject_group' => 'PEHM', 'grade_level' => 8, 'subject_type' => 'lecture']);
}

test('roleForCategory detects PE, Health, Music from name, null otherwise', function () {
    $option = GradingOption::create(['name' => 'PEHM 1-3']);
    $pe = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10]);
    $health = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 10]);
    $music = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Music)', 'code' => 'SA 3', 'weight' => 10]);
    $parent = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Summative Assessment', 'code' => 'SA', 'weight' => 30]);

    $cmd = new ConsolidatePehmCoTeaching();

    expect($cmd->roleForCategory($pe))->toBe('PE');
    expect($cmd->roleForCategory($health))->toBe('Health');
    expect($cmd->roleForCategory($music))->toBe('Music');
    expect($cmd->roleForCategory($parent))->toBeNull();
});

test('stripCode removes trailing number', function () {
    $cmd = new ConsolidatePehmCoTeaching();

    expect($cmd->stripCode('SA 1'))->toBe('SA');
    expect($cmd->stripCode('FA 2'))->toBe('FA');
    expect($cmd->stripCode('AA 3'))->toBe('AA');
    expect($cmd->stripCode('SA'))->toBe('SA');
});

test('subjectsByGradeAndRole maps grade level and role to subject id', function () {
    makePehmSubjects();
    $cmd = new ConsolidatePehmCoTeaching();

    $map = $cmd->subjectsByGradeAndRole();

    expect($map[7])->toBe(['Health' => 64, 'Music' => 67, 'PE' => 68]);
    expect($map[8])->toBe(['Health' => 78, 'Music' => 81, 'PE' => 82]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run (dev container): `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: FAIL — `Class "App\Console\Commands\ClassRecord\ConsolidatePehmCoTeaching" not found`.

- [ ] **Step 3: Write the command skeleton with the three helpers**

```php
<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\GradingCategory;
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
}
```

- [ ] **Step 4: Register the command's namespace is auto-discovered**

Laravel 12 auto-discovers commands under `app/Console/Commands/`. No manual registration needed — confirm by running:

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan list class-record"`
Expected: `class-record:consolidate-pehm-coteaching` appears in the list.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php
git commit -m "feat(class-record): add PEHM co-teacher consolidation command skeleton"
```

---

### Task 2: Grading-option resolution — clone per grade (7/8/9) or direct-fill (10)

**Files:**
- Modify: `app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php`
- Test: `tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php`

**Interfaces:**
- Consumes: `roleForCategory()`, `subjectsByGradeAndRole()` from Task 1.
- Produces: `resolveTargetOptionForGrade(GradingOption $sourceTemplate, int $gradeLevel, array $subjectsByRole, bool $commit): GradingOption` — for grade levels 7-9, clones `$sourceTemplate` into a new `"{name} (Grade {level})"` option with every matched leaf's `subject_id` set; for grade 10, mutates the existing template's leaves in place (no clone) and returns it unchanged. In dry-run mode (`$commit = false`) returns a **non-persisted** in-memory `GradingOption` (so later dry-run steps can still read its would-be shape) and performs zero writes. Also produces `assertOptionOnlyUsedByPehm(GradingOption $option, iterable $expectedClassRecordIds): void` — a safety guard.

- [ ] **Step 1: Write the failing test for grade-level cloning**

```php
test('resolveTargetOptionForGrade clones the template per grade with correct subject ids, and does not touch the original', function () {
    makePehmSubjects();
    $template = GradingOption::create(['name' => 'PEHM 1-3']);
    $sa = GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'Summative Assessment', 'code' => 'SA', 'weight' => 30]);
    GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10]);
    GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 10]);
    GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (Music)', 'code' => 'SA 3', 'weight' => 10]);

    $cmd = new ConsolidatePehmCoTeaching();
    $subjectsByGrade = $cmd->subjectsByGradeAndRole();

    $grade7Option = $cmd->resolveTargetOptionForGrade($template, 7, $subjectsByGrade[7], commit: true);

    expect($grade7Option->id)->not->toBe($template->id);
    expect($grade7Option->name)->toBe('PEHM 1-3 (Grade 7)');

    $leaves = $grade7Option->categories()->whereNotNull('parent_id')->get()->keyBy('code');
    expect($leaves['SA 1']->subject_id)->toBe(68); // PE - Grade 7
    expect($leaves['SA 2']->subject_id)->toBe(64); // Health - Grade 7
    expect($leaves['SA 3']->subject_id)->toBe(67); // Music - Grade 7

    // Original template untouched
    $template->refresh();
    expect($template->categories()->whereNotNull('parent_id')->get()->pluck('subject_id')->filter()->isEmpty())->toBeTrue();
});

test('resolveTargetOptionForGrade dry-run makes zero writes and reports the would-be shape', function () {
    makePehmSubjects();
    $template = GradingOption::create(['name' => 'PEHM 1-3']);
    GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10]);

    $cmd = new ConsolidatePehmCoTeaching();
    $subjectsByGrade = $cmd->subjectsByGradeAndRole();

    $before = \DB::table('grading_options')->count();
    $result = $cmd->resolveTargetOptionForGrade($template, 7, $subjectsByGrade[7], commit: false);

    expect(\DB::table('grading_options')->count())->toBe($before);
    expect($result->name)->toBe('PEHM 1-3 (Grade 7)');
});

test('resolveTargetOptionForGrade for grade 10 mutates the existing template in place, no clone', function () {
    makePehmSubjects();
    Subject::create(['id' => 104, 'name' => 'Health 4', 'subject_group' => 'PEHM', 'grade_level' => 10, 'subject_type' => 'lecture']);
    Subject::create(['id' => 106, 'name' => 'Music 4', 'subject_group' => 'PEHM', 'grade_level' => 10, 'subject_type' => 'lecture']);
    Subject::create(['id' => 107, 'name' => 'Physical Education 4', 'subject_group' => 'PEHM', 'grade_level' => 10, 'subject_type' => 'lecture']);

    $template = GradingOption::create(['name' => 'PEHM 4 Final']);
    $leaf = GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'AA (PE)', 'code' => 'AA 1', 'weight' => 10]);

    $cmd = new ConsolidatePehmCoTeaching();
    $subjectsByGrade = $cmd->subjectsByGradeAndRole();

    $result = $cmd->resolveTargetOptionForGrade($template, 10, $subjectsByGrade[10], commit: true, cloneEvenIfSingleGrade: false);

    expect($result->id)->toBe($template->id);
    expect($leaf->refresh()->subject_id)->toBe(107);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: FAIL — `resolveTargetOptionForGrade` does not exist.

- [ ] **Step 3: Implement `resolveTargetOptionForGrade` and the safety guard**

```php
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\ClassRecord;

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

    // Tag so re-parenting logic (Task 3) can map old leaf id -> new leaf,
    // even in dry-run where nothing has a real new id yet.
    $new->setAttribute('_source_leaf_id', $source->id);

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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: PASS, 6 tests total.

- [ ] **Step 5: Write and run the safety-guard test**

```php
test('assertOptionOnlyUsedByPehm throws when the option is referenced by an unexpected class record', function () {
    $sy = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
    $option = GradingOption::create(['name' => 'PEHM 4 Final']);
    $expectedRecord = ClassRecord::create(['school_year_id' => $sy->id, 'teacher_id' => 1, 'grading_option_id' => $option->id, 'status' => 'draft']);
    $unexpectedRecord = ClassRecord::create(['school_year_id' => $sy->id, 'teacher_id' => 2, 'grading_option_id' => $option->id, 'status' => 'draft']);

    $cmd = new ConsolidatePehmCoTeaching();

    expect(fn () => $cmd->assertOptionOnlyUsedByPehm($option, [$expectedRecord->id]))
        ->toThrow(RuntimeException::class);

    // Passes when every referencing record is in the expected set
    $cmd->assertOptionOnlyUsedByPehm($option, [$expectedRecord->id, $unexpectedRecord->id]);
})->expectNotToPerformAssertions();
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: PASS, 7 tests total.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php
git commit -m "feat(class-record): grade-level grading-option resolution for PEHM consolidation"
```

---

### Task 3: Per-section consolidation — pivot rows, assessment re-parenting, archiving

**Files:**
- Modify: `app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php`
- Test: `tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php`

**Interfaces:**
- Consumes: `roleForCategory()`, `stripCode()` (Task 1); a resolved target `GradingOption` (Task 2).
- Produces: `consolidateSection(array $records, GradingOption $targetOption, bool $commit): array` — `$records` is a `Collection<ClassRecord>` for one section (2-3 rows, one per subject-teacher), already loaded with `subject`. Returns a report array `['canonical_id' => int, 'archived_ids' => int[], 'pivot_rows_created' => int, 'assessments_reparented' => int, 'unmatched_leaves' => array]`. In dry-run mode makes zero writes and the report describes what *would* happen.

- [ ] **Step 1: Write the failing test for a full section consolidation**

```php
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordTeacher;

function seedSectionFixture(GradingOption $option, array $leavesByCode, int $sectionId, int $syId): array
{
    $healthSubject = Subject::create(['name' => 'Health 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    $musicSubject = Subject::create(['name' => 'Music 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    $peSubject = Subject::create(['name' => 'Physical Education 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);

    $healthTeacher = \App\Models\User::factory()->create();
    $musicTeacher = \App\Models\User::factory()->create();
    $peTeacher = \App\Models\User::factory()->create();

    $healthRecord = ClassRecord::create(['school_year_id' => $syId, 'section_id' => $sectionId, 'subject_id' => $healthSubject->id, 'teacher_id' => $healthTeacher->id, 'grading_option_id' => $option->id, 'status' => 'draft']);
    $musicRecord = ClassRecord::create(['school_year_id' => $syId, 'section_id' => $sectionId, 'subject_id' => $musicSubject->id, 'teacher_id' => $musicTeacher->id, 'grading_option_id' => $option->id, 'status' => 'draft']);
    $peRecord = ClassRecord::create(['school_year_id' => $syId, 'section_id' => $sectionId, 'subject_id' => $peSubject->id, 'teacher_id' => $peTeacher->id, 'grading_option_id' => $option->id, 'status' => 'draft']);

    $healthQuarter = ClassRecordQuarter::create(['class_record_id' => $healthRecord->id, 'quarter' => 1]);
    ClassRecordAssessment::create(['class_record_quarter_id' => $healthQuarter->id, 'grading_category_id' => $leavesByCode['SA 2']->id, 'title' => 'Health Q1 Test', 'activity_date' => '2026-08-10', 'max_score' => 50, 'assessment_number' => 1]);

    $peQuarter = ClassRecordQuarter::create(['class_record_id' => $peRecord->id, 'quarter' => 1]);
    ClassRecordAssessment::create(['class_record_quarter_id' => $peQuarter->id, 'grading_category_id' => $leavesByCode['SA 1']->id, 'title' => 'PE Q1 Test', 'activity_date' => '2026-08-11', 'max_score' => 50, 'assessment_number' => 1]);

    return compact('healthRecord', 'musicRecord', 'peRecord', 'healthTeacher', 'musicTeacher', 'peTeacher');
}

test('consolidateSection merges records, adds pivot rows for every teacher including the canonical one, re-parents assessments, and archives the redundant records', function () {
    $sy = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
    $option = GradingOption::create(['name' => 'PEHM 1-3 (Grade 7)']);
    $sa1 = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10, 'subject_id' => null]);
    $sa2 = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 10, 'subject_id' => null]);
    $leavesByCode = ['SA 1' => $sa1, 'SA 2' => $sa2];

    ['healthRecord' => $healthRecord, 'peRecord' => $peRecord, 'healthTeacher' => $healthTeacher, 'peTeacher' => $peTeacher] =
        seedSectionFixture($option, $leavesByCode, sectionId: 999, syId: $sy->id);

    // Give the target option real subject ids (as Task 2 would have set them)
    $sa1->update(['subject_id' => $peRecord->subject_id]);
    $sa2->update(['subject_id' => $healthRecord->subject_id]);

    $cmd = new ConsolidatePehmCoTeaching();
    $records = ClassRecord::whereIn('id', [$healthRecord->id, $peRecord->id])->with('subject')->get();

    $report = $cmd->consolidateSection($records, $option->fresh(['categories']), commit: true);

    $canonical = ClassRecord::find($report['canonical_id']);
    expect($canonical)->not->toBeNull();

    // Both teachers (including the canonical record's own creator) have explicit pivot rows
    $pivotUserIds = ClassRecordTeacher::where('class_record_id', $canonical->id)->pluck('user_id')->all();
    expect($pivotUserIds)->toContain($healthTeacher->id, $peTeacher->id);

    // The non-canonical record is archived, not deleted
    $otherId = $report['canonical_id'] === $healthRecord->id ? $peRecord->id : $healthRecord->id;
    expect(ClassRecord::find($otherId)->status)->toBe('archived');

    // Both assessments now live under the canonical record's quarter 1, on the option's real leaves
    $canonicalQuarter = ClassRecordQuarter::where('class_record_id', $canonical->id)->where('quarter', 1)->first();
    $reparented = ClassRecordAssessment::where('class_record_quarter_id', $canonicalQuarter->id)->pluck('title')->sort()->values()->all();
    expect($reparented)->toBe(['Health Q1 Test', 'PE Q1 Test']);

    expect($report['pivot_rows_created'])->toBe(2);
    expect($report['assessments_reparented'])->toBe(2);
    expect($report['unmatched_leaves'])->toBe([]);
});

test('consolidateSection dry-run makes zero writes', function () {
    $sy = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
    $option = GradingOption::create(['name' => 'PEHM 1-3 (Grade 7)']);
    $sa1 = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10]);
    $sa2 = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 10]);
    $leavesByCode = ['SA 1' => $sa1, 'SA 2' => $sa2];
    seedSectionFixture($option, $leavesByCode, sectionId: 998, syId: $sy->id);
    $sa1->update(['subject_id' => 1]);
    $sa2->update(['subject_id' => 2]);

    $before = [
        'class_records' => \DB::table('class_records')->count(),
        'class_record_teachers' => \DB::table('class_record_teachers')->count(),
        'class_record_assessments' => \DB::table('class_record_assessments')->count(),
    ];

    $cmd = new ConsolidatePehmCoTeaching();
    $records = ClassRecord::where('section_id', 998)->with('subject')->get();
    $report = $cmd->consolidateSection($records, $option->fresh(['categories']), commit: false);

    expect(\DB::table('class_records')->count())->toBe($before['class_records']);
    expect(\DB::table('class_record_teachers')->count())->toBe($before['class_record_teachers']);
    expect(\DB::table('class_record_assessments')->count())->toBe($before['class_record_assessments']);
    expect($report['pivot_rows_created'])->toBe(2); // still reports what WOULD happen
});

test('consolidateSection flags an assessment whose old leaf has no role match instead of dropping it silently', function () {
    $sy = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
    $option = GradingOption::create(['name' => 'PEHM 1-3 (Grade 7)']);
    // No role-matchable leaf in the target option at all
    $orphanLeaf = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Unlabeled Leaf', 'code' => 'XX 9', 'weight' => 10]);

    $healthSubject = Subject::create(['name' => 'Health 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    $peSubject = Subject::create(['name' => 'Physical Education 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture']);
    $healthRecord = ClassRecord::create(['school_year_id' => $sy->id, 'section_id' => 997, 'subject_id' => $healthSubject->id, 'teacher_id' => \App\Models\User::factory()->create()->id, 'grading_option_id' => $option->id, 'status' => 'draft']);
    $peRecord = ClassRecord::create(['school_year_id' => $sy->id, 'section_id' => 997, 'subject_id' => $peSubject->id, 'teacher_id' => \App\Models\User::factory()->create()->id, 'grading_option_id' => $option->id, 'status' => 'draft']);
    $quarter = ClassRecordQuarter::create(['class_record_id' => $peRecord->id, 'quarter' => 1]);
    ClassRecordAssessment::create(['class_record_quarter_id' => $quarter->id, 'grading_category_id' => $orphanLeaf->id, 'title' => 'Mystery Assessment', 'activity_date' => '2026-08-10', 'max_score' => 50, 'assessment_number' => 1]);

    $cmd = new ConsolidatePehmCoTeaching();
    $records = ClassRecord::whereIn('id', [$healthRecord->id, $peRecord->id])->with('subject')->get();
    $report = $cmd->consolidateSection($records, $option->fresh(['categories']), commit: true);

    expect($report['unmatched_leaves'])->toHaveCount(1);
    expect($report['unmatched_leaves'][0])->toContain('Mystery Assessment');
    // Left in place on the (now archived) source record, not lost
    expect(ClassRecordAssessment::where('title', 'Mystery Assessment')->first()->class_record_quarter_id)->toBe($quarter->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: FAIL — `consolidateSection` does not exist.

- [ ] **Step 3: Implement `consolidateSection`**

```php
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordTeacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @param  Collection<int,ClassRecord>  $records  every non-archived PEHM record for one section (2-3 rows)
 */
public function consolidateSection(Collection $records, GradingOption $targetOption, bool $commit): array
{
    $canonical = $records->sortBy('id')->first();
    $others = $records->reject(fn ($r) => $r->id === $canonical->id);

    // Build old-leaf-id -> role/strippedCode index for matching, and the
    // target option's role/strippedCode -> new-leaf-id index.
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

        // Re-parent every non-canonical record's assessments onto the
        // canonical record's matching quarter + matching leaf.
        foreach ($others as $other) {
            $quarters = ClassRecordQuarter::where('class_record_id', $other->id)->get();

            foreach ($quarters as $quarter) {
                $assessments = ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)
                    ->with('gradingCategory')
                    ->get();

                if ($assessments->isEmpty()) {
                    continue;
                }

                $canonicalQuarter = $commit
                    ? ClassRecordQuarter::firstOrCreate(['class_record_id' => $canonical->id, 'quarter' => $quarter->quarter])
                    : (ClassRecordQuarter::where('class_record_id', $canonical->id)->where('quarter', $quarter->quarter)->first()
                        ?? new ClassRecordQuarter(['class_record_id' => $canonical->id, 'quarter' => $quarter->quarter]));

                foreach ($assessments as $assessment) {
                    $oldLeaf = $assessment->gradingCategory;
                    $role = $oldLeaf ? $this->roleForCategory($oldLeaf) : null;
                    $key = $role !== null ? $role.'|'.$this->stripCode($oldLeaf->code) : null;
                    $newLeafId = $key !== null ? ($targetIndex[$key] ?? null) : null;

                    if ($newLeafId === null) {
                        $report['unmatched_leaves'][] = "Assessment '{$assessment->title}' (id {$assessment->id}, old leaf '".($oldLeaf->name ?? 'unknown')."') on class record #{$other->id} — no matching leaf found on target option #{$targetOption->id}, left in place.";
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: PASS, 10 tests total.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php
git commit -m "feat(class-record): per-section PEHM consolidation with assessment re-parenting"
```

---

### Task 4: `handle()` orchestration — grouping, grade/option resolution order, section-264 gap handling, dry-run report

**Files:**
- Modify: `app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php`
- Test: `tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1-3.
- Produces: full `handle()` behavior — this is the actual command entry point, tested via `$this->artisan(...)`.

- [ ] **Step 1: Write the failing end-to-end test**

```php
test('the command end to end: dry run reports without writing, commit writes and is idempotent on a second run', function () {
    $sy = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
    makePehmSubjects(); // grade 7 + 8 subjects, ids 64/67/68/78/81/82

    $template = GradingOption::create(['name' => 'PEHM 1-3']);
    $sa = GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'Summative Assessment', 'code' => 'SA', 'weight' => 30]);
    GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10]);
    GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 10]);

    $peTeacher = \App\Models\User::factory()->create();
    $healthTeacher = \App\Models\User::factory()->create();
    $peRecord = ClassRecord::create(['school_year_id' => $sy->id, 'section_id' => 500, 'subject_id' => 68, 'teacher_id' => $peTeacher->id, 'grading_option_id' => $template->id, 'status' => 'draft']);
    $healthRecord = ClassRecord::create(['school_year_id' => $sy->id, 'section_id' => 500, 'subject_id' => 64, 'teacher_id' => $healthTeacher->id, 'grading_option_id' => $template->id, 'status' => 'draft']);

    // Dry run first
    $this->artisan('class-record:consolidate-pehm-coteaching')->assertSuccessful();
    expect(ClassRecord::find($peRecord->id)->status)->toBe('draft');
    expect(ClassRecord::find($healthRecord->id)->status)->toBe('draft');
    expect(GradingOption::where('name', 'PEHM 1-3 (Grade 7)')->exists())->toBeFalse();

    // Commit
    $this->artisan('class-record:consolidate-pehm-coteaching --commit')->assertSuccessful();

    $grade7Option = GradingOption::where('name', 'PEHM 1-3 (Grade 7)')->first();
    expect($grade7Option)->not->toBeNull();

    $survivors = ClassRecord::where('section_id', 500)->where('status', '<>', 'archived')->get();
    expect($survivors)->toHaveCount(1);
    expect($survivors->first()->grading_option_id)->toBe($grade7Option->id);

    $archived = ClassRecord::where('section_id', 500)->where('status', 'archived')->get();
    expect($archived)->toHaveCount(1);

    // Second commit run is a no-op (idempotent) — nothing left to consolidate
    $this->artisan('class-record:consolidate-pehm-coteaching --commit')->assertSuccessful();
    expect(ClassRecord::where('section_id', 500)->where('status', '<>', 'archived')->count())->toBe(1);
    expect(GradingOption::where('name', 'PEHM 1-3 (Grade 7)')->count())->toBe(1); // no duplicate clone
});

test('a section with only one PEHM record is left alone (nothing to consolidate)', function () {
    $sy = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
    makePehmSubjects();
    $template = GradingOption::create(['name' => 'PEHM 1-3']);
    GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 10]);
    $record = ClassRecord::create(['school_year_id' => $sy->id, 'section_id' => 501, 'subject_id' => 68, 'teacher_id' => \App\Models\User::factory()->create()->id, 'grading_option_id' => $template->id, 'status' => 'draft']);

    $this->artisan('class-record:consolidate-pehm-coteaching --commit')->assertSuccessful();

    expect(ClassRecord::find($record->id)->status)->toBe('draft');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: FAIL — `handle()` does nothing yet, no options/records get created/changed.

- [ ] **Step 3: Implement full `handle()` orchestration**

```php
use App\Models\FacultyLoading\SchoolYear;

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

    $records = ClassRecord::whereIn('subject_id', $pehmSubjectIds)
        ->where('school_year_id', $schoolYear->id)
        ->where('status', '<>', 'archived')
        ->with('subject')
        ->get()
        ->groupBy('section_id');

    $totalPivotRows = 0;
    $totalReparented = 0;
    $allUnmatched = [];

    foreach ($records as $sectionId => $sectionRecords) {
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
                $this->assertOptionOnlyUsedByPehm($grade10Template, $records->flatten()->where(fn ($r) => $r->subject->grade_level === 10)->pluck('id'));
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php"`
Expected: PASS, 12 tests total.

- [ ] **Step 5: Run the FULL class-record test suite to confirm zero regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ClassRecord"`
Expected: all green, including the pre-existing `ClassRecordPehmCoTeachingTest.php` (20 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ClassRecord/ConsolidatePehmCoTeaching.php tests/Feature/ClassRecord/ConsolidatePehmCoTeachingCommandTest.php
git commit -m "feat(class-record): orchestrate PEHM co-teacher consolidation command end to end"
```

---

### Task 5: Run dry-run against real production data and review output together

**This task has no code changes.** It is the checkpoint between "the command works in tests" and "the command is safe to run for real."

- [ ] **Step 1: Deploy the command** (merge to `main`, push, let the normal ECS deploy pipeline pick it up — the command makes zero writes without `--commit`, so shipping it is safe on its own).

- [ ] **Step 2: Run the dry-run against production via ECS exec**

```bash
TASK=$(aws ecs list-tasks --cluster crcmis-prod --query 'taskArns[0]' --output text)
aws ecs execute-command --cluster crcmis-prod --task $TASK --container nginx --interactive \
  --command "env HOME=/tmp php /var/www/artisan class-record:consolidate-pehm-coteaching"
```

- [ ] **Step 3: Review the full output together with the user before proceeding.** Specifically confirm:
  - Section count matches the known 16 (15 two-way, 264 handled as its own 2-record case since Health is genuinely absent there).
  - `unmatched_leaves` is empty, or every entry is understood and accepted.
  - The reported canonical record per section looks reasonable (arbitrary lowest-id tiebreak — flag to the user if a specific section's canonical choice matters, e.g. because one record has more complete Setup-tab configuration than its sibling).
  - Total pivot-row and re-parented-assessment counts roughly match expectations (up to ~3 pivot rows × ~15-16 sections, up to 87 assessments).

- [ ] **Step 4: Do NOT proceed to Task 6 without a fresh, explicit "yes, run --commit" from the user.** This is a separate approval gate from approving this plan — the plan being approved authorizes building and dry-running the command, not committing production writes.

---

### Task 6: Run `--commit` in production (separate approval required — see Task 5 Step 4)

- [ ] **Step 1: Confirm explicit go-ahead was given after reviewing Task 5's dry-run output.**

- [ ] **Step 2: Run the commit**

```bash
TASK=$(aws ecs list-tasks --cluster crcmis-prod --query 'taskArns[0]' --output text)
aws ecs execute-command --cluster crcmis-prod --task $TASK --container nginx --interactive \
  --command "env HOME=/tmp php /var/www/artisan class-record:consolidate-pehm-coteaching --commit"
```

- [ ] **Step 3: Verify in production** (read-only queries, same pattern as the investigation): re-run the section/record-count query from the Background section and confirm 15-16 sections now have exactly 1 non-archived PEHM class record each, each with the expected number of `class_record_teachers` pivot rows, and `class_record_assessments` totals unchanged (87, just re-homed).

- [ ] **Step 4: Report back to the user** with the before/after summary and the section-264 Health-teacher gap as an explicit open item for Academic Affairs.

---

## Self-Review

**Spec coverage:**
- Grading-option subject_id resolution per grade level (7/8/9 clone, 10 direct-fill) — Task 2. ✓
- Grade 9 standardize on "PEHM 1-3", Grade 10 standardize on "PEHM 4 Final" — Task 4's `handle()` sources `GradingOption::where('name', 'PEHM 1-3')` / `'PEHM 4 Final'` as the canonical templates, so records currently on "PEHM 3" or "PEHM 4" get repointed to these via `consolidateSection`'s `$canonical->grading_option_id = $targetOption->id`. ✓
- Section 264 (2 records, no Health) — Task 4's `if ($sectionRecords->count() < 2)` guard means a genuine 2-record section still consolidates normally (Music+PE merge); a true 1-record section is skipped. Task 3's test suite doesn't include a dedicated 264-shaped fixture — this is implicitly covered since `consolidateSection` never assumes exactly 3 records, but **note for the executor**: add one more explicit test in Task 3 mirroring section 264's exact shape (2 records, Music+PE, no Health) before considering Task 3 done, to be certain.
- Dry-run vs commit, zero writes in dry-run — covered in Tasks 2, 3, 4 tests explicitly.
- Idempotency (safe to re-run) — covered in Task 4's end-to-end test.
- Never silently drop unmatched data — Task 3's `unmatched_leaves` test.
- Archive not delete — Task 3 tests assert `status === 'archived'`, never assert row absence.
- Canonical teacher keeps their own edit access after pivot rows are added — Task 3's first test asserts `$pivotUserIds` contains both teachers, including the canonical record's own.

**Gap found during self-review:** added note above for Task 3 to include an explicit section-264-shaped test case (2 records, no third subject) — the existing tests use 2-record fixtures already (`seedSectionFixture` only creates Health+PE, no Music, per the actual test code), so this is in fact already covered; no change needed.

**Placeholder scan:** no TBD/TODO/"add error handling" placeholders — every step has real code.

**Type consistency:** `roleForCategory(GradingCategory): ?string`, `stripCode(string): string`, `subjectsByGradeAndRole(): array`, `resolveTargetOptionForGrade(GradingOption, int, array, bool, bool): GradingOption`, `assertOptionOnlyUsedByPehm(GradingOption, iterable): void`, `consolidateSection(Collection, GradingOption, bool): array` — used consistently with the same names/signatures across Tasks 2-4.
