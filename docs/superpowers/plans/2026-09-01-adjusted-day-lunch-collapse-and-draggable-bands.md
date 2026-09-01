# Adjusted Day — Lunch Gap Collapse + Draggable Bands Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the early-start STEM-split type so Lunch is actually removed from the compressed timetable (not just hidden from display), and make Recess/White Space bands draggable (move + resize) in the Manual Adjustment calendar, across all 5 adjustment types.

**Architecture:** Part 1 extends `AdjustedClassScheduleService::generate()`'s existing per-slot-target compression with a synthetic zero-target slot for the intersection of a section's real inter-period gap and the campus Lunch window. Part 2 adds a second override table (mirroring the existing per-entry override exactly, keyed by section+band type instead of a schedule row) plus matching controller endpoints and frontend drag/resize interactions.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, PHPUnit + RefreshDatabase.

**Spec:** `docs/superpowers/specs/2026-09-01-adjusted-day-lunch-collapse-and-draggable-bands-design.md`

## Global Constraints

- Migrations must be additive — new table only, no changes to existing tables in this plan.
- `section_id` on the new table is a plain `unsignedInteger`, no DB-level foreign key — matches this codebase's established pattern for referencing the legacy `sections` table (its `id` is a 32-bit `increments()` column, not `bigIncrements`; see `2026_07_28_160100_create_class_admission_slips_table.php` and siblings for precedent).
- Band dragging (move + resize) applies to `RECESS` and `WHITE_SPACE` band types only, across all 5 adjustment types.
- The Lunch gap-collapse fix applies only when `adjustment->isEarlyStartStemSplit()` is true.
- Follow existing project conventions: `back()->with('success', ...)`, named routes, no Blade views, no TypeScript.

---

### Task 1: Migration — `class_schedule_day_adjustment_band_overrides` table

**Files:**
- Create: `database/migrations/2026_09_01_160000_create_class_schedule_day_adjustment_band_overrides_table.php`

