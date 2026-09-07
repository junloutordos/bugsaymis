# IPCR V2 Workflow/Audit/Signature/Notification Polish — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Layer a per-record audit timeline, PIN-based digital signature, admin-only reopen of locked records, email/in-app notifications, generation-flow polish, and a "Comments and Recommendations" section onto the already-working IPCR V2 module, without touching its status set or item-generation logic.

**Architecture:** `IpcrV2WorkflowService::transition()` becomes the single choke point that writes an append-only `ipcr_v2_status_logs` row and fires notifications on every status change; controllers gain PIN verification (via a new `DigitalSignatureService::assertSigningPin()` helper) on signing moments and required `remarks` on return moments; a new admin-only `reopen()` action bypasses the normal transition allow-list under its own guard; Vue Show pages get a reusable PIN-confirm composable, a status timeline component, and a Comments/rating-date block on the existing Rating Summary.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, MySQL 8, existing `NotificationService`/`RequestStatusNotification` (in-app + Soketi broadcast), Laravel Mailables, SweetAlert2 (already a dependency).

**Spec:** `docs/superpowers/specs/2026-09-08-ipcr-v2-workflow-polish-design.md`

## Global Constraints

- Never modify `IpcrV2WorkflowService::TRANSITIONS` or any `STATUS_*` string value — the v1 status set is reused exactly, per the spec.
- Signing moments requiring PIN verification (`DigitalSignatureService::assertSigningPin()`): `EmployeeIpcrV2Controller::submitForReview`, `submitForRating`; `DivisionChiefIpcrV2Controller::approveTargets`; `PMTIpcrV2Controller::approve`, `directorSign` (via `IpcrV2WorkflowService::finalize()`).
- Return/reject actions require `remarks` (string, required) but **no** PIN: `DivisionChiefIpcrV2Controller::disapproveTargets`, `PMTIpcrV2Controller::returnForRevision`.
- Housekeeping forwards (no PIN, remarks optional, still logged): `DivisionChiefIpcrV2Controller::submitToPMT`, `HRIpcrV2Controller::submitToPMT`/`batchSubmitToPMT`.
- `ipcr_v2_status_logs` records workflow-level transitions and the reopen action only. Individual item-rating edits (`rateCoreItem`/`rateSupportItem`) are **not** logged there — the item tables already carry their own `updated_at`, and logging every field edit would spam the timeline.
- Every `IpcrV2WorkflowService::transition()` call added or edited in this plan uses PHP 8 **named arguments** for the new optional params (`actor:`, `remarks:`, `actionType:`, `signedViaPin:`) — never positional — to avoid argument-order mistakes as the signature grows.
- No route or controller for the `Submitted to HR` status exists anywhere in IPCR V2 today (confirmed by repo-wide search) — that path is a pre-existing, out-of-scope gap and is **not** built in this plan.
- No changes to `IpcrV2GenerationService`'s item-generation math, `StrategicFunctionService`, `EmployeeFunctionSyncService`, or any v1 (`EmployeeIPCR`/`IPCRWorkflowService`/v1 Mail classes/v1 Blade or Vue pages) code path.
- Admin reopen reuses the existing `role:Administrator` middleware convention already used by every other Admin IPCR V2 route (`routes/ipcr-v2.php:55-58`) rather than a new permission — Administrator already bypasses all permission checks via `isSuperAdmin()`, so a dedicated permission would be redundant.
- Migrations are additive only (new nullable columns, new table) — safe under the project's blue-green expand/contract rule, no destructive changes.
- Run artisan/tests via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan <command>"` (dev service name is `php`, not `app`).

---

## Task 1: Migration — extend `ipcr_v2_records`

**Files:**
- Create: `database/migrations/2026_09_08_100000_add_workflow_polish_columns_to_ipcr_v2_records_table.php`
- Test: `tests/Feature/IPCRV2/IpcrV2MigrationsTest.php` (extend existing file)

**Interfaces:**
- Produces: columns `remarks`, `locked_at`, `locked_by_id`, `reopened_at`, `reopened_by_id`, `reopen_reason`, `comments_recommendations` on `ipcr_v2_records`, all nullable.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/IPCRV2/IpcrV2MigrationsTest.php`:

```php
public function test_ipcr_v2_records_has_workflow_polish_columns(): void
{
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('ipcr_v2_records', [
        'remarks', 'locked_at', 'locked_by_id', 'reopened_at', 'reopened_by_id',
        'reopen_reason', 'comments_recommendations',
    ]));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ipcr_v2_records_has_workflow_polish_columns"`
Expected: FAIL — columns don't exist yet.

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
        Schema::table('ipcr_v2_records', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('status');
            $table->timestamp('locked_at')->nullable()->after('director_signature');
            $table->foreignId('locked_by_id')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('locked_by_id');
            $table->foreignId('reopened_by_id')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by_id');
            $table->text('comments_recommendations')->nullable()->after('reopen_reason');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by_id');
            $table->dropConstrainedForeignId('reopened_by_id');
            $table->dropColumn(['remarks', 'locked_at', 'reopened_at', 'reopen_reason', 'comments_recommendations']);
        });
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_08_100000_add_workflow_polish_columns_to_ipcr_v2_records_table.php"`

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ipcr_v2_records_has_workflow_polish_columns"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_08_100000_add_workflow_polish_columns_to_ipcr_v2_records_table.php tests/Feature/IPCRV2/IpcrV2MigrationsTest.php
git commit -m "feat(ipcr-v2): add remarks/lock/reopen/comments columns to ipcr_v2_records"
```

---

## Task 2: `ipcr_v2_status_logs` table + `IpcrV2StatusLog` model + `IpcrV2Record` relations

**Files:**
- Create: `database/migrations/2026_09_08_100010_create_ipcr_v2_status_logs_table.php`
- Create: `app/Models/IPCRV2/IpcrV2StatusLog.php`
- Modify: `app/Models/IPCRV2/IpcrV2Record.php:15-32` (fillable/casts), add relations
- Test: `tests/Feature/IPCRV2/IpcrV2ModelsTest.php` (extend existing file)

**Interfaces:**
- Produces: `IpcrV2StatusLog::create(array $attrs)`, `IpcrV2Record::statusLogs()` (hasMany, ordered by `created_at`), `IpcrV2Record::lockedBy()`, `IpcrV2Record::reopenedBy()` (belongsTo `User`).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/IPCRV2/IpcrV2ModelsTest.php`:

```php
public function test_ipcr_v2_record_has_status_logs_relation(): void
{
    $user = \App\Models\User::factory()->create();
    $period = \App\Models\IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

    \App\Models\IPCRV2\IpcrV2StatusLog::create([
        'ipcr_v2_record_id' => $record->id,
        'from_status' => 'New Target',
        'to_status' => 'For Review',
        'action_type' => 'submitted',
        'actor_id' => $user->id,
    ]);

    $this->assertCount(1, $record->fresh()->statusLogs);
    $this->assertSame('submitted', $record->statusLogs->first()->action_type);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ipcr_v2_record_has_status_logs_relation"`
