# AMS Evaluation Period + Comprehensive Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the Activity Evaluation Committee or an activity's proponent open/close its evaluation period (closing blocks new evaluations and therefore new certificates), add real per-day attendance tracking for multi-day activities, and add a comprehensive on-screen + printable participant/attendance/evaluation report.

**Architecture:** Two additive migrations (`evaluation_open` + audit columns on `ams_activities`; new `ams_activity_attendance_days` table). Evaluation-period gating is a single boolean check added at the top of `EvaluationController`'s four submission/display entry points. Per-day attendance is a rollup layered on top of the existing `attended`/`hours_attended` columns — those columns keep meaning exactly what they always have, so certificate gating needs zero changes. A new `ActivityReportService` is the single source of truth consumed by both an on-screen Vue report page and a browser-print page that reuses the WFH module's letterhead pattern.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, MySQL 8.0 (via Docker `php` service), PHPUnit feature tests with `RefreshDatabase`.

**Spec:** `docs/superpowers/specs/2026-08-18-ams-evaluation-period-and-report-design.md`

## Global Constraints

- Migrations must be additive only (backward-compatible with currently-deployed code, per this repo's blue-green deploy discipline): new nullable/defaulted columns, new tables. No column drops/renames/type changes.
- No new permission strings. Reuse the existing `activities.manage|activities.view_all|activities.monitor|activities.evaluation_committee` group already on `ams/activities` routes.
- Certificate gating (`CertificateController`, `ActivityEvaluationEligibilityService`) must not be modified — it already enforces "must have evaluated," which combined with the new period gate is sufficient per the spec.
- Single-day activities (`end_date == start_date`, the majority of existing/future activities) must be byte-for-byte unaffected by the per-day attendance changes — no `daily` payload required or acted upon for them.
- The printable report is a browser-print Inertia page (`window.print()` + `/images/report_header.jpeg` / `/images/report_footer.jpeg` full-width letterhead images in repeating `<thead>/<tfoot>`), following `resources/js/Pages/HumanResource/WFH/PrintAccomplishments.vue` exactly — not an mPDF-generated file.
- All file uploads / base64 / S3 rules from `CLAUDE.md` are unaffected — this feature introduces no new file uploads.
- Run PHP tests via: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=<TestClass>"` (from `/Users/junlou/bugsaymis-docker`). Run `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -l <file>"` after every PHP edit. Run `npm run build` after Vue edits to catch compile errors (no JS test runner exists in this project — frontend verification is build-success + manual browser smoke-check, per `CLAUDE.md`).

---

### Task 1: Evaluation period schema + `Activity` model helpers

**Files:**
- Create: `database/migrations/2026_08_18_090001_add_evaluation_period_to_ams_activities_table.php`
- Modify: `app/Models/AMS/Activity.php`
- Test: `tests/Feature/AMS/EvaluationPeriodTest.php` (new file)

**Interfaces:**
- Produces: `Activity::isEvaluationOpen(): bool`-free — the raw `evaluation_open` cast boolean attribute is used directly everywhere (no wrapper method needed, simpler). `Activity::isMultiDay(): bool`, `Activity::attendanceDayList(): array` (list of `Y-m-d` strings, capped at 60, empty if not multi-day-eligible dates), `Activity::statusChangedBy(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_open_defaults_true_on_new_activity(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);

        $this->assertTrue($activity->fresh()->evaluation_open);
        $this->assertNull($activity->fresh()->evaluation_status_changed_at);
    }

    public function test_is_multi_day_and_attendance_day_list(): void
    {
        $owner = $this->userWithPermission('activities.manage');

        $singleDay = $this->makeActivity($owner, [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);
        $this->assertFalse($singleDay->isMultiDay());
        $this->assertSame([], $singleDay->attendanceDayList());

        $multiDay = $this->makeActivity($owner, [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
        ]);
        $this->assertTrue($multiDay->isMultiDay());
        $this->assertSame(
            ['2026-08-10', '2026-08-11', '2026-08-12'],
            $multiDay->attendanceDayList()
        );
    }

    private function makeActivity(User $owner, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Period Test Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ], $overrides));
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Period Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `/Users/junlou/bugsaymis-docker`):
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EvaluationPeriodTest"
```
Expected: FAIL — column `evaluation_open` doesn't exist / method `isMultiDay` doesn't exist.

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->boolean('evaluation_open')->default(true)->after('qr_token');
            $table->timestamp('evaluation_status_changed_at')->nullable()->after('evaluation_open');
            $table->foreignId('evaluation_status_changed_by')
                ->nullable()
                ->after('evaluation_status_changed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_status_changed_by');
            $table->dropColumn(['evaluation_open', 'evaluation_status_changed_at']);
        });
    }
};
```

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_18_090001_add_evaluation_period_to_ams_activities_table.php"
```

- [ ] **Step 4: Update the `Activity` model**

In `app/Models/AMS/Activity.php`, add `evaluation_open`, `evaluation_status_changed_at`, `evaluation_status_changed_by` to `$fillable`, add casts, and add the new methods/relation:

```php
protected $fillable = [
    'user_id',
    'title',
    'activity_type',
    'start_date',
    'start_time',
    'end_date',
    'end_time',
    'total_hours',
    'venue',
    'resource_person',
    'what_to_bring',
    'banner',
    'special_order',
    'activity_report',
    'official_documentation',
    'qr_token',
    'evaluation_open',
    'evaluation_status_changed_at',
    'evaluation_status_changed_by',
];
```

```php
protected $casts = [
    'start_date' => 'date:Y-m-d',
    'end_date'   => 'date:Y-m-d',
    'evaluation_open' => 'boolean',
    'evaluation_status_changed_at' => 'datetime',
];
```

```php
public function statusChangedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'evaluation_status_changed_by');
}

public function isMultiDay(): bool
{
    return $this->start_date && $this->end_date && $this->end_date->gt($this->start_date);
}

