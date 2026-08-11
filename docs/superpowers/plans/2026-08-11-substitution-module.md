# Substitution Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an employee going on approved Leave/Travel nominate a substitute, have that nomination approved by their Division Chief/AUH, and then let the approved substitute temporarily "Act As" the original user via a full session identity swap — reusing all existing permission and data-scoping code unchanged.

**Architecture:** Two new tables (`substitutions` = the grant, `acting_as_sessions` = usage audit), a `SubstitutionService` for the nomination/approval/revocation lifecycle, an `ActingAsService` + global `EnsureActingAsWindowValid` middleware for the session-swap mechanics, and a small `SubstitutionController`/`ActingAsController` pair exposed under the existing `hr.*` route group. Frontend adds a `Substitutions` index page, a persistent "Acting As" banner in `AdminLayout.vue`, and an "Assign Substitute" panel on the Leave/Travel Show pages.

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit (not Pest) with `RefreshDatabase`, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-11-substitution-module-design.md`.
- Migration filenames: `YYYY_MM_DD_HHMMSS_description.php`, every migration has a `down()`.
- Controllers return `Inertia::render(...)`, never Blade. Redirect after mutation via `back()->with('success', ...)` / `back()->with('error', ...)`.
- Authorization: SuperAdmin (`Administrator` role) bypasses all checks via `User::isSuperAdmin()`. Route-level `permission:` middleware for baseline gating; fine-grained identity checks (e.g. "only the resolved approver") done inline in the controller via `abort_if`/`abort_unless`, mirroring `LeaveApplicationController::approve()`.
- **Deliberate simplification vs. the spec's lifecycle diagram:** `Substitution.status` has 4 values — `pending_approval`, `approved`, `rejected`, `revoked`. The spec's `active`/`ended` states are not stored; they're derived at read-time via `Substitution::isWithinWindow()` / `scopeCurrent()`. This avoids a background scheduler to flip status at `start_date`/`end_date` while preserving the same observable behavior (a grant outside its date window can never be used to act-as, enforced by `ActingAsService::start()` and the `EnsureActingAsWindowValid` middleware on every request).
- **Scope note:** the cascade-on-cancellation requirement is wired into `LeaveApplicationController::cancel()` only. There is no analogous "cancel an approved travel" endpoint in the current codebase (`TravelController` has no `cancel()` method) — `SubstitutionService::revokeForCancelledAbsence()` is written generically so a future Travel cancellation flow can call it, but no such call site exists yet.
- Never use `new DateTime()` with Eloquent date-cast attributes — use `Carbon::parse($value)->format('Y-m-d')`.
- Never use `git add -A` / `git add .`; stage files by name.

---

### Task 1: Migrations — `substitutions` and `acting_as_sessions`

**Files:**
- Create: `database/migrations/2026_08_11_000001_create_substitutions_table.php`
- Create: `database/migrations/2026_08_11_000002_create_acting_as_sessions_table.php`

**Interfaces:**
- Produces: `substitutions` table (columns below) and `acting_as_sessions` table, consumed by Task 2's Eloquent models.

- [ ] **Step 1: Write `create_substitutions_table` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('original_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The employee on leave/travel being substituted for');

            $table->foreignId('substitute_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The employee covering');

            $table->morphs('absentable'); // absentable_type, absentable_id — LeaveApplication or TravelRequest

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status', 30)->default('pending_approval');
            // pending_approval | approved | rejected | revoked

            $table->foreignId('nominated_by')->constrained('users')->cascadeOnDelete();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['original_user_id', 'status'], 'idx_sub_original_status');
            $table->index(['substitute_user_id', 'status'], 'idx_sub_substitute_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitutions');
    }
};
```