Expected: FAIL — table/model don't exist.

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
        Schema::create('ipcr_v2_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_record_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action_type');
            $table->text('remarks')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->boolean('signed_via_pin')->default(false);
            $table->text('signature_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_status_logs');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_08_100010_create_ipcr_v2_status_logs_table.php"`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models\IPCRV2;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IpcrV2StatusLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'ipcr_v2_status_logs';

    protected $fillable = [
        'ipcr_v2_record_id', 'from_status', 'to_status', 'action_type',
        'remarks', 'actor_id', 'actor_role', 'signed_via_pin', 'signature_snapshot',
    ];

    protected $casts = [
        'signed_via_pin' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_record_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
```

- [ ] **Step 5: Update `IpcrV2Record`**

Modify `app/Models/IPCRV2/IpcrV2Record.php`:

```php
protected $fillable = [
    'user_id', 'rating_period_id', 'status',
    'submitted_for_review_at', 'target_approved_at', 'submitted_for_rating_at',
    'submitted_rating_at', 'submitted_for_pmtreview_at', 'submitted_to_hr_at',
    'director_signed_at', 'director_signature',
    'final_numeric_rating', 'final_adjectival_rating',
    'remarks', 'locked_at', 'locked_by_id', 'reopened_at', 'reopened_by_id',
    'reopen_reason', 'comments_recommendations',
];

protected $casts = [
    'submitted_for_review_at' => 'datetime',
    'target_approved_at' => 'datetime',
    'submitted_for_rating_at' => 'datetime',
    'submitted_rating_at' => 'datetime',
    'submitted_for_pmtreview_at' => 'datetime',
    'submitted_to_hr_at' => 'datetime',
    'director_signed_at' => 'datetime',
    'final_numeric_rating' => 'decimal:2',
    'locked_at' => 'datetime',
    'reopened_at' => 'datetime',
];
```

Add after `coachingSessions()`:

```php
public function statusLogs()
{
    return $this->hasMany(IpcrV2StatusLog::class, 'ipcr_v2_record_id')->orderBy('created_at');
}

public function lockedBy()
{
    return $this->belongsTo(User::class, 'locked_by_id');
}

public function reopenedBy()
{
    return $this->belongsTo(User::class, 'reopened_by_id');
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ipcr_v2_record_has_status_logs_relation"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_08_100010_create_ipcr_v2_status_logs_table.php app/Models/IPCRV2/IpcrV2StatusLog.php app/Models/IPCRV2/IpcrV2Record.php tests/Feature/IPCRV2/IpcrV2ModelsTest.php
git commit -m "feat(ipcr-v2): add ipcr_v2_status_logs table and model relations"
```

---

## Task 3: `DigitalSignatureService::assertSigningPin()`

**Files:**
- Modify: `app/Services/DigitalSignatureService.php` (add method)
- Test: Create `tests/Unit/DigitalSignatureServiceAssertPinTest.php`

**Interfaces:**
- Produces: `DigitalSignatureService::assertSigningPin(User $user, ?string $pin): void` — no-op if the user has no `signature_pin` set; throws `ValidationException` (key `pin`) if a PIN is set but `$pin` is empty or wrong.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DigitalSignatureServiceAssertPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_op_when_user_has_no_pin_set(): void
    {
        $user = User::factory()->create(['signature_pin' => null]);
        (new DigitalSignatureService())->assertSigningPin($user, null);
        $this->assertTrue(true);
    }

    public function test_throws_when_pin_set_but_none_given(): void
    {
        $user = User::factory()->create(['signature_pin' => Hash::make('123456')]);
        $this->expectException(ValidationException::class);
        (new DigitalSignatureService())->assertSigningPin($user, null);
    }

    public function test_throws_when_pin_set_and_wrong_pin_given(): void
    {
        $user = User::factory()->create(['signature_pin' => Hash::make('123456')]);
        $this->expectException(ValidationException::class);
        (new DigitalSignatureService())->assertSigningPin($user, '000000');
    }

    public function test_passes_when_correct_pin_given(): void
    {
        $user = User::factory()->create(['signature_pin' => Hash::make('123456')]);
        (new DigitalSignatureService())->assertSigningPin($user, '123456');
        $this->assertTrue(true);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DigitalSignatureServiceAssertPinTest"`
Expected: FAIL — method doesn't exist.

- [ ] **Step 3: Implement**

Add to `app/Services/DigitalSignatureService.php`, near `verifyPin()`, and add `use Illuminate\Validation\ValidationException;` to the top imports:

```php
/**
 * Enforce PIN verification for a signing action. No-op if the user has
 * never set a signature PIN (PIN signing is opt-in — matches the pattern
 * already live in IssuanceController::assertSigningPin()).
 */
public function assertSigningPin(User $user, ?string $pin): void
{
    if (empty($user->signature_pin)) {
        return;
    }

    if (empty($pin) || ! $this->verifyPin($user, $pin)) {
        throw ValidationException::withMessages([
            'pin' => 'The digital signature PIN is incorrect.',
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DigitalSignatureServiceAssertPinTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/DigitalSignatureService.php tests/Unit/DigitalSignatureServiceAssertPinTest.php
git commit -m "feat(digital-signature): add reusable assertSigningPin() helper"
```

---

## Task 4: `IpcrV2WorkflowService` — `logAction()`, updated `transition()`, updated `finalize()`

**Files:**
- Modify: `app/Services/IPCRV2/IpcrV2WorkflowService.php`
- Test: `tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php` (extend existing file)

**Interfaces:**
- Consumes: `IpcrV2StatusLog::create()` (Task 2), `DigitalSignatureService::assertSigningPin()` (Task 3).
- Produces:
  - `IpcrV2WorkflowService::logAction(IpcrV2Record $ipcr, ?User $actor, string $actionType, ?string $fromStatus, ?string $toStatus, ?string $remarks = null, bool $signedViaPin = false): IpcrV2StatusLog`
  - `IpcrV2WorkflowService::transition(IpcrV2Record $ipcr, string $to, array $extra = [], ?string $auditAction = null, ?User $actor = null, ?string $remarks = null, string $actionType = 'status_changed', bool $signedViaPin = false): IpcrV2Record`
  - `IpcrV2WorkflowService::finalize(IpcrV2Record $ipcr, User $director, ?string $pin = null): IpcrV2Record`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php`:

```php
public function test_transition_writes_a_status_log_row(): void
{
    $user = User::factory()->create();
    $actor = User::factory()->create();
    $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $this->period()->id]);
    $service = new IpcrV2WorkflowService();

    $service->transition($record, IpcrV2WorkflowService::STATUS_FOR_REVIEW, actor: $actor, actionType: 'submitted');

    $log = $record->fresh()->statusLogs->first();
    $this->assertNotNull($log);
    $this->assertSame('New Target', $log->from_status);
    $this->assertSame('For Review', $log->to_status);
    $this->assertSame('submitted', $log->action_type);
    $this->assertSame($actor->id, $log->actor_id);
}

public function test_transition_sets_the_remarks_column_to_the_latest_remark(): void
{
    $user = User::factory()->create();
    $record = IpcrV2Record::create([
        'user_id' => $user->id, 'rating_period_id' => $this->period()->id,
        'status' => IpcrV2WorkflowService::STATUS_FOR_REVIEW,
    ]);
    $service = new IpcrV2WorkflowService();

    $service->transition($record, IpcrV2WorkflowService::STATUS_RETURNED, remarks: 'Please fix the target wording.', actionType: 'returned');

    $this->assertSame('Please fix the target wording.', $record->fresh()->remarks);
}

public function test_log_action_writes_a_row_without_changing_status(): void
{
    $user = User::factory()->create();
    $actor = User::factory()->create();
    $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $this->period()->id]);
    $service = new IpcrV2WorkflowService();

    $service->logAction($record, $actor, 'rated', 'Submitted for Rating', 'Rated & For PMT Review');

    $this->assertSame('New Target', $record->fresh()->status);
    $this->assertCount(1, $record->fresh()->statusLogs);
}

public function test_finalize_sets_lock_columns_and_signed_log_entry(): void
{
    IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
    \App\Models\AgencyOutcome::create(['outcome' => 'A']);
    \App\Models\OPCR\OpcrIndicator::create([
        'fiscal_year' => 2026,
        'agency_outcome_id' => \App\Models\AgencyOutcome::first()->id,
        'description' => 'x', 'rating_average' => 4.0,
    ]);

    $director = User::factory()->create(['electronic_signature' => 'sig.png', 'signature_pin' => null]);
    $user = User::factory()->create();
    $record = IpcrV2Record::create([
        'user_id' => $user->id, 'rating_period_id' => IPCRRatingPeriod::first()->id,
        'status' => IpcrV2WorkflowService::STATUS_PMT_APPROVED,
    ]);
    $record->coreItems()->create(['label' => 'x', 'weight_percent' => 100, 'row_average' => 4.0]);

    $service = new IpcrV2WorkflowService();
    $service->finalize($record, $director);

    $fresh = $record->fresh();
    $this->assertNotNull($fresh->locked_at);
    $this->assertSame($director->id, $fresh->locked_by_id);
    $this->assertSame('signed', $fresh->statusLogs->last()->action_type);
}

