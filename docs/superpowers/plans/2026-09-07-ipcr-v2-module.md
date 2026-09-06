# IPCR V2 Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the parallel IPCR V2 module — Strategic (30%, live OPCR mirror) / Core (50%, per-subject 4-part rubric) / Support (20%) — reusing v1 IPCR's exact status flow, supervisor chain, and screen conventions, isolated from the live v1 IPCR module.

**Architecture:** New `ipcr_v2_*` tables and `App\Models\IPCRV2\*` models, one record per employee per rating period snapshotting `EmployeeFunction` rows (from the Employee Functions plan) into Core/Support items at "Generate Targets" time. `IpcrV2WorkflowService` owns status transitions for `IpcrV2Record` but delegates supervisor-chain resolution to the existing `IPCRWorkflowService` (that logic is generic over `User` already — duplicating ~300 lines of AUH/ACIDAA chain resolution would be a maintenance trap, not a design win). Five role-scoped controllers (Employee, Division Chief, PMT, HR, Admin) mirror v1's controller split exactly, with three shared Vue components (Strategic section, Core items table, Support items table) reused across every role's Show page to avoid duplicating markup five times.

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, PHPUnit + RefreshDatabase.

**Spec:** `docs/superpowers/specs/2026-09-07-ipcr-v2-design.md`

**Depends on:** `docs/superpowers/plans/2026-09-07-employee-functions.md` (must be merged first — this plan reads `App\Models\EmployeeFunction`).

## Global Constraints

- Zero shared mutable state with v1: never write to `employee_ipcrs`, `employee_ipcrs_plan`, or any v1 table. Never modify `IPCRWorkflowService` except to use its existing public methods.
- Status strings and transition map are byte-identical to `IPCRWorkflowService::STATUS_*`/`TRANSITIONS` (spec §Workflow & roles) — reuse the existing frontend composables `ipcrStatusClass` (`resources/js/Composables/ipcrStatusClass.js`) and `ipcrAdjectivalRating` (`resources/js/Composables/ipcrAdjectivalRating.js`) as-is; do not create v2-specific copies.
- Faculty vs. Non-Teaching detection: `$user->hasRole('Faculty') || (bool) $user->academic_unit_id` (same as Employee Functions plan and v1's `EmployeeIPCRController`).
- Final rating reuses the existing `IPCRWeightDistribution` table (`division_id, strategic, core, support` — whole-number percentages) with a 30/50/20 fallback when the employee's division has no configured row.
- `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan ..."` runs every artisan/test command.
- Never use `Auth::user()->role_id` — use `hasRole()`/`hasPermission()`. Stage files by name when committing, never `git add -A`/`.`.

---

### Task 1: IPCR V2 migrations

**Files:**
- Create: `database/migrations/2026_09_07_100000_create_ipcr_v2_records_table.php`
- Create: `database/migrations/2026_09_07_100010_create_ipcr_v2_core_items_table.php`
- Create: `database/migrations/2026_09_07_100020_create_ipcr_v2_support_items_table.php`
- Create: `database/migrations/2026_09_07_100030_create_ipcr_v2_coaching_sessions_table.php`
- Test: `tests/Feature/IPCRV2/IpcrV2MigrationsTest.php`

**Interfaces:**
- Produces: `ipcr_v2_records`, `ipcr_v2_core_items`, `ipcr_v2_support_items`, `ipcr_v2_coaching_sessions` tables.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IpcrV2MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipcr_v2_records_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_records', [
            'id', 'user_id', 'rating_period_id', 'status',
            'submitted_for_review_at', 'target_approved_at', 'submitted_for_rating_at',
            'submitted_rating_at', 'submitted_for_pmtreview_at', 'submitted_to_hr_at',
            'director_signed_at', 'director_signature',
            'final_numeric_rating', 'final_adjectival_rating',
        ]));
    }

    public function test_ipcr_v2_core_items_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_core_items', [
            'id', 'ipcr_v2_id', 'employee_function_id', 'label', 'weight_percent',
            'target', 'actual_accomplishment',
            'student_feedback_rating', 'supervisor_feedback_rating',
            'im_development_rating', 'timeliness_rating', 'row_average', 'remarks',
        ]));
    }

    public function test_ipcr_v2_support_items_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_support_items', [
            'id', 'ipcr_v2_id', 'employee_function_id', 'label',
            'actual_accomplishment', 'mov_link',
            'quality_rating', 'efficiency_rating', 'timeliness_rating', 'row_average', 'remarks',
        ]));
    }

    public function test_ipcr_v2_coaching_sessions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ipcr_v2_coaching_sessions', [
            'id', 'ipcr_v2_id', 'activity_type', 'mechanism', 'meeting_date',
            'channel_memo', 'channel_others', 'remarks',
            'conducted_by_name', 'conducted_at', 'noted_by_name', 'noted_at',
        ]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2MigrationsTest.php"`
Expected: FAIL — none of the tables exist.

- [ ] **Step 3: Write `2026_09_07_100000_create_ipcr_v2_records_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_period_id')->constrained('ipcr_rating_periods')->cascadeOnDelete();
            $table->string('status')->default('New Target');
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('target_approved_at')->nullable();
            $table->timestamp('submitted_for_rating_at')->nullable();
            $table->timestamp('submitted_rating_at')->nullable();
            $table->timestamp('submitted_for_pmtreview_at')->nullable();
            $table->timestamp('submitted_to_hr_at')->nullable();
            $table->timestamp('director_signed_at')->nullable();
            $table->text('director_signature')->nullable();
            $table->decimal('final_numeric_rating', 5, 2)->nullable();
            $table->string('final_adjectival_rating')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'rating_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_records');
    }
};
```

- [ ] **Step 4: Write `2026_09_07_100010_create_ipcr_v2_core_items_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_core_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->foreignId('employee_function_id')->nullable()->constrained('employee_functions')->nullOnDelete();
            $table->string('label', 255);
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->text('target')->nullable();
            $table->text('actual_accomplishment')->nullable();
            $table->unsignedTinyInteger('student_feedback_rating')->nullable();
            $table->unsignedTinyInteger('supervisor_feedback_rating')->nullable();
            $table->unsignedTinyInteger('im_development_rating')->nullable();
            $table->unsignedTinyInteger('timeliness_rating')->nullable();
            $table->decimal('row_average', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_core_items');
    }
};
```

- [ ] **Step 5: Write `2026_09_07_100020_create_ipcr_v2_support_items_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_support_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->foreignId('employee_function_id')->nullable()->constrained('employee_functions')->nullOnDelete();
            $table->string('label', 255);
            $table->text('actual_accomplishment')->nullable();
            $table->string('mov_link', 500)->nullable();
            $table->unsignedTinyInteger('quality_rating')->nullable();
            $table->unsignedTinyInteger('efficiency_rating')->nullable();
            $table->unsignedTinyInteger('timeliness_rating')->nullable();
            $table->decimal('row_average', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_support_items');
    }
};
```

- [ ] **Step 6: Write `2026_09_07_100030_create_ipcr_v2_coaching_sessions_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_coaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->enum('activity_type', ['monitoring', 'coaching']);
            $table->enum('mechanism', ['one_on_one', 'group']);
            $table->date('meeting_date');
            $table->boolean('channel_memo')->default(false);
            $table->string('channel_others', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->string('conducted_by_name', 255)->nullable();
            $table->timestamp('conducted_at')->nullable();
            $table->string('noted_by_name', 255)->nullable();
            $table->timestamp('noted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_coaching_sessions');
    }
};
```

- [ ] **Step 7: Run migrations in dev**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_07_100000_create_ipcr_v2_records_table.php && php artisan migrate --path=database/migrations/2026_09_07_100010_create_ipcr_v2_core_items_table.php && php artisan migrate --path=database/migrations/2026_09_07_100020_create_ipcr_v2_support_items_table.php && php artisan migrate --path=database/migrations/2026_09_07_100030_create_ipcr_v2_coaching_sessions_table.php"`
Expected: all four report `Migrated:`.

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2MigrationsTest.php"`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_09_07_100000_create_ipcr_v2_records_table.php database/migrations/2026_09_07_100010_create_ipcr_v2_core_items_table.php database/migrations/2026_09_07_100020_create_ipcr_v2_support_items_table.php database/migrations/2026_09_07_100030_create_ipcr_v2_coaching_sessions_table.php tests/Feature/IPCRV2/IpcrV2MigrationsTest.php
git commit -m "feat(ipcr-v2): add ipcr_v2 schema (records, core/support items, coaching sessions)"
```

---

### Task 2: IPCR V2 models

**Files:**
- Create: `app/Models/IPCRV2/IpcrV2Record.php`
- Create: `app/Models/IPCRV2/IpcrV2CoreItem.php`
- Create: `app/Models/IPCRV2/IpcrV2SupportItem.php`
- Create: `app/Models/IPCRV2/IpcrV2CoachingSession.php`
- Test: `tests/Feature/IPCRV2/IpcrV2ModelsTest.php`

**Interfaces:**
- Consumes: `ipcr_v2_*` tables (Task 1), `App\Models\User`, `App\Models\IPCRRatingPeriod`, `App\Models\EmployeeFunction`.
- Produces: `IpcrV2Record::user()`, `::period()`, `::coreItems()`, `::supportItems()`, `::coachingSessions()`, `::isFinalized()`, `::isPeriodClosed()`, `::isMutable()`; `IpcrV2CoreItem::ipcr()`, `::employeeFunction()`; `IpcrV2SupportItem` same shape; `IpcrV2CoachingSession::ipcr()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_relations_and_mutability(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'Jan-Jun 2026', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

        $this->assertTrue($record->user->is($user));
        $this->assertTrue($record->period->is($period));
        $this->assertTrue($record->isMutable());

        $record->update(['status' => 'Director Signed']);
        $this->assertTrue($record->fresh()->isFinalized());
        $this->assertFalse($record->fresh()->isMutable());
    }

    public function test_core_item_belongs_to_record_and_optional_employee_function(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'x']);

        $item = IpcrV2CoreItem::create([
            'ipcr_v2_id' => $record->id, 'employee_function_id' => $function->id, 'label' => 'Subject 1',
        ]);

        $this->assertTrue($item->ipcr->is($record));
        $this->assertTrue($item->employeeFunction->is($function));
        $this->assertCount(1, $record->fresh()->coreItems);
    }

    public function test_support_item_belongs_to_record(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);

        IpcrV2SupportItem::create(['ipcr_v2_id' => $record->id, 'label' => 'Committee w/o Load']);

        $this->assertCount(1, $record->fresh()->supportItems);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2ModelsTest.php"`