/** Inclusive list of Y-m-d date strings from start_date to end_date. Empty if not multi-day. */
public function attendanceDayList(): array
{
    if (! $this->isMultiDay()) {
        return [];
    }

    $days = [];
    $cursor = $this->start_date->copy();
    while ($cursor->lte($this->end_date) && count($days) < 60) {
        $days[] = $cursor->toDateString();
        $cursor->addDay();
    }

    return $days;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EvaluationPeriodTest"
```
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_18_090001_add_evaluation_period_to_ams_activities_table.php app/Models/AMS/Activity.php tests/Feature/AMS/EvaluationPeriodTest.php
git commit -m "feat(ams): add evaluation period fields and multi-day helpers to Activity"
```

---

### Task 2: `ams_activity_attendance_days` schema + model

**Files:**
- Create: `database/migrations/2026_08_18_090002_create_ams_activity_attendance_days_table.php`
- Create: `app/Models/AMS/ActivityAttendanceDay.php`
- Modify: `app/Models/AMS/Activity.php` (add `attendanceDays()` relation)
- Test: `tests/Feature/AMS/PerDayAttendanceTest.php` (new file, first test only — more added in Tasks 7–8)

**Interfaces:**
- Produces: `ActivityAttendanceDay` model, table columns `activity_id, participant_type ('employee'|'student'), participant_id, date (Y-m-d string, uncast), attended ('yes'|'no'), hours_attended (decimal:2)`. `Activity::attendanceDays(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerDayAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_day_belongs_to_activity_and_enforces_unique_combo(): void
    {
        $activity = Activity::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'title' => 'Multi-day Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
        ]);

        $day = ActivityAttendanceDay::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => 999,
            'date' => '2026-08-10',
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);

        $this->assertSame($activity->id, $day->activity->id);
        $this->assertCount(1, $activity->attendanceDays);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => 999,
            'date' => '2026-08-10',
            'attended' => 'no',
            'hours_attended' => 0,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: FAIL — table `ams_activity_attendance_days` doesn't exist.

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();
            $table->enum('participant_type', ['employee', 'student']);
            $table->unsignedBigInteger('participant_id');
            $table->date('date');
            $table->string('attended', 10)->default('no');
            $table->decimal('hours_attended', 4, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['activity_id', 'participant_type', 'participant_id', 'date'],
                'ams_attendance_days_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_attendance_days');
    }
};
```

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_18_090002_create_ams_activity_attendance_days_table.php"
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAttendanceDay extends Model
{
    protected $table = 'ams_activity_attendance_days';

    protected $fillable = [
        'activity_id',
        'participant_type',
        'participant_id',
        'date',
        'attended',
        'hours_attended',
    ];

    protected $casts = [
        'hours_attended' => 'decimal:2',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
```

Note: `date` is deliberately left uncast (plain `Y-m-d` string from MySQL's `DATE` column) — this project has a documented bug precedent (the meal-plan day-grid) caused by round-tripping local dates through JS `Date`/UTC conversions; keeping the column a plain string on both the PHP and JS sides avoids reintroducing that class of bug.

Add to `app/Models/AMS/Activity.php` (near the other `HasMany` relations):

```php
public function attendanceDays(): HasMany
{
    return $this->hasMany(ActivityAttendanceDay::class, 'activity_id');
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_18_090002_create_ams_activity_attendance_days_table.php app/Models/AMS/ActivityAttendanceDay.php app/Models/AMS/Activity.php tests/Feature/AMS/PerDayAttendanceTest.php
git commit -m "feat(ams): add ams_activity_attendance_days table and model"
```

---

### Task 3: Evaluation period toggle endpoint + authorization

**Files:**
- Modify: `app/Http/Controllers/AMS/ActivityController.php`
- Modify: `routes/ams.php`
- Test: `tests/Feature/AMS/EvaluationPeriodTest.php` (add tests)

**Interfaces:**
- Consumes: `Activity::$evaluation_open` etc. from Task 1.
- Produces: route `ams.activities.evaluation-period.toggle` (POST), `ActivityController::canToggleEvaluationPeriod(Activity): bool` (used by Task 5's `show()` payload too).

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AMS/EvaluationPeriodTest.php`)

```php
use App\Models\AMS\ActivityCoProponent;

    public function test_owner_can_close_and_reopen_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);

        $this->actingAs($owner)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $activity->refresh();
        $this->assertFalse($activity->evaluation_open);
        $this->assertNotNull($activity->evaluation_status_changed_at);
        $this->assertSame($owner->id, $activity->evaluation_status_changed_by);

        $this->actingAs($owner)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => true])
            ->assertRedirect();

        $this->assertTrue($activity->fresh()->evaluation_open);
    }

    public function test_co_proponent_can_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $coProponent = User::factory()->create(['email_verified_at' => now()]);
        ActivityCoProponent::create(['activity_id' => $activity->id, 'employee_id' => $coProponent->id]);

        $this->actingAs($coProponent)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($activity->fresh()->evaluation_open);
    }

    public function test_evaluation_committee_permission_holder_can_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $committeeMember = $this->userWithPermission('activities.evaluation_committee');

        $this->actingAs($committeeMember)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($activity->fresh()->evaluation_open);
    }

    public function test_unrelated_user_cannot_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $stranger = $this->userWithPermission('activities.view_all');

        $this->actingAs($stranger)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertForbidden();

        $this->assertTrue($activity->fresh()->evaluation_open);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EvaluationPeriodTest"
```
Expected: FAIL — route `ams.activities.evaluation-period.toggle` not defined.

- [ ] **Step 3: Add the route**

In `routes/ams.php`, add inside the existing `ams/activities` prefix group (right after the `certificates.*` routes, before the attachment proxy):

```php
        // ── Evaluation period ────────────────────────────────────────────────
        Route::post('/{activity}/evaluation-period/toggle',
            [ActivityController::class, 'toggleEvaluationPeriod'])->name('evaluation-period.toggle');

```

- [ ] **Step 4: Add the controller method + authorization helper**

In `app/Http/Controllers/AMS/ActivityController.php`, add near the other `// ── Authorization ──` private methods:

```php
    private function canToggleEvaluationPeriod(Activity $activity): bool
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->hasPermission('activities.evaluation_committee')) {
            return true;
        }

        $isOwner = $activity->user_id === $user->id;
        $isCo    = ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();

        return $isOwner || $isCo;
    }

    private function authorizeEvaluationPeriod(Activity $activity): void
    {
        abort_unless($this->canToggleEvaluationPeriod($activity), 403);
    }
```

And add the action method near `sendEvaluationLinks()`:

```php
    /** Open/close the evaluation period. Closing blocks new evaluations (and therefore new certificates for anyone who hasn't evaluated yet) without affecting anyone already evaluated/certified. */
    public function toggleEvaluationPeriod(Request $request, Activity $activity)
    {
        $this->authorizeEvaluationPeriod($activity);
        $data = $request->validate(['open' => 'required|boolean']);

        $activity->update([
            'evaluation_open'               => $data['open'],
            'evaluation_status_changed_at'  => now(),
            'evaluation_status_changed_by'  => Auth::id(),
        ]);

        $message = $data['open']
            ? 'Evaluation period reopened. Participants can submit evaluations again.'
            : 'Evaluation period closed. Anyone who has not yet evaluated can no longer evaluate or receive a certificate.';

        return back()->with('success', $message);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EvaluationPeriodTest"
```
Expected: PASS (6 tests total).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AMS/ActivityController.php routes/ams.php tests/Feature/AMS/EvaluationPeriodTest.php
git commit -m "feat(ams): add evaluation period open/close endpoint and authorization"
```

---

### Task 4: Gate evaluation submission on the period being open

**Files:**
- Modify: `app/Http/Controllers/AMS/EvaluationController.php`
- Test: `tests/Feature/AMS/EvaluationPeriodTest.php` (add tests)

**Interfaces:**
- Consumes: `Activity::$evaluation_open` (Task 1).
- Produces: `evaluationClosed` boolean prop on `AMS/Evaluate` and `AMS/EvaluateTWS` Inertia responses.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AMS/EvaluationPeriodTest.php`)

```php
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityTwsEvaluation;
use App\Models\AMS\CertificateService; // not used directly, kept for readability of related fixtures if needed

    public function test_closed_period_shows_closed_message_instead_of_in_house_form(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, ['evaluation_open' => false]);
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->get(route('ams.activities.evaluate.show', [$activity, $hash]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AMS/Evaluate')
                ->where('evaluationClosed', true)
            );
    }

    public function test_closed_period_blocks_in_house_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, ['evaluation_open' => false]);
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->post(route('ams.activities.evaluate.store', [$activity, $hash]), [
            'obj_1' => 'agree', 'obj_2' => 'agree', 'obj_3' => 'agree', 'obj_4' => 'agree',
            'mgmt_1' => 'agree', 'mgmt_2' => 'agree', 'mgmt_3' => 'agree',
            'mgmt_4' => 'agree', 'mgmt_5' => 'agree', 'mgmt_6' => 'agree',
            'phys_1' => 'agree', 'phys_2' => 'agree', 'phys_3' => 'agree',
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_evaluations', 0);
    }

    public function test_closed_period_blocks_tws_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, [
            'activity_type' => Activity::TYPE_TRAINING_WORKSHOP_SEMINAR,
            'evaluation_open' => false,
        ]);
        $speaker = $activity->speakers()->create(['name' => 'Speaker One', 'sort_order' => 0]);
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->post(route('ams.activities.evaluate.store', [$activity, $hash]), [
            'content_1' => 'agree', 'content_2' => 'agree', 'content_3' => 'agree',
            'content_4' => 'agree', 'content_5' => 'agree',
            'mgmt_length_of_program' => 'satisfied', 'mgmt_schedule' => 'satisfied',
            'mgmt_secretariat_support' => 'satisfied', 'mgmt_venue' => 'satisfied',
            'mgmt_accommodation' => 'satisfied', 'mgmt_food_meals' => 'satisfied',
            'overall_1_objectives_accomplished' => 'agree', 'overall_2_knowledge_increased' => 'agree',
            'speakers' => [[
                'speaker_id' => $speaker->id,
                'topic_depth_of_content' => 'excellent', 'topic_scope_coverage' => 'excellent',
                'topic_relevance_appropriateness' => 'excellent', 'attainment_of_objectives' => 'agree',
                'mastery_1_command_of_subject' => 'agree', 'mastery_2_pace_timing' => 'agree',
                'mastery_3_theory_application_balance' => 'agree', 'mastery_4_current_trends' => 'agree',
                'presentation_1_listened' => 'agree', 'presentation_2_answered_questions' => 'agree',
                'presentation_3_inspired_participation' => 'agree', 'presentation_4_held_interest' => 'agree',
                'acceptability_as_speaker' => 'agree',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_tws_evaluations', 0);
    }

    public function test_closed_period_blocks_walkin_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, ['evaluation_open' => false]);

        $this->get(route('ams.activities.evaluate.walkin.show', [$activity, $activity->qr_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('evaluationClosed', true));

        $this->post(route('ams.activities.evaluate.walkin.store', [$activity, $activity->qr_token]), [
            'sex' => 'male',
            'obj_1' => 'agree', 'obj_2' => 'agree', 'obj_3' => 'agree', 'obj_4' => 'agree',
            'mgmt_1' => 'agree', 'mgmt_2' => 'agree', 'mgmt_3' => 'agree',
            'mgmt_4' => 'agree', 'mgmt_5' => 'agree', 'mgmt_6' => 'agree',
            'phys_1' => 'agree', 'phys_2' => 'agree', 'phys_3' => 'agree',
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_evaluations', 0);
    }

    public function test_open_period_still_allows_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner); // evaluation_open defaults true
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->post(route('ams.activities.evaluate.store', [$activity, $hash]), [
            'obj_1' => 'agree', 'obj_2' => 'agree', 'obj_3' => 'agree', 'obj_4' => 'agree',
            'mgmt_1' => 'agree', 'mgmt_2' => 'agree', 'mgmt_3' => 'agree',
            'mgmt_4' => 'agree', 'mgmt_5' => 'agree', 'mgmt_6' => 'agree',
            'phys_1' => 'agree', 'phys_2' => 'agree', 'phys_3' => 'agree',
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_evaluations', 1);
    }

    public function test_certificate_download_still_works_after_period_closed_for_prior_evaluation(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $attendee = User::factory()->create(['email' => uniqid().'@example.test']);
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
            'certificate_path' => 'ams/certificates/already-issued.pdf',
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee', 'participant_id' => $attendee->id,
        ]);

        // Close the period after the evaluation/certificate already exist.
        $activity->update(['evaluation_open' => false]);

        $this->actingAs($owner)
            ->get(route('ams.activities.certificates.download.participant', [$activity, $participant]))
            ->assertOk();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EvaluationPeriodTest"
```
Expected: FAIL — closed-period tests submit successfully today (no gate exists yet); `evaluationClosed` prop missing.

- [ ] **Step 3: Add the gate to `EvaluationController`**

In `app/Http/Controllers/AMS/EvaluationController.php`:

Add `'evaluationClosed' => ! $activity->evaluation_open,` to the `Inertia::render('AMS/Evaluate', [...])` array inside `show()` (in-house branch) and to `showTws()`'s `Inertia::render('AMS/EvaluateTWS', [...])`. Add the same key to both `Inertia::render` calls inside `showWalkin()`.

Add the block-check as the first line of `store()`, right after the `resolveParticipant`/404 check and before the `isTrainingWorkshopSeminar()` dispatch:

```php
    public function store(Request $request, Activity $activity, string $hash)
    {
        $resolved = $this->resolveParticipant($activity, $hash);
        if (!$resolved) abort(404, 'Evaluation link is invalid or has expired.');

        if (! $activity->evaluation_open) {
            return back()->with('error', 'This evaluation period has closed.');
        }

        if ($activity->isTrainingWorkshopSeminar()) {
            return $this->storeTws($request, $activity, $hash, $resolved);
        }
        // ... rest unchanged
```

Add the same check as the first line of `storeWalkin()`, right after the `qr_token` check:

```php
    public function storeWalkin(Request $request, Activity $activity, string $qrToken)
    {
        abort_if($activity->qr_token !== $qrToken, 404);

        if (! $activity->evaluation_open) {
            return back()->with('error', 'This evaluation period has closed.');
        }

        // No duplicate check for walk-ins — ... (rest unchanged)
```

`storeTws()`/`storeWalkinTws()` need no separate check — they're only ever reached through `store()`/`storeWalkin()`, which already gate.

- [ ] **Step 4: Run tests to verify they pass**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EvaluationPeriodTest"
```
Expected: PASS (12 tests total).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AMS/EvaluationController.php tests/Feature/AMS/EvaluationPeriodTest.php
git commit -m "feat(ams): block evaluation submission when the evaluation period is closed"
```

---

### Task 5: `Show.vue` — evaluation period status + toggle UI

**Files:**
- Modify: `app/Http/Controllers/AMS/ActivityController.php` (`show()`, `mapActivity()`)
- Modify: `resources/js/Pages/AMS/Show.vue`

**Interfaces:**
- Consumes: `canToggleEvaluationPeriod(Activity): bool` from Task 3.
- Produces: `activity.evaluation_open`, `activity.evaluation_status_changed_at`, `activity.evaluation_status_changed_by` (name string|null), top-level Inertia prop `canToggleEvaluationPeriod` (boolean).

- [ ] **Step 1: Extend the backend payload**

In `app/Http/Controllers/AMS/ActivityController.php`, update the eager-load list in `show()`:

```php
        $activity->load(['creator', 'coProponents.employee', 'participants', 'studentAttendance', 'mealPlans', 'speakers', 'statusChangedBy']);
```

Add to the `Inertia::render('AMS/Show', [...])` array in `show()`:

```php
            'canToggleEvaluationPeriod' => $this->canToggleEvaluationPeriod($activity),
```

Add to `mapActivity()`'s returned array:

```php
            'evaluation_open'              => (bool) $a->evaluation_open,
            'evaluation_status_changed_at' => $a->evaluation_status_changed_at?->toDateTimeString(),
            'evaluation_status_changed_by' => $a->relationLoaded('statusChangedBy') ? $a->statusChangedBy?->name : null,
```

- [ ] **Step 2: Add the props + toggle handler in `Show.vue`'s `<script setup>`**

In `resources/js/Pages/AMS/Show.vue`, extend `defineProps`:

```js
const props = defineProps({
  activity:                  Object,
  participants:               Array,
  employees:                  Array,
  sections:                   Array,
  canEdit:                    Boolean,
  canManage:                  Boolean,
  canToggleEvaluationPeriod:  Boolean,
  evaluations:                Object,
  walkinEvalQr:                Object,
  quizzes:                    { type: Array, default: () => [] },
})
```

Add near `generateCertificates()`/`sendEvaluationLinks()`:

```js
const togglingPeriod = ref(false)

async function toggleEvaluationPeriod() {
  const closing = props.activity.evaluation_open
  const confirmed = await confirmAction({
    icon: 'warning',
    title: closing ? 'Close Evaluation Period?' : 'Reopen Evaluation Period?',
    text: closing
      ? 'Participants and walk-ins who have not yet evaluated will no longer be able to evaluate or receive a certificate. This does not affect anyone already evaluated/certified.'
      : 'Participants and walk-ins will be able to submit evaluations again.',
    confirmText: closing ? 'Yes, close it' : 'Yes, reopen it',
  })
  if (!confirmed) return

  togglingPeriod.value = true
  router.post(
    route('ams.activities.evaluation-period.toggle', props.activity.id),
    { open: !closing },
    { preserveScroll: true, onFinish: () => { togglingPeriod.value = false } }
  )
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt.replace(' ', 'T')).toLocaleString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}
```

(`ref` is already imported at the top of the file.)

- [ ] **Step 3: Add the status card to the Evaluations tab template**

In `resources/js/Pages/AMS/Show.vue`, insert this block immediately inside `<div v-if="activeTab === 'evaluations'" class="space-y-6">`, before the walk-in QR card:

```html
      <!-- Evaluation Period -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
          <div class="flex items-center gap-2">
            <AppBadge :color="activity.evaluation_open ? 'green' : 'red'">
              {{ activity.evaluation_open ? 'Evaluation Period Open' : 'Evaluation Period Closed' }}
            </AppBadge>
          </div>
          <p v-if="activity.evaluation_status_changed_at" class="text-xs text-slate-400 mt-1">
            Last changed by {{ activity.evaluation_status_changed_by ?? 'unknown' }} on {{ formatDateTime(activity.evaluation_status_changed_at) }}
          </p>
          <p v-if="!activity.evaluation_open" class="text-xs text-slate-500 mt-1">
            New evaluations (and certificates for anyone who hasn't evaluated) are blocked while closed.
          </p>
        </div>
        <AppButton
          v-if="canToggleEvaluationPeriod"
          :variant="activity.evaluation_open ? 'danger' : 'success'"
          size="sm"
          :loading="togglingPeriod"
          :disabled="togglingPeriod"
          @click="toggleEvaluationPeriod"
        >
          {{ activity.evaluation_open ? 'Close Evaluation Period' : 'Reopen Evaluation Period' }}
        </AppButton>
      </div>

```

- [ ] **Step 4: Verify the build**

Run:
```
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```
Expected: build succeeds with no errors.

- [ ] **Step 5: Manual smoke check**

Open the app (`http://localhost:8080`), navigate to an activity as its owner, open the Evaluations tab, confirm the status badge and toggle button render, click "Close Evaluation Period," confirm the dialog, and confirm the badge flips to "Closed" with the "last changed by" line populated.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AMS/ActivityController.php resources/js/Pages/AMS/Show.vue
git commit -m "feat(ams): show evaluation period status and toggle control on activity page"
```

---

### Task 6: `Evaluate.vue` / `EvaluateTWS.vue` — closed-period UI

**Files:**
- Modify: `resources/js/Pages/AMS/Evaluate.vue`
- Modify: `resources/js/Pages/AMS/EvaluateTWS.vue`

**Interfaces:**
- Consumes: `evaluationClosed` prop from Task 4.

- [ ] **Step 1: Add the prop and closed-state block to `Evaluate.vue`**

In `resources/js/Pages/AMS/Evaluate.vue`, add to `defineProps`:

```js
const props = defineProps({
  activity:         Object,
  participant:      Object,
  hash:             { type: String, default: null },
  qrToken:          { type: String, default: null },
  walkin:           { type: Boolean, default: false },
  alreadyEvaluated: Boolean,
  evaluationClosed: { type: Boolean, default: false },
})
```

Change line 119's `v-if` and add a new block right after the "Already evaluated" `</div>` (around line 130):

```html
      <!-- Already evaluated -->
      <div v-if="alreadyEvaluated || flashSuccess"
           class="bg-white rounded-2xl shadow-sm border border-success-100 p-8 text-center">
        <div class="flex justify-center mb-4">
          <div class="w-16 h-16 rounded-full bg-success-100 flex items-center justify-center">
            <CheckBadgeIcon class="w-9 h-9 text-success-600" />
          </div>
        </div>
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Evaluation Submitted</h2>
        <p class="text-sm text-slate-500">
          {{ flashSuccess ?? 'You have already submitted an evaluation for this activity. Thank you!' }}
        </p>
      </div>

      <!-- Evaluation period closed -->
      <div v-else-if="evaluationClosed"
           class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Evaluation Period Closed</h2>
        <p class="text-sm text-slate-500">
          The evaluation period for this activity has closed. Evaluations are no longer being accepted.
        </p>
      </div>

      <!-- Evaluation form -->
      <form v-else @submit.prevent="submit" class="space-y-6">
```

(The pre-existing `<form v-else ...>` tag is replaced by the same tag — the only change is that it now follows two `v-if`/`v-else-if` siblings instead of one.)

- [ ] **Step 2: Mirror the same change in `EvaluateTWS.vue`**

Apply the identical `defineProps` addition and the identical closed-state block (inserted the same way, right before the form, after the "already evaluated" block at line 162) to `resources/js/Pages/AMS/EvaluateTWS.vue`.

- [ ] **Step 3: Verify the build**

Run:
```
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```
Expected: build succeeds.

- [ ] **Step 4: Manual smoke check**

With an activity whose period is closed (toggle it via Task 5's UI), open its evaluation link (`Show.vue` → Participants tab → copy evaluation link) in a private/incognito window and confirm the "Evaluation Period Closed" message renders instead of the form, for both an in-house and a TWS activity, and for the walk-in QR link.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/AMS/Evaluate.vue resources/js/Pages/AMS/EvaluateTWS.vue
git commit -m "feat(ams): show closed-period message on participant/walk-in evaluation forms"
```

---

### Task 7: Per-day attendance — `applyDailyAttendance()` helper + employee attendance

**Files:**
- Modify: `app/Http/Controllers/AMS/ActivityController.php`
- Test: `tests/Feature/AMS/PerDayAttendanceTest.php` (add tests)

**Interfaces:**
- Consumes: `Activity::isMultiDay()` (Task 1), `ActivityAttendanceDay` (Task 2).
- Produces: `ActivityController::applyDailyAttendance(Activity, string $participantType, int $participantId, array $daily): array` returning `['attended' => 'yes'|'no', 'hours_attended' => float]`.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AMS/PerDayAttendanceTest.php`)

```php
use App\Models\AMS\ActivityCoProponent; // unused placeholder removed below if not needed
use App\Models\AMS\ActivityParticipant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

    public function test_single_day_activity_ignores_daily_payload(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-10');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), [
                'attended' => 'yes',
                'hours_attended' => 8,
                'daily' => [
                    ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ams_activity_attendance_days', 0);
        $this->assertSame('yes', $participant->fresh()->attended);
        $this->assertSame('8.00', $participant->fresh()->hours_attended);
    }

    public function test_multiday_employee_attendance_upserts_daily_rows_and_computes_rollup(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-12');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), [
                'attended' => 'no',
                'hours_attended' => 0,
                'daily' => [
                    ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8],
                    ['date' => '2026-08-11', 'attended' => 'no', 'hours_attended' => 0],
                    ['date' => '2026-08-12', 'attended' => 'yes', 'hours_attended' => 6],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ams_activity_attendance_days', 3);
        $this->assertDatabaseHas('ams_activity_attendance_days', [
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $attendee->id, 'date' => '2026-08-10', 'attended' => 'yes',
        ]);

        $participant->refresh();
        $this->assertSame('yes', $participant->attended); // present on any day
        $this->assertSame('14.00', $participant->hours_attended); // 8 + 6, day 2 absent contributes 0
    }

    public function test_resaving_daily_attendance_updates_existing_rows_not_duplicates(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);
        $payload = [
            'attended' => 'no', 'hours_attended' => 0,
            'daily' => [
                ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8],
                ['date' => '2026-08-11', 'attended' => 'yes', 'hours_attended' => 8],
            ],
        ];

        $this->actingAs($owner)->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), $payload);
        $this->assertDatabaseCount('ams_activity_attendance_days', 2);

        $payload['daily'][1]['hours_attended'] = 4;
        $this->actingAs($owner)->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), $payload);

        $this->assertDatabaseCount('ams_activity_attendance_days', 2); // still 2, not 4
        $this->assertDatabaseHas('ams_activity_attendance_days', [
            'activity_id' => $activity->id, 'date' => '2026-08-11', 'hours_attended' => 4,
        ]);
        $this->assertSame('12.00', $participant->fresh()->hours_attended); // 8 + 4
    }

    private function makeMultiDayActivity(User $owner, string $start, string $end): Activity
    {
        return Activity::create([
            'user_id' => $owner->id,
            'title' => 'Multi-day Test Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Daily Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
```

Add `use App\Models\AMS\Activity;` to the file's `use` block if not already present (it is, from Task 2's first test).

- [ ] **Step 2: Run tests to verify they fail**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: FAIL — `daily` payload currently has no effect and isn't even validated.

- [ ] **Step 3: Add the helper and wire it into `saveEmployeeAttendance()`**

In `app/Http/Controllers/AMS/ActivityController.php`, add `use App\Models\AMS\ActivityAttendanceDay;` to the imports, and add this private helper near `shouldInvalidateCertificate()`:

```php
    /**
     * Upserts per-day attendance rows for one participant and returns the
     * recomputed rollup: present on any day → 'yes'; hours summed across
     * days actually marked present. Callers only invoke this for multi-day
     * activities — single-day activities keep using the plain scalar fields.
     */
    private function applyDailyAttendance(Activity $activity, string $participantType, int $participantId, array $daily): array
    {
        DB::transaction(function () use ($activity, $participantType, $participantId, $daily) {
            foreach ($daily as $day) {
                ActivityAttendanceDay::updateOrCreate(
                    [
                        'activity_id'      => $activity->id,
                        'participant_type' => $participantType,
                        'participant_id'   => $participantId,
                        'date'             => $day['date'],
                    ],
                    [
                        'attended'       => $day['attended'],
                        'hours_attended' => $day['hours_attended'] ?? 0,
                    ]
                );
            }
        });

        $rows = ActivityAttendanceDay::where('activity_id', $activity->id)
            ->where('participant_type', $participantType)
            ->where('participant_id', $participantId)
            ->get();

        return [
            'attended'       => $rows->contains('attended', 'yes') ? 'yes' : 'no',
            'hours_attended' => (float) $rows->where('attended', 'yes')->sum('hours_attended'),
        ];
    }
```

Update `saveEmployeeAttendance()`:

```php
    public function saveEmployeeAttendance(Request $request, Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeManage($activity);
        abort_unless(
            $participant->activity_id === $activity->id && $participant->participant_type === 'employee',
            404
        );

        $data = $request->validate([
            'attended'                       => 'required|in:yes,no',
            'hours_attended'                 => 'nullable|numeric|min:0|max:99.99',
            'daily'                          => 'nullable|array',
            'daily.*.date'                   => 'required_with:daily|date',
            'daily.*.attended'               => 'required_with:daily|in:yes,no',
            'daily.*.hours_attended'         => 'nullable|numeric|min:0|max:99.99',
        ]);

        if ($activity->isMultiDay() && ! empty($data['daily'])) {
            $data = array_merge(
                $data,
                $this->applyDailyAttendance($activity, 'employee', $participant->participant_id, $data['daily'])
            );
        }
        unset($data['daily']);

        if ($participant->certificate_path && (
            ! $this->evaluationEligibility->hasEvaluated($activity, 'employee', $participant->participant_id)
            || $this->shouldInvalidateCertificate($participant, $data)
        )) {
            $this->certService->delete($participant->certificate_path);
            $data['certificate_path'] = null;
        }
        $participant->update($data);

        return back()->with('success', 'Attendance saved. Certificate generation remains pending until evaluation is complete.');
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: PASS (4 tests total).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AMS/ActivityController.php tests/Feature/AMS/PerDayAttendanceTest.php
git commit -m "feat(ams): support per-day attendance for multi-day activities (employees)"
```

---

### Task 8: Per-day attendance — section/student attendance

**Files:**
- Modify: `app/Http/Controllers/AMS/ActivityController.php`
- Test: `tests/Feature/AMS/PerDayAttendanceTest.php` (add test)

**Interfaces:**
- Consumes: `applyDailyAttendance()` from Task 7.

- [ ] **Step 1: Write the failing test** (append to `tests/Feature/AMS/PerDayAttendanceTest.php`)

```php
    public function test_multiday_section_student_attendance_upserts_daily_rows_and_rollup(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $section = \App\Models\AMS\ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => 1, 'participant_type' => 'section',
        ]);
        $studentAttendance = \App\Models\AMS\ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => 501, 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-section-attendance', [$activity, $section]), [
                'students' => [[
                    'attendance_id' => $studentAttendance->id,
                    'student_id' => 501,
                    'attended' => 'no',
                    'hours_attended' => 0,
                    'daily' => [
                        ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 7],
                        ['date' => '2026-08-11', 'attended' => 'yes', 'hours_attended' => 7],
                    ],
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ams_activity_attendance_days', 2);
        $this->assertDatabaseHas('ams_activity_attendance_days', [
            'activity_id' => $activity->id, 'participant_type' => 'student',
            'participant_id' => 501, 'date' => '2026-08-10', 'attended' => 'yes',
        ]);

        $studentAttendance->refresh();
        $this->assertSame('yes', $studentAttendance->attended);
        $this->assertSame('14.00', $studentAttendance->hours_attended);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: FAIL — `daily` per student row is not yet accepted/processed.

- [ ] **Step 3: Update `saveSectionAttendance()`**

```php
    public function saveSectionAttendance(Request $request, Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeManage($activity);
        abort_unless(
            $participant->activity_id === $activity->id && $participant->participant_type === 'section',
            404
        );

        $data = $request->validate([
            'students'                          => 'required|array',
            'students.*.attendance_id'          => 'required|integer',
            'students.*.attended'               => 'required|in:yes,no',
            'students.*.hours_attended'         => 'nullable|numeric|min:0|max:999.99',
            'students.*.student_id'             => 'required|integer',
            'students.*.daily'                  => 'nullable|array',
            'students.*.daily.*.date'           => 'required_with:students.*.daily|date',
            'students.*.daily.*.attended'       => 'required_with:students.*.daily|in:yes,no',
            'students.*.daily.*.hours_attended' => 'nullable|numeric|min:0|max:999.99',
        ]);

        foreach ($data['students'] as $row) {
            $attendance = ActivityStudentAttendance::where('activity_id', $activity->id)
                ->where('id', $row['attendance_id'])
                ->where('participant_id', $row['student_id'])
                ->first();
            if (!$attendance) continue;

            $attendanceData = [
                'attended'       => $row['attended'],
                'hours_attended' => $row['hours_attended'] ?? 0,
            ];

            if ($activity->isMultiDay() && ! empty($row['daily'])) {
                $attendanceData = array_merge(
                    $attendanceData,
                    $this->applyDailyAttendance($activity, 'student', $row['student_id'], $row['daily'])
                );
            }

            if ($attendance->certificate_path && (
                ! $this->evaluationEligibility->hasEvaluated($activity, 'student', $attendance->participant_id)
                || $this->shouldInvalidateCertificate($attendance, $attendanceData)
            )) {
                $this->certService->delete($attendance->certificate_path);
                $attendanceData['certificate_path'] = null;
            }
            $attendance->update($attendanceData);
        }

        return back()->with('success', 'Attendance saved. Certificates are generated only for students who have completed the evaluation.');
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: PASS (5 tests total).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AMS/ActivityController.php tests/Feature/AMS/PerDayAttendanceTest.php
git commit -m "feat(ams): support per-day attendance for multi-day activities (students)"
```

---

### Task 9: Expose per-day data via `show()` and `sectionStudents()`

**Files:**
- Modify: `app/Http/Controllers/AMS/ActivityController.php`
- Test: `tests/Feature/AMS/PerDayAttendanceTest.php` (add tests)

**Interfaces:**
- Produces: `activity.is_multi_day` (bool), `activity.attendance_days` (array of `Y-m-d` strings) on the `Show` page's `activity` prop; `participants[].daily` (object keyed by date → `{attended, hours}`) on both the `Show` page's `participants` prop and the `sectionStudents()` JSON response.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AMS/PerDayAttendanceTest.php`)

```php
    public function test_show_page_exposes_multi_day_flag_and_per_participant_daily_data(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
        ]);
        \App\Models\AMS\ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $attendee->id, 'date' => '2026-08-10',
            'attended' => 'yes', 'hours_attended' => 8,
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.show', $activity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activity.is_multi_day', true)
                ->where('activity.attendance_days', ['2026-08-10', '2026-08-11'])
                ->where("participants.0.daily.2026-08-10.attended", 'yes')
            );
    }

    public function test_section_students_endpoint_exposes_per_day_data(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $section = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => 1, 'participant_type' => 'section',
        ]);
        \Illuminate\Support\Facades\DB::table('section_students')->insert([
            'sectionid' => 1, 'studentid' => 501,
        ]);
        \App\Models\Student::create(['id' => 501, 'firstname' => 'Juan', 'lastname' => 'Cruz']);
        \App\Models\AMS\ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => 501, 'attended' => 'yes', 'hours_attended' => 7,
        ]);
        \App\Models\AMS\ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'student',
            'participant_id' => 501, 'date' => '2026-08-11', 'attended' => 'yes', 'hours_attended' => 7,
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('ams.activities.participants.students', [$activity, $section]))
            ->assertOk();

        $json = $response->json();
        $this->assertSame('yes', $json[0]['daily']['2026-08-11']['attended']);
    }