- [ ] **Step 2: Write `create_acting_as_sessions_table` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acting_as_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('substitution_id')
                ->constrained('substitutions')
                ->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 20)->nullable();
            // manual | expired | revoked | logout

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('substitution_id');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acting_as_sessions');
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_11_000001_create_substitutions_table.php --path=database/migrations/2026_08_11_000002_create_acting_as_sessions_table.php"`
Expected: both migrations run without error; `substitutions` and `acting_as_sessions` tables exist.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_11_000001_create_substitutions_table.php database/migrations/2026_08_11_000002_create_acting_as_sessions_table.php
git commit -m "feat(substitution): add substitutions and acting_as_sessions tables"
```

---

### Task 2: `Substitution` and `ActingAsSession` models + factories

**Files:**
- Create: `app/Models/HR/Substitution.php`
- Create: `app/Models/HR/ActingAsSession.php`
- Create: `database/factories/HR/SubstitutionFactory.php`
- Test: `tests/Feature/HR/SubstitutionModelTest.php`

**Interfaces:**
- Consumes: `substitutions`/`acting_as_sessions` tables (Task 1).
- Produces: `Substitution::STATUSES` array; `Substitution::isWithinWindow(?Carbon $date = null): bool`; scopes `scopeCurrent()`, `scopeApprovedOrPending()`; relations `originalUser()`, `substitute()`, `nominator()`, `approver()`, `revoker()`, `absentable()`, `actingAsSessions()`. `ActingAsSession::scopeOpen()`, relation `substitution()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstitutionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_within_window_true_when_today_between_start_and_end(): void
    {
        $original = User::factory()->create();
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);

        $substitution = Substitution::create([
            'original_user_id' => $original->id,
            'substitute_user_id' => $substitute->id,
            'absentable_type' => LeaveApplication::class,
            'absentable_id' => $leave->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
            'nominated_by' => $original->id,
        ]);

        $this->assertTrue($substitution->isWithinWindow());
        $this->assertSame($original->id, $substitution->originalUser->id);
        $this->assertSame($substitute->id, $substitution->substitute->id);
        $this->assertInstanceOf(LeaveApplication::class, $substitution->absentable);
    }

    public function test_is_within_window_false_when_outside_dates(): void
    {
        $substitution = Substitution::factory()->create([
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertFalse($substitution->isWithinWindow());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionModelTest.php"`
Expected: FAIL — class `App\Models\HR\Substitution` not found.

- [ ] **Step 3: Write `Substitution` model**

```php
<?php

namespace App\Models\HR;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Substitution extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'substitutions';

    public const STATUSES = [
        'pending_approval' => 'Pending Approval',
        'approved'         => 'Approved',
        'rejected'         => 'Rejected',
        'revoked'          => 'Revoked',
    ];

    protected $fillable = [
        'original_user_id',
        'substitute_user_id',
        'absentable_type',
        'absentable_id',
        'start_date',
        'end_date',
        'status',
        'nominated_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'revoked_by',
        'revoked_at',
        'revocation_reason',
        'notes',
    ];

    protected $casts = [
        'start_date'  => 'date:Y-m-d',
        'end_date'    => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function absentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actingAsSessions(): HasMany
    {
        return $this->hasMany(ActingAsSession::class);
    }

    // ── Query Scopes ───────────────────────────────────────────────────────

    /** Grants that are still awaiting or hold approval (not rejected/revoked). */
    public function scopeApprovedOrPending($query)
    {
        return $query->whereIn('status', ['pending_approval', 'approved']);
    }

    /** Approved grants whose date window covers a given date (defaults to today). */
    public function scopeCurrent($query, ?string $date = null)
    {
        $date ??= now()->toDateString();

        return $query->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isWithinWindow(?Carbon $date = null): bool
    {
        $date ??= now();

        return $this->status === 'approved'
            && $date->toDateString() >= $this->start_date->toDateString()
            && $date->toDateString() <= $this->end_date->toDateString();
    }
}
```

- [ ] **Step 4: Write `ActingAsSession` model**

```php
<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActingAsSession extends Model
{
    protected $table = 'acting_as_sessions';

    protected $fillable = [
        'substitution_id',
        'started_at',
        'ended_at',
        'ended_reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function substitution(): BelongsTo
    {
        return $this->belongsTo(Substitution::class);
    }

    /** Sessions that have not yet been closed. */
    public function scopeOpen($query)
    {
        return $query->whereNull('ended_at');
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories\HR;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HR\Substitution>
 */
class SubstitutionFactory extends Factory
{
    protected $model = Substitution::class;

    public function definition(): array
    {
        $original = User::factory()->create();
        $leaveType = LeaveType::firstOrCreate(
            ['code' => 'VL'],
            [
                'name' => 'Vacation Leave', 'days_per_year' => 15,
                'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
                'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
            ]
        );
        $leave = LeaveApplication::create([
            'user_id' => $original->id,
            'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [],
            'days_applied' => 3,
            'status' => 'approved',
        ]);

        return [
            'original_user_id' => $original->id,
            'substitute_user_id' => User::factory(),
            'absentable_type' => LeaveApplication::class,
            'absentable_id' => $leave->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending_approval',
            'nominated_by' => $original->id,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionModelTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models/HR/Substitution.php app/Models/HR/ActingAsSession.php database/factories/HR/SubstitutionFactory.php tests/Feature/HR/SubstitutionModelTest.php
git commit -m "feat(substitution): add Substitution and ActingAsSession models"
```

---

### Task 3: `SubstitutionService::nominate()` with validation rules

**Files:**
- Create: `app/Services/HR/SubstitutionService.php`
- Test: `tests/Feature/HR/SubstitutionNominationTest.php`

**Interfaces:**
- Consumes: `Substitution` model (Task 2), `App\Services\PerformanceManagement\IPCRWorkflowService::leaveRecommenderFor(User $employee): ?User`, `App\Models\Division` (`division_chief_id` column).
- Produces: `SubstitutionService::nominate(User $originalUser, User $substituteUser, Model $absentable, ?string $notes = null): Substitution` — throws `\Illuminate\Validation\ValidationException` (via `validator(...)->validate()`-style helper, see step 3) on any rule violation. `SubstitutionService::resolveApprover(User $originalUser): ?User` — consumed by Task 4 (approve/reject) and Task 7 (controller "For My Approval" tab).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\Role;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubstitutionNominationTest extends TestCase
{
    use RefreshDatabase;

    private SubstitutionService $service;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubstitutionService::class);
        $this->leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
    }

    private function approvedLeave(User $user, array $overrides = []): LeaveApplication
    {
        return LeaveApplication::create(array_merge([
            'user_id' => $user->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [],
            'days_applied' => 3,
            'status' => 'approved',
        ], $overrides));
    }

    public function test_nominate_creates_pending_approval_substitution(): void
    {
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD', 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => User::factory()->create()->id]);
        $substitute = User::factory()->create();
        $leave = $this->approvedLeave($original);

        $substitution = $this->service->nominate($original, $substitute, $leave);

        $this->assertSame('pending_approval', $substitution->status);
        $this->assertSame($original->id, $substitution->original_user_id);
        $this->assertSame($substitute->id, $substitution->substitute_user_id);
        $this->assertSame($leave->date_from->toDateString(), $substitution->start_date->toDateString());
        $this->assertSame($leave->date_to->toDateString(), $substitution->end_date->toDateString());
    }

    public function test_nominate_blocks_superadmin_original_user(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'Administrator'])->id);
        $substitute = User::factory()->create();
        $leave = $this->approvedLeave($admin);

        $this->expectException(ValidationException::class);
        $this->service->nominate($admin, $substitute, $leave);
    }

    public function test_nominate_blocks_self_substitution(): void
    {
        $original = User::factory()->create();
        $leave = $this->approvedLeave($original);

        $this->expectException(ValidationException::class);
        $this->service->nominate($original, $original, $leave);
    }

    public function test_nominate_blocks_substitute_with_overlapping_own_leave(): void
    {
        $original = User::factory()->create();
        $substitute = User::factory()->create();
        $this->approvedLeave($substitute, [
            'date_from' => now()->addDays(6)->toDateString(),
            'date_to' => now()->addDays(6)->toDateString(),
        ]);
        $leave = $this->approvedLeave($original);

        $this->expectException(ValidationException::class);
        $this->service->nominate($original, $substitute, $leave);
    }

    public function test_nominate_blocks_overlapping_grant_for_same_original_user(): void
    {
        $original = User::factory()->create();
        $substituteA = User::factory()->create();
        $substituteB = User::factory()->create();
        $leave = $this->approvedLeave($original);

        $this->service->nominate($original, $substituteA, $leave);

        $this->expectException(ValidationException::class);
        $this->service->nominate($original, $substituteB, $leave);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionNominationTest.php"`
Expected: FAIL — class `App\Services\HR\SubstitutionService` not found.

- [ ] **Step 3: Write `SubstitutionService`**

```php
<?php

namespace App\Services\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\Substitution;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubstitutionService
{
    public function __construct(private IPCRWorkflowService $ipcrWorkflow) {}

    /**
     * Create a pending-approval substitution grant tied to an approved
     * Leave/Travel absence.
     */
    public function nominate(User $originalUser, User $substituteUser, Model $absentable, ?string $notes = null): Substitution
    {
        [$start, $end] = $this->absentableDateRange($absentable);
        $errors = [];

        if (! $this->absentableIsApproved($absentable)) {
            $errors[] = 'The underlying leave/travel request is not approved.';
        }

        if ($originalUser->isSuperAdmin()) {
            $errors[] = 'Administrators cannot be substituted through this module.';
        }

        if ($originalUser->is($substituteUser)) {
            $errors[] = 'You cannot nominate yourself as your own substitute.';
        }

        if ($start && $end && $this->hasOverlappingApprovedLeave($substituteUser, $start, $end)) {
            $errors[] = 'The selected substitute has their own approved leave overlapping this period.';
        }

        if ($start && $end && $this->hasOverlappingGrant($originalUser, $start, $end)) {
            $errors[] = 'There is already an active or pending substitution for this period.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['substitution' => $errors]);
        }

        return Substitution::create([
            'original_user_id' => $originalUser->id,
            'substitute_user_id' => $substituteUser->id,
            'absentable_type' => get_class($absentable),
            'absentable_id' => $absentable->id,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'pending_approval',
            'nominated_by' => $originalUser->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Resolve who must approve a substitution nomination for this employee —
     * reuses the same Division Chief/AUH recommender resolution as the leave
     * approval workflow, falling back to the employee's Division Chief when
     * that resolves to nobody or to the employee themselves.
     */
    public function resolveApprover(User $originalUser): ?User
    {
        $recommender = $this->ipcrWorkflow->leaveRecommenderFor($originalUser);
        if ($recommender && (int) $recommender->id !== (int) $originalUser->id) {
            return $recommender;
        }

        $divisionChiefId = Division::where('id', $originalUser->division_id)->value('division_chief_id');
        if ($divisionChiefId && (int) $divisionChiefId !== (int) $originalUser->id) {
            return User::find($divisionChiefId);
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?string} [start_date, end_date] as Y-m-d strings */
    private function absentableDateRange(Model $absentable): array
    {
        if ($absentable instanceof LeaveApplication) {
            return [$absentable->date_from?->toDateString(), $absentable->date_to?->toDateString()];
        }

        if ($absentable instanceof TravelRequest) {
            return [$absentable->start_date?->toDateString(), $absentable->end_date?->toDateString()];
        }

        return [null, null];
    }

    private function absentableIsApproved(Model $absentable): bool
    {
        if ($absentable instanceof LeaveApplication) {
            return $absentable->status === 'approved';
        }

        if ($absentable instanceof TravelRequest) {
            return in_array($absentable->status, [
                'ocd_approved', 'transport_arranged', 'cash_advance_processing',
                'dv_created', 'released', 'completed',
            ], true);
        }

        return false;
    }

    private function hasOverlappingApprovedLeave(User $user, string $start, string $end): bool
    {
        return LeaveApplication::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start)
            ->exists();
    }

    private function hasOverlappingGrant(User $originalUser, string $start, string $end): bool
    {
        return Substitution::where('original_user_id', $originalUser->id)
            ->approvedOrPending()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionNominationTest.php"`
Expected: PASS (5 tests). If `hasOverlappingGrant`/`approvedOrPending` errors as an undefined scope method, confirm `scopeApprovedOrPending` from Task 2 is present — Eloquent exposes it as `->approvedOrPending()`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/HR/SubstitutionService.php tests/Feature/HR/SubstitutionNominationTest.php
git commit -m "feat(substitution): add SubstitutionService::nominate with validation rules"
```

---

### Task 4: `SubstitutionService::approve()` and `reject()`

**Files:**
- Modify: `app/Services/HR/SubstitutionService.php`
- Test: `tests/Feature/HR/SubstitutionApprovalTest.php`

**Interfaces:**
- Consumes: `SubstitutionService::resolveApprover()` (Task 3).
- Produces: `SubstitutionService::approve(Substitution $substitution, User $approver, ?string $remarks = null): Substitution`; `SubstitutionService::reject(Substitution $substitution, User $approver, string $reason): Substitution`. Both throw `\Illuminate\Validation\ValidationException` if `$substitution->status !== 'pending_approval'` or `$approver` doesn't match `resolveApprover($substitution->originalUser)` (unless `$approver->isSuperAdmin()`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubstitutionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private SubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubstitutionService::class);
    }

    private function pendingSubstitution(): array
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(), 'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $substitution = $this->service->nominate($original, $substitute, $leave);

        return [$substitution, $chief, $original];
    }

    public function test_resolved_approver_can_approve(): void
    {
        [$substitution, $chief] = $this->pendingSubstitution();

        $result = $this->service->approve($substitution, $chief, 'Looks good');

        $this->assertSame('approved', $result->status);
        $this->assertSame($chief->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_non_approver_cannot_approve(): void
    {
        [$substitution] = $this->pendingSubstitution();
        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->approve($substitution, $stranger);
    }

    public function test_already_decided_substitution_cannot_be_approved_again(): void
    {
        [$substitution, $chief] = $this->pendingSubstitution();
        $this->service->approve($substitution, $chief);

        $this->expectException(ValidationException::class);
        $this->service->approve($substitution->fresh(), $chief);
    }

    public function test_resolved_approver_can_reject_with_reason(): void
    {
        [$substitution, $chief] = $this->pendingSubstitution();

        $result = $this->service->reject($substitution, $chief, 'Not appropriate coverage');

        $this->assertSame('rejected', $result->status);
        $this->assertSame('Not appropriate coverage', $result->rejection_reason);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionApprovalTest.php"`
Expected: FAIL — `approve()`/`reject()` methods don't exist.

- [ ] **Step 3: Add `approve()` and `reject()` to `SubstitutionService`**

Add these two public methods to `app/Services/HR/SubstitutionService.php` (after `resolveApprover()`):

```php
    public function approve(Substitution $substitution, User $approver, ?string $remarks = null): Substitution
    {
        $this->guardPendingAndAuthorizedApprover($substitution, $approver);

        $substitution->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'notes' => trim(($substitution->notes ?? '') . ($remarks ? "\nApprover remarks: {$remarks}" : '')),
        ]);

        return $substitution->fresh();
    }

    public function reject(Substitution $substitution, User $approver, string $reason): Substitution
    {
        $this->guardPendingAndAuthorizedApprover($substitution, $approver);

        $substitution->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $substitution->fresh();
    }

    private function guardPendingAndAuthorizedApprover(Substitution $substitution, User $approver): void
    {
        if ($substitution->status !== 'pending_approval') {
            throw ValidationException::withMessages(['substitution' => ['This nomination has already been decided.']]);
        }

        if ($approver->isSuperAdmin()) {
            return;
        }

        $resolved = $this->resolveApprover($substitution->originalUser);
        if (! $resolved || (int) $resolved->id !== (int) $approver->id) {
            throw ValidationException::withMessages(['substitution' => ['You are not the resolved approver for this nomination.']]);
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionApprovalTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/HR/SubstitutionService.php tests/Feature/HR/SubstitutionApprovalTest.php
git commit -m "feat(substitution): add SubstitutionService::approve and reject"
```

---

### Task 5: `SubstitutionService::revoke()` + cascade from Leave cancellation

**Files:**
- Modify: `app/Services/HR/SubstitutionService.php`
- Modify: `app/Http/Controllers/HR/LeaveApplicationController.php:279-286` (inside `cancel()`, the `DB::transaction` closure)
- Test: `tests/Feature/HR/SubstitutionRevocationTest.php`

**Interfaces:**
- Consumes: `SubstitutionService::resolveApprover()` (Task 3).
- Produces: `SubstitutionService::revoke(Substitution $substitution, ?User $revoker, string $reason): Substitution` (throws `ValidationException` if not `approved`, or if `$revoker` is set and is neither the original user, the resolved approver, nor a SuperAdmin); `SubstitutionService::revokeForCancelledAbsence(Model $absentable): void` — revokes any `approved` substitution whose `absentable_type`/`absentable_id` matches, with `$revoker = null` (system-triggered).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubstitutionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private SubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubstitutionService::class);
    }

    private function approvedSubstitution(): array
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(), 'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $substitution = $this->service->nominate($original, $substitute, $leave);
        $substitution = $this->service->approve($substitution, $chief);

        return [$substitution, $original, $chief, $leave];
    }

    public function test_original_user_can_revoke_own_grant(): void
    {
        [$substitution, $original] = $this->approvedSubstitution();

        $result = $this->service->revoke($substitution, $original, 'Leave cut short');

        $this->assertSame('revoked', $result->status);
        $this->assertSame($original->id, $result->revoked_by);
        $this->assertSame('Leave cut short', $result->revocation_reason);
    }

    public function test_unrelated_user_cannot_revoke(): void
    {
        [$substitution] = $this->approvedSubstitution();
        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->revoke($substitution, $stranger, 'no reason');
    }

    public function test_revoke_for_cancelled_absence_finds_and_revokes_grant(): void
    {
        [$substitution, , , $leave] = $this->approvedSubstitution();

        $this->service->revokeForCancelledAbsence($leave);

        $this->assertSame('revoked', $substitution->fresh()->status);
        $this->assertNull($substitution->fresh()->revoked_by);
    }

    public function test_leave_cancellation_cascades_to_revoke_substitution(): void
    {
        [$substitution, $original, , $leave] = $this->approvedSubstitution();

        $this->actingAs($original)
            ->post(route('hr.leave.cancel', $leave))
            ->assertRedirect();

        $this->assertSame('revoked', $substitution->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionRevocationTest.php"`
Expected: FAIL — `revoke()`/`revokeForCancelledAbsence()` don't exist yet.

- [ ] **Step 3: Add `revoke()` and `revokeForCancelledAbsence()` to `SubstitutionService`**

Add after `reject()`:

```php
    public function revoke(Substitution $substitution, ?User $revoker, string $reason): Substitution
    {
        if ($substitution->status !== 'approved') {
            throw ValidationException::withMessages(['substitution' => ['Only an approved substitution can be revoked.']]);
        }

        if ($revoker !== null && ! $revoker->isSuperAdmin()) {
            $isOriginalUser = (int) $revoker->id === (int) $substitution->original_user_id;
            $resolvedApprover = $this->resolveApprover($substitution->originalUser);
            $isResolvedApprover = $resolvedApprover && (int) $resolvedApprover->id === (int) $revoker->id;

            if (! $isOriginalUser && ! $isResolvedApprover) {
                throw ValidationException::withMessages(['substitution' => ['You are not authorized to revoke this substitution.']]);
            }
        }

        $substitution->update([
            'status' => 'revoked',
            'revoked_by' => $revoker?->id,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        app(ActingAsService::class)->forceEndForSubstitution($substitution, 'revoked');

        return $substitution->fresh();
    }

    /** System-triggered revocation when the underlying Leave/Travel is cancelled. */
    public function revokeForCancelledAbsence(Model $absentable): void
    {
        Substitution::where('absentable_type', get_class($absentable))
            ->where('absentable_id', $absentable->id)
            ->where('status', 'approved')
            ->get()
            ->each(fn (Substitution $s) => $this->revoke($s, null, 'The underlying leave/travel request was cancelled.'));
    }
```

Add the import at the top of the file:

```php
use App\Services\HR\ActingAsService;
```

(`ActingAsService` itself is created in Task 8 — this file will not compile-check until then, but the tests for this task only exercise `revoke()`/`revokeForCancelledAbsence()`, which call `app(ActingAsService::class)`. **Skip ahead and do Task 8's `ActingAsService::forceEndForSubstitution()` stub first if running this task in isolation** — see Step 3a below.)

- [ ] **Step 3a: Create a minimal `ActingAsService` stub (fleshed out fully in Task 8)**

```php
<?php

namespace App\Services\HR;

use App\Models\HR\ActingAsSession;
use App\Models\HR\Substitution;

class ActingAsService
{
    public function forceEndForSubstitution(Substitution $substitution, string $reason): void
    {
        ActingAsSession::where('substitution_id', $substitution->id)
            ->open()
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);
    }
}
```

- [ ] **Step 4: Wire the cascade into `LeaveApplicationController::cancel()`**

In `app/Http/Controllers/HR/LeaveApplicationController.php`, the existing `cancel()` method's `DB::transaction` closure (around line 279-286) currently reads:

```php
        DB::transaction(function () use ($leaveApplication) {
            // Restore credits if the leave was already approved and credits were deducted
            if ($leaveApplication->status === 'approved') {
                $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
            }

            $leaveApplication->update(['status' => 'cancelled']);
        });
```

Change it to also cascade the substitution revocation:

```php
        DB::transaction(function () use ($leaveApplication) {
            // Restore credits if the leave was already approved and credits were deducted
            if ($leaveApplication->status === 'approved') {
                $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
            }

            app(\App\Services\HR\SubstitutionService::class)->revokeForCancelledAbsence($leaveApplication);

            $leaveApplication->update(['status' => 'cancelled']);
        });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionRevocationTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/HR/SubstitutionService.php app/Services/HR/ActingAsService.php app/Http/Controllers/HR/LeaveApplicationController.php tests/Feature/HR/SubstitutionRevocationTest.php
git commit -m "feat(substitution): add revoke and cascade from leave cancellation"
```

---

### Task 6: Permission seeder

**Files:**
- Create: `database/seeders/SubstitutionPermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: permissions `hr.substitution.approve`, `hr.substitution.revoke` attached to roles `Administrator` and `DivisionChief`.

- [ ] **Step 1: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * hr.substitution.approve / hr.substitution.revoke — admin-override
 * permissions for the Substitution module. Identity-based approve/revoke
 * (the employee's resolved AUH/Division Chief, or the employee themselves
 * for revoke) is authorized without these permissions — they exist only so
 * Administrators and Division Chiefs generally can act as a backstop.
 */
class SubstitutionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $approve = Permission::updateOrCreate(
            ['name' => 'hr.substitution.approve'],
            ['module' => 'HR', 'description' => 'Approve/reject substitution nominations (admin override)']
        );
        $revoke = Permission::updateOrCreate(
            ['name' => 'hr.substitution.revoke'],
            ['module' => 'HR', 'description' => 'Revoke an active substitution (admin override)']
        );

        foreach (['Administrator', 'DivisionChief'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->permissions()->syncWithoutDetaching([$approve->id, $revoke->id]);
        }
    }
}
```

- [ ] **Step 2: Register it in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, add after the `$this->call(QuizPermissionSeeder::class);` line:

```php
        // ── Substitution Module ─────────────────────────────────────────────────
        $this->call(SubstitutionPermissionSeeder::class);
```

- [ ] **Step 3: Run the seeder in dev**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=SubstitutionPermissionSeeder"`
Expected: no errors; `SELECT * FROM permissions WHERE name LIKE 'hr.substitution.%'` returns 2 rows.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/SubstitutionPermissionSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(substitution): add hr.substitution.approve/revoke permission seeder"
```

---

### Task 7: `SubstitutionController` + routes

**Files:**
- Create: `app/Http/Controllers/HR/SubstitutionController.php`
- Modify: `routes/web.php` (inside the `Route::middleware(['auth', 'verified'])->prefix('hr')->name('hr.')->group(...)` block, near the `// ── Leave Applications ──` section around line 1958)
- Test: `tests/Feature/HR/SubstitutionWorkflowTest.php`

**Interfaces:**
- Consumes: `SubstitutionService::nominate/approve/reject/revoke/resolveApprover()` (Tasks 3-5).
- Produces: routes `hr.substitutions.index` (GET), `hr.substitutions.store` (POST), `hr.substitutions.approve` (POST `/substitutions/{substitution}/approve`), `hr.substitutions.reject` (POST), `hr.substitutions.revoke` (POST).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstitutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDivisionAndLeave(): array
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(), 'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);

        return [$original, $substitute, $chief, $leave];
    }

    public function test_employee_can_nominate_a_substitute_for_their_own_leave(): void
    {
        [$original, $substitute, , $leave] = $this->setUpDivisionAndLeave();

        $this->actingAs($original)
            ->post(route('hr.substitutions.store'), [
                'substitute_user_id' => $substitute->id,
                'leave_application_id' => $leave->id,
                'notes' => 'Please cover my Grade 10 sections',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('substitutions', [
            'original_user_id' => $original->id,
            'substitute_user_id' => $substitute->id,
            'status' => 'pending_approval',
        ]);
    }

    public function test_employee_cannot_nominate_a_substitute_for_someone_elses_leave(): void
    {
        [$original, $substitute] = $this->setUpDivisionAndLeave();
        $intruder = User::factory()->create();
        [, , , $otherLeave] = $this->setUpDivisionAndLeave();

        $this->actingAs($intruder)
            ->post(route('hr.substitutions.store'), [
                'substitute_user_id' => $substitute->id,
                'leave_application_id' => $otherLeave->id,
            ])
            ->assertStatus(403);
    }

    public function test_resolved_approver_can_approve_via_http(): void
    {
        [$original, $substitute, $chief, $leave] = $this->setUpDivisionAndLeave();
        $substitution = app(\App\Services\HR\SubstitutionService::class)->nominate($original, $substitute, $leave);

        $this->actingAs($chief)
            ->post(route('hr.substitutions.approve', $substitution))
            ->assertRedirect();

        $this->assertSame('approved', $substitution->fresh()->status);
    }

    public function test_original_user_can_revoke_via_http(): void
    {
        [$original, $substitute, $chief, $leave] = $this->setUpDivisionAndLeave();
        $service = app(\App\Services\HR\SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        $this->actingAs($original)
            ->post(route('hr.substitutions.revoke', $substitution), ['reason' => 'No longer needed'])
            ->assertRedirect();

        $this->assertSame('revoked', $substitution->fresh()->status);
    }

    public function test_index_lists_my_nominations_and_my_substitutions(): void
    {
        [$original, $substitute, , $leave] = $this->setUpDivisionAndLeave();
        app(\App\Services\HR\SubstitutionService::class)->nominate($original, $substitute, $leave);

        $response = $this->actingAs($original)->get(route('hr.substitutions.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('HR/Substitutions/Index')
            ->has('myNominations', 1)
        );

        $response2 = $this->actingAs($substitute)->get(route('hr.substitutions.index'));
        $response2->assertInertia(fn ($page) => $page->has('mySubstitutions', 1));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionWorkflowTest.php"`
Expected: FAIL — route `hr.substitutions.store` not defined.

- [ ] **Step 3: Write `SubstitutionController`**

```php
<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\Substitution;
use App\Models\TravelRequest;
use App\Services\HR\SubstitutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubstitutionController extends Controller
{
    public function __construct(private SubstitutionService $substitutions) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $myNominations = Substitution::where('nominated_by', $user->id)
            ->with(['substitute:id,name', 'absentable'])
            ->latest()
            ->get();

        $mySubstitutions = Substitution::where('substitute_user_id', $user->id)
            ->approvedOrPending()
            ->with(['originalUser:id,name'])
            ->latest()
            ->get()
            ->map(fn (Substitution $s) => [
                ...$s->toArray(),
                'can_act_as' => $s->isWithinWindow(),
            ]);

        $forMyApproval = Substitution::where('status', 'pending_approval')
            ->with(['originalUser:id,name', 'substitute:id,name'])
            ->get()
            ->filter(function (Substitution $s) use ($user) {
                if ($user->isSuperAdmin() || $user->hasPermission('hr.substitution.approve')) {
                    return true;
                }
                $resolved = $this->substitutions->resolveApprover($s->originalUser);

                return $resolved && (int) $resolved->id === (int) $user->id;
            })
            ->values();

        return Inertia::render('HR/Substitutions/Index', [
            'myNominations' => $myNominations,
            'mySubstitutions' => $mySubstitutions,
            'forMyApproval' => $forMyApproval,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'substitute_user_id' => 'required|exists:users,id',
            'leave_application_id' => 'nullable|exists:leave_applications,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_if(
            empty($data['leave_application_id']) === empty($data['travel_request_id']),
            422,
            'Provide exactly one of leave_application_id or travel_request_id.'
        );

        $absentable = ! empty($data['leave_application_id'])
            ? LeaveApplication::findOrFail($data['leave_application_id'])
            : TravelRequest::findOrFail($data['travel_request_id']);

        abort_unless(
            (int) $absentable->user_id === Auth::id() || (int) ($absentable->traveler_id ?? null) === Auth::id(),
            403,
            'You can only nominate a substitute for your own leave/travel.'
        );

        $substitute = \App\Models\User::findOrFail($data['substitute_user_id']);

        $this->substitutions->nominate(Auth::user(), $substitute, $absentable, $data['notes'] ?? null);

        return back()->with('success', 'Substitute nominated — awaiting approval.');
    }

    public function approve(Request $request, Substitution $substitution)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $this->substitutions->approve($substitution, Auth::user(), $request->input('remarks'));

        return back()->with('success', 'Substitution approved.');
    }

    public function reject(Request $request, Substitution $substitution)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $this->substitutions->reject($substitution, Auth::user(), $data['reason']);

        return back()->with('success', 'Substitution rejected.');
    }

    public function revoke(Request $request, Substitution $substitution)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->substitutions->revoke($substitution, Auth::user(), $data['reason'] ?? 'Revoked.');

        return back()->with('success', 'Substitution revoked.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, inside the `hr.` group, immediately before the `// ── Leave Applications ──` comment (around line 1958), add:

```php
    // ── Substitutions ──────────────────────────────────────────────────────────
    Route::get('/substitutions', [\App\Http\Controllers\HR\SubstitutionController::class, 'index'])
        ->name('substitutions.index');
    Route::post('/substitutions', [\App\Http\Controllers\HR\SubstitutionController::class, 'store'])
        ->name('substitutions.store');
    Route::post('/substitutions/{substitution}/approve', [\App\Http\Controllers\HR\SubstitutionController::class, 'approve'])
        ->name('substitutions.approve');
    Route::post('/substitutions/{substitution}/reject', [\App\Http\Controllers\HR\SubstitutionController::class, 'reject'])
        ->name('substitutions.reject');
    Route::post('/substitutions/{substitution}/revoke', [\App\Http\Controllers\HR\SubstitutionController::class, 'revoke'])
        ->name('substitutions.revoke');

```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/SubstitutionWorkflowTest.php"`
Expected: PASS (5 tests). Note: `HR/Substitutions/Index` Inertia component doesn't exist yet (Task 12) — `assertInertia` only inspects the response payload, not that the Vue file renders, so this still passes; Inertia's test response doesn't require the frontend component to exist.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/HR/SubstitutionController.php routes/web.php tests/Feature/HR/SubstitutionWorkflowTest.php
git commit -m "feat(substitution): add SubstitutionController and routes"
```

---

### Task 8: `ActingAsService::start()` / `exit()`

**Files:**
- Modify: `app/Services/HR/ActingAsService.php` (created as a stub in Task 5)
- Test: `tests/Feature/HR/ActingAsServiceTest.php`

**Interfaces:**
- Consumes: `Substitution::isWithinWindow()` (Task 2).
- Produces: `ActingAsService::start(Substitution $substitution, User $trueUser, Request $request): void`; `ActingAsService::exit(Request $request, string $reason = 'manual'): void`; `ActingAsService::currentSubstitution(Request $request): ?Substitution` (used by Task 9's middleware and Task 11's Inertia shared prop).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\ActingAsSession;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\ActingAsService;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;

class ActingAsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubstitution()
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id, 'name' => 'Original Person']);
        $substitute = User::factory()->create(['name' => 'Substitute Person']);
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        return [$substitution, $original, $substitute];
    }

    public function test_start_swaps_auth_user_to_original_and_creates_open_session(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute);
        $request = Request::create('/');

        app(ActingAsService::class)->start($substitution, $substitute, $request);

        $this->assertSame($original->id, Auth::id());
        $this->assertDatabaseHas('acting_as_sessions', [
            'substitution_id' => $substitution->id,
            'ended_at' => null,
        ]);
    }

    public function test_exit_reverts_to_true_user_and_closes_session(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute);
        $request = Request::create('/');
        app(ActingAsService::class)->start($substitution, $substitute, $request);

        app(ActingAsService::class)->exit($request, 'manual');

        $this->assertSame($substitute->id, Auth::id());
        $session = ActingAsSession::where('substitution_id', $substitution->id)->first();
        $this->assertNotNull($session->ended_at);
        $this->assertSame('manual', $session->ended_reason);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsServiceTest.php"`
Expected: FAIL — `start()`/`exit()`/`currentSubstitution()` don't exist yet.

- [ ] **Step 3: Rewrite `ActingAsService` in full**

```php
<?php

namespace App\Services\HR;

use App\Models\HR\ActingAsSession;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ActingAsService
{
    private const SESSION_TRUE_USER_ID = 'true_user_id';
    private const SESSION_SUBSTITUTION_ID = 'acting_substitution_id';

    public function start(Substitution $substitution, User $trueUser, Request $request): void
    {
        if ((int) $substitution->substitute_user_id !== (int) $trueUser->id) {
            throw ValidationException::withMessages(['substitution' => ['You are not the substitute for this grant.']]);
        }

        if (! $substitution->isWithinWindow()) {
            throw ValidationException::withMessages(['substitution' => ['This substitution is not currently within its active date window.']]);
        }

        // Only one active identity at a time — auto-exit any other live session first.
        if ($request->session()->has(self::SESSION_SUBSTITUTION_ID)) {
            $this->exit($request, 'manual');
        }

        ActingAsSession::create([
            'substitution_id' => $substitution->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $request->session()->put(self::SESSION_TRUE_USER_ID, $trueUser->id);
        $request->session()->put(self::SESSION_SUBSTITUTION_ID, $substitution->id);

        Auth::login($substitution->originalUser);
    }

    public function exit(Request $request, string $reason = 'manual'): void
    {
        $substitutionId = $request->session()->get(self::SESSION_SUBSTITUTION_ID);
        $trueUserId = $request->session()->get(self::SESSION_TRUE_USER_ID);

        if (! $substitutionId || ! $trueUserId) {
            return;
        }

        ActingAsSession::where('substitution_id', $substitutionId)
            ->open()
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);

        $request->session()->forget([self::SESSION_TRUE_USER_ID, self::SESSION_SUBSTITUTION_ID]);

        $trueUser = User::find($trueUserId);
        if ($trueUser) {
            Auth::login($trueUser);
        }
    }

    public function forceEndForSubstitution(Substitution $substitution, string $reason): void
    {
        ActingAsSession::where('substitution_id', $substitution->id)
            ->open()
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);
    }

    /** The substitution currently being acted-as in this request's session, if any and still valid. */
    public function currentSubstitution(Request $request): ?Substitution
    {
        $substitutionId = $request->session()->get(self::SESSION_SUBSTITUTION_ID);
        if (! $substitutionId) {
            return null;
        }

        return Substitution::find($substitutionId);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsServiceTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/HR/ActingAsService.php tests/Feature/HR/ActingAsServiceTest.php
git commit -m "feat(substitution): implement ActingAsService start/exit session swap"
```

---

### Task 9: `EnsureActingAsWindowValid` middleware

**Files:**
- Create: `app/Http/Middleware/EnsureActingAsWindowValid.php`
- Modify: `bootstrap/app.php:23-27`
- Test: `tests/Feature/HR/ActingAsWindowMiddlewareTest.php`

**Interfaces:**
- Consumes: `ActingAsService::currentSubstitution()`, `ActingAsService::exit()` (Task 8).
- Produces: on every request, if the session holds an acting-as grant that is no longer `approved` or is outside its date window, the middleware reverts to the true user and redirects with a flash error — before the request reaches its controller.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\ActingAsService;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsWindowMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubstitution(array $overrides = [])
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create(array_merge([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ], []));
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);
        if ($overrides) {
            $substitution->update($overrides);
        }

        return [$substitution, $original, $substitute];
    }

    public function test_request_reverts_to_true_user_when_window_expired(): void
    {
        [$substitution, , $substitute] = $this->approvedSubstitution([
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
        ]);
        $this->actingAs($substitute);
        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        app(ActingAsService::class)->start($substitution, $substitute, $request);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect();
        $this->assertAuthenticatedAs($substitute);
    }

    public function test_request_proceeds_normally_when_no_acting_as_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsWindowMiddlewareTest.php"`
Expected: FAIL — first test fails because nothing reverts the session yet (still authenticated as `original`, not `substitute`).

- [ ] **Step 3: Write the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Services\HR\ActingAsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActingAsWindowValid
{
    public function __construct(private ActingAsService $actingAs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $substitution = $this->actingAs->currentSubstitution($request);

        if (! $substitution) {
            return $next($request);
        }

        $valid = $substitution->status === 'approved' && $substitution->isWithinWindow();

        if ($valid) {
            return $next($request);
        }

        $reason = $substitution->status === 'revoked' ? 'revoked' : 'expired';
        $this->actingAs->exit($request, $reason);

        return redirect()->route('dashboard')->with('error', 'Your acting-as access window has ended.');
    }
}
```

- [ ] **Step 4: Register the middleware globally**

In `bootstrap/app.php`, change the `$middleware->web(append: [...])` block (lines 23-27) from:

```php
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\AtlasRequestStatsMiddleware::class,
        ]);
```

to:

```php
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\AtlasRequestStatsMiddleware::class,
            \App\Http\Middleware\EnsureActingAsWindowValid::class,
        ]);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsWindowMiddlewareTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full existing test suite to check for regressions**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: same pass/fail counts as the pre-existing baseline (no new failures caused by the global middleware — it no-ops immediately for any request without an active acting-as session).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/EnsureActingAsWindowValid.php bootstrap/app.php tests/Feature/HR/ActingAsWindowMiddlewareTest.php
git commit -m "feat(substitution): enforce acting-as window on every request"
```

---

### Task 10: `ActingAsController` + routes

**Files:**
- Create: `app/Http/Controllers/HR/ActingAsController.php`
- Modify: `routes/web.php` (immediately after the Substitutions routes added in Task 7)
- Test: `tests/Feature/HR/ActingAsControllerTest.php`

**Interfaces:**
- Consumes: `ActingAsService::start()`/`exit()` (Task 8).
- Produces: routes `hr.substitutions.act-as.start` (POST `/substitutions/{substitution}/act-as`), `hr.substitutions.act-as.exit` (POST `/substitutions/act-as/exit`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubstitution()
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        return [$substitution, $original, $substitute];
    }

    public function test_substitute_can_start_acting_as(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();

        $this->actingAs($substitute)
            ->post(route('hr.substitutions.act-as.start', $substitution))
            ->assertRedirect();

        $this->assertAuthenticatedAs($original);
    }

    public function test_non_substitute_cannot_start_acting_as(): void
    {
        [$substitution] = $this->approvedSubstitution();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('hr.substitutions.act-as.start', $substitution))
            ->assertSessionHasErrors();

        $this->assertAuthenticatedAs($stranger);
    }

    public function test_exit_returns_to_true_user(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute)->post(route('hr.substitutions.act-as.start', $substitution));

        $this->post(route('hr.substitutions.act-as.exit'))->assertRedirect();

        $this->assertAuthenticatedAs($substitute);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsControllerTest.php"`
Expected: FAIL — route `hr.substitutions.act-as.start` not defined.

- [ ] **Step 3: Write `ActingAsController`**

```php
<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Substitution;
use App\Services\HR\ActingAsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActingAsController extends Controller
{
    public function __construct(private ActingAsService $actingAs) {}

    public function start(Request $request, Substitution $substitution)
    {
        $this->actingAs->start($substitution, Auth::user(), $request);

        return redirect()->route('dashboard')->with('success', "Now acting as {$substitution->originalUser->name}.");
    }

    public function exit(Request $request)
    {
        $this->actingAs->exit($request, 'manual');

        return redirect()->route('dashboard')->with('success', 'Returned to your own account.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, immediately after the Substitutions routes block added in Task 7 (after the `substitutions.revoke` route), add:

```php
    Route::post('/substitutions/{substitution}/act-as', [\App\Http\Controllers\HR\ActingAsController::class, 'start'])
        ->name('substitutions.act-as.start');
    Route::post('/substitutions/act-as/exit', [\App\Http\Controllers\HR\ActingAsController::class, 'exit'])
        ->name('substitutions.act-as.exit');

```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsControllerTest.php"`
Expected: PASS (3 tests). `test_non_substitute_cannot_start_acting_as` relies on `ValidationException` from `ActingAsService::start()` being converted into a redirect-with-session-errors by Laravel's default exception handling for web (non-JSON) requests — this is the framework default, no extra code needed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/HR/ActingAsController.php routes/web.php tests/Feature/HR/ActingAsControllerTest.php
git commit -m "feat(substitution): add ActingAsController start/exit endpoints"
```

---

### Task 11: Inertia shared `actingAs` prop

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/HR/ActingAsInertiaPropTest.php`

**Interfaces:**
- Consumes: `ActingAsService::currentSubstitution()` (Task 8).
- Produces: Inertia shared prop `actingAs` — `null` normally, or `{ original_user_name, substitute_user_name, substitution_id, end_date }` while an acting-as session is live. Consumed by Task 12's `ActingAsBanner.vue`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsInertiaPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_acting_as_prop_is_null_normally(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('actingAs', null));
    }

    public function test_acting_as_prop_populated_during_active_session(): void
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id, 'name' => 'Original Person']);
        $substitute = User::factory()->create(['name' => 'Substitute Person']);
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        $this->actingAs($substitute)->post(route('hr.substitutions.act-as.start', $substitution));

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('actingAs.original_user_name', 'Original Person')
            ->where('actingAs.substitute_user_name', 'Substitute Person')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsInertiaPropTest.php"`