Expected: FAIL — `App\Models\IPCRV2\IpcrV2Record` not found.

- [ ] **Step 3: Write `app/Models/IPCRV2/IpcrV2Record.php`**

```php
<?php

namespace App\Models\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IpcrV2Record extends Model
{
    protected $table = 'ipcr_v2_records';

    public const STATUS_DIRECTOR_SIGNED = 'Director Signed';

    protected $fillable = [
        'user_id', 'rating_period_id', 'status',
        'submitted_for_review_at', 'target_approved_at', 'submitted_for_rating_at',
        'submitted_rating_at', 'submitted_for_pmtreview_at', 'submitted_to_hr_at',
        'director_signed_at', 'director_signature',
        'final_numeric_rating', 'final_adjectival_rating',
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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function period()
    {
        return $this->belongsTo(IPCRRatingPeriod::class, 'rating_period_id');
    }

    public function coreItems()
    {
        return $this->hasMany(IpcrV2CoreItem::class, 'ipcr_v2_id');
    }

    public function supportItems()
    {
        return $this->hasMany(IpcrV2SupportItem::class, 'ipcr_v2_id');
    }

    public function coachingSessions()
    {
        return $this->hasMany(IpcrV2CoachingSession::class, 'ipcr_v2_id')->orderBy('meeting_date', 'desc');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_DIRECTOR_SIGNED;
    }

    public function isPeriodClosed(): bool
    {
        return $this->period?->isClosed() === true;
    }

    public function isMutable(): bool
    {
        return ! $this->isFinalized() && ! $this->isPeriodClosed();
    }
}
```

- [ ] **Step 4: Write `app/Models/IPCRV2/IpcrV2CoreItem.php`**

```php
<?php

namespace App\Models\IPCRV2;

use App\Models\EmployeeFunction;
use Illuminate\Database\Eloquent\Model;

class IpcrV2CoreItem extends Model
{
    protected $table = 'ipcr_v2_core_items';

    protected $fillable = [
        'ipcr_v2_id', 'employee_function_id', 'label', 'weight_percent',
        'target', 'actual_accomplishment',
        'student_feedback_rating', 'supervisor_feedback_rating',
        'im_development_rating', 'timeliness_rating', 'row_average', 'remarks',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'row_average' => 'decimal:2',
    ];

    public function ipcr()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_id');
    }

    public function employeeFunction()
    {
        return $this->belongsTo(EmployeeFunction::class);
    }
}
```

- [ ] **Step 5: Write `app/Models/IPCRV2/IpcrV2SupportItem.php`**

```php
<?php

namespace App\Models\IPCRV2;

use App\Models\EmployeeFunction;
use Illuminate\Database\Eloquent\Model;

class IpcrV2SupportItem extends Model
{
    protected $table = 'ipcr_v2_support_items';

    protected $fillable = [
        'ipcr_v2_id', 'employee_function_id', 'label',
        'actual_accomplishment', 'mov_link',
        'quality_rating', 'efficiency_rating', 'timeliness_rating', 'row_average', 'remarks',
    ];

    protected $casts = [
        'row_average' => 'decimal:2',
    ];

    public function ipcr()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_id');
    }

    public function employeeFunction()
    {
        return $this->belongsTo(EmployeeFunction::class);
    }
}
```

- [ ] **Step 6: Write `app/Models/IPCRV2/IpcrV2CoachingSession.php`**

```php
<?php

namespace App\Models\IPCRV2;

use Illuminate\Database\Eloquent\Model;

class IpcrV2CoachingSession extends Model
{
    protected $table = 'ipcr_v2_coaching_sessions';

    protected $fillable = [
        'ipcr_v2_id', 'activity_type', 'mechanism', 'meeting_date',
        'channel_memo', 'channel_others', 'remarks',
        'conducted_by_name', 'conducted_at', 'noted_by_name', 'noted_at',
    ];

    protected $casts = [
        'meeting_date' => 'date:Y-m-d',
        'channel_memo' => 'boolean',
        'conducted_at' => 'datetime',
        'noted_at' => 'datetime',
    ];

    public function ipcr()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_id');
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2ModelsTest.php"`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Models/IPCRV2 tests/Feature/IPCRV2/IpcrV2ModelsTest.php
git commit -m "feat(ipcr-v2): add IPCR V2 models"
```

---

### Task 3: `ipcr.v2.*` permission seeder

**Files:**
- Create: `database/seeders/IpcrV2PermissionSeeder.php`
- Test: `tests/Feature/IPCRV2/IpcrV2PermissionSeederTest.php`

**Interfaces:**
- Produces: permissions `ipcr.v2.view`, `ipcr.v2.create`, `ipcr.v2.update`, `ipcr.v2.submit`, `ipcr.v2.approve`, `ipcr.v2.monitor`, `ipcr.v2.admin`, granted to the same roles as v1's equivalent (`database/seeders/RolePermissionSeeder.php`): `Faculty`/`Staff` get view/create/update/submit; `DivisionChief`/`PMT` get view/approve/monitor; `HR`/`OCD` get view/monitor/admin.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\IpcrV2PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_seven_permissions_and_grants_faculty_the_employee_set(): void
    {
        $faculty = Role::create(['name' => 'Faculty']);

        (new IpcrV2PermissionSeeder())->run();

        foreach (['view', 'create', 'update', 'submit', 'approve', 'monitor', 'admin'] as $suffix) {
            $this->assertDatabaseHas('permissions', ['name' => "ipcr.v2.{$suffix}"]);
        }

        $names = $faculty->fresh()->permissions->pluck('name')->all();
        $this->assertEqualsCanonicalizing(
            ['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'],
            $names
        );
    }

    public function test_grants_division_chief_the_approve_set(): void
    {
        $dc = Role::create(['name' => 'DivisionChief']);

        (new IpcrV2PermissionSeeder())->run();

        $names = $dc->fresh()->permissions->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'], $names);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2PermissionSeederTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * IPCR V2 permissions — mirrors v1's ipcr.* permission/role shape exactly
 * (see database/seeders/RolePermissionSeeder.php).
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\IpcrV2PermissionSeeder --force
 */
class IpcrV2PermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'ipcr.v2.view' => 'View own IPCR V2',
        'ipcr.v2.create' => 'Create IPCR V2 entries',
        'ipcr.v2.update' => 'Update IPCR V2 entries',
        'ipcr.v2.submit' => 'Submit IPCR V2 for approval',
        'ipcr.v2.approve' => 'Approve IPCR V2 submissions',
        'ipcr.v2.monitor' => 'Monitor unit/division IPCR V2',
        'ipcr.v2.admin' => 'Manage IPCR V2 fiscal years and rating periods',
    ];

    private const EMPLOYEE_ROLES = ['Faculty', 'Staff'];
    private const SUPERVISOR_ROLES = ['DivisionChief', 'PMT'];
    private const MONITOR_ROLES = ['HR', 'OCD'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'IPCR V2',
                'description' => $description,
            ]);
        }

        $this->grant(self::EMPLOYEE_ROLES, ['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit']);
        $this->grant(self::SUPERVISOR_ROLES, ['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor']);
        $this->grant(self::MONITOR_ROLES, ['ipcr.v2.view', 'ipcr.v2.monitor', 'ipcr.v2.admin']);
    }

    private function grant(array $roleNames, array $permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id')->all();
        if (empty($ids)) {
            return;
        }
        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2PermissionSeederTest.php"`
Expected: PASS

- [ ] **Step 5: Run the seeder in dev**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=Database\\\\Seeders\\\\IpcrV2PermissionSeeder"`

- [ ] **Step 6: Commit**

```bash
git add database/seeders/IpcrV2PermissionSeeder.php tests/Feature/IPCRV2/IpcrV2PermissionSeederTest.php
git commit -m "feat(ipcr-v2): seed ipcr.v2.* permissions"
```

---

### Task 4: `IpcrV2WorkflowService`