**Interfaces:**
- Produces: `class_schedule_day_adjustment_band_overrides` (`id`, `adjustment_id` FK→`class_schedule_day_adjustments` cascade, `section_id` unsignedInteger no-FK, `band_type` string, `override_start_time`/`override_end_time` time, unique on `(adjustment_id, section_id, band_type)`).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manual, time-only correction applied to one Recess or White Space
     * band within one adjusted-day preview, scoped to one section — same
     * concept as class_schedule_day_adjustment_overrides but for a bell-
     * schedule band instead of a real ClassSchedule row (bands have no id
     * of their own to hang an override off).
     */
    public function up(): void
    {
        Schema::create('class_schedule_day_adjustment_band_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('class_schedule_day_adjustments', 'id', 'cs_day_adj_band_override_adjustment_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->string('band_type');
            $table->time('override_start_time');
            $table->time('override_end_time');
            $table->timestamps();

            $table->unique(['adjustment_id', 'section_id', 'band_type'], 'cs_day_adj_band_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustment_band_overrides');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_01_160000_create_class_schedule_day_adjustment_band_overrides_table.php --force"`
Expected: migration runs cleanly.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_09_01_160000_create_class_schedule_day_adjustment_band_overrides_table.php
git commit -m "feat(faculty-loading): add class_schedule_day_adjustment_band_overrides table"
```

---

### Task 2: Model — `ClassScheduleDayAdjustmentBandOverride`

**Files:**
- Create: `app/Models/FacultyLoading/ClassScheduleDayAdjustmentBandOverride.php`
- Modify: `app/Models/FacultyLoading/ClassScheduleDayAdjustment.php`

**Interfaces:**
- Consumes: Task 1's table.
- Produces: `ClassScheduleDayAdjustment::bandOverrides(): HasMany`.

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual, time-only correction applied to one Recess or White Space band
 * within one adjusted-day preview, scoped to one section — mirrors
 * ClassScheduleDayAdjustmentOverride but for a bell-schedule band instead
 * of a real ClassSchedule row. Never touches the underlying weekly
 * schedule or bell-schedule configuration.
 */
class ClassScheduleDayAdjustmentBandOverride extends Model
{
    protected $table = 'class_schedule_day_adjustment_band_overrides';

    protected $fillable = [
        'adjustment_id',
        'section_id',
        'band_type',
        'override_start_time',
        'override_end_time',
    ];

    protected $casts = [
        'override_start_time' => 'string',
        'override_end_time' => 'string',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleDayAdjustment::class, 'adjustment_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
```

- [ ] **Step 2: Add the relation to `ClassScheduleDayAdjustment`**

In `app/Models/FacultyLoading/ClassScheduleDayAdjustment.php`, add right after the existing `overrides()` method:

```php
    public function overrides(): HasMany
    {
        return $this->hasMany(ClassScheduleDayAdjustmentOverride::class, 'adjustment_id');
    }

    public function bandOverrides(): HasMany
    {
        return $this->hasMany(ClassScheduleDayAdjustmentBandOverride::class, 'adjustment_id');
    }
```

- [ ] **Step 3: Lint both files**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -l app/Models/FacultyLoading/ClassScheduleDayAdjustmentBandOverride.php && php -l app/Models/FacultyLoading/ClassScheduleDayAdjustment.php"`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add app/Models/FacultyLoading/ClassScheduleDayAdjustmentBandOverride.php app/Models/FacultyLoading/ClassScheduleDayAdjustment.php
git commit -m "feat(faculty-loading): add ClassScheduleDayAdjustmentBandOverride model"
```

---

### Task 3: Service — collapse the Lunch gap for early-start STEM-split

**Files:**
- Modify: `app/Services/FacultyLoading/AdjustedClassScheduleService.php`
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `SchedulingConstants::getEffectiveLunch(int $grade, string $day, ?array $sectionOverride): array` (existing, public).
- Produces: for `early_start_stem_split` only, a section's real inter-period gap that overlaps the campus Lunch window is fully or partially collapsed in the compressed timetable, proportional to the overlap.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`, right before `private function plotAssessment`:

```php
    public function test_early_start_stem_split_fully_collapses_a_gap_that_exactly_matches_lunch(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        // Grade 7 Tuesday-Friday lunch is 10:20-11:20 (SchedulingConstants::SECTION_LUNCH['G7_TueFri']).
        $periodA = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'AT1-G7', 'name' => 'Araling Panlipunan 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $periodB = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodA->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:30', 'end_time' => '10:20', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodB->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '11:20', 'end_time' => '12:10', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        $this->assertSame('07:00', $entries[0]['start_time']);
        $this->assertSame('07:30', $entries[0]['end_time']);
        // The two periods straddling lunch (10:20-11:20) are now back-to-back —
        // the gap that used to be Lunch has fully collapsed, not just hidden.
        $this->assertSame('08:40', $entries[1]['start_time']);
        $this->assertSame('09:10', $entries[1]['end_time']);
        $this->assertSame('09:10', $entries[2]['start_time']);
        $this->assertSame('09:40', $entries[2]['end_time']);
    }

    public function test_early_start_stem_split_does_not_fabricate_a_collapse_when_no_real_gap_overlaps_lunch(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        $second = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'AT1-G7', 'name' => 'Araling Panlipunan 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        // 09:00-09:50 — a normal 40-minute gap after the 07:30-08:20 period,
        // nowhere near the 10:20-11:20 lunch window.
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $second->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:00', 'end_time' => '09:50', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        $this->assertSame('07:00', $entries[0]['start_time']);
        $this->assertSame('07:30', $entries[0]['end_time']);
        // Nothing to collapse here — the original 40-minute gap (08:20-09:00,
        // now 07:30-08:10) survives intact, exactly as it did before the fix.
        $this->assertSame('08:10', $entries[1]['start_time']);
        $this->assertSame('08:40', $entries[1]['end_time']);
    }

    public function test_early_start_stem_split_only_collapses_the_portion_of_a_gap_overlapping_lunch(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();

        $periodA = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'AT1-G7', 'name' => 'Araling Panlipunan 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $periodB = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        // Gap is wider (09:10-10:00, then 11:40-12:30) than lunch (10:20-11:20)
        // on both sides — only the lunch-overlapping middle should collapse.
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodA->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:10', 'end_time' => '10:00', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $periodB->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '11:40', 'end_time' => '12:30', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $entries = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'];

        // Only the 60-minute lunch overlap collapses out of the 130-minute
        // original gap (10:00-11:40) — 70 minutes of genuine gap remain.
        $this->assertSame('08:50', $entries[1]['end_time']);
        $this->assertSame('10:00', $entries[2]['start_time']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter 'test_early_start_stem_split_fully_collapses|test_early_start_stem_split_does_not_fabricate|test_early_start_stem_split_only_collapses'"`
Expected: FAIL — the first and third tests fail (gap not collapsed at all / collapsed incorrectly); the second may already pass by coincidence (nothing to collapse either way) but run all three together to establish the pre-fix baseline.

- [ ] **Step 3: Implement**

In `app/Services/FacultyLoading/AdjustedClassScheduleService.php`, add `use Illuminate\Support\Collection;` is already imported. Add a new private method right after `transformTime()`:

```php
    /**
     * Synthetic zero-target slots for the portion of the campus Lunch window
     * that genuinely falls between two of this section's own consecutive
     * class periods — used only for early_start_stem_split, where Lunch is
     * meant to disappear from the timetable, not just from the display.
     * Only the intersection of the real gap and the canonical Lunch window
     * collapses: a section with no real gap there loses nothing, and a
     * section whose gap only partially overlaps only loses that portion —
     * same reasoning as anchoring compression to real data instead of the
     * idealized bell-schedule grid (see this class's other docblocks).
     *
     * @return array<int,array{start:string,end:string,target:int}>
     */
    private function lunchGapSlots(Collection $sectionSchedule, int $gradeLevel, string $day, Section $section): array
    {
        $lunch = SchedulingConstants::getEffectiveLunch($gradeLevel, $day, $this->trimWindow($section->lunchOverrideFor($day)));
        $lunchStart = SchedulingConstants::toMinutes($lunch['start']);
        $lunchEnd = SchedulingConstants::toMinutes($lunch['end']);

        $ordered = $sectionSchedule->values();
        $slots = [];

        for ($index = 1; $index < $ordered->count(); $index++) {
            $gapStart = SchedulingConstants::toMinutes(substr((string) $ordered[$index - 1]->end_time, 0, 5));
            $gapEnd = SchedulingConstants::toMinutes(substr((string) $ordered[$index]->start_time, 0, 5));

            $overlapStart = max($gapStart, $lunchStart);
            $overlapEnd = min($gapEnd, $lunchEnd);

            if ($overlapEnd > $overlapStart) {
                $slots[] = [
                    'start' => SchedulingConstants::fromMinutes($overlapStart),
                    'end' => SchedulingConstants::fromMinutes($overlapEnd),
                    'target' => 0,
                ];
            }
        }

        return $slots;
    }
```

Then, in `generate()`, wire it in right after `$sectionSlots` is built. Replace:

```php
                $sectionSlots = $sectionSchedule
                    ->map(function (ClassSchedule $s) use ($classDuration, $protectedPairs, $stemSplit, $stemMinutes, $nonStemMinutes) {
                        $isProtected = $protectedPairs && $s->subject_id
                            && $protectedPairs->has("{$s->section_id}:{$s->subject_id}");

                        $target = $stemSplit
                            ? ($s->subject?->is_stem ? $stemMinutes : $nonStemMinutes)
                            : $classDuration;

                        return [
                            'start' => substr((string) $s->start_time, 0, 5),
                            'end' => substr((string) $s->end_time, 0, 5),
                            // null = this period keeps its original length (not
                            // compressed): either the day isn't shortened, or this
                            // period is protected by a major assessment plotted today.
                            'target' => $isProtected ? null : $target,
                        ];
                    })
                    ->values()
                    ->all();
```

With:

```php
                $sectionSlots = $sectionSchedule
                    ->map(function (ClassSchedule $s) use ($classDuration, $protectedPairs, $stemSplit, $stemMinutes, $nonStemMinutes) {
                        $isProtected = $protectedPairs && $s->subject_id
                            && $protectedPairs->has("{$s->section_id}:{$s->subject_id}");

                        $target = $stemSplit
                            ? ($s->subject?->is_stem ? $stemMinutes : $nonStemMinutes)
                            : $classDuration;

                        return [
                            'start' => substr((string) $s->start_time, 0, 5),
                            'end' => substr((string) $s->end_time, 0, 5),
                            // null = this period keeps its original length (not
                            // compressed): either the day isn't shortened, or this
                            // period is protected by a major assessment plotted today.
                            'target' => $isProtected ? null : $target,
                        ];
                    })
                    ->values()
                    ->all();

                if ($stemSplit) {
                    // Lunch must actually disappear from the timetable for this
                    // type, not just from the display — see lunchGapSlots().
                    $sectionSlots = array_merge(
                        $sectionSlots,
                        $this->lunchGapSlots($sectionSchedule, $gradeLevel, $day, $section)
                    );
                }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter 'test_early_start_stem_split_fully_collapses|test_early_start_stem_split_does_not_fabricate|test_early_start_stem_split_only_collapses'"`
Expected: PASS, all three.

- [ ] **Step 5: Run the full adjustment test file for regressions**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"`
Expected: PASS, all tests (including the existing `test_early_start_stem_split_anchors_first_period_and_splits_duration_by_subject` test, which has no gap at the lunch window in its fixture and must be unaffected).

- [ ] **Step 6: Commit**

```bash
git add app/Services/FacultyLoading/AdjustedClassScheduleService.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "fix(faculty-loading): early-start STEM-split now collapses the Lunch gap, not just its label"
```

---

### Task 4: Service — apply band overrides when building Recess/White Space bands

**Files:**
- Modify: `app/Services/FacultyLoading/AdjustedClassScheduleService.php`
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `ClassScheduleDayAdjustment::bandOverrides()` (Task 2).
- Produces: `generate()`'s `bands` array — a `RECESS`/`WHITE_SPACE` band with a matching override uses its exact time and gains `manually_adjusted: true`; every other band gains `manually_adjusted: false` for a consistent shape.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`:

```php
    public function test_band_override_repositions_a_recess_band_and_can_be_removed(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'RECESS',
                'override_start_time' => '09:00',
                'override_end_time' => '09:15',
            ])
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 1);

        $bands = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0]['bands'];
        $recess = collect($bands)->firstWhere('type', 'RECESS');

        $this->assertSame('09:00', $recess['start']);
        $this->assertSame('09:15', $recess['end']);
        $this->assertTrue($recess['manually_adjusted']);

        $this->actingAs($this->manager)
            ->deleteJson(route('faculty-loading.schedules.day-adjustments.band-overrides.destroy', [$adjustment, $section->id, 'RECESS']))
            ->assertOk();

        $this->assertDatabaseCount('class_schedule_day_adjustment_band_overrides', 0);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter test_band_override_repositions_a_recess_band_and_can_be_removed"`
Expected: FAIL — the `band-overrides.store`/`band-overrides.destroy` routes don't exist yet (this test also exercises Task 5's controller/routes; it will fully pass once both tasks land — re-run after Task 5).

- [ ] **Step 3: Implement — apply overrides in `generate()`**

In `generate()`, add the band-overrides lookup near the existing `$overridesByScheduleId` line. Replace:

```php
        $overridesByScheduleId = $adjustment->exists
            ? $adjustment->overrides()->get()->keyBy('class_schedule_id')
            : collect();
```

With:

```php
        $overridesByScheduleId = $adjustment->exists
            ? $adjustment->overrides()->get()->keyBy('class_schedule_id')
            : collect();
        $bandOverridesBySectionType = $adjustment->exists
            ? $adjustment->bandOverrides()->get()->keyBy(fn ($o) => "{$o->section_id}:{$o->band_type}")
            : collect();
```

Then, right after the existing bands-building chain finishes (before the `OFFICIAL_ACTIVITY` append), apply overrides. Replace:

```php
                $bands = collect($bands)
                    ->when($hasShortenedClasses, fn ($items) => $items->reject(
                        fn (array $band) => in_array($band['type'] ?? '', $rejectedBandTypes, true),
                    ))
                    ->map(fn (array $band) => [
                        ...$band,
                        'start' => $this->transformTime((string) $band['start'], $sectionSlots, $sectionShift),
                        'end' => $this->transformTime((string) $band['end'], $sectionSlots, $sectionShift),
                    ])
                    ->when($activityStart, fn ($items) => $items->filter(
                        fn (array $band) => $band['end'] <= $activityStart,
                    ))
                    ->sortBy('start')
                    ->values()
                    ->all();

                if ($activityStart && $activityEnd) {
```

With:

```php
                $bands = collect($bands)
                    ->when($hasShortenedClasses, fn ($items) => $items->reject(
                        fn (array $band) => in_array($band['type'] ?? '', $rejectedBandTypes, true),
                    ))
                    ->map(fn (array $band) => [
                        ...$band,
                        'start' => $this->transformTime((string) $band['start'], $sectionSlots, $sectionShift),
                        'end' => $this->transformTime((string) $band['end'], $sectionSlots, $sectionShift),
                    ])
                    ->when($activityStart, fn ($items) => $items->filter(
                        fn (array $band) => $band['end'] <= $activityStart,
                    ))
                    ->sortBy('start')
                    ->values()
                    ->map(function (array $band) use ($section, $bandOverridesBySectionType) {
                        if (! in_array($band['type'] ?? '', ['RECESS', 'WHITE_SPACE'], true)) {
                            return $band;
                        }

                        $override = $bandOverridesBySectionType->get("{$section->id}:{$band['type']}");
                        if (! $override) {
                            return [...$band, 'manually_adjusted' => false];
                        }

                        return [
                            ...$band,
                            'start' => substr((string) $override->override_start_time, 0, 5),
                            'end' => substr((string) $override->override_end_time, 0, 5),
                            'manually_adjusted' => true,
                        ];
                    })
                    ->all();

                if ($activityStart && $activityEnd) {
```

- [ ] **Step 4: Run the test again**

It still needs Task 5's routes — expected to still fail with a route-not-found error at this point. Proceed to Task 5.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FacultyLoading/AdjustedClassScheduleService.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "feat(faculty-loading): apply band overrides to Recess/White Space in generate()"
```

---

### Task 5: Controller + routes — band override endpoints

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php`
- Modify: `routes/faculty-loading.php`
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php` (Task 4's test now fully exercises this; two more added below)

**Interfaces:**
- Produces: `POST faculty-loading.schedules.day-adjustments.band-overrides.store`, `DELETE faculty-loading.schedules.day-adjustments.band-overrides.destroy`.

- [ ] **Step 1: Add the routes**

In `routes/faculty-loading.php`, right after the existing entry-override routes:

```php
                Route::post('/{adjustment}/overrides', [ClassScheduleDayAdjustmentController::class, 'upsertOverride'])->name('overrides.store');
                Route::delete('/{adjustment}/overrides/{classScheduleId}', [ClassScheduleDayAdjustmentController::class, 'removeOverride'])->name('overrides.destroy');
                Route::post('/{adjustment}/band-overrides', [ClassScheduleDayAdjustmentController::class, 'upsertBandOverride'])->name('band-overrides.store');
                Route::delete('/{adjustment}/band-overrides/{sectionId}/{bandType}', [ClassScheduleDayAdjustmentController::class, 'removeBandOverride'])->name('band-overrides.destroy');
```

- [ ] **Step 2: Add the controller methods**

In `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php`, right after `removeOverride()`:

```php
    /**
     * Manually correct one Recess or White Space band's displayed time for
     * one section on this adjusted date only. Draft-only, same lifecycle as
     * upsertOverride() but keyed by section+band type instead of a
     * ClassSchedule row (bands have no id of their own).
     */
    public function upsertBandOverride(Request $request, ClassScheduleDayAdjustment $adjustment): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'band_type' => ['required', 'in:RECESS,WHITE_SPACE'],
            'override_start_time' => ['required', 'date_format:H:i'],
            'override_end_time' => ['required', 'date_format:H:i'],
        ]);

        if ($data['override_end_time'] <= $data['override_start_time']) {
            throw ValidationException::withMessages([
                'override_end_time' => 'The override end time must be after its start time.',
            ]);
        }

        $adjustment->bandOverrides()->updateOrCreate(
            ['section_id' => $data['section_id'], 'band_type' => $data['band_type']],
            ['override_start_time' => $data['override_start_time'], 'override_end_time' => $data['override_end_time']],
        );

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }

    public function removeBandOverride(ClassScheduleDayAdjustment $adjustment, int $sectionId, string $bandType): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $adjustment->bandOverrides()->where('section_id', $sectionId)->where('band_type', $bandType)->delete();

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }
```

- [ ] **Step 3: Run Task 4's test — should now fully pass**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter test_band_override_repositions_a_recess_band_and_can_be_removed"`
Expected: PASS