```

Note: adjust the `Student::create` fixture fields to match whatever columns are actually `NOT NULL`/required on the `students` table in this environment — check `database/migrations` for the `students` table if the test fails on a missing-column error, and add the minimum additional fields needed (this is the only place in this task where the exact schema might differ from the snippet above).

- [ ] **Step 2: Run tests to verify they fail**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: FAIL — `is_multi_day`/`attendance_days`/`daily` keys don't exist yet in either response.

- [ ] **Step 3: Update `mapActivity()`**

Add to the array returned by `mapActivity()` in `app/Http/Controllers/AMS/ActivityController.php`:

```php
            'is_multi_day'     => $a->isMultiDay(),
            'attendance_days'  => $a->attendanceDayList(),
```

- [ ] **Step 4: Update `show()`'s participant mapping**

In `show()`, before building `$participants`, add:

```php
        $attendanceDays = $activity->isMultiDay()
            ? ActivityAttendanceDay::where('activity_id', $activity->id)->get()
                ->groupBy(fn ($row) => $row->participant_type . ':' . $row->participant_id)
            : collect();
```

Then, inside the `$activity->participants->map(function ($p) use (...))` closure, add `$attendanceDays` to the `use` clause and add a `daily` key to both the section-row and employee-row returned arrays (section rows get an empty array since sections aren't individual attendees; employee rows get the real lookup):

```php
        $participants = $activity->participants->map(function ($p) use ($sectionsMap, $employeesMap, $evaluations, $activity, $attendanceDays) {
            $evalHash      = md5($p->participant_id . '-' . $activity->id);
            $evaluateUrl   = route('ams.activities.evaluate.show', [$activity->id, $evalHash]);

            if ($p->participant_type === 'section') {
                $s = $sectionsMap[$p->participant_id] ?? null;
                return [
                    'id'             => $p->id,
                    'participant_id' => $p->participant_id,
                    'type'           => 'section',
                    'label'          => $s ? "Grade {$s->levelid} — {$s->sectionname}" : "Section #{$p->participant_id}",
                    'attended'       => $p->attended,
                    'hours_attended' => $p->hours_attended,
                    'evaluated'      => isset($evaluations['section:' . $p->participant_id]),
                    'evaluate_url'   => $evaluateUrl,
                ];
            }
            $u = $employeesMap[$p->participant_id] ?? null;
            return [
                'id'             => $p->id,
                'participant_id' => $p->participant_id,
                'type'           => 'employee',
                'label'          => $u?->name ?? "Employee #{$p->participant_id}",
                'attended'       => $p->attended,
                'hours_attended' => $p->hours_attended,
                'evaluated'      => isset($evaluations['employee:' . $p->participant_id]),
                'has_certificate' => (bool) $p->certificate_path,
                'evaluate_url'   => $evaluateUrl,
                'daily'          => $attendanceDays->get('employee:' . $p->participant_id, collect())
                    ->mapWithKeys(fn ($row) => [$row->date => ['attended' => $row->attended, 'hours' => (float) $row->hours_attended]]),
            ];
        })->values()->all();