**Files:**
- Create: `app/Services/IPCRV2/IpcrV2WorkflowService.php`
- Test: `tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\PerformanceManagement\IPCRWorkflowService` (existing, injected — reused for `immediateSupervisorFor()`, `canEndorse()`'s division-chief lookup, `supervisedUserIds()`; those are generic over `User`, not `EmployeeIPCR`-specific), `App\Models\IPCRV2\IpcrV2Record` (Task 2).
- Produces: `IpcrV2WorkflowService::STATUS_*` constants + `TRANSITIONS` (byte-identical values to `IPCRWorkflowService`), `assertMutable(IpcrV2Record $r): void`, `assertOwner(User $u, IpcrV2Record $r): void`, `canManage(User $u, IpcrV2Record $r): bool`, `assertCanManage(...)`, `canEndorse(User $u, IpcrV2Record $r): bool`, `assertCanEndorse(...)`, `transition(IpcrV2Record $r, string $to, array $extra = [], ?string $auditAction = null): IpcrV2Record`, `assertPeriodAcceptsNewTargets(IPCRRatingPeriod $p): void`, `assertNoDuplicateForPeriod(int $userId, int $periodId, ?int $ignoreId = null): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IpcrV2WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $status = 'open'): IPCRRatingPeriod
    {
        return IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => $status]);
    }

    public function test_transition_moves_new_target_to_for_review(): void
    {
        $user = User::factory()->create();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $this->period()->id]);
        $service = new IpcrV2WorkflowService();

        $service->transition($record, IpcrV2WorkflowService::STATUS_FOR_REVIEW);

        $this->assertSame(IpcrV2WorkflowService::STATUS_FOR_REVIEW, $record->fresh()->status);
    }

    public function test_transition_rejects_invalid_move(): void
    {
        $user = User::factory()->create();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $this->period()->id]);
        $service = new IpcrV2WorkflowService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->transition($record, IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED);
    }

    public function test_assert_mutable_blocks_a_finalized_record(): void
    {
        $user = User::factory()->create();
        $record = IpcrV2Record::create([
            'user_id' => $user->id, 'rating_period_id' => $this->period()->id,
            'status' => IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED,
        ]);
        $service = new IpcrV2WorkflowService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assertMutable($record);
    }

    public function test_assert_no_duplicate_for_period_throws_on_second_record(): void
    {
        $user = User::factory()->create();
        $period = $this->period();
        IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $service = new IpcrV2WorkflowService();

        $this->expectException(ValidationException::class);
        $service->assertNoDuplicateForPeriod($user->id, $period->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\Division;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\AuditLogger;
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
        private ?IPCRWorkflowService $chain = null
    ) {
        $this->chain ??= app(IPCRWorkflowService::class);
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

    public function transition(IpcrV2Record $ipcr, string $to, array $extra = [], ?string $auditAction = null): IpcrV2Record
    {
        $this->assertMutable($ipcr);

        return DB::transaction(function () use ($ipcr, $to, $extra, $auditAction) {
            $fresh = IpcrV2Record::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(in_array($to, $allowed, true), 403, "Invalid IPCR V2 status change: \"{$fresh->status}\" cannot move to \"{$to}\".");

            $fresh->update(array_merge($extra, ['status' => $to]));

            AuditLogger::log([
                'action' => $auditAction ?? 'ipcr_v2_status_changed',
                'auditable_type' => IpcrV2Record::class,
                'auditable_id' => $fresh->id,
                'new_values' => array_merge(['status' => $to], $extra),
            ]);

            $ipcr->refresh();

            return $ipcr;
        });
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
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2WorkflowService.php tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php
git commit -m "feat(ipcr-v2): add IpcrV2WorkflowService"
```

---

### Task 5: `StrategicFunctionService`

**Files:**
- Create: `app/Services/IPCRV2/StrategicFunctionService.php`
- Test: `tests/Feature/IPCRV2/StrategicFunctionServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\OPCR\OpcrIndicator` (existing), `App\Models\IPCRRatingPeriod`.
- Produces: `StrategicFunctionService::currentIndicators(): \Illuminate\Support\Collection` — every `OpcrIndicator` for the current fiscal year (`IPCRRatingPeriod::current()->value('year')`), eager-loaded with `agencyOutcome`, grouped and ordered by Program (agency outcome text) A→Z, matching the OPCR Show page's own grouping.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicFunctionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_current_fiscal_year_indicators_grouped_by_program(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $programB = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);
        $programA = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);

        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $programB->id, 'description' => 'Indicator B1']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $programA->id, 'description' => 'Indicator A1']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programA->id, 'description' => 'Old year, excluded']);

        $result = (new StrategicFunctionService())->currentIndicators();

        $this->assertCount(2, $result);
        $this->assertSame('Indicator A1', $result->first()->description);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/StrategicFunctionServiceTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use Illuminate\Support\Collection;

/**
 * Strategic Function is read-only, identical for every employee, and
 * inherited live from the current fiscal year's OPCR — no snapshot, ever
 * (spec: "true to all employees," campus-wide, not a per-employee commitment).
 */
class StrategicFunctionService
{
    public function currentFiscalYear(): ?int
    {
        return IPCRRatingPeriod::current()->value('year');
    }

    public function currentIndicators(): Collection
    {
        $year = $this->currentFiscalYear();
        if (! $year) {
            return collect();
        }

        return OpcrIndicator::forFiscalYear($year)
            ->with(['agencyOutcome', 'actuals'])
            ->get()
            ->sortBy(fn ($i) => $i->agencyOutcome?->outcome ?? '')
            ->values();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/StrategicFunctionServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/StrategicFunctionService.php tests/Feature/IPCRV2/StrategicFunctionServiceTest.php
git commit -m "feat(ipcr-v2): add StrategicFunctionService (live OPCR mirror)"
```

---

### Task 6: `IpcrV2GenerationService` (Generate Targets)

**Files:**
- Create: `app/Services/IPCRV2/IpcrV2GenerationService.php`
- Modify: `app/Services/EmployeeFunctionSyncService.php` (add the ipcr_v2-aware preserve-real-data guard, now that `ipcr_v2_core_items`/`ipcr_v2_support_items` exist — this was explicitly deferred in the Employee Functions plan)
- Test: `tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\EmployeeFunction` (core/support scopes), `App\Models\IPCRV2\{IpcrV2Record,IpcrV2CoreItem,IpcrV2SupportItem}`, `IpcrV2WorkflowService` (Task 4).
- Produces: `IpcrV2GenerationService::generateTargets(User $user, IPCRRatingPeriod $period): IpcrV2Record` — creates the record (if not already present for this period) and snapshots every current `EmployeeFunction` core/support row into `ipcr_v2_core_items`/`ipcr_v2_support_items`, copying `label`/`weight_percent` at generation time (frozen thereafter). Throws `ValidationException` if the sum of Core `weight_percent` values isn't within 0.5 of 100 — this is the enforcement point for the spec's "Core row weights must sum to 100% within Core" rule, deliberately placed here (at commitment time) rather than in the Employee Functions plan, so HR can tag functions incrementally before generating.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IpcrV2GenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_record_and_snapshots_core_and_support_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 60]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 2', 'weight_percent' => 40]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Committee w/o Load']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertCount(2, $record->coreItems);
        $this->assertCount(1, $record->supportItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
    }

    public function test_throws_when_core_weights_do_not_sum_to_100(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 60]);

        $this->expectException(ValidationException::class);
        (new IpcrV2GenerationService())->generateTargets($user, $period);
    }

    public function test_frozen_label_survives_a_later_employee_function_edit(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $function->update(['label' => 'Renamed Subject']);

        $this->assertSame('Subject 1', $record->fresh()->coreItems->first()->label);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IpcrV2GenerationService
{
    public function __construct(
        private IpcrV2WorkflowService $workflow = new IpcrV2WorkflowService()
    ) {}

    public function generateTargets(User $user, IPCRRatingPeriod $period): IpcrV2Record
    {
        $this->workflow->assertPeriodAcceptsNewTargets($period);
        $this->workflow->assertNoDuplicateForPeriod($user->id, $period->id);

        $coreFunctions = EmployeeFunction::where('user_id', $user->id)->core()->get();
        $supportFunctions = EmployeeFunction::where('user_id', $user->id)->support()->get();

        $this->assertCoreWeightsSumTo100($coreFunctions);

        return DB::transaction(function () use ($user, $period, $coreFunctions, $supportFunctions) {
            $record = IpcrV2Record::create([
                'user_id' => $user->id,
                'rating_period_id' => $period->id,
            ]);

            foreach ($coreFunctions as $function) {
                $record->coreItems()->create([
                    'employee_function_id' => $function->id,
                    'label' => $function->label,
                    'weight_percent' => $function->weight_percent,
                ]);
            }

            foreach ($supportFunctions as $function) {
                $record->supportItems()->create([
                    'employee_function_id' => $function->id,
                    'label' => $function->label,
                ]);
            }

            return $record->fresh(['coreItems', 'supportItems']);
        });
    }

    private function assertCoreWeightsSumTo100($coreFunctions): void
    {
        if ($coreFunctions->isEmpty()) {
            return;
        }

        $sum = (float) $coreFunctions->sum('weight_percent');
        if (abs($sum - 100) > 0.5) {
            throw ValidationException::withMessages([
                'weight_percent' => "This employee's Core Function weights sum to {$sum}%, not 100%. Fix the weights on the Employee Functions tab before generating targets.",
            ]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Add the preserve-real-data guard to `EmployeeFunctionSyncService`**

Write the failing test first, in `tests/Feature/EmployeeFunctions/EmployeeFunctionSyncServiceTest.php` (append to the existing class from the Employee Functions plan):

```php
    public function test_re_sync_never_deletes_a_row_with_real_accomplishment_data(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'BIO1', 'Biology 1', 4);

        $assignment = \App\Models\FacultyLoading\LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);
        $function = EmployeeFunction::where('user_id', $teacher->id)->first();

