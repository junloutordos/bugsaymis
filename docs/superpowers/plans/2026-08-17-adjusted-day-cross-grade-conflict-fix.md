# Adjusted Day Cross-Grade Compression Conflict Fix — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop the "New Adjusted Day" (30-Minute Classes for Official Activity) form from blocking legitimate saves with a false "room/faculty conflict" error, while still blocking genuine same-grade double-bookings.

**Architecture:** `AdjustedClassScheduleService::generate()` compresses each grade's class periods independently (`transformTime()`, unchanged — this is correct for each grade's own displayed schedule). The bug is in `assertNoGeneratedConflicts()`, which pools all 6 grades' independently-shifted entries and treats wall-clock adjacency as ground truth. Because different grade groups have genuinely different break/homeroom placement (confirmed via `SchedulingConstants`), the same original clock moment shifts by a different amount per grade, which can invert the order of two back-to-back, non-conflicting bookings in a shared room/faculty slot. A whole-day shared reference clock is not constructible without distorting real per-grade period counts (verified — grade groups have 5–8 periods/day at different start times with non-aligned boundaries). The fix: only hard-block when both sides of a detected overlap belong to the **same grade** (a genuine, compression-independent conflict); for **cross-grade** overlaps, surface a non-blocking warning instead of throwing.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 + Inertia.js, PHPUnit feature tests (`RefreshDatabase`).

**Spec:** No separate spec doc — this plan itself documents the investigated root cause and agreed approach (confirmed with the user via `AskUserQuestion` across two rounds: "same-grade-only hard check + cross-grade soft warning").

## Global Constraints

- Never use `FormData`/multipart uploads — not applicable here (no file upload in this flow).
- Stage git commits by explicit file path, never `-A`/`.`.
- Every migration/model convention in this repo already applies; this plan touches no migrations.
- Do not touch `transformTime()`'s per-grade compression math — verified correct for display; only the conflict-detection comparison changes.

---

## Verified root cause (for the implementer's confidence — do not re-derive)

Brute-forced against the real `SchedulingConstants::getClassSlots()` data (Monday timetable) confirms a genuine false-positive:

- Grade 7 Period 1: `10:00–10:50` (Room 101)
- Grade 8 Period 3: `10:50–11:40` (Room 101, same room, immediately after — zero real gap, not a conflict)

After 50→30-minute compression:
- Grade 7's entry compresses to end at **10:30** (1 completed period × 20 min saved)
- Grade 8's entry compresses to start at **10:10** (2 completed periods × 20 min saved — G8 has an extra period, 08:50–09:40, before 10:00 that G7 doesn't have)

Pooled and sorted by the current code: `10:10 < 10:30` → false "room conflict" thrown, even though nothing about the request is wrong. This is architectural (different grades bank compression savings at different rates because their daily structure genuinely differs), not a data-entry error — same-grade comparisons never have this problem because both sides use the identical shift function.

---

## Task 1: Same-grade-only hard block + cross-grade soft warnings (backend)

**Files:**
- Modify: `app/Services/FacultyLoading/AdjustedClassScheduleService.php:142-236`
- Modify: `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php:105-146`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:93-96`
- Test: `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php`

**Interfaces:**
- Produces: `AdjustedClassScheduleService::generate()` return array gains a new key `'conflict_warnings' => array<string>` (empty array when there are none, or when the adjustment has no shortened-classes activity).
- Produces: session flash key `warning` (string or null), shared to Inertia as `flash.warning`, alongside the existing `flash.success`/`flash.error`.

- [ ] **Step 1: Write the failing feature test for the cross-grade false positive**

Add to `tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php` (inside the `ClassScheduleDayAdjustmentTest` class, after `test_transferred_flag_ceremony_can_be_combined_with_shortened_classes`):

```php
    public function test_cross_grade_room_overlap_after_compression_is_a_warning_not_a_blocking_error(): void
    {
        $room = Classroom::where('code', 'R101')->firstOrFail();
        $grade7Section = Section::where('sectionname', 'Aquamarine')->firstOrFail();
        $grade7Subject = Subject::where('code', 'MATH7')->firstOrFail();

        $grade8Section = Section::create([
            'levelid' => 8,
            'sectionname' => 'Beryl',
            'syid' => $this->term->school_year_id,
            'school_year_id' => $this->term->school_year_id,
            'is_active' => true,
        ]);
        $grade8Subject = Subject::create([
            'school_year_id' => $this->term->school_year_id,
            'code' => 'MATH8',
            'name' => 'Mathematics 2',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 8,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);

        // Grade 7 Period 1 (10:00-10:50) and Grade 8 Period 3 (10:50-11:40)
        // share Room 101 back-to-back on Monday — zero real gap, not a real
        // conflict. Different grade-day structures make G8 bank more
        // compression savings than G7 by 10:50, which currently inverts
        // their order after 30-minute compression.
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
            'subject_id' => $grade8Subject->id,
            'section_id' => $grade8Section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => '10:50',
            'end_time' => '11:40',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-10',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_schedule_day_adjustments', 1);
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('Grade 7', session('warning'));
        $this->assertStringContainsString('Grade 8', session('warning'));
    }

    public function test_same_grade_room_double_booking_still_blocks_save(): void
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

        // Two different Grade 7 sections genuinely double-booked into Room 101
        // at overlapping original times (10:00-10:50 vs 10:20-11:10) — a real
        // conflict the base scheduler should never have allowed, unrelated to
        // compression. Same grade => identical compression shift for both =>
        // still overlapping after compression => must still block.
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
            'adjustment_type' => 'shortened_classes',
            'effective_date' => '2026-08-10',
            'activity_title' => 'Heat Index Early Dismissal',
            'activity_start_time' => '13:00',
            'activity_end_time' => '17:00',
            'reason' => 'Due to high heat index',
        ])->assertSessionHasErrors('activity_start_time');

        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ClassScheduleDayAdjustmentTest"