public function test_finalize_rejects_a_wrong_pin(): void
{
    IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
    $director = User::factory()->create(['signature_pin' => \Illuminate\Support\Facades\Hash::make('123456')]);
    $user = User::factory()->create();
    $record = IpcrV2Record::create([
        'user_id' => $user->id, 'rating_period_id' => IPCRRatingPeriod::first()->id,
        'status' => IpcrV2WorkflowService::STATUS_PMT_APPROVED,
    ]);

    $service = new IpcrV2WorkflowService();
    $this->expectException(ValidationException::class);
    $service->finalize($record, $director, '000000');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrV2WorkflowServiceTest"`
Expected: FAIL — `logAction()` doesn't exist, `transition()`/`finalize()` don't accept the new params.

- [ ] **Step 3: Implement**

Replace the full contents of `app/Services/IPCRV2/IpcrV2WorkflowService.php`:

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\Division;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2StatusLog;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DigitalSignatureService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Status-machine authority for IpcrV2Record — literally mirrors
 * IPCRWorkflowService's constants/transitions/immutability rules, per the
 * spec's "reuse v1's mechanism" mandate. Supervisor-chain resolution
 * (immediateSupervisorFor / division-chief lookup) is NOT re-implemented
 * here — it's delegated to the existing IPCRWorkflowService, which already
 * operates on User and has no EmployeeIPCR-specific coupling.
 */
class IpcrV2WorkflowService
{
    public const STATUS_NEW_TARGET       = 'New Target';
    public const STATUS_FOR_REVIEW       = 'For Review';
    public const STATUS_RETURNED         = 'Returned for Revision';
    public const STATUS_TARGETS_APPROVED = 'Targets Approved';
    public const STATUS_FOR_RATING       = 'Submitted for Rating';
    public const STATUS_RATED            = 'Rated & For PMT Review';
    public const STATUS_SUBMITTED_HR     = 'Submitted to HR';
    public const STATUS_SUBMITTED_PMT    = 'Submitted to PMT';
    public const STATUS_PMT_RETURNED     = 'PMT Returned for Revision';
    public const STATUS_PMT_APPROVED     = 'Approved by PMT';
    public const STATUS_DIRECTOR_SIGNED  = 'Director Signed';

    public const TRANSITIONS = [
        self::STATUS_NEW_TARGET       => [self::STATUS_FOR_REVIEW],
        self::STATUS_FOR_REVIEW       => [self::STATUS_TARGETS_APPROVED, self::STATUS_RETURNED],
        self::STATUS_RETURNED         => [self::STATUS_FOR_REVIEW],
        self::STATUS_TARGETS_APPROVED => [self::STATUS_FOR_RATING],
        self::STATUS_FOR_RATING       => [self::STATUS_RATED, self::STATUS_TARGETS_APPROVED],
        self::STATUS_RATED            => [self::STATUS_SUBMITTED_HR, self::STATUS_SUBMITTED_PMT, self::STATUS_TARGETS_APPROVED],
        self::STATUS_SUBMITTED_HR     => [self::STATUS_SUBMITTED_PMT],
        self::STATUS_SUBMITTED_PMT    => [self::STATUS_PMT_APPROVED, self::STATUS_PMT_RETURNED],
        self::STATUS_PMT_RETURNED     => [self::STATUS_TARGETS_APPROVED],
        self::STATUS_PMT_APPROVED     => [self::STATUS_DIRECTOR_SIGNED],
        self::STATUS_DIRECTOR_SIGNED  => [],
    ];

    public function __construct(
        private ?IPCRWorkflowService $chain = null,
        private ?IpcrV2RatingService $rating = null,
        private ?DigitalSignatureService $signature = null
    ) {
        $this->chain ??= app(IPCRWorkflowService::class);
        $this->rating ??= app(IpcrV2RatingService::class);
        $this->signature ??= app(DigitalSignatureService::class);
    }

    public function assertMutable(IpcrV2Record $ipcr): void
    {
        abort_if($ipcr->isFinalized(), 403, 'This IPCR V2 has been signed by the Director and is final.');
        abort_if($ipcr->isPeriodClosed(), 403, 'The rating period for this IPCR V2 is closed.');
    }

    public function assertOwner(User $user, IpcrV2Record $ipcr): void
    {
        abort_if($ipcr->user_id !== $user->id, 403, 'You can only modify your own IPCR V2.');
    }

    public function canManage(User $user, IpcrV2Record $ipcr): bool
    {
        $ipcr->loadMissing('user');
        if (! $ipcr->user) {
            return $user->hasRole('OCD');
        }

        $supervisor = $this->chain->immediateSupervisorFor($ipcr->user);

        return $user->hasRole('OCD') || ($supervisor && $supervisor->id === $user->id);
    }

    public function assertCanManage(User $user, IpcrV2Record $ipcr): void
    {
        abort_unless($this->canManage($user, $ipcr), 403, "You are not this employee's immediate supervisor and cannot act on this IPCR V2.");
    }

    public function canEndorse(User $user, IpcrV2Record $ipcr): bool
    {
        $ipcr->loadMissing('user');
        $divisionChiefId = Division::where('id', $ipcr->user?->division_id)->value('division_chief_id');

        return $user->hasRole('OCD') || ($divisionChiefId && $user->id == $divisionChiefId);
    }

    public function assertCanEndorse(User $user, IpcrV2Record $ipcr): void
    {
        abort_unless($this->canEndorse($user, $ipcr), 403, "You are not this employee's Division Chief and cannot endorse this IPCR V2.");
    }

    public function transition(
        IpcrV2Record $ipcr,
        string $to,
        array $extra = [],
        ?string $auditAction = null,
        ?User $actor = null,
        ?string $remarks = null,
        string $actionType = 'status_changed',
        bool $signedViaPin = false,
    ): IpcrV2Record {
        $this->assertMutable($ipcr);

        $updated = DB::transaction(function () use ($ipcr, $to, $extra, $auditAction, $actor, $remarks, $actionType, $signedViaPin) {
            $fresh = IpcrV2Record::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $fresh->status;

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(in_array($to, $allowed, true), 403, "Invalid IPCR V2 status change: \"{$fresh->status}\" cannot move to \"{$to}\".");

            $fresh->update(array_merge($extra, ['status' => $to, 'remarks' => $remarks]));

            AuditLogger::log([
                'action' => $auditAction ?? 'ipcr_v2_status_changed',
                'auditable_type' => IpcrV2Record::class,
                'auditable_id' => $fresh->id,
                'new_values' => array_merge(['status' => $to], $extra),
            ]);

            $this->logAction($fresh, $actor, $actionType, $fromStatus, $to, $remarks, $signedViaPin);

            $ipcr->refresh();

            return $ipcr;
        });

        $this->notifyOnTransition($updated, $to, $remarks);

        return $updated;
    }

    /**
     * Write a timeline row without necessarily changing status — used
     * internally by transition() and directly by callers logging a
     * non-status-changing milestone (e.g. Admin's reopen action).
     */
    public function logAction(
        IpcrV2Record $ipcr,
        ?User $actor,
        string $actionType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $remarks = null,
        bool $signedViaPin = false,
    ): IpcrV2StatusLog {
        return IpcrV2StatusLog::create([
            'ipcr_v2_record_id' => $ipcr->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_type' => $actionType,
            'remarks' => $remarks,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->roles->pluck('name')->implode(', ') ?: null,
            'signed_via_pin' => $signedViaPin,
            'signature_snapshot' => $signedViaPin ? $actor?->electronic_signature : null,
        ]);
    }

    public function assertPeriodAcceptsNewTargets(IPCRRatingPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'rating_period_id' => "The rating period \"{$period->label}\" is closed and no longer accepts IPCR V2 records.",
            ]);
        }
    }

    public function assertNoDuplicateForPeriod(int $userId, int $periodId, ?int $ignoreId = null): void
    {
        $exists = IpcrV2Record::where('user_id', $userId)
            ->where('rating_period_id', $periodId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'rating_period_id' => 'This employee already has an IPCR V2 for this rating period.',
            ]);
        }
    }

    public function finalize(IpcrV2Record $ipcr, User $director, ?string $pin = null): IpcrV2Record
    {
        $this->signature->assertSigningPin($director, $pin);

        $finalNumeric = $this->rating->computeFinalRating($ipcr);

        return $this->transition($ipcr, self::STATUS_DIRECTOR_SIGNED, [
            'director_signed_at' => now(),
            'director_signature' => $director->electronic_signature,
            'final_numeric_rating' => $finalNumeric,
            'final_adjectival_rating' => $this->rating->adjectivalRating($finalNumeric),
            'locked_at' => now(),
            'locked_by_id' => $director->id,
        ], 'ipcr_v2_director_signed', actor: $director, actionType: 'signed', signedViaPin: ! empty($director->signature_pin));
    }

    /**
     * Resolve recipient(s) for a transition and fire in-app + email
     * notifications. Silent (no recipients) for internal milestones that
     * don't need a separate notice — STATUS_RATED is immediately followed
     * by a STATUS_SUBMITTED_PMT transition in the same request
     * (DivisionChiefIpcrV2Controller::submitToPMT), which does notify.
     */
    private function notifyOnTransition(IpcrV2Record $ipcr, string $to, ?string $remarks): void
    {
        $ipcr->loadMissing('user', 'period');
        $employee = $ipcr->user;
        if (! $employee) {
            return;
        }

        $recipients = match ($to) {
            self::STATUS_FOR_REVIEW, self::STATUS_FOR_RATING => array_filter([$this->chain->immediateSupervisorFor($employee)]),
            self::STATUS_TARGETS_APPROVED, self::STATUS_RETURNED, self::STATUS_PMT_RETURNED,
            self::STATUS_PMT_APPROVED, self::STATUS_DIRECTOR_SIGNED => [$employee],
            self::STATUS_SUBMITTED_PMT => User::havingRole('PMT')->get()->all(),
            default => [],
        };

        foreach (array_filter($recipients) as $recipient) {
            \App\Services\NotificationService::notifyUser(
                $recipient,
                'IPCR V2',
                $ipcr->period?->label ?? "IPCR V2 #{$ipcr->id}",
                $to,
                $this->urlFor($recipient, $ipcr),
                $remarks,
            );

            \Illuminate\Support\Facades\Mail::to($recipient->email)->queue(
                new \App\Mail\IpcrV2StatusMail($ipcr, $recipient, $to, $remarks)
            );
        }
    }

    private function urlFor(User $recipient, IpcrV2Record $ipcr): string
    {
        return match (true) {
            $recipient->hasAnyRole(['OCD', 'PMT']) => route('pmt-ipcr-v2.show', $ipcr->id),
            $recipient->hasRole('DivisionChief') => route('division-chief-ipcr-v2.show', $ipcr->id),
            $recipient->hasRole('HR') => route('hr-ipcr-v2.show', $ipcr->id),
            default => route('employee-ipcr-v2.show', $ipcr->id),
        };
    }
}
```

Note: `notifyOnTransition()` and `urlFor()` reference `App\Mail\IpcrV2StatusMail`, which doesn't exist until Task 6 — that's fine, PHP resolves the class name at call time, not at file-parse time, so Tasks 4-5 tests pass without it existing yet as long as no transition test actually reaches a real recipient (the tests in this task use bare `User::factory()->create()` with no division/role, so `notifyOnTransition()`'s recipient list is empty and the `Mail::to()` line is never reached).

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrV2WorkflowServiceTest"`
Expected: PASS (all, including the 3 pre-existing tests — `test_transition_moves_new_target_to_for_review`, `test_transition_rejects_invalid_move`, `test_assert_mutable_blocks_a_finalized_record`, `test_assert_no_duplicate_for_period_throws_on_second_record`)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2WorkflowService.php tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php
git commit -m "feat(ipcr-v2): audit-log every transition, lock columns on finalize, wire notifications"
```

---

## Task 5: `App\Mail\IpcrV2StatusMail` + blade view

**Files:**
- Create: `app/Mail/IpcrV2StatusMail.php`
- Create: `resources/views/emails/ipcr-v2/status_changed.blade.php`
- Test: Create `tests/Feature/IPCRV2/IpcrV2StatusMailTest.php`

**Interfaces:**
- Consumes: `App\Models\IPCRV2\IpcrV2Record`, `App\Models\User` (already exist).
- Produces: `new IpcrV2StatusMail(IpcrV2Record $ipcr, User $recipient, string $newStatus, ?string $remarks = null)` — a `Mailable` with `build()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Mail\IpcrV2StatusMail;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2StatusMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_status_and_remarks(): void
    {
        $employee = User::factory()->create(['name' => 'Jane Faculty']);
        $recipient = User::factory()->create(['name' => 'John Chief']);
        $period = IPCRRatingPeriod::create(['label' => 'SY 2026-2027', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $record->load('user', 'period');

        $mail = new IpcrV2StatusMail($record, $recipient, 'For Review', 'Please review promptly.');
        $rendered = $mail->render();

        $this->assertStringContainsString('John Chief', $rendered);
        $this->assertStringContainsString('For Review', $rendered);
        $this->assertStringContainsString('Please review promptly.', $rendered);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrV2StatusMailTest"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Implement**

`app/Mail/IpcrV2StatusMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IpcrV2StatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public IpcrV2Record $ipcr,
        public User $recipient,
        public string $newStatus,
        public ?string $remarks = null,
    ) {}

    public function build()
    {
        return $this->subject("IPCR V2 — {$this->newStatus}")
            ->view('emails.ipcr-v2.status_changed');
    }
}
```

`resources/views/emails/ipcr-v2/status_changed.blade.php`:

```blade
@extends('emails.layouts.base')

@section('header-title', 'IPCR V2 Status Update')
@section('header-subtitle', 'Individual Performance Commitment and Review — V2')

@section('content')
<p class="greeting">Dear <strong>{{ $recipient->name }}</strong>,</p>
<p class="lead">The IPCR V2 for <strong>{{ $ipcr->user->name }}</strong> is now <strong>{{ $newStatus }}</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->period?->label ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-blue">{{ $newStatus }}</span></td></tr>
    <tr><td class="lbl">Date</td><td class="val">{{ now()->format('F j, Y, g:i A') }}</td></tr>
</table>

@if(!empty($remarks))
<div class="callout callout-amber">
    <div class="callout-title">Remarks</div>
    {{ $remarks }}
</div>
@endif
@endsection
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrV2StatusMailTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Mail/IpcrV2StatusMail.php resources/views/emails/ipcr-v2/status_changed.blade.php tests/Feature/IPCRV2/IpcrV2StatusMailTest.php
git commit -m "feat(ipcr-v2): add parametrized status-change Mailable"
```

---

## Task 6: `EmployeeIpcrV2Controller` — PIN on submit actions

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php:93-109` (submitForReview, submitForRating), `:31-42` (index), `:44-68` (show)
- Test: `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `DigitalSignatureService::assertSigningPin()` (Task 3), `IpcrV2WorkflowService::transition()` new signature (Task 4).
- Produces: `index`/`show` Inertia props gain `hasPin: bool`, `signatureUri: ?string`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php` (adjust `use` imports as needed to match the file's existing style):

```php
public function test_submit_for_review_requires_correct_pin_when_pin_is_set(): void
{
    $role = \App\Models\Role::create(['name' => 'Faculty']);
    $ids = collect(['ipcr.v2.view', 'ipcr.v2.submit'])
        ->map(fn ($name) => \App\Models\Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
    $role->permissions()->attach($ids);

    $user = User::factory()->create(['signature_pin' => \Illuminate\Support\Facades\Hash::make('123456')]);
    $user->roles()->attach($role->id);
    $period = \App\Models\IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

    $wrong = $this->actingAs($user)->post(route('employee-ipcr-v2.submitReview', $record->id), ['pin' => '000000']);
    $wrong->assertSessionHasErrors('pin');
    $this->assertSame('New Target', $record->fresh()->status);

    $right = $this->actingAs($user)->post(route('employee-ipcr-v2.submitReview', $record->id), ['pin' => '123456']);
    $right->assertRedirect();
    $this->assertSame('For Review', $record->fresh()->status);
}

public function test_submit_for_review_succeeds_without_pin_when_no_pin_set(): void
{
    $role = \App\Models\Role::create(['name' => 'Faculty']);
    $ids = collect(['ipcr.v2.view', 'ipcr.v2.submit'])
        ->map(fn ($name) => \App\Models\Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
    $role->permissions()->attach($ids);

    $user = User::factory()->create(['signature_pin' => null]);
    $user->roles()->attach($role->id);
    $period = \App\Models\IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

    $response = $this->actingAs($user)->post(route('employee-ipcr-v2.submitReview', $record->id));
    $response->assertRedirect();
    $this->assertSame('For Review', $record->fresh()->status);
}
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrV2ControllerTest"` — check the file's existing test setup style (roles/permissions) first with Read before writing, since exact helper conventions may already exist there; adapt the snippets above to match rather than duplicating a different setup pattern.

- [ ] **Step 2: Run tests to verify they fail**

Expected: FAIL — controller doesn't validate/verify `pin` yet.

- [ ] **Step 3: Implement**

In `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`, add `use App\Services\DigitalSignatureService;` to imports and inject it via constructor:

```php
public function __construct(
    private IpcrV2WorkflowService $workflow,
    private IpcrV2GenerationService $generation,
    private StrategicFunctionService $strategic,
    private \App\Services\PerformanceManagement\IPCRWorkflowService $v1Chain,
    private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService,
    private DigitalSignatureService $sigService = new DigitalSignatureService()
) {}
```

Replace `index()` and `show()`:

```php
public function index(Request $request)
{
    $records = IpcrV2Record::where('user_id', $request->user()->id)
        ->with('period')
        ->latest('id')
        ->get();

    return Inertia::render('IPCRV2/EmployeeIpcrV2Index', [
        'records' => $records,
        'openPeriods' => IPCRRatingPeriod::open()->get(['id', 'label', 'year', 'semester']),
    ]);
}

public function show(Request $request, int $id)
{
    $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'statusLogs.actor'])->findOrFail($id);

    $isOwner = $record->user_id === $request->user()->id;
    abort_unless(
        $isOwner || $this->workflow->canManage($request->user(), $record),
        403,
        "You are not this employee's immediate supervisor and cannot view this IPCR V2."
    );

    $supervisor = $this->v1Chain->immediateSupervisorFor($record->user)
        ?? ($record->user->hasRole('DivisionChief') ? \App\Models\User::havingRole('OCD')->first() : null);
    $ocdUser = \App\Models\User::havingRole('OCD')->first();

    return Inertia::render('IPCRV2/EmployeeIpcrV2Show', [
        'ipcr' => $record,
        'strategicIndicators' => $this->strategic->currentIndicators(),
        'supervisor' => $supervisor?->only('name', 'position'),
        'ocdUser' => $ocdUser?->only('name', 'position'),
        'summary' => $this->summaryService->buildRows($record),
        'isOwner' => $isOwner,
        'isMutable' => $record->isMutable(),
        'hasPin' => ! empty($request->user()->signature_pin),
        'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
    ]);
}
```

Replace `submitForReview()` and `submitForRating()`:

```php
public function submitForReview(Request $request, int $id)
{
    $record = IpcrV2Record::findOrFail($id);
    $this->workflow->assertOwner($request->user(), $record);

    $data = $request->validate(['pin' => 'nullable|string']);
    $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

    $this->workflow->transition(
        $record,
        IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        actor: $request->user(),
        actionType: 'submitted',
        signedViaPin: ! empty($request->user()->signature_pin),
    );

    return back()->with('success', 'Submitted for review.');
}

public function submitForRating(Request $request, int $id)
{
    $record = IpcrV2Record::findOrFail($id);
    $this->workflow->assertOwner($request->user(), $record);

    $data = $request->validate(['pin' => 'nullable|string']);
    $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

    $this->workflow->transition(
        $record,
        IpcrV2WorkflowService::STATUS_FOR_RATING,
        extra: ['submitted_for_rating_at' => now()],
        actor: $request->user(),
        actionType: 'submitted',
        signedViaPin: ! empty($request->user()->signature_pin),
    );

    return back()->with('success', 'Submitted for rating.');
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrV2ControllerTest"`
Expected: PASS (full file — confirm no regressions in the pre-existing tests too)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): PIN-verify employee submit actions, expose hasPin/signatureUri"
```

---

## Task 7: `DivisionChiefIpcrV2Controller` — PIN, remarks, rated-bridge, comments

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php`
- Modify: `routes/ipcr-v2.php:29-40` (add `updateComments` route)
- Test: `tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `DigitalSignatureService::assertSigningPin()` (Task 3), `IpcrV2WorkflowService::transition()`/`logAction()` (Task 4).
- Produces: `division-chief-ipcr-v2.updateComments` route (PUT); `show()` props gain `hasPin`, `signatureUri`, `statusLogs` (via eager load on `ipcr`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php`:

```php
public function test_disapprove_targets_requires_remarks(): void
{
    $role = Role::create(['name' => 'DivisionChief']);
    $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
        ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
    $role->permissions()->attach($ids);

    $chief = User::factory()->create();
    $chief->roles()->attach($role->id);
    $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
    $employee = User::factory()->create(['division_id' => $division->id]);
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_FOR_REVIEW,
    ]);

    $missing = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.disapproveTargets', $record->id));
    $missing->assertSessionHasErrors('remarks');

    $ok = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.disapproveTargets', $record->id), ['remarks' => 'Please revise.']);
    $ok->assertRedirect();
    $this->assertSame('Returned for Revision', $record->fresh()->status);
    $this->assertSame('Please revise.', $record->fresh()->remarks);
}

public function test_submit_to_pmt_bridges_through_rated_when_starting_from_for_rating(): void
{
    $role = Role::create(['name' => 'DivisionChief']);
    $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
        ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
    $role->permissions()->attach($ids);

    $chief = User::factory()->create();
    $chief->roles()->attach($role->id);
    $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
    $employee = User::factory()->create(['division_id' => $division->id]);
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_FOR_RATING,
    ]);

    $response = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.submitToPMT', $record->id));

    $response->assertRedirect();
    $this->assertSame('Submitted to PMT', $record->fresh()->status);
    $actionTypes = $record->fresh()->statusLogs->pluck('action_type')->all();
    $this->assertSame(['rated', 'submitted'], $actionTypes);
}

public function test_update_comments_saves_while_mutable(): void
{
    $role = Role::create(['name' => 'DivisionChief']);
    $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
        ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
    $role->permissions()->attach($ids);

    $chief = User::factory()->create();
    $chief->roles()->attach($role->id);
    $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
    $employee = User::factory()->create(['division_id' => $division->id]);
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

    $response = $this->actingAs($chief)->put(route('division-chief-ipcr-v2.updateComments', $record->id), [
        'comments_recommendations' => 'Keep up the good work.',
    ]);

    $response->assertRedirect();
    $this->assertSame('Keep up the good work.', $record->fresh()->comments_recommendations);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DivisionChiefIpcrV2ControllerTest"`
Expected: FAIL

- [ ] **Step 3: Implement**

Add the route to `routes/ipcr-v2.php`, inside the existing `permission:ipcr.v2.approve` group (after the `submitToPMT` line at `:36`):

```php
Route::put('/division-chief/ipcr-v2/{id}/comments', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'updateComments'])->name('division-chief-ipcr-v2.updateComments');
```

Replace the full contents of `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php`:

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DivisionChiefIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private StrategicFunctionService $strategic,
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService()
    ) {}

    public function index(Request $request)
    {
        $records = IpcrV2Record::with('user', 'period')
            ->whereHas('user', function ($q) use ($request) {
                $q->where('division_id', $request->user()->division_id);
            })
            ->latest('id')
            ->get();

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Index', ['records' => $records]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions', 'statusLogs.actor'])->findOrFail($id);

        abort_unless(
            $request->user()->hasRole('OCD') || $record->user?->division_id === $request->user()->division_id,
            403,
            'This employee is not in your division.'
        );

        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'isMutable' => $record->isMutable(),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function approveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);

        $data = $request->validate(['pin' => 'nullable|string']);
        $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
            extra: ['target_approved_at' => now()],
            actor: $request->user(),
            actionType: 'approved',
            signedViaPin: ! empty($request->user()->signature_pin),
        );

        return back()->with('success', 'Targets approved.');
    }

    public function disapproveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);

        $data = $request->validate(['remarks' => 'required|string|max:1000']);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_RETURNED,
            actor: $request->user(),
            remarks: $data['remarks'],
            actionType: 'returned',
        );

        return back()->with('success', 'Returned for revision.');
    }

    public function rateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

        // A WDP-tagged materialized row (success_indicator set) is rated
        // Quality/Efficiency/Timeliness like a Support item; an untagged
        // (teaching-load) row keeps the fixed CSC 4-criteria rubric.
        if ($coreItem->success_indicator !== null) {
            $data = $request->validate([
                'quality_rating' => 'required|integer|min:1|max:5',
                'efficiency_rating' => 'required|integer|min:1|max:5',
                'timeliness_rating' => 'required|integer|min:1|max:5',
                'remarks' => 'nullable|string|max:1000',
            ]);

            $data['row_average'] = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

            $coreItem->update($data);

            return back()->with('success', 'Rated.');
        }

        $data = $request->validate([
            'student_feedback_rating' => 'required|integer|min:1|max:5',
            'supervisor_feedback_rating' => 'required|integer|min:1|max:5',
            'im_development_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(
            $data['student_feedback_rating'] * 0.30
            + $data['supervisor_feedback_rating'] * 0.20
            + $data['im_development_rating'] * 0.20
            + $data['timeliness_rating'] * 0.30,
            2
        );

        $coreItem->update($data);

        return back()->with('success', 'Rated.');
    }

    public function rateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'quality_rating' => 'required|integer|min:1|max:5',
            'efficiency_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

        $supportItem->update($data);

        return back()->with('success', 'Rated.');
    }

    /**
     * Moves the record to PMT. A record still sitting in "Submitted for
     * Rating" has no explicit "mark as rated" action in the UI — this
     * bridges STATUS_FOR_RATING -> STATUS_RATED -> STATUS_SUBMITTED_PMT as
     * two audited transitions in one request, rather than adding a new
     * button/route/permission for a milestone with no independent meaning
     * to the Division Chief.
     */
    public function submitToPMT(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanEndorse($request->user(), $record);

        if ($record->status === IpcrV2WorkflowService::STATUS_FOR_RATING) {
            $this->workflow->transition(
                $record,
                IpcrV2WorkflowService::STATUS_RATED,
                actor: $request->user(),
                actionType: 'rated',
            );
            $record = $record->fresh();
        }

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
            extra: ['submitted_for_pmtreview_at' => now()],
            actor: $request->user(),
            actionType: 'submitted',
        );

        return back()->with('success', 'Submitted to PMT.');
    }

    public function updateComments(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        $this->workflow->assertMutable($record);

        $data = $request->validate(['comments_recommendations' => 'nullable|string|max:2000']);
        $record->update($data);

        return back()->with('success', 'Comments saved.');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DivisionChiefIpcrV2ControllerTest"`
Expected: PASS (full file)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php routes/ipcr-v2.php tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): DC PIN/remarks wiring, rated-bridge fix, comments endpoint"
```

---

## Task 8: `PMTIpcrV2Controller` — PIN, remarks, director-sign PIN passthrough

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/PMTIpcrV2Controller.php`
- Test: `tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `DigitalSignatureService::assertSigningPin()` (Task 3), `IpcrV2WorkflowService::transition()`/`finalize()` (Task 4).
- Produces: `show()` props gain `hasPin`, `signatureUri`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php` already has a private `pmtUser(): User` helper (role `PMT`, permissions `ipcr.v2.view`/`ipcr.v2.approve`/`ipcr.v2.monitor`). Add these tests to the class, and add `use Illuminate\Support\Facades\Hash;` to the file's imports:

```php
public function test_return_for_revision_requires_remarks(): void
{
    $pmt = $this->pmtUser();
    $employee = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
    ]);

    $missing = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.return', $record->id));
    $missing->assertSessionHasErrors('remarks');
    $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $record->fresh()->status);

    $ok = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.return', $record->id), ['remarks' => 'Needs more detail.']);
    $ok->assertRedirect();
    $this->assertSame(IpcrV2WorkflowService::STATUS_PMT_RETURNED, $record->fresh()->status);
    $this->assertSame('Needs more detail.', $record->fresh()->remarks);
}

public function test_director_sign_requires_correct_pin_when_pin_is_set(): void
{
    $pmt = $this->pmtUser();
    $pmt->update(['signature_pin' => Hash::make('123456')]);
    $employee = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
    \App\Models\AgencyOutcome::create(['outcome' => 'A']);
    \App\Models\OPCR\OpcrIndicator::create([
        'fiscal_year' => 2026,
        'agency_outcome_id' => \App\Models\AgencyOutcome::first()->id,
        'description' => 'x', 'rating_average' => 4.0,
    ]);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_PMT_APPROVED,
    ]);
    $record->coreItems()->create(['label' => 'x', 'weight_percent' => 100, 'row_average' => 4.0]);

    $wrong = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.directorSign', $record->id), ['pin' => '000000']);
    $wrong->assertSessionHasErrors('pin');
    $this->assertSame(IpcrV2WorkflowService::STATUS_PMT_APPROVED, $record->fresh()->status);

    $right = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.directorSign', $record->id), ['pin' => '123456']);
    $right->assertRedirect();
    $this->assertSame(IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED, $record->fresh()->status);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PMTIpcrV2ControllerTest"`