        $period = \App\Models\IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $teacher->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create([
            'employee_function_id' => $function->id, 'label' => 'Biology 1',
            'actual_accomplishment' => 'Taught 30 students, submitted all grades on time.',
        ]);

        $assignment->delete();
        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $this->assertDatabaseHas('employee_functions', ['id' => $function->id]);
    }
```

Run it, confirm it fails (the row is deleted today), then modify
`app/Services/EmployeeFunctionSyncService.php`'s deletion step:

```php
            // Detach any prior auto-synced row for this user/term no longer
            // represented — UNLESS it has real IPCR V2 accomplishment data
            // logged against it, in which case it's left for manual review
            // (never silently drop rated/logged work).
            EmployeeFunction::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->autoSynced()
                ->whereNotIn('id', $keptIds)
                ->whereDoesntHave('ipcrV2CoreItems', fn ($q) => $q->whereNotNull('actual_accomplishment')->where('actual_accomplishment', '!=', ''))
                ->delete();
```

Add the corresponding relation to `app/Models/EmployeeFunction.php`:

```php
    public function ipcrV2CoreItems()
    {
        return $this->hasMany(\App\Models\IPCRV2\IpcrV2CoreItem::class);
    }
```

Run the full `EmployeeFunctionSyncServiceTest` file again — all tests (including the two from the Employee Functions plan) must still pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2GenerationService.php app/Services/EmployeeFunctionSyncService.php app/Models/EmployeeFunction.php tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php tests/Feature/EmployeeFunctions/EmployeeFunctionSyncServiceTest.php
git commit -m "feat(ipcr-v2): generate targets from Employee Functions, guard sync against real data loss"
```

---

### Task 7: `IpcrV2RatingService` (final weighted rating)

**Files:**
- Create: `app/Services/IPCRV2/IpcrV2RatingService.php`
- Test: `tests/Feature/IPCRV2/IpcrV2RatingServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\IPCRWeightDistribution` (existing), `StrategicFunctionService` (Task 5), `IpcrV2Record` (Task 2).
- Produces: `IpcrV2RatingService::computeFinalRating(IpcrV2Record $record): ?float`, `::adjectivalRating(?float $value): ?string` (identical bands to `IPCRWorkflowService::adjectivalRating()`), `::currentOpcrRating(): ?float` (average of `OpcrIndicator.rating_average` for the current FY — the "campus's actual OPCR rating" the Strategic weight multiplies).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRWeightDistribution;
use App\Models\OPCR\OpcrIndicator;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2RatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2RatingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_weighted_final_rating_using_default_30_50_20_split(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. Program']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'x', 'rating_average' => 5.0]);

        $user = User::factory()->create();
        $period = IPCRRatingPeriod::first();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100, 'row_average' => 4.0]);
        $record->supportItems()->create(['label' => 'Committee', 'row_average' => 3.0]);

        $rating = (new IpcrV2RatingService())->computeFinalRating($record->fresh(['coreItems', 'supportItems']));

        // 0.30*5.0 + 0.50*4.0 + 0.20*3.0 = 1.5 + 2.0 + 0.6 = 4.1
        $this->assertEqualsWithDelta(4.1, $rating, 0.01);
    }

    public function test_uses_division_specific_weight_distribution_when_configured(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. Program']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'x', 'rating_average' => 5.0]);

        $division = \App\Models\Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        IPCRWeightDistribution::create(['division_id' => $division->id, 'strategic' => 20, 'core' => 60, 'support' => 20]);

        $user = User::factory()->create(['division_id' => $division->id]);
        $period = IPCRRatingPeriod::first();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100, 'row_average' => 4.0]);
        $record->supportItems()->create(['label' => 'Committee', 'row_average' => 3.0]);

        $rating = (new IpcrV2RatingService())->computeFinalRating($record->fresh(['coreItems', 'supportItems', 'user']));

        // 0.20*5.0 + 0.60*4.0 + 0.20*3.0 = 1.0 + 2.4 + 0.6 = 4.0
        $this->assertEqualsWithDelta(4.0, $rating, 0.01);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2RatingServiceTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRWeightDistribution;

class IpcrV2RatingService
{
    private const DEFAULT_WEIGHTS = ['strategic' => 30, 'core' => 50, 'support' => 20];

    public function __construct(
        private StrategicFunctionService $strategic = new StrategicFunctionService()
    ) {}

    public function computeFinalRating(IpcrV2Record $record): ?float
    {
        $record->loadMissing(['coreItems', 'supportItems', 'user']);

        $weights = $this->weightsFor($record->user?->division_id);

        $strategicRating = $this->currentOpcrRating();
        $coreRating = $this->weightedCoreAverage($record->coreItems);
        $supportRating = $this->simpleAverage($record->supportItems->pluck('row_average'));

        if ($strategicRating === null && $coreRating === null && $supportRating === null) {
            return null;
        }

        $total = 0;
        $total += ($weights['strategic'] / 100) * ($strategicRating ?? 0);
        $total += ($weights['core'] / 100) * ($coreRating ?? 0);
        $total += ($weights['support'] / 100) * ($supportRating ?? 0);

        return round($total, 2);
    }

    public function adjectivalRating(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $value >= 4.51 => 'Outstanding',
            $value >= 3.51 => 'Very Satisfactory',
            $value >= 2.51 => 'Satisfactory',
            $value >= 1.51 => 'Unsatisfactory',
            default => 'Poor',
        };
    }

    public function currentOpcrRating(): ?float
    {
        $indicators = $this->strategic->currentIndicators();
        $ratings = $indicators->pluck('rating_average')->filter(fn ($v) => $v !== null);

        return $ratings->isEmpty() ? null : round((float) $ratings->avg(), 2);
    }

    private function weightsFor(?int $divisionId): array
    {
        if (! $divisionId) {
            return self::DEFAULT_WEIGHTS;
        }

        $row = IPCRWeightDistribution::where('division_id', $divisionId)->first();

        return $row
            ? ['strategic' => (int) $row->strategic, 'core' => (int) $row->core, 'support' => (int) $row->support]
            : self::DEFAULT_WEIGHTS;
    }

    private function weightedCoreAverage($coreItems): ?float
    {
        $rated = $coreItems->filter(fn ($i) => $i->row_average !== null);
        if ($rated->isEmpty()) {
            return null;
        }

        $totalWeight = (float) $rated->sum('weight_percent');
        if ($totalWeight <= 0) {
            return $this->simpleAverage($rated->pluck('row_average'));
        }

        $weightedSum = $rated->sum(fn ($i) => (float) $i->row_average * (float) $i->weight_percent);

        return round($weightedSum / $totalWeight, 2);
    }

    private function simpleAverage($values): ?float
    {
        $values = collect($values)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round((float) $values->avg(), 2);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/IpcrV2RatingServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Wire `IpcrV2WorkflowService`'s finalize path to use it**

Add to `app/Services/IPCRV2/IpcrV2WorkflowService.php` (constructor and a new `finalize()` method):

```php
    public function __construct(
        private ?IPCRWorkflowService $chain = null,
        private ?\App\Services\IPCRV2\IpcrV2RatingService $rating = null
    ) {
        $this->chain ??= app(IPCRWorkflowService::class);
        $this->rating ??= app(\App\Services\IPCRV2\IpcrV2RatingService::class);
    }

    public function finalize(IpcrV2Record $ipcr, User $director): IpcrV2Record
    {
        $finalNumeric = $this->rating->computeFinalRating($ipcr);

        return $this->transition($ipcr, self::STATUS_DIRECTOR_SIGNED, [
            'director_signed_at' => now(),
            'director_signature' => $director->electronic_signature,
            'final_numeric_rating' => $finalNumeric,
            'final_adjectival_rating' => $this->rating->adjectivalRating($finalNumeric),
        ], 'ipcr_v2_director_signed');
    }
```

Write the failing test first in `tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php`, then apply the change, then confirm it passes:

```php
    public function test_finalize_computes_and_freezes_the_final_rating(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        \App\Models\AgencyOutcome::create(['outcome' => 'A']);
        \App\Models\OPCR\OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => \App\Models\AgencyOutcome::first()->id,
            'description' => 'x', 'rating_average' => 4.0,
        ]);

        $director = User::factory()->create(['electronic_signature' => 'sig.png']);
        $user = User::factory()->create();
        $record = IpcrV2Record::create([
            'user_id' => $user->id, 'rating_period_id' => IPCRRatingPeriod::first()->id,
            'status' => IpcrV2WorkflowService::STATUS_PMT_APPROVED,
        ]);
        $record->coreItems()->create(['label' => 'x', 'weight_percent' => 100, 'row_average' => 4.0]);

        $service = new IpcrV2WorkflowService();
        $service->finalize($record, $director);

        $fresh = $record->fresh();
        $this->assertSame(IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED, $fresh->status);
        $this->assertNotNull($fresh->final_numeric_rating);
        $this->assertNotNull($fresh->final_adjectival_rating);
    }
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2RatingService.php app/Services/IPCRV2/IpcrV2WorkflowService.php tests/Feature/IPCRV2/IpcrV2RatingServiceTest.php tests/Feature/IPCRV2/IpcrV2WorkflowServiceTest.php
git commit -m "feat(ipcr-v2): compute final weighted rating via IPCRWeightDistribution"
```

---

### Task 8: `EmployeeIpcrV2Controller` + routes + Employee Vue pages + shared section components

**Files:**
- Create: `routes/ipcr-v2.php`
- Modify: `routes/web.php` (add `require __DIR__.'/ipcr-v2.php';` next to the other module `require`s, `routes/web.php:2885`)
- Create: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`
- Create: `resources/js/Components/IPCRV2/IpcrV2StrategicSection.vue`
- Create: `resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue`
- Create: `resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue`
- Create: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue`
- Create: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`
- Test: `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php`

**Interfaces:**
- Consumes: `IpcrV2WorkflowService` (Task 4), `IpcrV2GenerationService` (Task 6), `StrategicFunctionService` (Task 5), `IpcrV2RatingService` (Task 7).
- Produces: routes `employee-ipcr-v2.index`, `.show`, `.generateTargets`, `.submitReview`, `.updateCoreItem`, `.updateSupportItem`, `.submitRating`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): User
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_generate_targets_creates_an_ipcr_v2_record(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $employee->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.generateTargets'), [
            'rating_period_id' => $period->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ipcr_v2_records', ['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    }

    public function test_show_is_scoped_to_owner_by_default(): void
    {
        $employee = $this->employee();
        $other = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $other->id, 'rating_period_id' => $period->id]);

        // Owner check happens on mutating routes, not show() (matches v1 —
        // DC/HR/PMT also need to view). Confirm submitReview rejects a non-owner instead.
        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id));
        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php"`
Expected: FAIL — route `employee-ipcr-v2.generateTargets` not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\IPCRV2\IpcrV2GenerationService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private IpcrV2GenerationService $generation,
        private StrategicFunctionService $strategic
    ) {}

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

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);

        return Inertia::render('IPCRV2/EmployeeIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'isOwner' => $record->user_id === request()->user()->id,
            'isMutable' => $record->isMutable(),
        ]);
    }

    public function generateTargets(Request $request)
    {
        $data = $request->validate(['rating_period_id' => 'required|exists:ipcr_rating_periods,id']);
        $period = IPCRRatingPeriod::findOrFail($data['rating_period_id']);

        $record = $this->generation->generateTargets($request->user(), $period);

        return redirect()->route('employee-ipcr-v2.show', $record->id)->with('success', 'IPCR V2 targets generated.');
    }

    public function submitForReview(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_FOR_REVIEW);

        return back()->with('success', 'Submitted for review.');
    }

    public function submitForRating(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_FOR_RATING, ['submitted_for_rating_at' => now()]);

        return back()->with('success', 'Submitted for rating.');
    }

    public function updateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'target' => 'nullable|string|max:1000',
            'actual_accomplishment' => 'nullable|string|max:1000',
        ]);
        $coreItem->update($data);

        return back()->with('success', 'Updated.');
    }

    public function updateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'actual_accomplishment' => 'nullable|string|max:1000',
            'mov_link' => 'nullable|string|max:500',
        ]);
        $supportItem->update($data);

        return back()->with('success', 'Updated.');
    }
}
```

- [ ] **Step 4: Add `open()` scope check** — `IPCRRatingPeriod::open()` already exists (confirmed in `app/Models/IPCRRatingPeriod.php`), no model change needed here.

- [ ] **Step 5: Write `routes/ipcr-v2.php`**

```php
<?php