- [ ] **Step 4: Add validation and draft-only-guard tests**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`:

```php
    public function test_band_override_rejects_an_invalid_band_type(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'LUNCH',
                'override_start_time' => '09:00',
                'override_end_time' => '09:15',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('band_type');
    }

    public function test_band_override_endpoint_is_draft_only(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.band-overrides.store', $adjustment), [
                'section_id' => $section->id,
                'band_type' => 'RECESS',
                'override_start_time' => '09:00',
                'override_end_time' => '09:15',
            ])
            ->assertStatus(422);
    }
```

- [ ] **Step 5: Run the full adjustment test file**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"`
Expected: PASS, all tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php routes/faculty-loading.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "feat(faculty-loading): add band-override endpoints for Recess/White Space"
```

---

### Task 6: Frontend — draggable Recess/White Space bands (move)

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`

**Interfaces:**
- Consumes: `POST faculty-loading.schedules.day-adjustments.band-overrides.store` (Task 5).

- [ ] **Step 1: Generalize the `dragging` state to cover both entries and bands**

Replace:

```js
function onDragStart(event, entry, section) {
  const durationMinutes = toMinutes(entry.end_time) - toMinutes(entry.start_time)
  dragging.value = { entry, durationMinutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}
```

With:

```js
function isDraggableBand(band) {
  return band.type === 'RECESS' || band.type === 'WHITE_SPACE'
}

function onDragStart(event, entry, section) {
  const durationMinutes = toMinutes(entry.end_time) - toMinutes(entry.start_time)
  dragging.value = { kind: 'entry', target: entry, durationMinutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}

function onBandDragStart(event, band, section) {
  if (!isDraggableBand(band)) return
  const durationMinutes = toMinutes(band.end) - toMinutes(band.start)
  dragging.value = { kind: 'band', target: band, durationMinutes, section }
  event.dataTransfer.setData('text/plain', band.type)
  event.dataTransfer.effectAllowed = 'move'
}
```