Expected: FAIL

- [ ] **Step 3: Implement**

Replace the full contents of `app/Http/Controllers/IPCRV2/PMTIpcrV2Controller.php`:

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PMTIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService()
    ) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')
            ->whereIn('status', [
                IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                IpcrV2WorkflowService::STATUS_PMT_APPROVED,
                IpcrV2WorkflowService::STATUS_PMT_RETURNED,
            ])
            ->latest('id')
            ->get();

        return Inertia::render('IPCRV2/PMTIpcrV2Index', ['records' => $records]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'statusLogs.actor'])->findOrFail($id);
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/PMTIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'isMutable' => $record->isMutable(),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);

        $data = $request->validate(['pin' => 'nullable|string']);
        $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_PMT_APPROVED,
            actor: $request->user(),
            actionType: 'approved',
            signedViaPin: ! empty($request->user()->signature_pin),
        );

        return back()->with('success', 'Approved by PMT.');
    }

    public function returnForRevision(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);

        $data = $request->validate(['remarks' => 'required|string|max:1000']);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_PMT_RETURNED,
            actor: $request->user(),
            remarks: $data['remarks'],
            actionType: 'returned',
        );

        return back()->with('success', 'Returned for revision.');
    }

    public function directorSign(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $data = $request->validate(['pin' => 'nullable|string']);

        $this->workflow->finalize($record, $request->user(), $data['pin'] ?? null);

        return back()->with('success', 'Director signed.');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PMTIpcrV2ControllerTest"`
Expected: PASS (full file)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/PMTIpcrV2Controller.php tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): PMT PIN/remarks wiring on approve/return/director-sign"
```

---

## Task 9: `HRIpcrV2Controller` — audit-log housekeeping forwards

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/HRIpcrV2Controller.php:39-58`
- Test: `tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `IpcrV2WorkflowService::transition()` new signature (Task 4).

- [ ] **Step 1: Write the failing test**

`tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php` already has a private `hrUser(): User` helper (role `HR`, permissions `ipcr.v2.view`/`ipcr.v2.monitor`/`ipcr.v2.admin`). Add this test to the class:

```php
public function test_submit_to_pmt_writes_a_status_log(): void
{
    $hr = $this->hrUser();
    $employee = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_HR,
    ]);

    $response = $this->actingAs($hr)->post(route('hr-ipcr-v2.submitToPMT', $record->id));

    $response->assertRedirect();
    $this->assertSame('submitted', $record->fresh()->statusLogs->last()->action_type);
    $this->assertSame($hr->id, $record->fresh()->statusLogs->last()->actor_id);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=HRIpcrV2ControllerTest"`
Expected: FAIL

- [ ] **Step 3: Implement**

Replace `submitToPMT()` and `batchSubmitToPMT()` in `app/Http/Controllers/IPCRV2/HRIpcrV2Controller.php`:

```php
public function submitToPMT(Request $request, int $id)
{
    $record = IpcrV2Record::findOrFail($id);
    $this->workflow->transition(
        $record,
        IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
        actor: $request->user(),
        actionType: 'submitted',
    );

    return back()->with('success', 'Submitted to PMT.');
}

public function batchSubmitToPMT(Request $request)
{
    $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:ipcr_v2_records,id']);

    foreach (IpcrV2Record::whereIn('id', $data['ids'])->get() as $record) {
        if ($record->status === IpcrV2WorkflowService::STATUS_SUBMITTED_HR) {
            $this->workflow->transition(
                $record,
                IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                actor: $request->user(),
                actionType: 'submitted',
            );
        }
    }

    return back()->with('success', 'Batch submitted to PMT.');
}
```

Also add `'statusLogs.actor'` to the `show()` method's eager load (`IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions', 'statusLogs.actor'])`).

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=HRIpcrV2ControllerTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/HRIpcrV2Controller.php tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): audit-log HR forward-to-PMT actions"
```

---

## Task 10: `AdminIpcrV2Controller::reopen()`

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php`
- Modify: `routes/ipcr-v2.php:55-58` (add reopen route)
- Test: `tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `DigitalSignatureService::assertSigningPin()` (Task 3), `IpcrV2WorkflowService::logAction()` (Task 4).
- Produces: `POST admin-ipcr-v2.reopen` route; `show()` props gain `hasPin`, `signatureUri`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php` creates its Administrator user inline (no shared helper): `$adminRole = Role::create(['name' => 'Administrator']); $admin = User::factory()->create(); $admin->roles()->attach($adminRole->id);`. Add these tests to the class, and add `use App\Services\IPCRV2\IpcrV2WorkflowService;` to the file's imports:

```php
public function test_reopen_requires_a_reason(): void
{
    $adminRole = Role::create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole->id);

    $employee = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED,
        'locked_at' => now(),
    ]);

    $response = $this->actingAs($admin)->post(route('admin-ipcr-v2.reopen', $record->id));
    $response->assertSessionHasErrors('reason');
}

public function test_reopen_reverts_status_and_clears_lock(): void
{
    $adminRole = Role::create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole->id);

    $employee = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED,
        'locked_at' => now(), 'locked_by_id' => $employee->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin-ipcr-v2.reopen', $record->id), ['reason' => 'Wrong rating entered.']);

    $response->assertRedirect();
    $fresh = $record->fresh();
    $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $fresh->status);
    $this->assertNull($fresh->locked_at);
    $this->assertSame('Wrong rating entered.', $fresh->reopen_reason);
    $this->assertSame('reopened', $fresh->statusLogs->last()->action_type);
}

public function test_reopen_rejects_a_non_finalized_record(): void
{
    $adminRole = Role::create(['name' => 'Administrator']);
    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole->id);

    $employee = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => IpcrV2WorkflowService::STATUS_FOR_REVIEW,
    ]);

    $response = $this->actingAs($admin)->post(route('admin-ipcr-v2.reopen', $record->id), ['reason' => 'x']);
    $response->assertStatus(422);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AdminIpcrV2ControllerTest"`
Expected: FAIL — route/method don't exist.

- [ ] **Step 3: Implement**

Add the route to `routes/ipcr-v2.php`, inside the existing `role:Administrator` group:

```php
Route::post('/admin/ipcr-v2/{id}/reopen', [\App\Http\Controllers\IPCRV2\AdminIpcrV2Controller::class, 'reopen'])->name('admin-ipcr-v2.reopen');
```

Replace the full contents of `app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php`:

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Mail\IpcrV2StatusMail;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminIpcrV2Controller extends Controller
{
    public function __construct(
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private IpcrV2WorkflowService $workflow = new IpcrV2WorkflowService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService()
    ) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/AdminIpcrV2Index', ['records' => $records]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions', 'statusLogs.actor'])->findOrFail($id);
        $ocdUser = User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/AdminIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function reopen(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        abort_unless($record->isFinalized(), 422, 'This IPCR V2 is not locked — nothing to reopen.');

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'pin' => 'nullable|string',
        ]);

        $admin = $request->user();
        $this->sigService->assertSigningPin($admin, $data['pin'] ?? null);

        DB::transaction(function () use ($record, $admin, $data) {
            $fresh = IpcrV2Record::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $fresh->status;

            $fresh->update([
                'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                'locked_at' => null,
                'locked_by_id' => null,
                'reopened_at' => now(),
                'reopened_by_id' => $admin->id,
                'reopen_reason' => $data['reason'],
            ]);

            $this->workflow->logAction(
                $fresh,
                $admin,
                'reopened',
                $fromStatus,
                IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                $data['reason'],
                signedViaPin: ! empty($admin->signature_pin),
            );
        });

        $record->refresh()->loadMissing('user', 'period');
        $this->notifyReopen($record, $data['reason']);

        return back()->with('success', 'IPCR V2 reopened.');
    }

    private function notifyReopen(IpcrV2Record $record, string $reason): void
    {
        $recipients = array_filter(array_merge(
            [$record->user],
            User::havingRole('PMT')->get()->all(),
        ));

        foreach ($recipients as $recipient) {
            NotificationService::notifyUser(
                $recipient,
                'IPCR V2',
                $record->period?->label ?? "IPCR V2 #{$record->id}",
                'Reopened by Administrator',
                route('pmt-ipcr-v2.show', $record->id),
                $reason,
            );
            Mail::to($recipient->email)->queue(new IpcrV2StatusMail($record, $recipient, 'Reopened by Administrator', $reason));
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AdminIpcrV2ControllerTest"`
Expected: PASS (full file)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php routes/ipcr-v2.php tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): admin-only reopen of a locked record, fully audited and notified"
```

---

## Task 11: Empty-functions validation in `IpcrV2GenerationService`

**Files:**
- Modify: `app/Services/IPCRV2/IpcrV2GenerationService.php:18-26`
- Test: `tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php` (extend existing file)

**Interfaces:**
- Produces: `generateTargets()` throws `ValidationException` (key `employee_function`) when the employee has zero core AND zero support `EmployeeFunction` rows, instead of silently creating an empty record.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php`:

```php
public function test_generate_targets_rejects_an_employee_with_no_synced_functions(): void
{
    $user = \App\Models\User::factory()->create();
    $period = \App\Models\IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    (new \App\Services\IPCRV2\IpcrV2GenerationService())->generateTargets($user, $period);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_generate_targets_rejects_an_employee_with_no_synced_functions"`
Expected: FAIL — currently creates an empty record instead of throwing.

- [ ] **Step 3: Implement**

In `app/Services/IPCRV2/IpcrV2GenerationService.php`, modify `generateTargets()`:

```php
public function generateTargets(User $user, IPCRRatingPeriod $period): IpcrV2Record
{
    $this->workflow->assertPeriodAcceptsNewTargets($period);
    $this->workflow->assertNoDuplicateForPeriod($user->id, $period->id);

    $coreFunctions = EmployeeFunction::where('user_id', $user->id)->core()->with('workDistributionPlans:id,success_indicator')->get();
    $supportFunctions = EmployeeFunction::where('user_id', $user->id)->support()->with('workDistributionPlans:id,success_indicator')->get();

    if ($coreFunctions->isEmpty() && $supportFunctions->isEmpty()) {
        throw ValidationException::withMessages([
            'employee_function' => 'No functions are synced for this employee yet — sync Employee Functions first.',
        ]);
    }

    $this->assertCoreWeightsSumTo100($coreFunctions);

    return DB::transaction(function () use ($user, $period, $coreFunctions, $supportFunctions) {
        $record = IpcrV2Record::create([
            'user_id' => $user->id,
            'rating_period_id' => $period->id,
        ]);

        foreach ($coreFunctions as $function) {
            $this->createCoreItemsForFunction($record, $function);
        }

        foreach ($supportFunctions as $function) {
            $this->createSupportItemsForFunction($record, $function);
        }

        return $record->fresh(['coreItems', 'supportItems']);
    });
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrV2GenerationServiceTest"`
Expected: PASS (full file — confirm no regressions in pre-existing generation tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2GenerationService.php tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php
git commit -m "feat(ipcr-v2): reject target generation for an employee with no synced functions"
```

---

## Task 12: `usePinConfirm` composable + `EmployeeIpcrV2Show.vue` wiring

**Files:**
- Create: `resources/js/Composables/usePinConfirm.js`
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`

**Interfaces:**
- Produces: `usePinConfirm()` returning `{ showPinModal: Ref<boolean>, requestPin(action: (pin: string|null) => void): void, confirmPin(pin: string|null): void, cancelPin(): void }`.
- Consumes: `hasPin`/`signatureUri` props from Task 6's `EmployeeIpcrV2Controller::show()`; the existing `DigitalSignaturePin.vue` component (unmodified).

- [ ] **Step 1: Write the composable**

`resources/js/Composables/usePinConfirm.js`:

```js
import { ref } from "vue"

/**
 * Manages a single pending PIN-confirm action for the DigitalSignaturePin
 * modal. Each caller of requestPin() replaces any previous pending action —
 * only one confirm flow is ever in-flight per page.
 */
export function usePinConfirm() {
  const showPinModal = ref(false)
  let pendingAction = null

  function requestPin(action) {
    pendingAction = action
    showPinModal.value = true
  }

  function confirmPin(pin) {
    showPinModal.value = false
    const action = pendingAction
    pendingAction = null
    if (action) action(pin)
  }

  function cancelPin() {
    showPinModal.value = false
    pendingAction = null
  }

  return { showPinModal, requestPin, confirmPin, cancelPin }
}
```

- [ ] **Step 2: Wire it into `EmployeeIpcrV2Show.vue`**

Modify `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`:

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import { ArrowPathIcon } from "@heroicons/vue/24/outline"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"
import { usePinConfirm } from "@/Composables/usePinConfirm"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  supervisor: Object,
  ocdUser: Object,
  summary: Object,
  isOwner: Boolean,
  isMutable: Boolean,
  hasPin: Boolean,
  signatureUri: String,
})

const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

function submitForReview() {
  requestPin((pin) => submit((opts) => router.post(route("employee-ipcr-v2.submitReview", props.ipcr.id), { pin }, opts)))
}
function submitForRating() {
  requestPin((pin) => submit((opts) => router.post(route("employee-ipcr-v2.submitRating", props.ipcr.id), { pin }, opts)))
}
function syncFunctions() {
  submit((opts) => router.post(route("employee-ipcr-v2.syncFunctions", props.ipcr.id), {}, opts))
}
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.period?.label}`" />
  <AdminLayout title="IPCR V2">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <a :href="route('ipcr-v2-pdf.show', ipcr.id)" target="_blank" rel="noopener">
          <AppButton variant="secondary">Print PDF</AppButton>
        </a>
        <span class="text-xs px-2 py-1 rounded-full ml-2" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="supervisor" :ocd-user="ocdUser" />
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-slate-200 text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Function</th>
              <th colspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Output/Outcomes</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Success Indicator</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Target</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Actual Accomplishment</th>
              <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Remarks</th>
            </tr>
            <tr>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Sub Strategy</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Program</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="isOwner" :is-mutable="isMutable" />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="isOwner" :is-mutable="isMutable" />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" :rating-date="ipcr.director_signed_at" :comments="ipcr.comments_recommendations" />

    <div v-if="isOwner && isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton variant="secondary" :disabled="isSubmitting" @click="syncFunctions">
        <ArrowPathIcon class="w-4 h-4 mr-1" /> Sync from Employee Functions
      </AppButton>
      <AppButton v-if="ipcr.status === 'New Target'" :disabled="isSubmitting" @click="submitForReview">Submit for Review</AppButton>
      <AppButton v-if="ipcr.status === 'Targets Approved'" :disabled="isSubmitting" @click="submitForRating">Submit for Rating</AppButton>
    </div>

    <DigitalSignaturePin
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="isSubmitting"
      @confirm="confirmPin"
      @cancel="cancelPin"
    />
  </AdminLayout>
</template>
```

(The `:rating-date`/`:comments` props on `IpcrV2SummarySection` are wired here ahead of Task 17, which adds them to the component — this task's manual smoke check will show them as inert extra props until Task 17 lands; that's expected and harmless in Vue.)

- [ ] **Step 3: Build and manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or use the project's `build` skill)

Then in the browser (dev URL `http://localhost:8080`), as a Faculty/Staff user with a `New Target` IPCR V2 record: click "Submit for Review", confirm the PIN modal opens (or shows the "no PIN set" notice if the test user has none), confirm with the correct PIN, and verify the record's status updates and no console errors appear.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Composables/usePinConfirm.js resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue
git commit -m "feat(ipcr-v2): PIN-confirm modal on employee submit actions"
```

---

## Task 13: `DivisionChiefIpcrV2Show.vue` — PIN modal, remarks prompt, submit-to-PMT fix

**Files:**
- Modify: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue`

**Interfaces:**
- Consumes: `usePinConfirm` (Task 12), `DigitalSignaturePin.vue`, `hasPin`/`signatureUri` props (Task 7).

- [ ] **Step 1: Implement**

Replace the full contents of `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue`:

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { useSubmit } from "@/Composables/useSubmit"
import { usePinConfirm } from "@/Composables/usePinConfirm"
import Swal from "sweetalert2"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  ocdUser: Object,
  summary: Object,
  isMutable: Boolean,
  hasPin: Boolean,
  signatureUri: String,
})

const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

function approveTargets() {
  requestPin((pin) => submit((opts) => router.post(route("division-chief-ipcr-v2.approveTargets", props.ipcr.id), { pin }, opts)))
}