use App\Http\Controllers\IPCRV2\EmployeeIpcrV2Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'pshs.email'])->group(function () {
    Route::middleware('permission:ipcr.v2.view')->group(function () {
        Route::get('/employee-ipcr-v2', [EmployeeIpcrV2Controller::class, 'index'])->name('employee-ipcr-v2.index');
        Route::get('/employee-ipcr-v2/{id}', [EmployeeIpcrV2Controller::class, 'show'])->name('employee-ipcr-v2.show');
    });

    Route::middleware('permission:ipcr.v2.create')->group(function () {
        Route::post('/employee-ipcr-v2/generate-targets', [EmployeeIpcrV2Controller::class, 'generateTargets'])->name('employee-ipcr-v2.generateTargets');
    });

    Route::middleware('permission:ipcr.v2.submit')->group(function () {
        Route::post('/employee-ipcr-v2/{id}/submit-review', [EmployeeIpcrV2Controller::class, 'submitForReview'])->name('employee-ipcr-v2.submitReview');
        Route::post('/employee-ipcr-v2/{id}/submit-rating', [EmployeeIpcrV2Controller::class, 'submitForRating'])->name('employee-ipcr-v2.submitRating');
    });

    Route::middleware('permission:ipcr.v2.update')->group(function () {
        Route::put('/employee-ipcr-v2/{id}/core-items/{coreItem}', [EmployeeIpcrV2Controller::class, 'updateCoreItem'])->name('employee-ipcr-v2.updateCoreItem');
        Route::put('/employee-ipcr-v2/{id}/support-items/{supportItem}', [EmployeeIpcrV2Controller::class, 'updateSupportItem'])->name('employee-ipcr-v2.updateSupportItem');
    });
});
```

Check the `auth`/`pshs.email` middleware names by grepping the top of the existing v1 IPCR route group (`routes/web.php`, near line 1600) and use whatever the live group actually declares — copy it exactly rather than guessing, since a mismatched middleware name here would 500 every route in this file.

- [ ] **Step 6: Register the route file**

In `routes/web.php`, add `require __DIR__.'/ipcr-v2.php';` alongside the other `require __DIR__.'/...'` lines near line 2885.

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php"`
Expected: PASS

- [ ] **Step 8: Write `IpcrV2StrategicSection.vue`**

```vue
<script setup>
import AppCard from "@/Components/AppCard.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"

defineProps({
  indicators: { type: Array, default: () => [] },
})
</script>

<template>
  <AppCard class="mb-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-1">Strategic Function (30%)</h3>
    <p class="text-xs text-slate-500 mb-4">
      Inherited from the current campus OPCR — identical for every employee, read-only.
    </p>
    <table class="w-full">
      <thead>
        <tr>
          <th :class="TH">Program</th>
          <th :class="TH">Indicator</th>
          <th :class="TH">Target</th>
          <th :class="TH">Actual</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="indicator in indicators" :key="indicator.id">
          <td :class="TD">{{ indicator.agency_outcome?.outcome }}</td>
          <td :class="TD">{{ indicator.description }}</td>
          <td :class="TD">{{ indicator.target }}</td>
          <td :class="TD">{{ indicator.displayed_accomplishment ?? "—" }}</td>
        </tr>
        <tr v-if="!indicators.length">
          <td :class="TD" colspan="4">No OPCR indicators for the current fiscal year yet.</td>
        </tr>
      </tbody>
    </table>
  </AppCard>
</template>
```

- [ ] **Step 9: Write `IpcrV2CoreItemsTable.vue`**

```vue
<script setup>
import AppCard from "@/Components/AppCard.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppInput from "@/Components/AppInput.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
  }, opts))
}

function rowAverage(item) {
  const parts = [item.student_feedback_rating, item.supervisor_feedback_rating, item.im_development_rating, item.timeliness_rating]
  if (parts.some(v => v === null || v === undefined)) return "—"
  const weighted = parts[0] * 0.3 + parts[1] * 0.2 + parts[2] * 0.2 + parts[3] * 0.3
  return weighted.toFixed(2)
}
</script>

<template>
  <AppCard class="mb-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-1">Core Function (50%)</h3>
    <p class="text-xs text-slate-500 mb-4">
      Student feedback 30% / Supervisor feedback 20% / IM development 20% / Timeliness 30%.
    </p>
    <table class="w-full">
      <thead>
        <tr>
          <th :class="TH">Subject / Designation</th>
          <th :class="TH">Weight %</th>
          <th :class="TH">Target</th>
          <th :class="TH">Actual Accomplishment</th>
          <th :class="TH">Row Average</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td :class="TD">{{ item.label }}</td>
          <td :class="TD">{{ item.weight_percent ?? "—" }}</td>
          <td :class="TD">
            <AppTextarea v-if="isOwner && isMutable" v-model="item.target" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.target ?? "—" }}</span>
          </td>
          <td :class="TD">
            <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
          </td>
          <td :class="TD">{{ item.row_average ?? rowAverage(item) }}</td>
        </tr>
        <tr v-if="!items.length">
          <td :class="TD" colspan="5">No Core Function rows yet — generate targets from Employee Functions.</td>
        </tr>
      </tbody>
    </table>
  </AppCard>
</template>
```

- [ ] **Step 10: Write `IpcrV2SupportItemsTable.vue`**

```vue
<script setup>
import AppCard from "@/Components/AppCard.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
})

const { submit } = useSubmit()

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    actual_accomplishment: item.actual_accomplishment,
    mov_link: item.mov_link,
  }, opts))
}
</script>

<template>
  <AppCard>
    <h3 class="text-sm font-semibold text-slate-700 mb-1">Support Function (20%)</h3>
    <table class="w-full">
      <thead>
        <tr>
          <th :class="TH">Item</th>
          <th :class="TH">Actual Accomplishment</th>
          <th :class="TH">MOV</th>
          <th :class="TH">Row Average</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td :class="TD">{{ item.label }}</td>
          <td :class="TD">
            <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
          </td>
          <td :class="TD">
            <input v-if="isOwner && isMutable" v-model="item.mov_link" class="border rounded px-2 py-1 text-xs w-full" @blur="saveEmployeeFields(item)" />
            <span v-else>{{ item.mov_link ?? "—" }}</span>
          </td>
          <td :class="TD">{{ item.row_average ?? "—" }}</td>
        </tr>
        <tr v-if="!items.length">
          <td :class="TD" colspan="4">No Support Function rows yet.</td>
        </tr>
      </tbody>
    </table>
  </AppCard>
</template>
```