```

- [ ] **Step 5: Update `sectionStudents()`**

```php
    public function sectionStudents(Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeView($activity);
        abort_unless(
            $participant->activity_id === $activity->id && $participant->participant_type === 'section',
            404
        );

        $studentIds = DB::table('section_students')
            ->where('sectionid', $participant->participant_id)
            ->pluck('studentid');

        $attendance = ActivityStudentAttendance::where('activity_id', $activity->id)
            ->whereIn('participant_id', $studentIds)
            ->get()
            ->keyBy('participant_id');
        $evaluations = $this->evaluationEligibility->evaluatedMap($activity);

        $attendanceDays = $activity->isMultiDay()
            ? ActivityAttendanceDay::where('activity_id', $activity->id)
                ->where('participant_type', 'student')
                ->whereIn('participant_id', $studentIds)
                ->get()
                ->groupBy('participant_id')
            : collect();

        $students = Student::whereIn('id', $studentIds)
            ->orderBy('lastname')
            ->get(['id', 'firstname', 'lastname', 'middlename', 'student_email']);

        return response()->json(
            $students->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->full_name,
                'attended'       => $attendance[$s->id]?->attended ?? 'no',
                'hours_attended' => $attendance[$s->id]?->hours_attended ?? '0.00',
                'attendance_id'  => $attendance[$s->id]?->id,
                'evaluated'      => isset($evaluations['student:' . $s->id]),
                'has_certificate' => (bool) $attendance[$s->id]?->certificate_path,
                'daily'          => $attendanceDays->get($s->id, collect())
                    ->mapWithKeys(fn ($row) => [$row->date => ['attended' => $row->attended, 'hours' => (float) $row->hours_attended]]),
            ])->values()
        );
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerDayAttendanceTest"
```
Expected: PASS (7 tests total).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AMS/ActivityController.php tests/Feature/AMS/PerDayAttendanceTest.php
git commit -m "feat(ams): expose per-day attendance data in activity show and section-students endpoints"
```