async function disapproveTargets() {
  const { value: remarks, isConfirmed } = await Swal.fire({
    title: "Return targets for revision?",
    input: "textarea",
    inputLabel: "Remarks",
    inputPlaceholder: "Explain what needs to change...",
    showCancelButton: true,
    inputValidator: (value) => (!value ? "Remarks are required when returning for revision." : undefined),
  })
  if (!isConfirmed || !remarks) return
  submit((opts) => router.post(route("division-chief-ipcr-v2.disapproveTargets", props.ipcr.id), { remarks }, opts))
}

function submitToPMT() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.submitToPMT", props.ipcr.id), {}, opts))
}

function saveComments(text) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.updateComments", props.ipcr.id), { comments_recommendations: text }, opts))
}
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.user?.name}`" />
  <AdminLayout title="IPCR V2 Review">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="null" :ocd-user="ocdUser" />
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-slate-200 text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Function</th>
              <th colspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Output/Outcomes</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Success Indicator</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Target</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Actual Accomplishment</th>
              <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Remarks</th>
            </tr>
            <tr>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Sub Strategy</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Program</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="isMutable" can-rate />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="isMutable" can-rate />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection
      :summary="summary"
      :rating-date="ipcr.director_signed_at"
      :comments="ipcr.comments_recommendations"
      :editable="isMutable"
      @save-comments="saveComments"
    />

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'For Review'" variant="secondary" :disabled="isSubmitting" @click="disapproveTargets">Return for Revision</AppButton>
      <AppButton v-if="ipcr.status === 'For Review'" :disabled="isSubmitting" @click="approveTargets">Approve Targets</AppButton>
      <AppButton v-if="['Submitted for Rating', 'Rated & For PMT Review'].includes(ipcr.status)" :disabled="isSubmitting" @click="submitToPMT">Submit to PMT</AppButton>
    </div>

    <DigitalSignaturePin
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="isSubmitting"
      @confirm="confirmPin"
      @cancel="cancelPin"
    />
  </AdminLayout>
</template>
```

- [ ] **Step 2: Build and manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

In the browser as a Division Chief: open a record in "Submitted for Rating" status, click "Submit to PMT", confirm it moves straight to "Submitted to PMT" (bridging through "Rated" internally); open a record in "For Review", click "Return for Revision", confirm the remarks prompt blocks an empty submission and accepts a filled one.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue
git commit -m "feat(ipcr-v2): DC PIN modal, remarks prompt, submit-to-PMT status fix"
```

---

## Task 14: `PMTIpcrV2Show.vue` — PIN modal, remarks prompt

**Files:**
- Modify: `resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue`

**Interfaces:**
- Consumes: `usePinConfirm` (Task 12), `DigitalSignaturePin.vue`, `hasPin`/`signatureUri` props (Task 8).

- [ ] **Step 1: Implement**

Replace the full contents of `resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue`:

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"
import { usePinConfirm } from "@/Composables/usePinConfirm"
import Swal from "sweetalert2"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  ocdUser: Object,
  summary: Object,
  isMutable: Boolean,
  hasPin: Boolean,
  signatureUri: String,
})
const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

function approve() {
  requestPin((pin) => submit((opts) => router.post(route("pmt-ipcr-v2.approve", props.ipcr.id), { pin }, opts)))
}

async function returnForRevision() {
  const { value: remarks, isConfirmed } = await Swal.fire({
    title: "Return to Division Chief for revision?",
    input: "textarea",
    inputLabel: "Remarks",
    inputPlaceholder: "Explain what needs to change...",
    showCancelButton: true,
    inputValidator: (value) => (!value ? "Remarks are required when returning for revision." : undefined),
  })
  if (!isConfirmed || !remarks) return
  submit((opts) => router.post(route("pmt-ipcr-v2.return", props.ipcr.id), { remarks }, opts))
}

function directorSign() {
  requestPin((pin) => submit((opts) => router.post(route("pmt-ipcr-v2.directorSign", props.ipcr.id), { pin }, opts)))
}
</script>

<template>
  <Head :title="`PMT Review — ${ipcr.user?.name}`" />
  <AdminLayout title="PMT Review">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="null" :ocd-user="ocdUser" />
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-slate-200 text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Function</th>
              <th colspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Output/Outcomes</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Success Indicator</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Target</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Actual Accomplishment</th>
              <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Remarks</th>
            </tr>
            <tr>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Sub Strategy</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Program</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="false" />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="false" />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" :rating-date="ipcr.director_signed_at" :comments="ipcr.comments_recommendations" />

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" variant="secondary" :disabled="isSubmitting" @click="returnForRevision">Return</AppButton>
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" :disabled="isSubmitting" @click="approve">Approve</AppButton>
      <AppButton v-if="ipcr.status === 'Approved by PMT'" :disabled="isSubmitting" @click="directorSign">Director Sign</AppButton>
    </div>

    <DigitalSignaturePin
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="isSubmitting"
      @confirm="confirmPin"
      @cancel="cancelPin"
    />
  </AdminLayout>
</template>
```

- [ ] **Step 2: Build and manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

In the browser as a PMT user: approve a "Submitted to PMT" record with the PIN modal, return one with the remarks prompt, and Director-sign an "Approved by PMT" record; confirm the record becomes locked (no further action buttons render since `isMutable` becomes false).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue
git commit -m "feat(ipcr-v2): PMT PIN modal and remarks prompt"
```

---

## Task 15: `IpcrV2StatusTimeline.vue` + wire into all 5 Show pages

**Files:**
- Create: `resources/js/Components/IPCRV2/IpcrV2StatusTimeline.vue`
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`, `DivisionChiefIpcrV2Show.vue`, `PMTIpcrV2Show.vue`, `HRIpcrV2Show.vue`, `AdminIpcrV2Show.vue`

**Interfaces:**
- Consumes: `ipcr.status_logs` array (each row: `{ from_status, to_status, action_type, remarks, actor: {name}|null, actor_role, signed_via_pin, created_at }`), already eager-loaded as `statusLogs.actor` by Tasks 6-10.

- [ ] **Step 1: Implement the component**

`resources/js/Components/IPCRV2/IpcrV2StatusTimeline.vue`:

```vue
<script setup>
import AppCard from "@/Components/AppCard.vue"
import { ShieldCheckIcon, ClockIcon } from "@heroicons/vue/24/outline"

defineProps({
  logs: { type: Array, default: () => [] },
})

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric", hour: "numeric", minute: "2-digit" }) : "—"
}

const ACTION_LABEL = {
  submitted: "Submitted",
  approved: "Approved",
  returned: "Returned for Revision",
  rated: "Marked as Rated",
  signed: "Signed",
  reopened: "Reopened",
  status_changed: "Status Changed",
}
</script>

<template>
  <AppCard v-if="logs.length" class="mt-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-4">Audit Timeline</h3>
    <ol class="space-y-3">
      <li v-for="log in logs" :key="log.id" class="flex gap-3 text-sm">
        <div class="mt-0.5 shrink-0">
          <ShieldCheckIcon v-if="log.signed_via_pin" class="w-4 h-4 text-indigo-500" />
          <ClockIcon v-else class="w-4 h-4 text-slate-400" />
        </div>
        <div class="min-w-0">
          <p class="text-slate-700">
            <span class="font-medium">{{ ACTION_LABEL[log.action_type] ?? log.action_type }}</span>
            <span v-if="log.to_status"> — {{ log.to_status }}</span>
            <span v-if="log.actor?.name" class="text-slate-500"> by {{ log.actor.name }}</span>
          </p>
          <p v-if="log.remarks" class="text-slate-500 italic mt-0.5">"{{ log.remarks }}"</p>
          <p class="text-xs text-slate-400 mt-0.5">{{ formatDate(log.created_at) }}</p>
        </div>
      </li>
    </ol>
  </AppCard>
</template>
```

- [ ] **Step 2: Wire it into each Show page**

In each of `EmployeeIpcrV2Show.vue`, `DivisionChiefIpcrV2Show.vue`, `PMTIpcrV2Show.vue`, `HRIpcrV2Show.vue`, `AdminIpcrV2Show.vue`: add the import

```js
import IpcrV2StatusTimeline from "@/Components/IPCRV2/IpcrV2StatusTimeline.vue"
```

and add, immediately after the `<IpcrV2SummarySection ... />` element in each template:

```vue
<IpcrV2StatusTimeline :logs="ipcr.status_logs ?? []" />
```

`HRIpcrV2Show.vue` and `AdminIpcrV2Show.vue` currently have no `hasPin`/`isMutable`/action buttons — read each file first (`Read resources/js/Pages/IPCRV2/HRIpcrV2Show.vue` and `AdminIpcrV2Show.vue`) before editing, since this task only adds the timeline import + element, nothing else in those two files.

- [ ] **Step 3: Build and manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

In the browser, open any IPCR V2 record that has been through at least one transition (e.g. submitted for review) as any of the five roles and confirm the "Audit Timeline" card renders below the Rating Summary with the correct action label, actor name, and timestamp.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2StatusTimeline.vue resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue resources/js/Pages/IPCRV2/HRIpcrV2Show.vue resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue
git commit -m "feat(ipcr-v2): render per-record audit timeline on every Show page"
```

---

## Task 16: `IpcrV2SummarySection.vue` — Comments and Recommendations + rating date

**Files:**
- Modify: `resources/js/Components/IPCRV2/IpcrV2SummarySection.vue`
- Modify: `resources/views/ipcr-v2/pdf.blade.php:163-233`

**Interfaces:**
- Produces: `IpcrV2SummarySection` gains props `ratingDate: String|null`, `comments: String|null`, `editable: Boolean` (default `false`), and emits `save-comments(text: string)` when `editable`.
- Consumes: already-wired `:rating-date`/`:comments`/`:editable`/`@save-comments` bindings added in Tasks 12-14.

- [ ] **Step 1: Implement the Vue component change**

Replace the full contents of `resources/js/Components/IPCRV2/IpcrV2SummarySection.vue`:

```vue
<script setup>
import { ref, watch, computed } from "vue"
import AppCard from "@/Components/AppCard.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"

const props = defineProps({
  summary: { type: Object, default: () => ({ strategic: [], core: [], support: [] }) },
  ratingDate: { type: String, default: null },
  comments: { type: String, default: null },
  editable: { type: Boolean, default: false },
})

const emit = defineEmits(["save-comments"])

const draftComments = ref(props.comments ?? "")
watch(() => props.comments, (v) => { draftComments.value = v ?? "" })