- [ ] **Step 11: Write `EmployeeIpcrV2Index.vue`**

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppSelect from "@/Components/AppSelect.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ref } from "vue"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  records: Array,
  openPeriods: Array,
})

const { isSubmitting, submit } = useSubmit()
const selectedPeriod = ref(props.openPeriods[0]?.id ?? null)

function generateTargets() {
  submit((opts) => router.post(route("employee-ipcr-v2.generateTargets"), { rating_period_id: selectedPeriod.value }, opts))
}
</script>

<template>
  <Head title="My IPCR V2" />
  <AdminLayout title="My IPCR V2">
    <AppPageHeader title="My IPCR V2" subtitle="Strategic / Core / Support Functions" />

    <AppCard class="mb-6" v-if="openPeriods.length">
      <div class="flex items-end gap-3">
        <AppSelect v-model="selectedPeriod" label="Rating Period" :show-blank="false">
          <option v-for="p in openPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
        </AppSelect>
        <AppButton :disabled="isSubmitting || !selectedPeriod" @click="generateTargets">Generate Targets</AppButton>
      </div>
    </AppCard>

    <AppCard>
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Final Rating</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-sm">{{ record.final_numeric_rating ?? "—" }}</td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('employee-ipcr-v2.show', record.id))">View</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="4">No IPCR V2 records yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
```

- [ ] **Step 12: Write `EmployeeIpcrV2Show.vue`**

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  isOwner: Boolean,
  isMutable: Boolean,
})

const { isSubmitting, submit } = useSubmit()

function submitForReview() {
  submit((opts) => router.post(route("employee-ipcr-v2.submitReview", props.ipcr.id), {}, opts))
}

function submitForRating() {
  submit((opts) => router.post(route("employee-ipcr-v2.submitRating", props.ipcr.id), {}, opts))
}
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.period?.label}`" />
  <AdminLayout title="IPCR V2">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full mr-2" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <IpcrV2StrategicSection :indicators="strategicIndicators" />
    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="isOwner" :is-mutable="isMutable" />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="isOwner" :is-mutable="isMutable" />

    <div v-if="isOwner && isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'New Target'" :disabled="isSubmitting" @click="submitForReview">Submit for Review</AppButton>
      <AppButton v-if="ipcr.status === 'Targets Approved'" :disabled="isSubmitting" @click="submitForRating">Submit for Rating</AppButton>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 13: Build and manually verify**

Run: `npm run build`, then log in as a Faculty user in dev, visit `/employee-ipcr-v2`, generate targets for an open period, and confirm the Strategic section shows the current OPCR's Program A–D rows and the Core/Support sections show the employee's tagged functions.

- [ ] **Step 14: Commit**

```bash
git add routes/ipcr-v2.php routes/web.php app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php resources/js/Components/IPCRV2 resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): add employee self-service controller, routes, and screens"
```

---

### Task 9: `DivisionChiefIpcrV2Controller` + Coaching Journal + routes + Vue

**Files:**
- Create: `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php`
- Create: `app/Http/Controllers/IPCRV2/IpcrV2CoachingSessionController.php`
- Modify: `routes/ipcr-v2.php`
- Create: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Index.vue`
- Create: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue`
- Test: `tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php`

**Interfaces:**
- Consumes: `IpcrV2WorkflowService::canManage/assertCanManage/canEndorse/assertCanEndorse/transition` (Task 4), reuses `IpcrV2CoreItemsTable`/`IpcrV2SupportItemsTable`/`IpcrV2StrategicSection` (Task 8) with a `can-rate` prop for the supervisor-side rating inputs.
- Produces: routes `division-chief-ipcr-v2.index`, `.show`, `.approveTargets`, `.disapproveTargets`, `.rateCoreItem`, `.rateSupportItem`, `.submitToPMT`; `ipcr-v2-coaching-sessions.store`, `.destroy`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\Division;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionChiefIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_chief_can_approve_targets_of_their_own_division_employee(): void
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

        $response = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.approveTargets', $record->id));

        $response->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_TARGETS_APPROVED, $record->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php"`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write `DivisionChiefIpcrV2Controller`**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DivisionChiefIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private StrategicFunctionService $strategic
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

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'isMutable' => $record->isMutable(),
        ]);
    }

    public function approveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_TARGETS_APPROVED, ['target_approved_at' => now()]);

        return back()->with('success', 'Targets approved.');
    }

    public function disapproveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_RETURNED);

        return back()->with('success', 'Returned for revision.');
    }

    public function rateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

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

    public function submitToPMT(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanEndorse($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, ['submitted_for_pmtreview_at' => now()]);

        return back()->with('success', 'Submitted to PMT.');
    }
}
```

- [ ] **Step 4: Write `IpcrV2CoachingSessionController`**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2CoachingSession;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;

class IpcrV2CoachingSessionController extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

    public function store(Request $request, int $ipcrV2)
    {
        $record = IpcrV2Record::findOrFail($ipcrV2);
        $this->workflow->assertCanManage($request->user(), $record);

        $data = $request->validate([
            'activity_type' => 'required|in:monitoring,coaching',
            'mechanism' => 'required|in:one_on_one,group',
            'meeting_date' => 'required|date',
            'channel_memo' => 'boolean',
            'channel_others' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $record->coachingSessions()->create(array_merge($data, [
            'conducted_by_name' => $request->user()->name,
            'conducted_at' => now(),
        ]));

        return back()->with('success', 'Coaching session logged.');
    }

    public function destroy(Request $request, int $ipcrV2, IpcrV2CoachingSession $coachingSession)
    {
        $record = IpcrV2Record::findOrFail($ipcrV2);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($coachingSession->ipcr_v2_id !== $record->id, 404);

        $coachingSession->delete();

        return back()->with('success', 'Coaching session removed.');
    }
}
```

- [ ] **Step 5: Add routes to `routes/ipcr-v2.php`**

```php
Route::middleware(['auth', 'pshs.email', 'permission:ipcr.v2.approve'])->group(function () {
    Route::get('/division-chief/ipcr-v2', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'index'])->name('division-chief-ipcr-v2.index');
    Route::get('/division-chief/ipcr-v2/{id}', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'show'])->name('division-chief-ipcr-v2.show');
    Route::post('/division-chief/ipcr-v2/{id}/approve-targets', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'approveTargets'])->name('division-chief-ipcr-v2.approveTargets');
    Route::post('/division-chief/ipcr-v2/{id}/disapprove-targets', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'disapproveTargets'])->name('division-chief-ipcr-v2.disapproveTargets');
    Route::put('/division-chief/ipcr-v2/{id}/core-items/{coreItem}/rate', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'rateCoreItem'])->name('division-chief-ipcr-v2.rateCoreItem');
    Route::put('/division-chief/ipcr-v2/{id}/support-items/{supportItem}/rate', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'rateSupportItem'])->name('division-chief-ipcr-v2.rateSupportItem');
    Route::post('/division-chief/ipcr-v2/{id}/submit-to-pmt', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'submitToPMT'])->name('division-chief-ipcr-v2.submitToPMT');

    Route::post('/division-chief/ipcr-v2/{ipcrV2}/coaching-sessions', [\App\Http\Controllers\IPCRV2\IpcrV2CoachingSessionController::class, 'store'])->name('ipcr-v2-coaching-sessions.store');
    Route::delete('/division-chief/ipcr-v2/{ipcrV2}/coaching-sessions/{coachingSession}', [\App\Http\Controllers\IPCRV2\IpcrV2CoachingSessionController::class, 'destroy'])->name('ipcr-v2-coaching-sessions.destroy');
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php"`
Expected: PASS

- [ ] **Step 7: Write `DivisionChiefIpcrV2Index.vue`**

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"

defineProps({ records: Array })
</script>

<template>
  <Head title="My Division — IPCR V2" />
  <AdminLayout title="My Division — IPCR V2">
    <AppPageHeader title="My Division" subtitle="IPCR V2" />
    <AppCard>
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Employee</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3 text-sm">{{ record.user?.name }}</td>
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('division-chief-ipcr-v2.show', record.id))">Review</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="4">No IPCR V2 records in your division yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
```

- [ ] **Step 8: Write `DivisionChiefIpcrV2Show.vue`**

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  isMutable: Boolean,
})

const { isSubmitting, submit } = useSubmit()

function approveTargets() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.approveTargets", props.ipcr.id), {}, opts))
}
function disapproveTargets() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.disapproveTargets", props.ipcr.id), {}, opts))
}
function submitToPMT() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.submitToPMT", props.ipcr.id), {}, opts))
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

    <IpcrV2StrategicSection :indicators="strategicIndicators" />
    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="isMutable" can-rate />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="isMutable" />

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'For Review'" variant="secondary" :disabled="isSubmitting" @click="disapproveTargets">Return for Revision</AppButton>
      <AppButton v-if="ipcr.status === 'For Review'" :disabled="isSubmitting" @click="approveTargets">Approve Targets</AppButton>
      <AppButton v-if="ipcr.status === 'Rated & For PMT Review'" :disabled="isSubmitting" @click="submitToPMT">Submit to PMT</AppButton>
    </div>
  </AdminLayout>
</template>
```

