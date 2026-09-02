# Adjusted Day Calendar — Drag-and-Drop "Bump to Unplaced" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a class is dragged onto an already-occupied slot in the Adjusted Day Calendar, the occupant is automatically bumped — to an "Unplaced" tray if it's a real subject class (must be resolved before publishing), or silently removed if it's a non-teaching block.

**Architecture:** One new additive table (`class_schedule_day_adjustment_unplaced_entries`) records "this class has no slot on this adjusted day." `AdjustedClassScheduleService::generate()` excludes unplaced classes from a section's normal `entries` and returns them in a new `unplaced_entries` array instead. The existing `upsertOverride()` endpoint (already called by every drag-and-drop) detects same-section collisions and bumps them transactionally before placing the mover. `publish()` blocks while any subject-bearing class remains unplaced. The frontend adds a small per-section chip tray that reuses the existing drag machinery as-is.

**Tech Stack:** Laravel 12 (PHP 8.4), MySQL 8.0, Vue 3 `<script setup>`, native HTML5 drag-and-drop (no library).

**Spec:** `docs/superpowers/specs/2026-09-02-adjusted-day-drag-drop-unplaced-entries-design.md`

## Global Constraints

- Additive migration only — no changes to existing tables/columns (blue-green safe, no expand/contract split needed).
- A class can only ever return to its own section (`ClassSchedule.section_id` is fixed) — no cross-section re-placement.
- Bands (Recess/White Space/Wellness/Health Break/Elective/Science Core/Official Activity) are never bump candidates — this feature only touches `section.entries` (real classes and non-teaching blocks).
- Non-teaching removals never block publish; only subject-bearing (`entry_type === 'class'`) unplaced entries do.
- Follow this repo's Eloquent conventions exactly: no Laravel SoftDeletes, eager-load relations, `back()->with('success', ...)` patterns where already established.

---

### Task 1: Migration + model for the unplaced-entries table

**Files:**
- Create: `database/migrations/2026_09_02_100000_create_class_schedule_day_adjustment_unplaced_entries_table.php`
- Create: `app/Models/FacultyLoading/ClassScheduleDayAdjustmentUnplacedEntry.php`
- Modify: `app/Models/FacultyLoading/ClassScheduleDayAdjustment.php` (add `unplacedEntries()` relation, alongside the existing `overrides()`/`bandOverrides()` at lines 62-70)

**Interfaces:**
- Produces: `ClassScheduleDayAdjustmentUnplacedEntry` model with `fillable = ['adjustment_id', 'class_schedule_id']`, relations `adjustment(): BelongsTo` and `classSchedule(): BelongsTo`. `ClassScheduleDayAdjustment::unplacedEntries(): HasMany` (keyed `adjustment_id`, same pattern as `overrides()`/`bandOverrides()`).

This is a pure schema/model task — no dedicated test file, matching this codebase's existing convention (the sibling `class_schedule_day_adjustment_overrides` and `..._band_overrides` tables have no standalone migration/model tests either; they're exercised end-to-end by the Feature tests in later tasks).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A class bumped off its slot for one adjusted day by a drag-and-drop
     * placement colliding with it — either a real subject class awaiting
     * manual re-placement (surfaced in the calendar's "Unplaced" tray,
     * blocks publish until resolved) or a non_teaching block that's simply
     * removed for the day (never surfaced, never blocks publish).
     * Distinguished by the underlying ClassSchedule.entry_type, not a
     * column here — both are "this class has no slot today."
     */
    public function up(): void
    {
        Schema::create('class_schedule_day_adjustment_unplaced_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('class_schedule_day_adjustments', 'id', 'cs_day_adj_unplaced_adjustment_fk')
                ->cascadeOnDelete();
            $table->foreignId('class_schedule_id')
                ->constrained('class_schedules', 'id', 'cs_day_adj_unplaced_schedule_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['adjustment_id', 'class_schedule_id'], 'cs_day_adj_unplaced_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustment_unplaced_entries');
    }
};
```

- [ ] **Step 2: Run the migration in dev**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_02_100000_create_class_schedule_day_adjustment_unplaced_entries_table.php"
```

Expected: `Migrating: 2026_09_02_100000_...` then `Migrated:` — no errors.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A class that has no slot on one adjusted day — bumped by a drag-and-drop
 * placement colliding with it. See migration docblock for the two ways
 * this is used (unplaced subject class vs. removed non-teaching block).
 */
class ClassScheduleDayAdjustmentUnplacedEntry extends Model
{
    protected $table = 'class_schedule_day_adjustment_unplaced_entries';

    protected $fillable = [
        'adjustment_id',
        'class_schedule_id',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleDayAdjustment::class, 'adjustment_id');
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }
}
```

- [ ] **Step 4: Add the relation to `ClassScheduleDayAdjustment`**

In `app/Models/FacultyLoading/ClassScheduleDayAdjustment.php`, immediately after the existing `bandOverrides()` method (around line 70):

```php
    public function unplacedEntries(): HasMany
    {
        return $this->hasMany(ClassScheduleDayAdjustmentUnplacedEntry::class, 'adjustment_id');
    }