---

### Task 10: `Show.vue` — employee per-day attendance grid

**Files:**
- Modify: `resources/js/Pages/AMS/Show.vue`

**Interfaces:**
- Consumes: `activity.is_multi_day`, `activity.attendance_days`, `participants[].daily` from Task 9.

- [ ] **Step 1: Extend employee attendance state**

In `resources/js/Pages/AMS/Show.vue`'s `<script setup>`, update the employee attendance initialization and add expand state + a save function that includes `daily`:

```js
// Local editable state for employee attendance rows
const empAttendance = reactive({})
props.participants.filter(p => p.type === 'employee').forEach(p => {
  empAttendance[p.id] = {
    attended: p.attended,
    hours: p.hours_attended ?? '0.00',
    daily: { ...(p.daily ?? {}) },
  }
})

function initEmpRow(p) {
  if (!empAttendance[p.id]) {
    empAttendance[p.id] = { attended: p.attended, hours: p.hours_attended ?? '0.00', daily: { ...(p.daily ?? {}) } }
  }
}

function getEmpDay(p, date) {
  const row = empAttendance[p.id]
  if (!row.daily[date]) row.daily[date] = { attended: 'no', hours: 0 }
  return row.daily[date]
}

const expandedEmpDaily = ref(null)
function toggleEmpDailyExpand(p) {
  expandedEmpDaily.value = expandedEmpDaily.value === p.id ? null : p.id
}
```

Update `saveEmpAttendance()` to include the daily payload for multi-day activities:

```js
function saveEmpAttendance(p) {
  const row = empAttendance[p.id]
  const payload = { attended: row.attended, hours_attended: row.hours }
  if (props.activity.is_multi_day) {
    payload.daily = props.activity.attendance_days.map(date => ({
      date,
      attended: row.daily[date]?.attended ?? 'no',
      hours_attended: row.daily[date]?.hours ?? 0,
    }))
  }
  router.post(
    route('ams.activities.participants.save-attendance', [props.activity.id, p.id]),
    payload,
    { preserveScroll: true }
  )
}

function formatDayLabel(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', {
    weekday: 'short', month: 'short', day: 'numeric',
  })
}
```

- [ ] **Step 2: Update the employee row template**

In the participants table, replace the Hours/Status `<td>`s for the employee row (lines ~739–757) so they only show the quick single-toggle for single-day activities, and add an expand toggle + nested grid row for multi-day activities:

```html
              <!-- Employee row -->
              <tr v-if="p.type === 'employee'" :ref="() => initEmpRow(p)" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">
                  <span class="inline-flex items-center gap-1.5">
                    <button v-if="activity.is_multi_day" @click="toggleEmpDailyExpand(p)" class="shrink-0">
                      <component :is="expandedEmpDaily === p.id ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4 text-slate-400" />
                    </button>
                    {{ p.label }}
                  </span>
                </td>
                <td class="px-3 py-3 text-center">
                  <AppBadge color="indigo">Employee</AppBadge>
                </td>
                <td class="px-3 py-3 text-center">
                  <input v-if="canManage && empAttendance[p.id] && !activity.is_multi_day"
                         v-model="empAttendance[p.id].hours"
                         type="number" min="0" max="99" step="0.5"
                         class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <span v-else class="text-slate-600">{{ empAttendance[p.id]?.hours ?? p.hours_attended ?? '—' }}</span>
                </td>
                <td class="px-3 py-3 text-center">
                  <button v-if="canManage && empAttendance[p.id] && !activity.is_multi_day" @click="toggleAttendance(p)">
                    <AppBadge :color="attendanceColor(empAttendance[p.id].attended)">
                      <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                      {{ empAttendance[p.id].attended === 'yes' ? 'Present' : 'Absent' }}
                    </AppBadge>
                  </button>
                  <AppBadge v-else :color="attendanceColor(empAttendance[p.id]?.attended ?? p.attended)">
                    <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                    {{ (empAttendance[p.id]?.attended ?? p.attended) === 'yes' ? 'Present' : 'Absent' }}
                  </AppBadge>
                </td>
```

(The rest of the row — Evaluated cell and Actions cell — is unchanged.)

Immediately after the employee row's closing `</tr>` (right before the `<!-- Section row -->` comment), add the nested day-grid row:

```html
              <tr v-if="p.type === 'employee' && activity.is_multi_day && expandedEmpDaily === p.id">
                <td colspan="6" class="bg-slate-50 px-6 py-4 border-t border-slate-100">
                  <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-slate-500">Per-day attendance</span>
                    <AppButton v-if="canManage" size="sm" @click="saveEmpAttendance(p)">Save Daily Attendance</AppButton>
                  </div>
                  <div class="border border-slate-200 rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="bg-white border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                          <th class="text-left px-3 py-2">Day</th>
                          <th class="text-center px-3 py-2 w-28">Attendance</th>
                          <th class="text-center px-3 py-2 w-24">Hours</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-50">
                        <tr v-for="date in activity.attendance_days" :key="date" class="bg-white">
                          <td class="px-3 py-2 text-slate-700">{{ formatDayLabel(date) }}</td>
                          <td class="px-3 py-2 text-center">
                            <button v-if="canManage" @click="getEmpDay(p, date).attended = getEmpDay(p, date).attended === 'yes' ? 'no' : 'yes'">
                              <AppBadge :color="attendanceColor(getEmpDay(p, date).attended)">
                                {{ getEmpDay(p, date).attended === 'yes' ? 'Present' : 'Absent' }}
                              </AppBadge>
                            </button>
                            <AppBadge v-else :color="attendanceColor(getEmpDay(p, date).attended)">
                              {{ getEmpDay(p, date).attended === 'yes' ? 'Present' : 'Absent' }}
                            </AppBadge>
                          </td>
                          <td class="px-3 py-2 text-center">
                            <input v-if="canManage" v-model="getEmpDay(p, date).hours"
                                   type="number" min="0" max="99" step="0.5"
                                   class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <span v-else class="text-slate-600 text-xs">{{ getEmpDay(p, date).hours }}</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
```

- [ ] **Step 3: Verify the build**

Run:
```
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```
Expected: build succeeds.

- [ ] **Step 4: Manual smoke check**

