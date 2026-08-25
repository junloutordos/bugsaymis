# SOS Command Center — Location Resolution & Operational Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the SOS Command Center a resolved "where are they probably right now" location for every alert (student→classroom, faculty→class/office, staff→office), plus responder self-claim, a GPS map, a filterable history/stats view, site-wide real-time responder notification (no more "refresh and check Command Center"), and a reporter-facing live status/"I'm safe now" experience on web matching what AtlasGo already offers.

**Architecture:** A new `LocationResolverService` derives a role-aware location from data that already exists (enrollment→section→schedule→classroom for students, teaching load/schedule→office for faculty/staff), delegating to the existing `AdjustedClassScheduleService` on calendar-adjusted days. `SosAlertService::trigger()` persists a snapshot of this at trigger time; `SosAlertController` recomputes it live for still-open alerts. A new `sos_alert_responders` table backs a self-claim workflow broadcast over the existing `sos-responders` Echo channel. History and stats are read-only aggregates over existing tables — no new subsystem. Tasks 16-19 extend two features that already exist end-to-end for AtlasGo mobile / the public Emergency Alert Broadcast, reusing their exact broadcast channel (`sos-responders`), event (`SosAlertTriggered`), and self-service pattern (`SosAlertService::endByReporter()`) for the web/Atlas side, which currently has neither.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia, Tailwind, `leaflet` (new, OpenStreetMap tiles, no API key), `chart.js` + `vue-chartjs` (already installed).

**Spec:** `docs/superpowers/specs/2026-08-25-command-center-location-and-operations-design.md`

## Global Constraints

- All schema changes are additive/nullable — safe within a single blue-green deploy, no expand/contract split needed.
- No new permission strings — every new capability (claim/unclaim, history, stats) is gated by the existing `sos.respond` permission.
- Scope is Atlas web Command Center only — do not touch AtlasGo mobile in this plan.
- Map is Leaflet + OpenStreetMap tiles — no API key, no backend dependency.
- History/stats are derived from existing `sos_alerts`/`sos_alert_events` timestamps — the only new table is `sos_alert_responders`.
- `LocationResolverService` must check `ClassScheduleDayAdjustment::published()->forDate($date)->first()` before falling back to a raw `class_schedules` query, so location stays correct on flag-ceremony/shortened-class days.
- Tests: `RefreshDatabase`, `Role::firstOrCreate(['name' => ...])`/`Permission::firstOrCreate(...)` for named roles/permissions (never bare `::create()`), and `DB::table('students')->insertGetId([...])` then `Student::find()` for student fixtures (`Student::create()` throws — guarded legacy model).
- Tasks 17-19 touch reporter-facing self-service routes: use **distinct throttle names** per route (`sos-status` vs `sos-end`) — a prior AtlasGo bug (2026-08-22) shared one throttle key between a status-poll route and an end route, exhausting the end route's quota via normal polling. Do not repeat it.
- A silent/duress-triggered alert (`is_silent === true`) must never surface any reporter-facing UI (Task 19) — that defeats the entire purpose of silent mode.

---

### Task 1: `sos_alerts` location columns

**Files:**
- Create: `database/migrations/2026_08_25_090000_add_resolved_location_to_sos_alerts_table.php`
- Modify: `app/Models/Sos/SosAlert.php`
- Modify: `tests/Feature/Sos/SosSchemaTest.php`