```

- [ ] **Step 5: Verify with tinker**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"echo App\Models\FacultyLoading\ClassScheduleDayAdjustment::first()->unplacedEntries()->getRelated()::class;\""
```

Expected: prints `App\Models\FacultyLoading\ClassScheduleDayAdjustmentUnplacedEntry` with no error.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_02_100000_create_class_schedule_day_adjustment_unplaced_entries_table.php app/Models/FacultyLoading/ClassScheduleDayAdjustmentUnplacedEntry.php app/Models/FacultyLoading/ClassScheduleDayAdjustment.php
git commit -m "feat(faculty-loading): add unplaced-entries table for adjusted-day drag bump"
```

---

### Task 2: Service excludes unplaced entries, exposes `unplaced_entries` per section

**Files:**
- Modify: `app/Services/FacultyLoading/AdjustedClassScheduleService.php:46-51` (load unplaced ids alongside `overridesByScheduleId`), `:157-181` (entries-building block)
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `ClassScheduleDayAdjustment::unplacedEntries()` (Task 1).
- Produces: each section in `generate()`'s output gains `'unplaced_entries' => array<int, array{id:int, subject:?array, faculty:?array, classroom:?array, duration_minutes:int}>`. `entries` no longer contains rows whose `class_schedule_id` is in the adjustment's unplaced set. Non-`class` (`non_teaching`) unplaced rows appear in neither array.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`, after `test_early_start_stem_split_treats_science_core_and_elective_subjects_as_stem_even_without_is_stem_flag` (or any convenient spot before the `plotAssessment` helper):

```php
    public function test_unplaced_class_entry_is_excluded_from_entries_and_appears_in_unplaced_entries(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $filipino = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $filipinoClass = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $filipino->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:20', 'end_time' => '08:50', 'status' => 'active',
        ]);
        $advisory = ClassSchedule::create([
            'user_id' => $this->manager->id, 'section_id' => $section->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:50', 'end_time' => '09:00', 'status' => 'active',
            'entry_type' => 'non_teaching', 'title' => 'Advisory',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $filipinoClass->id]);
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $advisory->id]);

        $response = $this->actingAs($this->manager)
            ->getJson(route('faculty-loading.schedules.day-adjustments.preview', $adjustment))
            ->assertOk();

        $aquamarine = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0];
        $entryIds = collect($aquamarine['entries'])->pluck('id');
        $unplacedIds = collect($aquamarine['unplaced_entries'])->pluck('id');

        $this->assertNotContains($filipinoClass->id, $entryIds);
        $this->assertNotContains($advisory->id, $entryIds);
        $this->assertContains($filipinoClass->id, $unplacedIds);
        $this->assertNotContains($advisory->id, $unplacedIds);

        $unplacedFilipino = collect($aquamarine['unplaced_entries'])->firstWhere('id', $filipinoClass->id);
        $this->assertSame('Filipino 1', $unplacedFilipino['subject']['name']);
        $this->assertSame(30, $unplacedFilipino['duration_minutes']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_unplaced_class_entry_is_excluded_from_entries_and_appears_in_unplaced_entries tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: FAIL — `unplaced_entries` key doesn't exist yet (undefined array key), or the Filipino/Advisory entries still show up in `entries`.

- [ ] **Step 3: Load the unplaced set in `generate()`**

In `app/Services/FacultyLoading/AdjustedClassScheduleService.php`, right after the existing `$bandOverridesBySectionType` block (around line 49-51):

```php
        $unplacedScheduleIds = $adjustment->exists
            ? $adjustment->unplacedEntries()->pluck('class_schedule_id')
            : collect();