Create/edit an activity spanning 3 days, add an employee participant, open its Participants tab, expand the employee row's day-grid, mark two of three days present with different hours, click "Save Daily Attendance," reload the page, and confirm the grid and the main row's rollup Present/Absent + hours both reflect what was saved.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/AMS/Show.vue
git commit -m "feat(ams): per-day attendance grid for employee participants on multi-day activities"
```

---

### Task 11: `Show.vue` — student per-day attendance grid

**Files:**
- Modify: `resources/js/Pages/AMS/Show.vue`

**Interfaces:**
- Consumes: `activity.is_multi_day`, `activity.attendance_days`, per-student `daily` from Task 9's `sectionStudents()` response.

- [ ] **Step 1: Extend section-student state**

Update `toggleSectionExpand()` so loaded student rows seed a `daily` object, and add the analogous helpers:

```js
async function toggleSectionExpand(p) {
  if (expandedSection.value === p.id) { expandedSection.value = null; return }
  expandedSection.value = p.id
  if (sectionStudents.value[p.id]) return

  loadingStudents.value[p.id] = true
  try {
    const res  = await fetch(
      route('ams.activities.participants.students', [props.activity.id, p.id]),
      { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
    )
    const list = await res.json()
    sectionStudents.value[p.id] = list
    secAttendance[p.id] = list.map(s => ({ ...s, daily: { ...(s.daily ?? {}) } }))
  } finally {
    loadingStudents.value[p.id] = false
  }
}

function getStudentDay(student, date) {
  if (!student.daily[date]) student.daily[date] = { attended: 'no', hours: 0 }
  return student.daily[date]
}

const expandedStudentDaily = ref(null)
function toggleStudentDailyExpand(studentAttendanceId) {
  expandedStudentDaily.value = expandedStudentDaily.value === studentAttendanceId ? null : studentAttendanceId
}
```

Update `saveSectionAttendance()` to include each student's `daily` array for multi-day activities:

```js
function saveSectionAttendance(p) {
  const rows = secAttendance[p.id]
  if (!rows) return
  router.post(
    route('ams.activities.participants.save-section-attendance', [props.activity.id, p.id]),
    {
      students: rows.map(s => ({
        attendance_id:  s.attendance_id,
        attended:       s.attended,
        hours_attended: s.hours_attended,
        student_id:     s.id,
        ...(props.activity.is_multi_day ? {
          daily: props.activity.attendance_days.map(date => ({
            date,
            attended: s.daily[date]?.attended ?? 'no',
            hours_attended: s.daily[date]?.hours ?? 0,
          })),
        } : {}),
      }))
    },
    { preserveScroll: true }
  )
}
```

- [ ] **Step 2: Update the student roster row template**

In the inline student roster table (inside the expanded section row), change the Hours/Attendance `<td>`s to only offer the quick controls for single-day activities, and add a per-student expand button + nested day-grid row, mirroring Task 10's employee pattern:

```html
                            <tr v-for="student in secAttendance[p.id]" :key="student.id" class="bg-white hover:bg-slate-50">
                              <td class="px-3 py-2 text-slate-700">
                                <span class="inline-flex items-center gap-1.5">
                                  <button v-if="activity.is_multi_day" @click="toggleStudentDailyExpand(student.attendance_id)" class="shrink-0">
                                    <component :is="expandedStudentDaily === student.attendance_id ? ChevronUpIcon : ChevronDownIcon" class="w-3.5 h-3.5 text-slate-400" />
                                  </button>
                                  {{ student.name }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <input v-if="canManage && !activity.is_multi_day" v-model="student.hours_attended"
                                       type="number" min="0" max="999" step="0.5"
                                       class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <span v-else class="text-slate-600 text-xs">{{ student.hours_attended }}</span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <button v-if="canManage && !activity.is_multi_day" @click="student.attended = student.attended === 'yes' ? 'no' : 'yes'">
                                  <AppBadge :color="attendanceColor(student.attended)">
                                    <CheckCircleIcon class="w-3 h-3 mr-1" />
                                    {{ student.attended === 'yes' ? 'Present' : 'Absent' }}
                                  </AppBadge>
                                </button>
                                <AppBadge v-else :color="attendanceColor(student.attended)">
                                  <CheckCircleIcon class="w-3 h-3 mr-1" />
                                  {{ student.attended === 'yes' ? 'Present' : 'Absent' }}
                                </AppBadge>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <AppBadge :color="evaluatedColor(student.evaluated)">
                                  {{ student.evaluated ? 'Evaluated' : 'Pending' }}
                                </AppBadge>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <a
                                  v-if="student.attended === 'yes' && student.evaluated && student.has_certificate"
                                  :href="route('ams.activities.certificates.download.student', [activity.id, student.attendance_id])"
                                  target="_blank"
                                  class="inline-flex rounded p-1 text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700"
                                  title="Download certificate"
                                >
                                  <DocumentArrowDownIcon class="h-4 w-4" />
                                </a>
                                <span v-else class="text-xs text-slate-400">—</span>
                              </td>
                            </tr>
                            <tr v-if="activity.is_multi_day && expandedStudentDaily === student.attendance_id" :key="`${student.id}-daily`" class="bg-slate-50">
                              <td colspan="5" class="px-6 py-3 border-t border-slate-100">
                                <div class="flex flex-wrap gap-3">
                                  <div v-for="date in activity.attendance_days" :key="date" class="border border-slate-200 rounded-lg px-3 py-2 bg-white min-w-[140px]">
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-1">{{ formatDayLabel(date) }}</p>
                                    <button v-if="canManage" @click="getStudentDay(student, date).attended = getStudentDay(student, date).attended === 'yes' ? 'no' : 'yes'">
                                      <AppBadge :color="attendanceColor(getStudentDay(student, date).attended)">
                                        {{ getStudentDay(student, date).attended === 'yes' ? 'Present' : 'Absent' }}
                                      </AppBadge>
                                    </button>
                                    <input v-if="canManage" v-model="getStudentDay(student, date).hours"
                                           type="number" min="0" max="999" step="0.5"
                                           class="mt-1 w-full text-center rounded border border-slate-200 px-1 py-0.5 text-xs" />
                                  </div>
                                </div>
                              </td>
                            </tr>
```

- [ ] **Step 3: Verify the build**

Run:
```
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```
Expected: build succeeds.

- [ ] **Step 4: Manual smoke check**

On the same 3-day activity from Task 10, add a section participant, expand it, expand one student's daily grid, mark attendance across the days, click "Save Attendance," reload, and confirm both the per-day grid and the roster's rollup Present/Absent + hours reflect the save.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/AMS/Show.vue
git commit -m "feat(ams): per-day attendance grid for student roster on multi-day activities"
```

---

### Task 12: `ActivityReportService`

**Files:**
- Create: `app/Services/AMS/ActivityReportService.php`
- Test: `tests/Feature/AMS/ActivityReportTest.php` (new file)

**Interfaces:**
- Consumes: `ActivityEvaluationEligibilityService::evaluatedMap()` (existing), `Activity::isMultiDay()`/`attendanceDayList()` (Task 1), `ActivityAttendanceDay` (Task 2).
- Produces: `ActivityReportService::buildReport(Activity $activity): array` shaped as:
  ```php
  [
      'days' => ['2026-08-10', '2026-08-11'], // empty if not multi-day
      'kpis' => [
          'invited' => int, 'present' => int, 'attendance_rate' => float,
          'evaluated' => int, 'evaluation_rate' => float, 'certificates_issued' => int,
      ],
      'rows' => [
          [
              'name' => string, 'type' => 'Employee'|'Student', 'section' => string|null,
              'attended' => bool, 'hours_attended' => float, 'evaluated' => bool,
              'certificate_issued' => bool,
              'daily' => [['date' => '2026-08-10', 'attended' => bool], ...], // empty if not multi-day
          ],
      ],
  ]
  ```

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AMS\ActivityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_day_activity_report_has_no_day_columns_and_correct_kpis(): void
    {
        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Single Day Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $present = User::factory()->create(['name' => 'Present Employee']);
        $absent  = User::factory()->create(['name' => 'Absent Employee']);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $present->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
            'certificate_path' => 'ams/certificates/present.pdf',
        ]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $absent->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee', 'participant_id' => $present->id,
        ]);

        $report = app(ActivityReportService::class)->buildReport($activity);

        $this->assertSame([], $report['days']);
        $this->assertSame(2, $report['kpis']['invited']);
        $this->assertSame(1, $report['kpis']['present']);
        $this->assertSame(50.0, $report['kpis']['attendance_rate']);
        $this->assertSame(1, $report['kpis']['evaluated']);
        $this->assertSame(1, $report['kpis']['certificates_issued']);

        $presentRow = collect($report['rows'])->firstWhere('name', 'Present Employee');
        $this->assertTrue($presentRow['attended']);
        $this->assertTrue($presentRow['evaluated']);
        $this->assertTrue($presentRow['certificate_issued']);
        $this->assertSame([], $presentRow['daily']);
    }

    public function test_multiday_activity_report_includes_per_day_columns(): void
    {
        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Multi-day Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
        ]);
        $employee = User::factory()->create(['name' => 'Daily Employee']);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $employee->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
        ]);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $employee->id, 'date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8,
        ]);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $employee->id, 'date' => '2026-08-11', 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $report = app(ActivityReportService::class)->buildReport($activity);

        $this->assertSame(['2026-08-10', '2026-08-11'], $report['days']);
        $row = $report['rows'][0];
        $this->assertSame(
            [
                ['date' => '2026-08-10', 'attended' => true],
                ['date' => '2026-08-11', 'attended' => false],
            ],
            $row['daily']
        );
    }

    public function test_student_row_includes_section_label(): void
    {
        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Student Section Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $section = Section::create(['sectionname' => 'Newton', 'levelid' => 7, 'is_active' => true]);
        DB::table('section_students')->insert(['sectionid' => $section->id, 'studentid' => 501]);
        Student::create(['id' => 501, 'firstname' => 'Ada', 'lastname' => 'Lovelace']);
        ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => 501, 'attended' => 'yes', 'hours_attended' => 8,
        ]);

        $report = app(ActivityReportService::class)->buildReport($activity);

        $studentRow = collect($report['rows'])->firstWhere('type', 'Student');
        $this->assertSame('Grade 7 — Newton', $studentRow['section']);
    }
}
```

Note: as with Task 9, adjust the `Section::create`/`Student::create` fixtures' fields if the actual `sections`/`students` tables require additional `NOT NULL` columns not listed here — check their migrations if the test errors on a missing column.

- [ ] **Step 2: Run test to verify it fails**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ActivityReportTest"
```
Expected: FAIL — class `ActivityReportService` doesn't exist.

- [ ] **Step 3: Create the service**