Expected: FAIL — `actingAs` key not present in the shared props.

- [ ] **Step 3: Add the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, add `use App\Services\HR\ActingAsService;` to the imports, and add a new top-level key to the array returned by `share()` (alongside `'appVersion'` and `'atlasGoVersion'`, before the closing `]);` of the `array_merge(...)` call):

```php
            'actingAs' => function () use ($request) {
                try {
                    $substitution = app(ActingAsService::class)->currentSubstitution($request);
                    if (! $substitution) {
                        return null;
                    }

                    return [
                        'substitution_id' => $substitution->id,
                        'original_user_name' => $substitution->originalUser->name,
                        'substitute_user_name' => $substitution->substitute->name,
                        'end_date' => $substitution->end_date->toDateString(),
                    ];
                } catch (\Throwable $e) {
                    return null;
                }
            },
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/ActingAsInertiaPropTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/HR/ActingAsInertiaPropTest.php
git commit -m "feat(substitution): share actingAs prop with the frontend"
```

---

### Task 12: `ActingAsBanner.vue` + mount in `AdminLayout.vue`

**Files:**
- Create: `resources/js/Components/Layout/ActingAsBanner.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: Inertia shared prop `actingAs` (Task 11), route `hr.substitutions.act-as.exit` (Task 10).

- [ ] **Step 1: Write `ActingAsBanner.vue`**

```vue
<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page = usePage()
const actingAs = computed(() => page.props.actingAs)