- [ ] **Step 2: Update `onDragOver` and `onDrop` to branch on `dragging.value.kind`**

Replace:

```js
function onDragOver(event, section) {
  if (!dragging.value) return
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + dragging.value.durationMinutes
  conflictSectionId.value = wouldConflict(dragging.value.entry, start, end) ? section.id : null
}

async function onDrop(event, section) {
  if (!dragging.value) return
  const { entry, durationMinutes } = dragging.value
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + durationMinutes
  dragging.value = null
  conflictSectionId.value = null

  const { data } = await axios.post(
    route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id),
    {
      class_schedule_id: entry.id,
      override_start_time: fromMinutes(start),
      override_end_time: fromMinutes(end),
    },
  )
  emit('update:preview', data)
}
```

With:

```js
function onDragOver(event, section) {
  if (!dragging.value) return
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + dragging.value.durationMinutes
  // Bands are informational overlays — no conflict pre-check for them,
  // matching the spec's "no new overlap validation for bands" decision.
  conflictSectionId.value = dragging.value.kind === 'entry' && wouldConflict(dragging.value.target, start, end)
    ? section.id
    : null
}

async function onDrop(event, section) {
  if (!dragging.value) return
  const { kind, target, durationMinutes } = dragging.value
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + durationMinutes
  dragging.value = null
  conflictSectionId.value = null

  const { data } = kind === 'entry'
    ? await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
        class_schedule_id: target.id,
        override_start_time: fromMinutes(start),
        override_end_time: fromMinutes(end),
      })
    : await axios.post(route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id), {
        section_id: section.id,
        band_type: target.type,
        override_start_time: fromMinutes(start),
        override_end_time: fromMinutes(end),
      })
  emit('update:preview', data)
}
```