```php
<?php

namespace App\Services\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityReportService
{
    public function __construct(
        private ActivityEvaluationEligibilityService $evaluationEligibility,
    ) {}

    public function buildReport(Activity $activity): array
    {
        $evaluations = $this->evaluationEligibility->evaluatedMap($activity);
        $days        = $activity->attendanceDayList();

        $dailyRows = $activity->isMultiDay()
            ? ActivityAttendanceDay::where('activity_id', $activity->id)->get()
                ->groupBy(fn ($row) => $row->participant_type . ':' . $row->participant_id)
            : collect();

        $rows = collect();

        $employeeParticipants = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')->get();
        $employeesMap = User::whereIn('id', $employeeParticipants->pluck('participant_id'))
            ->with('division')->get(['id', 'name', 'division_id'])->keyBy('id');

        foreach ($employeeParticipants as $p) {
            $employee = $employeesMap[$p->participant_id] ?? null;
            $rows->push($this->buildRow(
                name: $employee?->name ?? "Employee #{$p->participant_id}",
                type: 'Employee',
                sectionLabel: $employee?->division?->division_name,
                attended: $p->attended,
                hours: (float) $p->hours_attended,
                evaluated: isset($evaluations['employee:' . $p->participant_id]),
                certificateIssued: (bool) $p->certificate_path,
                days: $days,
                dailyForParticipant: $dailyRows->get('employee:' . $p->participant_id, collect()),
            ));
        }

        $studentRows = ActivityStudentAttendance::where('activity_id', $activity->id)->get();
        $studentIds  = $studentRows->pluck('participant_id');
        $studentsMap = Student::whereIn('id', $studentIds)->get(['id', 'firstname', 'lastname', 'middlename'])->keyBy('id');

        $studentSectionIds = DB::table('section_students')->whereIn('studentid', $studentIds)->pluck('sectionid', 'studentid');
        $sectionsMap = Section::whereIn('id', $studentSectionIds->unique()->values())
            ->get(['id', 'sectionname', 'levelid'])->keyBy('id');

        foreach ($studentRows as $r) {
            $student = $studentsMap[$r->participant_id] ?? null;
            $section = $sectionsMap[$studentSectionIds[$r->participant_id] ?? null] ?? null;

            $rows->push($this->buildRow(
                name: $student?->full_name ?? "Student #{$r->participant_id}",
                type: 'Student',
                sectionLabel: $section ? "Grade {$section->levelid} — {$section->sectionname}" : null,
                attended: $r->attended,
                hours: (float) $r->hours_attended,
                evaluated: isset($evaluations['student:' . $r->participant_id]),
                certificateIssued: (bool) $r->certificate_path,
                days: $days,
                dailyForParticipant: $dailyRows->get('student:' . $r->participant_id, collect()),
            ));
        }

        $totalInvited      = $rows->count();
        $totalPresent       = $rows->where('attended', true)->count();
        $totalEvaluated     = $rows->where('evaluated', true)->count();
        $totalCertificates  = $rows->where('certificate_issued', true)->count();

        return [
            'days' => $days,
            'kpis' => [
                'invited'             => $totalInvited,
                'present'             => $totalPresent,
                'attendance_rate'     => $totalInvited > 0 ? round($totalPresent / $totalInvited * 100, 1) : 0.0,
                'evaluated'           => $totalEvaluated,
                'evaluation_rate'     => $totalInvited > 0 ? round($totalEvaluated / $totalInvited * 100, 1) : 0.0,
                'certificates_issued' => $totalCertificates,
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    private function buildRow(
        string $name,
        string $type,
        ?string $sectionLabel,
        string $attended,
        float $hours,
        bool $evaluated,
        bool $certificateIssued,
        array $days,
        Collection $dailyForParticipant,
    ): array {
        $dailyByDate = $dailyForParticipant->keyBy('date');

        return [
            'name'               => $name,
            'type'               => $type,
            'section'            => $sectionLabel,
            'attended'           => $attended === 'yes',
            'hours_attended'     => $hours,
            'evaluated'          => $evaluated,
            'certificate_issued' => $certificateIssued,
            'daily'              => collect($days)->map(fn ($date) => [
                'date'     => $date,
                'attended' => ($dailyByDate->get($date)?->attended ?? 'no') === 'yes',
            ])->values()->all(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ActivityReportTest"
```
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AMS/ActivityReportService.php tests/Feature/AMS/ActivityReportTest.php
git commit -m "feat(ams): add ActivityReportService for comprehensive attendance/evaluation reporting"
```

---

### Task 13: `ActivityReportController` + routes

**Files:**
- Create: `app/Http/Controllers/AMS/ActivityReportController.php`
- Modify: `routes/ams.php`
- Test: `tests/Feature/AMS/ActivityReportTest.php` (add tests)

**Interfaces:**
- Consumes: `ActivityReportService::buildReport()` (Task 12).
- Produces: routes `ams.activities.report` (GET), `ams.activities.report.print` (GET), rendering `AMS/Report` and `AMS/ReportPrint` Inertia pages.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AMS/ActivityReportTest.php`)

```php
use App\Models\AMS\ActivityCoProponent;
use App\Models\Permission;
use App\Models\Role;

    public function test_authorized_user_can_view_report_page(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = Activity::create([
            'user_id' => $owner->id, 'title' => 'Report Page Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.report', $activity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AMS/Report')
                ->where('activity.id', $activity->id)
                ->has('report.kpis')
                ->has('report.rows')
            );
    }

    public function test_print_page_renders_component(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = Activity::create([
            'user_id' => $owner->id, 'title' => 'Print Page Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.report.print', $activity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AMS/ReportPrint'));
    }

    public function test_unrelated_user_cannot_view_report_page(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $stranger = $this->userWithPermission('activities.manage');
        $activity = Activity::create([
            'user_id' => $owner->id, 'title' => 'Private Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);

        $this->actingAs($stranger)
            ->get(route('ams.activities.report', $activity))
            ->assertForbidden();
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Report Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ActivityReportTest"
```
Expected: FAIL — routes/controller don't exist.

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
use App\Services\AMS\ActivityReportService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityReportController extends Controller
{
    public function __construct(private ActivityReportService $reportService) {}

    public function show(Activity $activity)
    {
        $this->authorizeView($activity);
        $activity->load('creator');

        return Inertia::render('AMS/Report', [
            'activity' => $this->activityHeader($activity),
            'report'   => $this->reportService->buildReport($activity),
        ]);
    }

    public function print(Activity $activity)
    {
        $this->authorizeView($activity);
        $activity->load('creator');

        return Inertia::render('AMS/ReportPrint', [
            'activity' => $this->activityHeader($activity),
            'report'   => $this->reportService->buildReport($activity),
        ]);
    }

    private function activityHeader(Activity $activity): array
    {
        return [
            'id'         => $activity->id,
            'title'      => $activity->title,
            'venue'      => $activity->venue,
            'start_date' => $activity->start_date?->toDateString(),
            'end_date'   => $activity->end_date?->toDateString(),
            'proponent'  => $activity->creator?->name,
        ];
    }

    private function authorizeView(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->hasAnyPermission(['activities.view_all', 'activities.monitor', 'activities.evaluation_committee'])) {
            return;
        }

        $isOwner = $activity->user_id === $user->id;
        $isCo    = ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();
        abort_unless($isOwner || $isCo, 403);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/ams.php`, add the import and the two routes inside the existing `ams/activities` prefix group, right after the `evaluation-period.toggle` route added in Task 3:

```php
use App\Http\Controllers\AMS\ActivityReportController;
```

```php
        // ── Comprehensive report ─────────────────────────────────────────────
        Route::get('/{activity}/report',       [ActivityReportController::class, 'show'])->name('report');
        Route::get('/{activity}/report/print', [ActivityReportController::class, 'print'])->name('report.print');

```

Register these **before** the `Route::get('/{activity}', ...)` show route at the bottom of the group (same reasoning as the existing comment already in the file: specific routes must be registered before the catch-all `/{activity}` route).

- [ ] **Step 5: Run tests to verify they pass**

Run:
```
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ActivityReportTest"
```
Expected: PASS (6 tests total).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AMS/ActivityReportController.php routes/ams.php tests/Feature/AMS/ActivityReportTest.php
git commit -m "feat(ams): add activity report show/print routes and controller"
```

---

### Task 14: `AMS/Report.vue` — on-screen report page

**Files:**
- Create: `resources/js/Pages/AMS/Report.vue`

**Interfaces:**
- Consumes: `activity` (`{id, title, venue, start_date, end_date, proponent}`), `report` (`{days, kpis, rows}`) props from Task 13.

- [ ] **Step 1: Create the page**

```vue
<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  ArrowLeftIcon, PrinterIcon, UsersIcon, CheckCircleIcon,
  ClipboardDocumentCheckIcon, DocumentTextIcon, CalendarDaysIcon, MapPinIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activity: Object,
  report:   Object,
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

const search = ref('')
const typeFilter = ref('all')

const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.report.rows.filter(r => {
    if (q && !r.name.toLowerCase().includes(q)) return false
    if (typeFilter.value !== 'all' && r.type !== typeFilter.value) return false
    return true
  })
})

const kpiCards = computed(() => ([
  { label: 'Invited', value: props.report.kpis.invited, icon: UsersIcon, color: 'slate' },
  { label: 'Present', value: `${props.report.kpis.present} (${props.report.kpis.attendance_rate}%)`, icon: CheckCircleIcon, color: 'green' },
  { label: 'Evaluated', value: `${props.report.kpis.evaluated} (${props.report.kpis.evaluation_rate}%)`, icon: ClipboardDocumentCheckIcon, color: 'indigo' },
  { label: 'Certificates Issued', value: props.report.kpis.certificates_issued, icon: DocumentTextIcon, color: 'amber' },
]))
</script>

<template>
  <Head :title="`Report — ${activity.title}`" />
  <AdminLayout :title="`Report — ${activity.title}`">

    <div class="flex items-center justify-between mb-6">
      <a :href="route('ams.activities.show', activity.id)"
         class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="w-4 h-4" /> Back to Activity
      </a>
      <AppButton as="a" :href="route('ams.activities.report.print', activity.id)" target="_blank" variant="secondary" size="sm">
        <PrinterIcon class="w-4 h-4" /> Print / PDF
      </AppButton>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
      <h1 class="text-xl font-bold text-slate-800 mb-2">{{ activity.title }}</h1>
      <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
        <span class="inline-flex items-center gap-1"><CalendarDaysIcon class="w-4 h-4 text-slate-400" />
          {{ formatDate(activity.start_date) }}
          <template v-if="activity.end_date && activity.end_date !== activity.start_date"> – {{ formatDate(activity.end_date) }}</template>
        </span>
        <span v-if="activity.venue" class="inline-flex items-center gap-1"><MapPinIcon class="w-4 h-4 text-slate-400" /> {{ activity.venue }}</span>
        <span>Proponent: {{ activity.proponent ?? '—' }}</span>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div v-for="card in kpiCards" :key="card.label" class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
             :class="{
               slate: 'bg-slate-100 text-slate-500', green: 'bg-success-50 text-success-600',
               indigo: 'bg-indigo-50 text-indigo-600', amber: 'bg-warning-50 text-warning-600',
             }[card.color]">
          <component :is="card.icon" class="w-5 h-5" />
        </div>
        <div>
          <p class="text-xs text-slate-400 uppercase tracking-wide">{{ card.label }}</p>
          <p class="text-lg font-bold text-slate-800">{{ card.value }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3">
        <input v-model="search" placeholder="Search participants…"
               class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1 min-w-48" />
        <select v-model="typeFilter" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="all">All Types</option>
          <option value="Employee">Employees</option>
          <option value="Student">Students</option>
        </select>
        <span class="text-xs text-slate-400 ml-auto">{{ filteredRows.length }} result(s)</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider bg-slate-50">
              <th class="text-left px-4 py-2">Name</th>
              <th class="text-center px-3 py-2">Type</th>
              <th class="text-left px-3 py-2">Section / Division</th>
              <th v-for="date in report.days" :key="date" class="text-center px-3 py-2 whitespace-nowrap">
                {{ new Date(date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}
              </th>
              <th class="text-center px-3 py-2">Overall</th>
              <th class="text-center px-3 py-2">Hours</th>
              <th class="text-center px-3 py-2">Evaluated</th>
              <th class="text-center px-3 py-2">Certificate</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="row in filteredRows" :key="row.name + row.type" class="hover:bg-slate-50">
              <td class="px-4 py-2 font-medium text-slate-700">{{ row.name }}</td>
              <td class="px-3 py-2 text-center"><AppBadge :color="row.type === 'Employee' ? 'indigo' : 'amber'">{{ row.type }}</AppBadge></td>
              <td class="px-3 py-2 text-slate-500">{{ row.section ?? '—' }}</td>
              <td v-for="day in row.daily" :key="day.date" class="px-3 py-2 text-center">
                <CheckCircleIcon v-if="day.attended" class="w-4 h-4 text-success-500 inline" />
                <span v-else class="text-slate-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <AppBadge :color="row.attended ? 'green' : 'red'">{{ row.attended ? 'Present' : 'Absent' }}</AppBadge>
              </td>
              <td class="px-3 py-2 text-center text-slate-600">{{ row.hours_attended }}</td>
              <td class="px-3 py-2 text-center">
                <AppBadge :color="row.evaluated ? 'indigo' : 'slate'">{{ row.evaluated ? 'Yes' : 'No' }}</AppBadge>
              </td>
              <td class="px-3 py-2 text-center">
                <AppBadge :color="row.certificate_issued ? 'green' : 'slate'">{{ row.certificate_issued ? 'Issued' : '—' }}</AppBadge>
              </td>
            </tr>
            <tr v-if="!filteredRows.length">
              <td :colspan="4 + report.days.length" class="px-4 py-10 text-center text-sm text-slate-400 italic">No participants match the current filters.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AdminLayout>
</template>
```

- [ ] **Step 2: Verify the build**

Run:
```
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```
Expected: build succeeds.

- [ ] **Step 3: Manual smoke check**

Visit `/ams/activities/{id}/report` for both a single-day and a multi-day activity with mixed present/absent/evaluated/certificated participants; confirm KPI cards, dynamic day columns (multi-day only), and filters all render correctly.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/AMS/Report.vue
git commit -m "feat(ams): add on-screen comprehensive activity report page"
```

---

### Task 15: `AMS/ReportPrint.vue` + wire entry points into `Show.vue`

**Files:**
- Create: `resources/js/Pages/AMS/ReportPrint.vue`
- Modify: `resources/js/Pages/AMS/Show.vue`

**Interfaces:**
- Consumes: same `activity`/`report` props as Task 14.

- [ ] **Step 1: Create the print page**

Modeled directly on `resources/js/Pages/HumanResource/WFH/PrintAccomplishments.vue`'s letterhead structure:

```vue
<template>
  <Head :title="`Activity Report — ${activity.title}`" />

  <div id="ams-print-root">
    <table id="ams-pt-wrap">
      <thead>
        <tr><td id="ams-pt-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>

      <tfoot>
        <tr><td id="ams-pt-foot">
          <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </tfoot>

      <tbody>
        <tr><td id="ams-pt-body">

          <div style="text-align:center; margin:10px 0 12px;">
            <h2 style="font-size:13pt; font-weight:bold; letter-spacing:1px; margin:0;">ACTIVITY ATTENDANCE &amp; EVALUATION REPORT</h2>
          </div>

          <table class="ams-info-table">
            <thead>
              <tr>
                <th>ACTIVITY</th>
                <th>DATE(S)</th>
                <th>VENUE</th>
                <th>PROPONENT</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>{{ activity.title }}</td>
                <td>{{ dateLabel }}</td>
                <td>{{ activity.venue ?? '—' }}</td>
                <td>{{ activity.proponent ?? '—' }}</td>
              </tr>
            </tbody>
          </table>

          <table class="ams-kpi-table">
            <tbody>
              <tr>
                <td>Invited: <strong>{{ report.kpis.invited }}</strong></td>
                <td>Present: <strong>{{ report.kpis.present }} ({{ report.kpis.attendance_rate }}%)</strong></td>
                <td>Evaluated: <strong>{{ report.kpis.evaluated }} ({{ report.kpis.evaluation_rate }}%)</strong></td>
                <td>Certificates Issued: <strong>{{ report.kpis.certificates_issued }}</strong></td>
              </tr>
            </tbody>
          </table>

          <table class="ams-main-table">
            <thead>
              <tr>
                <th class="ams-col-name">Name</th>
                <th class="ams-col-type">Type</th>
                <th class="ams-col-section">Section / Division</th>
                <th v-for="date in report.days" :key="date" class="ams-col-day">{{ fmtDayShort(date) }}</th>
                <th class="ams-col-status">Overall</th>
                <th class="ams-col-hours">Hours</th>
                <th class="ams-col-status">Evaluated</th>
                <th class="ams-col-status">Certificate</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in report.rows" :key="row.name + row.type">
                <td class="ams-col-name">{{ row.name }}</td>
                <td class="ams-col-type">{{ row.type }}</td>
                <td class="ams-col-section">{{ row.section ?? '—' }}</td>
                <td v-for="day in row.daily" :key="day.date" class="ams-col-day">{{ day.attended ? '✓' : '—' }}</td>
                <td class="ams-col-status">{{ row.attended ? 'Present' : 'Absent' }}</td>
                <td class="ams-col-hours">{{ row.hours_attended }}</td>
                <td class="ams-col-status">{{ row.evaluated ? 'Yes' : 'No' }}</td>
                <td class="ams-col-status">{{ row.certificate_issued ? 'Issued' : '—' }}</td>
              </tr>
              <tr v-if="!report.rows.length">
                <td :colspan="4 + report.days.length" style="text-align:center; padding:16px; color:#aaa;">No participants recorded.</td>
              </tr>
            </tbody>
          </table>

          <div class="ams-sig-section">
            <p class="ams-sig-top">Prepared by:</p>
            <div class="ams-sig-single">
              <div class="ams-sig-name">{{ activity.proponent?.toUpperCase() ?? '—' }}</div>
              <div class="ams-sig-sub">Activity Proponent</div>
            </div>
          </div>

          <div class="ams-sig-section">
            <p class="ams-sig-top">Noted by:</p>
            <div class="ams-sig-single">
              <div class="ams-sig-line"></div>
              <div class="ams-sig-sub">Evaluation Committee</div>
            </div>
          </div>

        </td></tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  activity: Object,
  report:   Object,
})