Note: the DC's rating inputs (4-part rubric per Core item, Q/E/T per Support item) are not yet exposed in `IpcrV2CoreItemsTable`/`IpcrV2SupportItemsTable` — Step 9 below extends both shared components with a `can-rate` mode.

- [ ] **Step 9: Extend `IpcrV2CoreItemsTable.vue` and `IpcrV2SupportItemsTable.vue` with rating inputs**

In `resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue`, add a `canRate` prop and, when true, render four `<select>` 1–5 inputs per row plus a "Rate" button that calls `division-chief-ipcr-v2.rateCoreItem`:

```vue
<script setup>
// ...existing imports and props from Step 9 above, plus:
defineProps({
  // ...existing props...
  canRate: { type: Boolean, default: false },
})

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateCoreItem", [props.ipcrId, item.id]), {
    student_feedback_rating: item.student_feedback_rating,
    supervisor_feedback_rating: item.supervisor_feedback_rating,
    im_development_rating: item.im_development_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}
</script>
```

Add a ratings column in the template (after "Row Average"):

```vue
          <td v-if="canRate" :class="TD">
            <div class="flex gap-1">
              <select v-model.number="item.student_feedback_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :value="n">{{ n }}</option></select>
              <select v-model.number="item.supervisor_feedback_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :value="n">{{ n }}</option></select>
              <select v-model.number="item.im_development_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :value="n">{{ n }}</option></select>
              <select v-model.number="item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :value="n">{{ n }}</option></select>
              <button type="button" class="text-xs text-indigo-600" @click="rate(item)">Save</button>
            </div>
          </td>
```

Apply the analogous change to `IpcrV2SupportItemsTable.vue` (three 1–5 selects: quality/efficiency/timeliness, calling `division-chief-ipcr-v2.rateSupportItem`).

- [ ] **Step 10: Build and manually verify**

Run: `npm run build`, log in as a Division Chief, visit `/division-chief/ipcr-v2`, open an employee's record in "For Review" status, approve targets, then once in "Rated & For PMT Review" rate a Core and Support item and submit to PMT.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php app/Http/Controllers/IPCRV2/IpcrV2CoachingSessionController.php routes/ipcr-v2.php resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Index.vue resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): add Division Chief review, ratings, and Coaching Journal"
```

---

### Task 10: `PMTIpcrV2Controller` + routes + Vue

**Files:**
- Create: `app/Http/Controllers/IPCRV2/PMTIpcrV2Controller.php`
- Modify: `routes/ipcr-v2.php`
- Create: `resources/js/Pages/IPCRV2/PMTIpcrV2Index.vue`
- Create: `resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue`
- Test: `tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php`

**Interfaces:**
- Consumes: `IpcrV2WorkflowService::finalize` (Task 7's addition), `transition`.
- Produces: routes `pmt-ipcr-v2.index`, `.show`, `.approve`, `.return`, `.directorSign`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PMTIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function pmtUser(): User
    {
        $role = Role::create(['name' => 'PMT']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_pmt_can_approve_a_submitted_record(): void
    {
        $pmt = $this->pmtUser();
        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
        ]);

        $response = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.approve', $record->id));

        $response->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_PMT_APPROVED, $record->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php"`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PMTIpcrV2Controller extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

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

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);

        return Inertia::render('IPCRV2/PMTIpcrV2Show', ['ipcr' => $record, 'isMutable' => $record->isMutable()]);
    }

    public function approve(int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_PMT_APPROVED);

        return back()->with('success', 'Approved by PMT.');
    }

    public function returnForRevision(int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_PMT_RETURNED);

        return back()->with('success', 'Returned for revision.');
    }

    public function directorSign(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->finalize($record, $request->user());

        return back()->with('success', 'Director signed.');
    }
}
```

- [ ] **Step 4: Add routes to `routes/ipcr-v2.php`**

```php
Route::middleware(['auth', 'pshs.email', 'permission:ipcr.v2.approve'])->group(function () {
    Route::get('/pmt/ipcr-v2', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'index'])->name('pmt-ipcr-v2.index');
    Route::get('/pmt/ipcr-v2/{id}', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'show'])->name('pmt-ipcr-v2.show');
    Route::post('/pmt/ipcr-v2/{id}/approve', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'approve'])->name('pmt-ipcr-v2.approve');
    Route::post('/pmt/ipcr-v2/{id}/return', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'returnForRevision'])->name('pmt-ipcr-v2.return');
    Route::post('/pmt/ipcr-v2/{id}/director-sign', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'directorSign'])->name('pmt-ipcr-v2.directorSign');
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php"`
Expected: PASS

- [ ] **Step 6: Write `PMTIpcrV2Index.vue`** (same table shape as `DivisionChiefIpcrV2Index.vue` from Task 9, `route('pmt-ipcr-v2.show', ...)` instead)

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"

defineProps({ records: Array })
</script>

<template>
  <Head title="PMT — IPCR V2" />
  <AdminLayout title="PMT — IPCR V2">
    <AppPageHeader title="PMT Review" subtitle="IPCR V2" />
    <AppCard>
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Employee</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3 text-sm">{{ record.user?.name }}</td>
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('pmt-ipcr-v2.show', record.id))">Review</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="4">Nothing submitted to PMT yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
```

- [ ] **Step 7: Write `PMTIpcrV2Show.vue`**

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({ ipcr: Object, isMutable: Boolean })
const { isSubmitting, submit } = useSubmit()

function approve() {
  submit((opts) => router.post(route("pmt-ipcr-v2.approve", props.ipcr.id), {}, opts))
}
function returnForRevision() {
  submit((opts) => router.post(route("pmt-ipcr-v2.return", props.ipcr.id), {}, opts))
}
function directorSign() {
  submit((opts) => router.post(route("pmt-ipcr-v2.directorSign", props.ipcr.id), {}, opts))
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

    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="false" />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="false" />

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" variant="secondary" :disabled="isSubmitting" @click="returnForRevision">Return</AppButton>
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" :disabled="isSubmitting" @click="approve">Approve</AppButton>
      <AppButton v-if="ipcr.status === 'Approved by PMT'" :disabled="isSubmitting" @click="directorSign">Director Sign</AppButton>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 8: Build and manually verify**

Run: `npm run build`, log in as a PMT user, visit `/pmt/ipcr-v2`, approve a "Submitted to PMT" record, then Director-sign an "Approved by PMT" one and confirm `final_numeric_rating`/`final_adjectival_rating` appear.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/IPCRV2/PMTIpcrV2Controller.php routes/ipcr-v2.php resources/js/Pages/IPCRV2/PMTIpcrV2Index.vue resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue tests/Feature/IPCRV2/PMTIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): add PMT review and Director sign-off"
```

---

### Task 11: `HRIpcrV2Controller` + routes + Vue

**Files:**
- Create: `app/Http/Controllers/IPCRV2/HRIpcrV2Controller.php`
- Modify: `routes/ipcr-v2.php`
- Create: `resources/js/Pages/IPCRV2/HRIpcrV2Index.vue`
- Create: `resources/js/Pages/IPCRV2/HRIpcrV2Show.vue`
- Test: `tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php`