```
Expected: `test_cross_grade_room_overlap_after_compression_is_a_warning_not_a_blocking_error` FAILS (currently gets a validation error and redirect assertion / session-has-no-errors fails — the current code throws for this case). `test_same_grade_room_double_booking_still_blocks_save` currently PASSES already (that's fine — it's the regression guard, written now so Step 4 can't silently break it).

- [ ] **Step 3: Modify `AdjustedClassScheduleService`**

In `app/Services/FacultyLoading/AdjustedClassScheduleService.php`, replace the `if ($activityStart) { ... }` block (lines 142-156) with:

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

Then update the returned array (currently ends at `'grades' => $grades,`) to add the new key:

```php
            'calendar_start' => '07:30',
            'calendar_end' => '17:00',
            'conflict_warnings' => $conflictWarnings,
            'grades' => $grades,
        ];
```

Finally, replace `assertNoGeneratedConflicts()` (lines 213-236) entirely:

```php
    /**
     * Same-grade room/faculty overlaps after compression are genuine
     * conflicts (both sides shrink by the identical per-grade shift, so
     * compression cannot have manufactured them) and still block the save.
     *
     * Cross-grade overlaps cannot be trusted the same way: different grade
     * groups bank different amounts of compression savings by the same
     * wall-clock moment (their break/homeroom placement genuinely differs),
     * so two originally back-to-back, non-conflicting bookings can appear to
     * invert order after independent compression. Those are downgraded to a
     * warning instead of a blocking error.
     *
     * @return array<int,string> warning messages for cross-grade overlaps
     */
    private function assertNoGeneratedConflicts(array $grades): array
    {
        $entries = collect($grades)
            ->flatMap(fn (array $grade) => $grade['sections'])
            ->flatMap(fn (array $section) => $section['entries'])
            ->values();

        $warnings = [];

        foreach (['faculty' => 'faculty', 'classroom' => 'room'] as $relation => $label) {
            $groups = $entries->filter(fn (array $entry) => isset($entry[$relation]['id']))
                ->groupBy(fn (array $entry) => $entry[$relation]['id']);

            foreach ($groups as $rows) {
                $sorted = $rows->sortBy('start_time')->values();

                for ($index = 1; $index < $sorted->count(); $index++) {
                    $previous = $sorted[$index - 1];
                    $current = $sorted[$index];

                    if ($current['start_time'] >= $previous['end_time']) {
                        continue;
                    }

                    if ($current['grade_level'] === $previous['grade_level']) {
                        throw ValidationException::withMessages([
                            'activity_start_time' => "The compressed timetable creates a {$label} conflict. Review the preview or choose another activity time.",
                        ]);
                    }

                    $name = $current[$relation]['name'] ?? $current[$relation]['code'] ?? "#{$current[$relation]['id']}";
                    $warnings[] = sprintf(
                        'Possible %s overlap for %s between Grade %s and Grade %s around %s. Review the preview before publishing.',
                        $label,
                        $name,
                        $previous['grade_level'],
                        $current['grade_level'],
                        $current['start_time'],
                    );
                }
            }
        }

        return array_values(array_unique($warnings));
    }
