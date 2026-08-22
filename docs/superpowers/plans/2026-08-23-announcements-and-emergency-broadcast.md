# Announcements Full Wiring + Emergency Alert Broadcast Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Announcements module fully interactive (modal prompt + mark-as-read + AtlasGo reach) and give the SOS Command Center a campus-wide Emergency Alert broadcast capability, wired to both Atlas web and AtlasGo mobile.

**Architecture:** One shared polymorphic notice-delivery layer (`NoticeAudienceResolver` + `notice_acknowledgments` + a `HasNoticeAcknowledgments` trait) resolves and tracks read/ack state across three recipient types (`User`, `Student`, `ParentContact`). Announcements (Phase 1) and the new `EmergencyAlert` model (Phase 2) both consume this layer. Web delivery uses existing Echo/Soketi (`user.{id}` for the employee bell, a new shared `emergency-alerts` private channel for takeover). Mobile delivery is FCM push end-to-end — AtlasGo has no persistent socket and doesn't need one.

**Tech Stack:** Laravel 12 / PHP 8.4 (PHPUnit), Vue 3 + Inertia.js 2 (manual browser verification — this repo has no JS test runner), Flutter (Riverpod, flutter_test), MySQL 8, Redis-backed queues via Supervisor, Firebase Cloud Messaging (`kreait/firebase-php` server-side, `firebase_messaging` client-side).

**Spec:** `docs/superpowers/specs/2026-08-22-announcements-and-emergency-broadcast-design.md`

## Global Constraints

- Migrations are additive-only (blue-green discipline): new nullable columns via `->after()`, new tables — no drops/renames, per `CLAUDE.md`.
- File uploads (the announcement poster) must stay base64-JSON, never `multipart/form-data` — Cloudflare WAF blocks it. This module already does this correctly; do not change it.
- `Storage::disk('s3')` only, never `disk('public')`.
- Never use `Auth::user()->role_id` — use `hasPermission()`/`hasRole()`.
- No new TypeScript in Vue files (plain JS only). No new Blade views (Inertia only).
- `Student` model is `$guarded = ['*']` (legacy, read-only) — never mass-assign or `Student::create()`; new student-owned mutable state goes in `student_credentials`, matching that table's own stated purpose.
- New queued jobs that must not be delayed behind the `bulk` queue's large fan-outs get their own dedicated queue + Supervisor worker process, matching how `default` and `bulk` are already split in `docker/supervisord-worker.conf`.
- Stage files by exact path when committing (`git add <path>`), never `-A` or `.`.
- Run PHP tests via `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test <path>"` from `/Users/junlou/bugsaymis-docker`. Run Flutter tests via `flutter test <path>` from `~/bugsaymis-mobile`.

---

## Phase 1 — Shared infra + Announcements full wiring

### Task 1: `notice_acknowledgments` table + model + trait

**Files:**
- Create: `database/migrations/2026_08_23_090000_create_notice_acknowledgments_table.php`
- Create: `app/Models/NoticeAcknowledgment.php`
- Create: `app/Traits/HasNoticeAcknowledgments.php`
- Modify: `app/Models/Administration/Announcement.php`
- Test: `tests/Feature/Notices/HasNoticeAcknowledgmentsTraitTest.php`