function exitActingAs() {
  router.post(route('hr.substitutions.act-as.exit'))
}
</script>

<template>
  <div
    v-if="actingAs"
    class="w-full bg-amber-500 text-white text-sm px-4 py-2 flex items-center justify-between gap-3 flex-wrap"
  >
    <span>
      Acting as <strong>{{ actingAs.original_user_name }}</strong>
      — you are <strong>{{ actingAs.substitute_user_name }}</strong>
      (until {{ new Date(actingAs.end_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }})
    </span>
    <button
      @click="exitActingAs"
      class="bg-white text-amber-700 px-3 py-1 rounded-lg text-xs font-medium hover:bg-amber-50 shrink-0"
    >
      Return to my account
    </button>
  </div>
</template>
```

- [ ] **Step 2: Mount it in `AdminLayout.vue`**

In `resources/js/Layouts/AdminLayout.vue`, add the import near the other `@/Components/Layout/*` imports (around line 14, next to `AdminTopbar`):

```js
import ActingAsBanner from '@/Components/Layout/ActingAsBanner.vue';
```

In the template, change the opening of the root markup (around line 512-513) from:

```vue
  <div class="min-h-screen flex bg-slate-50">
```

to:

```vue
  <div class="min-h-screen flex flex-col bg-slate-50">
    <ActingAsBanner />
    <div class="min-h-screen flex flex-1">
```

...and add one closing `</div>` immediately before the layout's final closing `</template>` tag, to balance the newly-added wrapper `<div>`.

- [ ] **Step 3: Build the frontend**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manually verify in the browser**

Start the dev server if not already running, log in as a test user with an active `acting_as_sessions` row seeded via tinker (or by running through Tasks 7/10's flow in the UI once Task 13 ships the nomination form), and confirm the amber banner renders at the very top of every page, above the sidebar, and that "Return to my account" correctly reverts the session.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Layout/ActingAsBanner.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat(substitution): add persistent Acting As banner"
```

---

### Task 13: `Substitutions/Index.vue`

**Files:**
- Create: `resources/js/Pages/HR/Substitutions/Index.vue`

**Interfaces:**
- Consumes: Inertia props `myNominations`, `mySubstitutions`, `forMyApproval` (Task 7's `SubstitutionController::index()`), routes `hr.substitutions.approve`, `hr.substitutions.reject`, `hr.substitutions.revoke`, `hr.substitutions.act-as.start`.

- [ ] **Step 1: Write the page**

```vue
<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  myNominations: { type: Array, default: () => [] },
  mySubstitutions: { type: Array, default: () => [] },
  forMyApproval: { type: Array, default: () => [] },
})

const tab = ref(props.forMyApproval.length ? 'approval' : 'mine')

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'
}

function approve(substitution) {
  router.post(route('hr.substitutions.approve', substitution.id), {}, { preserveScroll: true })
}

function reject(substitution) {
  const reason = window.prompt('Reason for rejecting this nomination:')
  if (!reason) return
  router.post(route('hr.substitutions.reject', substitution.id), { reason }, { preserveScroll: true })
}

function revoke(substitution) {
  const reason = window.prompt('Reason for revoking this substitution (optional):') || ''
  router.post(route('hr.substitutions.revoke', substitution.id), { reason }, { preserveScroll: true })
}

function actAs(substitution) {
  router.post(route('hr.substitutions.act-as.start', substitution.id))
}
</script>

<template>
  <Head title="Substitutions" />
  <AdminLayout title="Substitutions">
    <div class="max-w-5xl mx-auto p-6 space-y-6">
      <h1 class="text-xl font-semibold text-slate-800">Substitutions</h1>

      <div class="flex gap-2 border-b border-slate-200">
        <button
          v-for="t in [
            { key: 'mine', label: `My Nominations (${myNominations.length})` },
            { key: 'approval', label: `For My Approval (${forMyApproval.length})` },
            { key: 'substituting', label: `My Substitutions (${mySubstitutions.length})` },
          ]"
          :key="t.key"
          @click="tab = t.key"
          :class="[
            'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
            tab === t.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
        >
          {{ t.label }}
        </button>
      </div>

      <div v-if="tab === 'mine'" class="space-y-3">
        <div v-if="!myNominations.length" class="text-sm text-slate-400 py-8 text-center">No nominations filed yet.</div>
        <div v-for="s in myNominations" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ s.substitute?.name }}</p>
            <p class="text-xs text-slate-500">{{ fmtDate(s.start_date) }} – {{ fmtDate(s.end_date) }} · Status: {{ s.status.replace('_', ' ') }}</p>
          </div>
        </div>
      </div>

      <div v-else-if="tab === 'approval'" class="space-y-3">
        <div v-if="!forMyApproval.length" class="text-sm text-slate-400 py-8 text-center">Nothing pending your approval.</div>
        <div v-for="s in forMyApproval" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4 flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ s.original_user?.name }} → {{ s.substitute?.name }}</p>
            <p class="text-xs text-slate-500">{{ fmtDate(s.start_date) }} – {{ fmtDate(s.end_date) }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button @click="approve(s)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Approve</button>
            <button @click="reject(s)" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-50">Reject</button>
          </div>
        </div>
      </div>

      <div v-else class="space-y-3">
        <div v-if="!mySubstitutions.length" class="text-sm text-slate-400 py-8 text-center">You are not covering for anyone.</div>
        <div v-for="s in mySubstitutions" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4 flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ s.original_user?.name }}</p>
            <p class="text-xs text-slate-500">{{ fmtDate(s.start_date) }} – {{ fmtDate(s.end_date) }} · Status: {{ s.status }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button v-if="s.can_act_as" @click="actAs(s)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Act as {{ s.original_user?.name }}</button>
            <button v-if="s.status === 'approved'" @click="revoke(s)" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-50">Revoke</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Build the frontend**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds.

- [ ] **Step 3: Manually verify in the browser**

Navigate to `/hr/substitutions` as a user with at least one nomination in each state, confirm the three tabs render correctly, and that Approve/Reject/Revoke/Act-as buttons round-trip successfully (each should flash a success banner and update the list).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/HR/Substitutions/Index.vue
git commit -m "feat(substitution): add Substitutions index page with 3 tabs"
```

---

### Task 14: "Assign Substitute" panel on Leave/Travel Show pages

**Files:**
- Modify: `resources/js/Pages/HR/Leave/Show.vue`
- Modify: `resources/js/Pages/Travel/Show.vue`
- Modify: `app/Http/Controllers/HR/LeaveApplicationController.php` (`show()` method) — pass an `eligibleSubstitutes` prop
- Modify: `app/Http/Controllers/TravelController.php` (`show()` method) — pass the same

**Interfaces:**
- Consumes: route `hr.substitutions.store` (Task 7).
- Produces: a reusable inline panel pattern (kept local to each page rather than extracted into a shared component, since the two pages pass different `leave_application_id`/`travel_request_id` fields and have different surrounding layouts).

- [ ] **Step 1: Add `eligibleSubstitutes` to `LeaveApplicationController::show()`**

Find the `show()` method in `app/Http/Controllers/HR/LeaveApplicationController.php` and add, alongside its existing `Inertia::render(...)` props array, a new key:

```php
            'eligibleSubstitutes' => \App\Models\User::employees()
                ->where('id', '!=', $leaveApplication->user_id)
                ->orderBy('name')
                ->get(['id', 'name']),
```

(`User::employees()` is the existing scope from `app/Models/User.php:316` that excludes Parent/Student mobile accounts.)

- [ ] **Step 2: Add the same to `TravelController::show()`**

In `app/Http/Controllers/TravelController.php`'s `show()` method, add the identical `eligibleSubstitutes` key (same snippet, `!= $travel->traveler_id` instead of `!= $leaveApplication->user_id`).

- [ ] **Step 3: Add the panel to `resources/js/Pages/HR/Leave/Show.vue`**

Add to the `<script setup>` block (near the existing `defineProps`, around line 244):

```js
import { useForm } from '@inertiajs/vue3'

const substituteForm = useForm({
  substitute_user_id: '',
  leave_application_id: props.application.id,
  notes: '',
})

function submitSubstitute() {
  substituteForm.post(route('hr.substitutions.store'), {
    preserveScroll: true,
    onSuccess: () => { substituteForm.reset('notes') },
  })
}
```

Add `eligibleSubstitutes: { type: Array, default: () => [] },` to the existing `defineProps({...})` call.

Add this panel to the template, conditioned on the leave being approved and the viewer being its owner — place it near the other status-conditional sections (e.g. right after the approval-stage timeline block that includes the `application.approved_by` check around line 145):

```vue
<div v-if="application.status === 'approved'" class="rounded-lg border border-slate-200 bg-white p-4 space-y-3">
  <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Assign Substitute</h3>
  <select v-model="substituteForm.substitute_user_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
    <option value="" disabled>Select a substitute…</option>
    <option v-for="u in eligibleSubstitutes" :key="u.id" :value="u.id">{{ u.name }}</option>
  </select>
  <textarea v-model="substituteForm.notes" placeholder="Handoff notes (optional)" rows="2" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
  <button
    @click="submitSubstitute"
    :disabled="!substituteForm.substitute_user_id || substituteForm.processing"
    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
  >
    Nominate Substitute
  </button>
  <p v-if="substituteForm.errors.substitution" class="text-xs text-danger-500">{{ substituteForm.errors.substitution }}</p>
</div>
```

- [ ] **Step 4: Add the equivalent panel to `resources/js/Pages/Travel/Show.vue`**

Repeat Step 3's pattern in `resources/js/Pages/Travel/Show.vue`, with `travel_request_id: props.travel.id` instead of `leave_application_id`, gated on the travel's approved-enough status (`['ocd_approved', 'transport_arranged', 'cash_advance_processing', 'dv_created', 'released', 'completed'].includes(travel.status)` instead of `application.status === 'approved'`), placed in a sensible existing panel location on that page.

- [ ] **Step 5: Build the frontend**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds.

- [ ] **Step 6: Manually verify in the browser**

As an employee with an approved leave, open its Show page, confirm the "Assign Substitute" panel appears, submit a nomination, and confirm it now shows up under `/hr/substitutions` → "My Nominations". Repeat for an approved travel request.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/HR/Leave/Show.vue resources/js/Pages/Travel/Show.vue app/Http/Controllers/HR/LeaveApplicationController.php app/Http/Controllers/TravelController.php
git commit -m "feat(substitution): add Assign Substitute panel to Leave/Travel Show pages"
```

---

### Task 15: Full regression run + end-to-end manual verification

**Files:** none (verification only).

- [ ] **Step 1: Run the full backend test suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: all new `tests/Feature/HR/Substitution*Test.php` and `tests/Feature/HR/ActingAs*Test.php` files pass; pre-existing failures (if any, per repo baseline) are unchanged in count.

- [ ] **Step 2: End-to-end manual browser walkthrough**

Using the dev environment (`http://localhost:8080`):
1. As an employee with an approved Leave, go to its Show page and nominate a substitute.
2. As that employee's Division Chief, visit `/hr/substitutions`, find it under "For My Approval", and approve it.
3. As the substitute, visit `/hr/substitutions`, find it under "My Substitutions", and click "Act as {name}".
4. Confirm you're redirected to the dashboard, the amber "Acting as..." banner is visible on every page, and you can access things the original user could access (e.g. their leave applications, their designated approvals) that you couldn't before.
5. Click "Return to my account" in the banner and confirm you're back to your own identity and the banner disappears.
6. As the original employee, cancel the (already-used) leave application from a fresh one still in `approved` status with an active substitution, and confirm the substitution flips to `revoked` and any live act-as session for it is force-ended.

- [ ] **Step 3: Report results**

Summarize pass/fail for each of the 6 walkthrough steps and the full test suite's final tally. Do not mark the plan complete if any step fails — file it as a bug against the relevant task instead.

---

## Self-Review Notes

- **Spec coverage:** data model (Tasks 1-2), nomination validation incl. SuperAdmin/self/overlap/availability blocks (Task 3), approval routing incl. applicant-is-approver escalation reuse (Task 3's `resolveApprover`, Task 4), rejection (Task 4), revocation incl. authorization (Task 5), cascade from leave cancellation (Task 5), permissions (Task 6), nomination/approval/reject/revoke HTTP surface (Task 7), session-swap mechanics incl. one-active-identity-at-a-time (Task 8), server-side window enforcement (Task 9), act-as entry/exit endpoints (Task 10), banner/shared prop (Tasks 11-12), index UI with 3 tabs (Task 13), nomination UI on both absence types (Task 14), full regression + manual E2E (Task 15). SuperAdmin kill-switch admin screen (spec section 2) and action-level audit trail beyond session start/end (spec's explicit "out of scope for v1") are intentionally not tasked — the former is a small addable extra once the core module is live, not blocking; flagging here rather than silently dropping it.
- **Type consistency:** `Substitution::isWithinWindow()` (Task 2) is used identically in `SubstitutionService` (Task 5), `ActingAsService` (Task 8), and the middleware (Task 9). `ActingAsService::currentSubstitution()` (Task 8) is the single source both the middleware (Task 9) and the Inertia prop (Task 11) read from — no duplicate session-key logic. Route names (`hr.substitutions.index/store/approve/reject/revoke/act-as.start/act-as.exit`) are consistent across Tasks 7, 10, 12, 13, 14.
- **Scope check:** single cohesive subsystem (grant lifecycle + session-swap mechanic), appropriately one plan; frontend split into 3 small tasks (banner, index page, Show-page panels) rather than one large task, matching file-boundary guidance.
