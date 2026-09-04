# OPCR Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a campus-level OPCR (Office Performance Commitment and Review) module — indicator tagging against the existing DOST Strategic Plan hierarchy, quarterly actual tracking, externally-supplied ratings, division accountability, FY clone-forward, and a matching PDF export.

**Architecture:** Four new tables (`opcr_periods`, `opcr_indicators`, `opcr_indicator_divisions`, `opcr_indicator_actuals`) under a new `App\Models\OPCR` namespace, fully isolated from `performance_indicators`/`WorkDistributionPlan` (only an optional one-way FK cross-reference). Reuses the existing `DostPillar`/`DostStrategy`/`DostSubStrategy`/`AgencyOutcome` models unchanged for tagging. Two controllers (`OpcrPeriodController`, `OpcrIndicatorController`) under `App\Http\Controllers\OPCR`, one Inertia page, one mPDF export service.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, mPDF 8, PHPUnit (`tests/Feature`), `node --test` for pure JS utilities (`tests/js`).

**Spec:** `docs/superpowers/specs/2026-09-04-opcr-module-design.md`

## Global Constraints

- All new tables are additive-only migrations — no changes to existing table schemas (per this project's blue-green migration discipline).
- `opcr_indicators.performance_indicator_id` is an optional, one-directional cross-reference only — nothing in this module ever writes to `performance_indicators`, `work_distribution_plans`, or their controllers except the two narrowly-scoped delete-guard additions in Task 9.
- No new roles. Gate everything with two new permissions: `opcr.view`, `opcr.manage`.
- Controllers: thin, `Inertia::render`, `back()->with('success', ...)` on mutations — per project convention (CLAUDE.md).
- Vue: `<script setup>`, reuse existing `App*` components (`AppTable`, `AppModal`, `AppSelect`, `AppInput`, `AppTextarea`, `AppButton`, `AppIconButton`, `AppPageHeader`, `AppFilterBar`, `EmptyState`, `PaginationControl`, `FiscalYearFilter`) and `vue-multiselect` for division pickers — matching `resources/js/Pages/PerformanceManagement/PerformanceIndicators.vue`.
- No `FormData`/multipart uploads anywhere in this module (none needed — no file uploads).
- PHP tests: PHPUnit `TestCase` classes in `tests/Feature`, matching `tests/Feature/DostStrategicPlanControllerTest.php`'s style (`RefreshDatabase`, `actingAs`, `assertInertia`). JS tests: `node --test tests/js/*.test.mjs`, matching `tests/js/outcomeGrouping.test.mjs`.
- Run PHP tests in the dev container: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=<TestClass>"`.
- Run JS tests: `npm run test:js` (from the repo root, no container needed).

---

### Task 1: Migrations + Models

**Files:**
- Create: `database/migrations/2026_09_04_090000_create_opcr_periods_table.php`
- Create: `database/migrations/2026_09_04_090100_create_opcr_indicators_table.php`
- Create: `database/migrations/2026_09_04_090200_create_opcr_indicator_divisions_table.php`
- Create: `database/migrations/2026_09_04_090300_create_opcr_indicator_actuals_table.php`
- Create: `app/Models/OPCR/OpcrPeriod.php`
- Create: `app/Models/OPCR/OpcrIndicator.php`
- Create: `app/Models/OPCR/OpcrIndicatorActual.php`
- Test: `tests/Feature/OPCR/OpcrModelsTest.php`

**Interfaces:**
- Produces: `OpcrPeriod::indicators()` (hasMany `OpcrIndicator`), `OpcrPeriod::scopeCurrent($query)`, `OpcrIndicator::period()`, `OpcrIndicator::subStrategy()`, `OpcrIndicator::agencyOutcome()`, `OpcrIndicator::performanceIndicator()`, `OpcrIndicator::divisions()` (belongsToMany `Division`), `OpcrIndicator::actuals()` (hasMany `OpcrIndicatorActual`), `OpcrIndicatorActual::indicator()`. Every later task's controllers/tests use these exact method names.

- [ ] **Step 1: Write the failing model/relationship test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrPeriod;
use App\Models\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_belongs_to_period_and_walks_up_the_dost_hierarchy(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Institutionalize FORWARD Framework']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'dost_sub_strategy_id' => $subStrategy->id,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Percentage of freshmen with GWA 2.5 or better',
            'target' => '0.96',
        ]);
        $indicator->divisions()->sync([$division->id]);

        $this->assertTrue($period->indicators->contains($indicator));
        $this->assertEquals($subStrategy->id, $indicator->subStrategy->id);
        $this->assertEquals($strategy->id, $indicator->subStrategy->strategy->id);
        $this->assertEquals($pillar->id, $indicator->subStrategy->strategy->pillar->id);
        $this->assertEquals('A. STEM Secondary Education', $indicator->agencyOutcome->outcome);
        $this->assertTrue($indicator->divisions->contains($division));
    }

    public function test_indicator_optionally_cross_references_an_existing_performance_indicator(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Existing IPCR indicator',
            'target' => '10',
        ]);

        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'performance_indicator_id' => $pi->id,
            'description' => 'OPCR indicator, cross-referenced',
        ]);

        $this->assertEquals($pi->id, $indicator->performanceIndicator->id);
    }

    public function test_indicator_without_dost_tagging_or_cross_reference_is_still_valid(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);

        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'description' => 'Untagged indicator',
        ]);

        $this->assertNull($indicator->subStrategy);
        $this->assertNull($indicator->agencyOutcome);
        $this->assertNull($indicator->performanceIndicator);
    }

    public function test_actuals_track_one_row_per_quarter(): void
    {
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.5']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 2, 'value' => '0.7']);

        $this->assertCount(2, $indicator->fresh()->actuals);
        $this->assertEquals('0.5', $indicator->actuals()->where('quarter', 1)->first()->value);
    }

    public function test_scope_current_returns_only_the_current_period(): void
    {
        OpcrPeriod::create(['fiscal_year' => 2025, 'period_label' => 'FY2025', 'is_current' => false]);
        $current = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => true]);

        $result = OpcrPeriod::current()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($current->id, $result->first()->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrModelsTest"`
Expected: FAIL — classes `App\Models\OPCR\OpcrPeriod` etc. don't exist yet.

- [ ] **Step 3: Create the migrations**

```php
// database/migrations/2026_09_04_090000_create_opcr_periods_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->unique();
            $table->string('period_label');
            $table->boolean('is_current')->default(false);
            $table->string('campus_director_name')->nullable();
            $table->string('oic_campus_director_name')->nullable();
            $table->string('executive_director_name')->nullable();
            $table->text('commitment_statement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_periods');
    }
};
```

```php
// database/migrations/2026_09_04_090100_create_opcr_indicators_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opcr_period_id')->constrained('opcr_periods')->cascadeOnDelete();
            $table->foreignId('dost_sub_strategy_id')->nullable()->constrained('dost_sub_strategies')->restrictOnDelete();
            $table->foreignId('agency_outcome_id')->nullable()->constrained('agency_org_outcomes')->restrictOnDelete();
            $table->foreignId('performance_indicator_id')->nullable()->constrained('performance_indicators')->nullOnDelete();
            $table->text('description');
            $table->string('target')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('rating_quality', 3, 2)->nullable();
            $table->decimal('rating_efficiency', 3, 2)->nullable();
            $table->decimal('rating_timeliness', 3, 2)->nullable();
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_indicators');
    }
};
```

```php
// database/migrations/2026_09_04_090200_create_opcr_indicator_divisions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_indicator_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opcr_indicator_id')->constrained('opcr_indicators')->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_indicator_divisions');
    }
};
```

```php
// database/migrations/2026_09_04_090300_create_opcr_indicator_actuals_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_indicator_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opcr_indicator_id')->constrained('opcr_indicators')->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->string('value')->nullable();
            $table->timestamps();
            $table->unique(['opcr_indicator_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_indicator_actuals');
    }
};
```

- [ ] **Step 4: Create the models**

```php
<?php
// app/Models/OPCR/OpcrPeriod.php

namespace App\Models\OPCR;

use Illuminate\Database\Eloquent\Model;

class OpcrPeriod extends Model
{
    protected $fillable = [
        'fiscal_year',
        'period_label',
        'is_current',
        'campus_director_name',
        'oic_campus_director_name',
        'executive_director_name',
        'commitment_statement',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function indicators()
    {
        return $this->hasMany(OpcrIndicator::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
```

```php
<?php
// app/Models/OPCR/OpcrIndicator.php

namespace App\Models\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostSubStrategy;
use App\Models\PerformanceIndicator;
use Illuminate\Database\Eloquent\Model;

class OpcrIndicator extends Model
{
    protected $fillable = [
        'opcr_period_id',
        'dost_sub_strategy_id',
        'agency_outcome_id',
        'performance_indicator_id',
        'description',
        'target',
        'budget',
        'remarks',
        'rating_quality',
        'rating_efficiency',
        'rating_timeliness',
        'rating_average',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'rating_quality' => 'decimal:2',
        'rating_efficiency' => 'decimal:2',
        'rating_timeliness' => 'decimal:2',
        'rating_average' => 'decimal:2',
    ];

    public function period()
    {
        return $this->belongsTo(OpcrPeriod::class, 'opcr_period_id');
    }

    public function subStrategy()
    {
        return $this->belongsTo(DostSubStrategy::class, 'dost_sub_strategy_id');
    }

    public function agencyOutcome()
    {
        return $this->belongsTo(AgencyOutcome::class, 'agency_outcome_id');
    }

    public function performanceIndicator()
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id');
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'opcr_indicator_divisions');
    }

    public function actuals()
    {
        return $this->hasMany(OpcrIndicatorActual::class);
    }
}
```

```php
<?php
// app/Models/OPCR/OpcrIndicatorActual.php

namespace App\Models\OPCR;

use Illuminate\Database\Eloquent\Model;

class OpcrIndicatorActual extends Model
{
    protected $fillable = ['opcr_indicator_id', 'quarter', 'value'];

    public function indicator()
    {
        return $this->belongsTo(OpcrIndicator::class, 'opcr_indicator_id');
    }
}
```

- [ ] **Step 5: Run migrations in dev**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate"`
Expected: 4 new migrations run successfully.

- [ ] **Step 6: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrModelsTest"`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_04_090000_create_opcr_periods_table.php \
        database/migrations/2026_09_04_090100_create_opcr_indicators_table.php \
        database/migrations/2026_09_04_090200_create_opcr_indicator_divisions_table.php \
        database/migrations/2026_09_04_090300_create_opcr_indicator_actuals_table.php \
        app/Models/OPCR/OpcrPeriod.php app/Models/OPCR/OpcrIndicator.php app/Models/OPCR/OpcrIndicatorActual.php \
        tests/Feature/OPCR/OpcrModelsTest.php
git commit -m "feat(opcr): add OPCR schema and models"
```

---

### Task 2: Permissions seeder

**Files:**
- Create: `database/seeders/OpcrPermissionSeeder.php`
- Test: `tests/Feature/OPCR/OpcrPermissionSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\Permission`, `App\Models\Role` (existing).
- Produces: permissions `opcr.view`, `opcr.manage` in the `permissions` table, granted to roles `OCD`, `PMT` (both `opcr.view` + `opcr.manage`) and `DivisionChief` (`opcr.view` only). Later tasks' route groups use these exact permission strings.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\OpcrPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permissions_and_grants_them_to_the_right_roles(): void
    {
        $ocd = Role::create(['name' => 'OCD']);
        $pmt = Role::create(['name' => 'PMT']);
        $divisionChief = Role::create(['name' => 'DivisionChief']);

        (new OpcrPermissionSeeder())->run();

        $view = Permission::where('name', 'opcr.view')->firstOrFail();
        $manage = Permission::where('name', 'opcr.manage')->firstOrFail();

        $this->assertTrue($ocd->fresh()->permissions->contains($view));
        $this->assertTrue($ocd->fresh()->permissions->contains($manage));
        $this->assertTrue($pmt->fresh()->permissions->contains($view));
        $this->assertTrue($pmt->fresh()->permissions->contains($manage));
        $this->assertTrue($divisionChief->fresh()->permissions->contains($view));
        $this->assertFalse($divisionChief->fresh()->permissions->contains($manage));
    }

    public function test_seeder_is_idempotent(): void
    {
        Role::create(['name' => 'OCD']);

        (new OpcrPermissionSeeder())->run();
        (new OpcrPermissionSeeder())->run();

        $this->assertEquals(1, Permission::where('name', 'opcr.view')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPermissionSeederTest"`
Expected: FAIL — `Database\Seeders\OpcrPermissionSeeder` doesn't exist.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * OPCR module permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\OpcrPermissionSeeder --force
 */
class OpcrPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'opcr.view' => 'View the campus OPCR (Office Performance Commitment and Review)',
        'opcr.manage' => 'Create/edit OPCR periods, indicators, targets, actuals, and ratings',
    ];

    private const MANAGE_ROLES = ['OCD', 'PMT'];

    private const VIEW_ONLY_ROLES = ['DivisionChief'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'OPCR',
                'description' => $description,
            ]);
        }

        $this->grant(self::MANAGE_ROLES, ['opcr.view', 'opcr.manage']);
        $this->grant(self::VIEW_ONLY_ROLES, ['opcr.view']);
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

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPermissionSeederTest"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/OpcrPermissionSeeder.php tests/Feature/OPCR/OpcrPermissionSeederTest.php
git commit -m "feat(opcr): add opcr.view/opcr.manage permission seeder"
```

---

### Task 3: `OpcrPeriodController@index` — grouped tree + division scoping

**Files:**
- Create: `app/Http/Controllers/OPCR/OpcrPeriodController.php`
- Modify: `routes/web.php` (new `permission:opcr.view|opcr.manage` group, placed after the existing `ipcr.view` group at line ~1261)
- Test: `tests/Feature/OPCR/OpcrPeriodIndexTest.php`

**Interfaces:**
- Consumes: `OpcrPeriod`, `OpcrIndicator` (Task 1), `DostPillar`/`DostStrategy`/`DostSubStrategy`/`AgencyOutcome`/`PerformanceIndicator`/`Division` (existing).
- Produces: route `opcr.index` (`GET /opcr`); Inertia page `PerformanceManagement/Opcr` with props `period`, `periods`, `indicators`, `pillars`, `agencyOutcomes`, `performanceIndicators`, `divisions`, `canManage`. Task 13 (frontend) consumes this exact prop shape.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodIndexTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $roleName, array $permNames): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        foreach ($permNames as $permName) {
            $permission = Permission::firstOrCreate(['name' => $permName], ['module' => 'OPCR', 'description' => $permName]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_manage_user_sees_all_indicators_in_the_current_period(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => true]);
        $divisionA = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $divisionB = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $i1 = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator 1']);
        $i1->divisions()->sync([$divisionA->id]);
        $i2 = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator 2']);
        $i2->divisions()->sync([$divisionB->id]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr')
            ->where('period.id', $period->id)
            ->has('indicators', 2)
            ->where('canManage', true)
        );
    }

    public function test_division_chief_sees_only_their_divisions_indicators_and_cannot_manage(): void
    {
        $user = $this->userWithPermission('DivisionChief', ['opcr.view']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $otherDivision = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $user->update(['division_id' => $division->id]);

        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => true]);
        $mine = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Mine']);
        $mine->divisions()->sync([$division->id]);
        $notMine = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Not mine']);
        $notMine->divisions()->sync([$otherDivision->id]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr')
            ->has('indicators', 1)
            ->where('indicators.0.id', $mine->id)
            ->where('canManage', false)
        );
    }

    public function test_index_renders_with_no_current_period(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr')
            ->where('period', null)
            ->has('indicators', 0)
        );
    }

    public function test_user_without_opcr_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodIndexTest"`
