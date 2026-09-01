# Adjusted Day — Early-Start STEM Split + Manual Adjustment Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 5th Adjusted Day Schedule type (`early_start_stem_split`) that anchors every section's first class period to a configurable day-start time (default 7:00 AM), gives STEM subjects 50-minute periods and non-STEM subjects 30-minute periods, drops the Lunch band, and supports an optional Health Break — then replace the Manual Adjustment screen's plain table with a drag-and-drop calendar (for all 5 adjustment types).

**Architecture:** Extends `AdjustedClassScheduleService::generate()`'s existing per-slot-target compression (built earlier the same day for "Protect Assessment Periods") with a per-section anchor shift and a per-period STEM/non-STEM target. Adds one boolean column to `subjects` and five nullable columns to `class_schedule_day_adjustments`. Manual Adjustment's drag-and-drop is frontend-only — it writes to the `ClassScheduleDayAdjustmentOverride` model and endpoints that already exist.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, PHPUnit + RefreshDatabase.

**Spec:** `docs/superpowers/specs/2026-09-01-adjusted-day-early-start-stem-split-design.md`

## Global Constraints

- Migrations must be additive/nullable — no destructive changes in this plan (blue-green safe, no expand/contract split needed).
- New adjustment type value: `early_start_stem_split`.
- Default day start time: `07:00`. Default STEM duration: `50` minutes. Default non-STEM duration: `30` minutes.
- Official Activity fields (`activity_title`/`activity_start_time`/`activity_end_time`) are **optional** for `early_start_stem_split` (required for every other shortened-family type, unchanged).
- Health Break fields (`health_break_title`/`health_break_start_time`/`health_break_end_time`) are optional for every type, but if any one is set, all three are required together and end > start.
- Recess and every other existing band stay untouched — only the LUNCH band is dropped, and only for `early_start_stem_split`.
- Follow existing project conventions throughout: `back()->with('success', ...)`, named routes, no Blade views, no TypeScript, `Storage::disk('s3')` n/a here.

---

### Task 1: `is_stem` column on Subjects (migration + model)

**Files:**
- Create: `database/migrations/2026_09_01_140000_add_is_stem_to_subjects_table.php`
- Modify: `app/Models/FacultyLoading/Subject.php`
- Test: `tests/Feature/FacultyLoading/SubjectControllerTest.php`

**Interfaces:**
- Produces: `subjects.is_stem` (boolean, default false), `Subject::$fillable` includes `is_stem`, `Subject::$casts['is_stem'] = 'boolean'`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FacultyLoading/SubjectControllerTest.php` (same file, same class, alongside the existing `subject_group` tests):

```php
    public function test_is_stem_is_saved_on_create_and_update(): void
    {
        $user = $this->userWith('faculty_loading.subjects.manage');

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.store'), $this->subjectPayload(['is_stem' => true]))
            ->assertRedirect();

        $subject = Subject::where('code', 'PE1-G7')->firstOrFail();
        $this->assertTrue($subject->is_stem);

        $this->actingAs($user)
            ->put(route('faculty-loading.subjects.update', $subject->id), $this->subjectPayload(['is_stem' => false]))
            ->assertRedirect();

        $this->assertFalse($subject->fresh()->is_stem);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/SubjectControllerTest.php --filter test_is_stem_is_saved_on_create_and_update"`
Expected: FAIL — `is_stem` isn't a validated/fillable field yet, so it's silently dropped and `assertTrue($subject->is_stem)` fails (column doesn't exist yet either — migration not run).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_stem')->default(false)->after('subject_type');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_stem');
        });
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_01_140000_add_is_stem_to_subjects_table.php"`

- [ ] **Step 4: Update the Subject model**

In `app/Models/FacultyLoading/Subject.php`, add `'is_stem'` to `$fillable` (after `'subject_type'`) and `'is_stem' => 'boolean'` to `$casts`:

```php
    protected $fillable = [
        'school_year_id',
        'code',
        'name',
        'description',
        'specialization_tags',
        'credit_units',
        'lecture_hours',
        'lab_hours',
        'load_units',
        'subject_type',
        'is_stem',
        'subject_group',
        'grade_level',
        'semester',
        'sessions_per_week',
        'minutes_per_session',
        'academic_unit_id',
        'is_active',
        'has_ilp',
        'requires_computer_lab',
    ];

    protected $casts = [
        'school_year_id'      => 'integer',
        'credit_units'        => 'integer',
        'lecture_hours'       => 'decimal:1',
        'lab_hours'           => 'decimal:1',
        'load_units'          => 'decimal:2',
        'is_stem'             => 'boolean',
        'grade_level'         => 'integer',
        'sessions_per_week'   => 'integer',
        'minutes_per_session' => 'integer',
        'is_active'           => 'boolean',
        'has_ilp'             => 'boolean',
        'requires_computer_lab' => 'boolean',
    ];
```

- [ ] **Step 5: Run test to verify it still fails (model done, controller not yet)**

Run the same phpunit command as Step 2.
Expected: FAIL — controller's `validate()` call doesn't have an `is_stem` rule yet, so it's still stripped from `$data` before `Subject::create()`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_01_140000_add_is_stem_to_subjects_table.php app/Models/FacultyLoading/Subject.php tests/Feature/FacultyLoading/SubjectControllerTest.php
git commit -m "feat(faculty-loading): add is_stem column to subjects"
```

---

### Task 2: SubjectController — validate, index, copy-from-year

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/SubjectController.php`
- Test: `tests/Feature/FacultyLoading/SubjectControllerTest.php` (test written in Task 1, Step 1 — now make it pass)

**Interfaces:**
- Consumes: `Subject::$fillable` includes `is_stem` (Task 1).
- Produces: `SubjectController::index()`'s `subjects` prop includes `is_stem` per row; `store()`/`update()` accept and persist `is_stem`; `copyFromYear()` carries it across school years.

- [ ] **Step 1: Add `is_stem` to the store() validation rules**

In `app/Http/Controllers/FacultyLoading/SubjectController.php::store()`, add to the `$request->validate([...])` array, right after `'subject_type'`:

```php
            'subject_type'         => 'required|in:lecture,laboratory,lecture_lab,research,elective,science_core',
            'is_stem'              => 'boolean',
```

- [ ] **Step 2: Same for update()**

Identical one-line addition to `update()`'s validation array.

- [ ] **Step 3: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/SubjectControllerTest.php --filter test_is_stem_is_saved_on_create_and_update"`
Expected: PASS

- [ ] **Step 4: Expose `is_stem` in index()'s subjects mapping**

In `index()`, add to the `$subjects = $subjectList->map(fn ($s) => [...])` array, right after `'subject_type' => $s->subject_type,`:

```php
            'subject_type'        => $s->subject_type,
            'is_stem'             => $s->is_stem,
```

- [ ] **Step 5: Carry `is_stem` through copyFromYear()**

In `copyFromYear()`, add to the `Subject::create([...])` array, right after `'subject_type' => $s->subject_type,`:

```php
                'subject_type'        => $s->subject_type,
                'is_stem'             => $s->is_stem,
```

- [ ] **Step 6: Write a test for copy-from-year carrying is_stem**

Add to `SubjectControllerTest.php`:

```php
    public function test_copy_from_year_carries_is_stem(): void
    {
        $user = $this->userWith('faculty_loading.subjects.manage');
        Subject::create($this->subjectPayload(['is_stem' => true]));

        $targetYear = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30',
            'is_current' => false, 'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.copy-from-year'), [
                'source_school_year_id' => $this->sy->id,
                'target_school_year_id' => $targetYear->id,
            ])
            ->assertRedirect();

        $copied = Subject::where('school_year_id', $targetYear->id)->where('code', 'PE1-G7')->firstOrFail();
        $this->assertTrue($copied->is_stem);
    }
```

- [ ] **Step 7: Run the full test file**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/SubjectControllerTest.php"`
Expected: PASS, all tests in the file.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/SubjectController.php tests/Feature/FacultyLoading/SubjectControllerTest.php
git commit -m "feat(faculty-loading): accept and expose is_stem in Subject admin"
```

---

### Task 3: Subjects/Index.vue — STEM checkbox and badge

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Subjects/Index.vue`

**Interfaces:**
- Consumes: `subjects[].is_stem` (boolean, Task 2).

- [ ] **Step 1: Add `is_stem` to the form's useForm() defaults**

In the `useForm({...})` call (around line 59-66), add `is_stem: false,` right after `subject_type: 'lecture',`:

```js
const form  = useForm({
  id: null, school_year_id: props.currentSchoolYearId,
  code: '', name: '', description: '', specialization_tags: '',
  credit_units: 3, load_units: 3,
  lecture_hours: 3, lab_hours: 0, subject_type: 'lecture', is_stem: false, grade_level: 7, subject_group: '',
  semester: 'both', sessions_per_week: 5, minutes_per_session: 60, is_active: true, has_ilp: false,
  requires_computer_lab: false,
})
```

- [ ] **Step 2: Carry `is_stem` through openForm()'s edit path and reset path**

In `openForm(s = null)`, add `is_stem: s.is_stem,` to the `Object.assign(form, {...})` call (edit branch), and `form.is_stem = false` to the reset branch (alongside `form.has_ilp = false`):

```js
function openForm(s = null) {
  if (s) {
    Object.assign(form, { id: s.id, school_year_id: props.currentSchoolYearId,
      code: s.code, name: s.name, description: s.description ?? '',
      specialization_tags: s.specialization_tags ?? '',
      credit_units: s.credit_units, load_units: s.load_units, lecture_hours: s.lecture_hours,
      lab_hours: s.lab_hours ?? 0, subject_type: s.subject_type, is_stem: s.is_stem, grade_level: s.grade_level,
      subject_group: s.subject_group ?? '',
      semester: s.semester, sessions_per_week: s.sessions_per_week,
      minutes_per_session: s.minutes_per_session, is_active: s.is_active, has_ilp: s.has_ilp,
      requires_computer_lab: s.requires_computer_lab })
  } else {
    form.reset()
    form.id = null
    form.school_year_id = props.currentSchoolYearId
    form.grade_level = 7; form.subject_type = 'lecture'
    form.is_stem = false
    form.subject_group = ''
    form.credit_units = 3; form.load_units = 3; form.lecture_hours = 3
    form.sessions_per_week = 5; form.minutes_per_session = 60; form.is_active = true
    form.has_ilp = false
    form.requires_computer_lab = false
    form.semester = 'both'
  }
  modal.value = true
}
```

- [ ] **Step 3: Add the checkbox to the modal**

In the modal template, right after the `sub-active` checkbox block (around line 323-326), add:

```html
          <div class="flex items-center gap-2 pt-6">
            <input v-model="form.is_active" type="checkbox" id="sub-active" class="rounded text-indigo-600" />
            <label for="sub-active" class="text-sm text-slate-600">Active</label>
          </div>
          <div class="flex items-center gap-2 pt-6">
            <input v-model="form.is_stem" type="checkbox" id="sub-stem" class="rounded text-indigo-600" />
            <label for="sub-stem" class="text-sm text-slate-600">STEM Subject</label>
          </div>
```

- [ ] **Step 4: Add a STEM badge to the table row and mobile card**

In the table row's status cell (around line 223-228), add the badge alongside the existing ComLab one:

```html
          <td class="px-4 py-3 text-center">
            <div class="flex flex-col items-center gap-1">
              <AppBadge :color="s.is_active ? 'green' : 'slate'">{{ s.is_active ? 'Active' : 'Inactive' }}</AppBadge>
              <AppBadge v-if="s.requires_computer_lab" color="indigo">ComLab priority</AppBadge>
              <AppBadge v-if="s.is_stem" color="purple">STEM</AppBadge>
            </div>
          </td>
```

And in the `#mobileCard` template's type-badge row (around line 246-251), add the same badge:

```html
            <div class="flex items-center gap-2">
              <AppBadge :color="typeBadge(s.subject_type)">{{ s.subject_type }}</AppBadge>
              <AppBadge v-if="s.subject_group" color="purple">{{ s.subject_group }}</AppBadge>
              <AppBadge v-if="s.is_stem" color="purple">STEM</AppBadge>
              <span class="text-xs text-slate-500">Grade {{ s.grade_level === 0 ? '—' : s.grade_level }}</span>
              <span class="text-xs text-slate-500">{{ s.load_units }} units</span>
            </div>
```

- [ ] **Step 5: Build the frontend**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Subjects/Index.vue
git commit -m "feat(faculty-loading): add STEM Subject checkbox to Subject admin"
```

---

### Task 4: New columns on `class_schedule_day_adjustments`

**Files:**
- Create: `database/migrations/2026_09_01_150000_add_early_start_stem_split_fields_to_class_schedule_day_adjustments.php`

**Interfaces:**
- Produces: `class_schedule_day_adjustments.day_start_time` (time, nullable), `.stem_class_duration_minutes` (unsigned smallint, nullable), `.non_stem_class_duration_minutes` (unsigned smallint, nullable), `.health_break_title` (string, nullable), `.health_break_start_time`/`.health_break_end_time` (time, nullable).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields for the early-start STEM-split adjustment type: the campus-wide
     * target start time every section's first class period is anchored to,
     * per-subject-type period durations, and an optional Health Break band.
     * All nullable — unused by every other adjustment type.
     */
    public function up(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->time('day_start_time')->nullable()->after('class_duration_minutes');
            $table->unsignedSmallInteger('stem_class_duration_minutes')->nullable()->after('day_start_time');
            $table->unsignedSmallInteger('non_stem_class_duration_minutes')->nullable()->after('stem_class_duration_minutes');
            $table->string('health_break_title')->nullable()->after('non_stem_class_duration_minutes');
            $table->time('health_break_start_time')->nullable()->after('health_break_title');
            $table->time('health_break_end_time')->nullable()->after('health_break_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'day_start_time',
                'stem_class_duration_minutes',
                'non_stem_class_duration_minutes',
                'health_break_title',
                'health_break_start_time',
                'health_break_end_time',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_01_150000_add_early_start_stem_split_fields_to_class_schedule_day_adjustments.php"`