```

- [ ] **Step 4: Filter entries and build `unplaced_entries` in the per-section loop**

In the same file, the entries-building block currently reads (around lines 157-181):

```php
                $entries = $sectionSchedule
                    ->map(function (ClassSchedule $schedule) use ($sectionSlots, $sectionShift, $overridesByScheduleId) {
                        $entry = $schedule->toCalendarArray();
                        $entry['raw_start_time'] = substr((string) $schedule->start_time, 0, 5);
                        $entry['raw_end_time'] = substr((string) $schedule->end_time, 0, 5);

                        $override = $overridesByScheduleId->get($schedule->id);
                        if ($override) {
                            // A manual time-only correction takes precedence
                            // over the computed compression/shift for this
                            // one entry — used to resolve a flagged conflict
                            // before publishing. Flagged for audit display.
                            $entry['start_time'] = substr((string) $override->override_start_time, 0, 5);
                            $entry['end_time'] = substr((string) $override->override_end_time, 0, 5);
                            $entry['manually_adjusted'] = true;
                        } else {
                            $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $sectionSlots, $sectionShift);
                            $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $sectionSlots, $sectionShift);
                            $entry['manually_adjusted'] = false;
                        }

                        return $entry;
                    })
                    ->values()
                    ->all();
```

Replace it with (adds the unplaced/removed split — the `.map()` body is unchanged, only what happens to its output changes):

```php
                $entries = $sectionSchedule
                    ->map(function (ClassSchedule $schedule) use ($sectionSlots, $sectionShift, $overridesByScheduleId) {
                        $entry = $schedule->toCalendarArray();
                        $entry['raw_start_time'] = substr((string) $schedule->start_time, 0, 5);
                        $entry['raw_end_time'] = substr((string) $schedule->end_time, 0, 5);

                        $override = $overridesByScheduleId->get($schedule->id);
                        if ($override) {
                            // A manual time-only correction takes precedence
                            // over the computed compression/shift for this
                            // one entry — used to resolve a flagged conflict
                            // before publishing. Flagged for audit display.
                            $entry['start_time'] = substr((string) $override->override_start_time, 0, 5);
                            $entry['end_time'] = substr((string) $override->override_end_time, 0, 5);
                            $entry['manually_adjusted'] = true;
                        } else {
                            $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $sectionSlots, $sectionShift);
                            $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $sectionSlots, $sectionShift);
                            $entry['manually_adjusted'] = false;
                        }

                        return $entry;
                    });

                // Bumped by a drag-and-drop collision (see
                // ClassScheduleDayAdjustmentController::upsertOverride()) —
                // has no slot on this adjusted day. A subject class goes to
                // the section's unplaced_entries for manual re-placement; a
                // non_teaching block is just gone (never surfaced).
                $unplacedForSection = $sectionSchedule
                    ->filter(fn (ClassSchedule $s) => $unplacedScheduleIds->contains($s->id) && $s->entry_type === 'class')
                    ->map(fn (ClassSchedule $s) => [
                        'id' => $s->id,
                        'subject' => $s->subject ? [
                            'id' => $s->subject->id,
                            'code' => $s->subject->code,
                            'name' => $s->subject->name,
                            'is_stem' => (bool) $s->subject->is_stem,
                        ] : null,
                        'faculty' => $s->faculty ? ['id' => $s->faculty->id, 'name' => $s->faculty->name] : null,
                        'classroom' => $s->classroom ? ['id' => $s->classroom->id, 'name' => $s->classroom->name] : null,
                        'duration_minutes' => SchedulingConstants::toMinutes(substr((string) $s->end_time, 0, 5))
                            - SchedulingConstants::toMinutes(substr((string) $s->start_time, 0, 5)),
                    ])
                    ->values()
                    ->all();

                $entries = $entries
                    ->reject(fn (array $entry) => $unplacedScheduleIds->contains($entry['id']))
                    ->values()
                    ->all();
```

- [ ] **Step 5: Add `unplaced_entries` to the section's returned array**

Immediately below, the existing section-array push (around the original line 266-271-ish, after the bands are finalized) reads:

```php
                $gradeSections[] = [
                    'id' => (int) $section->id,
                    'name' => $section->sectionname,
                    'entries' => $entries,
                    'bands' => $bands,
                ];