const dateLabel = computed(() => {
  const start = fmtDay(props.activity.start_date)
  const end = fmtDay(props.activity.end_date)
  return props.activity.start_date === props.activity.end_date ? start : `${start} – ${end}`
})

function fmtDay(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
function fmtDayShort(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

#ams-print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9pt;
  color: #000;
}

#ams-pt-wrap { width: 100%; border-collapse: collapse; }
#ams-pt-head, #ams-pt-foot { padding: 0 0.75in; }
#ams-pt-body { padding: 10px 0.75in; vertical-align: top; }

.ams-info-table, .ams-kpi-table, .ams-main-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}
.ams-info-table th, .ams-info-table td,
.ams-main-table th, .ams-main-table td {
  border: 1.5px solid #000;
  padding: 4px 6px;
  font-size: 8.5pt;
}
.ams-info-table th { font-weight: 700; background: #f5f5f5; text-align: center; }
.ams-main-table th { font-weight: 700; background: #f5f5f5; text-align: center; }
.ams-col-day, .ams-col-status, .ams-col-hours { text-align: center; white-space: nowrap; }
.ams-col-type { text-align: center; }

.ams-kpi-table td { border: 1px solid #ccc; padding: 5px 8px; font-size: 8.5pt; text-align: center; }

.ams-sig-section { margin: 18px 0; }
.ams-sig-top { font-size: 9pt; margin-bottom: 22px; }
.ams-sig-single { display: inline-block; min-width: 220px; }
.ams-sig-name {
  font-weight: 700; font-size: 10pt; text-decoration: underline; text-transform: uppercase;
  border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 3px;
}
.ams-sig-line { border-bottom: 1px solid #000; min-height: 30px; margin-bottom: 3px; }
.ams-sig-sub { font-size: 8.5pt; }

@page { margin: 0.25in 0 0 0; }
@media print {
  body { margin: 0; }
  tr { break-inside: avoid; page-break-inside: avoid; }
}
</style>
```

- [ ] **Step 2: Add "View Report" entry point to `Show.vue`**

In `resources/js/Pages/AMS/Show.vue`, add a "View Report" button next to the existing "Edit"/"Delete" buttons in the header (near line ~411–419):

```html
      <div class="flex items-center gap-2">
        <AppButton as="a" :href="route('ams.activities.report', activity.id)" variant="secondary" size="sm">
          <ChartBarIcon class="w-4 h-4" /> View Report
        </AppButton>
        <template v-if="canEdit">
```

(`ChartBarIcon` is already imported in this file for the Evaluations tab's empty state.)

- [ ] **Step 3: Verify the build**

Run:
```
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build
```
Expected: build succeeds.

- [ ] **Step 4: Manual smoke check**

From an activity's Show page, click "View Report," confirm `AMS/Report.vue` loads with correct data, then click "Print / PDF" and confirm `AMS/ReportPrint.vue` opens in a new tab with the letterhead images, KPI strip, participant table (with day columns for a multi-day activity), and signature blocks, and that the browser print dialog opens automatically.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/AMS/ReportPrint.vue resources/js/Pages/AMS/Show.vue
git commit -m "feat(ams): add printable letterhead report page and wire report entry points"
```

---

## Self-Review Notes

- **Spec coverage:** Evaluation period open/close (Tasks 1, 3–6), certificate blocking as a side-effect of the existing eligibility gate (verified via regression test in Task 4, no code change), per-day attendance for multi-day activities (Tasks 2, 7–11), comprehensive report on-screen + print with letterhead (Tasks 12–15). All spec decisions (default open, manual toggle, owner/co-proponent/evaluation-committee authorization, single-day activities unaffected, no Excel export, no scheduled close) are implemented or explicitly left alone.
- **Placeholder scan:** No TBD/TODO markers. The two fixture notes (Tasks 9 and 12) about verifying `students`/`sections` table columns are not placeholders — they're a heads-up that this plan was written from the migrations read during brainstorming and the exact required columns should be double-checked against `database/migrations/*_create_students_table.php` / `*_create_sections_table.php` if a fixture insert fails, since those tables weren't the focus of this feature and weren't fully re-verified column-by-column.
- **Type consistency:** `applyDailyAttendance()` (Task 7) is defined once and reused identically in Task 8. `ActivityReportService::buildReport()`'s return shape (Task 12) matches exactly what `ActivityReportController` passes through (Task 13) and what `Report.vue`/`ReportPrint.vue` consume (Tasks 14–15) — `days`, `kpis.{invited,present,attendance_rate,evaluated,evaluation_rate,certificates_issued}`, `rows[].{name,type,section,attended,hours_attended,evaluated,certificate_issued,daily}`.