const formattedDate = computed(() =>
  props.ratingDate
    ? new Date(props.ratingDate).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" })
    : null
)

function fmt(v) {
  return v === null || v === undefined ? "—" : (typeof v === "number" ? v.toFixed(2) : v)
}
</script>

<template>
  <AppCard class="mt-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-semibold text-slate-700">Rating Summary</h3>
      <span v-if="formattedDate" class="text-xs text-slate-500">Date: {{ formattedDate }}</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse border border-slate-200 text-sm">
        <thead>
          <tr>
            <th :class="TH" class="border border-slate-200">Agency Organizational Outcome</th>
            <th :class="TH" class="border border-slate-200 text-center">Quality</th>
            <th :class="TH" class="border border-slate-200 text-center">Efficiency</th>
            <th :class="TH" class="border border-slate-200 text-center">Timeliness</th>
            <th :class="TH" class="border border-slate-200 text-center">Average</th>
            <th :class="TH" class="border border-slate-200">Equivalent</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Strategic Functions (30%)</td>
          </tr>
          <tr v-for="row in summary.strategic" :key="'s-' + row.label">
            <td :class="TD" class="border border-slate-200">{{ row.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.equivalent ?? "—" }}</td>
          </tr>

          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Core Functions (50%)</td>
          </tr>
          <tr v-for="row in summary.core" :key="'c-' + row.label">
            <td :class="TD" class="border border-slate-200">{{ row.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.equivalent ?? "—" }}</td>
          </tr>

          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Support Functions (20%)</td>
          </tr>
          <tr v-for="row in summary.support" :key="'sup-' + row.label">
            <td :class="TD" class="border border-slate-200">{{ row.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.equivalent ?? "—" }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 text-xs text-slate-500 italic">
      Legend: 5 - Outstanding &nbsp; 4 - Very Satisfactory &nbsp; 3 - Satisfactory &nbsp; 2 - Unsatisfactory &nbsp; 1 - Poor
    </div>

    <div class="mt-6">
      <h4 class="text-sm font-semibold text-slate-700 mb-2">Comments and Recommendations for Development Purposes</h4>
      <textarea
        v-if="editable"
        v-model="draftComments"
        rows="3"
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        placeholder="Enter comments and recommendations..."
      ></textarea>
      <p v-else class="text-sm text-slate-600 whitespace-pre-line">{{ comments || "—" }}</p>
      <div v-if="editable" class="mt-2 flex justify-end">
        <button class="text-xs text-indigo-600 hover:text-indigo-700 font-medium" @click="emit('save-comments', draftComments)">
          Save Comments
        </button>
      </div>
    </div>

    <div class="overflow-x-auto mt-6">
      <table class="w-full border-collapse border border-slate-200 text-sm">
        <tr class="font-semibold text-slate-700">
          <td class="border border-slate-200 px-3 py-2 text-left">Discussed with</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Assessed by</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Final Rating by</td>
        </tr>
        <tr>
          <td class="border border-slate-200 px-3 py-6 text-center text-slate-400">____________________</td>
          <td class="border border-slate-200 px-3 py-6 text-center text-slate-400">____________________</td>
          <td class="border border-slate-200 px-3 py-6 text-center text-slate-400">____________________</td>
        </tr>
      </table>
    </div>
  </AppCard>
</template>
```

- [ ] **Step 2: Implement the PDF blade change**

In `resources/views/ipcr-v2/pdf.blade.php`, change line 163 and insert the Comments block between the Rating Summary table's closing `</table>` (line 210) and the signature `<table style="margin-top: 14px;">` (line 212):

Replace:
```blade
    <p style="margin-top: 10px;"><strong>Rating Summary</strong></p>
```
with:
```blade
    <p style="margin-top: 10px;">
        <strong>Rating Summary</strong>
        @if($ipcr->director_signed_at)
            <span style="float: right; font-size: 9px;">Date: {{ $ipcr->director_signed_at->format('F j, Y') }}</span>
        @endif
    </p>
```

Replace:
```blade
        </tbody>
    </table>

    <table style="margin-top: 14px;">
```
with:
```blade
        </tbody>
    </table>

    @if(!empty($ipcr->comments_recommendations))
    <p style="margin-top: 10px;"><strong>Comments and Recommendations for Development Purposes</strong></p>
    <p style="font-size: 9px;">{{ $ipcr->comments_recommendations }}</p>
    @endif

    <table style="margin-top: 14px;">
```

- [ ] **Step 3: Manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

In the browser: open the Division Chief Show page for a mutable record, type into "Comments and Recommendations for Development Purposes", click "Save Comments", reload, confirm it persisted; open the same record's Print PDF (`ipcr-v2-pdf.show`) and confirm the comments render between the Rating Summary and the signature block, and the rating date shows once the record has a `director_signed_at`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2SummarySection.vue resources/views/ipcr-v2/pdf.blade.php
git commit -m "feat(ipcr-v2): add Comments and Recommendations section + rating date to Rating Summary"
```

---

## Task 17: `EmployeeIpcrV2Index.vue` — duplicate-generation guard + status/remarks display

**Files:**
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue`

**Interfaces:**
- Consumes: `records[].remarks` (already present on the model as of Task 1, already returned by `EmployeeIpcrV2Controller::index()`'s `IpcrV2Record::where(...)->get()` since no column allowlist is applied there).

- [ ] **Step 1: Implement**

In `resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue`, add a computed to detect whether the selected period already has a record, and disable/relabel the button accordingly, plus surface `remarks` under the status badge:

Replace the `<script setup>` block's computed/button logic — after the existing `selectedPeriod` ref, add:

```js
const existingRecordForSelectedPeriod = computed(() =>
  props.records.find((r) => r.period?.id === selectedPeriod.value)
)
```

Replace the `#actions` template block:

```vue
<template #actions>
  <template v-if="openPeriods.length">
    <AppSelect v-model="selectedPeriod" :show-blank="false" class="w-56">
      <option v-for="p in openPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
    </AppSelect>
    <AppButton v-if="existingRecordForSelectedPeriod" variant="secondary" @click="viewRecord(existingRecordForSelectedPeriod)">
      <EyeIcon class="w-4 h-4" /> View Existing Record
    </AppButton>
    <AppButton v-else :disabled="isSubmitting || !selectedPeriod" @click="generateTargets">
      <PlusIcon class="w-4 h-4" /> Generate Targets
    </AppButton>
  </template>
</template>
```

Add a remarks line under the status badge in both the desktop row and mobile card. Replace:

```vue
<td class="px-4 py-3"><AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge></td>
```
with:
```vue
<td class="px-4 py-3">
  <AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge>
  <p v-if="record.remarks" class="text-xs text-slate-500 italic mt-1 max-w-[220px] truncate" :title="record.remarks">{{ record.remarks }}</p>
</td>
```

And in the mobile card template, replace:
```vue
<AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge>
```
with:
```vue
<div class="text-right">
  <AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge>
  <p v-if="record.remarks" class="text-xs text-slate-500 italic mt-1 max-w-[160px] truncate" :title="record.remarks">{{ record.remarks }}</p>
</div>
```

- [ ] **Step 2: Manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

In the browser as a Faculty/Staff user with an existing IPCR V2 record for the currently-selected open period: confirm "Generate Targets" is replaced by "View Existing Record" and clicking it navigates to the Show page; select a period with no existing record and confirm "Generate Targets" reappears. If a record has `remarks` set (e.g. after a return), confirm it shows truncated under the status badge.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue
git commit -m "feat(ipcr-v2): guard duplicate target generation, surface remarks on the index"
```

---

## Task 18: `AdminIpcrV2Show.vue` — reopen action

**Files:**
- Modify: `resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue`

**Interfaces:**
- Consumes: `usePinConfirm` (Task 12), `DigitalSignaturePin.vue`, `hasPin`/`signatureUri` props (Task 10), `admin-ipcr-v2.reopen` route (Task 10).

- [ ] **Step 1: Read the current file**

Read `resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue` in full before editing (it was not modified by any earlier task besides the Task 15 timeline addition) to match its existing script/template structure exactly.

- [ ] **Step 2: Implement**

Add to its `<script setup>` (alongside its existing imports):

```js
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { usePinConfirm } from "@/Composables/usePinConfirm"
import { useSubmit } from "@/Composables/useSubmit"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

async function reopen() {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: "Reopen this IPCR V2?",
    input: "textarea",
    inputLabel: "Reason for reopening",
    inputPlaceholder: "Explain why this record needs to be reopened...",
    showCancelButton: true,
    inputValidator: (value) => (!value ? "A reason is required." : undefined),
  })
  if (!isConfirmed || !reason) return

  requestPin((pin) => submit((opts) => router.post(route("admin-ipcr-v2.reopen", props.ipcr.id), { reason, pin }, opts)))
}
```

(Add `hasPin: Boolean, signatureUri: String` to its existing `defineProps` if not already declared by an earlier pass — check first, since Task 15 only added the timeline import/element to this file.)

Add to its template, only when the record is locked (`ipcr.status === 'Director Signed'`):

```vue
<div v-if="ipcr.status === 'Director Signed'" class="mt-6 flex justify-end">
  <AppButton variant="secondary" :disabled="isSubmitting" @click="reopen">Reopen</AppButton>
</div>

<DigitalSignaturePin
  :show="showPinModal"
  :has-pin="hasPin"
  :signature-uri="signatureUri"
  :loading="isSubmitting"
  confirm-label="Reopen"
  @confirm="confirmPin"
  @cancel="cancelPin"
/>
```

(If the file doesn't already import `AppButton`, add `import AppButton from "@/Components/AppButton.vue"`.)

- [ ] **Step 3: Manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

In the browser as an Administrator: open a "Director Signed" record, click "Reopen", fill the reason prompt, confirm the PIN modal, and verify the record's status reverts to "Submitted to PMT" and the Audit Timeline (Task 15) shows a "Reopened" entry with the reason as remarks.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue
git commit -m "feat(ipcr-v2): admin reopen action on a locked record"
```

---

## Final verification

After all 18 tasks:

- [ ] Run the full IPCR V2 test suite: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IPCRV2"` — expect all green, zero regressions.
- [ ] Run the full test suite once: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"` — confirm no unrelated regressions (v1 IPCR, notifications, mail).
- [ ] `npm run build` clean, no console errors when clicking through the full Employee → Division Chief → PMT → Director Sign → Admin Reopen path manually in the browser as described in Tasks 12-18's manual-verify steps.