```

Add `'unplaced_entries' => $unplacedForSection` to it:

```php
                $gradeSections[] = [
                    'id' => (int) $section->id,
                    'name' => $section->sectionname,
                    'entries' => $entries,
                    'bands' => $bands,
                    'unplaced_entries' => $unplacedForSection,
                ];
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_unplaced_class_entry_is_excluded_from_entries_and_appears_in_unplaced_entries tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: PASS.

- [ ] **Step 7: Run the full adjustment test file to check for regressions**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: all tests pass (39 total after this addition).

- [ ] **Step 8: Commit**

```bash
git add app/Services/FacultyLoading/AdjustedClassScheduleService.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "feat(faculty-loading): exclude unplaced entries from the timeline, expose unplaced_entries per section"
```

---

### Task 3: Controller bumps same-section collisions on drop

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php:1-21` (imports), `:285-308` (`upsertOverride`)
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `ClassScheduleDayAdjustment::unplacedEntries()` (Task 1), `AdjustedClassScheduleService::generate()`'s per-section `entries`/`unplaced_entries` (Task 2).
- Produces: `upsertOverride()`'s existing response shape is unchanged (still `response()->json($this->adjustedSchedules->generate(...))`) — callers (the frontend) need no changes to consume the bump side effects, they're just reflected in the returned preview.

- [ ] **Step 1: Write the failing test — bumping a class**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`:

```php
    public function test_dropping_a_class_onto_an_occupied_slot_bumps_the_occupant_to_unplaced(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $filipino = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        // Immediately after $this->tuesdayClass (MATH7, 07:30-08:20).
        $filipinoClass = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $filipino->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:20', 'end_time' => '08:50', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        // Drag MATH7 (currently 07:30-08:20) onto Filipino's slot (08:20-08:50).
        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $this->tuesdayClass->id,
                'override_start_time' => '08:20',
                'override_end_time' => '08:50',
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_schedule_day_adjustment_unplaced_entries', [
            'adjustment_id' => $adjustment->id,
            'class_schedule_id' => $filipinoClass->id,
        ]);
        $this->assertDatabaseHas('class_schedule_day_adjustment_overrides', [
            'adjustment_id' => $adjustment->id,
            'class_schedule_id' => $this->tuesdayClass->id,
            'override_start_time' => '08:20:00',
            'override_end_time' => '08:50:00',
        ]);

        $aquamarine = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0];
        $entryIds = collect($aquamarine['entries'])->pluck('id');
        $unplacedIds = collect($aquamarine['unplaced_entries'])->pluck('id');

        $this->assertContains($this->tuesdayClass->id, $entryIds);
        $this->assertNotContains($filipinoClass->id, $entryIds);
        $this->assertContains($filipinoClass->id, $unplacedIds);
    }

    public function test_dropping_a_class_onto_a_non_teaching_block_removes_it_without_a_tray_entry(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $advisory = ClassSchedule::create([
            'user_id' => $this->manager->id, 'section_id' => $section->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:20', 'end_time' => '08:30', 'status' => 'active',
            'entry_type' => 'non_teaching', 'title' => 'Advisory',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();

        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $this->tuesdayClass->id,
                'override_start_time' => '08:20',
                'override_end_time' => '08:30',
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_schedule_day_adjustment_unplaced_entries', [
            'adjustment_id' => $adjustment->id,
            'class_schedule_id' => $advisory->id,
        ]);

        $aquamarine = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0];
        $this->assertNotContains($advisory->id, collect($aquamarine['entries'])->pluck('id'));
        $this->assertNotContains($advisory->id, collect($aquamarine['unplaced_entries'])->pluck('id'));
    }

    public function test_re_placing_an_unplaced_class_clears_its_unplaced_status(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $filipino = Subject::create([
            'school_year_id' => $this->term->school_year_id, 'code' => 'FIL1-G7', 'name' => 'Filipino 1',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        $filipinoClass = ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $filipino->id, 'section_id' => $section->id, 'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '10:00', 'end_time' => '10:30', 'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $filipinoClass->id]);

        // Drag the (currently unplaced) Filipino chip onto an open slot.
        $response = $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $filipinoClass->id,
                'override_start_time' => '11:00',
                'override_end_time' => '11:30',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('class_schedule_day_adjustment_unplaced_entries', [
            'adjustment_id' => $adjustment->id,
            'class_schedule_id' => $filipinoClass->id,
        ]);

        $aquamarine = collect($response->json('grades'))->firstWhere('grade_level', 7)['sections'][0];
        $this->assertContains($filipinoClass->id, collect($aquamarine['entries'])->pluck('id'));
        $this->assertNotContains($filipinoClass->id, collect($aquamarine['unplaced_entries'])->pluck('id'));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='test_dropping_a_class_onto_an_occupied_slot_bumps_the_occupant_to_unplaced|test_dropping_a_class_onto_a_non_teaching_block_removes_it_without_a_tray_entry|test_re_placing_an_unplaced_class_clears_its_unplaced_status' tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: all 3 FAIL (no bump logic exists yet — the occupant stays put, `class_schedule_day_adjustment_unplaced_entries` never gets a row).

- [ ] **Step 3: Add the `ClassSchedule` import**

In `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php`, add to the existing `use` block (around line 6-8):

```php
use App\Models\FacultyLoading\ClassSchedule;
```

- [ ] **Step 4: Implement the bump logic in `upsertOverride()`**

The method currently reads (lines 285-308):

```php
    public function upsertOverride(Request $request, ClassScheduleDayAdjustment $adjustment): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $data = $request->validate([
            'class_schedule_id' => ['required', 'integer', 'exists:class_schedules,id'],
            'override_start_time' => ['required', 'date_format:H:i'],
            'override_end_time' => ['required', 'date_format:H:i'],
        ]);

        if ($data['override_end_time'] <= $data['override_start_time']) {
            throw ValidationException::withMessages([
                'override_end_time' => 'The override end time must be after its start time.',
            ]);
        }

        $adjustment->overrides()->updateOrCreate(
            ['class_schedule_id' => $data['class_schedule_id']],
            ['override_start_time' => $data['override_start_time'], 'override_end_time' => $data['override_end_time']],
        );

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }
```

Replace it with:

```php
    public function upsertOverride(Request $request, ClassScheduleDayAdjustment $adjustment): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $data = $request->validate([
            'class_schedule_id' => ['required', 'integer', 'exists:class_schedules,id'],
            'override_start_time' => ['required', 'date_format:H:i'],
            'override_end_time' => ['required', 'date_format:H:i'],
        ]);

        if ($data['override_end_time'] <= $data['override_start_time']) {
            throw ValidationException::withMessages([
                'override_end_time' => 'The override end time must be after its start time.',
            ]);
        }

        DB::transaction(function () use ($data, $adjustment) {
            $movingSchedule = ClassSchedule::findOrFail($data['class_schedule_id']);

            // Bump every other entry in the same section whose current
            // (already override-aware) time collides with the mover's new
            // range — a class goes to Unplaced for manual re-placement, a
            // non_teaching block is just removed (no tray entry for it).
            $preview = $this->adjustedSchedules->generate($adjustment);
            $section = collect($preview['grades'])
                ->flatMap(fn (array $grade) => $grade['sections'])
                ->firstWhere('id', $movingSchedule->section_id);

            if ($section) {
                foreach ($section['entries'] as $entry) {
                    if ($entry['id'] === $movingSchedule->id) {
                        continue;
                    }
                    $collides = $entry['start_time'] < $data['override_end_time']
                        && $data['override_start_time'] < $entry['end_time'];
                    if (! $collides) {
                        continue;
                    }

                    $adjustment->unplacedEntries()->updateOrCreate(['class_schedule_id' => $entry['id']], []);
                    $adjustment->overrides()->where('class_schedule_id', $entry['id'])->delete();
                }
            }

            // Providing an explicit time inherently means "place me here
            // now" — clears the mover's own unplaced status, if any (this
            // is how a chip dragged out of the Unplaced tray gets resolved).
            $adjustment->unplacedEntries()->where('class_schedule_id', $data['class_schedule_id'])->delete();

            $adjustment->overrides()->updateOrCreate(
                ['class_schedule_id' => $data['class_schedule_id']],
                ['override_start_time' => $data['override_start_time'], 'override_end_time' => $data['override_end_time']],
            );
        });

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='test_dropping_a_class_onto_an_occupied_slot_bumps_the_occupant_to_unplaced|test_dropping_a_class_onto_a_non_teaching_block_removes_it_without_a_tray_entry|test_re_placing_an_unplaced_class_clears_its_unplaced_status' tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: all 3 PASS.

