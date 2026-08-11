# Issuance Flexible Multi-Criteria Recipients Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single exclusive `recipient_type` radio (all/office/division/individual staff) with a composable multi-criteria targeting model, and add students (individual/section/grade-level/all) as a new targetable recipient dimension, notified by best-effort email only.

**Architecture:** A new `issuance_recipient_criteria` table records *what was targeted* (one row per selected office/division/individual/section/grade/student, or a flag row for "all staff"/"all students"). `IssuanceService::resolveTargetIds()` turns any combination of criteria into a deduplicated staff-ID set and student-ID set, which get flattened into `issuance_recipients` rows exactly as today (one row per person) — `issuance_recipients` gains one new nullable `student_id` column alongside its existing `user_id`. A new shared `RecipientPicker.vue` component (8 independent toggles instead of 4 exclusive radio tiles) replaces the duplicated picker markup in all 3 places it currently appears.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>`, Inertia.js 2, PHPUnit (RefreshDatabase, factories).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-11-issuance-flexible-recipients-design.md`
- Students are notify-only via best-effort email — no Student Portal integration, no acknowledgment tracking for students (confirmed with user).
- `students.id` is a plain `int` (legacy MyISAM table) — never use `foreignId()`/`->constrained('students')`. Follow `student_enrollments.student_id`'s exact convention: `$table->unsignedInteger('student_id')->nullable()`, no FK constraint.
- Student resolution always goes through `Registrar\StudentEnrollment::active()->forSchoolYear($currentSY)`, never the legacy `section_students` mirror table.
- No shared component other than the new `RecipientPicker.vue` may change (`AppCard`, `AppButton`, `AppBadge`, `AppInput` stay untouched).
- No live resolved-recipient-count preview in the picker UI — out of scope.
- Additive-only schema changes (new nullable column, new table) — no destructive migration, ships in one deploy.

---

## File Structure

**New files:**
- `database/migrations/YYYY_MM_DD_HHMMSS_add_student_recipients_to_issuances.php`
- `app/Models/IssuanceRecipientCriterion.php`
- `resources/js/Components/RecipientPicker.vue`

**Modified files:**
- `app/Models/IssuanceRecipient.php` — `student()` relation, `student_id` fillable
- `app/Models/Issuance.php` — `recipientCriteria()` relation
- `app/Services/IssuanceService.php` — `resolveTargetIds()`, `recordCriteria()`, `updateRecipientTypeSummary()`, `summarizeSelectedTypes()`, `deliverRecipientEmail()`; rewritten `buildRecipients()`/`addRecipients()`
- `app/Http/Controllers/IssuanceController.php` — `store()`, `release()`, `addRecipients()`, `doRelease()`, `create()`, `show()`
- `app/Jobs/ProcessIssuanceRelease.php`, `app/Jobs/ResendIssuanceEmails.php`, `app/Jobs/NotifyAddedIssuanceRecipients.php` — use `deliverRecipientEmail()`
- `resources/js/Pages/Issuances/Create.vue`, `resources/js/Pages/Issuances/Show.vue`, `resources/js/Pages/Issuances/Index.vue`
- `tests/Unit/IssuanceServiceAddRecipientsTest.php`, `tests/Feature/IssuanceRecipientsAddTest.php`, `tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php` — updated to new payload shape

---

## Before You Start

```bash
cd /Users/junlou/bugsaymis-docker && docker compose up -d
```

Run PHP tests via: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=<Filter>"`

---