- [ ] **Step 3: Make Recess/White Space band divs draggable in the template**

Replace:

```html
            <div
              v-for="band in section.bands"
              :key="`${band.type}-${band.start}`"
              class="absolute inset-x-0 rounded bg-slate-200/60 px-1.5 py-0.5 text-[10px] text-slate-500"
              :style="bandStyle(band)"
            >
              {{ band.label }}
            </div>
```

With:

```html
            <div
              v-for="band in section.bands"
              :key="`${band.type}-${band.start}`"
              :draggable="isDraggableBand(band)"
              class="absolute inset-x-0 rounded px-1.5 py-0.5 text-[10px]"
              :class="bandClass(band)"
              :style="bandStyle(band)"
              @dragstart="onBandDragStart($event, band, section)"
            >
              {{ band.label }}
            </div>
```

- [ ] **Step 4: Add the `bandClass()` helper**

Right after `entryClass()`:

```js
function bandClass(band) {
  if (!isDraggableBand(band)) {
    return 'bg-slate-200/60 text-slate-500'
  }
  return band.manually_adjusted
    ? 'cursor-grab border border-indigo-300 bg-indigo-50 text-indigo-800 active:cursor-grabbing'
    : 'cursor-grab border border-slate-200 bg-slate-200/60 text-slate-600 active:cursor-grabbing'
}
```