Expected: FAIL — route `opcr.index` doesn't exist.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\OPCR;

use App\Http\Controllers\Controller;
use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\OPCR\OpcrPeriod;
use App\Models\PerformanceIndicator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpcrPeriodController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canManage = $user->isSuperAdmin() || $user->hasPermission('opcr.manage');

        $period = OpcrPeriod::current()->first();

        $indicatorsQuery = $period
            ? $period->indicators()->with([
                'subStrategy.strategy.pillar',
                'agencyOutcome',
                'performanceIndicator',
                'divisions',
                'actuals',
            ])->orderBy('id')
            : null;

        if ($indicatorsQuery && ! $canManage) {
            $indicatorsQuery->whereHas('divisions', function ($q) use ($user) {
                $q->where('divisions.id', $user->division_id);
            });
        }

        return Inertia::render('PerformanceManagement/Opcr', [
            'period' => $period,
            'periods' => OpcrPeriod::orderByDesc('fiscal_year')->get(['id', 'fiscal_year', 'period_label', 'is_current']),
            'indicators' => $indicatorsQuery ? $indicatorsQuery->get() : [],
            'pillars' => DostPillar::with('strategies.subStrategies')->get(),
            'agencyOutcomes' => AgencyOutcome::query()->topLevel()->excludingAutoGeneratedMarker()->with('children')->get(),
            'performanceIndicators' => PerformanceIndicator::all(['id', 'description']),
            'divisions' => Division::all(),
            'canManage' => $canManage,
        ]);
    }
}
```

- [ ] **Step 4: Add the route group**

In `routes/web.php`, immediately after the existing `ipcr.view` group closes (after line ~1261, right before the `// Performance Management — Committees & Special Assignments` comment), add:

```php
// OPCR — Office Performance Commitment and Review (campus-level).
Route::middleware('permission:opcr.view|opcr.manage')->group(function () {
    Route::get('/opcr', [\App\Http\Controllers\OPCR\OpcrPeriodController::class, 'index'])->name('opcr.index');
});
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodIndexTest"`
Expected: PASS (4 tests). The Inertia component won't exist yet as a Vue file — that's fine, `assertInertia` only inspects the response payload, not the rendered Vue component.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OPCR/OpcrPeriodController.php routes/web.php tests/Feature/OPCR/OpcrPeriodIndexTest.php
git commit -m "feat(opcr): add OPCR index endpoint with division-scoped view"
```

---

### Task 4: `OpcrPeriodController@store` / `@update` — period CRUD + `is_current` toggle

**Files:**
- Modify: `app/Http/Controllers/OPCR/OpcrPeriodController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OPCR/OpcrPeriodCrudTest.php`

**Interfaces:**
- Consumes: `OpcrPeriod` (Task 1).
- Produces: routes `opcr-periods.store` (`POST /opcr-periods`), `opcr-periods.update` (`PUT /opcr-periods/{opcrPeriod}`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodCrudTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_creates_a_new_period_not_marked_current(): void
    {
        $user = $this->manager();

        $response = $this->actingAs($user)->post(route('opcr-periods.store'), [
            'fiscal_year' => 2027,
            'period_label' => 'January - December 2027',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_periods', ['fiscal_year' => 2027, 'is_current' => false]);
    }

    public function test_store_rejects_duplicate_fiscal_year(): void
    {
        $user = $this->manager();
        OpcrPeriod::create(['fiscal_year' => 2027, 'period_label' => 'FY2027']);

        $response = $this->actingAs($user)->post(route('opcr-periods.store'), [
            'fiscal_year' => 2027,
            'period_label' => 'Duplicate',
        ]);

        $response->assertSessionHasErrors('fiscal_year');
    }

    public function test_update_marking_a_period_current_unmarks_all_others(): void
    {
        $user = $this->manager();
        $old = OpcrPeriod::create(['fiscal_year' => 2025, 'period_label' => 'FY2025', 'is_current' => true]);
        $new = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => false]);

        $response = $this->actingAs($user)->put(route('opcr-periods.update', $new), [
            'fiscal_year' => 2026,
            'period_label' => 'FY2026',
            'is_current' => true,
            'campus_director_name' => 'RAMIL A. SANCHEZ',
        ]);

        $response->assertRedirect();
        $this->assertTrue($new->fresh()->is_current);
        $this->assertFalse($old->fresh()->is_current);
        $this->assertEquals('RAMIL A. SANCHEZ', $new->fresh()->campus_director_name);
    }

    public function test_view_only_user_cannot_store_a_period(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->post(route('opcr-periods.store'), [
            'fiscal_year' => 2027,
            'period_label' => 'FY2027',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodCrudTest"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Add `store`/`update` to the controller**

Add to `app/Http/Controllers/OPCR/OpcrPeriodController.php` (add `use Illuminate\Support\Facades\DB;` and `use Illuminate\Validation\Rule;` to the top):

```php
    public function store(Request $request)
    {
        $data = $request->validate([
            'fiscal_year' => 'required|integer|min:2000|max:2100|unique:opcr_periods,fiscal_year',
            'period_label' => 'required|string|max:255',
        ]);

        OpcrPeriod::create($data);

        return back()->with('success', 'OPCR period created.');
    }

    public function update(Request $request, OpcrPeriod $opcrPeriod)
    {
        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('opcr_periods', 'fiscal_year')->ignore($opcrPeriod->id)],
            'period_label' => 'required|string|max:255',
            'is_current' => 'boolean',
            'campus_director_name' => 'nullable|string|max:255',
            'oic_campus_director_name' => 'nullable|string|max:255',
            'executive_director_name' => 'nullable|string|max:255',
            'commitment_statement' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $opcrPeriod) {
            if (! empty($data['is_current'])) {
                OpcrPeriod::where('id', '!=', $opcrPeriod->id)->update(['is_current' => false]);
            }
            $opcrPeriod->update($data);
        });

        return back()->with('success', 'OPCR period updated.');
    }
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, inside the `opcr.view|opcr.manage` group added in Task 3, add (mutations gated tighter, per Global Constraints):

```php
    Route::middleware('permission:opcr.manage')->group(function () {
        Route::post('/opcr-periods', [\App\Http\Controllers\OPCR\OpcrPeriodController::class, 'store'])->name('opcr-periods.store');
        Route::put('/opcr-periods/{opcrPeriod}', [\App\Http\Controllers\OPCR\OpcrPeriodController::class, 'update'])->name('opcr-periods.update');
    });
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodCrudTest"`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OPCR/OpcrPeriodController.php routes/web.php tests/Feature/OPCR/OpcrPeriodCrudTest.php
git commit -m "feat(opcr): add period create/update with is_current toggle"
```

---

### Task 5: `OpcrPeriodController@cloneFrom` — FY clone-forward

**Files:**
- Modify: `app/Http/Controllers/OPCR/OpcrPeriodController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OPCR/OpcrPeriodCloneTest.php`

**Interfaces:**
- Consumes: `OpcrIndicator`, `OpcrPeriod` (Task 1).
- Produces: route `opcr-periods.clone` (`POST /opcr-periods/{opcrPeriod}/clone`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodCloneTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_clone_copies_tagging_target_budget_and_divisions_but_resets_actuals_and_rating(): void
    {
        $user = $this->manager();
        $source = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $target = OpcrPeriod::create(['fiscal_year' => 2027, 'period_label' => 'FY2027']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $source_indicator = OpcrIndicator::create([
            'opcr_period_id' => $source->id,
            'description' => 'Cohort survival rate',
            'target' => '0.9',
            'budget' => 5000,
            'rating_quality' => 5,
            'rating_average' => 5,
        ]);
        $source_indicator->divisions()->sync([$division->id]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $source_indicator->id, 'quarter' => 1, 'value' => '0.5']);

        $response = $this->actingAs($user)->post(route('opcr-periods.clone', $target), [
            'source_period_id' => $source->id,
        ]);

        $response->assertRedirect();
        $cloned = $target->fresh()->indicators()->first();
        $this->assertNotNull($cloned);
        $this->assertEquals('Cohort survival rate', $cloned->description);
        $this->assertEquals('0.9', $cloned->target);
        $this->assertEquals(5000, $cloned->budget);
        $this->assertTrue($cloned->divisions->contains($division));
        $this->assertNull($cloned->rating_quality);
        $this->assertCount(0, $cloned->actuals);
    }

    public function test_clone_is_rejected_when_target_period_already_has_indicators(): void
    {
        $user = $this->manager();
        $source = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $target = OpcrPeriod::create(['fiscal_year' => 2027, 'period_label' => 'FY2027']);
        OpcrIndicator::create(['opcr_period_id' => $target->id, 'description' => 'Already here']);

        $response = $this->actingAs($user)->post(route('opcr-periods.clone', $target), [
            'source_period_id' => $source->id,
        ]);

        $response->assertSessionHasErrors('source_period_id');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodCloneTest"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add `cloneFrom` to the controller**

Add to `app/Http/Controllers/OPCR/OpcrPeriodController.php` (add `use App\Models\OPCR\OpcrIndicator;` and `use Illuminate\Validation\ValidationException;` to the top):

```php
    public function cloneFrom(Request $request, OpcrPeriod $opcrPeriod)
    {
        $data = $request->validate([
            'source_period_id' => 'required|exists:opcr_periods,id|different:opcr_period',
        ]);

        if ($opcrPeriod->indicators()->exists()) {
            throw ValidationException::withMessages([
                'source_period_id' => 'This period already has indicators. Cloning is only allowed into an empty period.',
            ]);
        }

        $source = OpcrPeriod::findOrFail($data['source_period_id']);

        DB::transaction(function () use ($source, $opcrPeriod) {
            $indicators = $source->indicators()->with('divisions')->get();
            foreach ($indicators as $indicator) {
                $clone = $indicator->replicate([
                    'rating_quality', 'rating_efficiency', 'rating_timeliness', 'rating_average',
                ]);
                $clone->opcr_period_id = $opcrPeriod->id;
                $clone->save();
                $clone->divisions()->sync($indicator->divisions->pluck('id'));
            }
        });

        return back()->with('success', "Cloned from \"{$source->period_label}\".");
    }
```

Note: `replicate([...])` nulls the listed attributes on the clone (mPDF/Eloquent's `replicate` sets excluded attributes to `null`, it does not merely omit them from the insert) — confirm this against `IPCRRatingPeriodController::copyFramework`'s identical use of `replicate(['fiscal_year'])`.

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `permission:opcr.manage` group added in Task 4:

```php
        Route::post('/opcr-periods/{opcrPeriod}/clone', [\App\Http\Controllers\OPCR\OpcrPeriodController::class, 'cloneFrom'])->name('opcr-periods.clone');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodCloneTest"`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OPCR/OpcrPeriodController.php routes/web.php tests/Feature/OPCR/OpcrPeriodCloneTest.php
git commit -m "feat(opcr): add FY clone-forward for OPCR periods"
```

---

### Task 6: `OpcrIndicatorController@store` / `@update` / `@destroy`

**Files:**
- Create: `app/Http/Controllers/OPCR/OpcrIndicatorController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OPCR/OpcrIndicatorCrudTest.php`

**Interfaces:**
- Consumes: `OpcrIndicator` (Task 1).
- Produces: routes `opcr-indicators.store` (`POST /opcr-indicators`), `opcr-indicators.update` (`PUT /opcr-indicators/{opcrIndicator}`), `opcr-indicators.destroy` (`DELETE /opcr-indicators/{opcrIndicator}`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorCrudTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_creates_an_indicator_with_tagging_and_divisions(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'opcr_period_id' => $period->id,
            'dost_sub_strategy_id' => $subStrategy->id,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Percentage of graduates pursuing STEM',
            'target' => '0.9',
            'budget' => 15000,
            'remarks' => 'Notes',
            'division_ids' => [$division->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_indicators', [
            'opcr_period_id' => $period->id,
            'dost_sub_strategy_id' => $subStrategy->id,
            'description' => 'Percentage of graduates pursuing STEM',
        ]);
        $indicator = OpcrIndicator::firstWhere('description', 'Percentage of graduates pursuing STEM');
        $this->assertTrue($indicator->divisions->contains($division));
    }

    public function test_store_allows_no_tagging_at_all(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'opcr_period_id' => $period->id,
            'description' => 'Untagged indicator',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_indicators', ['description' => 'Untagged indicator']);
    }

    public function test_store_rejects_missing_description(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'opcr_period_id' => $period->id,
        ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_update_resyncs_divisions(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $divisionA = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $divisionB = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);
        $indicator->divisions()->sync([$divisionA->id]);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'description' => 'Indicator updated',
            'division_ids' => [$divisionB->id],
        ]);

        $response->assertRedirect();
        $fresh = $indicator->fresh();
        $this->assertEquals('Indicator updated', $fresh->description);
        $this->assertFalse($fresh->divisions->contains($divisionA));
        $this->assertTrue($fresh->divisions->contains($divisionB));
    }

    public function test_destroy_deletes_the_indicator(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->delete(route('opcr-indicators.destroy', $indicator));

        $response->assertRedirect();
        $this->assertDatabaseMissing('opcr_indicators', ['id' => $indicator->id]);
    }

    public function test_view_only_user_cannot_store(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'opcr_period_id' => $period->id,
            'description' => 'Indicator',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrIndicatorCrudTest"`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\OPCR;