### Task 1: Schema — `student_id` column + `issuance_recipient_criteria` table

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_student_recipients_to_issuances.php`
- Create: `app/Models/IssuanceRecipientCriterion.php`
- Modify: `app/Models/IssuanceRecipient.php`
- Modify: `app/Models/Issuance.php`

**Interfaces:**
- Produces: `issuance_recipients.student_id` (nullable int, no FK), `issuance_recipient_criteria` table (`issuance_id`, `type`, `target_id`), `IssuanceRecipientCriterion` model, `IssuanceRecipient::student()` belongsTo, `Issuance::recipientCriteria()` hasMany.

- [ ] **Step 1: Generate the migration file**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan make:migration add_student_recipients_to_issuances"
```

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuance_recipients', function (Blueprint $table) {
            $table->unsignedInteger('student_id')->nullable()->after('user_id');
            $table->index(['issuance_id', 'student_id']);
        });

        Schema::create('issuance_recipient_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->constrained()->onDelete('cascade');
            $table->string('type', 20); // all_staff | office | division | individual_staff | all_students | section | grade_level | individual_student
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->index('issuance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_recipient_criteria');

        Schema::table('issuance_recipients', function (Blueprint $table) {
            $table->dropIndex(['issuance_recipients_issuance_id_student_id_index']);
            $table->dropColumn('student_id');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/<generated_filename>.php"
```

Verify: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"echo Schema::hasColumn('issuance_recipients','student_id') ? 'ok' : 'missing';\""` prints `ok`.

- [ ] **Step 4: Create `IssuanceRecipientCriterion` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuanceRecipientCriterion extends Model
{
    protected $fillable = ['issuance_id', 'type', 'target_id'];

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class);
    }
}
```

- [ ] **Step 5: Add `student()` relation to `IssuanceRecipient`**

In `app/Models/IssuanceRecipient.php`, update:

```php
class IssuanceRecipient extends Model
{
    protected $fillable = [
        'issuance_id', 'user_id', 'student_id', 'office_id', 'notified_at', 'acknowledged_at',
        'email_status', 'emailed_at', 'email_error',
    ];

    protected $casts = [
        'notified_at'    => 'datetime',
        'acknowledged_at'=> 'datetime',
        'emailed_at'     => 'datetime',
    ];

    public function issuance()
    {
        return $this->belongsTo(Issuance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
```

- [ ] **Step 6: Add `recipientCriteria()` relation to `Issuance`**

In `app/Models/Issuance.php`, add alongside the existing `recipients()` relation (find it via `grep -n "public function recipients" app/Models/Issuance.php`):

```php
public function recipientCriteria()
{
    return $this->hasMany(IssuanceRecipientCriterion::class);
}
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/IssuanceRecipientCriterion.php app/Models/IssuanceRecipient.php app/Models/Issuance.php
git commit -m "feat(issuances): add student_id column and recipient criteria table"
```

---

### Task 2: `IssuanceService` recipient resolution rewrite

**Files:**
- Modify: `app/Services/IssuanceService.php`
- Modify: `tests/Unit/IssuanceServiceAddRecipientsTest.php` (existing calls use the old `recipient_type` payload shape — must be rewritten to the new shape or they will fail)

**Interfaces:**
- Consumes: `IssuanceRecipientCriterion` (Task 1), `App\Models\FacultyLoading\SchoolYear` (`where('is_current', true)->first()`), `App\Models\Registrar\StudentEnrollment` (`scopeActive`, `scopeForSchoolYear`, columns `student_id`, `section_id`, `grade_level`).
- Produces: `IssuanceService::buildRecipients(Issuance $issuance, array $data): void`, `IssuanceService::addRecipients(Issuance $issuance, array $data): array` — both now accept `{all_staff, office_ids, division_ids, user_ids, all_students, section_ids, grade_levels, student_ids}` instead of `{recipient_type, office_ids, user_ids, division_ids}`. `IssuanceService::summarizeSelectedTypes(array $data): string` (new, public, pure — used by Task 4's `store()`).

- [ ] **Step 1: Write the new/updated tests first**

Replace the entire contents of `tests/Unit/IssuanceServiceAddRecipientsTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Division;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\IssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IssuanceServiceAddRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function releasedIssuance(): Issuance
    {
        $creator = User::factory()->create();

        return Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual_staff',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    private function currentSchoolYear(): SchoolYear
    {
        return SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
    }

    private function makeStudent(array $overrides = []): int
    {
        return (int) DB::table('students')->insertGetId(array_merge([
            'lastname' => 'Test' . uniqid(),
            'firstname' => 'Student',
        ], $overrides));
    }

    private function enroll(int $studentId, SchoolYear $sy, ?int $sectionId = null, int $gradeLevel = 9): StudentEnrollment
    {
        return StudentEnrollment::create([
            'student_id' => $studentId,
            'school_year_id' => $sy->id,
            'section_id' => $sectionId,
            'grade_level' => $gradeLevel,
            'enrollment_type' => 'returning',
            'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);
    }

    public function test_it_adds_new_individual_staff_recipients_and_returns_their_ids(): void
    {
        $issuance = $this->releasedIssuance();
        $user = User::factory()->create();

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'user_ids' => [$user->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertDatabaseHas('issuance_recipients', [
            'issuance_id' => $issuance->id,
            'user_id' => $user->id,
        ]);
        $recipient = IssuanceRecipient::find($newIds[0]);
        $this->assertNotNull($recipient->notified_at);
    }

    public function test_it_skips_users_who_are_already_recipients(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        $new = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'user_ids' => [$existing->id, $new->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($new->id, IssuanceRecipient::find($newIds[0])->user_id);
        $this->assertSame(2, $issuance->recipients()->count());
    }

    public function test_it_returns_empty_array_when_everyone_selected_is_already_a_recipient(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'user_ids' => [$existing->id],
        ]);

        $this->assertSame([], $newIds);
        $this->assertSame(1, $issuance->recipients()->count());
    }

    public function test_it_adds_recipients_by_office(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Test Office ' . uniqid()]);
        $memberA = User::factory()->create(['office_id' => $office->id]);
        $memberB = User::factory()->create(['office_id' => $office->id]);
        User::factory()->create(); // unrelated user, must not be added

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
        ]);

        $this->assertCount(2, $newIds);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberA->id]);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberB->id]);
    }

    public function test_it_adds_recipients_by_division(): void
    {
        $issuance = $this->releasedIssuance();
        $division = Division::create(['division_name' => 'Test Division ' . uniqid()]);
        $member = User::factory()->create(['division_id' => $division->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'division_ids' => [$division->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($member->id, IssuanceRecipient::find($newIds[0])->user_id);
    }

    public function test_it_adds_all_active_employees_and_excludes_inactive_ones(): void
    {
        $issuance = $this->releasedIssuance();
        $active = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        (new IssuanceService())->addRecipients($issuance, ['all_staff' => true]);

        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $active->id]);
        $this->assertDatabaseMissing('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $inactive->id]);
    }

    public function test_it_combines_office_and_individual_staff_without_duplicating_overlap(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Combo Office ' . uniqid()]);
        $officeMember = User::factory()->create(['office_id' => $office->id]);
        $extraPerson = User::factory()->create();

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
            'user_ids' => [$officeMember->id, $extraPerson->id], // officeMember picked both ways
        ]);

        $this->assertCount(2, $newIds); // officeMember once, extraPerson once — not 3
        $this->assertSame(2, $issuance->recipients()->count());
    }

    public function test_it_adds_students_by_individual_section_grade_and_all(): void
    {
        $issuance = $this->releasedIssuance();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'sectionname' => 'Test-A', 'levelid' => 9, 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);

        $individualStudentId = $this->makeStudent();
        $this->enroll($individualStudentId, $sy, null, 8);

        $sectionStudentId = $this->makeStudent();
        $this->enroll($sectionStudentId, $sy, $section->id, 9);

        $gradeStudentId = $this->makeStudent();
        $this->enroll($gradeStudentId, $sy, null, 10);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'student_ids' => [$individualStudentId],
            'section_ids' => [$section->id],
            'grade_levels' => [10],
        ]);

        $this->assertCount(3, $newIds);
        foreach ([$individualStudentId, $sectionStudentId, $gradeStudentId] as $sid) {
            $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'student_id' => $sid]);
        }
    }

    public function test_it_dedupes_a_student_picked_both_individually_and_via_their_section(): void
    {
        $issuance = $this->releasedIssuance();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'sectionname' => 'Test-B', 'levelid' => 7, 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, $section->id, 7);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'student_ids' => [$studentId],
            'section_ids' => [$section->id],
        ]);

        $this->assertCount(1, $newIds);
    }

    public function test_it_aborts_when_targeting_students_with_no_current_school_year(): void
    {
        $issuance = $this->releasedIssuance();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new IssuanceService())->addRecipients($issuance, ['all_students' => true]);
    }

    public function test_it_records_criteria_rows_for_a_combined_selection(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Criteria Office ' . uniqid()]);
        $user = User::factory()->create();

        (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
            'user_ids' => [$user->id],
        ]);

        $this->assertDatabaseHas('issuance_recipient_criteria', ['issuance_id' => $issuance->id, 'type' => 'office', 'target_id' => $office->id]);
        $this->assertDatabaseHas('issuance_recipient_criteria', ['issuance_id' => $issuance->id, 'type' => 'individual_staff', 'target_id' => $user->id]);
    }

    public function test_it_sets_recipient_type_summary_to_mixed_when_multiple_types_selected(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Summary Office ' . uniqid()]);
        $user = User::factory()->create();

        (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
            'user_ids' => [$user->id],
        ]);

        $this->assertSame('mixed', $issuance->fresh()->recipient_type);
    }

    public function test_it_sets_recipient_type_summary_to_single_type_when_only_one_selected(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Single Office ' . uniqid()]);

        (new IssuanceService())->addRecipients($issuance, ['office_ids' => [$office->id]]);

        $this->assertSame('office', $issuance->fresh()->recipient_type);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceServiceAddRecipientsTest"
```

Expected: failures (old code still expects `recipient_type` key, no `student_id`/criteria support yet).

- [ ] **Step 3: Rewrite `IssuanceService`'s recipient-fan-out section**

In `app/Services/IssuanceService.php`, add imports at the top:

```php
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IssuanceRecipientCriterion;
use App\Models\Registrar\StudentEnrollment;
use App\Mail\IssuanceReleasedMail;
use Illuminate\Support\Facades\Mail;
```

Replace the entire `// ── Recipient fan-out ─...` section (from `public function buildRecipients` through the end of the original `addRecipients` method) with:

```php
    // ── Recipient fan-out ─────────────────────────────────────────────────────

    public function buildRecipients(Issuance $issuance, array $data): void
    {
        $issuance->recipients()->delete();

        ['staff' => $staffIds, 'students' => $studentIds] = $this->resolveTargetIds($data);

        $rows = $staffIds->map(fn ($uid) => [
            'issuance_id' => $issuance->id,
            'user_id'     => $uid,
            'created_at'  => now(),
            'updated_at'  => now(),
        ])->concat($studentIds->map(fn ($sid) => [
            'issuance_id' => $issuance->id,
            'student_id'  => $sid,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]))->all();

        if (! empty($rows)) IssuanceRecipient::insert($rows);

        $this->recordCriteria($issuance, $data);
        $this->updateRecipientTypeSummary($issuance);
    }

    /**
     * Additive recipient fan-out for an already-released issuance. Unlike
     * buildRecipients() this never deletes existing rows — it resolves the
     * requested target set the same way, diffs out anyone already a
     * recipient, and inserts only the new rows. Returns the newly-inserted
     * issuance_recipients IDs so the caller can notify just those people.
     */
    public function addRecipients(Issuance $issuance, array $data): array
    {
        ['staff' => $staffIds, 'students' => $studentIds] = $this->resolveTargetIds($data);

        $existingUserIds    = $issuance->recipients()->whereNotNull('user_id')->pluck('user_id')->all();
        $existingStudentIds = $issuance->recipients()->whereNotNull('student_id')->pluck('student_id')->all();

        $newStaffIds   = $staffIds->diff($existingUserIds)->values();
        $newStudentIds = $studentIds->diff($existingStudentIds)->values();

        $now = now();
        $rows = $newStaffIds->map(fn ($uid) => [
            'issuance_id' => $issuance->id, 'user_id' => $uid,
            'notified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ])->concat($newStudentIds->map(fn ($sid) => [
            'issuance_id' => $issuance->id, 'student_id' => $sid,
            'notified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]))->all();

        if (! empty($rows)) IssuanceRecipient::insert($rows);

        $this->recordCriteria($issuance, $data);
        $this->updateRecipientTypeSummary($issuance);

        if (empty($rows)) return [];

        return $issuance->recipients()
            ->where(function ($q) use ($newStaffIds, $newStudentIds) {
                $q->whereIn('user_id', $newStaffIds)->orWhereIn('student_id', $newStudentIds);
            })
            ->pluck('id')->all();
    }

    /**
     * Resolves a flexible multi-criteria targeting payload into deduplicated
     * staff and student ID sets. Payload keys (all optional):
     * all_staff, office_ids, division_ids, user_ids, all_students,
     * section_ids, grade_levels, student_ids.
     *
     * @return array{staff: \Illuminate\Support\Collection<int,int>, students: \Illuminate\Support\Collection<int,int>}
     */
    private function resolveTargetIds(array $data): array
    {
        $staff = collect();
        if ($data['all_staff'] ?? false) {
            $staff = $staff->concat(User::employees()->where('status', '<>', 'inactive')->pluck('id'));
        }
        if (! empty($data['office_ids'])) {
            $staff = $staff->concat(User::employees()->whereIn('office_id', $data['office_ids'])->where('status', '<>', 'inactive')->pluck('id'));
        }
        if (! empty($data['division_ids'])) {
            $staff = $staff->concat(User::employees()->whereIn('division_id', $data['division_ids'])->where('status', '<>', 'inactive')->pluck('id'));
        }
        if (! empty($data['user_ids'])) {
            $staff = $staff->concat(User::employees()->whereIn('id', $data['user_ids'])->pluck('id'));
        }
        $staff = $staff->unique()->values();

        $wantsStudents = ($data['all_students'] ?? false)
            || ! empty($data['section_ids']) || ! empty($data['grade_levels']) || ! empty($data['student_ids']);

        $students = collect();
        if ($wantsStudents) {
            $currentSY = SchoolYear::where('is_current', true)->first();
            abort_if(! $currentSY, 422, 'No current school year is set — cannot resolve student recipients.');

            if ($data['all_students'] ?? false) {
                $students = $students->concat(StudentEnrollment::active()->forSchoolYear($currentSY->id)->pluck('student_id'));
            }
            if (! empty($data['section_ids'])) {
                $students = $students->concat(StudentEnrollment::active()->forSchoolYear($currentSY->id)->whereIn('section_id', $data['section_ids'])->pluck('student_id'));
            }
            if (! empty($data['grade_levels'])) {
                $students = $students->concat(StudentEnrollment::active()->forSchoolYear($currentSY->id)->whereIn('grade_level', $data['grade_levels'])->pluck('student_id'));
            }
            if (! empty($data['student_ids'])) {
                $students = $students->concat(StudentEnrollment::active()->forSchoolYear($currentSY->id)->whereIn('student_id', $data['student_ids'])->pluck('student_id'));
            }
            $students = $students->unique()->values();
        }

        return ['staff' => $staff, 'students' => $students];
    }

    private function recordCriteria(Issuance $issuance, array $data): void
    {
        $now  = now();
        $rows = [];
        $add  = function (string $type, $targetId = null) use (&$rows, $issuance, $now) {
            $rows[] = ['issuance_id' => $issuance->id, 'type' => $type, 'target_id' => $targetId, 'created_at' => $now, 'updated_at' => $now];
        };

        if ($data['all_staff'] ?? false) $add('all_staff');
        foreach ($data['office_ids'] ?? [] as $id) $add('office', $id);
        foreach ($data['division_ids'] ?? [] as $id) $add('division', $id);
        foreach ($data['user_ids'] ?? [] as $id) $add('individual_staff', $id);
        if ($data['all_students'] ?? false) $add('all_students');
        foreach ($data['section_ids'] ?? [] as $id) $add('section', $id);
        foreach ($data['grade_levels'] ?? [] as $id) $add('grade_level', $id);
        foreach ($data['student_ids'] ?? [] as $id) $add('individual_student', $id);

        if ($rows) IssuanceRecipientCriterion::insert($rows);
    }

    private function updateRecipientTypeSummary(Issuance $issuance): void
    {
        $types = $issuance->recipientCriteria()->distinct()->pluck('type')->all();
        $issuance->recipient_type = count($types) === 1 ? $types[0] : 'mixed';
        $issuance->save();
    }

    /**
     * Pure preview of what updateRecipientTypeSummary() would produce, computed
     * from a raw request payload before any criteria rows exist (used by
     * IssuanceController::store() when saving a not-yet-released draft).
     */
    public function summarizeSelectedTypes(array $data): string
    {
        $types = [];
        if ($data['all_staff'] ?? false) $types[] = 'all_staff';
        if (! empty($data['office_ids'])) $types[] = 'office';
        if (! empty($data['division_ids'])) $types[] = 'division';
        if (! empty($data['user_ids'])) $types[] = 'individual_staff';
        if ($data['all_students'] ?? false) $types[] = 'all_students';
        if (! empty($data['section_ids'])) $types[] = 'section';
        if (! empty($data['grade_levels'])) $types[] = 'grade_level';
        if (! empty($data['student_ids'])) $types[] = 'individual_student';

        return count($types) === 1 ? $types[0] : 'mixed';
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceServiceAddRecipientsTest"
```

Expected: all pass. If `test_it_aborts_when_targeting_students_with_no_current_school_year` fails because a `SchoolYear` factory/seeder auto-creates a current one in test setup, check `database/factories` / `TestCase` for a global seed and adjust the test to explicitly ensure no current SY exists (e.g. `SchoolYear::query()->update(['is_current' => false])` at the top of that test) rather than assuming a clean slate.

- [ ] **Step 5: Commit**

```bash
git add app/Services/IssuanceService.php tests/Unit/IssuanceServiceAddRecipientsTest.php
git commit -m "feat(issuances): flexible multi-criteria recipient resolution incl. students"
```

---

### Task 3: Shared email delivery + job updates (student branch)

**Files:**
- Modify: `app/Services/IssuanceService.php`
- Modify: `app/Jobs/ProcessIssuanceRelease.php`
- Modify: `app/Jobs/ResendIssuanceEmails.php`
- Modify: `app/Jobs/NotifyAddedIssuanceRecipients.php`
- Modify: `tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php`

**Interfaces:**
- Produces: `IssuanceService::deliverRecipientEmail(IssuanceRecipient $recipient, Issuance $issuance): string` — returns `'sent'`, `'skipped'`, or `'failed'`.

- [ ] **Step 1: Add failing tests for the student branch**

Append to `tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php` (inside the class, before the final `}`):

```php
    public function test_it_emails_a_student_recipient_and_does_not_send_bell_notification(): void
    {
        Mail::fake();

        $issuance = $this->releasedIssuance();
        $studentId = (int) \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'lastname' => 'Doe', 'firstname' => 'Jane',
            'student_email' => 'jane.doe@crc.pshs.edu.ph',
        ]);
        $recipient = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'student_id' => $studentId]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$recipient->id]))->handle();

        Mail::assertSent(IssuanceReleasedMail::class, fn ($mail) => $mail->issuance->is($issuance) && $mail->recipientName === 'Doe, Jane');
        $this->assertSame('sent', $recipient->fresh()->email_status);
        // students have no `users` row — no bell/push notification is possible or attempted
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $studentId, 'notifiable_type' => 'App\\Models\\Student']);
    }

    public function test_it_marks_a_student_with_no_email_as_skipped(): void
    {
        Mail::fake();

        $issuance = $this->releasedIssuance();
        $studentId = (int) \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'lastname' => 'NoEmail', 'firstname' => 'Test',
        ]);
        $recipient = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'student_id' => $studentId]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$recipient->id]))->handle();

        $this->assertSame('skipped', $recipient->fresh()->email_status);
        Mail::assertNotSent(IssuanceReleasedMail::class);
    }
```

- [ ] **Step 2: Run to verify these two fail**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyAddedIssuanceRecipientsJobTest"
```

Expected: the two new tests fail (job doesn't know about `student_id` yet), the existing 3 still pass.

- [ ] **Step 3: Add `deliverRecipientEmail()` to `IssuanceService`**

Add this method to `app/Services/IssuanceService.php`, near the other recipient-fan-out methods:

```php
    /**
     * Resolves the recipient's email/name (staff vs student), sends
     * IssuanceReleasedMail, and persists email_status/emailed_at/email_error.
     * Returns 'sent' | 'skipped' | 'failed'.
     */
    public function deliverRecipientEmail(IssuanceRecipient $recipient, Issuance $issuance): string
    {
        if ($recipient->student_id) {
            $email = $recipient->student?->student_email;
            $name  = $recipient->student?->full_name;
        } else {
            $email = $recipient->user?->email;
            $name  = $recipient->user?->name;
        }

        if (empty($email)) {
            $recipient->update([
                'email_status' => 'skipped',
                'email_error'  => 'No email on file for this recipient.',
            ]);
            return 'skipped';
        }

        try {
            Mail::to($email)->send(new IssuanceReleasedMail($issuance, $name));
            $recipient->update(['email_status' => 'sent', 'emailed_at' => now(), 'email_error' => null]);
            return 'sent';
        } catch (\Throwable $e) {
            $recipient->update(['email_status' => 'failed', 'email_error' => $e->getMessage()]);
            logger()->warning('Issuance recipient email failed', [
                'issuance_id'  => $issuance->id,
                'recipient_id' => $recipient->id,
                'kind'         => $recipient->student_id ? 'student' : 'staff',
                'email'        => $email,
                'error'        => $e->getMessage(),
            ]);
            return 'failed';
        }
    }
```

- [ ] **Step 4: Update `NotifyAddedIssuanceRecipients`**

Replace the `$recipients = ...` line and the `foreach` loop body in `app/Jobs/NotifyAddedIssuanceRecipients.php`:

```php
    public function handle(IssuanceService $svc): void
    {
        $issuance = Issuance::find($this->issuanceId);

        if (! $issuance) {
            logger()->error('NotifyAddedIssuanceRecipients: issuance not found', [
                'issuance_id' => $this->issuanceId,
            ]);
            return;
        }

        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with(['user', 'student'])->get();
        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($recipients as $recipient) {
            $status = $svc->deliverRecipientEmail($recipient, $issuance);
            match ($status) {
                'sent'    => $sent++,
                'skipped' => $skipped++,
                'failed'  => $failed++,
            };

            if ($status === 'sent' && ! $recipient->student_id) {
                try {
                    NotificationService::notifyUser(
                        $recipient->user,
                        'Issuance',
                        $issuance->display_number,
                        ($issuance->isSupplement() ? $issuance->document_kind_label : $issuance->type_label) . ": {$issuance->title}",
                        route('issuances.show', $issuance->id),
                    );
                } catch (\Throwable $e) {
                    logger()->warning('NotifyAddedIssuanceRecipients: bell/push failed', [
                        'issuance_id'  => $issuance->id,
                        'recipient_id' => $recipient->id,
                        'error'        => $e->getMessage(),
                    ]);
                }
            }
        }

        logger()->info('NotifyAddedIssuanceRecipients: complete', [
            'issuance_id' => $issuance->id,
            'requested'   => count($this->recipientIds),
            'sent'        => $sent,
            'skipped'     => $skipped,
            'failed'      => $failed,
        ]);
    }
```

Also change the `handle()` signature to inject `IssuanceService $svc` (Laravel resolves it automatically) and remove the now-unused `use App\Mail\IssuanceReleasedMail;` / `use Illuminate\Support\Facades\Mail;` imports if no longer referenced directly in this file (they're used inside the service now); add `use App\Services\IssuanceService;`.

- [ ] **Step 5: Update `ProcessIssuanceRelease`** — same transformation

In `app/Jobs/ProcessIssuanceRelease.php`, `handle()` already receives `IssuanceService $svc`. Replace the `$recipients = ...` line and its `foreach` loop:

```php
        // 3. Notify all recipients (email + bell + push)
        $recipients = $issuance->recipients()->with(['user', 'student'])->get();
        $sent       = 0;
        $skipped    = 0;
        $failed     = 0;

        foreach ($recipients as $recipient) {
            $status = $svc->deliverRecipientEmail($recipient, $issuance);
            match ($status) {
                'sent'    => $sent++,
                'skipped' => $skipped++,
                'failed'  => $failed++,
            };

            if ($status === 'sent' && ! $recipient->student_id) {
                try {
                    NotificationService::notifyUser(
                        $recipient->user,
                        'Issuance',
                        $issuance->display_number,
                        ($issuance->isSupplement() ? $issuance->document_kind_label : $issuance->type_label) . ": {$issuance->title}",
                        route('issuances.show', $issuance->id),
                    );
                } catch (\Throwable $e) {
                    logger()->warning('ProcessIssuanceRelease: bell/push failed', [
                        'issuance_id' => $issuance->id,
                        'recipient_id'=> $recipient->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }
```

Remove the now-unused `use App\Mail\IssuanceReleasedMail;` / `use Illuminate\Support\Facades\Mail;` imports if nothing else in the file uses them.

- [ ] **Step 6: Update `ResendIssuanceEmails`** — no bell/push branch (unchanged behavior: resend never re-notifies)

In `app/Jobs/ResendIssuanceEmails.php`, add `use App\Services\IssuanceService;`, change `handle()` to `handle(IssuanceService $svc): void`, and replace the `$recipients = ...` line and loop:

```php
        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with(['user', 'student'])->get();
        $sent       = 0;
        $skipped    = 0;
        $failed     = 0;

        foreach ($recipients as $recipient) {
            $status = $svc->deliverRecipientEmail($recipient, $issuance);
            match ($status) {
                'sent'    => $sent++,
                'skipped' => $skipped++,
                'failed'  => $failed++,
            };
        }
```

Remove now-unused `IssuanceReleasedMail`/`Mail` imports if unused elsewhere in the file.

- [ ] **Step 7: Run tests to verify they pass**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyAddedIssuanceRecipientsJobTest"
```

Expected: all 5 pass (3 original + 2 new).

- [ ] **Step 8: Commit**

```bash
git add app/Services/IssuanceService.php app/Jobs/ProcessIssuanceRelease.php app/Jobs/ResendIssuanceEmails.php app/Jobs/NotifyAddedIssuanceRecipients.php tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php
git commit -m "feat(issuances): deliver best-effort email to student recipients"
```

---

### Task 4: Controller validation + supplement inheritance

**Files:**
- Modify: `app/Http/Controllers/IssuanceController.php`
- Modify: `tests/Feature/IssuanceRecipientsAddTest.php`

**Interfaces:**
- Consumes: `IssuanceService::summarizeSelectedTypes()`, `buildRecipients()`, `addRecipients()` (Task 2).
- Produces: `store()`, `release()`, `addRecipients()` validated field set: `all_staff`, `office_ids[]`, `division_ids[]`, `user_ids[]`, `all_students`, `section_ids[]`, `grade_levels[]`, `student_ids[]`.

- [ ] **Step 1: Update the Feature test file's payloads first**

In `tests/Feature/IssuanceRecipientsAddTest.php`, every `->post(route('issuances.recipients.add', $issuance->id), ['recipient_type' => '...', ...])` call drops the `'recipient_type' => '...',` line — e.g.:

```php
// before
$response = $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
    'recipient_type' => 'individual',
    'user_ids' => [$newUser->id],
]);

// after
$response = $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
    'user_ids' => [$newUser->id],
]);
```

Apply this same removal (drop the `'recipient_type' => '...',` line, keep everything else) to all 6 test methods in that file, and update the two `releasedIssuance()`/draft-issuance helper's `'recipient_type' => 'individual'` seed value to `'recipient_type' => 'individual_staff'` (cosmetic — just keeps the fixture consistent with the new naming, not read by any assertion).

Then add one new test at the end of the class (before the closing `}`):

```php
    public function test_admin_can_combine_office_and_individual_in_one_request(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Combo Office ' . uniqid()]);
        $officeMember = User::factory()->create(['office_id' => $office->id]);
        $individual = User::factory()->create();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'office_ids' => [$office->id],
            'user_ids' => [$individual->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $officeMember->id]);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $individual->id]);
    }

    public function test_adding_recipients_rejects_an_empty_selection(): void
    {
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [])
            ->assertStatus(422);
    }
```

- [ ] **Step 2: Run to verify current state**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceRecipientsAddTest"
```

Expected: the two new tests fail (controller doesn't accept the new shape yet); the 6 existing ones may pass or fail depending on whether the controller's `required|in:...` rejects a request missing `recipient_type` — either way, they need the controller change in the next step.

- [ ] **Step 3: Add the shared "at least one selected" guard**

In `app/Http/Controllers/IssuanceController.php`, add this private method (near `assertSigningPin`):

```php
    private function assertHasRecipientSelection(array $data): void
    {
        $hasAny = ($data['all_staff'] ?? false) || ($data['all_students'] ?? false)
            || ! empty($data['office_ids']) || ! empty($data['division_ids']) || ! empty($data['user_ids'])
            || ! empty($data['section_ids']) || ! empty($data['grade_levels']) || ! empty($data['student_ids']);
        abort_if(! $hasAny, 422, 'Select at least one recipient.');
    }
```

Define this shared validation rule set as a private method too, to avoid repeating the 16-line array three times:

```php
    private function recipientTargetingRules(): array
    {
        return [
            'all_staff'      => 'nullable|boolean',
            'office_ids'     => 'nullable|array',
            'office_ids.*'   => 'exists:offices,id',
            'division_ids'   => 'nullable|array',
            'division_ids.*' => 'exists:divisions,id',
            'user_ids'       => 'nullable|array',
            'user_ids.*'     => 'exists:users,id',
            'all_students'   => 'nullable|boolean',
            'section_ids'    => 'nullable|array',
            'section_ids.*'  => 'exists:sections,id',
            'grade_levels'   => 'nullable|array',
            'grade_levels.*' => 'exists:grade_levels,grade',
            'student_ids'    => 'nullable|array',
            'student_ids.*'  => 'exists:students,id',
        ];
    }
```

- [ ] **Step 4: Update `store()`**

Replace the validation array in `store()` — remove `'recipient_type' => 'required|in:all,office,individual,division',`, `'office_ids' => 'nullable|array', 'office_ids.*' => 'exists:offices,id', 'user_ids' => ..., 'division_ids' => ...` (all superseded), and merge in the shared rules:

```php
        $validated = $request->validate(array_merge([
            'type'               => ['required', Rule::in(array_keys(IssuanceType::activeLabels()))],
            'title'              => 'required|string|max:500',
            'content'            => 'nullable|string',
            'scan_base64'        => 'nullable|string',
            'scan_filename'      => 'nullable|string|max:255',
            'scan_mime'          => 'nullable|string',
            'should_release'     => 'nullable|boolean',
            'pin'                => 'nullable|string',
        ], $this->recipientTargetingRules()));

        $this->assertHasRecipientSelection($validated);

        if ($validated['should_release'] ?? false) {
            $this->assertSigningPin($request, $validated);
        }
```

In the `Issuance::create([...])` call inside `store()`'s transaction, replace `'recipient_type' => $validated['recipient_type'],` with:

```php
                'recipient_type'     => $this->svc->summarizeSelectedTypes($validated),
```

- [ ] **Step 5: Update `release()`**

```php
    public function release(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if(! $issuance->isEditable(), 422, 'Only draft issuances can be released.');
        abort_if($issuance->isArchived(), 422, 'Restore this draft from the archive before releasing it.');
        if ($issuance->isSupplement()) {
            abort_if(! $issuance->parentIssuance?->isReleased() || $issuance->parentIssuance?->isArchived(), 422,
                'The parent issuance must be active and released.');
        }

        $validated = $request->validate(array_merge([
            'pin' => 'nullable|string',
        ], $this->recipientTargetingRules()));

        if (! $issuance->isSupplement()) {
            $this->assertHasRecipientSelection($validated);
        }

        $this->doRelease($request, $issuance, $validated);

        return back()->with('success', "{$issuance->display_number} released to recipients.");
    }
```

- [ ] **Step 6: Update `doRelease()`**

Replace the `DB::transaction` body:

```php
        DB::transaction(function () use ($issuance, $data) {
            $issuance->content_hash = $this->svc->computeHash($issuance);
            $issuance->status       = 'released';
            $issuance->released_at  = now();

            if ($issuance->isSupplement()) {
                $issuance->recipient_type = $issuance->parentIssuance->recipient_type;
                $issuance->save();
                $rows = $issuance->parentIssuance->recipients()->get(['user_id', 'office_id', 'student_id'])->map(fn ($recipient) => [
                    'issuance_id' => $issuance->id,
                    'user_id' => $recipient->user_id,
                    'office_id' => $recipient->office_id,
                    'student_id' => $recipient->student_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();
                if ($rows) IssuanceRecipient::insert($rows);
            } else {
                $issuance->save();
                $this->svc->buildRecipients($issuance, $data);
            }
            $issuance->recipients()->update(['notified_at' => now()]);
        });
```

- [ ] **Step 7: Update `addRecipients()`**

```php
    public function addRecipients(Request $request, Issuance $issuance)
    {
        abort_if(! $issuance->isReleased(), 422, 'Only released issuances can receive additional recipients.');
        abort_if($issuance->isArchived(), 422, 'Restore this issuance from the archive before adding recipients.');

        $validated = $request->validate($this->recipientTargetingRules());
        $this->assertHasRecipientSelection($validated);

        $newRecipientIds = $this->svc->addRecipients($issuance, $validated);

        if (empty($newRecipientIds)) {
            return back()->with('success', 'No new recipients — everyone selected already has this issuance.');
        }

        AuditLogger::log([
            'action'         => 'issuance_recipients_added',
            'auditable_type' => Issuance::class,
            'auditable_id'   => $issuance->id,
            'new_values'     => ['added_count' => count($newRecipientIds)],
        ]);

        NotifyAddedIssuanceRecipients::dispatch($issuance->id, $newRecipientIds);

        return back()->with('success', count($newRecipientIds) . ' new recipient(s) added and notified.');
    }
```

(The audit log's `new_values` drops `'recipient_type' => $validated['recipient_type']` since that key no longer exists — `added_count` alone is preserved.)

- [ ] **Step 8: Run tests to verify they pass**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceRecipientsAddTest"
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceSupplementTest"
```

Expected: all pass. `IssuanceSupplementTest` must still pass unchanged — it exercises the supplement-inheritance path touched in Step 6; if it fails, read its assertions before changing supplement logic further (don't guess).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/IssuanceController.php tests/Feature/IssuanceRecipientsAddTest.php
git commit -m "feat(issuances): accept flexible multi-criteria recipient payload in controllers"
```

---

### Task 5: Controller props — sections, grade levels, students

**Files:**
- Modify: `app/Http/Controllers/IssuanceController.php` (`create()`, `show()`)

**Interfaces:**
- Consumes: `FacultyLoading\SchoolYear`, `FacultyLoading\Section`, `FacultyLoading\GradeLevel`, `Registrar\StudentEnrollment`, `Student::full_name`.
- Produces: `sections`, `gradeLevels`, `students` Inertia props (array of `{id, sectionname, levelid}`, `{grade, label}`, `{id, full_name, grade_level, section_id}` respectively).

- [ ] **Step 1: Add imports**

At the top of `app/Http/Controllers/IssuanceController.php`, add:

```php
use App\Models\FacultyLoading\GradeLevel;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
```

- [ ] **Step 2: Add a private helper to build the 3 new props (shared by `create()` and `show()`)**

```php
    /** @return array{sections: \Illuminate\Support\Collection, gradeLevels: \Illuminate\Support\Collection, students: \Illuminate\Support\Collection} */
    private function recipientTargetingOptions(): array
    {
        $currentSY = SchoolYear::where('is_current', true)->first();

        $sections = $currentSY
            ? Section::where('school_year_id', $currentSY->id)->where('is_active', true)
                ->orderBy('levelid')->orderBy('sectionname')->get(['id', 'sectionname', 'levelid'])
            : collect();

        $gradeLevels = GradeLevel::orderBy('sort_order')->get(['grade', 'label']);

        $students = $currentSY
            ? StudentEnrollment::active()->forSchoolYear($currentSY->id)
                ->with('student:id,lastname,firstname,middlename')
                ->get()
                ->map(fn ($e) => [
                    'id'          => $e->student_id,
                    'full_name'   => $e->student?->full_name,
                    'grade_level' => $e->grade_level,
                    'section_id'  => $e->section_id,
                ])
                ->filter(fn ($s) => $s['full_name'])
                ->values()
            : collect();

        return compact('sections', 'gradeLevels', 'students');
    }
```

- [ ] **Step 3: Wire into `create()`**

```php
    public function create()
    {
        return Inertia::render('Issuances/Create', array_merge([
            'typeLabels' => Issuance::typeLabels(),
            'offices'    => Office::orderBy('name')->get(['id', 'name']),
            'divisions'  => Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym']),
            'users'      => User::employees()->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'position']),
            'hasPin'     => ! empty(auth()->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri(auth()->user()),
        ], $this->recipientTargetingOptions()));
    }
```

- [ ] **Step 4: Wire into `show()`**

Immediately before the `return Inertia::render('Issuances/Show', [...])` statement in `show()`, add:

```php
        $targetingOptions = $isAdmin ? $this->recipientTargetingOptions() : ['sections' => [], 'gradeLevels' => [], 'students' => []];
```

The `return Inertia::render('Issuances/Show', [...])` array already conditionally builds `divisions`/`offices`/`users` on `$isAdmin`. Add the three new props the same way, right after the existing `'users'` line, reading from `$targetingOptions` computed once above (not calling `recipientTargetingOptions()` repeatedly):

```php
            'users'      => $isAdmin ? User::employees()->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'position']) : [],
            'sections'    => $targetingOptions['sections'],
            'gradeLevels' => $targetingOptions['gradeLevels'],
            'students'    => $targetingOptions['students'],
```

- [ ] **Step 5: Also update the recipients query in `show()` to eager-load `student`**

Find `$recipients = $issuance->recipients()->with(['user:id,name,position,office_id', 'office:id,name'])->get()->map(...)` and change to:

```php
        $recipients = $issuance->recipients()
            ->with(['user:id,name,position,office_id', 'office:id,name', 'student:id,lastname,firstname,middlename'])
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'user'            => $r->user?->only('id', 'name', 'position'),
                'student'         => $r->student ? ['id' => $r->student->id, 'full_name' => $r->student->full_name] : null,
                'office'          => $r->office?->only('id', 'name'),
                'acknowledged_at' => $r->acknowledged_at?->toISOString(),
                'notified_at'     => $r->notified_at?->toISOString(),
                'email_status'    => $r->email_status,
                'emailed_at'      => $r->emailed_at?->toISOString(),
                'email_error'     => $r->email_error,
                'is_me'           => $r->user_id === $user->id,
            ]);
```

- [ ] **Step 6: Manually verify props via tinker**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"dd((new App\Http\Controllers\IssuanceController(app(App\Services\IssuanceService::class), app(App\Services\DigitalSignatureService::class))));\""
```

(This just confirms the controller instantiates without error — the real verification happens visually in Task 9's manual QA once the frontend consumes these props.)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/IssuanceController.php
git commit -m "feat(issuances): pass sections/gradeLevels/students props for recipient picker"
```

---

### Task 6: `RecipientPicker.vue` shared component

**Files:**
- Create: `resources/js/Components/RecipientPicker.vue`

**Interfaces:**
- Props: `offices`, `divisions`, `users`, `sections`, `gradeLevels`, `students` (all `Array`, default `[]`); `modelValue` (`Object`, required — shape `{all_staff, office_ids, division_ids, user_ids, all_students, section_ids, grade_levels, student_ids}`).
- Emits: `update:modelValue`.

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { ref, computed } from 'vue'
import AppInput from '@/Components/AppInput.vue'
import {
  UserGroupIcon, BuildingOfficeIcon, BuildingLibraryIcon, UserIcon,
  UsersIcon, Squares2X2Icon, AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  offices:     { type: Array, default: () => [] },
  divisions:   { type: Array, default: () => [] },
  users:       { type: Array, default: () => [] },
  sections:    { type: Array, default: () => [] },
  gradeLevels: { type: Array, default: () => [] },
  students:    { type: Array, default: () => [] },
  modelValue:  { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue'])

function update(patch) {
  emit('update:modelValue', { ...props.modelValue, ...patch })
}

const TOGGLES = [
  { key: 'all_staff',          label: 'All Staff',            sub: 'Every active employee',   icon: UserGroupIcon,      kind: 'flag' },
  { key: 'office',              label: 'By Office',            sub: 'Select offices',          icon: BuildingOfficeIcon, kind: 'list', field: 'office_ids' },
  { key: 'division',            label: 'By Division',          sub: 'Select divisions',        icon: BuildingLibraryIcon, kind: 'list', field: 'division_ids' },
  { key: 'individual_staff',    label: 'Individual Staff',     sub: 'Pick specific employees', icon: UserIcon,           kind: 'list', field: 'user_ids' },
  { key: 'all_students',        label: 'All Students',         sub: 'Every enrolled student',  icon: UsersIcon,          kind: 'flag' },
  { key: 'section',             label: 'By Section',           sub: 'Select sections',         icon: Squares2X2Icon,     kind: 'list', field: 'section_ids' },
  { key: 'grade_level',         label: 'By Grade Level',       sub: 'Select grade levels',     icon: AcademicCapIcon,    kind: 'list', field: 'grade_levels' },
  { key: 'individual_student',  label: 'Individual Students',  sub: 'Pick specific students',  icon: UserIcon,           kind: 'list', field: 'student_ids' },
]

const openPanels = ref(new Set(
  TOGGLES.filter(t => t.kind === 'flag' ? props.modelValue[t.key] : props.modelValue[t.field]?.length).map(t => t.key)
))

function isOpen(t) {
  return openPanels.value.has(t.key)
}

function toggleChip(t) {
  const next = new Set(openPanels.value)
  if (next.has(t.key)) {
    next.delete(t.key)
    if (t.kind === 'flag') update({ [t.key]: false })
    else update({ [t.field]: [] })
  } else {
    next.add(t.key)
    if (t.kind === 'flag') update({ [t.key]: true })
  }
  openPanels.value = next
}

function toggleInList(field, id) {
  const list = props.modelValue[field] ?? []
  const idx = list.indexOf(id)
  const next = idx === -1 ? [...list, id] : list.filter(x => x !== id)
  update({ [field]: next })
}

const search = ref({ office_ids: '', division_ids: '', user_ids: '', section_ids: '', student_ids: '' })

function filterList(list, q, keyFn) {
  const needle = (q || '').toLowerCase()
  return (list ?? []).filter(item => !needle || keyFn(item).toLowerCase().includes(needle))
}

const filteredOffices   = computed(() => filterList(props.offices, search.value.office_ids, o => o.name))
const filteredDivisions = computed(() => filterList(props.divisions, search.value.division_ids, d => `${d.division_name} ${d.acronym ?? ''}`))
const filteredUsers     = computed(() => filterList(props.users, search.value.user_ids, u => `${u.name} ${u.position ?? ''}`))
const filteredSections  = computed(() => filterList(props.sections, search.value.section_ids, s => s.sectionname))
const filteredStudents  = computed(() => filterList(props.students, search.value.student_ids, s => s.full_name))
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-2">Who receives this issuance?</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <button v-for="t in TOGGLES" :key="t.key" type="button"
          @click="toggleChip(t)"
          class="flex flex-col items-center gap-1 p-3 rounded-xl border text-center transition-colors"
          :class="isOpen(t) ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
          <component :is="t.icon" class="h-5 w-5" :class="isOpen(t) ? 'text-indigo-600' : 'text-slate-400'" />
          <p class="text-xs font-semibold" :class="isOpen(t) ? 'text-indigo-700' : 'text-slate-700'">{{ t.label }}</p>
          <p class="text-[10px] text-slate-400">{{ t.sub }}</p>
        </button>
      </div>
    </div>

    <div v-if="isOpen(TOGGLES[1])" class="space-y-2">
      <AppInput v-model="search.office_ids" type="text" placeholder="Search offices…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="o in filteredOffices" :key="o.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.office_ids ?? []).includes(o.id)"
            @change="toggleInList('office_ids', o.id)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ o.name }}</span>
        </label>
      </div>
      <p v-if="modelValue.office_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.office_ids.length }} office(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[2])" class="space-y-2">
      <AppInput v-model="search.division_ids" type="text" placeholder="Search divisions…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="d in filteredDivisions" :key="d.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.division_ids ?? []).includes(d.id)"
            @change="toggleInList('division_ids', d.id)" class="rounded border-slate-300 text-indigo-600" />
          <div>
            <p class="text-sm text-slate-700">{{ d.division_name }}</p>
            <p v-if="d.acronym" class="text-xs text-slate-400">{{ d.acronym }}</p>
          </div>
        </label>
      </div>
      <p v-if="modelValue.division_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.division_ids.length }} division(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[3])" class="space-y-2">
      <AppInput v-model="search.user_ids" type="text" placeholder="Search by name or position…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="u in filteredUsers.slice(0, 50)" :key="u.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.user_ids ?? []).includes(u.id)"
            @change="toggleInList('user_ids', u.id)" class="rounded border-slate-300 text-indigo-600" />
          <div>
            <p class="text-sm font-medium text-slate-700">{{ u.name }}</p>
            <p v-if="u.position" class="text-xs text-slate-400">{{ u.position }}</p>
          </div>
        </label>
      </div>
      <p v-if="modelValue.user_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.user_ids.length }} person(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[5])" class="space-y-2">
      <AppInput v-model="search.section_ids" type="text" placeholder="Search sections…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="s in filteredSections" :key="s.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.section_ids ?? []).includes(s.id)"
            @change="toggleInList('section_ids', s.id)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ s.sectionname }} <span class="text-xs text-slate-400">(Grade {{ s.levelid }})</span></span>
        </label>
      </div>
      <p v-if="modelValue.section_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.section_ids.length }} section(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[6])" class="space-y-2">
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="g in gradeLevels" :key="g.grade" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.grade_levels ?? []).includes(g.grade)"
            @change="toggleInList('grade_levels', g.grade)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ g.label }}</span>
        </label>
      </div>
      <p v-if="modelValue.grade_levels?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.grade_levels.length }} grade level(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[7])" class="space-y-2">
      <AppInput v-model="search.student_ids" type="text" placeholder="Search students by name…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="s in filteredStudents.slice(0, 50)" :key="s.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.student_ids ?? []).includes(s.id)"
            @change="toggleInList('student_ids', s.id)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ s.full_name }} <span class="text-xs text-slate-400">(Grade {{ s.grade_level }})</span></span>
        </label>
      </div>
      <p v-if="modelValue.student_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.student_ids.length }} student(s) selected</p>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Sanity-check the SFC compiles**

```bash
cd /Users/junlou/bugsaymis-docker/src/bugsaymis && node -e "
const { parse, compileTemplate } = require('@vue/compiler-sfc');
const fs = require('fs');
const src = fs.readFileSync('resources/js/Components/RecipientPicker.vue', 'utf8');
const { descriptor, errors } = parse(src);
if (errors.length) { console.error('PARSE ERRORS:', errors); process.exit(1); }
const result = compileTemplate({ source: descriptor.template.content, filename: 'RecipientPicker.vue', id: 'test' });
if (result.errors.length) { console.error('TEMPLATE ERRORS:', result.errors); process.exit(1); }
console.log('OK');
"
```

Expected: `OK`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/RecipientPicker.vue
git commit -m "feat(issuances): add shared RecipientPicker component"
```

---

### Task 7: Integrate `RecipientPicker` into `Create.vue`

**Files:**
- Modify: `resources/js/Pages/Issuances/Create.vue`

**Interfaces:**
- Consumes: `RecipientPicker` (Task 6).

- [ ] **Step 1: Update props, imports, and replace the picker state**

Add `RecipientPicker` to the imports and the 3 new props:

```js
import RecipientPicker from '@/Components/RecipientPicker.vue'
```

```js
const props = defineProps({
  typeLabels:   Object,
  offices:      Array,
  divisions:    Array,
  users:        Array,
  sections:     Array,
  gradeLevels:  Array,
  students:     Array,
  hasPin:       Boolean,
  signatureUri: String,
})
```

Replace the entire `// ── Step 3: Recipients ─...` block (from `const recipientType = ref('all')` through the `toggleDivision` function) with:

```js
// ── Step 3: Recipients ─────────────────────────────────────────────────────
const targeting = ref({
  all_staff: false, office_ids: [], division_ids: [], user_ids: [],
  all_students: false, section_ids: [], grade_levels: [], student_ids: [],
})
```

- [ ] **Step 2: Update `buildPayload()`**

```js
function buildPayload(pin = null) {
  return {
    type: type.value, title: title.value,
    content:      contentMode.value === 'editor' ? content.value : null,
    scan_base64:  contentMode.value === 'upload'  ? scanBase64.value : null,
    scan_filename:contentMode.value === 'upload'  ? scanFilename.value : null,
    scan_mime:    contentMode.value === 'upload'  ? scanMime.value : null,
    ...targeting.value,
    pin,
  }
}
```

- [ ] **Step 3: Replace the Step 3 template markup**

Replace everything from `<!-- Recipient type -->` through the closing `</div>` of the individual-selection block (i.e. the whole recipient-tile-grid + office/division/individual sub-pickers) with:

```html
          <RecipientPicker
            v-model="targeting"
            :offices="offices" :divisions="divisions" :users="users"
            :sections="sections" :grade-levels="gradeLevels" :students="students"
          />
```

- [ ] **Step 4: Update the Summary block**

Replace the `<p><span class="text-slate-500">Recipients:</span> ...</p>` line — the old inline object-lookup no longer has a single `recipientType` to key off. Replace with a small computed summary:

```js
const targetingSummaryParts = computed(() => {
  const t = targeting.value
  const parts = []
  if (t.all_staff) parts.push('All Staff')
  if (t.office_ids.length) parts.push(`${t.office_ids.length} Office(s)`)
  if (t.division_ids.length) parts.push(`${t.division_ids.length} Division(s)`)
  if (t.user_ids.length) parts.push(`${t.user_ids.length} Staff Member(s)`)
  if (t.all_students) parts.push('All Students')
  if (t.section_ids.length) parts.push(`${t.section_ids.length} Section(s)`)
  if (t.grade_levels.length) parts.push(`${t.grade_levels.length} Grade Level(s)`)
  if (t.student_ids.length) parts.push(`${t.student_ids.length} Student(s)`)
  return parts.length ? parts.join(', ') : 'None selected'
})
```

```html
            <p><span class="text-slate-500">Recipients:</span> <strong>{{ targetingSummaryParts }}</strong></p>
```

- [ ] **Step 5: Manual verification**

Start `npm run dev` if not running, log in as admin, go to `/issuances/create`, reach Step 3, verify: toggling multiple chips (e.g. "By Office" + "Individual Staff") shows both sub-pickers simultaneously; the Summary line updates to reflect combined selections; "Save as Draft" and "Sign & Release" both still submit successfully.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Issuances/Create.vue
git commit -m "feat(issuances): use RecipientPicker in Create.vue"
```

---

### Task 8: Integrate `RecipientPicker` into `Show.vue`'s Release Settings panel

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue`

**Interfaces:**
- Consumes: `RecipientPicker` (Task 6), `sections`/`gradeLevels`/`students` props (Task 5).

- [ ] **Step 1: Update props and imports**

```js
import RecipientPicker from '@/Components/RecipientPicker.vue'
```

```js
const props = defineProps({
  issuance:         Object,
  recipients:       Array,
  divisions:        Array,
  isAdmin:          Boolean,
  hasPin:           Boolean,
  signatureUri:     String,
  myAcknowledgedAt: String,
  verifyUrl:        String,
  supplements:      Array,
  offices:          Array,
  users:            Array,
  sections:         Array,
  gradeLevels:      Array,
  students:         Array,
})
```

- [ ] **Step 2: Replace the Release-flow state**

Replace `const recipientType = ref(props.issuance.recipient_type ?? 'all')`, `const selectedDivisionIds = ref([])`, `const divisionSearch = ref('')`, and the `filteredDivisions` computed + `toggleDivision` function with:

```js
const releaseTargeting = ref({
  all_staff: false, office_ids: [], division_ids: [], user_ids: [],
  all_students: false, section_ids: [], grade_levels: [], student_ids: [],
})
```

- [ ] **Step 3: Update `onPinVerified()`**

```js
function onPinVerified(pin) {
  showPinModal.value = false
  submitting.value   = true
  releaseErrors.value = {}
  router.post(route('issuances.release', props.issuance.id), {
    ...releaseTargeting.value,
    pin,
  }, {
    onSuccess: () => { submitting.value = false; showReleasePanel.value = false },
    onError:   e  => { releaseErrors.value = e; submitting.value = false },
    preserveScroll: true,
  })
}
```

- [ ] **Step 4: Replace the Release Settings panel markup**

Replace the whole `<div v-else>` block (the one with `label.block...Recipients` and the 4-option radio list) and the division-picker `<div v-if="!issuance.is_supplement && recipientType === 'division'">` block with a single `RecipientPicker`:

```html
              <div v-if="issuance.is_supplement" class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs text-indigo-700">
                Recipients will be inherited from {{ issuance.parent_issuance?.control_number }}. Each recipient will receive and acknowledge this document separately.
              </div>
              <RecipientPicker v-else
                v-model="releaseTargeting"
                :offices="offices" :divisions="divisions" :users="users"
                :sections="sections" :grade-levels="gradeLevels" :students="students"
              />
```

- [ ] **Step 5: Manual verification**

As admin, create a draft issuance (via Create.vue, "Save as Draft"), open it, click "Sign & Release", verify the same 8-toggle `RecipientPicker` appears (no more "(configure on create)" limitation — office/individual/section/etc. are now fully pickable at release time too), and releasing with a combined selection (e.g. one division + one section) succeeds and produces the right recipients on the Acknowledgments card.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "feat(issuances): use RecipientPicker in Release Settings panel"
```

---

### Task 9: Integrate `RecipientPicker` into `Show.vue`'s Add Recipient modal

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue`

**Interfaces:**
- Consumes: `RecipientPicker` (Task 6).

- [ ] **Step 1: Replace the Add Recipient state**

Replace the entire `// ── Add Recipient (post-release) ─...` block (`showAddRecipientModal` through `toggleAddDivision`) with:

```js
// ── Add Recipient (post-release) ───────────────────────────────────────────
const showAddRecipientModal = ref(false)
const addTargeting = ref({
  all_staff: false, office_ids: [], division_ids: [], user_ids: [],
  all_students: false, section_ids: [], grade_levels: [], student_ids: [],
})
const addingRecipients   = ref(false)
const addRecipientErrors = ref({})

function openAddRecipientModal() {
  addTargeting.value = {
    all_staff: false, office_ids: [], division_ids: [], user_ids: [],
    all_students: false, section_ids: [], grade_levels: [], student_ids: [],
  }
  addRecipientErrors.value = {}
  showAddRecipientModal.value = true
}

function submitAddRecipients() {
  addingRecipients.value = true
  addRecipientErrors.value = {}
  router.post(route('issuances.recipients.add', props.issuance.id), addTargeting.value, {
    preserveScroll: true,
    onSuccess: () => { addingRecipients.value = false; showAddRecipientModal.value = false },
    onError: e => { addRecipientErrors.value = e; addingRecipients.value = false },
  })
}
```

- [ ] **Step 2: Replace the Add Recipient modal template**

Replace the whole `<div class="space-y-5">...</div>` body inside `<AppModal :show="showAddRecipientModal" ...>` (the "Who should be added?" tile grid plus the office/division/individual sub-picker blocks) with:

```html
      <div class="space-y-5">
        <RecipientPicker
          v-model="addTargeting"
          :offices="offices" :divisions="divisions" :users="users"
          :sections="sections" :grade-levels="gradeLevels" :students="students"
        />

        <p v-if="Object.keys(addRecipientErrors).length" class="text-xs text-red-600">
          {{ Object.values(addRecipientErrors)[0] }}
        </p>
      </div>
```

(The error display simplifies from checking 4 specific keys to just showing the first validation error present — the new payload has 8 possible keys, so enumerating them individually is no longer worth the verbosity.)

- [ ] **Step 3: Manual verification**

As admin, on a released issuance, open "Add Recipient", verify the same picker appears; combine "By Grade Level" + "Individual Students" in one submission and confirm new recipients (both staff-shaped and student-shaped rows) appear in the Acknowledgments list, and that already-tagged people/students are silently skipped (dedup still works).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "feat(issuances): use RecipientPicker in Add Recipient modal"
```

---

### Task 10: Show.vue — student rows in Acknowledgments list + progress bar exclusion

**Files:**
- Modify: `resources/js/Pages/Issuances/Show.vue`

- [ ] **Step 1: Update the recipient row template**

Find the recipient list rendering (`<div v-for="r in recipients" :key="r.id" ...>`). Change the name line:

```html
                    <p class="text-xs font-medium text-slate-700 truncate">{{ r.user?.name ?? r.student?.full_name ?? r.office?.name ?? '—' }}</p>
                    <p v-if="r.user?.position" class="text-[10px] text-slate-400 truncate">{{ r.user.position }}</p>
                    <p v-else-if="r.student" class="text-[10px] text-slate-400 truncate">Student</p>
```

And the acknowledge-status slot at the end of the row — wrap the existing checkmark/clock in a staff-only guard:

```html
                  <span v-if="!r.student && r.acknowledged_at" class="text-success-500 text-xs" title="Acknowledged">✓</span>
                  <ClockIcon v-else-if="!r.student" class="h-3.5 w-3.5 text-slate-300" />
```

(Previously this was `<span v-if="r.acknowledged_at" ...>✓</span><ClockIcon v-else .../>` with no student guard — now a student row renders neither element, leaving that slot blank instead of a misleading pending clock.)

- [ ] **Step 2: Exclude students from the acknowledgment progress computeds**

Replace:

```js
const ackCount    = computed(() => (props.recipients ?? []).filter(r => r.acknowledged_at).length)
const totalCount  = computed(() => (props.recipients ?? []).length)
const ackPercent  = computed(() => totalCount.value ? Math.round((ackCount.value / totalCount.value) * 100) : 0)
```

with:

```js
const staffRecipients = computed(() => (props.recipients ?? []).filter(r => !r.student))
const ackCount    = computed(() => staffRecipients.value.filter(r => r.acknowledged_at).length)
const totalCount  = computed(() => staffRecipients.value.length)
const ackPercent  = computed(() => totalCount.value ? Math.round((ackCount.value / totalCount.value) * 100) : 0)
```

- [ ] **Step 3: Check "Select all" and bulk-resend still work across staff+student rows**

The `toggleSelectAll()`/`resendAll()` functions use `(props.recipients ?? [])` (not `staffRecipients`) — that's correct and must stay unchanged: resend/select-all should still cover student rows (they can still be resent an email), only the *acknowledgment* progress bar is staff-only. Verify no other code path was accidentally changed to reference `staffRecipients` where `recipients` was intended.

- [ ] **Step 4: Manual verification**

On a released issuance with both staff and student recipients, verify: student rows show name + "Student" + email status badge + resend icon (if not sent), but no acknowledge checkmark/clock; the progress bar's denominator only counts staff; "Select all" / "Resend All" still include student rows.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Issuances/Show.vue
git commit -m "feat(issuances): show student recipients in Acknowledgments list, exclude from ack progress"
```

---

### Task 11: `Index.vue` recipient-type label map

**Files:**
- Modify: `resources/js/Pages/Issuances/Index.vue`

- [ ] **Step 1: Find and expand the label map**

```bash
grep -n "all:.*'All Staff'\|recipient_type" resources/js/Pages/Issuances/Index.vue
```

Expand the object (however it's currently named — likely something like `RECIPIENT_TYPE_LABEL` or an inline map near the badge render) to:

```js
const RECIPIENT_TYPE_LABEL = {
  all: 'All Staff',                    // legacy
  individual: 'Individual',            // legacy
  all_staff: 'All Staff',
  individual_staff: 'Individual Staff',
  office: 'By Office',
  division: 'By Division',
  all_students: 'All Students',
  section: 'By Section',
  grade_level: 'By Grade Level',
  individual_student: 'Individual Student',
  mixed: 'Mixed',
}
```

Use the exact variable/object name already present in the file (found via the grep above) rather than introducing a second one.

- [ ] **Step 2: Manual verification**

Visit `/issuances`, confirm the recipient-type badge renders a real label (not `undefined`/blank) for both a pre-existing issuance (legacy value) and a newly-released one with a combined selection (shows "Mixed").

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Issuances/Index.vue
git commit -m "feat(issuances): expand recipient-type label map for new criteria types"
```

---

### Task 12: Full verification pass

**Files:** None (verification only).

- [ ] **Step 1: Run the full Issuances-related test suite**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=Issuance"
```

Expected: all pass (includes `IssuanceServiceAddRecipientsTest`, `IssuanceRecipientsAddTest`, `NotifyAddedIssuanceRecipientsJobTest`, `IssuanceSupplementTest`).

- [ ] **Step 2: Run the full project test suite**

```bash
docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test 2>/dev/null" > /tmp/full-test-output.log 2>&1
grep -n "Tests:" /tmp/full-test-output.log
```

Expected: failure count matches the project's known pre-existing baseline (43, per prior session) plus zero new failures — any new failing test not in that known set must be investigated before proceeding, not dismissed as "probably unrelated."

- [ ] **Step 3: Manual end-to-end walkthrough**

As admin: create a new issuance targeting "By Office" + "By Grade Level" combined, sign & release, confirm both staff and student rows appear in Acknowledgments with correct email-status badges; open "Add Recipient" and add one more individual student; confirm the new student appears and gets emailed (check mail log or a test inbox depending on dev mail config); confirm a staff recipient can still see and acknowledge the issuance from their own account; confirm `/issuances` index shows the right badge for this issuance ("Mixed").

- [ ] **Step 4: Report results**

Summarize pass/fail counts and any deviations found during manual walkthrough before moving to the finishing-a-development-branch flow.