- [ ] **Step 6: Run the full adjustment test file to check for regressions**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: all tests pass (42 total after this addition). Pay particular attention to the existing override tests (`test_override_resolves_a_flagged_cross_grade_warning`, `test_override_can_be_removed`, etc.) — they must still pass unchanged, since the new collision-detection only *adds* behavior when a genuine same-section overlap exists.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "feat(faculty-loading): bump same-section collisions to unplaced/removed on drag-and-drop"
```

---

### Task 4: Publish blocks on unresolved unplaced classes

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php:172-195` (`publish`)
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `ClassScheduleDayAdjustment::unplacedEntries()` (Task 1), `ClassScheduleDayAdjustmentUnplacedEntry::classSchedule()` (Task 1).
- Produces: `publish()` now throws `ValidationException::withMessages(['unplaced' => ...])` (422, redirect-back-with-errors — same Inertia error flow already used elsewhere in this controller) when any subject-bearing class is unplaced.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`:

```php
    public function test_publish_is_blocked_while_a_subject_class_is_unplaced(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $this->tuesdayClass->id]);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertSessionHasErrors('unplaced');

        $this->assertSame('draft', $adjustment->fresh()->status);
    }

    public function test_publish_succeeds_once_the_unplaced_class_is_resolved(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $this->tuesdayClass->id]);

        $this->actingAs($this->manager)
            ->postJson(route('faculty-loading.schedules.day-adjustments.overrides.store', $adjustment), [
                'class_schedule_id' => $this->tuesdayClass->id,
                'override_start_time' => '09:00',
                'override_end_time' => '09:50',
            ])
            ->assertOk();

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $adjustment->fresh()->status);
    }

    public function test_publish_succeeds_with_only_a_removed_non_teaching_block_unplaced(): void
    {
        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $advisory = ClassSchedule::create([
            'user_id' => $this->manager->id, 'section_id' => $section->id,
            'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '09:00', 'end_time' => '09:10', 'status' => 'active',
            'entry_type' => 'non_teaching', 'title' => 'Advisory',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $adjustment->unplacedEntries()->create(['class_schedule_id' => $advisory->id]);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $adjustment->fresh()->status);
    }
```

- [ ] **Step 2: Run the tests to verify the first one fails**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='test_publish_is_blocked_while_a_subject_class_is_unplaced|test_publish_succeeds_once_the_unplaced_class_is_resolved|test_publish_succeeds_with_only_a_removed_non_teaching_block_unplaced' tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: `test_publish_is_blocked_while_a_subject_class_is_unplaced` FAILS (publish currently succeeds unconditionally); the other two PASS already (no gate exists yet, so nothing blocks them — that's fine, they'll stay green after Step 3 too).

- [ ] **Step 3: Add the gate to `publish()`**

The method currently reads (lines 172-195):

```php
    public function publish(ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be published.');

        DB::transaction(function () use ($adjustment) {
```

Insert the gate right after the `abort_if`:

```php
    public function publish(ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be published.');

        $hasUnresolvedSubjectClass = $adjustment->unplacedEntries()
            ->whereHas('classSchedule', fn ($query) => $query->where('entry_type', 'class'))
            ->exists();
        if ($hasUnresolvedSubjectClass) {
            throw ValidationException::withMessages([
                'unplaced' => 'This adjustment has one or more unplaced classes — resolve them in the Unplaced tray before publishing.',
            ]);
        }

        DB::transaction(function () use ($adjustment) {
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='test_publish_is_blocked_while_a_subject_class_is_unplaced|test_publish_succeeds_once_the_unplaced_class_is_resolved|test_publish_succeeds_with_only_a_removed_non_teaching_block_unplaced' tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: all 3 PASS.

- [ ] **Step 5: Run the full adjustment test file to check for regressions**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"
```

Expected: all tests pass (45 total after this addition) — every existing publish test (`test_a_second_active_adjustment_for_the_same_date_is_still_rejected`, `test_grades_can_be_edited_after_publishing_and_snapshot_is_refrozen`, etc.) must still pass unchanged, since none of them create unplaced entries.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "feat(faculty-loading): block publish while a subject-bearing class is unplaced"
```

---

### Task 5: Frontend — Unplaced chip tray per section

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue` (template: insert a new row after the "Time axis + section columns" row, before the closing of the `overflow-x-auto` wrapper; script: add `onChipDragStart`)

**Interfaces:**
- Consumes: `section.unplaced_entries` (Task 2's output — array of `{id, subject, faculty, classroom, duration_minutes}`), the existing `dragging` ref, `onDragOver`/`onDrop` (unchanged — this task adds a new drag *source* only, no changes to the drop side), `entryColorStyle(entry)` (existing helper, already handles a bare `{subject}`-shaped object).

No backend calls of its own — reuses `faculty-loading.schedules.day-adjustments.overrides.store` via the existing `onDrop` handler.

- [ ] **Step 1: Add the chip tray row to the template**

In `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`, the "Time axis + section columns" row currently closes like this (inside the `overflow-x-auto` wrapper, right before that wrapper's own closing tag):

```html
          </div>
        </div>
      </div>

      <!-- Official Activity / Early Dismissal isn't draggable or editable —
```

Insert a new row between the closing `</div>` of the "Time axis + section columns" row and the `overflow-x-auto` wrapper's own close (i.e., still inside `overflow-x-auto` so it scrolls horizontally in sync with the columns above it):

```html
          </div>
        </div>

        <!-- Unplaced tray — bumped subject classes for this section,
             draggable back onto any open slot in the same section. -->
        <div v-if="grade.sections.some(s => (s.unplaced_entries ?? []).length)"
          class="flex border-t border-slate-100 bg-slate-50/60">
          <div class="sticky left-0 z-10 shrink-0 border-r border-slate-100 bg-slate-50/60" :style="{ width: `${GUTTER}px` }" />
          <div v-for="section in grade.sections" :key="section.id"
            class="flex w-56 shrink-0 flex-wrap gap-1 border-l border-slate-100 p-1.5 first:border-l-0">
            <div v-for="entry in section.unplaced_entries ?? []" :key="entry.id"
              draggable="true"
              class="cursor-grab overflow-hidden truncate rounded-full border px-2 py-0.5 text-[10px] font-medium shadow-sm active:cursor-grabbing"
              :style="entryColorStyle(entry)"
              :title="`${entry.subject?.name ?? '—'} — unplaced, drag onto an open slot to re-place`"
              @dragstart="onChipDragStart($event, entry, section)"
            >{{ entry.subject?.name ?? '—' }}</div>
          </div>
        </div>
      </div>

      <!-- Official Activity / Early Dismissal isn't draggable or editable —
```

- [ ] **Step 2: Add `onChipDragStart` to the script**

In the `<script setup>` block, immediately after the existing `onDragStart` function:

```js
function onDragStart(event, entry, section) {
  const durationMinutes = toMinutes(entry.end_time) - toMinutes(entry.start_time)
  dragging.value = { kind: 'entry', target: entry, durationMinutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}
```

add:

```js
// A chip has no current start/end (it's unplaced) — its natural duration
// is server-provided instead of computed from start_time/end_time. Once
// dragging starts, this is otherwise identical to moving an on-calendar
// entry: onDragOver/onDrop need no changes.
function onChipDragStart(event, entry, section) {
  dragging.value = { kind: 'entry', target: entry, durationMinutes: entry.duration_minutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}
```

- [ ] **Step 3: Build the frontend**

```bash
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```

Expected: builds clean, no errors.

- [ ] **Step 4: Verify in the browser**

1. Create a test draft adjustment (any type, e.g. postponed Monday → Tuesday, matching the pattern used earlier this session) covering at least one grade.
2. Open its Resolve Conflicts page.
3. Drag one class entry onto another class entry's slot in the *same section* — confirm the dropped-onto entry disappears from the timeline and a new chip appears in a tray row below that section's timeline showing its subject name.
4. Drag that chip back onto an open slot in the same section — confirm it reappears on the timeline at the new time and the chip disappears from the tray.
5. Repeat step 3 but drop a class onto a non-teaching (Advisory-style) block's slot — confirm the block disappears with **no** chip appearing anywhere.
6. With a chip still unplaced, click **Publish** — confirm it's rejected with a visible error message (not a blank 500/422 page).
7. Drag the remaining chip to an open slot, then click **Publish** again — confirm it succeeds.
8. Cancel/delete the test adjustment afterward (same cleanup pattern used earlier this session) so no test data is left in the dev database.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue
git commit -m "feat(faculty-loading): add Unplaced chip tray with drag-to-re-place in the adjusted day calendar"
```

---

## Final regression pass

After all 5 tasks:

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php tests/Feature/Sos/LocationResolverServiceTest.php"
```

Expected: all pass, no regressions (`LocationResolverServiceTest` is the only other consumer of `AdjustedClassScheduleService`, per earlier verification this session).