**Interfaces:**
- Produces: `HasNoticeAcknowledgments::acknowledgments(): MorphMany`, `::isAcknowledgedBy(Model $recipient): bool`, `::acknowledgeFor(Model $recipient): void` — every later task (Announcement, EmergencyAlert, both NoticeControllers) calls these three.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasNoticeAcknowledgmentsTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledge_for_creates_row_and_is_acknowledged_by_reflects_it(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();

        $this->assertFalse($announcement->isAcknowledgedBy($user));

        $announcement->acknowledgeFor($user);

        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($user));
        $this->assertDatabaseHas('notice_acknowledgments', [
            'notice_type' => Announcement::class, 'notice_id' => $announcement->id,
            'recipient_type' => User::class, 'recipient_id' => $user->id,
        ]);
    }

    public function test_acknowledge_for_is_idempotent(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();

        $announcement->acknowledgeFor($user);
        $announcement->acknowledgeFor($user); // must not throw a unique-constraint violation

        $this->assertSame(1, $announcement->acknowledgments()->count());
    }

    public function test_two_different_recipient_types_acknowledging_the_same_notice_do_not_collide(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create(['id' => 5001]);
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-ACK-1', 'firstname' => 'Ack', 'lastname' => 'Student',
        ]);
        $student = \App\Models\Student::find($studentId);

        $announcement->acknowledgeFor($user);
        $announcement->acknowledgeFor($student);

        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($user));
        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($student));
        $this->assertSame(2, $announcement->acknowledgments()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/HasNoticeAcknowledgmentsTraitTest.php"`
Expected: FAIL — `Call to undefined method App\Models\Administration\Announcement::isAcknowledgedBy()` (and no `notice_acknowledgments` table).

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
        Schema::create('notice_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->string('notice_type');
            $table->unsignedBigInteger('notice_id');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(
                ['notice_type', 'notice_id', 'recipient_type', 'recipient_id'],
                'notice_ack_unique'
            );
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_acknowledgments');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_23_090000_create_notice_acknowledgments_table.php"`

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NoticeAcknowledgment extends Model
{
    protected $fillable = [
        'notice_type', 'notice_id', 'recipient_type', 'recipient_id', 'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function notice(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 5: Create the trait**

```php
<?php

namespace App\Traits;

use App\Models\NoticeAcknowledgment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared read/acknowledgment tracking for anything a recipient (User,
 * Student, or ParentContact) must explicitly dismiss — Announcements and
 * EmergencyAlerts both use this rather than each building their own
 * per-recipient-type pivot tables.
 */
trait HasNoticeAcknowledgments
{
    public function acknowledgments(): MorphMany
    {
        return $this->morphMany(NoticeAcknowledgment::class, 'notice');
    }

    public function isAcknowledgedBy(Model $recipient): bool
    {
        return $this->acknowledgments()
            ->where('recipient_type', get_class($recipient))
            ->where('recipient_id', $recipient->getKey())
            ->exists();
    }

    public function acknowledgeFor(Model $recipient): void
    {
        NoticeAcknowledgment::firstOrCreate([
            'notice_type'    => static::class,
            'notice_id'      => $this->getKey(),
            'recipient_type' => get_class($recipient),
            'recipient_id'   => $recipient->getKey(),
        ], [
            'acknowledged_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Apply the trait to `Announcement`**

In `app/Models/Administration/Announcement.php`, add the import and `use` statement:

```php
use App\Traits\HasNoticeAcknowledgments;
```

```php
class Announcement extends Model
{
    use HasNoticeAcknowledgments;

    protected $table = 'announcements';
    // ...rest unchanged
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/HasNoticeAcknowledgmentsTraitTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_23_090000_create_notice_acknowledgments_table.php app/Models/NoticeAcknowledgment.php app/Traits/HasNoticeAcknowledgments.php app/Models/Administration/Announcement.php tests/Feature/Notices/HasNoticeAcknowledgmentsTraitTest.php
git commit -m "feat(notices): add polymorphic notice-acknowledgment tracking"
```

---

### Task 2: Student FCM push token — close the "students can't receive push" gap

**Files:**
- Create: `database/migrations/2026_08_23_090100_add_fcm_device_token_to_student_credentials_table.php`
- Modify: `app/Models/StudentCredential.php`
- Modify: `app/Models/Student.php`
- Modify: `app/Http/Controllers/StudentAttendance/Api/AuthController.php:157-179`
- Test: `tests/Feature/Mobile/StudentFcmTokenTest.php`

**Interfaces:**
- Produces: `Student::credential(): HasOne` (returns `StudentCredential|null`), `StudentCredential.fcm_device_token` column. Task 7 (`NotifyAnnouncementJob`) and Task 19 (`DispatchEmergencyAlertJob`) both read `$student->credential?->fcm_device_token`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use App\Models\StudentCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_student_updating_fcm_token_persists_it_on_credential(): void
    {
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-FCM-1', 'firstname' => 'Fcm', 'lastname' => 'Student',
        ]);
        $credential = StudentCredential::create([
            'student_id' => $studentId, 'email' => 'fcm-student@example.com',
            'password' => bcrypt('secret'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $student = Student::find($studentId);
        $token = $student->createToken('test-device', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/mobile/fcm-token', ['fcm_token' => 'device-token-abc']);

        $response->assertOk();
        $this->assertSame('device-token-abc', $credential->fresh()->fcm_device_token);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Mobile/StudentFcmTokenTest.php"`
Expected: FAIL — `fcm_device_token` column does not exist on `student_credentials`, and the assertion fails since `updateFcmToken()` currently no-ops for `Student`.

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
        Schema::table('student_credentials', function (Blueprint $table) {
            $table->string('fcm_device_token', 500)->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_credentials', function (Blueprint $table) {
            $table->dropColumn('fcm_device_token');
        });
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_23_090100_add_fcm_device_token_to_student_credentials_table.php"`

- [ ] **Step 4: Add `fcm_device_token` to `StudentCredential::$fillable`**

In `app/Models/StudentCredential.php`:

```php
    protected $fillable = [
        'student_id',
        'email',
        'password',
        'status',
        'email_verified_at',
        'fcm_device_token',
    ];
```

- [ ] **Step 5: Add the `credential()` relation to `Student`**

In `app/Models/Student.php`, add the import and relation method (near the existing `parentContacts()` relation):

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

```php
    public function credential(): HasOne
    {
        return $this->hasOne(StudentCredential::class, 'student_id');
    }
```

(Add `use App\Models\StudentCredential;` if `StudentCredential` is not already imported in this file — check the existing `use` block first, both classes live in `App\Models` so no import is actually needed if `Student.php` doesn't already alias something else named `StudentCredential`.)

- [ ] **Step 6: Fix `updateFcmToken()` to handle the `Student` guard case**

In `app/Http/Controllers/StudentAttendance/Api/AuthController.php`, replace the method body (currently lines 163-179):

```php
    /**
     * PUT /api/mobile/fcm-token
     * Called by the Flutter app on startup or when FCM token refreshes.
     * Updates the authenticated recipient's FCM token — a parent updates
     * their own row directly; a student's token lives on their
     * `student_credentials` row (the `students` table itself is legacy/
     * read-only and never gets app-owned columns).
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if ($user instanceof ParentContact) {
            $user->update([
                'fcm_device_token' => $validated['fcm_token'],
                'notify_push'      => true,
            ]);
        } elseif ($user instanceof Student) {
            $user->credential?->update(['fcm_device_token' => $validated['fcm_token']]);
        }

        return response()->json(['message' => 'FCM token updated.']);
    }
```

(`Student` is already imported at the top of this file — used by `loginStudent()`.)

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Mobile/StudentFcmTokenTest.php"`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_23_090100_add_fcm_device_token_to_student_credentials_table.php app/Models/StudentCredential.php app/Models/Student.php app/Http/Controllers/StudentAttendance/Api/AuthController.php tests/Feature/Mobile/StudentFcmTokenTest.php
git commit -m "fix(mobile): persist FCM push token for student accounts"
```

---

### Task 3: `NoticeAudienceResolver` service

**Files:**
- Create: `app/Services/NoticeAudienceResolver.php`
- Test: `tests/Feature/Notices/NoticeAudienceResolverTest.php`

**Interfaces:**
- Consumes: `User::scopeEmployees()` (existing, `app/Models/User.php:316`), `Student::credential()` (Task 2), `ParentContact` model (existing).
- Produces: `NoticeAudienceResolver::resolve(string $audience): array` returning `['users' => Collection<User>, 'students' => Collection<Student>, 'parents' => Collection<ParentContact>]`. Task 7 (`NotifyAnnouncementJob`) and Task 19 (`DispatchEmergencyAlertJob`) both call this.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use App\Models\User;
use App\Services\NoticeAudienceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoticeAudienceResolverTest extends TestCase
{
    use RefreshDatabase;

    private function seedOneOfEach(): array
    {
        $employee = User::factory()->create(['account_type' => 'employee', 'status' => 'active']);

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-RES-1', 'firstname' => 'Res', 'lastname' => 'Olver',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'res-student@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $parent = ParentContact::create([
            'name' => 'Res Parent', 'email' => 'res-parent@example.com',
            'password' => bcrypt('x'), 'status' => 'active',
        ]);

        return [$employee, $studentId, $parent];
    }

    public function test_employees_audience_returns_only_employees(): void
    {
        [$employee] = $this->seedOneOfEach();

        $result = app(NoticeAudienceResolver::class)->resolve('employees');

        $this->assertTrue($result['users']->contains('id', $employee->id));
        $this->assertCount(0, $result['students']);
        $this->assertCount(0, $result['parents']);
    }

    public function test_students_audience_only_includes_students_with_an_atlasgo_credential(): void
    {
        [, $studentIdWithApp] = $this->seedOneOfEach();
        $studentIdWithoutApp = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-RES-2', 'firstname' => 'No', 'lastname' => 'App',
        ]);

        $result = app(NoticeAudienceResolver::class)->resolve('students');

        $this->assertTrue($result['students']->contains('id', $studentIdWithApp));
        $this->assertFalse($result['students']->contains('id', $studentIdWithoutApp));
    }

    public function test_parents_audience_returns_only_parents(): void
    {
        [, , $parent] = $this->seedOneOfEach();

        $result = app(NoticeAudienceResolver::class)->resolve('parents');

        $this->assertTrue($result['parents']->contains('id', $parent->id));
        $this->assertCount(0, $result['users']);
        $this->assertCount(0, $result['students']);
    }

    public function test_all_audience_returns_the_union_of_all_three_recipient_types(): void
    {
        [$employee, $studentId, $parent] = $this->seedOneOfEach();

        $result = app(NoticeAudienceResolver::class)->resolve('all');

        $this->assertTrue($result['users']->contains('id', $employee->id));
        $this->assertTrue($result['students']->contains('id', $studentId));
        $this->assertTrue($result['parents']->contains('id', $parent->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NoticeAudienceResolverTest.php"`
Expected: FAIL — `Class "App\Services\NoticeAudienceResolver" not found`

- [ ] **Step 3: Implement the service**

```php
<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use Illuminate\Support\Collection;

class NoticeAudienceResolver
{
    /**
     * @return array{users: Collection<int, User>, students: Collection<int, Student>, parents: Collection<int, ParentContact>}
     */
    public function resolve(string $audience): array
    {
        return match ($audience) {
            'employees' => [
                'users'    => $this->employees(),
                'students' => collect(),
                'parents'  => collect(),
            ],
            'students' => [
                'users'    => collect(),
                'students' => $this->students(),
                'parents'  => collect(),
            ],
            'parents' => [
                'users'    => collect(),
                'students' => collect(),
                'parents'  => $this->parents(),
            ],
            'all' => [
                'users'    => $this->employees(),
                'students' => $this->students(),
                'parents'  => $this->parents(),
            ],
            default => [
                'users' => collect(), 'students' => collect(), 'parents' => collect(),
            ],
        };
    }

    private function employees(): Collection
    {
        return User::employees()->where('status', '<>', 'inactive')->get();
    }

    private function students(): Collection
    {
        // Only students who actually have an AtlasGo account can receive
        // anything (no other delivery channel exists for students).
        return Student::whereHas('credential')->get();
    }

    private function parents(): Collection
    {
        return ParentContact::where('status', 'active')->get();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NoticeAudienceResolverTest.php"`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/NoticeAudienceResolver.php tests/Feature/Notices/NoticeAudienceResolverTest.php
git commit -m "feat(notices): add NoticeAudienceResolver for cross-model audience targeting"
```

---

### Task 4: Extend `Announcement` audience — model scopes + controller validation

**Files:**
- Modify: `app/Models/Administration/Announcement.php`
- Modify: `app/Http/Controllers/Administration/AnnouncementController.php:126-140`
- Test: `tests/Feature/Notices/AnnouncementAudienceScopesTest.php`

**Interfaces:**
- Produces: `Announcement::scopeVisibleTo(User $user)` (extended — now also matches `audience='employees'`), `Announcement::scopeVisibleToAudienceGroup(string $group)` (new — matches `'all'` or the given group, used for `'students'`/`'parents'`). Task 8 (web `NoticeController`) and Task 11 (mobile `NoticeController`) both use these.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementAudienceScopesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnnouncement(string $audience): Announcement
    {
        return Announcement::create([
            'title' => "Audience {$audience}", 'body' => 'Body', 'audience' => $audience,
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_visible_to_user_matches_all_and_employees_but_not_students_or_parents(): void
    {
        $all       = $this->makeAnnouncement('all');
        $employees = $this->makeAnnouncement('employees');
        $students  = $this->makeAnnouncement('students');
        $parents   = $this->makeAnnouncement('parents');
        $user      = User::factory()->create();

        $ids = Announcement::visibleTo($user)->pluck('id');

        $this->assertTrue($ids->contains($all->id));
        $this->assertTrue($ids->contains($employees->id));
        $this->assertFalse($ids->contains($students->id));
        $this->assertFalse($ids->contains($parents->id));
    }

    public function test_visible_to_audience_group_matches_all_and_the_named_group_only(): void
    {
        $all      = $this->makeAnnouncement('all');
        $students = $this->makeAnnouncement('students');
        $parents  = $this->makeAnnouncement('parents');

        $ids = Announcement::visibleToAudienceGroup('students')->pluck('id');

        $this->assertTrue($ids->contains($all->id));
        $this->assertTrue($ids->contains($students->id));
        $this->assertFalse($ids->contains($parents->id));
    }

    public function test_draft_announcements_are_never_visible_regardless_of_audience(): void
    {
        Announcement::create([
            'title' => 'Draft', 'body' => 'Body', 'audience' => 'all',
            'status' => 'draft', 'created_by' => User::factory()->create()->id,
        ]);

        $this->assertCount(0, Announcement::visibleToAudienceGroup('students')->get());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/AnnouncementAudienceScopesTest.php"`
Expected: FAIL — the `employees` case isn't matched by the current `visibleTo` scope, and `scopeVisibleToAudienceGroup` doesn't exist.

- [ ] **Step 3: Update the model's scopes**

In `app/Models/Administration/Announcement.php`, replace `scopeVisibleTo` and add `scopeVisibleToAudienceGroup`:

```php
    /** Published announcements addressed to everyone, all employees, or specifically to $user. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q
                ->whereIn('audience', ['all', 'employees'])
                ->orWhereHas('targets', fn ($t) => $t->where('users.id', $user->id)));
    }

    /** Published announcements addressed to everyone or to the given non-employee group (students|parents). */
    public function scopeVisibleToAudienceGroup(Builder $query, string $group): Builder
    {
        return $query->where('status', 'published')
            ->whereIn('audience', ['all', $group]);
    }
```

- [ ] **Step 4: Update controller validation to accept the new audience values**

In `app/Http/Controllers/Administration/AnnouncementController.php`, in `validateAnnouncement()`:

```php
            'audience'      => 'required|in:all,employees,students,parents,specific',
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/AnnouncementAudienceScopesTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Administration/Announcement.php app/Http/Controllers/Administration/AnnouncementController.php tests/Feature/Notices/AnnouncementAudienceScopesTest.php
git commit -m "feat(announcements): extend audience to employees/students/parents groups"
```

---

### Task 5: `NotifyAnnouncementJob` — route through the resolver, push to students/parents

**Files:**
- Modify: `app/Jobs/NotifyAnnouncementJob.php`
- Test: `tests/Feature/Notices/NotifyAnnouncementJobTest.php`

**Interfaces:**
- Consumes: `NoticeAudienceResolver::resolve()` (Task 3), `FcmService::send()` (existing, `app/Services/StudentAttendance/FcmService.php`), `Student::credential()` (Task 2), `ParentContact::wantsPushNotification()` (existing).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Jobs\NotifyAnnouncementJob;
use App\Models\Administration\Announcement;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use App\Models\User;
use App\Services\StudentAttendance\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyAnnouncementJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_audience_still_notifies_via_the_existing_bell_unchanged(): void
    {
        Notification::fake();

        $employee = User::factory()->create(['account_type' => 'employee', 'status' => 'active']);
        $announcement = Announcement::create([
            'title' => 'Staff Notice', 'body' => 'Body', 'audience' => 'employees',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        (new NotifyAnnouncementJob($announcement->id))->handle(
            app(\App\Services\NoticeAudienceResolver::class),
            app(FcmService::class),
        );

        Notification::assertSentTo($employee, \App\Notifications\RequestStatusNotification::class);
    }

    public function test_students_audience_pushes_via_fcm_to_students_with_a_token(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-JOB-1', 'firstname' => 'Job', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'job-student@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
            'fcm_device_token' => 'student-device-token',
        ]);
        $announcement = Announcement::create([
            'title' => 'Student Notice', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')
            ->once()
            ->with('student-device-token', 'New Announcement', 'Student Notice', [
                'type' => 'announcement', 'announcement_id' => (string) $announcement->id,
            ])
            ->andReturn(true);

        (new NotifyAnnouncementJob($announcement->id))->handle(
            app(\App\Services\NoticeAudienceResolver::class),
            $fcm,
        );
    }

    public function test_parents_audience_pushes_only_to_parents_who_want_push(): void
    {
        $wantsPush = ParentContact::create([
            'name' => 'Wants Push', 'email' => 'wants@example.com', 'password' => bcrypt('x'),
            'status' => 'active', 'notify_push' => true, 'fcm_device_token' => 'parent-token',
        ]);
        ParentContact::create([
            'name' => 'No Push', 'email' => 'nopush@example.com', 'password' => bcrypt('x'),
            'status' => 'active', 'notify_push' => false, 'fcm_device_token' => 'parent-token-2',
        ]);
        $announcement = Announcement::create([
            'title' => 'Parent Notice', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')->once()->with('parent-token', 'New Announcement', 'Parent Notice', [
            'type' => 'announcement', 'announcement_id' => (string) $announcement->id,
        ])->andReturn(true);

        (new NotifyAnnouncementJob($announcement->id))->handle(
            app(\App\Services\NoticeAudienceResolver::class),
            $fcm,
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NotifyAnnouncementJobTest.php"`
Expected: FAIL — `handle()` doesn't accept these parameters yet and never calls `FcmService`.

- [ ] **Step 3: Rewrite `handle()`**

Replace the body of `app/Jobs/NotifyAnnouncementJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Administration\Announcement;
use App\Services\NoticeAudienceResolver;
use App\Services\NotificationService;
use App\Services\StudentAttendance\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    // Single attempt — a retry would double-notify every targeted recipient.
    public int $tries = 1;

    public function __construct(public int $announcementId)
    {
        $this->onQueue('bulk');
    }

    public function handle(NoticeAudienceResolver $resolver, FcmService $fcm): void
    {
        $announcement = Announcement::find($this->announcementId);

        if (! $announcement || ! $announcement->isPublished()) {
            logger()->error('NotifyAnnouncementJob: announcement missing or unpublished', [
                'announcement_id' => $this->announcementId,
            ]);
            return;
        }

        if ($announcement->audience === 'specific') {
            $users = $announcement->targets()->where('status', '<>', 'inactive')->get();
            $students = collect();
            $parents = collect();
        } else {
            $resolved = $resolver->resolve($announcement->audience);
            $users = $resolved['users'];
            $students = $resolved['students'];
            $parents = $resolved['parents'];
        }

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                NotificationService::notifyUser(
                    $user,
                    'Announcement',
                    '#' . $announcement->id,
                    $announcement->title,
                    route('announcements.index'),
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('NotifyAnnouncementJob: notify failed', [
                    'announcement_id' => $announcement->id,
                    'user_id'         => $user->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $pushData = ['type' => 'announcement', 'announcement_id' => (string) $announcement->id];

        foreach ($students as $student) {
            $token = $student->credential?->fcm_device_token;
            if (! $token) {
                continue;
            }
            try {
                $fcm->send($token, 'New Announcement', $announcement->title, $pushData);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('NotifyAnnouncementJob: student push failed', [
                    'announcement_id' => $announcement->id, 'student_id' => $student->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($parents as $parent) {
            if (! $parent->wantsPushNotification()) {
                continue;
            }
            try {
                $fcm->send($parent->fcm_device_token, 'New Announcement', $announcement->title, $pushData);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('NotifyAnnouncementJob: parent push failed', [
                    'announcement_id' => $announcement->id, 'parent_contact_id' => $parent->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        logger()->info('NotifyAnnouncementJob: complete', [
            'announcement_id' => $announcement->id,
            'audience'        => $announcement->audience,
            'sent'            => $sent,
            'failed'          => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyAnnouncementJob: job FAILED', [
            'announcement_id' => $this->announcementId,
            'error'           => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NotifyAnnouncementJobTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full Announcements + Notices suite to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices tests/Feature/Administration"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/NotifyAnnouncementJob.php tests/Feature/Notices/NotifyAnnouncementJobTest.php
git commit -m "feat(announcements): push to students/parents via FCM on publish"
```

---

### Task 6: Web `NoticeController` — pending queue + acknowledge endpoint

**Files:**
- Create: `app/Http/Controllers/NoticeController.php`
- Modify: `routes/web.php` (near the `/docs` route, ~line 372)
- Test: `tests/Feature/Notices/NoticeControllerTest.php`

**Interfaces:**
- Produces: `GET /notices/pending` → `{"announcements": [{id, title, body, poster_path, published_at}], "emergency_alerts": []}` (the `emergency_alerts` key stays a hardcoded empty array until Task 18 populates it — the frontend queue component is written once in Task 7 to already expect this shape). `POST /notices/{type}/{id}/acknowledge` where `type` is `announcement` in this phase.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_excludes_already_acknowledged_and_unpublished_announcements(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        $unread = Announcement::create([
            'title' => 'Unread', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $alreadyRead = Announcement::create([
            'title' => 'Already Read', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $alreadyRead->acknowledgeFor($user);
        Announcement::create([
            'title' => 'Draft', 'body' => 'Body', 'audience' => 'all',
            'status' => 'draft', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/notices/pending');

        $response->assertOk();
        $titles = collect($response->json('announcements'))->pluck('title');
        $this->assertTrue($titles->contains('Unread'));
        $this->assertFalse($titles->contains('Already Read'));
        $this->assertFalse($titles->contains('Draft'));
    }

    public function test_acknowledge_writes_a_notice_acknowledgment_row(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::create([
            'title' => 'To Ack', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/notices/announcement/{$announcement->id}/acknowledge");

        $response->assertOk();
        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($user));
    }

    public function test_pending_requires_authentication(): void
    {
        $this->getJson('/notices/pending')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NoticeControllerTest.php"`
Expected: FAIL — route `notices/pending` doesn't exist (404).

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Administration\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeController extends Controller
{
    public function pending(): JsonResponse
    {
        $user = Auth::user();

        $announcements = Announcement::visibleTo($user)
            ->get()
            ->reject(fn (Announcement $a) => $a->isAcknowledgedBy($user))
            ->map(fn (Announcement $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'poster_path'  => $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'announcements'    => $announcements,
            'emergency_alerts' => [],
        ]);
    }

    public function acknowledge(Request $request, string $type, int $id): JsonResponse
    {
        $user = Auth::user();

        $notice = match ($type) {
            'announcement' => Announcement::findOrFail($id),
            default        => abort(404),
        };

        $notice->acknowledgeFor($user);

        return response()->json(['message' => 'Acknowledged.']);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, immediately after the `/docs` route (~line 372):

```php
    // ── Notices (Announcements + Emergency Alerts, unified read-tracking) ─────
    Route::get('/notices/pending', [\App\Http\Controllers\NoticeController::class, 'pending'])->name('notices.pending');
    Route::post('/notices/{type}/{id}/acknowledge', [\App\Http\Controllers\NoticeController::class, 'acknowledge'])
        ->whereIn('type', ['announcement', 'emergency-alert'])
        ->whereNumber('id')
        ->name('notices.acknowledge');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NoticeControllerTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NoticeController.php routes/web.php tests/Feature/Notices/NoticeControllerTest.php
git commit -m "feat(notices): add web pending-notices + acknowledge endpoints"
```

---

### Task 7: Web `NoticeQueueModal.vue` + `AdminLayout.vue` wiring

**Files:**
- Create: `resources/js/Components/Notices/NoticeQueueModal.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: `GET /notices/pending`, `POST /notices/{type}/{id}/acknowledge` (Task 6).
- Produces: exposes `receiveEmergencyAlert(alert)` (a component method, via `defineExpose`) — Task 24 (web emergency takeover) calls this through a template ref when a live broadcast arrives, so the same queue/priority logic in this component doesn't need to be duplicated.

- [ ] **Step 1: Create the component**

```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { storageUrl } from '@/Composables/useStorage'

const pending = ref({ emergency_alerts: [], announcements: [] })
const loaded = ref(false)

// Emergency alerts always come first — a life-safety notice must never
// wait behind "no classes Friday" in the queue.
const queue = computed(() => [
  ...pending.value.emergency_alerts.map(e => ({ ...e, kind: 'emergency-alert' })),
  ...pending.value.announcements.map(a => ({ ...a, kind: 'announcement' })),
])
const current = computed(() => queue.value[0] ?? null)
const currentIndex = computed(() => current.value ? queue.value.indexOf(current.value) : -1)

async function fetchPending() {
  try {
    const { data } = await axios.get('/notices/pending')
    pending.value = data
  } catch {
    // Silent — the modal just won't show; it isn't worth blocking the page over.
  } finally {
    loaded.value = true
  }
}

async function acknowledge() {
  if (!current.value) return
  const item = current.value
  try {
    await axios.post(`/notices/${item.kind}/${item.id}/acknowledge`)
  } catch {
    return // leave it in the queue, try again next load
  }
  if (item.kind === 'emergency-alert') {
    pending.value.emergency_alerts = pending.value.emergency_alerts.filter(e => e.id !== item.id)
  } else {
    pending.value.announcements = pending.value.announcements.filter(a => a.id !== item.id)
  }
}

/** Called by AdminLayout when a live emergency broadcast arrives on the Echo channel. */
function receiveEmergencyAlert(alert) {
  if (pending.value.emergency_alerts.some(e => e.id === alert.id)) return
  pending.value.emergency_alerts = [alert, ...pending.value.emergency_alerts]
}

defineExpose({ receiveEmergencyAlert })

onMounted(fetchPending)
</script>

<template>
  <div v-if="loaded && current" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-4">
    <div
      class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
      :class="current.kind === 'emergency-alert' ? 'border-4 border-red-600' : ''"
    >
      <p v-if="queue.length > 1" class="mb-2 text-xs font-medium text-slate-400">
        {{ currentIndex + 1 }} of {{ queue.length }}
      </p>

      <span
        v-if="current.kind === 'emergency-alert'"
        class="mb-2 inline-block rounded-full bg-red-600 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-white"
      >
        Emergency Alert{{ current.severity ? ` — ${current.severity}` : '' }}
      </span>

      <h2 class="text-lg font-semibold text-slate-900">{{ current.title }}</h2>
      <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ current.body || current.message }}</p>
      <img
        v-if="current.poster_path"
        :src="storageUrl(current.poster_path)"
        :alt="current.title"
        class="mt-3 max-h-64 w-full rounded-lg object-cover"
      />

      <button
        class="mt-5 w-full rounded-lg px-4 py-2 text-sm font-medium text-white"
        :class="current.kind === 'emergency-alert' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
        @click="acknowledge"
      >
        {{ current.kind === 'emergency-alert' ? 'Acknowledge' : 'Mark as Read' }}
      </button>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Wire it into `AdminLayout.vue`**

In `resources/js/Layouts/AdminLayout.vue`, add the import near the other component imports (~line 24):

```js
import NoticeQueueModal from '@/Components/Notices/NoticeQueueModal.vue'
```

Add a template ref and mount the component. Add near the top of the root template (a sibling of the existing layout content, so it overlays everything):

```html
  <NoticeQueueModal ref="noticeQueueModal" />
```

Expose the ref in the `<script setup>` block, near the other `ref()` declarations:

```js
const noticeQueueModal = ref(null)
```

- [ ] **Step 3: Manual verification**

Run `npm run dev` (or the project's usual Vite dev command) from `/Users/junlou/bugsaymis-docker/src/bugsaymis`, log in as an employee in the browser, and via `docker compose exec php artisan tinker` (or the Announcements UI) publish a test announcement with `audience = 'all'`. Reload any authenticated page — the modal must appear, non-dismissible via Escape/backdrop click, and "Mark as Read" must close it and it must not reappear on a further reload.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Notices/NoticeQueueModal.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat(notices): add announcement/emergency queue modal to web layout"
```

---

### Task 8: Web dashboard unread badge

**Files:**
- Modify: `app/Services/PersonalDashboardService.php:71-85`
- Find and modify the dashboard card Vue component that renders the `announcements` prop (locate via `grep -rl "announcements" resources/js/Pages/Dashboard.vue resources/js/Components` from the repo root — this codebase's dashboard card is rendered inline in `Dashboard.vue` per the existing `PersonalDashboardService::announcements()` shape, not a separate component file)
- Test: `tests/Feature/Notices/PersonalDashboardAnnouncementsTest.php`

**Interfaces:**
- Consumes: `Announcement::isAcknowledgedBy()` (Task 1).
- Produces: `announcements[].is_read: bool` in the `PersonalDashboardService` payload — the Vue card uses this to render an unread dot/badge per item plus a total unread count.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use App\Services\PersonalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalDashboardAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_announcements_include_is_read_flag(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        $unread = Announcement::create([
            'title' => 'Unread', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $read = Announcement::create([
            'title' => 'Read', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $read->acknowledgeFor($user);

        $data = app(PersonalDashboardService::class)->forUser($user);

        $byTitle = collect($data['announcements'])->keyBy('title');
        $this->assertFalse($byTitle['Unread']['is_read']);
        $this->assertTrue($byTitle['Read']['is_read']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/PersonalDashboardAnnouncementsTest.php"`

If `PersonalDashboardService` doesn't expose a public `forUser($user)` entry point under that exact name, first read `app/Services/PersonalDashboardService.php` in full to find its actual public method name and adjust this test's call to match — do not guess; the private `announcements()` method shown in Task exploration is called from a public method a few lines above it in that file.

Expected: FAIL — `is_read` key missing from the array.

- [ ] **Step 3: Update `PersonalDashboardService::announcements()`**

In `app/Services/PersonalDashboardService.php`, replace the `announcements()` method:

```php
    /** Latest published announcements addressed to this user. */
    private function announcements(User $user): array
    {
        return \App\Models\Administration\Announcement::visibleTo($user)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'poster_path', 'published_at'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'has_poster' => (bool) $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
                'is_read' => $a->isAcknowledgedBy($user),
            ])
            ->all();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/PersonalDashboardAnnouncementsTest.php"`
Expected: PASS

- [ ] **Step 5: Add the unread badge to the Vue dashboard card**

Locate the announcement card markup with:

```bash
grep -n "announcements" resources/js/Pages/Dashboard.vue
```

Add a small unread-count badge next to the card heading (exact class names should match the two-tone badge style already used elsewhere in this file — copy an existing `<span class="rounded-full ...">` badge from the same file rather than inventing new classes) and, per item, a `v-if="!item.is_read"` dot before the title. Since this file's exact current layout wasn't captured verbatim in this plan, read it first, then make the minimal edit: add a computed `unreadAnnouncementCount` in that page's `<script setup>` (`props.announcements.filter(a => !a.is_read).length`), render it as a badge, and add the unread dot per list item.

- [ ] **Step 6: Manual verification**

Reload `/dashboard` as an employee with at least one unread and one read announcement seeded — confirm the badge count and per-item dot match.

- [ ] **Step 7: Commit**

```bash
git add app/Services/PersonalDashboardService.php resources/js/Pages/Dashboard.vue tests/Feature/Notices/PersonalDashboardAnnouncementsTest.php
git commit -m "feat(dashboard): show unread badge on the announcements card"
```

---

### Task 9: Mobile API `NoticeController`

**Files:**
- Create: `app/Http/Controllers/StudentAttendance/Api/NoticeController.php`
- Modify: `routes/api.php` (inside the `mobile` prefix's `auth:sanctum` group, ~line 48)
- Test: `tests/Feature/Mobile/MobileNoticeControllerTest.php`

**Interfaces:**
- Consumes: `Announcement::scopeVisibleToAudienceGroup()` (Task 4), `HasNoticeAcknowledgments` (Task 1).
- Produces: `GET /api/mobile/notices/pending`, `POST /api/mobile/notices/{type}/{id}/acknowledge` — same response shape as the web endpoint (Task 6), consumed by the Flutter provider in Task 10.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Administration\Announcement;
use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileNoticeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_pending_returns_announcements_for_all_and_students_audiences(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-MOBNOTICE-1', 'firstname' => 'Mob', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'mobnotice@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $student = Student::find($studentId);
        $token = $student->createToken('device', ['mobile'])->plainTextToken;

        $creator = User::factory()->create();
        Announcement::create([
            'title' => 'For Students', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        Announcement::create([
            'title' => 'For Parents', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/pending');

        $response->assertOk();
        $titles = collect($response->json('announcements'))->pluck('title');
        $this->assertTrue($titles->contains('For Students'));
        $this->assertFalse($titles->contains('For Parents'));
    }

    public function test_parent_acknowledge_marks_it_read_and_it_drops_out_of_pending(): void
    {
        $parent = ParentContact::create([
            'name' => 'Mob Parent', 'email' => 'mobparent@example.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $token = $parent->createToken('device', ['mobile'])->plainTextToken;
        $announcement = Announcement::create([
            'title' => 'For Parents', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/notices/announcement/{$announcement->id}/acknowledge")
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/pending');
        $this->assertEmpty($response->json('announcements'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Mobile/MobileNoticeControllerTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Create the controller**

```php
<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Administration\Announcement;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $recipient = $request->user();
        $group = $recipient instanceof Student ? 'students' : 'parents';

        $announcements = Announcement::visibleToAudienceGroup($group)
            ->get()
            ->reject(fn (Announcement $a) => $a->isAcknowledgedBy($recipient))
            ->map(fn (Announcement $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'poster_path'  => $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'announcements'    => $announcements,
            'emergency_alerts' => [],
        ]);
    }

    public function acknowledge(Request $request, string $type, int $id): JsonResponse
    {
        $recipient = $request->user();

        $notice = match ($type) {
            'announcement' => Announcement::findOrFail($id),
            default        => abort(404),
        };

        $notice->acknowledgeFor($recipient);

        return response()->json(['message' => 'Acknowledged.']);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/api.php`, inside the `Route::middleware('auth:sanctum')->group(function () { ... })` block under the `mobile` prefix (~line 48), add — matching the web route's `/acknowledge` suffix shape for consistency, and leaving room for future non-acknowledge sub-actions on a notice:

```php
        Route::get('/notices/pending', [NoticeController::class, 'pending'])->name('notices.pending');
        Route::post('/notices/{type}/{id}/acknowledge', [NoticeController::class, 'acknowledge'])
            ->whereIn('type', ['announcement', 'emergency-alert'])
            ->whereNumber('id')
            ->name('notices.acknowledge');
```

Add the `use App\Http\Controllers\StudentAttendance\Api\NoticeController;` import at the top of `routes/api.php` if this file uses top-of-file imports for its controllers (check the existing `use` statements at the top of `routes/api.php` — if controllers there are referenced by full `\App\Http\Controllers\...` paths instead, match that file's existing convention rather than introducing a new import style).

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Mobile/MobileNoticeControllerTest.php"`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/NoticeController.php routes/api.php tests/Feature/Mobile/MobileNoticeControllerTest.php
git commit -m "feat(mobile): add pending-notices + acknowledge API for students/parents"
```

---

### Task 10: Flutter `notices_provider.dart`

**Files:**
- Create: `~/bugsaymis-mobile/lib/src/features/notices/notices_provider.dart`
- Test: `~/bugsaymis-mobile/test/features/notices/notices_provider_test.dart`

**Interfaces:**
- Consumes: `apiClientProvider` (existing, `lib/src/core/api_client.dart`).
- Produces: `noticesProvider` (a `FutureProvider<NoticesData>`, auto-disposed), `NoticesData { List<NoticeItem> announcements; List<NoticeItem> emergencyAlerts; }`, `NoticeItem { int id; String title; String body; String? posterPath; String kind; }`. Task 11 (dialog widget) and Task 13 (dashboard card) both consume `noticesProvider`.

- [ ] **Step 1: Write the failing test**

```dart
import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/api_client.dart';
import 'package:atlasgo/src/features/notices/notices_provider.dart';

class _StaticAdapter implements HttpClientAdapter {
  final String body;
  _StaticAdapter(this.body);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream,
      Future<void>? cancelFuture) async {
    return ResponseBody.fromString(body, 200, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const secureStorageChannel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(secureStorageChannel, (call) async => null);

  test('parses announcements and emergency_alerts into typed notice items', () async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _StaticAdapter('''
      {
        "emergency_alerts": [{"id": 9, "title": "Lockdown", "message": "Stay indoors", "severity": "critical"}],
        "announcements": [{"id": 1, "title": "No classes Friday", "body": "Enjoy the break", "poster_path": null}]
      }
    ''');

    final container = ProviderContainer(overrides: [apiClientProvider.overrideWithValue(apiClient)]);
    addTearDown(container.dispose);

    final data = await container.read(noticesProvider.future);

    expect(data.emergencyAlerts, hasLength(1));
    expect(data.emergencyAlerts.first.kind, 'emergency-alert');
    expect(data.emergencyAlerts.first.title, 'Lockdown');
    expect(data.announcements, hasLength(1));
    expect(data.announcements.first.kind, 'announcement');
    expect(data.announcements.first.title, 'No classes Friday');
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `~/bugsaymis-mobile`): `flutter test test/features/notices/notices_provider_test.dart`
Expected: FAIL — file doesn't exist.

- [ ] **Step 3: Implement the provider**

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';

class NoticeItem {
  final int id;
  final String title;
  final String body;
  final String? posterPath;
  final String kind; // 'announcement' | 'emergency-alert'
  final String? severity; // only set for emergency-alert items

  NoticeItem({
    required this.id,
    required this.title,
    required this.body,
    required this.kind,
    this.posterPath,
    this.severity,
  });

  factory NoticeItem.fromAnnouncementJson(Map<String, dynamic> json) => NoticeItem(
        id: json['id'] as int,
        title: json['title'] as String,
        body: (json['body'] ?? '') as String,
        posterPath: json['poster_path'] as String?,
        kind: 'announcement',
      );

  factory NoticeItem.fromEmergencyAlertJson(Map<String, dynamic> json) => NoticeItem(
        id: json['id'] as int,
        title: json['title'] as String,
        body: (json['message'] ?? '') as String,
        kind: 'emergency-alert',
        severity: json['severity'] as String?,
      );
}

class NoticesData {
  final List<NoticeItem> emergencyAlerts;
  final List<NoticeItem> announcements;

  NoticesData({required this.emergencyAlerts, required this.announcements});

  /// Emergency alerts always sort first — mirrors the web queue's priority rule.
  List<NoticeItem> get queue => [...emergencyAlerts, ...announcements];
}

final noticesProvider = FutureProvider.autoDispose<NoticesData>((ref) async {
  final api = ref.read(apiClientProvider);
  final response = await api.get('/notices/pending');
  final data = response.data as Map<String, dynamic>;

  return NoticesData(
    emergencyAlerts: (data['emergency_alerts'] as List<dynamic>? ?? [])
        .map((e) => NoticeItem.fromEmergencyAlertJson(e as Map<String, dynamic>))
        .toList(),
    announcements: (data['announcements'] as List<dynamic>? ?? [])
        .map((a) => NoticeItem.fromAnnouncementJson(a as Map<String, dynamic>))
        .toList(),
  );
});

Future<void> acknowledgeNotice(ApiClient api, NoticeItem item) =>
    api.post('/notices/${item.kind}/${item.id}/acknowledge');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `flutter test test/features/notices/notices_provider_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/notices/notices_provider.dart test/features/notices/notices_provider_test.dart
git commit -m "feat(notices): add notices_provider fetching pending announcements/alerts"
```

---

### Task 11: Flutter `NoticeQueueDialog` widget + wiring into both dashboards

**Files:**
- Create: `~/bugsaymis-mobile/lib/src/features/notices/notice_queue_dialog.dart`
- Modify: `~/bugsaymis-mobile/lib/src/features/home/home_screen.dart`
- Modify: `~/bugsaymis-mobile/lib/src/features/student/student_dashboard_screen.dart`

**Interfaces:**
- Consumes: `noticesProvider`, `acknowledgeNotice()` (Task 10).
- Produces: `showPendingNoticesIfAny(BuildContext, WidgetRef)` — a function both dashboard screens call from `initState`/a post-frame callback.

- [ ] **Step 1: Implement the dialog + launcher function**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';
import 'notices_provider.dart';

/// Call once when a dashboard screen first builds (after auth) — shows the
/// full unread queue (emergency alerts first) as a sequence of non-dismissible
/// dialogs, exactly mirroring the web NoticeQueueModal's behavior.
void showPendingNoticesIfAny(BuildContext context, WidgetRef ref) {
  Future.microtask(() async {
    final data = await ref.read(noticesProvider.future);
    if (!context.mounted) return;
    await _showQueue(context, ref, List<NoticeItem>.from(data.queue));
  });
}

Future<void> _showQueue(BuildContext context, WidgetRef ref, List<NoticeItem> queue) async {
  if (queue.isEmpty) return;
  final item = queue.first;

  await showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (dialogContext) => PopScope(
      canPop: false,
      child: NoticeQueueDialog(
        item: item,
        position: '1 of ${queue.length}',
        showPosition: queue.length > 1,
        onAcknowledge: () async {
          await acknowledgeNotice(ref.read(apiClientProvider), item);
          if (dialogContext.mounted) Navigator.of(dialogContext).pop();
        },
      ),
    ),
  );

  if (context.mounted) {
    await _showQueue(context, ref, queue.sublist(1));
  }
}

class NoticeQueueDialog extends StatelessWidget {
  final NoticeItem item;
  final String position;
  final bool showPosition;
  final Future<void> Function() onAcknowledge;

  const NoticeQueueDialog({
    super.key,
    required this.item,
    required this.position,
    required this.showPosition,
    required this.onAcknowledge,
  });

  bool get _isEmergency => item.kind == 'emergency-alert';

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: _isEmergency ? const BorderSide(color: Colors.red, width: 4) : BorderSide.none,
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (showPosition)
              Text(position, style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
            if (_isEmergency) ...[
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(999)),
                child: const Text('EMERGENCY ALERT',
                    style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
              ),
            ],
            const SizedBox(height: 8),
            Text(item.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            Text(item.body, style: const TextStyle(fontSize: 14)),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: _isEmergency ? Colors.red.shade700 : Theme.of(context).colorScheme.primary,
                ),
                onPressed: onAcknowledge,
                child: Text(_isEmergency ? 'Acknowledge' : 'Mark as Read'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 2: Wire into `home_screen.dart`**

Read `lib/src/features/home/home_screen.dart` first to find its `build()`/`initState()` shape (it's a `ConsumerWidget` or `ConsumerStatefulWidget` per this repo's established Riverpod convention). Add the import:

```dart
import '../notices/notice_queue_dialog.dart';
```

If `HomeScreen` is a `ConsumerStatefulWidget`, call `showPendingNoticesIfAny(context, ref)` inside a `WidgetsBinding.instance.addPostFrameCallback` in `initState()`. If it's a stateless `ConsumerWidget`, wrap the call the same way inside `build()` guarded by a `useEffect`-style one-shot — since this repo doesn't use `flutter_hooks` (confirm via `grep flutter_hooks pubspec.yaml` before assuming), the correct pattern here is converting the relevant section to use a `Consumer` + a local `bool _shown` flag is over-engineering; simplest correct fix matching this repo's actual widget type: read the file first and add the post-frame callback in whichever lifecycle method that widget type actually exposes.

- [ ] **Step 3: Wire into `student_dashboard_screen.dart`**

Same pattern — this is a **separate widget from `HomeScreen`** (confirmed gotcha from a prior session, see `project_atlasgo_foundation_redesign` memory: `/student/home` routes to `StudentDashboardScreen`, not `HomeScreen`). Add the identical `showPendingNoticesIfAny(context, ref)` call to this screen's own lifecycle method — skipping this screen means students never see the modal at all, since they never render `HomeScreen`.

- [ ] **Step 4: Manual verification**

Boot the iOS Simulator (or Android emulator) against dev Docker, log in as the test student fixture (`claude-emu-test@crc.pshs.edu.ph` per `project_atlasgo_foundation_redesign` memory, or create a fresh one), publish a test announcement with `audience = 'students'` via the web Announcements page, then reopen/foreground the app — the dialog must appear, "Mark as Read" must dismiss it, and it must not reappear.

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/notices/notice_queue_dialog.dart lib/src/features/home/home_screen.dart lib/src/features/student/student_dashboard_screen.dart
git commit -m "feat(notices): show pending announcement/alert queue on app open"
```

---

### Task 12: Flutter dashboard "Latest Announcements" card

**Files:**
- Create: `~/bugsaymis-mobile/lib/src/features/notices/announcements_card.dart`
- Modify: `~/bugsaymis-mobile/lib/src/features/home/home_screen.dart`
- Modify: `~/bugsaymis-mobile/lib/src/features/student/student_dashboard_screen.dart`

**Interfaces:**
- Consumes: `noticesProvider` (Task 10).

- [ ] **Step 1: Implement the card**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../shared/widgets/app_card.dart';
import 'notices_provider.dart';

class AnnouncementsCard extends ConsumerWidget {
  const AnnouncementsCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notices = ref.watch(noticesProvider);

    return notices.when(
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
      data: (data) {
        if (data.announcements.isEmpty) return const SizedBox.shrink();

        return AppCard(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Text('Announcements', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.indigo.shade50,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        '${data.announcements.length}',
                        style: TextStyle(fontSize: 11, color: Colors.indigo.shade700, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                for (final item in data.announcements.take(3))
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Text(item.title, style: const TextStyle(fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}
```

(Read `lib/src/shared/widgets/app_card.dart` first to confirm `AppCard`'s actual constructor signature — this repo's own `project_atlasgo_foundation_redesign` memory notes it recently gained elevation/press-scale props; use it exactly as the rest of the Home screen already does, matching whatever call pattern is already present there.)

- [ ] **Step 2: Add the card to both dashboards**

In `home_screen.dart` and `student_dashboard_screen.dart`, add `const AnnouncementsCard()` into the existing card list/column, in the same position other "needs your attention"-style cards already occupy (read each file first to match its existing card-list structure exactly).

- [ ] **Step 3: Manual verification**

With the same test announcement from Task 11 still unread, confirm the card renders on both Home (parent) and Student Dashboard screens with the correct unread count and truncated title list.

- [ ] **Step 4: Commit**

```bash
git add lib/src/features/notices/announcements_card.dart lib/src/features/home/home_screen.dart lib/src/features/student/student_dashboard_screen.dart
git commit -m "feat(notices): add announcements dashboard card to AtlasGo"
```

---

### Task 13: Flutter FCM tap-to-navigate for announcements

**Files:**
- Modify: `~/bugsaymis-mobile/lib/main.dart`

**Interfaces:**
- Consumes: `pendingNotificationProvider` (existing, `lib/src/features/notifications/fcm_service.dart`) — the push payload's `data['type']` is `'announcement'` (set server-side in Task 5's `$pushData`).

- [ ] **Step 1: Extend the notification-tap listener**

In `main.dart`, inside the existing `ref.listen<Map<String, dynamic>?>(pendingNotificationProvider, ...)` block, add a branch before the final `ref.read(pendingNotificationProvider.notifier).state = null;`:

```dart
    ref.listen<Map<String, dynamic>?>(pendingNotificationProvider, (_, data) {
      if (data == null) return;
      if (data['type'] == 'student_attendance') {
        final studentId = int.tryParse(data['student_id']?.toString() ?? '');
        if (studentId != null) {
          router.go('/attendance', extra: {
            'studentId': studentId,
            'studentName': data['student_name']?.toString() ?? '',
          });
        }
      } else if (data['type'] == 'announcement') {
        // No dedicated announcement-detail screen exists yet — route to
        // whichever dashboard the current role uses, where the unread
        // queue dialog (Task 11) will surface it on load.
        final user = ref.read(authStateProvider).value;
        router.go(user?.isStudent == true ? '/student/home' : '/home');
      }
      ref.read(pendingNotificationProvider.notifier).state = null;
    });
```

- [ ] **Step 2: Run static analysis**

Run: `flutter analyze`
Expected: no new errors/warnings introduced.

- [ ] **Step 3: Manual verification**

Send a test push via the dev `FcmService` (trigger a real announcement publish targeting a student/parent with a valid device token) with the app backgrounded, tap the system notification, confirm the app opens to the correct dashboard.

- [ ] **Step 4: Commit**

```bash
git add lib/main.dart
git commit -m "feat(notices): route to dashboard on announcement push tap"
```

---

### Task 14: Phase 1 verification checkpoint

**Files:** none (manual verification only)

- [ ] **Step 1: Run the full backend notice-related suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices tests/Feature/Mobile tests/Feature/Administration"`
Expected: all PASS.

- [ ] **Step 2: Run the full Flutter test suite**

Run (from `~/bugsaymis-mobile`): `flutter test`
Expected: all PASS, including the pre-existing suite (no regressions from Tasks 10-13).

- [ ] **Step 3: End-to-end manual pass**

As documented in the spec's rollout section — publish one announcement per audience value (`employees`, `students`, `parents`, `all`) from the web Announcements page and confirm: employee bell + web modal for `employees`/`all`; AtlasGo push + in-app dialog + dashboard card for `students`/`parents`/`all`, split correctly by audience (a `students`-only announcement must never reach a parent test account, and vice versa).

- [ ] **Step 4: Commit any fixes found during manual verification, then proceed to Phase 2**

No commit here if nothing needed fixing — this is a checkpoint, not a deliverable.

---

## Phase 2 — Emergency Alert Broadcast (Command Center)

### Task 15: `emergency_alerts` table + model + shared Echo channel

**Files:**
- Create: `database/migrations/2026_08_23_090200_create_emergency_alerts_table.php`
- Create: `app/Models/Sos/EmergencyAlert.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/Sos/EmergencyAlertModelTest.php`

**Interfaces:**
- Produces: `EmergencyAlert` (uses `HasNoticeAcknowledgments` from Task 1), `EmergencyAlert::scopeActive()`, `::scopeVisibleTo(User)`, `::scopeVisibleToAudienceGroup(string)`, `::isResolved(): bool`. The `emergency-alerts` private Echo channel, listenable by any authenticated `User`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\EmergencyAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyAlertModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeAlert(array $overrides = []): EmergencyAlert
    {
        return EmergencyAlert::create(array_merge([
            'title' => 'Test Alert', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_active_scope_excludes_resolved_alerts(): void
    {
        $active = $this->makeAlert();
        $resolved = $this->makeAlert(['status' => 'resolved']);

        $ids = EmergencyAlert::active()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($resolved->id));
    }

    public function test_visible_to_audience_group_matches_all_and_the_named_group(): void
    {
        $this->makeAlert(['audience' => 'employees']);
        $students = $this->makeAlert(['audience' => 'students']);
        $all = $this->makeAlert(['audience' => 'all']);

        $ids = EmergencyAlert::visibleToAudienceGroup('students')->pluck('id');

        $this->assertTrue($ids->contains($students->id));
        $this->assertTrue($ids->contains($all->id));
        $this->assertCount(2, $ids);
    }

    public function test_is_resolved_reflects_status(): void
    {
        $alert = $this->makeAlert();
        $this->assertFalse($alert->isResolved());

        $alert->update(['status' => 'resolved']);
        $this->assertTrue($alert->fresh()->isResolved());
    }

    public function test_acknowledgment_tracking_works_via_the_shared_trait(): void
    {
        $alert = $this->makeAlert();
        $user = User::factory()->create();

        $this->assertFalse($alert->isAcknowledgedBy($user));
        $alert->acknowledgeFor($user);
        $this->assertTrue($alert->fresh()->isAcknowledgedBy($user));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/EmergencyAlertModelTest.php"`
Expected: FAIL — class/table don't exist.

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
        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('severity', 20)->default('warning'); // info | warning | critical
            $table->string('audience', 20)->default('all');     // all | employees | students | parents
            $table->string('status', 20)->default('active');    // active | resolved
            $table->string('source', 20)->default('manual');    // manual | escalated
            $table->foreignId('sos_alert_id')->nullable()->constrained('sos_alerts')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_23_090200_create_emergency_alerts_table.php"`

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models\Sos;

use App\Models\User;
use App\Traits\HasNoticeAcknowledgments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyAlert extends Model
{
    use HasNoticeAcknowledgments;

    protected $fillable = [
        'title', 'message', 'severity', 'audience', 'status', 'source',
        'sos_alert_id', 'created_by', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function sosAlert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Active alerts visible to an employee — matches audience 'all' or 'employees'. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->active()->whereIn('audience', ['all', 'employees']);
    }

    /** Active alerts visible to the given non-employee group (students|parents). */
    public function scopeVisibleToAudienceGroup(Builder $query, string $group): Builder
    {
        return $query->active()->whereIn('audience', ['all', $group]);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
```

- [ ] **Step 5: Register the Echo channel**

In `routes/channels.php`, add after the existing `sos-responders` channel:

```php
// Any authenticated Atlas web user should see an active campus-wide
// emergency broadcast in real time — unlike sos-responders (triage-only
// staff), this channel is intentionally open to every logged-in employee.
Broadcast::channel('emergency-alerts', function ($user) {
    return true;
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/EmergencyAlertModelTest.php"`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_23_090200_create_emergency_alerts_table.php app/Models/Sos/EmergencyAlert.php routes/channels.php tests/Feature/Sos/EmergencyAlertModelTest.php
git commit -m "feat(sos): add EmergencyAlert model and shared broadcast channel"
```

---

### Task 16: `EmergencyAlertBroadcast` + `EmergencyAlertResolved` events

**Files:**
- Create: `app/Events/Sos/EmergencyAlertBroadcast.php`
- Create: `app/Events/Sos/EmergencyAlertResolved.php`
- Test: `tests/Feature/Sos/EmergencyAlertEventsTest.php`

**Interfaces:**
- Produces: two `ShouldBroadcastNow` events on the `emergency-alerts` private channel, `broadcastAs()` = `emergency.alert.broadcast` / `emergency.alert.resolved`. Task 17 (controller) fires these synchronously.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\EmergencyAlertBroadcast;
use App\Events\Sos\EmergencyAlertResolved;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class EmergencyAlertEventsTest extends TestCase
{
    public function test_broadcast_event_shape(): void
    {
        $event = new EmergencyAlertBroadcast(['id' => 1, 'title' => 'Test']);

        $this->assertSame('emergency.alert.broadcast', $event->broadcastAs());
        $this->assertEquals([new PrivateChannel('emergency-alerts')], $event->broadcastOn());
        $this->assertSame(['id' => 1, 'title' => 'Test'], $event->broadcastWith());
    }

    public function test_resolved_event_shape(): void
    {
        $event = new EmergencyAlertResolved(['id' => 1, 'status' => 'resolved']);

        $this->assertSame('emergency.alert.resolved', $event->broadcastAs());
        $this->assertEquals([new PrivateChannel('emergency-alerts')], $event->broadcastOn());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/EmergencyAlertEventsTest.php"`
Expected: FAIL — classes don't exist.

- [ ] **Step 3: Create the events**

```php
<?php

namespace App\Events\Sos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlertBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('emergency-alerts')];
    }

    public function broadcastAs(): string
    {
        return 'emergency.alert.broadcast';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

```php
<?php

namespace App\Events\Sos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlertResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('emergency-alerts')];
    }

    public function broadcastAs(): string
    {
        return 'emergency.alert.resolved';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/EmergencyAlertEventsTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Events/Sos/EmergencyAlertBroadcast.php app/Events/Sos/EmergencyAlertResolved.php tests/Feature/Sos/EmergencyAlertEventsTest.php
git commit -m "feat(sos): add EmergencyAlertBroadcast/Resolved broadcast events"
```

---

### Task 17: `DispatchEmergencyAlertJob` + dedicated `emergency` queue worker

**Files:**
- Create: `app/Jobs/Sos/DispatchEmergencyAlertJob.php`
- Modify: `docker/supervisord-worker.conf`
- Test: `tests/Feature/Sos/DispatchEmergencyAlertJobTest.php`

**Interfaces:**
- Consumes: `NoticeAudienceResolver::resolve()` (Task 3), `FcmService::send()`, `SmsGateService::send()` (existing), `Mail` (Laravel facade).
- Produces: fan-out of push/SMS/email to the resolved audience for a given `EmergencyAlert` id, on a dedicated `emergency` queue.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Jobs\Sos\DispatchEmergencyAlertJob;
use App\Models\Sos\EmergencyAlert;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use App\Services\NoticeAudienceResolver;
use App\Services\StudentAttendance\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchEmergencyAlertJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_on_the_dedicated_emergency_queue(): void
    {
        $alert = EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'critical',
            'audience' => 'employees', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ]);

        DispatchEmergencyAlertJob::dispatch($alert->id);

        \Illuminate\Support\Facades\Queue::assertPushedOn('emergency', DispatchEmergencyAlertJob::class);
    }

    public function test_parents_audience_pushes_and_sms_and_emails_all_channels(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $parent = ParentContact::create([
            'name' => 'Parent', 'email' => 'parent@example.com', 'password' => bcrypt('x'),
            'status' => 'active', 'notify_push' => true, 'notify_sms' => true,
            'fcm_device_token' => 'token-x', 'mobile_phone' => '09171234567',
        ]);
        $alert = EmergencyAlert::create([
            'title' => 'Lockdown', 'message' => 'Stay indoors', 'severity' => 'critical',
            'audience' => 'parents', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')->once()->with('token-x', 'Lockdown', 'Stay indoors', [
            'type' => 'emergency_alert', 'emergency_alert_id' => (string) $alert->id,
            'title' => 'Lockdown', 'message' => 'Stay indoors',
        ])->andReturn(true);

        (new DispatchEmergencyAlertJob($alert->id))->handle(app(NoticeAudienceResolver::class), $fcm);

        Http::assertSentCount(1); // SMS to the parent's mobile_phone
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/DispatchEmergencyAlertJobTest.php"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Implement the job**

```php
<?php

namespace App\Jobs\Sos;

use App\Mail\SosAlertMail;
use App\Models\Sos\EmergencyAlert;
use App\Services\NoticeAudienceResolver;
use App\Services\StudentAttendance\FcmService;
use App\Services\StudentAttendance\SmsGateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class DispatchEmergencyAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    // Single attempt — a retry would double-notify every recipient of a
    // life-safety broadcast.
    public int $tries = 1;

    public function __construct(public int $emergencyAlertId)
    {
        // Never share a queue with 'bulk' — a large announcement fan-out
        // must never delay an emergency broadcast's SMS/push/email delivery.
        $this->onQueue('emergency');
    }

    public function handle(NoticeAudienceResolver $resolver, FcmService $fcm): void
    {
        $alert = EmergencyAlert::find($this->emergencyAlertId);

        if (! $alert) {
            logger()->error('DispatchEmergencyAlertJob: alert missing', ['emergency_alert_id' => $this->emergencyAlertId]);
            return;
        }

        $resolved = $resolver->resolve($alert->audience);
        // title/message are duplicated into the data payload (not just the
        // FCM "notification" fields) because the app's foreground handler
        // (see the emergency-takeover task) reads message.data exclusively,
        // to show the takeover without a second network round-trip.
        $pushData = [
            'type' => 'emergency_alert',
            'emergency_alert_id' => (string) $alert->id,
            'title' => $alert->title,
            'message' => $alert->message,
        ];
        $smsGate = app(SmsGateService::class);

        foreach ($resolved['users'] as $user) {
            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new SosAlertMail($alert, $user));
                } catch (\Throwable $e) {
                    logger()->warning('DispatchEmergencyAlertJob: employee email failed', [
                        'emergency_alert_id' => $alert->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        foreach ($resolved['students'] as $student) {
            $token = $student->credential?->fcm_device_token;
            if ($token) {
                $fcm->send($token, $alert->title, $alert->message, $pushData);
            }
        }

        foreach ($resolved['parents'] as $parent) {
            if ($parent->wantsPushNotification()) {
                $fcm->send($parent->fcm_device_token, $alert->title, $alert->message, $pushData);
            }
            if ($parent->notify_sms && ! empty($parent->mobile_phone)) {
                $smsGate->send($parent->mobile_phone, "PSHS-CRC ALERT: {$alert->title} — {$alert->message}");
            }
        }

        logger()->info('DispatchEmergencyAlertJob: complete', [
            'emergency_alert_id' => $alert->id,
            'audience'           => $alert->audience,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('DispatchEmergencyAlertJob: job FAILED', [
            'emergency_alert_id' => $this->emergencyAlertId,
            'error'              => $e->getMessage(),
        ]);
    }
}
```

Note: `SosAlertMail` currently expects a `SosAlert` in its constructor — check `app/Mail/SosAlertMail.php` before reusing it here. If its constructor type-hints `SosAlert` specifically (not a shared interface), do not reuse it as-is: instead write a small dedicated `App\Mail\EmergencyAlertMail` (same structure — subject/body from `$alert->title`/`$alert->message`, mirroring `SosAlertMail`'s existing Blade view pattern) rather than forcing an incompatible type through it. Read `app/Mail/SosAlertMail.php` first and decide based on what's actually there.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/DispatchEmergencyAlertJobTest.php"`
Expected: PASS (2 tests)

- [ ] **Step 5: Add the dedicated `emergency` queue worker**

In `docker/supervisord-worker.conf`, add a new program block after `queue-worker-bulk`:

```ini
[program:queue-worker-emergency]
; Emergency Alert broadcasts only (->onQueue('emergency')). Deliberately
; separate from both 'default' and 'bulk' so a large announcement fan-out
; (queue-worker-bulk) can never sit in front of a life-safety broadcast's
; push/SMS/email delivery. Short timeout — nothing routed here should run long.
command=php /var/www/artisan queue:work redis --queue=emergency --sleep=1 --tries=1 --timeout=120 --max-jobs=500 --max-time=3600
directory=/var/www
user=www-data
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/var/log/queue-worker-emergency.log
stdout_logfile_maxbytes=10MB
stderr_logfile=/var/log/queue-worker-emergency-error.log
stderr_logfile_maxbytes=5MB
priority=29
```

(`priority=29` — one below `queue-worker-default`'s 30 — so Supervisor starts it first on container boot; Supervisor priority only affects start/stop ordering, not runtime scheduling, but there's no reason not to have the emergency worker up before the others.)

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/Sos/DispatchEmergencyAlertJob.php docker/supervisord-worker.conf tests/Feature/Sos/DispatchEmergencyAlertJobTest.php
git commit -m "feat(sos): add DispatchEmergencyAlertJob on a dedicated queue worker"
```

**Note for deployment:** this change to `docker/supervisord-worker.conf` only takes effect on the next deploy of the `crcmis-prod-worker` ECS service (new container image). Flag this explicitly when this phase ships — an emergency alert created before that service redeploys will still broadcast in-app instantly (Task 18's synchronous event) but its queued push/SMS/email fan-out won't run until the worker picks up the new config.

---

### Task 18: `EmergencyAlertController` — create, escalate-from-SOS, resolve

**Files:**
- Create: `app/Http/Controllers/Sos/EmergencyAlertController.php`
- Modify: `routes/web.php:378-388` (inside the existing `sos.` route group)
- Test: `tests/Feature/Sos/EmergencyAlertControllerTest.php`

**Interfaces:**
- Consumes: `EmergencyAlertBroadcast`/`EmergencyAlertResolved` events (Task 16), `DispatchEmergencyAlertJob` (Task 17).
- Produces: `POST sos.broadcast.store`, `POST sos.broadcast.from-sos`, `POST sos.broadcast.resolve`, `GET sos.broadcast.index` — Task 19 (Command Center Vue) calls all four by route name.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\EmergencyAlert;
use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmergencyAlertControllerTest extends TestCase
{
    use RefreshDatabase;

    private function responderUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'sos.respond']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        return $user;
    }

    public function test_standalone_create_broadcasts_immediately_and_queues_dispatch(): void
    {
        Event::fake([\App\Events\Sos\EmergencyAlertBroadcast::class]);
        Bus::fake([\App\Jobs\Sos\DispatchEmergencyAlertJob::class]);

        $response = $this->actingAs($this->responderUser())->postJson(route('sos.broadcast.store'), [
            'title' => 'Weather Advisory', 'message' => 'Classes suspended.',
            'severity' => 'warning', 'audience' => 'all',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('emergency_alerts', [
            'title' => 'Weather Advisory', 'source' => 'manual', 'sos_alert_id' => null, 'status' => 'active',
        ]);
        Event::assertDispatched(\App\Events\Sos\EmergencyAlertBroadcast::class);
        Bus::assertDispatched(\App\Jobs\Sos\DispatchEmergencyAlertJob::class);
    }

    public function test_escalate_from_sos_sets_source_and_link(): void
    {
        Event::fake([\App\Events\Sos\EmergencyAlertBroadcast::class]);
        Bus::fake([\App\Jobs\Sos\DispatchEmergencyAlertJob::class]);

        $sosAlert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'security', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        $response = $this->actingAs($this->responderUser())
            ->postJson(route('sos.broadcast.from-sos', $sosAlert), [
                'title' => 'Security Incident', 'message' => 'Please remain indoors.',
                'severity' => 'critical', 'audience' => 'all',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('emergency_alerts', [
            'source' => 'escalated', 'sos_alert_id' => $sosAlert->id,
        ]);
    }

    public function test_resolve_marks_status_and_broadcasts_follow_up(): void
    {
        Event::fake([\App\Events\Sos\EmergencyAlertResolved::class]);

        $responder = $this->responderUser();
        $alert = EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $responder->id,
        ]);

        $response = $this->actingAs($responder)->postJson(route('sos.broadcast.resolve', $alert));

        $response->assertOk();
        $this->assertSame('resolved', $alert->fresh()->status);
        Event::assertDispatched(\App\Events\Sos\EmergencyAlertResolved::class);
    }

    public function test_broadcast_requires_sos_respond_permission(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('sos.broadcast.store'), [
            'title' => 'X', 'message' => 'Y', 'severity' => 'info', 'audience' => 'all',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/EmergencyAlertControllerTest.php"`
Expected: FAIL — routes/controller don't exist.

- [ ] **Step 3: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Sos;

use App\Events\Sos\EmergencyAlertBroadcast;
use App\Events\Sos\EmergencyAlertResolved;
use App\Http\Controllers\Controller;
use App\Jobs\Sos\DispatchEmergencyAlertJob;
use App\Models\Sos\EmergencyAlert;
use App\Models\Sos\SosAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmergencyAlertController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            EmergencyAlert::orderByDesc('created_at')->limit(50)->get()->map(fn ($a) => $this->serialize($a))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $alert = $this->create($this->validated($request), sosAlertId: null);

        return response()->json($this->serialize($alert), 201);
    }

    public function storeFromSos(Request $request, SosAlert $alert): JsonResponse
    {
        $emergencyAlert = $this->create($this->validated($request), sosAlertId: $alert->id);

        return response()->json($this->serialize($emergencyAlert), 201);
    }

    public function resolve(Request $request, EmergencyAlert $emergencyAlert): JsonResponse
    {
        if ($emergencyAlert->isResolved()) {
            return response()->json(['message' => 'This alert is already resolved.'], 422);
        }

        $emergencyAlert->update([
            'status'      => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        event(new EmergencyAlertResolved($this->serialize($emergencyAlert->fresh())));

        return response()->json($this->serialize($emergencyAlert->fresh()));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'    => 'required|string|max:255',
            'message'  => 'required|string|max:5000',
            'severity' => 'required|in:info,warning,critical',
            'audience' => 'required|in:all,employees,students,parents',
        ]);
    }

    private function create(array $data, ?int $sosAlertId): EmergencyAlert
    {
        $alert = EmergencyAlert::create([
            ...$data,
            'source'       => $sosAlertId ? 'escalated' : 'manual',
            'sos_alert_id' => $sosAlertId,
            'created_by'   => auth()->id(),
            'status'       => 'active',
        ]);

        event(new EmergencyAlertBroadcast($this->serialize($alert)));
        DispatchEmergencyAlertJob::dispatch($alert->id);

        return $alert;
    }

    private function serialize(EmergencyAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'title'        => $alert->title,
            'message'      => $alert->message,
            'severity'     => $alert->severity,
            'audience'     => $alert->audience,
            'status'       => $alert->status,
            'source'       => $alert->source,
            'sos_alert_id' => $alert->sos_alert_id,
            'created_at'   => $alert->created_at->toIso8601String(),
            'resolved_at'  => $alert->resolved_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, inside the existing `Route::middleware('permission:sos.respond')->group(function () { ... })` block (~lines 381-388), add:

```php
            Route::get('/broadcast/history', [\App\Http\Controllers\Sos\EmergencyAlertController::class, 'index'])->name('broadcast.index');
            Route::post('/broadcast', [\App\Http\Controllers\Sos\EmergencyAlertController::class, 'store'])->name('broadcast.store');
            Route::post('/{alert}/broadcast', [\App\Http\Controllers\Sos\EmergencyAlertController::class, 'storeFromSos'])->name('broadcast.from-sos')->whereNumber('alert');
            Route::post('/broadcast/{emergencyAlert}/resolve', [\App\Http\Controllers\Sos\EmergencyAlertController::class, 'resolve'])->name('broadcast.resolve')->whereNumber('emergencyAlert');
```

(These sit under the existing `Route::prefix('sos')->name('sos.')` wrapper, so the resulting route names are `sos.broadcast.index`, `sos.broadcast.store`, `sos.broadcast.from-sos`, `sos.broadcast.resolve` — matching the test above.)

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/EmergencyAlertControllerTest.php"`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Sos/EmergencyAlertController.php routes/web.php tests/Feature/Sos/EmergencyAlertControllerTest.php
git commit -m "feat(sos): add EmergencyAlertController — create, escalate, resolve"
```

---

### Task 19: Extend web + mobile `NoticeController` to include emergency alerts

**Files:**
- Modify: `app/Http/Controllers/NoticeController.php` (Task 6)
- Modify: `app/Http/Controllers/StudentAttendance/Api/NoticeController.php` (Task 9)
- Test: `tests/Feature/Notices/NoticeControllerEmergencyAlertTest.php`

**Interfaces:**
- Consumes: `EmergencyAlert::scopeVisibleTo()`/`::scopeVisibleToAudienceGroup()` (Task 15).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Notices;

use App\Models\Sos\EmergencyAlert;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeControllerEmergencyAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_pending_includes_active_unacknowledged_emergency_alerts(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        $alert = EmergencyAlert::create([
            'title' => 'Lockdown', 'message' => 'Stay indoors', 'severity' => 'critical',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/notices/pending');

        $titles = collect($response->json('emergency_alerts'))->pluck('title');
        $this->assertTrue($titles->contains('Lockdown'));
    }

    public function test_web_pending_excludes_resolved_emergency_alerts(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        EmergencyAlert::create([
            'title' => 'Resolved One', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'resolved', 'source' => 'manual', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/notices/pending');

        $this->assertEmpty($response->json('emergency_alerts'));
    }

    public function test_mobile_parent_pending_includes_matching_audience_emergency_alerts(): void
    {
        $parent = ParentContact::create([
            'name' => 'P', 'email' => 'p@example.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $token = $parent->createToken('device', ['mobile'])->plainTextToken;
        EmergencyAlert::create([
            'title' => 'Parent Alert', 'message' => 'Body', 'severity' => 'critical',
            'audience' => 'parents', 'status' => 'active', 'source' => 'manual', 'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/pending');

        $titles = collect($response->json('emergency_alerts'))->pluck('title');
        $this->assertTrue($titles->contains('Parent Alert'));
    }

    public function test_acknowledge_endpoint_accepts_emergency_alert_type(): void
    {
        $user = User::factory()->create();
        $alert = EmergencyAlert::create([
            'title' => 'X', 'message' => 'Y', 'severity' => 'info',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson("/notices/emergency-alert/{$alert->id}/acknowledge")
            ->assertOk();

        $this->assertTrue($alert->fresh()->isAcknowledgedBy($user));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NoticeControllerEmergencyAlertTest.php"`
Expected: FAIL — `emergency_alerts` is hardcoded empty, and `acknowledge()` doesn't handle `emergency-alert`.

- [ ] **Step 3: Update the web controller**

In `app/Http/Controllers/NoticeController.php`, replace `pending()` and the `match` in `acknowledge()`:

```php
    public function pending(): JsonResponse
    {
        $user = Auth::user();

        $announcements = Announcement::visibleTo($user)
            ->get()
            ->reject(fn (Announcement $a) => $a->isAcknowledgedBy($user))
            ->map(fn (Announcement $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'poster_path'  => $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->values();

        $emergencyAlerts = EmergencyAlert::visibleTo($user)
            ->get()
            ->reject(fn (EmergencyAlert $a) => $a->isAcknowledgedBy($user))
            ->map(fn (EmergencyAlert $a) => [
                'id'       => $a->id,
                'title'    => $a->title,
                'message'  => $a->message,
                'severity' => $a->severity,
            ])
            ->values();

        return response()->json([
            'announcements'    => $announcements,
            'emergency_alerts' => $emergencyAlerts,
        ]);
    }

    public function acknowledge(Request $request, string $type, int $id): JsonResponse
    {
        $user = Auth::user();

        $notice = match ($type) {
            'announcement'     => Announcement::findOrFail($id),
            'emergency-alert'  => EmergencyAlert::findOrFail($id),
            default            => abort(404),
        };

        $notice->acknowledgeFor($user);

        return response()->json(['message' => 'Acknowledged.']);
    }
```

Add `use App\Models\Sos\EmergencyAlert;` to the top of the file.

- [ ] **Step 4: Update the mobile controller identically**

Apply the same shape to `app/Http/Controllers/StudentAttendance/Api/NoticeController.php`, using `EmergencyAlert::visibleToAudienceGroup($group)` in place of `visibleTo($user)`, and the same `match` extension in `acknowledge()`. Add `use App\Models\Sos\EmergencyAlert;`.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices/NoticeControllerEmergencyAlertTest.php"`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full Notices + Sos + Mobile suites to confirm no regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Notices tests/Feature/Sos tests/Feature/Mobile"`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/NoticeController.php app/Http/Controllers/StudentAttendance/Api/NoticeController.php tests/Feature/Notices/NoticeControllerEmergencyAlertTest.php
git commit -m "feat(notices): include emergency alerts in pending-notices endpoints"
```

---

### Task 20: Command Center Vue — broadcast + escalate + resolve UI

**Files:**
- Modify: `resources/js/Pages/Sos/CommandCenter.vue`
- Modify: `app/Http/Controllers/Sos/SosAlertController.php:44-53` (pass `emergencyAlerts` prop)

**Interfaces:**
- Consumes: `sos.broadcast.store`, `sos.broadcast.from-sos`, `sos.broadcast.resolve`, `sos.broadcast.index` route names (Task 18).

- [ ] **Step 1: Pass emergency alert history into the page**

In `app/Http/Controllers/Sos/SosAlertController.php`, update `index()`:

```php
    public function index()
    {
        $alerts = SosAlert::with(['events' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('triggered_at')
            ->limit(100)
            ->get()
            ->map(fn (SosAlert $alert) => $this->serialize($alert));

        $emergencyAlerts = \App\Models\Sos\EmergencyAlert::orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'message' => $a->message,
                'severity' => $a->severity, 'audience' => $a->audience, 'status' => $a->status,
                'source' => $a->source, 'sos_alert_id' => $a->sos_alert_id,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return Inertia::render('Sos/CommandCenter', ['alerts' => $alerts, 'emergencyAlerts' => $emergencyAlerts]);
    }
```

- [ ] **Step 2: Add the broadcast UI to `CommandCenter.vue`**

Update the `<script setup>` block — add the new prop, form state, and actions:

```js
const props = defineProps({ alerts: Array, emergencyAlerts: Array })

const emergencyAlerts = ref([...props.emergencyAlerts])
const showBroadcastForm = ref(false)
const broadcastForm = ref({ title: '', message: '', severity: 'warning', audience: 'all' })

function openStandaloneBroadcast() {
  broadcastForm.value = { title: '', message: '', severity: 'warning', audience: 'all' }
  showBroadcastForm.value = 'standalone'
}

function openEscalateBroadcast(alert) {
  const severity = ['security', 'fire_disaster'].includes(alert.alert_type) ? 'critical' : 'warning'
  broadcastForm.value = {
    title: `Emergency: ${alert.alert_type.replace('_', ' ')}`,
    message: `An emergency has been reported on campus (${alert.alert_type.replace('_', ' ')}). Please follow safety instructions from campus staff.`,
    severity,
    audience: 'all',
  }
  showBroadcastForm.value = alert.id
}

async function submitBroadcast() {
  const url = showBroadcastForm.value === 'standalone'
    ? route('sos.broadcast.store')
    : route('sos.broadcast.from-sos', showBroadcastForm.value)
  const { data } = await axios.post(url, broadcastForm.value)
  emergencyAlerts.value.unshift(data)
  showBroadcastForm.value = false
}

async function resolveEmergencyAlert(alert) {
  const { data } = await axios.post(route('sos.broadcast.resolve', alert.id))
  const idx = emergencyAlerts.value.findIndex(a => a.id === alert.id)
  if (idx !== -1) emergencyAlerts.value[idx] = data
}

function subscribeEmergencyChannel() {
  if (!window.Echo) return
  window.Echo.private('emergency-alerts')
    .listen('.emergency.alert.broadcast', (payload) => emergencyAlerts.value.unshift(payload))
    .listen('.emergency.alert.resolved', (payload) => {
      const idx = emergencyAlerts.value.findIndex(a => a.id === payload.id)
      if (idx !== -1) emergencyAlerts.value[idx] = payload
    })
}
```

Call `subscribeEmergencyChannel()` inside the existing `onMounted(subscribe)` (rename or add alongside it — this file's existing `onMounted(subscribe)` only wires `sos-responders`; add a second call: `onMounted(() => { subscribe(); subscribeEmergencyChannel() })`).

Add to the template: a "New Emergency Alert" button near the "Active Alerts" heading, a "Broadcast Public Alert" button inside the selected-alert detail panel (next to the existing Acknowledge/Verify buttons), a form modal (title/message/severity-select/audience-select + Send/Cancel) shown when `showBroadcastForm` is truthy, and an "Emergency Alerts" history section listing `emergencyAlerts` with a "Resolve" button on `status === 'active'` rows. Match the file's existing Tailwind conventions exactly (red accents for anything emergency-related, per this project's color-palette convention of reserving red for genuine status) — copy the existing button/card classes already used elsewhere in this same file rather than inventing new ones.

- [ ] **Step 3: Manual verification**

As a user with `sos.respond`: (a) create a standalone alert, confirm it appears immediately via the Echo listener without a page reload, confirm the takeover modal (Task 21, once wired) fires on another logged-in session; (b) select an active SOS alert, click "Broadcast Public Alert", confirm the form prefills correctly, submit, confirm `sos_alert_id` is set; (c) resolve an active alert, confirm its status flips and a "resolved" follow-up event fires.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Sos/SosAlertController.php resources/js/Pages/Sos/CommandCenter.vue
git commit -m "feat(sos): add emergency broadcast/escalate/resolve UI to Command Center"
```

---

### Task 21: Web emergency takeover — wire the live channel into `NoticeQueueModal`

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: `NoticeQueueModal`'s exposed `receiveEmergencyAlert()` (Task 7), the `emergency-alerts` Echo channel (Task 15/16).

- [ ] **Step 1: Add the Echo listener**

In `resources/js/Layouts/AdminLayout.vue`, add a new setup function alongside the existing `setupChatNotifications()`:

```js
function setupEmergencyAlertListener() {
  if (!window.Echo) return

  window.Echo.private('emergency-alerts')
    .listen('.emergency.alert.broadcast', (payload) => {
      noticeQueueModal.value?.receiveEmergencyAlert(payload)
    })
}
```

Call it wherever `setupChatNotifications()` is currently called from (find that call site in the same file's `onMounted` and add `setupEmergencyAlertListener()` next to it).

- [ ] **Step 2: Manual verification**

With two browser sessions logged in as different employees: from Session A's Command Center, create a standalone emergency alert; confirm Session B — sitting on any other authenticated page, not Command Center — gets the takeover modal within a second or two, with no page reload.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat(sos): wire real-time emergency takeover into the web layout"
```

---

### Task 22: Flutter emergency takeover — FCM foreground handler + overlay

**Files:**
- Modify: `~/bugsaymis-mobile/lib/src/features/notifications/fcm_service.dart`
- Modify: `~/bugsaymis-mobile/lib/main.dart`
- Test: `~/bugsaymis-mobile/test/features/notifications/fcm_service_emergency_test.dart`

**Interfaces:**
- Produces: `pendingEmergencyAlertProvider` (a `StateProvider<Map<String, dynamic>?>`, mirroring the existing `pendingNotificationProvider` pattern) — set the instant an FCM message with `data['type'] == 'emergency_alert'` arrives in the foreground, not just on tap.

- [ ] **Step 1: Write the failing test**

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:atlasgo/src/features/notifications/fcm_service.dart';

void main() {
  test('pendingEmergencyAlertProvider starts null and can be set directly', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);

    expect(container.read(pendingEmergencyAlertProvider), isNull);

    container.read(pendingEmergencyAlertProvider.notifier).state = {
      'type': 'emergency_alert', 'title': 'Lockdown', 'message': 'Stay indoors',
    };

    expect(container.read(pendingEmergencyAlertProvider)?['title'], 'Lockdown');
  });
}
```

(This test only exercises the provider's plumbing directly — `FirebaseMessaging.onMessage`'s actual stream cannot be driven from a unit test without the Firebase platform channel, matching how the existing `FcmService` itself has no direct test coverage; the behavior this enables is verified manually in Step 4.)

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/features/notifications/fcm_service_emergency_test.dart`
Expected: FAIL — `pendingEmergencyAlertProvider` doesn't exist.

- [ ] **Step 3: Add the provider and wire the foreground handler**

In `lib/src/features/notifications/fcm_service.dart`, add near the existing `pendingNotificationProvider`:

```dart
/// Set the instant an emergency-alert push arrives in the foreground —
/// unlike pendingNotificationProvider (which only fires on a notification
/// tap), this must interrupt immediately, matching the web takeover's
/// real-time behavior. AtlasGo has no persistent socket connection, so
/// this push IS the mobile real-time channel.
final pendingEmergencyAlertProvider =
    StateProvider<Map<String, dynamic>?>((ref) => null);
```

In `FcmService.initialize()`, update the `FirebaseMessaging.onMessage.listen` callback to branch on `message.data['type']`:

```dart
    // Show notification when app is in foreground
    FirebaseMessaging.onMessage.listen((message) {
      if (message.data['type'] == 'emergency_alert') {
        _ref.read(pendingEmergencyAlertProvider.notifier).state = message.data;
        return; // the takeover overlay handles this — no local notification needed
      }

      final notification = message.notification;
      if (notification == null) return;

      _localNotifications.show(
        notification.hashCode,
        notification.title,
        notification.body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            _androidChannel.id,
            _androidChannel.name,
            channelDescription: _androidChannel.description,
            importance: Importance.high,
            priority: Priority.high,
            icon: '@mipmap/ic_launcher',
          ),
          iOS: const DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
          ),
        ),
      );
    });
```

- [ ] **Step 4: Add the takeover overlay to `main.dart`**

In `lib/main.dart`, import the emergency provider and the dialog widget:

```dart
import 'src/features/notices/notice_queue_dialog.dart';
```

Add a listener alongside the existing `pendingNotificationProvider` listener in `AtlasGoApp.build()`:

```dart
    // Emergency alert arrived while the app is foregrounded — interrupt
    // immediately via a full-screen takeover, don't wait for a tap.
    ref.listen<Map<String, dynamic>?>(pendingEmergencyAlertProvider, (_, data) {
      if (data == null) return;
      final navigatorContext = router.routerDelegate.navigatorKey.currentContext;
      if (navigatorContext != null) {
        showDialog<void>(
          context: navigatorContext,
          barrierDismissible: false,
          builder: (dialogContext) => PopScope(
            canPop: false,
            child: NoticeQueueDialog(
              item: NoticeItem(
                id: int.tryParse(data['emergency_alert_id']?.toString() ?? '') ?? 0,
                title: data['title']?.toString() ?? 'Emergency Alert',
                body: data['body']?.toString() ?? '',
                kind: 'emergency-alert',
              ),
              position: '',
              showPosition: false,
              onAcknowledge: () async {
                Navigator.of(dialogContext).pop();
              },
            ),
          ),
        );
      }
      ref.read(pendingEmergencyAlertProvider.notifier).state = null;
    });
```

Add the matching import for `NoticeItem`:

```dart
import 'src/features/notices/notices_provider.dart';
```

Note: the emergency branch above reads `title`/`message` from `message.data`, not from `message.notification` — Task 17's `DispatchEmergencyAlertJob` already puts both into `$pushData` (the data payload) for exactly this reason, so no server-side change is needed here.

- [ ] **Step 5: Run test to verify it passes**

Run: `flutter test test/features/notifications/fcm_service_emergency_test.dart`
Expected: PASS

- [ ] **Step 6: Run the full Flutter suite**

Run: `flutter test`
Expected: all PASS, no regressions.

- [ ] **Step 7: Manual verification**

With the AtlasGo app foregrounded on a real device/simulator, dev backend running, and a valid FCM token registered: create a standalone emergency alert from Command Center targeting `students` or `parents`. Confirm the takeover dialog appears within a few seconds without the user touching a system notification, "Acknowledge" dismisses it, and it does not reappear on next app open (since Task 19's `pending` endpoint will have already been acknowledged via the `Acknowledge` tap — verify this actually calls the acknowledge endpoint; if the quick-dismiss handler above only pops the dialog without calling `acknowledgeNotice()`, fix it to call `acknowledgeNotice(ref.read(apiClientProvider), item)` before popping, matching Task 11's pattern).

- [ ] **Step 8: Commit**

```bash
git add lib/src/features/notifications/fcm_service.dart lib/main.dart test/features/notifications/fcm_service_emergency_test.dart
git commit -m "feat(sos): show emergency alert takeover on FCM foreground receipt"
```

---

### Task 23: Phase 2 verification checkpoint + full regression pass

**Files:** none (manual verification only)

- [ ] **Step 1: Run the full backend suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: all PASS — this is the full project suite, confirming Phase 2 introduced no regressions anywhere (SOS, Announcements, or otherwise).

- [ ] **Step 2: Run the full Flutter suite**

Run (from `~/bugsaymis-mobile`): `flutter test`
Expected: all PASS.

- [ ] **Step 3: End-to-end manual pass — standalone broadcast**

From Command Center, create a standalone `all`-audience `critical` alert. Confirm: web takeover fires on a second logged-in employee session within seconds (Task 21); AtlasGo takeover fires on a foregrounded test student/parent session (Task 22); `emergency_alerts` and `sos_notification_logs`-equivalent delivery (check `logger()` output / dev mail log / SMS gate fake, since no real gateway exists in dev) show attempted delivery to all three recipient types.

- [ ] **Step 4: End-to-end manual pass — escalate from SOS**

Trigger a test SOS alert (student or staff), acknowledge it in Command Center, click "Broadcast Public Alert", confirm the prefilled form, submit, confirm the resulting `emergency_alerts` row has `source=escalated` and the correct `sos_alert_id`, and confirm delivery as in Step 3.

- [ ] **Step 5: End-to-end manual pass — resolve**

Resolve the alert created in Step 3 or 4. Confirm its Command Center row flips to resolved/read-only, and that a "resolved" follow-up notice actually reaches a session that still had the original takeover open (Echo `.emergency.alert.resolved` listener) — this specific case has no automated test in this plan; verify it manually with two browser tabs.

- [ ] **Step 6: Deployment note — confirm the worker config landed**

After this phase deploys, confirm the `crcmis-prod-worker` ECS service actually picked up the new `queue-worker-emergency` Supervisor program (Task 17) — check the running task's Supervisor process list (via ECS exec) shows `queue-worker-emergency` alongside `queue-worker-default`/`queue-worker-bulk`, not just that the web/blue-green service redeployed. A missed worker-service redeploy would mean emergency alerts broadcast in-app instantly (the synchronous Echo event still fires from the web container) but silently never send push/SMS/email.

- [ ] **Step 7: No commit** — this is a verification checkpoint. If any manual pass in Steps 3-6 surfaces a real bug, fix it as its own small commit before considering this plan complete.