```

`$entry['grade_level']` already exists on every entry — it comes straight from `ClassSchedule::toCalendarArray()` (`'grade_level' => $this->section?->levelid`), so no extra tagging is needed.

- [ ] **Step 4: Share the `warning` flash key with Inertia**

In `app/Http/Middleware/HandleInertiaRequests.php`, change:

```php
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
```

to:

```php
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
```

- [ ] **Step 5: Flash conflict warnings from the controller**

In `app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php`, change `store()`:

```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $this->validatedData($request);
        $warnings = [];

        DB::transaction(function () use ($data, &$warnings) {
            $adjustment = ClassScheduleDayAdjustment::create([
                ...$data,
                'ceremony_start_time' => '07:30',
                'ceremony_end_time' => '08:00',
                'shift_minutes' => $this->hasFlag($data['adjustment_type']) ? 30 : 0,
                'class_duration_minutes' => $this->hasShortenedClasses($data['adjustment_type']) ? 30 : null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            // Validate fit and generated campus conflicts before keeping the draft.
            $warnings = $this->adjustedSchedules->generate($adjustment)['conflict_warnings'] ?? [];
        });

        return back()->with([
            'success' => 'Adjusted-day schedule saved as a draft.',
            'warning' => $warnings ? implode(' ', $warnings) : null,
        ]);
    }
```

And `update()`:

```php
    public function update(Request $request, ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be edited.');

        $data = $this->validatedData($request, $adjustment);
        $warnings = [];

        DB::transaction(function () use ($adjustment, $data, &$warnings) {
            $adjustment->update([
                ...$data,
                'shift_minutes' => $this->hasFlag($data['adjustment_type']) ? 30 : 0,
                'class_duration_minutes' => $this->hasShortenedClasses($data['adjustment_type']) ? 30 : null,
            ]);
            $warnings = $this->adjustedSchedules->generate($adjustment->fresh())['conflict_warnings'] ?? [];
        });

        return back()->with([
            'success' => 'Adjusted-day draft updated.',
            'warning' => $warnings ? implode(' ', $warnings) : null,
        ]);
    }
```

(`publish()` and `printableSnapshot()` call the same `generate()`/`assertNoGeneratedConflicts()` code path and automatically stop hard-failing on cross-grade overlaps too — no change needed there.)

- [ ] **Step 6: Run the tests to verify they pass**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ClassScheduleDayAdjustmentTest"
```
Expected: all tests in the file PASS, including both new ones.

- [ ] **Step 7: Run the full test suite to check for regressions**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"
```
Expected: no new failures versus the pre-change baseline.

- [ ] **Step 8: PHP lint the modified files**

Run the `lint` skill, or:
```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -l app/Services/FacultyLoading/AdjustedClassScheduleService.php && php -l app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php && php -l app/Http/Middleware/HandleInertiaRequests.php"
```

- [ ] **Step 9: Commit**

```bash
git add app/Services/FacultyLoading/AdjustedClassScheduleService.php app/Http/Controllers/FacultyLoading/ClassScheduleDayAdjustmentController.php app/Http/Middleware/HandleInertiaRequests.php tests/Feature/FacultyLoading/ClassScheduleDayAdjustmentTest.php
git commit -m "fix(faculty-loading): stop cross-grade compression from false-flagging room/faculty conflicts on adjusted days"
```

---

## Task 2: Render the cross-grade warning banner (frontend)

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/Schedules/DayAdjustments.vue:19-21`

**Interfaces:**
- Consumes: `$page.props.flash.warning` (string or null/undefined), shared by Task 1 Step 4.

- [ ] **Step 1: Add the warning banner next to the existing success banner**

In `resources/js/Pages/FacultyLoading/Schedules/DayAdjustments.vue`, immediately after the existing success banner block:

```html
      <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ $page.props.flash.success }}
      </div>

      <div v-if="$page.props.flash?.warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ $page.props.flash.warning }}
      </div>
```

- [ ] **Step 2: Build the frontend**

Run the `build` skill, or:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"
```
Expected: build succeeds with no errors.

- [ ] **Step 3: Manual verification (cannot be automated — OAuth login blocks self-testing in browser)**

Note for whoever runs this: log in as a `faculty_loading.manage` user, open Faculty Loading → Adjusted Day Schedules → New Adjustment, pick "30-Minute Classes for Official Activity", pick a date/time that previously produced the false room-conflict error, and confirm:
1. Save Draft now succeeds (modal closes, draft appears in the list).
2. If a genuine cross-grade room/faculty overlap exists after compression, an amber warning banner appears on the page (not a blocking form error).
3. A genuine same-grade double-booking still blocks the save with the red form error as before.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/FacultyLoading/Schedules/DayAdjustments.vue
git commit -m "feat(faculty-loading): show non-blocking warning banner for cross-grade adjusted-day conflicts"
```

---

## Self-Review

**Spec coverage:** User-agreed approach was "same-grade-only hard check + cross-grade soft warning." Task 1 implements the same-grade/cross-grade split and warning collection; Task 2 surfaces it in the UI. Both new test cases (false-positive no longer blocks; genuine same-grade conflict still blocks) are covered in Task 1 Step 1.

**Placeholder scan:** No TBD/TODO; all code blocks are complete and copy-pasteable; test data uses concrete, brute-force-verified times.

**Type consistency:** `generate()` return array key `conflict_warnings` (array of strings) is produced in Task 1 Step 3 and consumed in Task 1 Step 5 via `['conflict_warnings'] ?? []`. Controller flashes it under session key `warning` (string, joined), consumed by Task 1 Step 4 (middleware) and Task 2 Step 1 (`flash.warning`) — names match throughout.