**Interfaces:**
- Produces: routes `hr-ipcr-v2.index`, `.show`, `.submitToPMT`, `.batchSubmitToPMT`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HRIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function hrUser(): User
    {
        $role = Role::create(['name' => 'HR']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.monitor', 'ipcr.v2.admin'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_hr_can_batch_submit_rated_records_to_pmt(): void
    {
        $hr = $this->hrUser();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();
        $recordA = IpcrV2Record::create(['user_id' => $employeeA->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_HR]);
        $recordB = IpcrV2Record::create(['user_id' => $employeeB->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_HR]);

        $response = $this->actingAs($hr)->post(route('hr-ipcr-v2.batchSubmitToPMT'), [
            'ids' => [$recordA->id, $recordB->id],
        ]);

        $response->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $recordA->fresh()->status);
        $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $recordB->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php"`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HRIpcrV2Controller extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/HRIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);

        return Inertia::render('IPCRV2/HRIpcrV2Show', ['ipcr' => $record]);
    }

    public function submitToPMT(int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_SUBMITTED_PMT);

        return back()->with('success', 'Submitted to PMT.');
    }

    public function batchSubmitToPMT(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:ipcr_v2_records,id']);

        foreach (IpcrV2Record::whereIn('id', $data['ids'])->get() as $record) {
            if ($record->status === IpcrV2WorkflowService::STATUS_SUBMITTED_HR) {
                $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_SUBMITTED_PMT);
            }
        }

        return back()->with('success', 'Batch submitted to PMT.');
    }
}
```

- [ ] **Step 4: Add routes to `routes/ipcr-v2.php`**

```php
Route::middleware(['auth', 'pshs.email', 'permission:ipcr.v2.monitor'])->group(function () {
    Route::get('/hr/ipcr-v2', [\App\Http\Controllers\IPCRV2\HRIpcrV2Controller::class, 'index'])->name('hr-ipcr-v2.index');
    Route::get('/hr/ipcr-v2/{id}', [\App\Http\Controllers\IPCRV2\HRIpcrV2Controller::class, 'show'])->name('hr-ipcr-v2.show');
    Route::post('/hr/ipcr-v2/{id}/submit-to-pmt', [\App\Http\Controllers\IPCRV2\HRIpcrV2Controller::class, 'submitToPMT'])->name('hr-ipcr-v2.submitToPMT');
    Route::post('/hr/ipcr-v2/batch-submit-to-pmt', [\App\Http\Controllers\IPCRV2\HRIpcrV2Controller::class, 'batchSubmitToPMT'])->name('hr-ipcr-v2.batchSubmitToPMT');
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php"`
Expected: PASS

- [ ] **Step 6: Write `HRIpcrV2Index.vue`** (same list shape as Task 10's `PMTIpcrV2Index.vue`, add row checkboxes + a "Batch Submit to PMT" button posting the selected ids to `hr-ipcr-v2.batchSubmitToPMT`)

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ref, computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({ records: Array })
const { isSubmitting, submit } = useSubmit()
const selected = ref([])

const submittedIds = computed(() => props.records.filter(r => r.status === "Submitted to HR").map(r => r.id))

function batchSubmit() {
  submit((opts) => router.post(route("hr-ipcr-v2.batchSubmitToPMT"), { ids: selected.value }, opts), {
    onSuccess: () => { selected.value = [] },
  })
}
</script>

<template>
  <Head title="HR — IPCR V2" />
  <AdminLayout title="HR — IPCR V2 Monitoring">
    <AppPageHeader title="HR IPCR V2 Monitoring" />
    <AppCard>
      <div class="flex justify-end mb-3">
        <AppButton :disabled="isSubmitting || !selected.length" @click="batchSubmit">Batch Submit to PMT ({{ selected.length }})</AppButton>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th class="px-4 py-2"></th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Employee</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3">
              <input v-if="record.status === 'Submitted to HR'" type="checkbox" :value="record.id" v-model="selected" />
            </td>
            <td class="px-4 py-3 text-sm">{{ record.user?.name }}</td>
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('hr-ipcr-v2.show', record.id))">View</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="5">No IPCR V2 records yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
```

- [ ] **Step 7: Write `HRIpcrV2Show.vue`** (read-only variant of `PMTIpcrV2Show.vue` from Task 10 — same shared components, no action buttons, since HR's only mutating action is the batch-submit on the index page)

```vue
<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

defineProps({ ipcr: Object })
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.user?.name}`" />
  <AdminLayout title="HR IPCR V2 Monitoring">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="false" />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="false" />
  </AdminLayout>
</template>
```

- [ ] **Step 8: Build and manually verify**

Run: `npm run build`, log in as an HR user, visit `/hr/ipcr-v2`, select two "Submitted to HR" rows, batch submit to PMT, confirm both move to "Submitted to PMT."

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/IPCRV2/HRIpcrV2Controller.php routes/ipcr-v2.php resources/js/Pages/IPCRV2/HRIpcrV2Index.vue resources/js/Pages/IPCRV2/HRIpcrV2Show.vue tests/Feature/IPCRV2/HRIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): add HR monitoring and batch submit to PMT"
```

---

### Task 12: `AdminIpcrV2Controller` (unscoped monitor) + sidebar entry

**Files:**
- Create: `app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php`
- Modify: `routes/ipcr-v2.php`
- Create: `resources/js/Pages/IPCRV2/AdminIpcrV2Index.vue`
- Modify: `resources/js/Layouts/navigation.js` (add "IPCR V2" nav entries)
- Test: `tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php`

**Interfaces:**
- Produces: routes `admin-ipcr-v2.index`, `.show`, gated `role:Administrator` (matches v1's `AdminIPCRController` convention exactly — a raw role middleware, not a permission, since `isSuperAdmin()` bypasses permission checks but not raw role checks).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_every_record_unscoped(): void
    {
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        IpcrV2Record::create(['user_id' => User::factory()->create()->id, 'rating_period_id' => $period->id]);
        IpcrV2Record::create(['user_id' => User::factory()->create()->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($admin)->get(route('admin-ipcr-v2.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('records', 2));
    }

    public function test_non_administrator_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin-ipcr-v2.index'))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php"`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use Inertia\Inertia;

class AdminIpcrV2Controller extends Controller
{
    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/AdminIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);

        return Inertia::render('IPCRV2/HRIpcrV2Show', ['ipcr' => $record]);
    }
}
```

- [ ] **Step 4: Add routes to `routes/ipcr-v2.php`**

```php
Route::middleware(['auth', 'pshs.email', 'role:Administrator'])->group(function () {
    Route::get('/admin/ipcr-v2', [\App\Http\Controllers\IPCRV2\AdminIpcrV2Controller::class, 'index'])->name('admin-ipcr-v2.index');
    Route::get('/admin/ipcr-v2/{id}', [\App\Http\Controllers\IPCRV2\AdminIpcrV2Controller::class, 'show'])->name('admin-ipcr-v2.show');
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php"`
Expected: PASS

- [ ] **Step 6: Write `AdminIpcrV2Index.vue`** (same shape as `HRIpcrV2Index.vue` from Task 11, without the batch-submit action, `route('admin-ipcr-v2.show', ...)`)

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"

defineProps({ records: Array })
</script>

<template>
  <Head title="IPCR V2 Monitoring" />
  <AdminLayout title="IPCR V2 Monitoring">
    <AppPageHeader title="IPCR V2 Monitoring" subtitle="All employees, every status" />
    <AppCard>
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Employee</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3 text-sm">{{ record.user?.name }}</td>
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('admin-ipcr-v2.show', record.id))">View</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="4">No IPCR V2 records yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
```

- [ ] **Step 7: Add sidebar entries**

In `resources/js/Layouts/navigation.js`, add a new "IPCR V2" section near the existing "IPCR" entry (around line 713):

```js
      {
        label: "IPCR V2",
        routeName: "employee-ipcr-v2.index",
        href: route("employee-ipcr-v2.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.v2.view"],
      },
```

And near the existing "My Division" entry (around line 748):

```js
      {
        label: "My Division (V2)",
        routeName: "division-chief-ipcr-v2.index",
        href: route("division-chief-ipcr-v2.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.v2.approve"],
      },
```

Find the existing "PMT" and "HR" IPCR nav sections in the same file (search for `pmt-ipcr.index` and `hr-ipcr.index`) and add sibling entries for `pmt-ipcr-v2.index` (`permissions: ["ipcr.v2.approve"]`) and `hr-ipcr-v2.index` (`permissions: ["ipcr.v2.monitor"]`) directly beside them, following the exact same object shape.

- [ ] **Step 8: Build and manually verify**

Run: `npm run build`, log in as Administrator, visit `/admin/ipcr-v2`, confirm every record across every employee/status is listed regardless of division. Then confirm the new "IPCR V2" sidebar entries appear for a Faculty, Division Chief, PMT, and HR user respectively (each only sees their own scoped entry).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php routes/ipcr-v2.php resources/js/Pages/IPCRV2/AdminIpcrV2Index.vue resources/js/Layouts/navigation.js tests/Feature/IPCRV2/AdminIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): add unscoped Admin monitor and sidebar entries"
```

---

### Task 13: Full regression run

**Files:** none (verification-only task).

- [ ] **Step 1: Run the full backend test suite**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: every `IPCRV2`/`EmployeeFunctions` test passes; the pre-existing baseline failure count (if any — check via `git log`/prior session notes before this branch) is unchanged, confirming zero regressions in v1 IPCR, OPCR, or any other module.

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`
Expected: no errors, no missing-import warnings for any new `IPCRV2` component.

- [ ] **Step 3: Manual click-through in dev**

Using the `run` skill or a browser session against `http://localhost:8080`, walk one full cycle end-to-end: as a Faculty employee — sync Employee Functions from Faculty Loading, generate IPCR V2 targets, submit for review; as their Division Chief — approve targets; as the employee — submit for rating; as the Division Chief — rate a Core item (4-part rubric) and a Support item, submit to PMT; as PMT — approve, then Director-sign; confirm `final_numeric_rating`/`final_adjectival_rating` compute and display correctly on the Employee's own Show page.

- [ ] **Step 4: Commit any fixes found during click-through, otherwise no commit needed for this task.**

---

## Self-Review Notes

- **Spec coverage**: isolation (namespaces/tables/routes/permissions) ✓ Tasks 1-3, Employee Functions consumption ✓ Task 6, Strategic Function live OPCR mirror ✓ Task 5/8, Core Function 4-part rubric ✓ Task 8-9, Support Function ✓ Task 8-9, workflow/roles reuse ✓ Task 4/9-12, final rating via `IPCRWeightDistribution` ✓ Task 7, Coaching Journal port ✓ Task 9. Research Advising annex remains explicitly out of scope per the spec's Non-goals — not built here.
- **Type consistency**: `IpcrV2WorkflowService::STATUS_*` constants (Task 4) are used identically across every controller (Tasks 8-12) and every Vue page's status-string comparisons (`ipcr.status === 'For Review'` etc.) — verified these match the constant *values*, not invented strings.
- **Open item carried from the spec**: `emp_category` was flagged in the spec as needing verification — resolved during planning (not deferred to implementation): v1's actual Faculty/Non-Teaching check is `hasRole('Faculty') || (bool) academic_unit_id` (`EmployeeIPCRController.php:68`), confirmed via direct grep, not assumed. Both plans use this exact check.