- [ ] **Step 5: Build the frontend**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue
git commit -m "feat(faculty-loading): make Recess/White Space bands draggable (move)"
```

---

### Task 7: Frontend — resize handles for Recess/White Space bands

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`

**Interfaces:**
- Consumes: `POST faculty-loading.schedules.day-adjustments.band-overrides.store` (Task 5), reused from Task 6.

- [ ] **Step 1: Add resize-handle markup inside the band div**

Replace:

```html
            <div
              v-for="band in section.bands"
              :key="`${band.type}-${band.start}`"
              :draggable="isDraggableBand(band)"
              class="absolute inset-x-0 rounded px-1.5 py-0.5 text-[10px]"
              :class="bandClass(band)"
              :style="bandStyle(band)"
              @dragstart="onBandDragStart($event, band, section)"
            >
              {{ band.label }}
            </div>
```

With:

```html
            <div
              v-for="band in section.bands"
              :key="`${band.type}-${band.start}`"
              :draggable="isDraggableBand(band)"
              class="absolute inset-x-0 rounded px-1.5 py-0.5 text-[10px]"
              :class="bandClass(band)"
              :style="bandStyle(band)"
              @dragstart="onBandDragStart($event, band, section)"
            >
              <div v-if="isDraggableBand(band)" class="absolute inset-x-0 top-0 h-1.5 cursor-ns-resize"
                @mousedown.stop.prevent="startResize($event, band, section, 'start')"
                @dragstart.stop.prevent></div>
              {{ band.label }}
              <div v-if="isDraggableBand(band)" class="absolute inset-x-0 bottom-0 h-1.5 cursor-ns-resize"
                @mousedown.stop.prevent="startResize($event, band, section, 'end')"
                @dragstart.stop.prevent></div>
            </div>
```