Expected: migration runs cleanly.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_09_01_150000_add_early_start_stem_split_fields_to_class_schedule_day_adjustments.php
git commit -m "feat(faculty-loading): add early-start STEM-split columns to class_schedule_day_adjustments"
```

---

### Task 5: `ClassScheduleDayAdjustment` model helpers

**Files:**
- Modify: `app/Models/FacultyLoading/ClassScheduleDayAdjustment.php`

**Interfaces:**
- Consumes: new columns from Task 4.
- Produces: `hasShortenedClasses()` includes `'early_start_stem_split'`; new `isEarlyStartStemSplit(): bool`; new `hasHealthBreak(): bool`.

- [ ] **Step 1: Add new columns to `$fillable` and `$casts`**

```php
    protected $fillable = [
        'academic_term_id',
        'postponed_from_date',
        'effective_date',
        'adjustment_type',
        'grade_levels',
        'ceremony_start_time',
        'ceremony_end_time',
        'shift_minutes',
        'activity_title',
        'activity_start_time',
        'activity_end_time',
        'class_duration_minutes',
        'day_start_time',
        'stem_class_duration_minutes',
        'non_stem_class_duration_minutes',
        'health_break_title',
        'health_break_start_time',
        'health_break_end_time',
        'reason',
        'status',
        'schedule_snapshot',
        'created_by',
        'published_by',
        'published_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'postponed_from_date' => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'grade_levels' => 'array',
        'shift_minutes' => 'integer',
        'class_duration_minutes' => 'integer',
        'stem_class_duration_minutes' => 'integer',
        'non_stem_class_duration_minutes' => 'integer',
        'schedule_snapshot' => 'array',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
```

- [ ] **Step 2: Update `hasShortenedClasses()` and add the two new helpers**

```php
    public function hasShortenedClasses(): bool
    {
        return in_array($this->adjustment_type, [
            'shortened_classes',
            'flag_ceremony_shortened_classes',
            'shortened_classes_protect_assessments',
            'early_start_stem_split',
        ], true);
    }

    public function protectsAssessmentPeriods(): bool
    {
        return $this->adjustment_type === 'shortened_classes_protect_assessments';
    }

    public function isEarlyStartStemSplit(): bool
    {
        return $this->adjustment_type === 'early_start_stem_split';
    }

    public function hasHealthBreak(): bool
    {
        return filled($this->health_break_title)
            && filled($this->health_break_start_time)
            && filled($this->health_break_end_time);
    }
```

- [ ] **Step 3: Write a quick unit assertion (no separate test file needed — covered by Task 6/7's feature tests)**

This task has no independently-testable behavior on its own (it's plumbing consumed by Task 6). Verify with a lint-only check:

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -l app/Models/FacultyLoading/ClassScheduleDayAdjustment.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Models/FacultyLoading/ClassScheduleDayAdjustment.php
git commit -m "feat(faculty-loading): add early-start STEM-split helpers to ClassScheduleDayAdjustment"
```

---

### Task 6: `AdjustedClassScheduleService` — anchor shift, STEM/non-STEM target, Lunch drop, Health Break band

**Files:**
- Modify: `app/Services/FacultyLoading/AdjustedClassScheduleService.php`
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `ClassScheduleDayAdjustment::isEarlyStartStemSplit()`, `::hasHealthBreak()` (Task 5); `ClassSchedule->subject->is_stem` (Task 1, already eager-loaded by the existing query).
- Produces: `generate()` output unchanged in shape — same `grades[].sections[].entries[]`/`bands[]`, `calendar_start` — for `early_start_stem_split` the entries/bands reflect the new anchor+split behavior.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php` (same class, reuses `setUp()`'s fixtures — `$this->tuesdayClass` is MATH7, section "Aquamarine", grade 7, Tuesday 07:30-08:20):

```php
    public function test_early_start_stem_split_anchors_first_period_and_splits_duration_by_subject(): void
    {
        Subject::where('code', 'MATH7')->update(['is_stem' => true]);

        $section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $filipino = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'FIL1-G7',
            'name' => 'Filipino 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $filipino->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:20',
            'end_time' => '09:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-04',
            'health_break_title' => 'Snack Break',
            'health_break_start_time' => '09:20',
            'health_break_end_time' => '09:30',
            'reason' => 'Heat advisory early start',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertSame('07:00:00', $adjustment->day_start_time);
        $this->assertSame(50, $adjustment->stem_class_duration_minutes);
        $this->assertSame(30, $adjustment->non_stem_class_duration_minutes);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $section = collect($adjustment->fresh()->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0];

        // MATH7 is STEM: keeps its full 50-minute length, anchored to 07:00.
        $this->assertSame('07:00', $section['entries'][0]['start_time']);
        $this->assertSame('07:50', $section['entries'][0]['end_time']);
        // Filipino is non-STEM: compresses to 30 minutes, immediately after.
        $this->assertSame('07:50', $section['entries'][1]['start_time']);
        $this->assertSame('08:20', $section['entries'][1]['end_time']);

        // Lunch is dropped for this type; Health Break is added.
        $this->assertNotContains('LUNCH', array_column($section['bands'], 'type'));
        $this->assertContains('Snack Break', array_column($section['bands'], 'label'));

        $this->assertSame('07:00', $adjustment->fresh()->schedule_snapshot['calendar_start']);

        // The underlying weekly schedule is untouched.
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter test_early_start_stem_split_anchors_first_period_and_splits_duration_by_subject"`
Expected: FAIL — `'early_start_stem_split'` isn't a valid `adjustment_type` yet (422 validation error), or once the type is added, times won't yet reflect the anchor+split behavior.

- [ ] **Step 3: Implement — anchor shift + per-period target + Lunch drop + Health Break band + calendar_start**

In `app/Services/FacultyLoading/AdjustedClassScheduleService.php::generate()`:

Replace:
```php
        $hasFlag = $adjustment->hasFlagCeremony();
        $hasShortenedClasses = $adjustment->hasShortenedClasses();
        $shift = $hasFlag ? (int) $adjustment->shift_minutes : 0;
        $classDuration = $hasShortenedClasses ? (int) ($adjustment->class_duration_minutes ?: 30) : null;
        $activityStart = $hasShortenedClasses ? substr((string) $adjustment->activity_start_time, 0, 5) : null;
        $activityEnd = $hasShortenedClasses ? substr((string) $adjustment->activity_end_time, 0, 5) : null;
```

With:
```php
        $hasFlag = $adjustment->hasFlagCeremony();
        $hasShortenedClasses = $adjustment->hasShortenedClasses();
        $stemSplit = $adjustment->isEarlyStartStemSplit();
        $shift = $hasFlag ? (int) $adjustment->shift_minutes : 0;
        $classDuration = $hasShortenedClasses ? (int) ($adjustment->class_duration_minutes ?: 30) : null;
        $stemMinutes = $stemSplit ? (int) ($adjustment->stem_class_duration_minutes ?: 50) : null;
        $nonStemMinutes = $stemSplit ? (int) ($adjustment->non_stem_class_duration_minutes ?: 30) : null;
        $dayStartMinutes = $stemSplit
            ? SchedulingConstants::toMinutes(substr((string) ($adjustment->day_start_time ?: '07:00'), 0, 5))
            : null;
        $activityStart = $hasShortenedClasses ? substr((string) $adjustment->activity_start_time, 0, 5) : null;
        $activityEnd = $hasShortenedClasses ? substr((string) $adjustment->activity_end_time, 0, 5) : null;
```

Replace:
```php
            foreach ($sections->where('levelid', $gradeLevel) as $section) {
                $sectionSchedule = $scheduleRows->get($section->id) ?? collect();

                // Compression is measured against this section's OWN actual
                // scheduled times, not the idealized bell-schedule grid — real
                // timetables routinely drift from the canonical periods (see
                // test_same_section_period_drift_does_not_false_positive_room_conflict).
                // Anchoring to the canonical grid instead of reality let classes
                // compress to the wrong — sometimes zero — duration whenever a
                // section's actual periods didn't tile it exactly.
                $sectionSlots = $sectionSchedule
                    ->map(function (ClassSchedule $s) use ($classDuration, $protectedPairs) {
                        $isProtected = $protectedPairs && $s->subject_id
                            && $protectedPairs->has("{$s->section_id}:{$s->subject_id}");

                        return [
                            'start' => substr((string) $s->start_time, 0, 5),
                            'end' => substr((string) $s->end_time, 0, 5),
                            // null = this period keeps its original length (not
                            // compressed): either the day isn't shortened, or this
                            // period is protected by a major assessment plotted today.
                            'target' => $isProtected ? null : $classDuration,
                        ];
                    })
                    ->values()
                    ->all();

                $entries = $sectionSchedule
                    ->map(function (ClassSchedule $schedule) use ($sectionSlots, $shift, $overridesByScheduleId) {
```

With:
```php
            foreach ($sections->where('levelid', $gradeLevel) as $section) {
                $sectionSchedule = $scheduleRows->get($section->id) ?? collect();

                // For early-start STEM-split, every section is individually
                // anchored so its own first class period starts at the same
                // campus-wide day_start_time — different grades' sections
                // normally start their first period at different clock
                // times, so the shift needed to reach the same target start
                // differs per section.
                $sectionShift = $shift;
                if ($stemSplit && $sectionSchedule->isNotEmpty()) {
                    $firstOriginalStart = SchedulingConstants::toMinutes(
                        substr((string) $sectionSchedule->first()->start_time, 0, 5)
                    );
                    $sectionShift = $dayStartMinutes - $firstOriginalStart;
                }

                // Compression is measured against this section's OWN actual
                // scheduled times, not the idealized bell-schedule grid — real
                // timetables routinely drift from the canonical periods (see
                // test_same_section_period_drift_does_not_false_positive_room_conflict).
                // Anchoring to the canonical grid instead of reality let classes
                // compress to the wrong — sometimes zero — duration whenever a
                // section's actual periods didn't tile it exactly.
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

                $entries = $sectionSchedule
                    ->map(function (ClassSchedule $schedule) use ($sectionSlots, $sectionShift, $overridesByScheduleId) {
```

A few lines below, inside that same closure, replace:
```php
                            $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $sectionSlots, $shift);
                            $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $sectionSlots, $shift);
```

With:
```php
                            $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $sectionSlots, $sectionShift);
                            $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $sectionSlots, $sectionShift);
```

Then replace the bands block:
```php
                $bands = collect($bands)
                    ->when($hasShortenedClasses, fn ($items) => $items->reject(
                        fn (array $band) => in_array($band['type'] ?? '', ['CONSULT', 'ACTIVITY', 'FLAG_RETREAT'], true),
                    ))
                    ->map(fn (array $band) => [
                        ...$band,
                        'start' => $this->transformTime((string) $band['start'], $sectionSlots, $shift),
                        'end' => $this->transformTime((string) $band['end'], $sectionSlots, $shift),
                    ])
                    ->when($activityStart, fn ($items) => $items->filter(
                        fn (array $band) => $band['end'] <= $activityStart,
                    ))
                    ->sortBy('start')
                    ->values()
                    ->all();

                if ($activityStart && $activityEnd) {
                    $bands[] = [
                        'start' => $activityStart,
                        'end' => $activityEnd,
                        'type' => 'OFFICIAL_ACTIVITY',
                        'label' => $adjustment->activity_title,
                    ];
                }
```

With:
```php
                $rejectedBandTypes = $stemSplit
                    ? ['CONSULT', 'ACTIVITY', 'FLAG_RETREAT', 'LUNCH']
                    : ['CONSULT', 'ACTIVITY', 'FLAG_RETREAT'];

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
                    $bands[] = [
                        'start' => $activityStart,
                        'end' => $activityEnd,
                        'type' => 'OFFICIAL_ACTIVITY',
                        'label' => $adjustment->activity_title,
                    ];
                }

                if ($adjustment->hasHealthBreak()) {
                    $bands[] = [
                        'start' => substr((string) $adjustment->health_break_start_time, 0, 5),
                        'end' => substr((string) $adjustment->health_break_end_time, 0, 5),
                        'type' => 'HEALTH_BREAK',
                        'label' => $adjustment->health_break_title,
                    ];
                }
```

Finally, replace:
```php
            'calendar_start' => '07:30',
            'calendar_end' => '17:00',
```

With:
```php
            'calendar_start' => $stemSplit ? substr((string) ($adjustment->day_start_time ?: '07:00'), 0, 5) : '07:30',
            'calendar_end' => '17:00',
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter test_early_start_stem_split_anchors_first_period_and_splits_duration_by_subject"`
Expected: PASS

(This test still requires the controller changes from Task 8 to accept the new `adjustment_type`/fields — if it fails with a 422/session error at this point, that's expected; Task 8 makes it pass end-to-end. Re-run it again after Task 8.)

- [ ] **Step 5: Run the full adjustment test file to check for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"`
Expected: PASS, all tests (some may still fail until Task 8 lands — re-check after that task).

- [ ] **Step 6: Commit**

```bash
git add app/Services/FacultyLoading/AdjustedClassScheduleService.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "feat(faculty-loading): early-start STEM-split anchor shift, duration split, Lunch drop, Health Break band"
```

---

### Task 7: Decouple conflict detection from Official Activity

**Files:**
- Modify: `app/Services/FacultyLoading/AdjustedClassScheduleService.php`
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Consumes: `ClassScheduleDayAdjustment::hasShortenedClasses()` (existing).
- Produces: `generate()`'s `conflict_warnings` (and its blocking `ValidationException`) now run for every shortened-family day, not only when `activity_start_time` is set.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`:

```php
    public function test_early_start_stem_split_still_blocks_a_genuine_double_booking_with_no_activity_declared(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $otherGrade7Section = Section::create([
            'levelid' => 7,
            'sectionname' => 'Citrine',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);

        // Two sections genuinely double-booked into Room 101 at overlapping
        // original times — a real conflict unrelated to compression. This
        // must still block even though early_start_stem_split declares no
        // Official Activity at all (blocking is based on raw-time overlap,
        // which is unaffected by each section's own individual anchor shift).
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $grade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:00',
            'end_time' => '10:50',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $grade7Subject->id,
            'section_id' => $otherGrade7Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:20',
            'end_time' => '11:10',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'grade_levels' => [7, 8, 9, 10, 11, 12],
            'adjustment_type' => 'early_start_stem_split',
            'effective_date' => '2026-08-10',
            'reason' => 'Heat advisory early start',
        ])->assertSessionHasErrors('activity_start_time');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter test_early_start_stem_split_still_blocks_a_genuine_double_booking_with_no_activity_declared"`
Expected: FAIL — with no `activity_start_time` set, `generate()` currently skips `assertNoGeneratedConflicts()` entirely (gated on `if ($activityStart)`), so the store() call redirects successfully instead of raising a session error, and a row IS persisted.

- [ ] **Step 3: Implement**

In `generate()`, replace:
```php
        $conflictWarnings = [];

        if ($activityStart) {
            $lateEntry = collect($grades)
                ->flatMap(fn (array $grade) => $grade['sections'])
                ->flatMap(fn (array $section) => $section['entries'])
                ->first(fn (array $entry) => $entry['end_time'] > $activityStart);

            if ($lateEntry) {
                $label = $lateEntry['subject']['code'] ?? $lateEntry['subject']['name'] ?? 'A class';
                throw ValidationException::withMessages([
                    'activity_start_time' => "{$label} still ends at {$lateEntry['end_time']}. Choose a later activity start time.",
                ]);
            }

            $conflictWarnings = $this->assertNoGeneratedConflicts($grades);
        }
```

With:
```php
        $conflictWarnings = [];

        if ($activityStart) {
            $lateEntry = collect($grades)
                ->flatMap(fn (array $grade) => $grade['sections'])
                ->flatMap(fn (array $section) => $section['entries'])
                ->first(fn (array $entry) => $entry['end_time'] > $activityStart);

            if ($lateEntry) {
                $label = $lateEntry['subject']['code'] ?? $lateEntry['subject']['name'] ?? 'A class';
                throw ValidationException::withMessages([
                    'activity_start_time' => "{$label} still ends at {$lateEntry['end_time']}. Choose a later activity start time.",
                ]);
            }
        }

        // Conflict detection always runs for any shortened-family day, not
        // just when an Official Activity is declared — a plain early-start
        // day with no activity and no health break previously skipped this
        // check entirely.
        if ($hasShortenedClasses) {
            $conflictWarnings = $this->assertNoGeneratedConflicts($grades);
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter test_early_start_stem_split_still_blocks_a_genuine_double_booking_with_no_activity_declared"`
Expected: PASS (once Task 8's controller changes are also in place — this test needs `early_start_stem_split` accepted without a required activity).

- [ ] **Step 5: Run the full adjustment test file**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"`
Expected: PASS, all tests — confirms this change doesn't alter behavior for the 4 pre-existing types (they always had `activity_start_time` required already, so this is a no-op for them).

- [ ] **Step 6: Commit**

```bash
git add app/Services/FacultyLoading/AdjustedClassScheduleService.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "fix(faculty-loading): run adjusted-day conflict detection regardless of Official Activity"
```

---

### Task 8: Controller — accept the new type, validate new fields, coverage prop

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php`

**Interfaces:**
- Consumes: `Subject` model with `is_stem` (Task 1); `ClassScheduleDayAdjustment` new fillable columns (Task 5).
- Produces: `store()`/`update()` accept `adjustment_type = 'early_start_stem_split'` with optional activity fields and optional health-break fields; `index()`'s Inertia props include `stemSubjectCoverage: { tagged, total }`.

- [ ] **Step 1: Add the `Subject` import**

At the top of `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php`, add:

```php
use App\Models\FacultyLoading\Subject;
```

- [ ] **Step 2: Add `stemSubjectCoverage` to index()**

Replace:
```php
        return Inertia::render('FacultyLoading/Schedules/DayAdjustments', [
            'term' => $term ? [
                'id' => $term->id,
                'label' => $term->full_label,
                'start_date' => $term->start_date?->toDateString(),
                'end_date' => $term->end_date?->toDateString(),
            ] : null,
            'terms' => $terms,
            'adjustments' => $adjustments,
            'canManage' => $request->user()->hasPermission('faculty_loading.manage'),
        ]);
```

With:
```php
        $stemCoverage = $term
            ? [
                'tagged' => Subject::where('school_year_id', $term->school_year_id)->where('is_active', true)->where('is_stem', true)->count(),
                'total' => Subject::where('school_year_id', $term->school_year_id)->where('is_active', true)->count(),
            ]
            : ['tagged' => 0, 'total' => 0];

        return Inertia::render('FacultyLoading/Schedules/DayAdjustments', [
            'term' => $term ? [
                'id' => $term->id,
                'label' => $term->full_label,
                'start_date' => $term->start_date?->toDateString(),
                'end_date' => $term->end_date?->toDateString(),
            ] : null,
            'terms' => $terms,
            'adjustments' => $adjustments,
            'canManage' => $request->user()->hasPermission('faculty_loading.manage'),
            'stemSubjectCoverage' => $stemCoverage,
        ]);
```

- [ ] **Step 3: Extend validatedData()'s validation rules**

Replace:
```php
        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'adjustment_type' => ['nullable', 'in:flag_ceremony,shortened_classes,flag_ceremony_shortened_classes,shortened_classes_protect_assessments'],
            'grade_levels' => ['required', 'array', 'min:1'],
            'grade_levels.*' => ['integer', 'in:7,8,9,10,11,12'],
            'postponed_from_date' => ['nullable', 'date'],
            'effective_date' => ['required', 'date'],
            'activity_title' => ['nullable', 'string', 'max:255'],
            'activity_start_time' => ['nullable', 'date_format:H:i'],
            'activity_end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
```

With:
```php
        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'adjustment_type' => ['nullable', 'in:flag_ceremony,shortened_classes,flag_ceremony_shortened_classes,shortened_classes_protect_assessments,early_start_stem_split'],
            'grade_levels' => ['required', 'array', 'min:1'],
            'grade_levels.*' => ['integer', 'in:7,8,9,10,11,12'],
            'postponed_from_date' => ['nullable', 'date'],
            'effective_date' => ['required', 'date'],
            'activity_title' => ['nullable', 'string', 'max:255'],
            'activity_start_time' => ['nullable', 'date_format:H:i'],
            'activity_end_time' => ['nullable', 'date_format:H:i'],
            'day_start_time' => ['nullable', 'date_format:H:i'],
            'stem_class_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:60'],
            'non_stem_class_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:60'],
            'health_break_title' => ['nullable', 'string', 'max:255'],
            'health_break_start_time' => ['nullable', 'date_format:H:i'],
            'health_break_end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
```

- [ ] **Step 4: Default-fill early-start fields, skip them for other types**

Replace:
```php
        $data['adjustment_type'] ??= 'flag_ceremony';
        $selectedGrades = array_values(array_unique(array_map('intval', $data['grade_levels'])));
```

With:
```php
        $data['adjustment_type'] ??= 'flag_ceremony';
        if ($data['adjustment_type'] === 'early_start_stem_split') {
            $data['day_start_time'] ??= '07:00';
            $data['stem_class_duration_minutes'] ??= 50;
            $data['non_stem_class_duration_minutes'] ??= 30;
        } else {
            $data['day_start_time'] = null;
            $data['stem_class_duration_minutes'] = null;
            $data['non_stem_class_duration_minutes'] = null;
        }
        $selectedGrades = array_values(array_unique(array_map('intval', $data['grade_levels'])));
```

- [ ] **Step 5: Make Official Activity optional for early_start_stem_split; validate Health Break together**

Replace:
```php
        if ($this->hasShortenedClasses($data['adjustment_type'])) {
            foreach (['activity_title', 'activity_start_time', 'activity_end_time'] as $field) {
                if (empty($data[$field])) {
                    throw ValidationException::withMessages([
                        $field => 'This field is required for a shortened-class day.',
                    ]);
                }
            }

            if ($data['activity_end_time'] <= $data['activity_start_time']) {
                throw ValidationException::withMessages([
                    'activity_end_time' => 'The activity end time must be after its start time.',
                ]);
            }

            $allGradesOpen = collect($selectedGrades)
                ->every(fn (int $grade) => $this->calendar->isSchoolDay($data['effective_date'], $grade));
            if (! $allGradesOpen) {
                throw ValidationException::withMessages([
                    'effective_date' => 'The shortened-class adjustment must fall on a school day for every selected grade level.',
                ]);
            }
        } else {
            $data['activity_title'] = null;
            $data['activity_start_time'] = null;
            $data['activity_end_time'] = null;
        }
```

With:
```php
        if ($this->hasShortenedClasses($data['adjustment_type'])) {
            // The early-start STEM-split day doesn't require a declared
            // Official Activity — the point of the day is simply an earlier
            // start, not necessarily freeing time for a campus event.
            if ($data['adjustment_type'] !== 'early_start_stem_split') {
                foreach (['activity_title', 'activity_start_time', 'activity_end_time'] as $field) {
                    if (empty($data[$field])) {
                        throw ValidationException::withMessages([
                            $field => 'This field is required for a shortened-class day.',
                        ]);
                    }
                }

                if ($data['activity_end_time'] <= $data['activity_start_time']) {
                    throw ValidationException::withMessages([
                        'activity_end_time' => 'The activity end time must be after its start time.',
                    ]);
                }
            }

            $allGradesOpen = collect($selectedGrades)
                ->every(fn (int $grade) => $this->calendar->isSchoolDay($data['effective_date'], $grade));
            if (! $allGradesOpen) {
                throw ValidationException::withMessages([
                    'effective_date' => 'The shortened-class adjustment must fall on a school day for every selected grade level.',
                ]);
            }
        } else {
            $data['activity_title'] = null;
            $data['activity_start_time'] = null;
            $data['activity_end_time'] = null;
        }

        if ($data['health_break_title'] || $data['health_break_start_time'] || $data['health_break_end_time']) {
            foreach (['health_break_title', 'health_break_start_time', 'health_break_end_time'] as $field) {
                if (empty($data[$field])) {
                    throw ValidationException::withMessages([
                        $field => 'All three health break fields are required together.',
                    ]);
                }
            }

            if ($data['health_break_end_time'] <= $data['health_break_start_time']) {
                throw ValidationException::withMessages([
                    'health_break_end_time' => 'The health break end time must be after its start time.',
                ]);
            }
        }
```

- [ ] **Step 6: Add the new type to the private `hasShortenedClasses()` helper**

Replace:
```php
    private function hasShortenedClasses(string $type): bool
    {
        return in_array($type, [
            'shortened_classes',
            'flag_ceremony_shortened_classes',
            'shortened_classes_protect_assessments',
        ], true);
    }
```

With:
```php
    private function hasShortenedClasses(string $type): bool
    {
        return in_array($type, [
            'shortened_classes',
            'flag_ceremony_shortened_classes',
            'shortened_classes_protect_assessments',
            'early_start_stem_split',
        ], true);
    }
```

- [ ] **Step 7: Run the two tests written in Tasks 6 and 7 (now should fully pass)**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php --filter 'test_early_start_stem_split'"`
Expected: PASS — both `test_early_start_stem_split_anchors_first_period_and_splits_duration_by_subject` and `test_early_start_stem_split_still_blocks_a_genuine_double_booking_with_no_activity_declared`.

- [ ] **Step 8: Run the full adjustment test file for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M -c /dev/null vendor/bin/phpunit tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php"`
Expected: PASS, all tests.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php
git commit -m "feat(faculty-loading): accept early_start_stem_split in the day-adjustments controller"
```

---

### Task 9: DayAdjustments.vue — new type in the create/edit form

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/DayAdjustments.vue`

**Interfaces:**
- Consumes: `stemSubjectCoverage` prop (Task 8).
- Produces: form can create/edit `early_start_stem_split` adjustments with day-start time, STEM/non-STEM duration overrides, and an optional Health Break.

- [ ] **Step 1: Add the prop**

In `defineProps({...})`, add:

```js
const props = defineProps({
  term: { type: Object, default: null },
  terms: { type: Array, default: () => [] },
  adjustments: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  stemSubjectCoverage: { type: Object, default: () => ({ tagged: 0, total: 0 }) },
})
```

- [ ] **Step 2: Add form fields with defaults**

In `useForm({...})`, add the new fields after `activity_end_time`:

```js
const form = useForm({
  academic_term_id: props.term?.id ?? null,
  adjustment_type: 'flag_ceremony',
  grade_levels: [...ALL_GRADES],
  postponed_from_date: '',
  effective_date: '',
  activity_title: '',
  activity_start_time: '13:00',
  activity_end_time: '17:00',
  day_start_time: '07:00',
  stem_class_duration_minutes: 50,
  non_stem_class_duration_minutes: 30,
  health_break_title: '',
  health_break_start_time: '',
  health_break_end_time: '',
  reason: '',
})
```

- [ ] **Step 3: Carry the fields through openCreate()/openEdit()**

In `openCreate()`, add after `form.activity_end_time = '17:00'`:

```js
  form.day_start_time = '07:00'
  form.stem_class_duration_minutes = 50
  form.non_stem_class_duration_minutes = 30
  form.health_break_title = ''
  form.health_break_start_time = ''
  form.health_break_end_time = ''
```

In `openEdit(item)`, add after `form.activity_end_time = item.activity_end_time ?? '17:00'`:

```js
  form.day_start_time = item.day_start_time ?? '07:00'
  form.stem_class_duration_minutes = item.stem_class_duration_minutes ?? 50
  form.non_stem_class_duration_minutes = item.non_stem_class_duration_minutes ?? 30
  form.health_break_title = item.health_break_title ?? ''
  form.health_break_start_time = item.health_break_start_time ?? ''
  form.health_break_end_time = item.health_break_end_time ?? ''
```

- [ ] **Step 4: Add the new `<option>` and helper functions**

In the type `<select>`, add after the last option:

```html
            <option value="shortened_classes_protect_assessments">30-Minute Classes (Protect Assessment Periods)</option>
            <option value="early_start_stem_split">Early Start — STEM/Non-STEM Split (7 AM)</option>
```

Add a new helper next to `protectsAssessments()`:

```js
function isEarlyStartStemSplit(type) {
  return type === 'early_start_stem_split'
}
```

Update `adjustmentTypeLabel()`:

```js
function adjustmentTypeLabel(type) {
  return ({
    flag_ceremony: 'Transferred Flag Ceremony',
    shortened_classes: '30-Minute Classes',
    flag_ceremony_shortened_classes: 'Flag Ceremony + 30-Minute Classes',
    shortened_classes_protect_assessments: '30-Minute Classes (Protect Assessments)',
    early_start_stem_split: 'Early Start — STEM/Non-STEM Split',
  })[type] ?? type
}
```

- [ ] **Step 5: Add the conditional field block in the modal**

Right after the existing `v-if="hasShortenedClasses(form.adjustment_type)"` block (the Official Activity fields), add a new block:

```html
        <div v-if="isEarlyStartStemSplit(form.adjustment_type)" class="space-y-4 rounded-xl border border-purple-100 bg-purple-50/50 p-4">
          <div v-if="stemSubjectCoverage.total > 0" class="rounded-lg border border-purple-200 bg-white px-3 py-2 text-xs text-purple-800">
            {{ stemSubjectCoverage.tagged }} of {{ stemSubjectCoverage.total }} active subjects are tagged STEM.
            <span v-if="stemSubjectCoverage.tagged === 0" class="font-semibold">No subjects are tagged yet — every class will compress to the non-STEM duration. Tag subjects in the Subject Catalog first.</span>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Day starts</label>
              <input v-model="form.day_start_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">STEM period (min)</label>
              <input v-model.number="form.stem_class_duration_minutes" type="number" min="10" max="60"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Non-STEM period (min)</label>
              <input v-model.number="form.non_stem_class_duration_minutes" type="number" min="10" max="60"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Health break (optional)</label>
            <input v-model="form.health_break_title" type="text" placeholder="Example: Snack Break"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p v-if="form.errors.health_break_title" class="mt-1 text-xs text-rose-600">{{ form.errors.health_break_title }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Break starts</label>
              <input v-model="form.health_break_start_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.health_break_start_time" class="mt-1 text-xs text-rose-600">{{ form.errors.health_break_start_time }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Break ends</label>
              <input v-model="form.health_break_end_time" type="time"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors.health_break_end_time" class="mt-1 text-xs text-rose-600">{{ form.errors.health_break_end_time }}</p>
            </div>
          </div>
          <p class="text-xs text-slate-500">Lunch is not plotted on this type of adjusted day. Leave the break blank to skip it entirely.</p>
        </div>
```

Note: this block sits alongside the existing Official Activity block (both can show at once, since `hasShortenedClasses()` now also returns true for `early_start_stem_split` — Official Activity stays visible and optional for this type, Health Break is the new addition).

- [ ] **Step 6: Add a details line to the adjustments table**

In the table's "Details" cell, add a branch for the new type:

```html
                <td class="px-4 py-3 text-sm text-slate-600">
                  <div v-if="hasFlag(item.adjustment_type)">Flag 7:30–8:00 AM<span v-if="item.postponed_from_date"> · From {{ formatDate(item.postponed_from_date) }}</span></div>
                  <div v-if="item.adjustment_type === 'early_start_stem_split'">
                    Starts {{ item.day_start_time }} · STEM {{ item.stem_class_duration_minutes }}min / Non-STEM {{ item.non_stem_class_duration_minutes }}min
                    <span v-if="item.health_break_title"> · {{ item.health_break_title }} {{ formatTimeRange(item.health_break_start_time, item.health_break_end_time) }}</span>
                  </div>
                  <div v-else-if="hasShortenedClasses(item.adjustment_type)">
                    30-minute classes<span v-if="protectsAssessments(item.adjustment_type)"> (major assessment periods stay 50 min)</span> · {{ item.activity_title }} · {{ formatTimeRange(item.activity_start_time, item.activity_end_time) }}
                  </div>
                </td>
```

This needs `item.day_start_time`, `item.stem_class_duration_minutes`, `item.non_stem_class_duration_minutes`, `item.health_break_title`, `item.health_break_start_time`, `item.health_break_end_time` in the `adjustments` Inertia prop — add these to `ClassScheduleDayAdjustmentController::index()`'s `$adjustments` mapping:

```php
                'class_duration_minutes' => $adjustment->class_duration_minutes,
                'day_start_time' => $adjustment->day_start_time ? substr((string) $adjustment->day_start_time, 0, 5) : null,
                'stem_class_duration_minutes' => $adjustment->stem_class_duration_minutes,
                'non_stem_class_duration_minutes' => $adjustment->non_stem_class_duration_minutes,
                'health_break_title' => $adjustment->health_break_title,
                'health_break_start_time' => $adjustment->health_break_start_time ? substr((string) $adjustment->health_break_start_time, 0, 5) : null,
                'health_break_end_time' => $adjustment->health_break_end_time ? substr((string) $adjustment->health_break_end_time, 0, 5) : null,
```

(This edit belongs in `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php::index()`'s `$adjustments` map, right after the existing `class_duration_minutes` line.)

- [ ] **Step 7: Build the frontend**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/DayAdjustments.vue app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php
git commit -m "feat(faculty-loading): early-start STEM-split fields in the Day Adjustments form"
```

---

### Task 10: `AdjustedDayCalendar.vue` — new component, static render (bands + entries)

**Files:**
- Create: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`
- Modify: `app/Models/FacultyLoading/ClassSchedule.php`

**Interfaces:**
- Consumes: `preview` object shaped exactly like `generate()`'s return value (`grades[].sections[].entries[]`/`bands[]`, `calendar_start`, `calendar_end`).
- Produces: new component with props `{ preview: Object }`, purely presentational in this task (no interactivity yet — that's Tasks 11-12). Renders one card per grade, one column per section, entries and bands as absolutely-positioned blocks on a shared time axis.

- [ ] **Step 1: Expose `is_stem` in `toCalendarArray()`'s subject sub-array**

The calendar needs to know which entries are STEM to badge them. In `app/Models/FacultyLoading/ClassSchedule.php::toCalendarArray()`, replace:

```php
            'subject'            => $this->subject ? [
                'id'          => $this->subject->id,
                'code'        => $this->subject->code,
                'name'        => $this->subject->name,
                'is_elective' => $this->subject->grade_level === 0 || $this->subject->subject_type === 'elective',
                'is_science_core' => $this->subject->subject_type === 'science_core',
            ] : null,
```

With:
```php
            'subject'            => $this->subject ? [
                'id'          => $this->subject->id,
                'code'        => $this->subject->code,
                'name'        => $this->subject->name,
                'is_elective' => $this->subject->grade_level === 0 || $this->subject->subject_type === 'elective',
                'is_science_core' => $this->subject->subject_type === 'science_core',
                'is_stem'     => (bool) $this->subject->is_stem,
            ] : null,
```

- [ ] **Step 2: Write the component**

```vue
<template>
  <div class="space-y-4">
    <div v-for="grade in gradesWithEntries" :key="grade.grade_level" class="rounded-xl border border-slate-200 bg-white p-4">
      <h3 class="mb-3 text-sm font-semibold text-slate-700">Grade {{ grade.grade_level }}</h3>
      <div class="flex gap-3 overflow-x-auto pb-2">
        <div v-for="section in grade.sections" :key="section.id" class="w-56 shrink-0">
          <div class="mb-1 text-center text-xs font-semibold text-slate-500">{{ section.name }}</div>
          <div
            class="relative rounded-lg border border-slate-100 bg-slate-50"
            :style="{ height: `${totalMinutes * PX_PER_MINUTE}px` }"
            :data-section-id="section.id"
            @dragover.prevent="onDragOver($event, section)"
            @drop.prevent="onDrop($event, section)"
          >
            <div
              v-for="band in section.bands"
              :key="`${band.type}-${band.start}`"
              class="absolute inset-x-0 rounded bg-slate-200/60 px-1.5 py-0.5 text-[10px] text-slate-500"
              :style="bandStyle(band)"
            >
              {{ band.label }}
            </div>
            <div
              v-for="entry in section.entries"
              :key="entry.id"
              :draggable="true"
              class="absolute inset-x-1 cursor-grab rounded-md border px-2 py-1 text-xs shadow-sm active:cursor-grabbing"
              :class="entryClass(entry)"
              :style="entryStyle(entry)"
              :data-entry-id="entry.id"
              @dragstart="onDragStart($event, entry, section)"
              @click="$emit('edit-entry', entry)"
            >
              <div class="flex items-center justify-between gap-1">
                <span class="truncate font-medium">{{ entry.subject?.name ?? entry.title ?? '—' }}</span>
                <span v-if="entry.subject?.is_stem" class="shrink-0 rounded-full bg-purple-100 px-1.5 text-[9px] font-semibold text-purple-700">STEM</span>
              </div>
              <div class="text-[10px] text-slate-500">{{ entry.start_time }}–{{ entry.end_time }} · {{ entry.classroom?.name ?? '—' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  preview: { type: Object, required: true },
})

defineEmits(['edit-entry', 'move-entry'])

const PX_PER_MINUTE = 1.5

const gradesWithEntries = computed(() => (props.preview.grades ?? []).filter(grade => grade.sections?.length))

const calendarStartMinutes = computed(() => toMinutes(props.preview.calendar_start ?? '07:30'))
const calendarEndMinutes = computed(() => toMinutes(props.preview.calendar_end ?? '17:00'))
const totalMinutes = computed(() => calendarEndMinutes.value - calendarStartMinutes.value)

function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function offsetStyle(startHHMM, endHHMM) {
  const top = (toMinutes(startHHMM) - calendarStartMinutes.value) * PX_PER_MINUTE
  const height = Math.max(16, (toMinutes(endHHMM) - toMinutes(startHHMM)) * PX_PER_MINUTE)
  return { top: `${top}px`, height: `${height}px` }
}

function entryStyle(entry) {
  return offsetStyle(entry.start_time, entry.end_time)
}

function bandStyle(band) {
  return offsetStyle(band.start, band.end)
}

function entryClass(entry) {
  return entry.manually_adjusted
    ? 'border-indigo-300 bg-indigo-50 text-indigo-800'
    : 'border-slate-200 bg-white text-slate-700'
}

defineExpose({ toMinutes, calendarStartMinutes })
</script>
```

- [ ] **Step 3: Build the frontend**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors. (The `@dragover`/`@drop`/`onDragStart`/`onDragOver`/`onDrop` handlers referenced in the template don't exist yet — Vue's `<script setup>` will throw a build error for undefined template refs. Stub them now so this task's build passes cleanly:)

Add these three no-op stubs to the `<script setup>` block (Task 11 replaces them with real logic):

```js
function onDragStart(event, entry, section) {
  event.dataTransfer.setData('text/plain', String(entry.id))
}
function onDragOver(event, section) {}
function onDrop(event, section) {}
```

Run the build again.
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue app/Models/FacultyLoading/ClassSchedule.php
git commit -m "feat(faculty-loading): add AdjustedDayCalendar.vue static render (bands + entries)"
```

---

### Task 11: Drag-to-move with live conflict pre-check, wired to the override endpoint

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`

**Interfaces:**
- Consumes: `POST faculty-loading.schedules.day-adjustments.overrides.store` (existing route, existing controller action `upsertOverride` — no backend change).
- Produces: emits `update:preview` with the fresh `generate()` result returned by the override endpoint, so the parent page's warnings banner updates live.

- [ ] **Step 1: Add the `adjustment` prop and the drag/drop implementation**

Replace the three stub functions from Task 10 with:

```js
import { computed, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  preview: { type: Object, required: true },
  adjustment: { type: Object, required: true },
})

const emit = defineEmits(['edit-entry', 'update:preview'])

const PX_PER_MINUTE = 1.5
const SNAP_MINUTES = 5

const dragging = ref(null) // { entry, durationMinutes, section }

const gradesWithEntries = computed(() => (props.preview.grades ?? []).filter(grade => grade.sections?.length))

const calendarStartMinutes = computed(() => toMinutes(props.preview.calendar_start ?? '07:30'))
const calendarEndMinutes = computed(() => toMinutes(props.preview.calendar_end ?? '17:00'))
const totalMinutes = computed(() => calendarEndMinutes.value - calendarStartMinutes.value)

function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function fromMinutes(total) {
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function offsetStyle(startHHMM, endHHMM) {
  const top = (toMinutes(startHHMM) - calendarStartMinutes.value) * PX_PER_MINUTE
  const height = Math.max(16, (toMinutes(endHHMM) - toMinutes(startHHMM)) * PX_PER_MINUTE)
  return { top: `${top}px`, height: `${height}px` }
}

function entryStyle(entry) {
  return offsetStyle(entry.start_time, entry.end_time)
}

function bandStyle(band) {
  return offsetStyle(band.start, band.end)
}

function entryClass(entry) {
  return entry.manually_adjusted
    ? 'border-indigo-300 bg-indigo-50 text-indigo-800'
    : 'border-slate-200 bg-white text-slate-700'
}

function allEntries() {
  return gradesWithEntries.value.flatMap(grade => grade.sections).flatMap(section => section.entries)
}

// Live client-side pre-check only — purely advisory (drives the drop
// target's highlight color). The server call on drop is authoritative;
// this never blocks a drop, it only colors it.
function wouldConflict(entry, proposedStartMinutes, proposedEndMinutes) {
  return allEntries().some(other => {
    if (other.id === entry.id) return false
    const sameRoom = other.classroom?.id && entry.classroom?.id && other.classroom.id === entry.classroom.id
    const sameFaculty = other.faculty?.id && entry.faculty?.id && other.faculty.id === entry.faculty.id
    if (!sameRoom && !sameFaculty) return false
    const otherStart = toMinutes(other.start_time)
    const otherEnd = toMinutes(other.end_time)
    return proposedStartMinutes < otherEnd && otherStart < proposedEndMinutes
  })
}

function onDragStart(event, entry, section) {
  const durationMinutes = toMinutes(entry.end_time) - toMinutes(entry.start_time)
  dragging.value = { entry, durationMinutes, section }
  event.dataTransfer.setData('text/plain', String(entry.id))
  event.dataTransfer.effectAllowed = 'move'
}

function proposedStartMinutes(event, columnEl) {
  const rect = columnEl.getBoundingClientRect()
  const offsetY = event.clientY - rect.top
  const rawMinutes = calendarStartMinutes.value + offsetY / PX_PER_MINUTE
  return Math.round(rawMinutes / SNAP_MINUTES) * SNAP_MINUTES
}

function onDragOver(event, section) {
  if (!dragging.value) return
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + dragging.value.durationMinutes
  event.currentTarget.dataset.previewConflict = wouldConflict(dragging.value.entry, start, end) ? '1' : '0'
}

async function onDrop(event, section) {
  if (!dragging.value) return
  const { entry, durationMinutes } = dragging.value
  const start = proposedStartMinutes(event, event.currentTarget)
  const end = start + durationMinutes
  dragging.value = null
  delete event.currentTarget.dataset.previewConflict

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

- [ ] **Step 2: Add a visual conflict cue to the column while dragging**

In the template, add `:class="{ 'ring-2 ring-rose-300': $el?.dataset?.previewConflict === '1' }"` is awkward with a plain DOM dataset read in the template — use a small reactive flag instead. Replace the column `<div>` opening tag:

```html
          <div
            class="relative rounded-lg border border-slate-100 bg-slate-50"
            :style="{ height: `${totalMinutes * PX_PER_MINUTE}px` }"
            :data-section-id="section.id"
            @dragover.prevent="onDragOver($event, section)"
            @drop.prevent="onDrop($event, section)"
          >
```

With (introduce a `conflictSectionId` ref instead of a DOM dataset):

```html
          <div
            class="relative rounded-lg border bg-slate-50"
            :class="conflictSectionId === section.id ? 'border-rose-300 ring-2 ring-rose-200' : 'border-slate-100'"
            :style="{ height: `${totalMinutes * PX_PER_MINUTE}px` }"
            @dragover.prevent="onDragOver($event, section)"
            @dragleave="conflictSectionId = null"
            @drop.prevent="onDrop($event, section)"
          >
```

And in the script, add `const conflictSectionId = ref(null)` next to `dragging`, and update `onDragOver`/`onDrop` to set/clear it instead of writing to `dataset`:

```js
const dragging = ref(null)
const conflictSectionId = ref(null)
```

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

- [ ] **Step 3: Build the frontend**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue
git commit -m "feat(faculty-loading): drag-to-move with live conflict pre-check in AdjustedDayCalendar"
```

---

### Task 12: Click-to-edit precision popover

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue`

**Interfaces:**
- Reuses: same `overrides.store`/`overrides.destroy` endpoints as drag (Task 11).
- Produces: clicking an entry (instead of dragging it) opens a modal to type an exact start/end time — same precision-editing capability `ResolveConflicts.vue` already has today, ported into the new component so `ResolveConflicts.vue` can drop its own copy in Task 13.

- [ ] **Step 1: Add the modal markup**

At the end of the component's template (as a sibling of the top-level `<div class="space-y-4">`, so wrap both in a fragment or a shared root — since Vue 3 `<script setup>` allows multiple root nodes, add this after the closing `</div>` of the grades loop):

```html
  <AppModal :show="showOverrideModal" title="Adjust class time" size="sm" @close="showOverrideModal = false">
    <div v-if="editingEntry" class="space-y-4">
      <p class="text-sm text-slate-600">
        {{ editingEntry.subject?.name ?? editingEntry.title }} — currently {{ editingEntry.start_time }}–{{ editingEntry.end_time }}
      </p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">New start time</label>
          <input v-model="overrideForm.override_start_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">New end time</label>
          <input v-model="overrideForm.override_end_time" type="time"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      <p v-if="overrideError" class="text-xs text-rose-600">{{ overrideError }}</p>
    </div>

    <template #footer>
      <div class="flex w-full items-center justify-between gap-2">
        <AppButton v-if="editingEntry?.manually_adjusted" variant="ghost" class="text-rose-600" @click="removeOverride">Remove override</AppButton>
        <div class="ml-auto flex gap-2">
          <AppButton variant="ghost" @click="showOverrideModal = false">Cancel</AppButton>
          <AppButton :loading="savingOverride" @click="saveOverride">Save</AppButton>
        </div>
      </div>
    </template>
  </AppModal>
```

Change the entry block's `@click` from `$emit('edit-entry', entry)` to `openOverride(entry)`:

```html
              @click="openOverride(entry)"
```

- [ ] **Step 2: Add the modal's script logic and imports**

Add `AppModal`/`AppButton` imports at the top of `<script setup>`:

```js
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
```

Add the modal state and handlers (place near the other refs):

```js
const showOverrideModal = ref(false)
const editingEntry = ref(null)
const overrideForm = ref({ override_start_time: '', override_end_time: '' })
const overrideError = ref('')
const savingOverride = ref(false)

function openOverride(entry) {
  editingEntry.value = entry
  overrideForm.value = { override_start_time: entry.start_time, override_end_time: entry.end_time }
  overrideError.value = ''
  showOverrideModal.value = true
}

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

Remove the now-unused `defineEmits(['edit-entry', 'update:preview'])` `edit-entry` entry (keep `update:preview` only):

```js
const emit = defineEmits(['update:preview'])
```

- [ ] **Step 3: Build the frontend**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/AdjustedDayCalendar.vue
git commit -m "feat(faculty-loading): click-to-edit precision popover in AdjustedDayCalendar"
```

---

### Task 13: Wire `AdjustedDayCalendar.vue` into `ResolveConflicts.vue`

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/ResolveConflicts.vue`

**Interfaces:**
- Consumes: `AdjustedDayCalendar.vue` (Tasks 10-12) — props `{ preview, adjustment }`, event `update:preview`.

- [ ] **Step 1: Replace the table body with the calendar component**

Replace the entire `<AppCard v-for="grade in gradesWithEntries" ...>` block (the per-grade table) with:

```html
      <AdjustedDayCalendar :preview="currentPreview" :adjustment="adjustment" @update:preview="value => currentPreview = value" />
```

- [ ] **Step 2: Remove the now-unused table-only script (keep the warnings banner logic, the modal, and its handlers can go — the new component owns its own modal)**

Remove from the `<script setup>` block: the `showOverrideModal`, `editingEntry`, `overrideForm`, `overrideError`, `savingOverride`, `openOverride`, `saveOverride`, `removeOverride`, and `isFlagged` functions/refs (all now live inside `AdjustedDayCalendar.vue`). Remove the old `<AppModal>` block from the template too (Task 12 ported an equivalent one into the new component). Keep `gradesWithEntries`/`warnings`/`formattedEffectiveDate` computeds — actually `gradesWithEntries` is no longer used directly in this file once the table markup is gone; remove it, but keep `warnings` (still drives the top banner) and `formattedEffectiveDate`.

Add the import:

```js
import AdjustedDayCalendar from './AdjustedDayCalendar.vue'
```

The resulting `<script setup>` should be:

```js
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AdjustedDayCalendar from './AdjustedDayCalendar.vue'
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  adjustment: { type: Object, required: true },
  term: { type: Object, required: true },
  preview: { type: Object, required: true },
})

const currentPreview = ref(props.preview)

const warnings = computed(() => currentPreview.value.conflict_warnings ?? [])
const formattedEffectiveDate = computed(() =>
  new Date(`${props.adjustment.effective_date}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }))
```

- [ ] **Step 3: Update the imports used in the template (remove now-unused icons/components)**

`AppIconButton`, `AppModal`, `PencilSquareIcon`, `XMarkIcon`, and `axios` are no longer used directly in this file — remove their imports if the build step below flags them as unused (Vite won't error on unused imports, but leaving dead imports contradicts the project's lean-file convention, so remove them explicitly).

- [ ] **Step 4: Build the frontend**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 5: Manual verification in dev (Chrome MCP)**

This is the one part of this plan with no automated test — Part 3 is frontend-only and the backend contract it relies on (`upsertOverride`/`removeOverride`/`preview`) already has full test coverage from before this plan. Verify by hand:

1. In dev, create a draft adjustment (any type) that has two entries genuinely double-booked into the same room at different original times such that after compression they land close together (or use the flow from the `test_early_start_stem_split_still_blocks_a_genuine_double_booking...` fixture data manually, or simpler: create an early_start_stem_split draft, open Manual Adjustment).
2. Open "Resolve conflicts" for that draft.
3. Confirm the calendar renders (grade cards, section columns, entries positioned on the time axis, bands shown, STEM badge visible on tagged subjects).
4. Drag an entry to a new time; confirm the block moves, the drop persists (reload the page — the moved time and the "Adjusted" (manually_adjusted) styling survive).
5. Click an entry; confirm the precision-edit modal opens pre-filled with its current time, save works, remove-override works.
6. Confirm the warnings banner at the top still updates after a drag/edit.

Expected: all six checks pass. If Chrome MCP / dev login isn't available in this environment (OAuth-only login, no dev-login bypass — see `project_class_scheduling_audit.md`), state explicitly that this step could not be completed and why, rather than claiming it was verified.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/ResolveConflicts.vue
git commit -m "feat(faculty-loading): replace Manual Adjustment table with AdjustedDayCalendar"
```

---

## Self-Review Notes

**Spec coverage:**
- STEM tagging (Part 1) → Tasks 1-3.
- New adjustment type mechanics: per-section anchor shift, per-period STEM/non-STEM target, Lunch drop, Health Break band, `calendar_start` fix, coverage banner → Tasks 4, 6, 8, 9.
- Conflict-detection decoupling (bundled fix) → Task 7.
- Manual Adjustment calendar/drag-and-drop/conflict UI for all 5 types → Tasks 10-13.
- Testing plan's explicit scenarios (anchor-shift correctness, STEM/non-STEM split, Lunch absence, Health Break presence, conflict-detection-without-activity regression, frontend click-through) → covered by Tasks 6, 7, 13 Step 5.

**Type consistency check:** `override_start_time`/`override_end_time` (`H:i` strings) used identically in Task 11's drag handler and Task 12's modal handler, matching the existing `upsertOverride` validation (`date_format:H:i`). `class_schedule_id` used consistently as `entry.id` throughout (matches `toCalendarArray()`'s `'id' => $this->id`). `is_stem` flows as a plain boolean from migration → model cast → controller → Vue form/table without a naming drift anywhere.

**Placeholder scan:** no TBD/TODO; every step has real code, not descriptions of code.