use App\Http\Controllers\Controller;
use App\Models\OPCR\OpcrIndicator;
use Illuminate\Http\Request;

class OpcrIndicatorController extends Controller
{
    private function rules(): array
    {
        return [
            'dost_sub_strategy_id' => 'nullable|exists:dost_sub_strategies,id',
            'agency_outcome_id' => 'nullable|exists:agency_org_outcomes,id',
            'performance_indicator_id' => 'nullable|exists:performance_indicators,id',
            'description' => 'required|string',
            'target' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'division_ids' => 'array',
            'division_ids.*' => 'exists:divisions,id',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge($this->rules(), [
            'opcr_period_id' => 'required|exists:opcr_periods,id',
        ]));

        $indicator = OpcrIndicator::create($data);
        $indicator->divisions()->sync($data['division_ids'] ?? []);

        return back()->with('success', 'Indicator created.');
    }

    public function update(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate($this->rules());

        $opcrIndicator->update($data);
        $opcrIndicator->divisions()->sync($data['division_ids'] ?? []);

        return back()->with('success', 'Indicator updated.');
    }

    public function destroy(OpcrIndicator $opcrIndicator)
    {
        $opcrIndicator->delete();

        return back()->with('success', 'Indicator deleted.');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, inside the `permission:opcr.manage` group:

```php
        Route::post('/opcr-indicators', [\App\Http\Controllers\OPCR\OpcrIndicatorController::class, 'store'])->name('opcr-indicators.store');
        Route::put('/opcr-indicators/{opcrIndicator}', [\App\Http\Controllers\OPCR\OpcrIndicatorController::class, 'update'])->name('opcr-indicators.update');
        Route::delete('/opcr-indicators/{opcrIndicator}', [\App\Http\Controllers\OPCR\OpcrIndicatorController::class, 'destroy'])->name('opcr-indicators.destroy');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrIndicatorCrudTest"`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OPCR/OpcrIndicatorController.php routes/web.php tests/Feature/OPCR/OpcrIndicatorCrudTest.php
git commit -m "feat(opcr): add OPCR indicator CRUD"
```

---

### Task 7: `OpcrIndicatorController@updateActual` — quarterly actuals

**Files:**
- Modify: `app/Http/Controllers/OPCR/OpcrIndicatorController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OPCR/OpcrIndicatorActualTest.php`

**Interfaces:**
- Consumes: `OpcrIndicatorActual` (Task 1).
- Produces: route `opcr-indicators.actual` (`PUT /opcr-indicators/{opcrIndicator}/actual`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorActualTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_setting_q1_then_q2_creates_two_rows(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 1, 'value' => '0.5'])->assertRedirect();
        $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 2, 'value' => '0.7'])->assertRedirect();

        $this->assertCount(2, $indicator->fresh()->actuals);
    }

    public function test_resubmitting_the_same_quarter_updates_in_place(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.5']);

        $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 1, 'value' => '0.6'])->assertRedirect();

        $this->assertCount(1, $indicator->fresh()->actuals);
        $this->assertEquals('0.6', $indicator->actuals()->where('quarter', 1)->first()->value);
    }

    public function test_quarter_must_be_between_1_and_4(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 5, 'value' => '0.6']);

        $response->assertSessionHasErrors('quarter');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrIndicatorActualTest"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add `updateActual` to the controller**

Add to `app/Http/Controllers/OPCR/OpcrIndicatorController.php`:

```php
    public function updateActual(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate([
            'quarter' => 'required|integer|between:1,4',
            'value' => 'nullable|string|max:255',
        ]);

        $opcrIndicator->actuals()->updateOrCreate(
            ['quarter' => $data['quarter']],
            ['value' => $data['value'] ?? null]
        );

        return back()->with('success', "Q{$data['quarter']} actual recorded.");
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `permission:opcr.manage` group:

```php
        Route::put('/opcr-indicators/{opcrIndicator}/actual', [\App\Http\Controllers\OPCR\OpcrIndicatorController::class, 'updateActual'])->name('opcr-indicators.actual');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrIndicatorActualTest"`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OPCR/OpcrIndicatorController.php routes/web.php tests/Feature/OPCR/OpcrIndicatorActualTest.php
git commit -m "feat(opcr): add quarterly actual recording"
```

---

### Task 8: `OpcrIndicatorController@updateRating`

**Files:**
- Modify: `app/Http/Controllers/OPCR/OpcrIndicatorController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OPCR/OpcrIndicatorRatingTest.php`

**Interfaces:**
- Produces: route `opcr-indicators.rating` (`PUT /opcr-indicators/{opcrIndicator}/rating`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorRatingTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_rating_saves_exactly_what_is_submitted_without_recomputing_average(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->put(route('opcr-indicators.rating', $indicator), [
            'rating_quality' => 5,
            'rating_efficiency' => 4,
            'rating_timeliness' => 3,
            'rating_average' => 4.5, // deliberately NOT (5+4+3)/3, to prove it's never recomputed
        ]);

        $response->assertRedirect();
        $fresh = $indicator->fresh();
        $this->assertEquals(5, $fresh->rating_quality);
        $this->assertEquals(4, $fresh->rating_efficiency);
        $this->assertEquals(3, $fresh->rating_timeliness);
        $this->assertEquals(4.5, $fresh->rating_average);
    }

    public function test_rating_values_must_be_between_1_and_5(): void
    {
        $user = $this->manager();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        $indicator = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->put(route('opcr-indicators.rating', $indicator), [
            'rating_quality' => 6,
        ]);

        $response->assertSessionHasErrors('rating_quality');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrIndicatorRatingTest"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add `updateRating` to the controller**

Add to `app/Http/Controllers/OPCR/OpcrIndicatorController.php`:

```php
    public function updateRating(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate([
            'rating_quality' => 'nullable|numeric|between:1,5',
            'rating_efficiency' => 'nullable|numeric|between:1,5',
            'rating_timeliness' => 'nullable|numeric|between:1,5',
            'rating_average' => 'nullable|numeric|between:1,5',
        ]);

        $opcrIndicator->update($data);

        return back()->with('success', 'Rating recorded.');
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `permission:opcr.manage` group:

```php
        Route::put('/opcr-indicators/{opcrIndicator}/rating', [\App\Http\Controllers\OPCR\OpcrIndicatorController::class, 'updateRating'])->name('opcr-indicators.rating');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrIndicatorRatingTest"`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OPCR/OpcrIndicatorController.php routes/web.php tests/Feature/OPCR/OpcrIndicatorRatingTest.php
git commit -m "feat(opcr): add rating recording endpoint"
```

---

### Task 9: Delete-restrict guards on `DostSubStrategy` and `AgencyOutcome`

**Files:**
- Modify: `app/Models/DostSubStrategy.php`
- Modify: `app/Models/AgencyOutcome.php`
- Modify: `app/Http/Controllers/DostSubStrategyController.php`
- Modify: `app/Http/Controllers/AgencyOutcomeController.php`
- Test: `tests/Feature/OPCR/OpcrTaggingDeleteGuardTest.php`

**Interfaces:**
- Produces: `DostSubStrategy::opcrIndicators()`, `AgencyOutcome::opcrIndicators()` (both hasMany `OpcrIndicator`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrTaggingDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_cannot_delete_a_sub_strategy_still_tagged_on_an_opcr_indicator(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        OpcrIndicator::create(['opcr_period_id' => $period->id, 'dost_sub_strategy_id' => $subStrategy->id, 'description' => 'Indicator']);

        $response = $this->actingAs($admin)->delete(route('dost-sub-strategies.destroy', $subStrategy));

        $response->assertSessionHasErrors();
        $this->assertModelExists($subStrategy);
    }

    public function test_cannot_delete_an_agency_outcome_still_tagged_on_an_opcr_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        OpcrIndicator::create(['opcr_period_id' => $period->id, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $outcome->id));

        $response->assertSessionHasErrors();
        $this->assertModelExists($outcome);
    }

    public function test_sub_strategy_with_no_opcr_indicators_still_deletes_normally(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $response = $this->actingAs($admin)->delete(route('dost-sub-strategies.destroy', $subStrategy));

        $response->assertRedirect();
        $this->assertModelMissing($subStrategy);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrTaggingDeleteGuardTest"`
Expected: FAIL — `DostSubStrategy::destroy`/`AgencyOutcomeController::destroy` don't guard against OPCR tagging yet.

- [ ] **Step 3: Add the relation to `DostSubStrategy`**

In `app/Models/DostSubStrategy.php`, add:

```php
    public function opcrIndicators()
    {
        return $this->hasMany(\App\Models\OPCR\OpcrIndicator::class, 'dost_sub_strategy_id');
    }
```

- [ ] **Step 4: Guard `DostSubStrategyController::destroy`**

In `app/Http/Controllers/DostSubStrategyController.php`, replace:

```php
    public function destroy(DostSubStrategy $dostSubStrategy)
    {
        $dostSubStrategy->delete();

        return back()->with('success', 'Sub-Strategy deleted.');
    }
```

with:

```php
    public function destroy(DostSubStrategy $dostSubStrategy)
    {
        if ($dostSubStrategy->opcrIndicators()->exists()) {
            return back()->withErrors(['dostSubStrategy' => 'This Sub-Strategy is still tagged on one or more OPCR indicators.']);
        }

        $dostSubStrategy->delete();

        return back()->with('success', 'Sub-Strategy deleted.');
    }
```

- [ ] **Step 5: Add the relation to `AgencyOutcome`**

In `app/Models/AgencyOutcome.php`, add (near the existing `performanceIndicators()` method):

```php
    public function opcrIndicators()
    {
        return $this->hasMany(\App\Models\OPCR\OpcrIndicator::class, 'agency_outcome_id');
    }
```

- [ ] **Step 6: Guard `AgencyOutcomeController::destroy`**

In `app/Http/Controllers/AgencyOutcomeController.php`, in `destroy()`, add a third check alongside the two existing ones (children / performanceIndicators):

```php
        if ($agencyOutcome->opcrIndicators()->exists()) {
            return back()->withErrors(['agencyOutcome' => 'This outcome is still tagged on one or more OPCR indicators.']);
        }
```

placed after the existing `performanceIndicators()->exists()` check and before `$agencyOutcome->delete();`.

- [ ] **Step 7: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrTaggingDeleteGuardTest"`
Expected: PASS (3 tests).

- [ ] **Step 8: Run the full existing DOST/AgencyOutcome regression suite to confirm no breakage**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DostStrategicPlanControllerTest --filter=AgencyOutcomeControllerTest --filter=DostStrategicPlanCascadeDeleteTest --filter=AgencyOutcomeDeleteBehaviorTest"`
Expected: PASS, unchanged.

- [ ] **Step 9: Commit**

```bash
git add app/Models/DostSubStrategy.php app/Models/AgencyOutcome.php \
        app/Http/Controllers/DostSubStrategyController.php app/Http/Controllers/AgencyOutcomeController.php \
        tests/Feature/OPCR/OpcrTaggingDeleteGuardTest.php
git commit -m "feat(opcr): guard DOST tagging deletes against in-use OPCR indicators"
```

---

### Task 10: PDF export

**Files:**
- Create: `app/Services/OPCR/OpcrPeriodPdfService.php`
- Create: `resources/views/opcr/pdf.blade.php`
- Modify: `app/Http/Controllers/OPCR/OpcrPeriodController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OPCR/OpcrPeriodPdfTest.php`

**Interfaces:**
- Consumes: `OpcrPeriod` with `indicators.subStrategy.strategy.pillar`, `indicators.agencyOutcome`, `indicators.divisions`, `indicators.actuals` eager-loaded.
- Produces: route `opcr-periods.pdf` (`GET /opcr-periods/{opcrPeriod}/pdf`), `OpcrPeriodPdfService::stream(OpcrPeriod $period): StreamedResponse`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\OPCR;

use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodPdfTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_pdf_renders_for_a_period_with_indicators(): void
    {
        $user = $this->viewer();
        $period = OpcrPeriod::create([
            'fiscal_year' => 2026,
            'period_label' => 'January - December 2026',
            'campus_director_name' => 'RAMIL A. SANCHEZ',
            'executive_director_name' => 'RONNALEE N. ORTEZA',
        ]);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'description' => 'Cohort survival rate',
            'target' => '0.9',
        ]);
        $indicator->divisions()->sync([$division->id]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.8889']);

        $response = $this->actingAs($user)->get(route('opcr-periods.pdf', $period));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_renders_for_an_empty_period(): void
    {
        $user = $this->viewer();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);

        $response = $this->actingAs($user)->get(route('opcr-periods.pdf', $period));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodPdfTest"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Write the blade view**

```blade
{{-- resources/views/opcr/pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        h1 { font-size: 13px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 10px; text-align: center; margin-top: 0; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #888; padding: 3px 4px; vertical-align: top; }
        th { background: #eee; font-size: 8px; text-transform: uppercase; }
        .commitment { margin-top: 10px; }
        .signatures { margin-top: 30px; width: 100%; }
        .signatures td { border: none; text-align: center; padding-top: 20px; }
        .sig-name { border-top: 1px solid #000; display: inline-block; padding-top: 2px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>OFFICE PERFORMANCE COMMITMENT AND REVIEW (OPCR)</h1>
    <h2>FY {{ $period->fiscal_year }}</h2>

    <p class="commitment">
        {{ $period->commitment_statement
            ?: "I, {$period->campus_director_name}, Campus Director of the PSHS-Caraga Region Campus, commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for the period {$period->period_label}." }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Pillar / Outcome</th>
                <th>Strategy</th>
                <th>Sub-Strategy</th>
                <th>PSHS Program</th>
                <th>Performance Indicator</th>
                <th>Target</th>
                <th>Budget</th>
                <th>Division</th>
                <th>Q1</th>
                <th>Q2</th>
                <th>Q3</th>
                <th>Q4</th>
                <th>Q</th>
                <th>E</th>
                <th>T</th>
                <th>A</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($indicators as $indicator)
                <tr>
                    <td>{{ $indicator->subStrategy?->strategy?->pillar?->name ?? '—' }}</td>
                    <td>{{ $indicator->subStrategy?->strategy?->name ?? '—' }}</td>
                    <td>{{ $indicator->subStrategy?->description ?? '—' }}</td>
                    <td>{{ $indicator->agencyOutcome?->outcome ?? '—' }}</td>
                    <td>{{ $indicator->description }}</td>
                    <td>{{ $indicator->target ?? '—' }}</td>
                    <td>{{ $indicator->budget !== null ? number_format($indicator->budget, 2) : '—' }}</td>
                    <td>{{ $indicator->divisions->pluck('acronym')->implode(', ') ?: '—' }}</td>
                    @for ($q = 1; $q <= 4; $q++)
                        <td>{{ $indicator->actuals->firstWhere('quarter', $q)?->value ?? '—' }}</td>
                    @endfor
                    <td>{{ $indicator->rating_quality ?? '—' }}</td>
                    <td>{{ $indicator->rating_efficiency ?? '—' }}</td>
                    <td>{{ $indicator->rating_timeliness ?? '—' }}</td>
                    <td>{{ $indicator->rating_average ?? '—' }}</td>
                    <td>{{ $indicator->remarks ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="16" style="text-align:center;">No indicators tagged yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td style="width:33%;">
                <span class="sig-name">{{ $period->campus_director_name ?? '—' }}</span><br>
                Campus Director
            </td>
            <td style="width:33%;">
                <span class="sig-name">{{ $period->oic_campus_director_name ?? '—' }}</span><br>
                OIC-Campus Director
            </td>
            <td style="width:33%;">
                <span class="sig-name">{{ $period->executive_director_name ?? '—' }}</span><br>
                Executive Director, PSHS System
            </td>
        </tr>
    </table>
</body>
</html>
```

- [ ] **Step 4: Write the PDF service**

```php
<?php

namespace App\Services\OPCR;

use App\Models\OPCR\OpcrPeriod;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpcrPeriodPdfService
{
    public function stream(OpcrPeriod $period): StreamedResponse
    {
        $period->loadMissing([
            'indicators.subStrategy.strategy.pillar',
            'indicators.agencyOutcome',
            'indicators.divisions',
            'indicators.actuals',
        ]);

        $html = view('opcr.pdf', [
            'period' => $period,
            'indicators' => $period->indicators,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('OPCR FY ' . $period->fiscal_year);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'OPCR_FY' . $period->fiscal_year . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfBytes),
        ]);
    }
}
```

- [ ] **Step 5: Add the controller action**

Add to `app/Http/Controllers/OPCR/OpcrPeriodController.php` (add `use App\Services\OPCR\OpcrPeriodPdfService;` to the top):

```php
    public function pdf(OpcrPeriod $opcrPeriod, OpcrPeriodPdfService $service)
    {
        return $service->stream($opcrPeriod);
    }
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, inside the outer `permission:opcr.view|opcr.manage` group (NOT the tighter `opcr.manage` group — viewers can export too, per the spec):

```php
    Route::get('/opcr-periods/{opcrPeriod}/pdf', [\App\Http\Controllers\OPCR\OpcrPeriodController::class, 'pdf'])->name('opcr-periods.pdf');
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=OpcrPeriodPdfTest"`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Services/OPCR/OpcrPeriodPdfService.php resources/views/opcr/pdf.blade.php \
        app/Http/Controllers/OPCR/OpcrPeriodController.php routes/web.php tests/Feature/OPCR/OpcrPeriodPdfTest.php
git commit -m "feat(opcr): add OPCR PDF export"
```

---

### Task 11: Frontend grouping utility (pure JS, TDD via `node --test`)

**Files:**
- Create: `resources/js/Utils/OPCR/opcrGrouping.js`
- Test: `tests/js/opcrGrouping.test.mjs`

**Interfaces:**
- Produces: `groupIndicatorsByHierarchy(indicators)` — returns a nested plain object `{ [pillarName]: { [strategyName]: { [subStrategyName]: [indicator, ...] } } }`; indicators with no `sub_strategy` are grouped under a literal `"Untagged"` key at the top level (not nested under pillar/strategy). Task 13's Vue page consumes this exact function/shape.

- [ ] **Step 1: Write the failing test**

```js
import assert from 'node:assert/strict'
import test from 'node:test'

import { groupIndicatorsByHierarchy } from '../../resources/js/Utils/OPCR/opcrGrouping.js'

function indicator({ id, pillar = null, strategy = null, subStrategy = null }) {
  return {
    id,
    sub_strategy: subStrategy
      ? {
          description: subStrategy,
          strategy: {
            name: strategy,
            pillar: { name: pillar },
          },
        }
      : null,
  }
}

test('groups indicators by pillar -> strategy -> sub-strategy', () => {
  const indicators = [
    indicator({ id: 1, pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 2, pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 3, pillar: 'Pillar 1', strategy: 'Strategy 2', subStrategy: 'Sub B' }),
  ]

  const grouped = groupIndicatorsByHierarchy(indicators)

  assert.deepEqual(Object.keys(grouped), ['Pillar 1'])
  assert.deepEqual(Object.keys(grouped['Pillar 1']).sort(), ['Strategy 1', 'Strategy 2'])
  assert.equal(grouped['Pillar 1']['Strategy 1']['Sub A'].length, 2)
  assert.equal(grouped['Pillar 1']['Strategy 2']['Sub B'].length, 1)
})

test('untagged indicators (no sub_strategy) group under a top-level "Untagged" key', () => {
  const indicators = [indicator({ id: 1 })]

  const grouped = groupIndicatorsByHierarchy(indicators)

  assert.deepEqual(Object.keys(grouped), ['Untagged'])
  assert.equal(grouped['Untagged']['Untagged']['Untagged'].length, 1)
})

test('mixed tagged and untagged indicators both appear', () => {
  const indicators = [
    indicator({ id: 1, pillar: 'Pillar 1', strategy: 'Strategy 1', subStrategy: 'Sub A' }),
    indicator({ id: 2 }),
  ]

  const grouped = groupIndicatorsByHierarchy(indicators)

  assert.deepEqual(Object.keys(grouped).sort(), ['Pillar 1', 'Untagged'])
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js`
Expected: FAIL — `resources/js/Utils/OPCR/opcrGrouping.js` doesn't exist.

- [ ] **Step 3: Write the utility**

```js
// resources/js/Utils/OPCR/opcrGrouping.js

const UNTAGGED = 'Untagged'

/**
 * Groups a flat OPCR indicator list into { pillar: { strategy: { subStrategy: [indicator, ...] } } }.
 * Indicators without a sub_strategy land under the single "Untagged" bucket at every level.
 */
export function groupIndicatorsByHierarchy(indicators) {
  const grouped = {}

  for (const indicator of indicators) {
    const subStrategy = indicator.sub_strategy
    const pillarName = subStrategy?.strategy?.pillar?.name ?? UNTAGGED
    const strategyName = subStrategy?.strategy?.name ?? UNTAGGED
    const subStrategyName = subStrategy?.description ?? UNTAGGED

    grouped[pillarName] ??= {}
    grouped[pillarName][strategyName] ??= {}
    grouped[pillarName][strategyName][subStrategyName] ??= []
    grouped[pillarName][strategyName][subStrategyName].push(indicator)
  }

  return grouped
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npm run test:js`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Utils/OPCR/opcrGrouping.js tests/js/opcrGrouping.test.mjs
git commit -m "feat(opcr): add pure JS indicator grouping utility"
```

---

### Task 12: `useOpcr` composable

**Files:**
- Create: `resources/js/Composables/useOpcr.js`

**Interfaces:**
- Consumes: `groupIndicatorsByHierarchy` (Task 11).
- Produces: `useOpcr(props)` returning `{ groupedIndicators, showIndicatorModal, indicatorModalMode, selectedIndicator, indicatorForm, openIndicatorModal, closeIndicatorModal, submitIndicator, deleteIndicator, updateActual, updateRating, newPillarName, addPillar, newStrategy, addStrategy, newSubStrategy, addSubStrategy, newProgram, addProgram, showPeriodModal, periodForm, openPeriodModal, closePeriodModal, submitPeriod, cloneForm, showCloneModal, openCloneModal, closeCloneModal, submitClone }` — all state exposed as refs/computed (never returning a bare reactive object — matches the project's known composable gotcha). The `add*` functions POST directly to the existing `dost-pillars.store`/`dost-strategies.store`/`dost-sub-strategies.store`/`outcome.store` routes (unchanged from the DOST Strategic Plan module) — this is the "inline tagging" decision from the spec, not a new backend endpoint. Task 13's Vue page consumes this exact return shape.

This task has no automated test (it's Inertia/`router`-coupled UI wiring, not pure logic — same convention as `usePerformanceIndicators.js`, which has no test file either). Verification is via Task 13's manual browser click-through.

- [ ] **Step 1: Write the composable**

```js
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"
import { groupIndicatorsByHierarchy } from "@/Utils/OPCR/opcrGrouping.js"

export function useOpcr(props) {
  const groupedIndicators = computed(() => groupIndicatorsByHierarchy(props.indicators || []))

  // ── Indicator modal ──────────────────────────────────────────────────
  const showIndicatorModal = ref(false)
  const indicatorModalMode = ref("create")
  const selectedIndicator = ref(null)

  const blankIndicatorForm = () => ({
    id: null,
    dost_sub_strategy_id: "",
    agency_outcome_id: "",
    performance_indicator_id: "",
    description: "",
    target: "",
    budget: "",
    remarks: "",
    divisions: [],
  })

  const indicatorForm = ref(blankIndicatorForm())

  const openIndicatorModal = (mode, indicator = null) => {
    indicatorModalMode.value = mode
    showIndicatorModal.value = true
    selectedIndicator.value = indicator

    if ((mode === "edit" || mode === "view") && indicator) {
      indicatorForm.value = {
        id: indicator.id,
        dost_sub_strategy_id: indicator.dost_sub_strategy_id ?? "",
        agency_outcome_id: indicator.agency_outcome_id ?? "",
        performance_indicator_id: indicator.performance_indicator_id ?? "",
        description: indicator.description ?? "",
        target: indicator.target ?? "",
        budget: indicator.budget ?? "",
        remarks: indicator.remarks ?? "",
        divisions: indicator.divisions ? [...indicator.divisions] : [],
      }
    } else {
      indicatorForm.value = blankIndicatorForm()
    }
  }

  const closeIndicatorModal = () => {
    showIndicatorModal.value = false
    selectedIndicator.value = null
  }

  const submitIndicator = () => {
    const payload = {
      ...indicatorForm.value,
      division_ids: indicatorForm.value.divisions.map((d) => d.id),
    }

    const onDone = (label) => ({
      onSuccess: async () => {
        closeIndicatorModal()
        await Swal.fire("Success", label, "success")
        router.reload({ only: ["indicators"] })
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })

    if (indicatorModalMode.value === "create") {
      router.post(route("opcr-indicators.store"), payload, onDone("Indicator created."))
    } else {
      router.put(route("opcr-indicators.update", indicatorForm.value.id), payload, onDone("Indicator updated."))
    }
  }

  const deleteIndicator = async (indicator) => {
    const result = await Swal.fire({
      title: `Delete indicator "${indicator?.description ?? ""}"?`,
      text: "This action cannot be undone!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    })

    if (result.isConfirmed) {
      router.delete(route("opcr-indicators.destroy", indicator.id), {
        onSuccess: async () => {
          await Swal.fire("Deleted", "Indicator deleted.", "success")
          router.reload({ only: ["indicators"] })
        },
      })
    }
  }

  const updateActual = (indicator, quarter, value) => {
    router.put(route("opcr-indicators.actual", indicator.id), { quarter, value }, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["indicators"] }),
    })
  }

  const updateRating = (indicator, ratings) => {
    router.put(route("opcr-indicators.rating", indicator.id), ratings, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["indicators"] }),
    })
  }

  // ── Inline DOST tag creation (no schema of its own — reuses the
  // existing DostPillar/DostStrategy/DostSubStrategy/AgencyOutcome
  // store routes so the indicator form never has to leave the page) ───
  const newPillarName = ref("")
  const addPillar = () => {
    if (!newPillarName.value.trim()) return
    router.post(route("dost-pillars.store"), { name: newPillarName.value }, {
      preserveScroll: true,
      onSuccess: () => {
        newPillarName.value = ""
        router.reload({ only: ["pillars"] })
      },
    })
  }

  const newStrategy = ref({ dost_pillar_id: "", name: "" })
  const addStrategy = () => {
    if (!newStrategy.value.dost_pillar_id || !newStrategy.value.name.trim()) return
    router.post(route("dost-strategies.store"), newStrategy.value, {
      preserveScroll: true,
      onSuccess: () => {
        newStrategy.value = { dost_pillar_id: "", name: "" }
        router.reload({ only: ["pillars"] })
      },
    })
  }

  const newSubStrategy = ref({ dost_strategy_id: "", description: "" })
  const addSubStrategy = () => {
    if (!newSubStrategy.value.dost_strategy_id || !newSubStrategy.value.description.trim()) return
    router.post(route("dost-sub-strategies.store"), newSubStrategy.value, {
      preserveScroll: true,
      onSuccess: () => {
        newSubStrategy.value = { dost_strategy_id: "", description: "" }
        router.reload({ only: ["pillars"] })
      },
    })
  }

  const newProgram = ref({ outcome: "", function_type: "" })
  const addProgram = () => {
    if (!newProgram.value.outcome.trim() || !newProgram.value.function_type.trim()) return
    router.post(route("outcome.store"), newProgram.value, {
      preserveScroll: true,
      onSuccess: () => {
        newProgram.value = { outcome: "", function_type: "" }
        router.reload({ only: ["agencyOutcomes"] })
      },
    })
  }

  // ── Period modal (metadata + is_current) ─────────────────────────────
  const showPeriodModal = ref(false)
  const periodForm = ref({
    id: null,
    fiscal_year: "",
    period_label: "",
    is_current: false,
    campus_director_name: "",
    oic_campus_director_name: "",
    executive_director_name: "",
    commitment_statement: "",
  })

  const openPeriodModal = (period = null) => {
    showPeriodModal.value = true
    periodForm.value = period
      ? { ...period }
      : { id: null, fiscal_year: "", period_label: "", is_current: false, campus_director_name: "", oic_campus_director_name: "", executive_director_name: "", commitment_statement: "" }
  }

  const closePeriodModal = () => {
    showPeriodModal.value = false
  }

  const submitPeriod = () => {
    const onDone = {
      onSuccess: async () => {
        closePeriodModal()
        await Swal.fire("Success", "Period saved.", "success")
        window.location.reload()
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    }

    if (periodForm.value.id) {
      router.put(route("opcr-periods.update", periodForm.value.id), periodForm.value, onDone)
    } else {
      router.post(route("opcr-periods.store"), periodForm.value, onDone)
    }
  }

  // ── Clone modal ───────────────────────────────────────────────────────
  const showCloneModal = ref(false)
  const cloneForm = ref({ source_period_id: "" })

  const openCloneModal = () => {
    showCloneModal.value = true
  }

  const closeCloneModal = () => {
    showCloneModal.value = false
  }

  const submitClone = (targetPeriodId) => {
    router.post(route("opcr-periods.clone", targetPeriodId), cloneForm.value, {
      onSuccess: async () => {
        closeCloneModal()
        await Swal.fire("Success", "Cloned successfully.", "success")
        window.location.reload()
      },
      onError: async (errors) => {
        await Swal.fire("Error", Object.values(errors).flat().join(", "), "error")
      },
    })
  }

  return {
    groupedIndicators,
    showIndicatorModal,
    indicatorModalMode,
    selectedIndicator,
    indicatorForm,
    openIndicatorModal,
    closeIndicatorModal,
    submitIndicator,
    deleteIndicator,
    updateActual,
    updateRating,
    newPillarName,
    addPillar,
    newStrategy,
    addStrategy,
    newSubStrategy,
    addSubStrategy,
    newProgram,
    addProgram,
    showPeriodModal,
    periodForm,
    openPeriodModal,
    closePeriodModal,
    submitPeriod,
    showCloneModal,
    cloneForm,
    openCloneModal,
    closeCloneModal,
    submitClone,
  }
}
```

- [ ] **Step 2: Verify the module has no syntax errors**

Run: `npx vite build --mode development 2>&1 | tail -30` (or simply proceed to Task 13, which imports this file — a syntax error there will surface as a build failure in Task 13's Step 2).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Composables/useOpcr.js
git commit -m "feat(opcr): add useOpcr composable"
```

---

### Task 13: `Opcr.vue` page + nav entry

**Files:**
- Create: `resources/js/Pages/PerformanceManagement/Opcr.vue`
- Modify: `resources/js/Layouts/navigation.js` (add nav entry after the existing "DOST Strategic Plan" entry, ~line 683)

**Interfaces:**
- Consumes: props `period`, `periods`, `indicators`, `pillars`, `agencyOutcomes`, `performanceIndicators`, `divisions`, `canManage` (Task 3); `useOpcr` (Task 12); reuses `DostPillarController`/`DostStrategyController`/`DostSubStrategyController`'s existing `store` routes (`dost-pillars.store`, `dost-strategies.store`, `dost-sub-strategies.store`) for inline tag creation.

No automated test for this task — Vue page verification is manual browser click-through (Task 15), matching this project's established convention for Inertia page components (see `DostStrategicPlanController`'s spec, which relied on PHPUnit assertInertia + manual click-through rather than a Vue component test).

- [ ] **Step 1: Write the page**

```vue
<script setup>
import { ref, computed } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppTable from "@/Components/AppTable.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon, DocumentArrowDownIcon } from "@heroicons/vue/24/outline"
import { useOpcr } from "@/Composables/useOpcr.js"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

const props = defineProps({
  period: Object,
  periods: { type: Array, default: () => [] },
  indicators: { type: Array, default: () => [] },
  pillars: { type: Array, default: () => [] },
  agencyOutcomes: { type: Array, default: () => [] },
  performanceIndicators: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
})

const {
  groupedIndicators,
  showIndicatorModal,
  indicatorModalMode,
  indicatorForm,
  openIndicatorModal,
  closeIndicatorModal,
  submitIndicator,
  deleteIndicator,
  updateActual,
  updateRating,
  newPillarName,
  addPillar,
  newStrategy,
  addStrategy,
  newSubStrategy,
  addSubStrategy,
  newProgram,
  addProgram,
  showPeriodModal,
  periodForm,
  openPeriodModal,
  closePeriodModal,
  submitPeriod,
  showCloneModal,
  cloneForm,
  openCloneModal,
  closeCloneModal,
  submitClone,
} = useOpcr(props)

const showAddPillar = ref(false)
const showAddStrategy = ref(false)
const showAddSubStrategy = ref(false)
const showAddProgram = ref(false)

const allStrategies = computed(() => props.pillars.flatMap((p) => p.strategies ?? []))
</script>

<template>
  <Head title="OPCR" />
  <AdminLayout title="Office Performance Commitment and Review (OPCR)">
    <div class="space-y-5">
      <AppPageHeader
        title="OPCR"
        :subtitle="period ? `FY ${period.fiscal_year} — ${period.period_label}` : 'No current OPCR period set up yet.'"
      >
        <template #actions>
          <AppButton v-if="period" variant="secondary" as="a" :href="route('opcr-periods.pdf', period.id)" target="_blank">
            <DocumentArrowDownIcon class="w-4 h-4" /> Export PDF
          </AppButton>
          <template v-if="canManage">
            <AppButton variant="secondary" @click="openCloneModal">Clone from FY —</AppButton>
            <AppButton variant="secondary" @click="openPeriodModal(period)">{{ period ? 'Edit Period' : 'New FY' }}</AppButton>
            <AppButton @click="openIndicatorModal('create')" :disabled="!period">
              <PlusIcon class="w-4 h-4" /> New Indicator
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <AppTable :is-empty="indicators.length === 0" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Pillar / Strategy / Sub-Strategy</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Indicator</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Division</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q1</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q2</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q3</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q4</th>
            <th class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating (Q/E/T/A)</th>
            <th v-if="canManage" class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Action</th>
          </tr>
        </template>

        <template v-for="(strategies, pillarName) in groupedIndicators" :key="pillarName">
          <template v-for="(subStrategies, strategyName) in strategies" :key="strategyName">
            <template v-for="(rows, subStrategyName) in subStrategies" :key="subStrategyName">
              <tr v-for="(indicator, idx) in rows" :key="indicator.id" class="hover:bg-indigo-50/40">
                <td class="px-3 py-2 text-xs text-slate-600 align-top">
                  <template v-if="idx === 0">
                    <div class="font-medium text-slate-700">{{ pillarName }}</div>
                    <div>{{ strategyName }}</div>
                    <div class="text-slate-400">{{ subStrategyName }}</div>
                  </template>
                </td>
                <td class="px-3 py-2 text-sm text-slate-700 align-top">{{ indicator.description }}</td>
                <td class="px-3 py-2 text-sm text-slate-700 align-top">{{ indicator.target ?? '—' }}</td>
                <td class="px-3 py-2 text-sm text-slate-700 align-top">
                  {{ indicator.divisions?.map(d => d.acronym ?? d.division_name).join(', ') || '—' }}
                </td>
                <td v-for="q in [1, 2, 3, 4]" :key="q" class="px-3 py-2 align-top">
                  <input
                    v-if="canManage"
                    :value="indicator.actuals?.find(a => a.quarter === q)?.value ?? ''"
                    class="w-16 rounded border border-slate-200 px-1 py-0.5 text-xs"
                    @change="updateActual(indicator, q, $event.target.value)"
                  />
                  <span v-else class="text-sm text-slate-700">{{ indicator.actuals?.find(a => a.quarter === q)?.value ?? '—' }}</span>
                </td>
                <td class="px-3 py-2 align-top">
                  <div v-if="canManage" class="flex gap-1">
                    <input
                      v-for="field in ['rating_quality', 'rating_efficiency', 'rating_timeliness', 'rating_average']"
                      :key="field"
                      :value="indicator[field] ?? ''"
                      type="number" min="1" max="5" step="0.01"
                      class="w-12 rounded border border-slate-200 px-1 py-0.5 text-xs"
                      @change="updateRating(indicator, { ...['rating_quality','rating_efficiency','rating_timeliness','rating_average'].reduce((acc, f) => ({ ...acc, [f]: indicator[f] }), {}), [field]: $event.target.value || null })"
                    />
                  </div>
                  <span v-else class="text-sm text-slate-700">
                    {{ [indicator.rating_quality, indicator.rating_efficiency, indicator.rating_timeliness, indicator.rating_average].map(v => v ?? '—').join(' / ') }}
                  </span>
                </td>
                <td v-if="canManage" class="px-3 py-2 text-center align-top">
                  <div class="flex items-center justify-center gap-1">
                    <AppIconButton label="Edit" @click="openIndicatorModal('edit', indicator)">
                      <PencilSquareIcon class="w-4 h-4" />
                    </AppIconButton>
                    <AppIconButton label="Delete" variant="danger" @click="deleteIndicator(indicator)">
                      <TrashIcon class="w-4 h-4" />
                    </AppIconButton>
                  </div>
                </td>
              </tr>
            </template>
          </template>
        </template>

        <template #empty>
          <EmptyState title="No OPCR indicators yet" subtitle="Set up a current FY period, then add indicators." />
        </template>
      </AppTable>

      <!-- Indicator create/edit modal -->
      <AppModal :show="showIndicatorModal" :title="indicatorModalMode === 'create' ? 'New Indicator' : 'Edit Indicator'" size="lg" @close="closeIndicatorModal">
        <form id="opcr-indicator-form" @submit.prevent="submitIndicator" class="space-y-4">
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">Pillar</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddPillar = !showAddPillar">+ Add new</button>
            </div>
            <div v-if="showAddPillar" class="flex gap-2 mb-2">
              <AppInput v-model="newPillarName" type="text" placeholder="New Pillar name" class="flex-1" />
              <AppButton type="button" variant="secondary" @click="addPillar(); showAddPillar = false">Add</AppButton>
            </div>

            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">Strategy</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddStrategy = !showAddStrategy">+ Add new</button>
            </div>
            <div v-if="showAddStrategy" class="flex gap-2 mb-2">
              <AppSelect v-model="newStrategy.dost_pillar_id" placeholder="Pillar">
                <option v-for="p in pillars" :key="p.id" :value="p.id">{{ p.name }}</option>
              </AppSelect>
              <AppInput v-model="newStrategy.name" type="text" placeholder="New Strategy name" class="flex-1" />
              <AppButton type="button" variant="secondary" @click="addStrategy(); showAddStrategy = false">Add</AppButton>
            </div>

            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">Sub-Strategy (this is what gets tagged)</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddSubStrategy = !showAddSubStrategy">+ Add new</button>
            </div>
            <div v-if="showAddSubStrategy" class="flex gap-2 mb-2">
              <AppSelect v-model="newSubStrategy.dost_strategy_id" placeholder="Strategy">
                <option v-for="s in allStrategies" :key="s.id" :value="s.id">{{ s.name }}</option>
              </AppSelect>
              <AppInput v-model="newSubStrategy.description" type="text" placeholder="New Sub-Strategy description" class="flex-1" />
              <AppButton type="button" variant="secondary" @click="addSubStrategy(); showAddSubStrategy = false">Add</AppButton>
            </div>

            <AppSelect v-model="indicatorForm.dost_sub_strategy_id" placeholder="-- None --">
              <optgroup v-for="pillar in pillars" :key="pillar.id" :label="pillar.name">
                <optgroup v-for="strategy in pillar.strategies" :key="strategy.id" :label="strategy.name">
                  <option v-for="sub in strategy.sub_strategies" :key="sub.id" :value="sub.id">{{ sub.description }}</option>
                </optgroup>
              </optgroup>
            </AppSelect>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">PSHS Program (Agency Outcome)</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddProgram = !showAddProgram">+ Add new</button>
            </div>
            <div v-if="showAddProgram" class="flex gap-2 mb-2">
              <AppInput v-model="newProgram.outcome" type="text" placeholder="Program name, e.g. E. New Program" class="flex-1" />
              <AppSelect v-model="newProgram.function_type" placeholder="Function type">
                <option value="Strategic Functions">Strategic Functions</option>
                <option value="Core Functions">Core Functions</option>
                <option value="Support Functions">Support Functions</option>
              </AppSelect>
              <AppButton type="button" variant="secondary" @click="addProgram(); showAddProgram = false">Add</AppButton>
            </div>
            <AppSelect v-model="indicatorForm.agency_outcome_id" placeholder="-- None --">
              <option v-for="outcome in agencyOutcomes" :key="outcome.id" :value="outcome.id">{{ outcome.outcome }}</option>
            </AppSelect>
          </div>

          <AppSelect v-model="indicatorForm.performance_indicator_id" label="Link to an existing IPCR indicator (optional)" placeholder="-- None --">
            <option v-for="pi in performanceIndicators" :key="pi.id" :value="pi.id">{{ pi.description }}</option>
          </AppSelect>

          <AppTextarea v-model="indicatorForm.description" label="Indicator Description" :rows="2" required />
          <AppInput v-model="indicatorForm.target" label="Target" type="text" />
          <AppInput v-model="indicatorForm.budget" label="Budget" type="number" min="0" step="0.01" />

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Division(s) Accountable</label>
            <Multiselect
              v-model="indicatorForm.divisions"
              :options="divisions"
              :multiple="true"
              :close-on-select="false"
              label="division_name"
              track-by="id"
              placeholder="Select one or more divisions"
            />
          </div>

          <AppTextarea v-model="indicatorForm.remarks" label="Remarks" :rows="2" />
        </form>

        <template #footer>
          <AppButton variant="secondary" @click="closeIndicatorModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-indicator-form">Save</AppButton>
        </template>
      </AppModal>

      <!-- Period create/edit modal -->
      <AppModal :show="showPeriodModal" title="OPCR Period" size="md" @close="closePeriodModal">
        <form id="opcr-period-form" @submit.prevent="submitPeriod" class="space-y-4">
          <AppInput v-model="periodForm.fiscal_year" label="Fiscal Year" type="number" required />
          <AppInput v-model="periodForm.period_label" label="Period Label" type="text" placeholder="January - December 2026" required />
          <AppInput v-model="periodForm.campus_director_name" label="Campus Director Name" type="text" />
          <AppInput v-model="periodForm.oic_campus_director_name" label="OIC-Campus Director Name" type="text" />
          <AppInput v-model="periodForm.executive_director_name" label="Executive Director Name" type="text" />
          <AppTextarea v-model="periodForm.commitment_statement" label="Commitment Statement (optional override)" :rows="3" />
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" v-model="periodForm.is_current" />
            Make this the current OPCR period
          </label>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closePeriodModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-period-form">Save</AppButton>
        </template>
      </AppModal>

      <!-- Clone modal -->
      <AppModal :show="showCloneModal" title="Clone from a previous FY" size="sm" @close="closeCloneModal">
        <form id="opcr-clone-form" @submit.prevent="submitClone(period.id)" class="space-y-4">
          <AppSelect v-model="cloneForm.source_period_id" label="Source Period" required placeholder="-- Select --">
            <option v-for="p in periods.filter(p => p.id !== period?.id)" :key="p.id" :value="p.id">FY {{ p.fiscal_year }} — {{ p.period_label }}</option>
          </AppSelect>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeCloneModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-clone-form">Clone</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Add the nav entry**

In `resources/js/Layouts/navigation.js`, immediately after the "DOST Strategic Plan" child entry (ends ~line 683, before the "Agency Org Outcome" entry), add:

```js
      {
        label: "OPCR",
        routeName: "opcr.index",
        href: route("opcr.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["opcr.view", "opcr.manage"],
      },
```

- [ ] **Step 3: Build the frontend**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or `npm run build` from the host if Node runs there — check which the project actually uses by looking at how `build` was invoked in the most recent related commit).
Expected: build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/PerformanceManagement/Opcr.vue resources/js/Layouts/navigation.js
git commit -m "feat(opcr): add OPCR page and nav entry"
```

---

### Task 14: Run the full backend regression suite

**Files:** none (verification-only task).

- [ ] **Step 1: Run the full PHPUnit suite**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: all `OPCR`-namespaced tests pass; failure count for the rest of the suite matches the pre-existing baseline exactly (spot-check any new failures against `git stash` + re-run on plain `main` before assuming they're pre-existing, per this project's established practice).

- [ ] **Step 2: Run the JS test suite**

Run: `npm run test:js`
Expected: all pass, including the new `opcrGrouping.test.mjs`.

- [ ] **Step 3: Seed permissions in dev**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=Database\\\\Seeders\\\\OpcrPermissionSeeder"`
Expected: `opcr.view`/`opcr.manage` permissions exist and are granted to `OCD`/`PMT`/`DivisionChief` in the dev database.

- [ ] **Step 4: Report status**

No commit — this task only verifies. If any non-pre-existing failure is found, stop and fix it in the task that introduced it before proceeding (do not patch forward in this task).

---

### Task 15: Manual browser click-through

**Files:** none (verification-only task).

This project's established practice for Performance-Management-area work (per `project_spms_module.md` / `project_pm_v2_module.md`) is: **do not consider a module "done" on green tests alone — the user needs to actually click through it before this is treated as finished.** This task is a checklist, not code.

- [ ] **Step 1: Log in as a user with `opcr.manage` (OCD or PMT role) in dev** (`http://localhost:8080`)

- [ ] **Step 2: Navigate to Performance Mngmt → OPCR**, confirm the page loads with no current period, click "New FY", create FY2026, mark it current.

- [ ] **Step 3: Click "New Indicator"**, use the "+ Add new" links to create a Pillar, a Strategy under it, a Sub-Strategy under that, and a PSHS Program that don't exist yet, all without leaving the modal — confirm each newly-created row appears in its dropdown immediately after the partial reload.

- [ ] **Step 4: Enter a Q1 actual value inline, then a Q2 value** — confirm both persist independently.

- [ ] **Step 5: Enter Q/E/T/A rating values inline** — confirm they save as-entered.

- [ ] **Step 6: Click "Export PDF"** — confirm it opens a PDF matching the source document's shape (title, commitment statement, grouped table, signature blocks).

- [ ] **Step 7: Log in as a user with only `opcr.view` and `division_id` set to a division that IS tagged on an indicator** — confirm they see only their division's indicators, read-only (no edit/delete controls, no New Indicator button).

- [ ] **Step 8: Report back to the user directly** — this is the checkpoint the two prior reverts skipped. Do not report this module "complete" without this step having actually happened.

- [ ] **Step 9: Only after the user confirms it matches what they wanted**, proceed to `superpowers:finishing-a-development-branch` for the commit/deploy decision — do not commit-and-deploy as an automatic continuation of this plan.