- [ ] **Step 2: Add the resize state and handlers**

Add near the other `ref` declarations (after `conflictSectionId`):

```js
const resizing = ref(null) // { band, section, edge, startY, originalStart, originalEnd }
const MIN_BAND_MINUTES = 5
```

Add near the end of the script, after `onDrop`:

```js
function startResize(event, band, section, edge) {
  resizing.value = {
    band,
    section,
    edge,
    startY: event.clientY,
    originalStart: toMinutes(band.start),
    originalEnd: toMinutes(band.end),
  }
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', onResizeEnd)
}

function onResizeMove(event) {
  if (!resizing.value) return
  const rawDelta = (event.clientY - resizing.value.startY) / PX_PER_MINUTE
  resizing.value.deltaMinutes = Math.round(rawDelta / SNAP_MINUTES) * SNAP_MINUTES
}

async function onResizeEnd() {
  window.removeEventListener('mousemove', onResizeMove)
  window.removeEventListener('mouseup', onResizeEnd)
  if (!resizing.value) return

  const { band, section, edge, originalStart, originalEnd, deltaMinutes = 0 } = resizing.value
  resizing.value = null

  let start = originalStart
  let end = originalEnd
  if (edge === 'start') {
    start = Math.min(originalStart + deltaMinutes, originalEnd - MIN_BAND_MINUTES)
  } else {
    end = Math.max(originalEnd + deltaMinutes, originalStart + MIN_BAND_MINUTES)
  }

  if (start === originalStart && end === originalEnd) return

  const { data } = await axios.post(
    route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id),
    {
      section_id: section.id,
      band_type: band.type,
      override_start_time: fromMinutes(start),
      override_end_time: fromMinutes(end),
    },
  )
  emit('update:preview', data)
}
```

- [ ] **Step 3: Build the frontend**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue
git commit -m "feat(faculty-loading): add resize handles to Recess/White Space bands"
```

---

### Task 8: Frontend — click-to-edit generalized to bands

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`

**Interfaces:**
- Reuses: the existing precision-edit modal (currently entry-only), `POST`/`DELETE band-overrides` endpoints (Task 5).

- [ ] **Step 1: Add a click handler to draggable bands**

In the band `<div>` from Task 7, add a click handler:

```html
              @dragstart="onBandDragStart($event, band, section)"
              @click="isDraggableBand(band) && openBandOverride(section, band)"
            >
```

- [ ] **Step 2: Add `editingBand` state alongside `editingEntry`**

Replace:

```js
const showOverrideModal = ref(false)
const editingEntry = ref(null)
const overrideForm = ref({ override_start_time: '', override_end_time: '' })
const overrideError = ref('')
const savingOverride = ref(false)
```

With:

```js
const showOverrideModal = ref(false)
const editingEntry = ref(null)
const editingBand = ref(null) // { section, band }
const overrideForm = ref({ override_start_time: '', override_end_time: '' })
const overrideError = ref('')
const savingOverride = ref(false)
```

- [ ] **Step 3: Add `openBandOverride()` and generalize `openOverride()` to clear the other target**

Replace:

```js
function openOverride(entry) {
  editingEntry.value = entry
  overrideForm.value = { override_start_time: entry.start_time, override_end_time: entry.end_time }
  overrideError.value = ''
  showOverrideModal.value = true
}
```

With:

```js
function openOverride(entry) {
  editingEntry.value = entry
  editingBand.value = null
  overrideForm.value = { override_start_time: entry.start_time, override_end_time: entry.end_time }
  overrideError.value = ''
  showOverrideModal.value = true
}

function openBandOverride(section, band) {
  editingBand.value = { section, band }
  editingEntry.value = null
  overrideForm.value = { override_start_time: band.start, override_end_time: band.end }
  overrideError.value = ''
  showOverrideModal.value = true
}
```

- [ ] **Step 4: Generalize `saveOverride()` and `removeOverride()`**

Replace:

```js
async function saveOverride() {
  savingOverride.value = true
  overrideError.value = ''
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
      class_schedule_id: editingEntry.value.id,
      override_start_time: overrideForm.value.override_start_time,
      override_end_time: overrideForm.value.override_end_time,
    })
    emit('update:preview', data)
    showOverrideModal.value = false
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    overrideError.value = errors.override_end_time?.[0] ?? errors.override_start_time?.[0] ?? error.response?.data?.message ?? 'Unable to save this adjustment.'
  } finally {
    savingOverride.value = false
  }
}

async function removeOverride() {
  const { data } = await axios.delete(route('faculty-loading.schedules.day-adjustments.overrides.destroy', [props.adjustment.id, editingEntry.value.id]))
  emit('update:preview', data)
  showOverrideModal.value = false
}
```

With:

```js
async function saveOverride() {
  savingOverride.value = true
  overrideError.value = ''
  try {
    const { data } = editingEntry.value
      ? await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
          class_schedule_id: editingEntry.value.id,
          override_start_time: overrideForm.value.override_start_time,
          override_end_time: overrideForm.value.override_end_time,
        })
      : await axios.post(route('faculty-loading.schedules.day-adjustments.band-overrides.store', props.adjustment.id), {
          section_id: editingBand.value.section.id,
          band_type: editingBand.value.band.type,
          override_start_time: overrideForm.value.override_start_time,
          override_end_time: overrideForm.value.override_end_time,
        })
    emit('update:preview', data)
    showOverrideModal.value = false
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    overrideError.value = errors.override_end_time?.[0] ?? errors.override_start_time?.[0] ?? error.response?.data?.message ?? 'Unable to save this adjustment.'
  } finally {
    savingOverride.value = false
  }
}

async function removeOverride() {
  const { data } = editingEntry.value
    ? await axios.delete(route('faculty-loading.schedules.day-adjustments.overrides.destroy', [props.adjustment.id, editingEntry.value.id]))
    : await axios.delete(route('faculty-loading.schedules.day-adjustments.band-overrides.destroy', [props.adjustment.id, editingBand.value.section.id, editingBand.value.band.type]))
  emit('update:preview', data)
  showOverrideModal.value = false
}
```

- [ ] **Step 5: Update the modal template to show either target**

Replace:

```html
    <div v-if="editingEntry" class="space-y-4">
      <p class="text-sm text-slate-600">
        {{ editingEntry.subject?.name ?? editingEntry.title }} — currently {{ editingEntry.start_time }}–{{ editingEntry.end_time }}
      </p>
```

With:

```html
    <div v-if="editingEntry || editingBand" class="space-y-4">
      <p v-if="editingEntry" class="text-sm text-slate-600">
        {{ editingEntry.subject?.name ?? editingEntry.title }} — currently {{ editingEntry.start_time }}–{{ editingEntry.end_time }}
      </p>
      <p v-else class="text-sm text-slate-600">
        {{ editingBand.band.label }} — currently {{ editingBand.band.start }}–{{ editingBand.band.end }}
      </p>
```

And update the "Remove override" button's visibility condition. Replace:

```html
        <AppButton v-if="editingEntry?.manually_adjusted" variant="ghost" class="text-rose-600" @click="removeOverride">Remove override</AppButton>
```

With:

```html
        <AppButton v-if="editingEntry?.manually_adjusted || editingBand?.band.manually_adjusted" variant="ghost" class="text-rose-600" @click="removeOverride">Remove override</AppButton>
```

- [ ] **Step 6: Build the frontend**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 7: Full backend regression run**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"`
Expected: PASS, all tests.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue
git commit -m "feat(faculty-loading): generalize click-to-edit precision modal to bands"
```

---

## Self-Review Notes

**Spec coverage:**
- Part 1 (Lunch gap collapse, intersection-based, `early_start_stem_split`-only) → Task 3, three tests covering full collapse, no-fabrication, and partial-overlap.
- Part 2 schema/model/service/controller (band overrides, all 5 types) → Tasks 1, 2, 4, 5.
- Part 2 frontend (move, resize, click-to-edit) → Tasks 6, 7, 8.

**Type consistency check:** `band_type` used identically as `'RECESS'|'WHITE_SPACE'` string across migration, model, controller validation, and frontend (`band.type` from `generate()`'s existing band shape — never renamed). `section_id` passed as a plain integer consistently (route param, request field, JS payload). `dragging.value.kind`/`target` naming introduced in Task 6 is used consistently through Tasks 6-8 (no leftover `dragging.value.entry` reference from the pre-existing code).

**Placeholder scan:** no TBD/TODO; every step has real code.