**Interfaces:**
- Produces: `sos_alerts.resolved_location_type` (string), `.resolved_location_label` (string), `.resolved_building` (string, nullable), `.resolved_room` (string, nullable), `.resolved_source` (string) — all nullable columns, all added to `SosAlert::$fillable`.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->string('resolved_location_type')->nullable()->after('geofence_zone_id');
            $table->string('resolved_location_label')->nullable()->after('resolved_location_type');
            $table->string('resolved_building')->nullable()->after('resolved_location_label');
            $table->string('resolved_room')->nullable()->after('resolved_building');
            $table->string('resolved_source')->nullable()->after('resolved_room');
        });
    }

    public function down(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->dropColumn([
                'resolved_location_type', 'resolved_location_label',
                'resolved_building', 'resolved_room', 'resolved_source',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run the migration in dev**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_25_090000_add_resolved_location_to_sos_alerts_table.php"`
Expected: `Migrating: 2026_08_25_090000_add_resolved_location_to_sos_alerts_table` then `Migrated:` — no errors.

- [ ] **Step 3: Update `SosAlert::$fillable`**

In `app/Models/Sos/SosAlert.php`, change:

```php
    protected $fillable = [
        'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
        'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
        'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
    ];
```

to:

```php
    protected $fillable = [
        'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
        'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
        'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
        'resolved_location_type', 'resolved_location_label',
        'resolved_building', 'resolved_room', 'resolved_source',
    ];
```

- [ ] **Step 4: Update the schema regression test**

In `tests/Feature/Sos/SosSchemaTest.php`, change the `sos_alerts` assertion to:

```php
        $this->assertTrue(Schema::hasColumns('sos_alerts', [
            'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
            'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
            'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
            'resolved_location_type', 'resolved_location_label',
            'resolved_building', 'resolved_room', 'resolved_source',
        ]));
```

- [ ] **Step 5: Run the test**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSchemaTest.php"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_25_090000_add_resolved_location_to_sos_alerts_table.php app/Models/Sos/SosAlert.php tests/Feature/Sos/SosSchemaTest.php
git commit -m "feat(sos): add resolved-location columns to sos_alerts"
```

---

### Task 2: `sos_alert_responders` table + model

**Files:**
- Create: `database/migrations/2026_08_25_090100_create_sos_alert_responders_table.php`
- Create: `app/Models/Sos/SosAlertResponder.php`
- Modify: `app/Models/Sos/SosAlert.php`
- Modify: `tests/Feature/Sos/SosSchemaTest.php`

**Interfaces:**
- Produces: `SosAlertResponder` model (`sos_alert_id`, `user_id`, `claimed_at`, `unclaimed_at`), `SosAlert::responders(): HasMany`, `SosAlert::activeResponders(): HasMany` (where `unclaimed_at` is null).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alert_responders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained('sos_alerts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamp('unclaimed_at')->nullable();

            $table->index(['sos_alert_id', 'unclaimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alert_responders');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_25_090100_create_sos_alert_responders_table.php"`
Expected: `Migrated:` — no errors.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models\Sos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlertResponder extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['sos_alert_id', 'user_id', 'claimed_at', 'unclaimed_at'];

    protected $casts = ['claimed_at' => 'datetime', 'unclaimed_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Add relations to `SosAlert`**

In `app/Models/Sos/SosAlert.php`, add `use Illuminate\Database\Eloquent\Relations\HasMany;` to the imports (alongside the existing `HasMany` import already there — it's already imported for `events()`, so just add the two methods) and add, after `events()`:

```php
    public function responders(): HasMany
    {
        return $this->hasMany(SosAlertResponder::class);
    }

    public function activeResponders(): HasMany
    {
        return $this->hasMany(SosAlertResponder::class)->whereNull('unclaimed_at');
    }
```

- [ ] **Step 5: Update the schema regression test**

In `tests/Feature/Sos/SosSchemaTest.php`, add after the `sos_escalation_tier_users` assertion:

```php
        $this->assertTrue(Schema::hasColumns('sos_alert_responders', [
            'sos_alert_id', 'user_id', 'claimed_at', 'unclaimed_at',
        ]));
```

- [ ] **Step 6: Run the test**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSchemaTest.php"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_25_090100_create_sos_alert_responders_table.php app/Models/Sos/SosAlertResponder.php app/Models/Sos/SosAlert.php tests/Feature/Sos/SosSchemaTest.php
git commit -m "feat(sos): add sos_alert_responders table and model"
```

---

### Task 3: `LocationResolverService` — student resolution

**Files:**
- Create: `app/Services/Sos/LocationResolverService.php`
- Test: `tests/Feature/Sos/LocationResolverServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Student` (id only), `App\Models\FacultyLoading\SchoolYear::current()` scope + `currentTerm(): ?AcademicTerm`, `App\Models\Registrar\StudentEnrollment` (`student_id`, `school_year_id`, `section_id`, `scopeActive`), `App\Models\FacultyLoading\Section` (`classroom_id`, `classroom(): BelongsTo`), `App\Models\FacultyLoading\ClassSchedule` (`scopeClasses`, `scopeOccupying`, `scopeOnDay`, `academic_term_id`, `start_time`, `end_time`, `section_id`, relations `classroom`, `subject`, `faculty`).
- Produces: `LocationResolverService::resolve(Model $triggerable, Carbon $atTime): array` returning `['type' => string, 'label' => string, 'building' => ?string, 'room' => ?string, 'source' => string]`. This exact shape is relied on by Task 4 (faculty branch), Task 6 (persist on trigger), and Task 7 (controller serialization).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Sos\LocationResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => 'semester', 'is_current' => true,
        ]);
    }

    private function seedStudent(): int
    {
        return DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-LOC-1', 'firstname' => 'Loc', 'lastname' => 'Test',
        ]);
    }

    public function test_student_mid_class_resolves_to_classroom(): void
    {
        $term = $this->currentTerm();
        $classroom = Classroom::create(['school_year_id' => $term->school_year_id, 'name' => 'Room 204', 'code' => 'R204', 'building' => 'Main Building']);
        $section = Section::create(['levelid' => 7, 'sectionname' => 'Newton', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'classroom_id' => $classroom->id, 'is_active' => true]);
        $subject = Subject::create(['school_year_id' => $term->school_year_id, 'code' => 'SCI7', 'name' => 'Science 7', 'subject_type' => 'core', 'load_units' => 1]);
        $teacher = User::factory()->create(['name' => 'Ms. Curie']);

        $monday = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 15);

        ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $classroom->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'entry_type' => 'class', 'session_type' => 'regular', 'day_of_week' => 'Monday',
            'start_time' => '09:30:00', 'end_time' => '10:30:00', 'status' => 'active',
        ]);

        $studentId = $this->seedStudent();
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $term->school_year_id, 'section_id' => $section->id,
            'grade_level' => 7, 'enrollment_type' => 'regular', 'status' => 'enrolled', 'enrollment_date' => now(),
        ]);

        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), $monday);

        $this->assertSame('classroom', $result['type']);
        $this->assertSame('Room 204 — Science 7 with Ms. Curie', $result['label']);
        $this->assertSame('Main Building', $result['building']);
        $this->assertSame('Room 204', $result['room']);
        $this->assertSame('schedule', $result['source']);
    }

    public function test_student_in_a_gap_falls_back_to_homeroom(): void
    {
        $term = $this->currentTerm();
        $classroom = Classroom::create(['school_year_id' => $term->school_year_id, 'name' => 'Room 101', 'code' => 'R101']);
        $section = Section::create(['levelid' => 7, 'sectionname' => 'Newton', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'classroom_id' => $classroom->id, 'is_active' => true]);

        $studentId = $this->seedStudent();
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $term->school_year_id, 'section_id' => $section->id,
            'grade_level' => 7, 'enrollment_type' => 'regular', 'status' => 'enrolled', 'enrollment_date' => now(),
        ]);

        $sunday = Carbon::now()->next(Carbon::MONDAY)->subDay()->setTime(10, 0);

        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), $sunday);

        $this->assertSame('homeroom', $result['type']);
        $this->assertSame('Homeroom: Room 101', $result['label']);
        $this->assertSame('homeroom', $result['source']);
    }

    public function test_student_with_no_enrollment_is_unknown(): void
    {
        $this->currentTerm();
        $studentId = $this->seedStudent();

        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), Carbon::now());

        $this->assertSame('unknown', $result['type']);
        $this->assertSame('fallback', $result['source']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/LocationResolverServiceTest.php"`
Expected: FAIL — `Class "App\Services\Sos\LocationResolverService" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services\Sos;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LocationResolverService
{
    /** @return array{type:string,label:string,building:?string,room:?string,source:string} */
    public function resolve(Model $triggerable, Carbon $atTime): array
    {
        return $triggerable instanceof Student
            ? $this->resolveStudent($triggerable, $atTime)
            : $this->resolveEmployee($triggerable, $atTime);
    }

    private function resolveStudent(Student $student, Carbon $atTime): array
    {
        $term = $this->currentTerm();
        if (! $term) {
            return $this->unknown();
        }

        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('school_year_id', $term->school_year_id)
            ->active()
            ->first();

        if (! $enrollment) {
            return $this->unknown('Not enrolled this term');
        }

        $section = Section::find($enrollment->section_id);
        if (! $section) {
            return $this->unknown('Not enrolled this term');
        }

        $entry = $this->matchScheduleEntry($term, $atTime, sectionId: $section->id, facultyId: null);
        if ($entry) {
            return [
                'type' => 'classroom',
                'label' => "{$entry['classroom']} — {$entry['subject']} with {$entry['faculty']}",
                'building' => $entry['building'],
                'room' => $entry['classroom'],
                'source' => 'schedule',
            ];
        }

        if ($section->classroom) {
            return [
                'type' => 'homeroom',
                'label' => "Homeroom: {$section->classroom->name}",
                'building' => $section->classroom->building,
                'room' => $section->classroom->name,
                'source' => 'homeroom',
            ];
        }

        return $this->unknown();
    }

    private function resolveEmployee(User $user, Carbon $atTime): array
    {
        return $this->unknown(); // faculty/staff branch added in Task 4
    }

    protected function currentTerm(): ?AcademicTerm
    {
        return SchoolYear::current()->first()?->currentTerm();
    }

    /** @return array{classroom:?string,building:?string,subject:?string,faculty:?string}|null */
    protected function matchScheduleEntry(AcademicTerm $term, Carbon $atTime, ?int $sectionId, ?int $facultyId): ?array
    {
        $query = ClassSchedule::with(['classroom', 'subject', 'faculty'])
            ->classes()
            ->occupying()
            ->onDay($atTime->englishDayOfWeek)
            ->where('academic_term_id', $term->id)
            ->where('start_time', '<=', $atTime->format('H:i:s'))
            ->where('end_time', '>', $atTime->format('H:i:s'));

        $schedule = $sectionId !== null
            ? $query->where('section_id', $sectionId)->first()
            : $query->forFaculty($facultyId)->first();

        if (! $schedule) {
            return null;
        }

        return [
            'classroom' => $schedule->classroom?->name,
            'building' => $schedule->classroom?->building,
            'subject' => $schedule->subject?->name,
            'faculty' => $schedule->faculty?->name,
        ];
    }

    protected function unknown(string $reason = 'No scheduled location'): array
    {
        return ['type' => 'unknown', 'label' => $reason, 'building' => null, 'room' => null, 'source' => 'fallback'];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/LocationResolverServiceTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/LocationResolverService.php tests/Feature/Sos/LocationResolverServiceTest.php
git commit -m "feat(sos): resolve student location from enrollment/schedule/homeroom"
```

---

### Task 4: `LocationResolverService` — faculty/staff resolution

**Files:**
- Modify: `app/Services/Sos/LocationResolverService.php`
- Modify: `tests/Feature/Sos/LocationResolverServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (`office_id`, `office(): BelongsTo` → `Office` with `name`, `division_id`; `division(): BelongsTo` → `Division` with `division_name`), same `matchScheduleEntry()`/`currentTerm()` from Task 3.
- Produces: `resolveEmployee()` now returns `classroom`/`office`/`unknown` — no change to the public `resolve()` signature.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Sos/LocationResolverServiceTest.php`:

```php
    public function test_faculty_mid_class_resolves_to_classroom(): void
    {
        $term = $this->currentTerm();
        $classroom = Classroom::create(['school_year_id' => $term->school_year_id, 'name' => 'Room 305', 'code' => 'R305', 'building' => 'Science Wing']);
        $section = Section::create(['levelid' => 8, 'sectionname' => 'Darwin', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'is_active' => true]);
        $subject = Subject::create(['school_year_id' => $term->school_year_id, 'code' => 'MATH8', 'name' => 'Math 8', 'subject_type' => 'core', 'load_units' => 1]);
        $teacher = User::factory()->create(['name' => 'Mr. Newton']);

        $monday = Carbon::now()->next(Carbon::MONDAY)->setTime(13, 0);

        ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $classroom->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'entry_type' => 'class', 'session_type' => 'regular', 'day_of_week' => 'Monday',
            'start_time' => '12:30:00', 'end_time' => '13:30:00', 'status' => 'active',
        ]);

        $result = app(LocationResolverService::class)->resolve($teacher, $monday);

        $this->assertSame('classroom', $result['type']);
        $this->assertSame('Teaching Math 8 — Room 305', $result['label']);
        $this->assertSame('schedule', $result['source']);
    }

    public function test_faculty_with_no_current_class_falls_back_to_office(): void
    {
        $this->currentTerm();
        $division = \App\Models\Division::create(['division_name' => 'Curriculum & Instruction Division', 'status' => 'active']);
        $office = \App\Models\Office::create(['name' => 'CID Office', 'division_id' => $division->id]);
        $teacher = User::factory()->create(['office_id' => $office->id]);

        $result = app(LocationResolverService::class)->resolve($teacher, Carbon::now()->next(Carbon::SUNDAY));

        $this->assertSame('office', $result['type']);
        $this->assertSame('CID Office (Curriculum & Instruction Division)', $result['label']);
        $this->assertSame('office', $result['source']);
    }

    public function test_staff_with_no_teaching_load_resolves_straight_to_office(): void
    {
        $this->currentTerm();
        $office = \App\Models\Office::create(['name' => 'General Services Office']);
        $staff = User::factory()->create(['office_id' => $office->id]);

        $result = app(LocationResolverService::class)->resolve($staff, Carbon::now());

        $this->assertSame('office', $result['type']);
        $this->assertSame('General Services Office', $result['label']);
    }

    public function test_staff_with_no_office_is_unknown(): void
    {
        $this->currentTerm();
        $staff = User::factory()->create(['office_id' => null]);

        $result = app(LocationResolverService::class)->resolve($staff, Carbon::now());

        $this->assertSame('unknown', $result['type']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/LocationResolverServiceTest.php"`
Expected: the 4 new tests FAIL (office/classroom assertions against the current `unknown()` stub), the 3 existing tests still PASS.

- [ ] **Step 3: Implement `resolveEmployee()`**

In `app/Services/Sos/LocationResolverService.php`, replace:

```php
    private function resolveEmployee(User $user, Carbon $atTime): array
    {
        return $this->unknown(); // faculty/staff branch added in Task 4
    }
```

with:

```php
    private function resolveEmployee(User $user, Carbon $atTime): array
    {
        $term = $this->currentTerm();

        if ($term) {
            $entry = $this->matchScheduleEntry($term, $atTime, sectionId: null, facultyId: $user->id);
            if ($entry) {
                return [
                    'type' => 'classroom',
                    'label' => "Teaching {$entry['subject']} — {$entry['classroom']}",
                    'building' => $entry['building'],
                    'room' => $entry['classroom'],
                    'source' => 'schedule',
                ];
            }
        }

        if ($user->office_id && $user->office) {
            $label = $user->office->division
                ? "{$user->office->name} ({$user->office->division->division_name})"
                : $user->office->name;

            return [
                'type' => 'office',
                'label' => $label,
                'building' => null,
                'room' => $user->office->name,
                'source' => 'office',
            ];
        }

        return $this->unknown();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/LocationResolverServiceTest.php"`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/LocationResolverService.php tests/Feature/Sos/LocationResolverServiceTest.php
git commit -m "feat(sos): resolve faculty/staff location from schedule or office"
```

---

### Task 5: `LocationResolverService` — adjusted-day schedule + GPS badge

**Files:**
- Modify: `app/Services/Sos/LocationResolverService.php`
- Modify: `tests/Feature/Sos/LocationResolverServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\FacultyLoading\ClassScheduleDayAdjustment` (`scopePublished`, `scopeForDate`, `schedule_snapshot` array cast), `App\Services\FacultyLoading\AdjustedClassScheduleService::printableSnapshot(ClassScheduleDayAdjustment): array` (returns `['grades' => [['sections' => [['id' => int, 'entries' => [['start_time'=>'H:i','end_time'=>'H:i','subject'=>['name'=>...],'classroom'=>['name'=>...],'faculty'=>['id'=>int,'name'=>...]]]]]]]]`), `App\Services\HR\GeofenceService::resolve(?float,?float): array{status:string,zoneId:?int}`, `App\Models\HR\OnlinePunchGeofenceZone` (`label`).
- Produces: `matchScheduleEntry()` now checks adjusted-day snapshots first; new public `LocationResolverService::gpsBadge(?float $lat, ?float $lng): array{on_campus:?bool,zone_label:?string}` — used by Task 7 (controller serialization).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Sos/LocationResolverServiceTest.php`:

```php
    public function test_student_location_uses_adjusted_day_snapshot_when_published(): void
    {
        $term = $this->currentTerm();
        $section = Section::create(['levelid' => 7, 'sectionname' => 'Newton', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'is_active' => true]);

        $studentId = $this->seedStudent();
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $term->school_year_id, 'section_id' => $section->id,
            'grade_level' => 7, 'enrollment_type' => 'regular', 'status' => 'enrolled', 'enrollment_date' => now(),
        ]);

        $monday = Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0);

        \App\Models\FacultyLoading\ClassScheduleDayAdjustment::create([
            'academic_term_id' => $term->id,
            'effective_date' => $monday->toDateString(),
            'adjustment_type' => 'flag_ceremony',
            'shift_minutes' => 15,
            'status' => 'published',
            'schedule_snapshot' => [
                'grades' => [[
                    'grade_level' => 7,
                    'sections' => [[
                        'id' => $section->id,
                        'entries' => [[
                            'start_time' => '08:45',
                            'end_time' => '09:15',
                            'subject' => ['name' => 'Flag Ceremony Homeroom'],
                            'classroom' => ['name' => 'Covered Court'],
                            'faculty' => ['id' => 0, 'name' => 'Adviser'],
                        ]],
                        'bands' => [],
                    ]],
                ]],
            ],
        ]);

        // Without adjusted-day awareness this would miss (no raw class_schedules row exists at all).
        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), $monday);

        $this->assertSame('classroom', $result['type']);
        $this->assertSame('Covered Court — Flag Ceremony Homeroom with Adviser', $result['label']);
    }

    public function test_gps_badge_reports_on_campus_zone(): void
    {
        \App\Models\HR\OnlinePunchGeofenceZone::create([
            'label' => 'Main Gate', 'latitude' => 9.7833, 'longitude' => 125.4833,
            'radius_meters' => 200, 'is_active' => true,
        ]);

        $badge = app(LocationResolverService::class)->gpsBadge(9.7833, 125.4833);

        $this->assertTrue($badge['on_campus']);
        $this->assertSame('Main Gate', $badge['zone_label']);
    }

    public function test_gps_badge_is_null_when_no_coordinates(): void
    {
        $badge = app(LocationResolverService::class)->gpsBadge(null, null);

        $this->assertNull($badge['on_campus']);
        $this->assertNull($badge['zone_label']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/LocationResolverServiceTest.php"`
Expected: the 3 new tests FAIL (`gpsBadge` doesn't exist yet; the adjusted-day test falls through to `unknown` since no raw schedule row exists for that day).

- [ ] **Step 3: Implement adjusted-day matching + `gpsBadge()`**

In `app/Services/Sos/LocationResolverService.php`:

Add imports:

```php
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\HR\OnlinePunchGeofenceZone;
use App\Services\FacultyLoading\AdjustedClassScheduleService;
use App\Services\HR\GeofenceService;
```

Add a constructor:

```php
    public function __construct(
        private readonly AdjustedClassScheduleService $adjustedScheduleService,
        private readonly GeofenceService $geofenceService,
    ) {}
```

Add the public method (anywhere after `resolve()`):

```php
    /** @return array{on_campus:?bool,zone_label:?string} */
    public function gpsBadge(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['on_campus' => null, 'zone_label' => null];
        }

        $geofence = $this->geofenceService->resolve($lat, $lng);

        if (in_array($geofence['status'], ['unconfigured', 'no_permission'], true)) {
            return ['on_campus' => null, 'zone_label' => null];
        }

        $zone = $geofence['zoneId'] ? OnlinePunchGeofenceZone::find($geofence['zoneId']) : null;

        return [
            'on_campus' => $geofence['status'] === 'inside',
            'zone_label' => $zone?->label,
        ];
    }
```

Replace `matchScheduleEntry()` with a version that checks the adjustment first:

```php
    /** @return array{classroom:?string,building:?string,subject:?string,faculty:?string}|null */
    protected function matchScheduleEntry(AcademicTerm $term, Carbon $atTime, ?int $sectionId, ?int $facultyId): ?array
    {
        $adjustment = ClassScheduleDayAdjustment::published()->forDate($atTime->toDateString())
            ->where('academic_term_id', $term->id)
            ->first();

        if ($adjustment) {
            return $this->matchAdjustedEntry($adjustment, $atTime->format('H:i'), $sectionId, $facultyId);
        }

        $query = ClassSchedule::with(['classroom', 'subject', 'faculty'])
            ->classes()
            ->occupying()
            ->onDay($atTime->englishDayOfWeek)
            ->where('academic_term_id', $term->id)
            ->where('start_time', '<=', $atTime->format('H:i:s'))
            ->where('end_time', '>', $atTime->format('H:i:s'));

        $schedule = $sectionId !== null
            ? $query->where('section_id', $sectionId)->first()
            : $query->forFaculty($facultyId)->first();

        if (! $schedule) {
            return null;
        }

        return [
            'classroom' => $schedule->classroom?->name,
            'building' => $schedule->classroom?->building,
            'subject' => $schedule->subject?->name,
            'faculty' => $schedule->faculty?->name,
        ];
    }

    /** @return array{classroom:?string,building:?string,subject:?string,faculty:?string}|null */
    protected function matchAdjustedEntry(ClassScheduleDayAdjustment $adjustment, string $time, ?int $sectionId, ?int $facultyId): ?array
    {
        $snapshot = $this->adjustedScheduleService->printableSnapshot($adjustment);

        foreach ($snapshot['grades'] ?? [] as $grade) {
            foreach ($grade['sections'] ?? [] as $section) {
                if ($sectionId !== null && (int) $section['id'] !== $sectionId) {
                    continue;
                }

                foreach ($section['entries'] ?? [] as $entry) {
                    if ($facultyId !== null && ($entry['faculty']['id'] ?? null) !== $facultyId) {
                        continue;
                    }

                    if ($time >= $entry['start_time'] && $time < $entry['end_time']) {
                        return [
                            'classroom' => $entry['classroom']['name'] ?? null,
                            // The adjusted-day snapshot doesn't carry building info —
                            // an acceptable degradation on the rare adjusted day.
                            'building' => null,
                            'subject' => $entry['subject']['name'] ?? null,
                            'faculty' => $entry['faculty']['name'] ?? null,
                        ];
                    }
                }
            }
        }

        return null;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/LocationResolverServiceTest.php"`
Expected: PASS (10 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/LocationResolverService.php tests/Feature/Sos/LocationResolverServiceTest.php
git commit -m "feat(sos): honor adjusted-day schedules and add GPS zone badge"
```

---

### Task 6: Persist location snapshot at trigger time

**Files:**
- Modify: `app/Services/Sos/SosAlertService.php`
- Modify: `tests/Feature/Sos/SosAlertServiceTriggerTest.php`

**Interfaces:**
- Consumes: `LocationResolverService::resolve()` from Task 5.
- Produces: `sos_alerts.resolved_location_*` populated on every `SosAlertService::trigger()` call — relied on by Task 7's controller serialization.

- [ ] **Step 1: Read the existing trigger test file to match its conventions**

`tests/Feature/Sos/SosAlertServiceTriggerTest.php` already exists — open it to see its current fixture setup before adding a new test (constructor DI for `SosAlertService` is resolved via `app(SosAlertService::class)`, matching the container so the new `LocationResolverService` dependency is auto-wired without any test changes needed there).

- [ ] **Step 2: Write the failing test**

Add to `tests/Feature/Sos/SosAlertServiceTriggerTest.php`:

```php
    public function test_trigger_persists_a_resolved_location_snapshot(): void
    {
        \App\Models\Sos\SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $user = \App\Models\User::factory()->create(['office_id' => null]);

        $result = app(\App\Services\Sos\SosAlertService::class)->trigger(
            triggerable: $user, alertType: 'general', isSilent: false,
            lat: null, lng: null, accuracy: null, ip: null,
        );

        $this->assertSame('unknown', $result['alert']->resolved_location_type);
        $this->assertSame('fallback', $result['alert']->resolved_source);
        $this->assertNotNull($result['alert']->resolved_location_label);
    }
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceTriggerTest.php"`
Expected: FAIL — `resolved_location_type` is null (column not yet populated).

- [ ] **Step 4: Wire `LocationResolverService` into `trigger()`**

In `app/Services/Sos/SosAlertService.php`:

Add to imports: `use App\Services\Sos\LocationResolverService;`

Change the constructor:

```php
    public function __construct(
        private readonly CampusPresenceService $campusPresence,
        private readonly LocationResolverService $locationResolver,
    ) {}
```

In `trigger()`, insert before the `SosAlert::create([...])` call:

```php
        $location = $this->locationResolver->resolve($triggerable, now());
```

and add these keys to the `SosAlert::create([...])` array (right after `'geofence_zone_id' => $gate['geofence']['zoneId'] ?? null,`):

```php
            'resolved_location_type'  => $location['type'],
            'resolved_location_label' => $location['label'],
            'resolved_building'       => $location['building'],
            'resolved_room'           => $location['room'],
            'resolved_source'         => $location['source'],
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceTriggerTest.php"`
Expected: PASS

- [ ] **Step 6: Run the full SOS regression suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos tests/Feature/Mobile/StudentSosTriggerTest.php tests/Feature/Mobile/StudentSosStatusEndTest.php"`
Expected: PASS (all files) — proves the new constructor dependency didn't break any existing trigger path (staff, student portal, AtlasGo mobile).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Sos/SosAlertService.php tests/Feature/Sos/SosAlertServiceTriggerTest.php
git commit -m "feat(sos): persist a resolved-location snapshot at trigger time"
```

---

### Task 7: Serve location + GPS badge from the Command Center API

**Files:**
- Modify: `app/Http/Controllers/Sos/SosAlertController.php`
- Modify: `tests/Feature/Sos/SosAlertControllerTest.php`

**Interfaces:**
- Consumes: `LocationResolverService::resolve()`/`gpsBadge()` from Task 5, `SosAlert::responders()` from Task 2 (empty until Task 8 wires claim/unclaim).
- Produces: `serialize()` output now includes `resolved_location`, `current_location` (only for non-closed alerts), `gps_badge`, `responders` — consumed by Task 12/13 frontend components.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Sos/SosAlertControllerTest.php`:

```php
    public function test_index_includes_resolved_and_current_location(): void
    {
        \App\Models\Sos\SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $reporter = User::factory()->create(['office_id' => null]);
        $this->actingAs($reporter)->postJson('/sos/trigger', ['alert_type' => 'general']);

        $responder = $this->responder();
        $response = $this->actingAs($responder)->getJson('/sos/'.SosAlert::first()->id);

        $response->assertOk()
            ->assertJsonPath('resolved_location.type', 'unknown')
            ->assertJsonStructure(['current_location' => ['type', 'label', 'building', 'room', 'source']])
            ->assertJsonStructure(['gps_badge' => ['on_campus', 'zone_label']])
            ->assertJsonPath('responders', []);
    }

    public function test_closed_alert_has_no_live_current_location(): void
    {
        $responder = $this->responder();
        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1,
            'triggered_at' => now(), 'resolved_at' => now(),
        ]);

        $this->actingAs($responder)->getJson("/sos/{$alert->id}")
            ->assertOk()->assertJsonPath('current_location', null);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertControllerTest.php"`
Expected: FAIL — `resolved_location`/`current_location`/`gps_badge`/`responders` keys missing from the JSON response.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Sos/SosAlertController.php`:

Add to imports: `use App\Services\Sos\LocationResolverService;`

Add a constructor:

```php
    public function __construct(private readonly LocationResolverService $locationResolver) {}
```

Update `index()`'s eager-load to include `triggerable` and `responders.user`:

```php
    public function index()
    {
        $alerts = SosAlert::with(['events' => fn ($q) => $q->orderByDesc('created_at'), 'triggerable', 'responders.user'])
            ->orderByDesc('triggered_at')
            ->limit(100)
            ->get()
            ->map(fn (SosAlert $alert) => $this->serialize($alert));
        // ... rest unchanged
```

Update `show()` to eager-load the same relations:

```php
    public function show(SosAlert $alert)
    {
        return response()->json($this->serialize($alert->load(['events', 'triggerable', 'responders.user'])));
    }
```

Replace `serialize()`:

```php
    private function serialize(SosAlert $alert): array
    {
        $isActive = ! in_array($alert->status, ['resolved', 'false_alarm'], true);

        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'resolved_at'  => $alert->resolved_at?->toIso8601String(),
            'resolved_location' => [
                'type'     => $alert->resolved_location_type,
                'label'    => $alert->resolved_location_label,
                'building' => $alert->resolved_building,
                'room'     => $alert->resolved_room,
                'source'   => $alert->resolved_source,
            ],
            'current_location' => $isActive && $alert->relationLoaded('triggerable') && $alert->triggerable
                ? $this->locationResolver->resolve($alert->triggerable, now())
                : null,
            'gps_badge' => $this->locationResolver->gpsBadge($alert->lat, $alert->lng),
            'responders' => $alert->relationLoaded('responders')
                ? $alert->responders->whereNull('unclaimed_at')->values()->map(fn ($r) => [
                    'user_id'    => $r->user_id,
                    'name'       => $r->user->name,
                    'claimed_at' => $r->claimed_at->toIso8601String(),
                ])->values()
                : [],
            'events'       => $alert->relationLoaded('events')
                ? $alert->events->map(fn ($e) => [
                    'type'       => $e->type,
                    'payload'    => $e->payload,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values()
                : [],
        ];
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertControllerTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Sos/SosAlertController.php tests/Feature/Sos/SosAlertControllerTest.php
git commit -m "feat(sos): expose resolved/current location and GPS badge in Command Center API"
```

---

### Task 8: Responder self-claim

**Files:**
- Modify: `app/Services/Sos/SosAlertService.php`
- Modify: `app/Http/Controllers/Sos/SosAlertController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/SosAlertClaimTest.php`

**Interfaces:**
- Consumes: `SosAlertResponder` (Task 2), `SosAlertEvent`, `SosAlertUpdated` broadcast event.
- Produces: `SosAlertService::claim(SosAlert, User): SosAlertEvent`, `SosAlertService::unclaim(SosAlert, User): ?SosAlertEvent` (null = no-op, nothing was claimed), routes `sos.claim` / `sos.unclaim` (`POST /sos/{alert}/claim` / `/unclaim`), broadcast payload now includes a live `responders` array.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertClaimTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    private function alert(): SosAlert
    {
        return SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);
    }

    public function test_responder_can_claim_and_appears_in_responders_list(): void
    {
        $responder = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")
            ->assertOk()
            ->assertJsonPath('responders.0.user_id', $responder->id);

        $this->assertDatabaseHas('sos_alert_responders', ['sos_alert_id' => $alert->id, 'user_id' => $responder->id, 'unclaimed_at' => null]);
    }

    public function test_claim_is_idempotent(): void
    {
        $responder = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();
        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();

        $this->assertSame(1, \App\Models\Sos\SosAlertResponder::where('sos_alert_id', $alert->id)->whereNull('unclaimed_at')->count());
    }

    public function test_responder_can_unclaim(): void
    {
        $responder = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();
        $this->actingAs($responder)->postJson("/sos/{$alert->id}/unclaim")
            ->assertOk()->assertJsonPath('responders', []);
    }

    public function test_unclaim_by_a_non_claimant_is_a_no_op(): void
    {
        $responder = $this->responder();
        $other = $this->responder();
        $alert = $this->alert();

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/claim")->assertOk();
        $this->actingAs($other)->postJson("/sos/{$alert->id}/unclaim")
            ->assertOk()->assertJsonPath('responders.0.user_id', $responder->id);
    }

    public function test_claim_requires_sos_respond_permission(): void
    {
        $user = User::factory()->create();
        $alert = $this->alert();

        $this->actingAs($user)->postJson("/sos/{$alert->id}/claim")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertClaimTest.php"`
Expected: FAIL — routes don't exist (404).

- [ ] **Step 3: Add `claim()`/`unclaim()` to `SosAlertService`**

In `app/Services/Sos/SosAlertService.php`, add `use App\Models\Sos\SosAlertResponder;` to imports, and add these methods (after `endByReporter()`):

```php
    public function claim(SosAlert $alert, User $responder): SosAlertEvent
    {
        $alreadyClaimed = SosAlertResponder::where('sos_alert_id', $alert->id)
            ->where('user_id', $responder->id)
            ->whereNull('unclaimed_at')
            ->exists();

        if (! $alreadyClaimed) {
            SosAlertResponder::create([
                'sos_alert_id' => $alert->id,
                'user_id'      => $responder->id,
                'claimed_at'   => now(),
            ]);
        }

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id, 'type' => 'claimed',
            'actor_type' => User::class, 'actor_id' => $responder->id, 'payload' => null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function unclaim(SosAlert $alert, User $responder): ?SosAlertEvent
    {
        $updated = SosAlertResponder::where('sos_alert_id', $alert->id)
            ->where('user_id', $responder->id)
            ->whereNull('unclaimed_at')
            ->update(['unclaimed_at' => now()]);

        if ($updated === 0) {
            return null;
        }

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id, 'type' => 'unclaimed',
            'actor_type' => User::class, 'actor_id' => $responder->id, 'payload' => null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }
```

Update `broadcastPayload()` to include live responders:

```php
    private function broadcastPayload(SosAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'responders'   => $alert->responders()->whereNull('unclaimed_at')->with('user:id,name')->get()
                ->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $r->user->name, 'claimed_at' => $r->claimed_at->toIso8601String()])
                ->values()->all(),
        ];
    }
```

- [ ] **Step 4: Add controller actions**

In `app/Http/Controllers/Sos/SosAlertController.php`, add after `acknowledge()`:

```php
    public function claim(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->claim($alert, $request->user());
        return response()->json($this->serialize($alert->fresh(['events', 'triggerable', 'responders.user'])));
    }

    public function unclaim(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->unclaim($alert, $request->user());
        return response()->json($this->serialize($alert->fresh(['events', 'triggerable', 'responders.user'])));
    }
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, inside the `permission:sos.respond` group, add right after the `show` route:

```php
            Route::post('/{alert}/claim', [\App\Http\Controllers\Sos\SosAlertController::class, 'claim'])->name('claim')->whereNumber('alert');
            Route::post('/{alert}/unclaim', [\App\Http\Controllers\Sos\SosAlertController::class, 'unclaim'])->name('unclaim')->whereNumber('alert');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertClaimTest.php"`
Expected: PASS (5 tests)

- [ ] **Step 7: Run the full SOS regression suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos"`
Expected: PASS — the `broadcastPayload()` change is additive (new `responders` key) and shouldn't break `SosBroadcastEventsTest.php`'s existing assertions; if it does, that test only needs its payload assertion widened, not rewritten.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Sos/SosAlertService.php app/Http/Controllers/Sos/SosAlertController.php routes/web.php tests/Feature/Sos/SosAlertClaimTest.php
git commit -m "feat(sos): add responder self-claim/unclaim workflow"
```

---

### Task 9: Alert history endpoint

**Files:**
- Modify: `app/Http/Controllers/Sos/SosAlertController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/SosAlertHistoryTest.php`

**Interfaces:**
- Produces: `GET /sos/history` (route `sos.history`), paginated JSON of closed alerts (`resolved`/`false_alarm`), filterable by `from`, `to`, `alert_type`, `status`, `reporter`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SosAlertHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_history_only_returns_closed_alerts(): void
    {
        $reporter = User::factory()->create(['name' => 'Juan Dela Cruz']);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $reporter->id, 'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now()]);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $reporter->id, 'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);

        $response = $this->actingAs($this->responder())->getJson('/sos/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_history_filters_by_alert_type_and_reporter_name(): void
    {
        $reporter = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $other = User::factory()->create(['name' => 'Maria Santos']);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $reporter->id, 'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $other->id, 'alert_type' => 'security', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);

        $response = $this->actingAs($this->responder())->getJson('/sos/history?reporter=Juan');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('medical', $response->json('data.0.alert_type'));
    }

    public function test_history_filters_by_student_reporter_name(): void
    {
        $studentId = DB::table('students')->insertGetId(['pisaysystemID' => 'HIST-1', 'firstname' => 'Ana', 'lastname' => 'Reyes']);
        SosAlert::create(['triggerable_type' => \App\Models\Student::class, 'triggerable_id' => $studentId, 'alert_type' => 'general', 'status' => 'false_alarm', 'current_tier_order' => 1, 'triggered_at' => now()]);

        $response = $this->actingAs($this->responder())->getJson('/sos/history?reporter=Reyes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertHistoryTest.php"`
Expected: FAIL — 404, no `sos.history` route yet.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Sos/SosAlertController.php`, add to imports: `use App\Models\Student; use Illuminate\Support\Facades\DB;`, and add after `resolve()`:

```php
    public function history(Request $request)
    {
        $validated = $request->validate([
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'alert_type' => 'nullable|in:medical,security,fire_disaster,general',
            'status'     => 'nullable|in:resolved,false_alarm',
            'reporter'   => 'nullable|string|max:255',
        ]);

        $query = SosAlert::with(['events', 'triggerable'])
            ->whereIn('status', ['resolved', 'false_alarm'])
            ->orderByDesc('triggered_at');

        if (! empty($validated['from'])) {
            $query->where('triggered_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('triggered_at', '<=', $validated['to']);
        }
        if (! empty($validated['alert_type'])) {
            $query->where('alert_type', $validated['alert_type']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['reporter'])) {
            $term = '%'.$validated['reporter'].'%';
            $userIds = \App\Models\User::where('name', 'like', $term)->pluck('id');
            $studentIds = DB::table('students')->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", [$term])->pluck('id');

            $query->where(function ($q) use ($userIds, $studentIds) {
                $q->where(fn ($q2) => $q2->where('triggerable_type', \App\Models\User::class)->whereIn('triggerable_id', $userIds))
                  ->orWhere(fn ($q2) => $q2->where('triggerable_type', Student::class)->whereIn('triggerable_id', $studentIds));
            });
        }

        return response()->json($query->paginate(20)->through(fn (SosAlert $alert) => $this->serialize($alert)));
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `permission:sos.respond` group, add before the `/{alert}` show route (so the literal `/history` path is registered ahead of the numeric-constrained wildcard):

```php
            Route::get('/history', [\App\Http\Controllers\Sos\SosAlertController::class, 'history'])->name('history');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertHistoryTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Sos/SosAlertController.php routes/web.php tests/Feature/Sos/SosAlertHistoryTest.php
git commit -m "feat(sos): add filterable Command Center alert history endpoint"
```

---

### Task 10: Alert stats endpoint

**Files:**
- Modify: `app/Http/Controllers/Sos/SosAlertController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/SosAlertStatsTest.php`

**Interfaces:**
- Produces: `GET /sos/stats` (route `sos.stats`) returning `{ by_type: {alert_type: count}, by_month: {"YYYY-MM": count}, avg_first_claim_minutes: ?float, avg_resolution_minutes: ?float }`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosAlertEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertStatsTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_stats_computes_counts_and_averages(): void
    {
        $reporter = User::factory()->create();
        $triggeredAt = now()->subMinutes(30);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $reporter->id,
            'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1,
            'triggered_at' => $triggeredAt, 'resolved_at' => $triggeredAt->copy()->addMinutes(20),
        ]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id, 'type' => 'claimed',
            'actor_type' => User::class, 'actor_id' => $reporter->id,
            'created_at' => $triggeredAt->copy()->addMinutes(5),
        ]);

        $response = $this->actingAs($this->responder())->getJson('/sos/stats');

        $response->assertOk()
            ->assertJsonPath('by_type.medical', 1)
            ->assertJsonPath('avg_first_claim_minutes', 5.0)
            ->assertJsonPath('avg_resolution_minutes', 20.0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertStatsTest.php"`
Expected: FAIL — 404, no `sos.stats` route yet.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Sos/SosAlertController.php`, add after `history()`:

```php
    public function stats(Request $request)
    {
        $validated = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $base = SosAlert::query();
        if (! empty($validated['from'])) {
            $base->where('triggered_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $base->where('triggered_at', '<=', $validated['to']);
        }

        $byType = (clone $base)->select('alert_type', DB::raw('count(*) as total'))
            ->groupBy('alert_type')->pluck('total', 'alert_type');

        $byMonth = (clone $base)->select(DB::raw("DATE_FORMAT(triggered_at, '%Y-%m') as month"), DB::raw('count(*) as total'))
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $closedAlerts = (clone $base)->whereIn('status', ['resolved', 'false_alarm'])
            ->whereNotNull('resolved_at')->get(['id', 'triggered_at', 'resolved_at']);

        $avgResolutionMinutes = $closedAlerts->isEmpty() ? null
            : round($closedAlerts->avg(fn (SosAlert $a) => $a->triggered_at->diffInMinutes($a->resolved_at)), 1);

        $alertIds = (clone $base)->pluck('id');
        $triggeredAtByAlert = (clone $base)->pluck('triggered_at', 'id');

        $firstClaimByAlert = \App\Models\Sos\SosAlertEvent::where('type', 'claimed')
            ->whereIn('sos_alert_id', $alertIds)
            ->orderBy('created_at')
            ->get()
            ->unique('sos_alert_id');

        $claimMinutes = $firstClaimByAlert
            ->map(fn ($event) => isset($triggeredAtByAlert[$event->sos_alert_id])
                ? $triggeredAtByAlert[$event->sos_alert_id]->diffInMinutes($event->created_at)
                : null)
            ->filter(fn ($minutes) => $minutes !== null);

        $avgFirstClaimMinutes = $claimMinutes->isEmpty() ? null : round($claimMinutes->avg(), 1);

        return response()->json([
            'by_type'                  => $byType,
            'by_month'                 => $byMonth,
            'avg_first_claim_minutes'  => $avgFirstClaimMinutes,
            'avg_resolution_minutes'   => $avgResolutionMinutes,
        ]);
    }
```

Add `use Illuminate\Support\Facades\DB;` to imports if Task 9 didn't already add it.

- [ ] **Step 4: Add the route**

In `routes/web.php`, right after the `/history` route added in Task 9:

```php
            Route::get('/stats', [\App\Http\Controllers\Sos\SosAlertController::class, 'stats'])->name('stats');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertStatsTest.php"`
Expected: PASS

- [ ] **Step 6: Run the full SOS regression suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos tests/Feature/Mobile/StudentSosTriggerTest.php tests/Feature/Mobile/StudentSosStatusEndTest.php"`
Expected: PASS — full backend surface for this plan is now green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Sos/SosAlertController.php routes/web.php tests/Feature/Sos/SosAlertStatsTest.php
git commit -m "feat(sos): add Command Center response-time/volume stats endpoint"
```

---

### Task 11: Leaflet map component

**Files:**
- Modify: `package.json`
- Create: `resources/js/Components/Sos/AlertMap.vue`

**Interfaces:**
- Produces: `<AlertMap :lat="Number|null" :lng="Number|null" :label="String|null" />` — a Leaflet map pinned at `lat`/`lng` with `label` as the marker popup text, or an empty state when either coordinate is null. Consumed by Task 12.

- [ ] **Step 1: Install Leaflet**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm install leaflet"`
Expected: `leaflet` added to `package.json` `dependencies`.

- [ ] **Step 2: Write the component**

```vue
<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({ lat: { type: Number, default: null }, lng: { type: Number, default: null }, label: { type: String, default: null } })

const mapEl = ref(null)
let map = null
let marker = null

function render() {
  if (!mapEl.value || props.lat === null || props.lng === null) return

  if (!map) {
    map = L.map(mapEl.value).setView([props.lat, props.lng], 17)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)
  } else {
    map.setView([props.lat, props.lng], 17)
  }

  if (marker) marker.remove()
  marker = L.marker([props.lat, props.lng]).addTo(map)
  if (props.label) marker.bindPopup(props.label).openPopup()
}

onMounted(render)
watch(() => [props.lat, props.lng, props.label], render)
onUnmounted(() => { if (map) map.remove() })
</script>

<template>
  <div v-if="lat !== null && lng !== null" ref="mapEl" class="h-48 w-full rounded-lg border border-slate-200"></div>
  <div v-else class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-200 text-xs text-slate-400">
    No GPS data reported
  </div>
</template>
```

- [ ] **Step 3: Build the frontend to check for errors**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds, no missing-module or syntax errors.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/Components/Sos/AlertMap.vue
git commit -m "feat(sos): add Leaflet-based alert location map component"
```

---

### Task 12: Location panel in the alert detail pane

**Files:**
- Create: `resources/js/Components/Sos/AlertLocationPanel.vue`
- Modify: `resources/js/Pages/Sos/CommandCenter.vue`

**Interfaces:**
- Consumes: `AlertMap.vue` from Task 11, and the `resolved_location`/`current_location`/`gps_badge`/`lat`/`lng` fields from Task 7's API response.
- Produces: `<AlertLocationPanel :alert="selected" />`, mounted in `CommandCenter.vue`'s selected-alert detail pane.

- [ ] **Step 1: Write the component**

```vue
<script setup>
import AlertMap from '@/Components/Sos/AlertMap.vue'

const props = defineProps({ alert: { type: Object, required: true } })

const typeIcon = { classroom: '🏫', homeroom: '🏠', office: '🏢', unknown: '❓' }
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</h4>

    <div class="mt-2 flex items-start gap-2">
      <span class="text-lg">{{ typeIcon[alert.resolved_location.type] ?? '❓' }}</span>
      <div>
        <p class="text-sm font-medium text-slate-900">Reported at trigger: {{ alert.resolved_location.label }}</p>
        <p v-if="alert.current_location && alert.current_location.label !== alert.resolved_location.label"
           class="mt-1 text-sm text-slate-600">
          Currently scheduled: {{ alert.current_location.label }}
        </p>
      </div>
    </div>

    <div class="mt-3 flex items-center gap-2 text-xs">
      <span v-if="alert.gps_badge.on_campus === true" class="rounded-full bg-emerald-100 px-2 py-1 font-medium text-emerald-700">
        On campus{{ alert.gps_badge.zone_label ? ` · near ${alert.gps_badge.zone_label}` : '' }}
      </span>
      <span v-else-if="alert.gps_badge.on_campus === false" class="rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700">Off campus</span>
      <span v-else class="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-500">No GPS signal</span>
    </div>

    <div class="mt-3">
      <AlertMap :lat="alert.lat" :lng="alert.lng" :label="alert.resolved_location.label" />
    </div>
  </div>
</template>
```

- [ ] **Step 2: Mount it in `CommandCenter.vue`**

In `resources/js/Pages/Sos/CommandCenter.vue`, add the import:

```js
import AlertLocationPanel from '@/Components/Sos/AlertLocationPanel.vue'
```

In the `<template>`, inside the `v-if="selected"` detail pane, right after the `<p class="mt-1 text-xs text-slate-500">{{ selected.alert_type... }}</p>` line, add:

```html
        <div class="mt-4">
          <AlertLocationPanel :alert="selected" />
        </div>
```

- [ ] **Step 3: Manually verify in the dev browser**

Run the dev server per project convention (`cd /Users/junlou/bugsaymis-docker && docker compose up -d` if not already running, `npm run dev` for HMR), log in as a `DRRM Coordinator`/Administrator, trigger a test SOS alert from another session/account, open `/sos`, select the alert, and confirm the location panel renders a label, a GPS badge, and either a map pin or the "No GPS data reported" empty state.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Sos/AlertLocationPanel.vue resources/js/Pages/Sos/CommandCenter.vue
git commit -m "feat(sos): show resolved location, GPS badge, and map in Command Center"
```

---

### Task 13: Responder self-claim UI

**Files:**
- Create: `resources/js/Components/Sos/ResponderList.vue`
- Modify: `resources/js/Pages/Sos/CommandCenter.vue`
- Modify: `app/Http/Controllers/Sos/SosAlertController.php` (thread `authUserId` into the Inertia page props)

**Interfaces:**
- Consumes: `alert.responders` (array of `{user_id, name, claimed_at}`) from Task 7/8's API response; `route('sos.claim', alert.id)` / `route('sos.unclaim', alert.id)` from Task 8.
- Produces: `<ResponderList :alert="selected" :current-user-id="Number" @claimed="..." @unclaimed="..." />`.

- [ ] **Step 1: Write the component**

```vue
<script setup>
import axios from 'axios'

const props = defineProps({ alert: { type: Object, required: true }, currentUserId: { type: Number, required: true } })
const emit = defineEmits(['updated'])

const isClaimedByMe = () => props.alert.responders.some(r => r.user_id === props.currentUserId)

async function toggleClaim() {
  const action = isClaimedByMe() ? 'unclaim' : 'claim'
  const { data } = await axios.post(route(`sos.${action}`, props.alert.id))
  emit('updated', data)
}
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responding</h4>

    <div v-if="alert.responders.length === 0" class="mt-2 text-sm text-slate-400">No one has claimed this alert yet.</div>
    <ul v-else class="mt-2 space-y-1">
      <li v-for="r in alert.responders" :key="r.user_id" class="text-sm text-slate-700">{{ r.name }}</li>
    </ul>

    <button
      class="mt-3 w-full rounded-lg px-3 py-2 text-sm font-medium text-white"
      :class="isClaimedByMe() ? 'bg-slate-600 hover:bg-slate-700' : 'bg-indigo-600 hover:bg-indigo-700'"
      @click="toggleClaim"
    >
      {{ isClaimedByMe() ? "Stop responding" : "I'm responding" }}
    </button>
  </div>
</template>
```

- [ ] **Step 2: Mount it in `CommandCenter.vue`**

Add the import:

```js
import ResponderList from '@/Components/Sos/ResponderList.vue'
```

`CommandCenter.vue` needs the current user's id — add a prop and pass it from the controller. First, in `app/Http/Controllers/Sos/SosAlertController.php`'s `index()`, add `'authUserId' => auth()->id(),` to the `Inertia::render('Sos/CommandCenter', [...])` array. Then in `CommandCenter.vue`:

```js
const props = defineProps({ alerts: Array, emergencyAlerts: Array, authUserId: Number })
```

In the `<template>`, right after the `AlertLocationPanel` mount added in Task 12:

```html
        <div class="mt-4">
          <ResponderList :alert="selected" :current-user-id="authUserId" @updated="(data) => { upsertAlert(data); selected = data }" />
        </div>
```

- [ ] **Step 3: Manually verify in the dev browser**

With two responder accounts logged in (two browser sessions), open `/sos` in both, select the same active alert in both, click "I'm responding" in one, and confirm the responders list updates live in the other session via the existing `sos-responders` Echo channel (no page refresh — `upsertAlert()` already merges any payload field, including the new `responders` array from `SosAlertUpdated`'s broadcast).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Sos/ResponderList.vue resources/js/Pages/Sos/CommandCenter.vue app/Http/Controllers/Sos/SosAlertController.php
git commit -m "feat(sos): add responder self-claim UI to Command Center"
```

---

### Task 14: History tab

**Files:**
- Create: `resources/js/Components/Sos/CommandCenterHistoryTab.vue`
- Modify: `resources/js/Pages/Sos/CommandCenter.vue`

**Interfaces:**
- Consumes: `GET /sos/history` (Task 9), `AlertLocationPanel.vue` (Task 12, reused read-only for a selected history row).
- Produces: a tab in `CommandCenter.vue` showing the filterable history list.

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AlertLocationPanel from '@/Components/Sos/AlertLocationPanel.vue'

const alerts = ref([])
const meta = ref(null)
const selected = ref(null)
const filters = ref({ from: '', to: '', alert_type: '', status: '', reporter: '' })

async function load(page = 1) {
  const { data } = await axios.get(route('sos.history'), { params: { ...filters.value, page } })
  alerts.value = data.data
  meta.value = { current_page: data.current_page, last_page: data.last_page }
  selected.value = null
}

onMounted(() => load())
</script>

<template>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
      <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
        <input v-model="filters.from" type="date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
        <input v-model="filters.to" type="date" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
        <select v-model="filters.alert_type" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
          <option value="">All types</option>
          <option value="medical">Medical</option>
          <option value="security">Security</option>
          <option value="fire_disaster">Fire/Disaster</option>
          <option value="general">General</option>
        </select>
        <select v-model="filters.status" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
          <option value="">All statuses</option>
          <option value="resolved">Resolved</option>
          <option value="false_alarm">False alarm</option>
        </select>
        <input v-model="filters.reporter" placeholder="Reporter name" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
      </div>
      <button class="mb-4 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white" @click="load(1)">Apply filters</button>

      <div v-if="alerts.length === 0" class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">No matching alerts.</div>
      <div v-for="alert in alerts" :key="alert.id" class="mb-2 cursor-pointer rounded-lg border border-slate-100 p-3 text-sm hover:border-indigo-300" @click="selected = alert">
        #{{ alert.id }} — {{ alert.alert_type.replace('_', ' ') }} — {{ alert.resolved_location.label }}
        — <span class="text-xs text-slate-500">{{ new Date(alert.triggered_at).toLocaleString('en-PH') }}</span>
      </div>

      <div v-if="meta && meta.last_page > 1" class="mt-3 flex gap-2">
        <button v-for="page in meta.last_page" :key="page" class="rounded px-2 py-1 text-xs"
                :class="page === meta.current_page ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'"
                @click="load(page)">{{ page }}</button>
      </div>
    </div>

    <div v-if="selected" class="rounded-xl border border-slate-200 bg-white p-5">
      <h3 class="text-sm font-semibold text-slate-900">Alert #{{ selected.id }}</h3>
      <p class="mt-1 text-xs text-slate-500">{{ selected.status }} · {{ selected.resolution_notes }}</p>
      <div class="mt-4">
        <AlertLocationPanel :alert="selected" />
      </div>
      <div class="mt-5">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timeline</h4>
        <ul class="mt-2 space-y-1 text-xs text-slate-600">
          <li v-for="(e, i) in selected.events" :key="i">{{ e.type }} — {{ new Date(e.created_at).toLocaleString('en-PH') }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Add tab navigation to `CommandCenter.vue`**

Add the import and a tab ref:

```js
import CommandCenterHistoryTab from '@/Components/Sos/CommandCenterHistoryTab.vue'

const activeTab = ref('active')
```

Wrap the existing `<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">...</div>` block (the current whole page body) with tab nav, right after `<AdminLayout title="SOS Command Center">`:

```html
    <div class="mb-4 flex gap-2 border-b border-slate-200">
      <button class="px-3 py-2 text-sm font-medium" :class="activeTab === 'active' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'" @click="activeTab = 'active'">Active</button>
      <button class="px-3 py-2 text-sm font-medium" :class="activeTab === 'history' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'" @click="activeTab = 'history'">History</button>
    </div>

    <div v-show="activeTab === 'active'">
      <!-- existing grid content unchanged -->
    </div>
    <CommandCenterHistoryTab v-if="activeTab === 'history'" />
```

- [ ] **Step 3: Manually verify in the dev browser**

Resolve a test alert, switch to the History tab, confirm it appears, apply each filter individually (date range, type, status, reporter name) and confirm the list narrows correctly, and click a row to confirm the detail pane (location panel + timeline) renders.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Sos/CommandCenterHistoryTab.vue resources/js/Pages/Sos/CommandCenter.vue
git commit -m "feat(sos): add filterable alert history tab to Command Center"
```

---

### Task 15: Stats tab

**Files:**
- Create: `resources/js/Components/Sos/CommandCenterStatsTab.vue`
- Modify: `resources/js/Pages/Sos/CommandCenter.vue`

**Interfaces:**
- Consumes: `GET /sos/stats` (Task 10), `chart.js`/`vue-chartjs` (already in `package.json`).
- Produces: a third "Stats" tab in `CommandCenter.vue`.

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale)

const stats = ref(null)

onMounted(async () => {
  const { data } = await axios.get(route('sos.stats'))
  stats.value = data
})

const monthlyChartData = computed(() => {
  if (!stats.value) return { labels: [], datasets: [] }
  return {
    labels: Object.keys(stats.value.by_month),
    datasets: [{ label: 'Alerts', backgroundColor: '#4f46e5', data: Object.values(stats.value.by_month) }],
  }
})
</script>

<template>
  <div v-if="!stats" class="text-sm text-slate-400">Loading stats…</div>
  <div v-else class="space-y-6">
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div v-for="(count, type) in stats.by_type" :key="type" class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ type.replace('_', ' ') }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ count }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">Avg. time to first claim</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.avg_first_claim_minutes ?? '—' }}<span v-if="stats.avg_first_claim_minutes" class="text-sm font-normal"> min</span></p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">Avg. time to resolution</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.avg_resolution_minutes ?? '—' }}<span v-if="stats.avg_resolution_minutes" class="text-sm font-normal"> min</span></p>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
      <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Alerts by month</h4>
      <Bar :data="monthlyChartData" :options="{ responsive: true, plugins: { legend: { display: false } } }" />
    </div>
  </div>
</template>
```

- [ ] **Step 2: Add the third tab to `CommandCenter.vue`**

Add the import:

```js
import CommandCenterStatsTab from '@/Components/Sos/CommandCenterStatsTab.vue'
```

Add the tab button next to "History" (from Task 14):

```html
      <button class="px-3 py-2 text-sm font-medium" :class="activeTab === 'stats' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500'" @click="activeTab = 'stats'">Stats</button>
```

Add the tab panel next to `CommandCenterHistoryTab`:

```html
    <CommandCenterStatsTab v-if="activeTab === 'stats'" />
```

- [ ] **Step 3: Build and manually verify in the dev browser**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds. Then in the browser, switch to the Stats tab and confirm the tiles and bar chart render with real numbers matching the alerts created during Task 14's manual testing.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Sos/CommandCenterStatsTab.vue resources/js/Pages/Sos/CommandCenter.vue
git commit -m "feat(sos): add response-time and volume stats tab to Command Center"
```

---

### Task 16: Enrich the responder broadcast payload with reporter name + resolved location

**Files:**
- Modify: `app/Services/Sos/SosAlertService.php`
- Test: `tests/Feature/Sos/SosBroadcastEventsTest.php` (existing file — verify it exists first; if the exact filename differs, add to whichever existing test file already asserts `SosAlertTriggered`'s payload)

**Interfaces:**
- Consumes: `resolved_location_type`/`resolved_location_label` on `SosAlert` (Task 6).
- Produces: `SosAlertService::broadcastPayload()` now includes `reporter_name`, `resolved_location_type`, `resolved_location_label` — relied on by Task 18's `SosResponderAlertModal.vue`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_trigger_broadcast_includes_reporter_name_and_location(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\Sos\SosAlertTriggered::class]);
        \App\Models\Sos\SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $user = \App\Models\User::factory()->create(['name' => 'Juan Dela Cruz', 'office_id' => null]);

        app(\App\Services\Sos\SosAlertService::class)->trigger(
            triggerable: $user, alertType: 'general', isSilent: false,
            lat: null, lng: null, accuracy: null, ip: null,
        );

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\Sos\SosAlertTriggered::class,
            fn (\App\Events\Sos\SosAlertTriggered $event) =>
                $event->payload['reporter_name'] === 'Juan Dela Cruz'
                && $event->payload['resolved_location_type'] === 'unknown'
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosBroadcastEventsTest.php"`
Expected: FAIL — `reporter_name` key missing from the payload.

- [ ] **Step 3: Add a `reporterName()` helper and update `broadcastPayload()`**

In `app/Services/Sos/SosAlertService.php`, add this private method (near `broadcastPayload()`):

```php
    private function reporterName(Model $triggerable): string
    {
        if ($triggerable instanceof User) {
            return $triggerable->name;
        }

        return trim($triggerable->firstname.' '.$triggerable->lastname);
    }
```

Update `broadcastPayload()` (as last modified by Task 8) to add three keys:

```php
    private function broadcastPayload(SosAlert $alert): array
    {
        return [
            'id'                      => $alert->id,
            'alert_type'              => $alert->alert_type,
            'is_silent'               => $alert->is_silent,
            'status'                  => $alert->status,
            'lat'                     => $alert->lat,
            'lng'                     => $alert->lng,
            'triggered_at'            => $alert->triggered_at->toIso8601String(),
            'reporter_name'           => $this->reporterName($alert->triggerable),
            'resolved_location_type'  => $alert->resolved_location_type,
            'resolved_location_label' => $alert->resolved_location_label,
            'responders'              => $alert->responders()->whereNull('unclaimed_at')->with('user:id,name')->get()
                ->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $r->user->name, 'claimed_at' => $r->claimed_at->toIso8601String()])
                ->values()->all(),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosBroadcastEventsTest.php"`
Expected: PASS

- [ ] **Step 5: Run the full SOS regression suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos tests/Feature/Mobile/StudentSosTriggerTest.php tests/Feature/Mobile/StudentSosStatusEndTest.php"`
Expected: PASS — the payload change is additive.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Sos/SosAlertService.php tests/Feature/Sos/SosBroadcastEventsTest.php
git commit -m "feat(sos): include reporter name and resolved location in responder broadcasts"
```

---

### Task 17: Web self-service status + mark-safe endpoints

**Files:**
- Create: `app/Http/Controllers/Sos/SosSelfServiceController.php`
- Modify: `routes/web.php`
- Modify: `app/Services/Sos/SosAlertService.php` (generalize `endByReporter()`'s hardcoded resolution note)
- Test: `tests/Feature/Sos/SosSelfServiceControllerTest.php`

**Interfaces:**
- Consumes: `SosAlertService::endByReporter(SosAlert $alert, Model $reporter): SosAlertEvent` (existing, already generic over `Model`).
- Produces: `GET /sos/{alert}/mine` (route `sos.mine.status`), `POST /sos/{alert}/mine/end` (route `sos.mine.end`) — both open to any authenticated reporter, ownership-checked in the controller (no `sos.respond` gate). Consumed by Task 19's `SosMyStatusModal.vue`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosSelfServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function openAlertFor(User $reporter): SosAlert
    {
        return SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $reporter->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);
    }

    public function test_reporter_can_poll_their_own_alert_status(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $reporter = User::factory()->create(['office_id' => null]);
        $this->actingAs($reporter)->postJson('/sos/trigger', ['alert_type' => 'general']);

        $alert = SosAlert::first();
        $this->actingAs($reporter)->getJson("/sos/{$alert->id}/mine")
            ->assertOk()
            ->assertJsonPath('status', 'triggered')
            ->assertJsonStructure(['resolved_location_type', 'resolved_location_label', 'events']);
    }

    public function test_a_different_user_cannot_poll_someone_elses_alert(): void
    {
        $reporter = User::factory()->create();
        $other = User::factory()->create();
        $alert = $this->openAlertFor($reporter);

        $this->actingAs($other)->getJson("/sos/{$alert->id}/mine")->assertForbidden();
    }

    public function test_reporter_can_mark_themselves_safe(): void
    {
        $reporter = User::factory()->create();
        $alert = $this->openAlertFor($reporter);

        $this->actingAs($reporter)->postJson("/sos/{$alert->id}/mine/end")
            ->assertOk()->assertJsonPath('status', 'resolved');
    }

    public function test_a_different_user_cannot_end_someone_elses_alert(): void
    {
        $reporter = User::factory()->create();
        $other = User::factory()->create();
        $alert = $this->openAlertFor($reporter);

        $this->actingAs($other)->postJson("/sos/{$alert->id}/mine/end")->assertForbidden();
    }

    public function test_ending_an_already_closed_alert_returns_conflict(): void
    {
        $reporter = User::factory()->create();
        $alert = $this->openAlertFor($reporter);
        $this->actingAs($reporter)->postJson("/sos/{$alert->id}/mine/end")->assertOk();

        $this->actingAs($reporter)->postJson("/sos/{$alert->id}/mine/end")->assertStatus(409);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSelfServiceControllerTest.php"`
Expected: FAIL — routes don't exist (404).

- [ ] **Step 3: Generalize `endByReporter()`'s resolution note**

In `app/Services/Sos/SosAlertService.php`, `endByReporter()` currently hardcodes `'resolution_notes' => 'Ended by reporting student.'` — written back when only `Student` could self-end. Now that `User` reporters use it too (via this task), that message would misleadingly show "Ended by reporting student" in the Command Center History tab (Task 14) for a staff member's own alert. Change it to:

```php
            'resolution_notes' => 'Ended by reporter.',
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web/Atlas mirror of StudentAttendance\Api\StudentSosController::show()/end() —
 * lets any reporter (User) who triggered their own SosAlert poll its status and
 * stand it down themselves, matching what AtlasGo already offers students.
 */
class SosSelfServiceController extends Controller
{
    public function status(Request $request, SosAlert $alert): JsonResponse
    {
        $this->authorizeOwnership($request, $alert);

        return response()->json($this->serialize($alert->load('events')));
    }

    public function end(Request $request, SosAlert $alert, SosAlertService $service): JsonResponse
    {
        $this->authorizeOwnership($request, $alert);

        try {
            $service->endByReporter($alert, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->serialize($alert->fresh()->load('events')));
    }

    private function authorizeOwnership(Request $request, SosAlert $alert): void
    {
        $user = $request->user();
        if ($alert->triggerable_type !== get_class($user) || $alert->triggerable_id !== $user->getKey()) {
            abort(403);
        }
    }

    private function serialize(SosAlert $alert): array
    {
        return [
            'id'                       => $alert->id,
            'alert_type'               => $alert->alert_type,
            'status'                   => $alert->status,
            'triggered_at'             => $alert->triggered_at->toIso8601String(),
            'resolved_at'              => $alert->resolved_at?->toIso8601String(),
            'resolved_location_type'   => $alert->resolved_location_type,
            'resolved_location_label'  => $alert->resolved_location_label,
            'events'                   => $alert->events->map(fn ($e) => [
                'type'       => $e->type,
                'created_at' => $e->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, inside the open `sos.*` group (no permission gate), right after the `/emergency-status` route:

```php
        Route::get('/{alert}/mine', [\App\Http\Controllers\Sos\SosSelfServiceController::class, 'status'])
            ->name('mine.status')->whereNumber('alert')->middleware('throttle:30,1,sos-status');
        Route::post('/{alert}/mine/end', [\App\Http\Controllers\Sos\SosSelfServiceController::class, 'end'])
            ->name('mine.end')->whereNumber('alert')->middleware('throttle:10,1,sos-end');
```

Distinct throttle names (`sos-status` vs `sos-end`) are deliberate — AtlasGo hit a bug in 2026-08-22 where a shared throttle key let status polling exhaust the end route's quota.

- [ ] **Step 6: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSelfServiceControllerTest.php"`
Expected: PASS (5 tests)

- [ ] **Step 7: Run the full SOS regression suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos tests/Feature/Mobile/StudentSosTriggerTest.php tests/Feature/Mobile/StudentSosStatusEndTest.php"`
Expected: PASS — confirms the `endByReporter()` message change doesn't break any AtlasGo mobile assertion that happens to check the old string (if one does, update its expected string to `'Ended by reporter.'`, not revert the fix).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Sos/SosSelfServiceController.php app/Services/Sos/SosAlertService.php routes/web.php tests/Feature/Sos/SosSelfServiceControllerTest.php
git commit -m "feat(sos): add web self-service SOS status polling and mark-safe endpoint"
```

---

### Task 18: Site-wide responder notification (modal + border + active-status endpoint)

**Files:**
- Modify: `app/Http/Controllers/Sos/SosAlertController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/SosAlertControllerTest.php`
- Create: `resources/js/Components/Sos/SosResponderAlertModal.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: `reporter_name`/`resolved_location_label` (Task 16) in the `.sos.alert.triggered` broadcast payload; existing `.sos.alert.updated` broadcast; `EmergencyBorderOverlay.vue` (existing, unmodified, reused as a second instance).
- Produces: `GET /sos/active-status` (route `sos.active-status`, permission `sos.respond`) → `{active: bool, count: int}`; `<SosResponderAlertModal ref>` exposing `receiveNewAlert(payload)`.

- [ ] **Step 1: Write the failing backend tests**

Add to `tests/Feature/Sos/SosAlertControllerTest.php`:

```php
    public function test_active_status_reports_open_alert_count(): void
    {
        $responder = $this->responder();
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id, 'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now()]);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id, 'alert_type' => 'general', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);

        $this->actingAs($responder)->getJson('/sos/active-status')
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('count', 1);
    }

    public function test_active_status_requires_sos_respond_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/sos/active-status')->assertForbidden();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertControllerTest.php"`
Expected: FAIL — 404, route doesn't exist.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Sos/SosAlertController.php`, add after `index()`:

```php
    public function activeStatus(): \Illuminate\Http\JsonResponse
    {
        $count = SosAlert::whereNotIn('status', ['resolved', 'false_alarm'])->count();

        return response()->json(['active' => $count > 0, 'count' => $count]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `permission:sos.respond` group, add before the `/{alert}` route:

```php
            Route::get('/active-status', [\App\Http\Controllers\Sos\SosAlertController::class, 'activeStatus'])->name('active-status');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertControllerTest.php"`
Expected: PASS

- [ ] **Step 6: Write `SosResponderAlertModal.vue`**

```vue
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const visible = ref(false)
const alertData = ref(null)

function receiveNewAlert(payload) {
  // Silent/duress alerts stay invisible everywhere, including this modal —
  // that's the entire point of silent mode.
  if (payload.is_silent) return
  alertData.value = payload
  visible.value = true
}

function goToCommandCenter() {
  visible.value = false
  router.visit(route('sos.index'))
}

function dismiss() {
  visible.value = false
}

defineExpose({ receiveNewAlert })
</script>

<template>
  <div v-if="visible" class="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 px-4 pt-20">
    <div class="w-full max-w-md rounded-2xl border-2 border-red-600 bg-white p-6 shadow-2xl">
      <div class="mb-3 flex items-center gap-2 text-red-700">
        <ExclamationTriangleIcon class="h-6 w-6 animate-pulse" />
        <h2 class="text-lg font-semibold">New SOS Alert</h2>
      </div>
      <p class="text-sm text-slate-700">
        <span class="font-medium">{{ alertData.reporter_name }}</span> triggered a
        <span class="font-medium">{{ alertData.alert_type.replace('_', ' ') }}</span> alert.
      </p>
      <p v-if="alertData.resolved_location_label" class="mt-1 text-sm text-slate-600">
        Last known location: {{ alertData.resolved_location_label }}
      </p>
      <div class="mt-5 flex gap-2">
        <button type="button" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" @click="goToCommandCenter">
          View in Command Center
        </button>
        <button type="button" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200" @click="dismiss">
          Dismiss
        </button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 7: Wire it into `AdminLayout.vue`**

Add the import next to the existing `EmergencyBorderOverlay` import:

```js
import SosResponderAlertModal from '@/Components/Sos/SosResponderAlertModal.vue'
```

Add refs near the existing `hasActiveEmergency` ref:

```js
const sosResponderModal = ref(null)
const hasActiveSosAlert = ref(false)
```

Add these functions near `fetchEmergencyStatus()`/`setupEmergencyAlertListener()`:

```js
async function fetchSosActiveStatus() {
  if (!hasPerm('sos.respond')) return
  try {
    const res = await window.axios.get(route('sos.active-status'))
    hasActiveSosAlert.value = res.data.active
  } catch {
    hasActiveSosAlert.value = false
  }
}

let sosResponderChannel = null
function setupSosResponderListener() {
  if (!window.Echo || !hasPerm('sos.respond')) return

  sosResponderChannel = window.Echo.private('sos-responders')
    .listen('.sos.alert.triggered', (payload) => {
      sosResponderModal.value?.receiveNewAlert(payload)
      hasActiveSosAlert.value = true
    })
    .listen('.sos.alert.updated', () => {
      fetchSosActiveStatus()
    })
}
```

In `onMounted()`, right after the existing `setupEmergencyAlertListener(); fetchEmergencyStatus();` lines, add:

```js
  setupSosResponderListener()
  fetchSosActiveStatus()
```

In the `<template>`, right after the existing `<EmergencyBorderOverlay :active="hasActiveEmergency" />` line, add a second, responder-only instance plus the new modal:

```html
  <SosResponderAlertModal v-if="hasPerm('sos.respond')" ref="sosResponderModal" />
  <EmergencyBorderOverlay v-if="hasPerm('sos.respond')" :active="hasActiveSosAlert" />
```

(Both border instances share the same red-pulse visual by design — a responder seeing it can mean either a public emergency broadcast or an open SOS alert; both are true "something is wrong" signals, so no need to distinguish them visually for this pass.)

- [ ] **Step 8: Build and manually verify in the dev browser**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds.

Then: log in as a `DRRM Coordinator`/Administrator in one browser session and stay on a page other than Command Center (e.g. the Dashboard). From a second session, trigger a non-silent test SOS alert. Confirm the modal pops immediately in the first session without any refresh, showing reporter name and location, and that the red border activates. Click "Dismiss" and confirm the border stays on (alert still open). Resolve the alert from Command Center and confirm the border clears within a few seconds (via the `.sos.alert.updated` re-fetch).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Sos/SosAlertController.php routes/web.php tests/Feature/Sos/SosAlertControllerTest.php resources/js/Components/Sos/SosResponderAlertModal.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat(sos): notify responders site-wide the moment an SOS alert fires"
```

---

### Task 19: Reporter "My SOS Status" modal

**Files:**
- Modify: `resources/js/Components/Sos/SosFloatingButton.vue`
- Create: `resources/js/Components/Sos/SosMyStatusModal.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: `GET /sos/{alert}/mine` / `POST /sos/{alert}/mine/end` (Task 17).
- Produces: `<SosMyStatusModal ref>` exposing `open(alertId: Number)`, mounted site-wide; `SosFloatingButton` now emits `@triggered="(alertId) => ..."` on a successful non-silent trigger.

- [ ] **Step 1: Emit the new alert id from `SosFloatingButton.vue`**

In `resources/js/Components/Sos/SosFloatingButton.vue`, add below the existing `props` declaration:

```js
const emit = defineEmits(['triggered'])
```

In `dispatch()`, inside the `if (!isSilent) { ... }` block (after the successful `axios.post` call), add:

```js
    if (!isSilent) {
      pickerOpen.value = false
      selectedCategory.value = null
      emit('triggered', data.alert_id)
    }
```

(This only fires for non-silent alerts — a silent/duress trigger must stay invisible, so it deliberately never emits.)

- [ ] **Step 2: Write `SosMyStatusModal.vue`**

```vue
<script setup>
import { ref, onUnmounted } from 'vue'
import axios from 'axios'

const visible = ref(false)
const minimized = ref(false)
const status = ref(null)
const alertId = ref(null)
let pollTimer = null

const CLOSED_STATUSES = ['resolved', 'false_alarm']

async function poll() {
  if (!alertId.value) return
  try {
    const { data } = await axios.get(route('sos.mine.status', alertId.value))
    status.value = data
    if (CLOSED_STATUSES.includes(data.status)) {
      stopPolling()
      localStorage.removeItem('sos_my_active_alert_id')
    }
  } catch {
    stopPolling()
    localStorage.removeItem('sos_my_active_alert_id')
  }
}

function open(id) {
  alertId.value = id
  visible.value = true
  minimized.value = false
  poll()
  stopPolling()
  pollTimer = setInterval(poll, 7000)
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}

async function markSafe() {
  await axios.post(route('sos.mine.end', alertId.value))
  await poll()
}

function minimize() {
  minimized.value = true
}

function expand() {
  minimized.value = false
}

onUnmounted(stopPolling)

defineExpose({ open })
</script>

<template>
  <div v-if="visible && minimized" class="fixed bottom-24 right-5 z-50 cursor-pointer rounded-full bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-lg" @click="expand">
    SOS: {{ status?.status ?? '...' }}
  </div>

  <div v-else-if="visible" class="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 px-4 pt-20">
    <div class="w-full max-w-md rounded-2xl border-2 border-red-600 bg-white p-6 shadow-2xl">
      <h2 class="text-lg font-semibold text-red-700">Your SOS Alert</h2>
      <p class="mt-2 text-sm text-slate-700">
        Status: <span class="font-medium capitalize">{{ status?.status?.replace('_', ' ') }}</span>
      </p>
      <p v-if="status?.resolved_location_label" class="mt-1 text-sm text-slate-600">
        We noted your location as: {{ status.resolved_location_label }}
      </p>
      <p v-if="CLOSED_STATUSES.includes(status?.status)" class="mt-3 text-sm font-medium text-emerald-700">
        This alert has been closed.
      </p>

      <div class="mt-5 flex gap-2">
        <button
          v-if="!CLOSED_STATUSES.includes(status?.status)"
          type="button" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
          @click="markSafe"
        >
          I'm safe now
        </button>
        <button type="button" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200" @click="minimize">
          Minimize
        </button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 3: Wire it into `AdminLayout.vue`**

Add the import:

```js
import SosMyStatusModal from '@/Components/Sos/SosMyStatusModal.vue'
```

Add a ref near `sosResponderModal`:

```js
const myStatusModal = ref(null)

function onSosTriggered(alertId) {
  localStorage.setItem('sos_my_active_alert_id', String(alertId))
  myStatusModal.value?.open(alertId)
}
```

In `onMounted()`, after the `setupSosResponderListener(); fetchSosActiveStatus();` lines added in Task 18, add:

```js
  const savedAlertId = localStorage.getItem('sos_my_active_alert_id')
  if (savedAlertId) {
    myStatusModal.value?.open(Number(savedAlertId))
  }
```

Find the existing `<SosFloatingButton trigger-route="sos.trigger" />` line and add the new listener:

```html
  <SosFloatingButton trigger-route="sos.trigger" @triggered="onSosTriggered" />
```

Add the modal mount near the other site-wide components:

```html
  <SosMyStatusModal ref="myStatusModal" />
```

- [ ] **Step 4: Build and manually verify in the dev browser**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds.

Then: as any authenticated employee, trigger a non-silent test SOS alert via the floating button. Confirm the status modal opens immediately, showing "triggered" and (once resolved by a responder in another session) updating to "resolved" within ~7 seconds without a page refresh. Click "Minimize" and confirm it collapses to a small status chip that reopens on click. Refresh the page mid-alert and confirm the modal/chip reappears automatically (localStorage). Trigger a **silent** alert (long-press) and confirm no modal or chip ever appears. Click "I'm safe now" on an active alert and confirm it closes and the chip/modal clears.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Sos/SosFloatingButton.vue resources/js/Components/Sos/SosMyStatusModal.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat(sos): add reporter-facing live status modal with self mark-safe"
```

---

## Final verification

- [ ] Run the complete SOS + mobile-SOS regression suite once more: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos tests/Feature/Mobile/StudentSosTriggerTest.php tests/Feature/Mobile/StudentSosStatusEndTest.php"` — expect all green.
- [ ] Run a full frontend build: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` — expect no errors.
- [ ] Manual click-through in the dev browser covering: an active alert's location panel + map + GPS badge, claiming/unclaiming as two different responders, the History tab's filters, and the Stats tab's tiles/chart.
- [ ] Manual click-through (two sessions) covering: a responder sees the forced modal + border from any page the instant a non-silent alert fires (Task 18), a reporter sees their own live status modal and can mark themselves safe (Task 19), and a silent/duress alert produces zero UI on either side.
