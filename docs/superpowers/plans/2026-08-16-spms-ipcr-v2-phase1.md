# SPMS Phase 1 (IPCR v2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the individual-level (IPCR) tier of the new SPMS module — new template with embedded rubrics, load-driven target generation, MOV evidence checklist, and configurable weights — fully separate from the live v1 IPCR module, per `docs/superpowers/specs/2026-08-16-performance-management-v2-spms-design.md`.

**Architecture:** Seven new `spms_*` tables under a new `App\Models\SPMS` namespace, a workflow service mirroring `IPCRWorkflowService`'s state-machine pattern, a target-generation service mirroring `FacultyIPCRBaselineService`'s `LoadAssignment`-driven approach, base64/S3 evidence uploads following the repo's existing `uploadBase64Photo()` pattern, and role-facing Inertia controllers/pages mirroring the existing `PerformanceManagement/*` structure. `dpcr_id` on `spms_ipcrs` stays nullable — Phase 2 populates it.

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL, PHPUnit (not Pest — confirmed via `phpunit.xml`), Vue 3 `<script setup>` + Inertia 2, Tailwind, S3 via `Storage::disk('s3')`.

## Global Constraints

- Table prefix `spms_` on every new table; namespace `App\Models\SPMS` for every new model — zero shared tables, routes, or FKs with the live `ipcr*`/`employee_ipcrs*` tables or `App\Models\EmployeeIPCR` (per spec's Data Strategy).
- Weight defaults: Strategic/Core/Support = 30/50/20, resolved per level+division+fiscal_year via `spms_weight_profiles`, never hardcoded in a service constant (unlike v1's `IPCRWorkflowService::FUNCTION_WEIGHTS`, which this deliberately does not replicate).
- All file uploads (MOV evidence) as base64 JSON body — never `FormData`/multipart (Cloudflare WAF blocks it).
- All file storage via `Storage::disk('s3')`, never `disk('public')`; served only via an authenticated proxy route, never a direct S3 URL.
- Every migration needs a working `down()`; additive-only column changes (`->after()`) if any later task touches an already-shipped table in this plan.
- Permission string pattern `spms.<submodule>.<action>`; seeded via `Permission::updateOrCreate()` + `syncWithoutDetaching()`, idempotent.
- `Carbon::parse($value)->format('Y-m-d')` for any date-cast Eloquent attribute — never `new DateTime()` (PHP 8 type coercion silently zeroes values, per this repo's known gotcha).
- Status columns are plain strings with human-readable default values (e.g. `'Draft Target'`), matching `IPCRRatingPeriod`/`EmployeeIPCR` convention — not a DB enum type.
- Tests run against real MySQL in the test environment (`DB_CONNECTION=mysql`), via `RefreshDatabase`, using `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=<Name>"`.

---

### Task 1: `spms_fiscal_periods` table + model

**Files:**
- Create: `database/migrations/2026_08_16_100001_create_spms_fiscal_periods_table.php`
- Create: `app/Models/SPMS/FiscalPeriod.php`
- Create: `database/factories/SPMS/FiscalPeriodFactory.php`
- Test: `tests/Feature/SPMS/FiscalPeriodTest.php`

**Interfaces:**
- Produces: `FiscalPeriod` model with `cadence` (`'quarter'|'semester'|'annual'`), `fiscal_year` (int), `label`, `start_date`, `end_date`, `parent_period_id` (nullable self-FK), `school_year_id` (nullable FK `school_years.id`), `is_current` (bool). Scopes: `scopeCurrent($query)`, `scopeOfCadence($query, string $cadence)`. Relation: `parent(): BelongsTo`, `children(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/FiscalPeriodTest.php
namespace Tests\Feature\SPMS;

use App\Models\SPMS\FiscalPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_scope_returns_only_current_period(): void
    {
        FiscalPeriod::factory()->create(['is_current' => false, 'label' => 'Q1 2025']);
        $current = FiscalPeriod::factory()->create(['is_current' => true, 'label' => 'Q1 2026']);

        $result = FiscalPeriod::current()->get();

        $this->assertCount(1, $result);
        $this->assertSame($current->id, $result->first()->id);
    }

    public function test_quarter_links_to_parent_semester(): void
    {
        $semester = FiscalPeriod::factory()->create(['cadence' => 'semester', 'label' => '1st Semester 2026']);
        $quarter = FiscalPeriod::factory()->create([
            'cadence' => 'quarter',
            'label' => 'Q1 2026',
            'parent_period_id' => $semester->id,
        ]);

        $this->assertSame($semester->id, $quarter->parent->id);
        $this->assertTrue($semester->children->contains('id', $quarter->id));
    }

    public function test_of_cadence_scope_filters_by_cadence(): void
    {
        FiscalPeriod::factory()->create(['cadence' => 'quarter']);
        FiscalPeriod::factory()->create(['cadence' => 'annual']);

        $result = FiscalPeriod::ofCadence('annual')->get();

        $this->assertCount(1, $result);
        $this->assertSame('annual', $result->first()->cadence);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=FiscalPeriodTest"`
Expected: FAIL — class `App\Models\SPMS\FiscalPeriod` not found.

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_08_16_100001_create_spms_fiscal_periods_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('cadence'); // 'quarter' | 'semester' | 'annual'
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('label'); // e.g. "Q1 2026", "1st Semester 2026", "FY 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('parent_period_id')->nullable()->constrained('spms_fiscal_periods')->nullOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_fiscal_periods');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_16_100001_create_spms_fiscal_periods_table.php"`
Expected: `Migrated: ... create_spms_fiscal_periods_table`

- [ ] **Step 5: Create the model**

```php
<?php
// app/Models/SPMS/FiscalPeriod.php
namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class FiscalPeriod extends Model
{
    use HasFactory;

    protected $table = 'spms_fiscal_periods';

    protected $fillable = [
        'cadence', 'fiscal_year', 'label', 'start_date', 'end_date',
        'parent_period_id', 'school_year_id', 'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'fiscal_year' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'parent_period_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'parent_period_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeOfCadence(Builder $query, string $cadence): Builder
    {
        return $query->where('cadence', $cadence);
    }
}
```

- [ ] **Step 6: Create the factory**

```php
<?php
// database/factories/SPMS/FiscalPeriodFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\FiscalPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalPeriodFactory extends Factory
{
    protected $model = FiscalPeriod::class;

    public function definition(): array
    {
        return [
            'cadence' => 'quarter',
            'fiscal_year' => 2026,
            'label' => 'Q1 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'parent_period_id' => null,
            'school_year_id' => null,
            'is_current' => false,
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=FiscalPeriodTest"`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_16_100001_create_spms_fiscal_periods_table.php app/Models/SPMS/FiscalPeriod.php database/factories/SPMS/FiscalPeriodFactory.php tests/Feature/SPMS/FiscalPeriodTest.php
git commit -m "feat(spms): add FiscalPeriod model and migration"
```

---

### Task 2: `spms_outcomes` + `spms_performance_indicators` tables + models

**Files:**
- Create: `database/migrations/2026_08_16_100002_create_spms_outcomes_table.php`
- Create: `database/migrations/2026_08_16_100003_create_spms_performance_indicators_table.php`
- Create: `database/migrations/2026_08_16_100004_create_spms_division_performance_indicator_table.php`
- Create: `app/Models/SPMS/Outcome.php`
- Create: `app/Models/SPMS/PerformanceIndicator.php`
- Create: `database/factories/SPMS/OutcomeFactory.php`
- Create: `database/factories/SPMS/PerformanceIndicatorFactory.php`
- Test: `tests/Feature/SPMS/PerformanceIndicatorTest.php`

**Interfaces:**
- Consumes: none new (uses `divisions` table, already exists).
- Produces: `Outcome` (`outcome`, `sub_outcome`, `function_type` `'strategic'|'core'|'support'`, `fiscal_year`), `PerformanceIndicator` (`spms_outcome_id`, `description`, `target`, `budget`, `fiscal_year`), `PerformanceIndicator::divisions(): BelongsToMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/PerformanceIndicatorTest.php
namespace Tests\Feature\SPMS;

use App\Models\Division;
use App\Models\SPMS\Outcome;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicator_belongs_to_outcome(): void
    {
        $outcome = Outcome::factory()->create(['function_type' => 'core']);
        $indicator = PerformanceIndicator::factory()->create(['spms_outcome_id' => $outcome->id]);

        $this->assertSame($outcome->id, $indicator->outcome->id);
        $this->assertTrue($outcome->indicators->contains('id', $indicator->id));
    }

    public function test_indicator_attaches_to_multiple_divisions(): void
    {
        $indicator = PerformanceIndicator::factory()->create();
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $indicator->divisions()->attach([$divisionA->id, $divisionB->id]);

        $this->assertCount(2, $indicator->fresh()->divisions);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerformanceIndicatorTest"`
Expected: FAIL — `App\Models\SPMS\Outcome` not found.

- [ ] **Step 3: Create the three migrations**

```php
<?php
// database/migrations/2026_08_16_100002_create_spms_outcomes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('outcome');
            $table->string('sub_outcome')->nullable();
            $table->string('function_type'); // 'strategic' | 'core' | 'support'
            $table->unsignedSmallInteger('fiscal_year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_outcomes');
    }
};
```

```php
<?php
// database/migrations/2026_08_16_100003_create_spms_performance_indicators_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spms_outcome_id')->constrained('spms_outcomes')->cascadeOnDelete();
            $table->text('description');
            $table->text('target')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->unsignedSmallInteger('fiscal_year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_performance_indicators');
    }
};
```

```php
<?php
// database/migrations/2026_08_16_100004_create_spms_division_performance_indicator_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_division_performance_indicator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('spms_performance_indicator_id')->constrained('spms_performance_indicators')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_division_performance_indicator');
    }
};
```

- [ ] **Step 4: Run migrations**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_16_100002_create_spms_outcomes_table.php && php artisan migrate --path=database/migrations/2026_08_16_100003_create_spms_performance_indicators_table.php && php artisan migrate --path=database/migrations/2026_08_16_100004_create_spms_division_performance_indicator_table.php"`
Expected: all three migrate cleanly.

- [ ] **Step 5: Create the models**

```php
<?php
// app/Models/SPMS/Outcome.php
namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outcome extends Model
{
    use HasFactory;

    protected $table = 'spms_outcomes';

    protected $fillable = ['outcome', 'sub_outcome', 'function_type', 'fiscal_year'];

    protected $casts = ['fiscal_year' => 'integer'];

    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class, 'spms_outcome_id');
    }
}
```

```php
<?php
// app/Models/SPMS/PerformanceIndicator.php
namespace App\Models\SPMS;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PerformanceIndicator extends Model
{
    use HasFactory;

    protected $table = 'spms_performance_indicators';

    protected $fillable = ['spms_outcome_id', 'description', 'target', 'budget', 'fiscal_year'];

    protected $casts = ['budget' => 'decimal:2', 'fiscal_year' => 'integer'];

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(Outcome::class, 'spms_outcome_id');
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(
            Division::class,
            'spms_division_performance_indicator',
            'spms_performance_indicator_id',
            'division_id'
        )->withTimestamps();
    }
}
```

- [ ] **Step 6: Create factories**

```php
<?php
// database/factories/SPMS/OutcomeFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\Outcome;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutcomeFactory extends Factory
{
    protected $model = Outcome::class;

    public function definition(): array
    {
        return [
            'outcome' => $this->faker->sentence(4),
            'sub_outcome' => $this->faker->sentence(3),
            'function_type' => 'core',
            'fiscal_year' => 2026,
        ];
    }
}
```

```php
<?php
// database/factories/SPMS/PerformanceIndicatorFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\Outcome;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceIndicatorFactory extends Factory
{
    protected $model = PerformanceIndicator::class;

    public function definition(): array
    {
        return [
            'spms_outcome_id' => Outcome::factory(),
            'description' => $this->faker->sentence(8),
            'target' => '100% compliance',
            'budget' => null,
            'fiscal_year' => 2026,
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=PerformanceIndicatorTest"`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_16_100002_create_spms_outcomes_table.php database/migrations/2026_08_16_100003_create_spms_performance_indicators_table.php database/migrations/2026_08_16_100004_create_spms_division_performance_indicator_table.php app/Models/SPMS/Outcome.php app/Models/SPMS/PerformanceIndicator.php database/factories/SPMS/OutcomeFactory.php database/factories/SPMS/PerformanceIndicatorFactory.php tests/Feature/SPMS/PerformanceIndicatorTest.php
git commit -m "feat(spms): add Outcome and PerformanceIndicator models"
```

---

### Task 3: `spms_weight_profiles` table + model + `WeightProfileResolver` service

**Files:**
- Create: `database/migrations/2026_08_16_100005_create_spms_weight_profiles_table.php`
- Create: `app/Models/SPMS/WeightProfile.php`
- Create: `app/Services/SPMS/WeightProfileResolver.php`
- Create: `database/factories/SPMS/WeightProfileFactory.php`
- Test: `tests/Unit/SPMS/WeightProfileResolverTest.php`

**Interfaces:**
- Produces: `WeightProfile` (`level` `'opcr'|'dpcr'|'ipcr'`, `division_id` nullable, `fiscal_year`, `strategic_pct`, `core_pct`, `support_pct`, `core_subweights` JSON nullable). `WeightProfileResolver::resolve(string $level, ?int $divisionId, int $fiscalYear): array` returns `['strategic_pct' => float, 'core_pct' => float, 'support_pct' => float, 'core_subweights' => array|null]`, falling back division-specific → system default (`division_id === null`) → hardcoded `30/50/20` if nothing is seeded at all.
- Consumes (later tasks): `IPCRWorkflowService::computeWeightedAverage()` (Task 8) calls `WeightProfileResolver::resolve('ipcr', $divisionId, $fiscalYear)`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/SPMS/WeightProfileResolverTest.php
namespace Tests\Unit\SPMS;

use App\Models\Division;
use App\Models\SPMS\WeightProfile;
use App\Services\SPMS\WeightProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_hardcoded_default_when_nothing_seeded(): void
    {
        $weights = (new WeightProfileResolver())->resolve('ipcr', null, 2026);

        $this->assertSame(30.0, $weights['strategic_pct']);
        $this->assertSame(50.0, $weights['core_pct']);
        $this->assertSame(20.0, $weights['support_pct']);
    }

    public function test_uses_system_default_profile_when_no_division_override(): void
    {
        WeightProfile::factory()->create([
            'level' => 'ipcr', 'division_id' => null, 'fiscal_year' => 2026,
            'strategic_pct' => 25, 'core_pct' => 55, 'support_pct' => 20,
        ]);

        $weights = (new WeightProfileResolver())->resolve('ipcr', 999, 2026);

        $this->assertSame(25.0, $weights['strategic_pct']);
    }

    public function test_division_specific_profile_overrides_system_default(): void
    {
        $division = Division::factory()->create();
        WeightProfile::factory()->create([
            'level' => 'ipcr', 'division_id' => null, 'fiscal_year' => 2026,
            'strategic_pct' => 30, 'core_pct' => 50, 'support_pct' => 20,
        ]);
        WeightProfile::factory()->create([
            'level' => 'ipcr', 'division_id' => $division->id, 'fiscal_year' => 2026,
            'strategic_pct' => 20, 'core_pct' => 60, 'support_pct' => 20,
        ]);

        $weights = (new WeightProfileResolver())->resolve('ipcr', $division->id, 2026);

        $this->assertSame(20.0, $weights['strategic_pct']);
        $this->assertSame(60.0, $weights['core_pct']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=WeightProfileResolverTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_08_16_100005_create_spms_weight_profiles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_weight_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // 'opcr' | 'dpcr' | 'ipcr'
            $table->foreignId('division_id')->nullable()->constrained('divisions')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('strategic_pct', 5, 2);
            $table->decimal('core_pct', 5, 2);
            $table->decimal('support_pct', 5, 2);
            $table->json('core_subweights')->nullable(); // DPCR-only: {core_duties_pct, student_eval_pct, supervisor_eval_pct}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_weight_profiles');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_16_100005_create_spms_weight_profiles_table.php"`

- [ ] **Step 5: Create the model**

```php
<?php
// app/Models/SPMS/WeightProfile.php
namespace App\Models\SPMS;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightProfile extends Model
{
    use HasFactory;

    protected $table = 'spms_weight_profiles';

    protected $fillable = [
        'level', 'division_id', 'fiscal_year',
        'strategic_pct', 'core_pct', 'support_pct', 'core_subweights',
    ];

    protected $casts = [
        'strategic_pct' => 'decimal:2',
        'core_pct' => 'decimal:2',
        'support_pct' => 'decimal:2',
        'core_subweights' => 'array',
        'fiscal_year' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
```

- [ ] **Step 6: Create the resolver service**

```php
<?php
// app/Services/SPMS/WeightProfileResolver.php
namespace App\Services\SPMS;

use App\Models\SPMS\WeightProfile;

class WeightProfileResolver
{
    private const DEFAULT_WEIGHTS = [
        'strategic_pct' => 30.0,
        'core_pct' => 50.0,
        'support_pct' => 20.0,
        'core_subweights' => null,
    ];

    public function resolve(string $level, ?int $divisionId, int $fiscalYear): array
    {
        if ($divisionId !== null) {
            $divisionProfile = WeightProfile::where('level', $level)
                ->where('division_id', $divisionId)
                ->where('fiscal_year', $fiscalYear)
                ->first();

            if ($divisionProfile) {
                return $this->toArray($divisionProfile);
            }
        }

        $defaultProfile = WeightProfile::where('level', $level)
            ->whereNull('division_id')
            ->where('fiscal_year', $fiscalYear)
            ->first();

        if ($defaultProfile) {
            return $this->toArray($defaultProfile);
        }

        return self::DEFAULT_WEIGHTS;
    }

    private function toArray(WeightProfile $profile): array
    {
        return [
            'strategic_pct' => (float) $profile->strategic_pct,
            'core_pct' => (float) $profile->core_pct,
            'support_pct' => (float) $profile->support_pct,
            'core_subweights' => $profile->core_subweights,
        ];
    }
}
```

- [ ] **Step 7: Create the factory**

```php
<?php
// database/factories/SPMS/WeightProfileFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\WeightProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeightProfileFactory extends Factory
{
    protected $model = WeightProfile::class;

    public function definition(): array
    {
        return [
            'level' => 'ipcr',
            'division_id' => null,
            'fiscal_year' => 2026,
            'strategic_pct' => 30,
            'core_pct' => 50,
            'support_pct' => 20,
            'core_subweights' => null,
        ];
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=WeightProfileResolverTest"`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_16_100005_create_spms_weight_profiles_table.php app/Models/SPMS/WeightProfile.php app/Services/SPMS/WeightProfileResolver.php database/factories/SPMS/WeightProfileFactory.php tests/Unit/SPMS/WeightProfileResolverTest.php
git commit -m "feat(spms): add WeightProfile model and resolver service"
```

---

### Task 4: `spms_ipcrs` table + model with status constants

**Files:**
- Create: `database/migrations/2026_08_16_100006_create_spms_ipcrs_table.php`
- Create: `app/Models/SPMS/Ipcr.php`
- Create: `database/factories/SPMS/IpcrFactory.php`
- Test: `tests/Feature/SPMS/IpcrModelTest.php`

**Interfaces:**
- Produces: `Ipcr::STATUS_DRAFT_TARGET = 'Draft Target'`, `STATUS_TARGET_SUBMITTED = 'Target Submitted'`, `STATUS_TARGET_APPROVED = 'Target Approved'`, `STATUS_SUBMITTED_FOR_RATING = 'Submitted for Rating'`, `STATUS_RATED = 'Rated'`, `STATUS_DC_REVIEWED = 'DC Reviewed'`, `STATUS_PMT_HR_REVIEWED = 'PMT/HR Reviewed'`, `STATUS_DIRECTOR_SIGNED = 'Director Signed'`, `STATUS_RETURNED = 'Returned'`. Fields: `user_id`, `fiscal_period_id`, `dpcr_id` (nullable), `status`, `weight_profile_id` (nullable), `final_rating` (nullable decimal), `final_adjectival` (nullable string). Relations: `user()`, `fiscalPeriod()`, `weightProfile()`, `targets(): HasMany` (to `IpcrTarget`, Task 5).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/IpcrModelTest.php
namespace Tests\Feature\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_draft_target_status(): void
    {
        $ipcr = Ipcr::factory()->create();

        $this->assertSame(Ipcr::STATUS_DRAFT_TARGET, $ipcr->status);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id]);

        $this->assertSame($user->id, $ipcr->user->id);
    }

    public function test_dpcr_id_is_nullable(): void
    {
        $ipcr = Ipcr::factory()->create();

        $this->assertNull($ipcr->dpcr_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrModelTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_08_16_100006_create_spms_ipcrs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_ipcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained('spms_fiscal_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('dpcr_id')->nullable(); // FK added in Phase 2 when spms_dpcrs exists
            $table->string('status')->default('Draft Target');
            $table->foreignId('weight_profile_id')->nullable()->constrained('spms_weight_profiles')->nullOnDelete();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('final_adjectival')->nullable();
            $table->timestamp('target_submitted_at')->nullable();
            $table->timestamp('target_approved_at')->nullable();
            $table->timestamp('submitted_for_rating_at')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('director_signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_ipcrs');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_16_100006_create_spms_ipcrs_table.php"`

- [ ] **Step 5: Create the model**

```php
<?php
// app/Models/SPMS/Ipcr.php
namespace App\Models\SPMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ipcr extends Model
{
    use HasFactory;

    protected $table = 'spms_ipcrs';

    public const STATUS_DRAFT_TARGET = 'Draft Target';
    public const STATUS_TARGET_SUBMITTED = 'Target Submitted';
    public const STATUS_TARGET_APPROVED = 'Target Approved';
    public const STATUS_SUBMITTED_FOR_RATING = 'Submitted for Rating';
    public const STATUS_RATED = 'Rated';
    public const STATUS_DC_REVIEWED = 'DC Reviewed';
    public const STATUS_PMT_HR_REVIEWED = 'PMT/HR Reviewed';
    public const STATUS_DIRECTOR_SIGNED = 'Director Signed';
    public const STATUS_RETURNED = 'Returned';

    protected $fillable = [
        'user_id', 'fiscal_period_id', 'dpcr_id', 'status', 'weight_profile_id',
        'final_rating', 'final_adjectival',
        'target_submitted_at', 'target_approved_at', 'submitted_for_rating_at',
        'rated_at', 'director_signed_at',
    ];

    protected $casts = [
        'final_rating' => 'decimal:2',
        'target_submitted_at' => 'datetime',
        'target_approved_at' => 'datetime',
        'submitted_for_rating_at' => 'datetime',
        'rated_at' => 'datetime',
        'director_signed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function weightProfile(): BelongsTo
    {
        return $this->belongsTo(WeightProfile::class, 'weight_profile_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(IpcrTarget::class, 'ipcr_id');
    }
}
```

- [ ] **Step 6: Create the factory**

```php
<?php
// database/factories/SPMS/IpcrFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpcrFactory extends Factory
{
    protected $model = Ipcr::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fiscal_period_id' => FiscalPeriod::factory(['cadence' => 'semester']),
            'dpcr_id' => null,
            'status' => Ipcr::STATUS_DRAFT_TARGET,
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrModelTest"`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_16_100006_create_spms_ipcrs_table.php app/Models/SPMS/Ipcr.php database/factories/SPMS/IpcrFactory.php tests/Feature/SPMS/IpcrModelTest.php
git commit -m "feat(spms): add Ipcr model with workflow status constants"
```

---

### Task 5: `spms_ipcr_targets` table + model

**Files:**
- Create: `database/migrations/2026_08_16_100007_create_spms_ipcr_targets_table.php`
- Create: `app/Models/SPMS/IpcrTarget.php`
- Create: `database/factories/SPMS/IpcrTargetFactory.php`
- Test: `tests/Feature/SPMS/IpcrTargetTest.php`

**Interfaces:**
- Consumes: `Ipcr` (Task 4).
- Produces: `IpcrTarget` (`ipcr_id`, `function_type` `'strategic'|'core'|'support'`, `source_type`/`source_id` nullable polymorphic, `success_indicator`, `target`, `rubric_text`, `weight_pct`, `actual_q`/`actual_e`/`actual_t`, `rating_q`/`rating_e`/`rating_t`/`rating_avg`, `remarks`). Relation `ipcr(): BelongsTo`, `source(): MorphTo`, `movChecklistItems(): HasMany` (to `MovChecklistItem`, Task 6).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/IpcrTargetTest.php
namespace Tests\Feature\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_ipcr(): void
    {
        $ipcr = Ipcr::factory()->create();
        $target = IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->assertSame($ipcr->id, $target->ipcr->id);
        $this->assertTrue($ipcr->targets->contains('id', $target->id));
    }

    public function test_function_type_and_weight_are_stored(): void
    {
        $target = IpcrTarget::factory()->create([
            'function_type' => 'core',
            'weight_pct' => 12.5,
            'rubric_text' => '5: 96-100%, 4: 91-95%, 3: 86-90%, 2: 81-85%, 1: below 81%',
        ]);

        $this->assertSame('core', $target->function_type);
        $this->assertSame('12.50', $target->weight_pct);
        $this->assertStringContainsString('96-100%', $target->rubric_text);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrTargetTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_08_16_100007_create_spms_ipcr_targets_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_ipcr_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained('spms_ipcrs')->cascadeOnDelete();
            $table->string('function_type'); // 'strategic' | 'core' | 'support'
            $table->string('source_type')->nullable(); // e.g. App\Models\LoadAssignment, App\Models\Committee
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('success_indicator');
            $table->text('target')->nullable();
            $table->text('rubric_text')->nullable();
            $table->decimal('weight_pct', 5, 2)->default(0);
            $table->decimal('actual_q', 5, 2)->nullable();
            $table->decimal('actual_e', 5, 2)->nullable();
            $table->decimal('actual_t', 5, 2)->nullable();
            $table->decimal('rating_q', 4, 2)->nullable();
            $table->decimal('rating_e', 4, 2)->nullable();
            $table->decimal('rating_t', 4, 2)->nullable();
            $table->decimal('rating_avg', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_ipcr_targets');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_16_100007_create_spms_ipcr_targets_table.php"`

- [ ] **Step 5: Create the model**

```php
<?php
// app/Models/SPMS/IpcrTarget.php
namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IpcrTarget extends Model
{
    use HasFactory;

    protected $table = 'spms_ipcr_targets';

    protected $fillable = [
        'ipcr_id', 'function_type', 'source_type', 'source_id',
        'success_indicator', 'target', 'rubric_text', 'weight_pct',
        'actual_q', 'actual_e', 'actual_t',
        'rating_q', 'rating_e', 'rating_t', 'rating_avg', 'remarks',
    ];

    protected $casts = [
        'weight_pct' => 'decimal:2',
        'actual_q' => 'decimal:2', 'actual_e' => 'decimal:2', 'actual_t' => 'decimal:2',
        'rating_q' => 'decimal:2', 'rating_e' => 'decimal:2', 'rating_t' => 'decimal:2',
        'rating_avg' => 'decimal:2',
    ];

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class, 'ipcr_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function movChecklistItems(): HasMany
    {
        return $this->hasMany(MovChecklistItem::class, 'spms_ipcr_target_id');
    }
}
```

- [ ] **Step 6: Create the factory**

```php
<?php
// database/factories/SPMS/IpcrTargetFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpcrTargetFactory extends Factory
{
    protected $model = IpcrTarget::class;

    public function definition(): array
    {
        return [
            'ipcr_id' => Ipcr::factory(),
            'function_type' => 'core',
            'source_type' => null,
            'source_id' => null,
            'success_indicator' => $this->faker->sentence(6),
            'target' => '100%',
            'rubric_text' => null,
            'weight_pct' => 10,
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IpcrTargetTest"`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_16_100007_create_spms_ipcr_targets_table.php app/Models/SPMS/IpcrTarget.php database/factories/SPMS/IpcrTargetFactory.php tests/Feature/SPMS/IpcrTargetTest.php
git commit -m "feat(spms): add IpcrTarget model"
```

---

### Task 6: `spms_ipcr_mov_checklist` table + model

**Files:**
- Create: `database/migrations/2026_08_16_100008_create_spms_ipcr_mov_checklist_table.php`
- Create: `app/Models/SPMS/MovChecklistItem.php`
- Create: `database/factories/SPMS/MovChecklistItemFactory.php`
- Test: `tests/Feature/SPMS/MovChecklistItemTest.php`

**Interfaces:**
- Consumes: `IpcrTarget` (Task 5).
- Produces: `MovChecklistItem` (`spms_ipcr_target_id`, `document_type`, `status` `'pending'|'submitted'|'not_applicable'` default `'pending'`, `s3_key` nullable, `submitted_at` nullable, `submitted_by` nullable FK `users.id`). Relation `target(): BelongsTo`, `submitter(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/MovChecklistItemTest.php
namespace Tests\Feature\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovChecklistItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_pending_status(): void
    {
        $item = MovChecklistItem::factory()->create();

        $this->assertSame('pending', $item->status);
    }

    public function test_belongs_to_ipcr_target(): void
    {
        $target = IpcrTarget::factory()->create();
        $item = MovChecklistItem::factory()->create(['spms_ipcr_target_id' => $target->id]);

        $this->assertSame($target->id, $item->target->id);
        $this->assertTrue($target->movChecklistItems->contains('id', $item->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MovChecklistItemTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_08_16_100008_create_spms_ipcr_mov_checklist_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_ipcr_mov_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spms_ipcr_target_id')->constrained('spms_ipcr_targets')->cascadeOnDelete();
            $table->string('document_type'); // e.g. SIP, OCM/CFFS, Grading Sheets, APR
            $table->string('status')->default('pending'); // pending | submitted | not_applicable
            $table->string('s3_key')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_ipcr_mov_checklist');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_16_100008_create_spms_ipcr_mov_checklist_table.php"`

- [ ] **Step 5: Create the model**

```php
<?php
// app/Models/SPMS/MovChecklistItem.php
namespace App\Models\SPMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'spms_ipcr_mov_checklist';

    protected $fillable = [
        'spms_ipcr_target_id', 'document_type', 'status', 's3_key', 'submitted_at', 'submitted_by',
    ];

    protected $casts = ['submitted_at' => 'datetime'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(IpcrTarget::class, 'spms_ipcr_target_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
```

- [ ] **Step 6: Create the factory**

```php
<?php
// database/factories/SPMS/MovChecklistItemFactory.php
namespace Database\Factories\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovChecklistItemFactory extends Factory
{
    protected $model = MovChecklistItem::class;

    public function definition(): array
    {
        return [
            'spms_ipcr_target_id' => IpcrTarget::factory(),
            'document_type' => 'SIP',
            'status' => 'pending',
        ];
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MovChecklistItemTest"`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_16_100008_create_spms_ipcr_mov_checklist_table.php app/Models/SPMS/MovChecklistItem.php database/factories/SPMS/MovChecklistItemFactory.php tests/Feature/SPMS/MovChecklistItemTest.php
git commit -m "feat(spms): add MovChecklistItem model"
```

---

### Task 7: SPMS permission seeder

**Files:**
- Create: `database/seeders/SPMSPermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (add `$this->call(SPMSPermissionSeeder::class);`)
- Test: `tests/Feature/SPMS/SPMSPermissionSeederTest.php`

**Interfaces:**
- Produces permission strings: `spms.ipcr.manage`, `spms.ipcr.review`, `spms.admin.manage`, attached to roles `Administrator`, `Faculty`, `Staff`, `DivisionChief`, `HR`, `PMT`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/SPMSPermissionSeederTest.php
namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\SPMSPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SPMSPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_permissions_and_attaches_to_roles(): void
    {
        $faculty = Role::create(['name' => 'Faculty']);
        $dc = Role::create(['name' => 'DivisionChief']);

        (new SPMSPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'spms.ipcr.manage']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.ipcr.review']);
        $this->assertDatabaseHas('permissions', ['name' => 'spms.admin.manage']);

        $manage = Permission::where('name', 'spms.ipcr.manage')->first();
        $this->assertTrue($faculty->fresh()->permissions->contains($manage));

        $review = Permission::where('name', 'spms.ipcr.review')->first();
        $this->assertTrue($dc->fresh()->permissions->contains($review));
    }

    public function test_is_idempotent(): void
    {
        Role::create(['name' => 'Faculty']);

        (new SPMSPermissionSeeder())->run();
        (new SPMSPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'spms.ipcr.manage')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SPMSPermissionSeederTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the seeder**

```php
<?php
// database/seeders/SPMSPermissionSeeder.php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SPMSPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'spms.ipcr.manage' => [
            'description' => 'Create and manage own SPMS IPCR (targets, MOV checklist, submit for rating)',
            'roles' => ['Administrator', 'Faculty', 'Staff'],
        ],
        'spms.ipcr.review' => [
            'description' => 'Review/rate SPMS IPCRs (Division Chief, PMT, HR, Director stages)',
            'roles' => ['Administrator', 'DivisionChief', 'HR', 'PMT'],
        ],
        'spms.admin.manage' => [
            'description' => 'Configure SPMS weight profiles, fiscal periods, and MOV document types',
            'roles' => ['Administrator', 'HR', 'PMT'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $config) {
            $permission = Permission::updateOrCreate(
                ['name' => $name],
                ['module' => 'SPMS', 'description' => $config['description']]
            );

            foreach ($config['roles'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                $role?->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }
}
```

- [ ] **Step 4: Register in DatabaseSeeder**

Modify `database/seeders/DatabaseSeeder.php`, adding to the `run()` method's `$this->call([...])` list (or a standalone `$this->call(SPMSPermissionSeeder::class);` line, matching however the existing `IssuancePermissionSeeder` is registered there):

```php
$this->call(SPMSPermissionSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SPMSPermissionSeederTest"`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the seeder in dev**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=Database\\\\Seeders\\\\SPMSPermissionSeeder"`
Expected: seeder runs without error against the dev database.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/SPMSPermissionSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/SPMS/SPMSPermissionSeederTest.php
git commit -m "feat(spms): add SPMS permission seeder"
```

---

### Task 8: `IPCRWorkflowService` (SPMS) — state machine, weighted rating, adjectival banding

**Files:**
- Create: `app/Services/SPMS/IPCRWorkflowService.php`
- Test: `tests/Unit/SPMS/IPCRWorkflowServiceTest.php`

**Interfaces:**
- Consumes: `Ipcr` (Task 4), `IpcrTarget` (Task 5), `WeightProfileResolver::resolve()` (Task 3).
- Produces: `IPCRWorkflowService::submitTarget(Ipcr $ipcr, User $actor): Ipcr`, `approveTarget(Ipcr $ipcr, User $actor): Ipcr`, `submitForRating(Ipcr $ipcr, User $actor): Ipcr`, `rate(Ipcr $ipcr, User $actor): Ipcr` (computes and stores per-target `rating_avg` from Q/E/T, then transitions to `STATUS_RATED`), `reviewByDivisionChief(Ipcr $ipcr, User $actor): Ipcr`, `reviewByPmtHr(Ipcr $ipcr, User $actor): Ipcr`, `finalize(Ipcr $ipcr, User $actor): Ipcr` (terminal — sets `final_rating`/`final_adjectival`, status `STATUS_DIRECTOR_SIGNED`), `returnToSender(Ipcr $ipcr, User $actor, string $reason): Ipcr` (status → `STATUS_RETURNED`, always re-editable by resetting to `STATUS_DRAFT_TARGET` on the employee's next `submitTarget()` call), `computeWeightedAverage(Ipcr $ipcr): float`, `adjectivalRating(float $rating): string`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/SPMS/IPCRWorkflowServiceTest.php
namespace Tests\Unit\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use App\Services\SPMS\IPCRWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private IPCRWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IPCRWorkflowService();
    }

    public function test_submit_target_transitions_draft_to_submitted(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_DRAFT_TARGET]);

        $result = $this->service->submitTarget($ipcr, $ipcr->user);

        $this->assertSame(Ipcr::STATUS_TARGET_SUBMITTED, $result->status);
        $this->assertNotNull($result->target_submitted_at);
    }

    public function test_submit_target_rejects_wrong_status(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_RATED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitTarget($ipcr, $ipcr->user);
    }

    public function test_only_owner_can_submit_target(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_DRAFT_TARGET]);
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->submitTarget($ipcr, $other);
    }

    public function test_compute_weighted_average_applies_default_30_50_20(): void
    {
        $ipcr = Ipcr::factory()->create();
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'strategic', 'rating_avg' => 5.0]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'support', 'rating_avg' => 3.0]);

        // 5*0.30 + 4*0.50 + 3*0.20 = 1.5 + 2.0 + 0.6 = 4.1
        $this->assertEqualsWithDelta(4.1, $this->service->computeWeightedAverage($ipcr), 0.001);
    }

    public function test_adjectival_rating_bands(): void
    {
        $this->assertSame('Outstanding', $this->service->adjectivalRating(4.51));
        $this->assertSame('Very Satisfactory', $this->service->adjectivalRating(3.51));
        $this->assertSame('Satisfactory', $this->service->adjectivalRating(2.51));
        $this->assertSame('Unsatisfactory', $this->service->adjectivalRating(1.51));
        $this->assertSame('Poor', $this->service->adjectivalRating(1.0));
    }

    public function test_finalize_is_terminal_and_immutable(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_PMT_HR_REVIEWED]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0]);
        $director = User::factory()->create();

        $result = $this->service->finalize($ipcr, $director);

        $this->assertSame(Ipcr::STATUS_DIRECTOR_SIGNED, $result->status);
        $this->assertNotNull($result->final_rating);
        $this->assertNotNull($result->final_adjectival);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitTarget($result, $result->user);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IPCRWorkflowServiceTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the service**

```php
<?php
// app/Services/SPMS/IPCRWorkflowService.php
namespace App\Services\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class IPCRWorkflowService
{
    private const TRANSITIONS = [
        Ipcr::STATUS_DRAFT_TARGET => [Ipcr::STATUS_TARGET_SUBMITTED],
        Ipcr::STATUS_TARGET_SUBMITTED => [Ipcr::STATUS_TARGET_APPROVED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_TARGET_APPROVED => [Ipcr::STATUS_SUBMITTED_FOR_RATING],
        Ipcr::STATUS_SUBMITTED_FOR_RATING => [Ipcr::STATUS_RATED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_RATED => [Ipcr::STATUS_DC_REVIEWED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_DC_REVIEWED => [Ipcr::STATUS_PMT_HR_REVIEWED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_PMT_HR_REVIEWED => [Ipcr::STATUS_DIRECTOR_SIGNED, Ipcr::STATUS_RETURNED],
        Ipcr::STATUS_DIRECTOR_SIGNED => [],
        Ipcr::STATUS_RETURNED => [Ipcr::STATUS_DRAFT_TARGET],
    ];

    public function submitTarget(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanManage($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_TARGET_SUBMITTED, ['target_submitted_at' => now()]);
    }

    public function approveTarget(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_TARGET_APPROVED, ['target_approved_at' => now()]);
    }

    public function submitForRating(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanManage($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_SUBMITTED_FOR_RATING, ['submitted_for_rating_at' => now()]);
    }

    public function rate(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        foreach ($ipcr->targets as $target) {
            if ($target->rating_avg === null && $target->rating_q !== null && $target->rating_e !== null && $target->rating_t !== null) {
                $average = round(((float) $target->rating_q + (float) $target->rating_e + (float) $target->rating_t) / 3, 2);
                $target->update(['rating_avg' => $average]);
            }
        }

        return $this->transition($ipcr, Ipcr::STATUS_RATED, ['rated_at' => now()]);
    }

    public function reviewByDivisionChief(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_DC_REVIEWED);
    }

    public function reviewByPmtHr(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_PMT_HR_REVIEWED);
    }

    public function finalize(Ipcr $ipcr, User $actor): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        $rating = $this->computeWeightedAverage($ipcr);

        return $this->transition($ipcr, Ipcr::STATUS_DIRECTOR_SIGNED, [
            'final_rating' => $rating,
            'final_adjectival' => $this->adjectivalRating($rating),
            'director_signed_at' => now(),
        ]);
    }

    public function returnToSender(Ipcr $ipcr, User $actor, string $reason): Ipcr
    {
        $this->assertCanReview($ipcr, $actor);

        return $this->transition($ipcr, Ipcr::STATUS_RETURNED, ['return_reason' => $reason]);
    }

    public function computeWeightedAverage(Ipcr $ipcr): float
    {
        $weights = (new WeightProfileResolver())->resolve(
            'ipcr',
            $ipcr->user->division_id ?? null,
            $ipcr->fiscalPeriod->fiscal_year
        );

        $averages = ['strategic' => 0.0, 'core' => 0.0, 'support' => 0.0];

        foreach (['strategic', 'core', 'support'] as $type) {
            $targets = $ipcr->targets->where('function_type', $type)->whereNotNull('rating_avg');
            if ($targets->isNotEmpty()) {
                $averages[$type] = (float) $targets->avg('rating_avg');
            }
        }

        return round(
            $averages['strategic'] * ($weights['strategic_pct'] / 100)
            + $averages['core'] * ($weights['core_pct'] / 100)
            + $averages['support'] * ($weights['support_pct'] / 100),
            2
        );
    }

    public function adjectivalRating(float $rating): string
    {
        return match (true) {
            $rating >= 4.51 => 'Outstanding',
            $rating >= 3.51 => 'Very Satisfactory',
            $rating >= 2.51 => 'Satisfactory',
            $rating >= 1.51 => 'Unsatisfactory',
            default => 'Poor',
        };
    }

    private function assertCanManage(Ipcr $ipcr, User $actor): void
    {
        if ($ipcr->user_id !== $actor->id && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('Only the IPCR owner may perform this action.');
        }
    }

    private function assertCanReview(Ipcr $ipcr, User $actor): void
    {
        if (!$actor->hasPermission('spms.ipcr.review') && !$actor->isSuperAdmin()) {
            throw new AuthorizationException('You do not have permission to review this IPCR.');
        }
    }

    private function transition(Ipcr $ipcr, string $to, array $extra = []): Ipcr
    {
        return DB::transaction(function () use ($ipcr, $to, $extra) {
            $locked = Ipcr::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$locked->status] ?? [];
            if (!in_array($to, $allowed, true)) {
                throw new \InvalidArgumentException("Cannot transition SPMS IPCR #{$locked->id} from '{$locked->status}' to '{$to}'.");
            }

            $locked->update(array_merge(['status' => $to], $extra));

            return $locked->fresh();
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IPCRWorkflowServiceTest"`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SPMS/IPCRWorkflowService.php tests/Unit/SPMS/IPCRWorkflowServiceTest.php
git commit -m "feat(spms): add IPCRWorkflowService state machine and rating computation"
```

---

### Task 9: `IPCRTargetGenerationService` — load-driven target generation

**Files:**
- Create: `app/Services/SPMS/IPCRTargetGenerationService.php`
- Test: `tests/Unit/SPMS/IPCRTargetGenerationServiceTest.php`

**Interfaces:**
- Consumes: `Ipcr`/`IpcrTarget` (Tasks 4–5), `LoadAssignment` model (existing, per `FacultyIPCRBaselineService`'s usage — read its exact field names before implementing this task, since the fork's research did not include `LoadAssignment`'s schema; confirm via `php artisan tinker --execute="dd((new App\Models\LoadAssignment)->getFillable());"` in the dev container before writing Step 3).
- Produces: `IPCRTargetGenerationService::generate(Ipcr $ipcr): array{attached: int, personalized: int}` — never overwrites an existing target with the same `source_type`+`source_id` on that IPCR.

- [ ] **Step 1: Confirm `LoadAssignment`'s schema**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"dd((new App\\\\Models\\\\LoadAssignment)->getFillable());\""`

Note the exact column names for `user_id`, `school_year_id`, `load_source`, `assignment_type` (or equivalent) — adjust Steps 3–4 below to match whatever this returns before writing code.

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/Unit/SPMS/IPCRTargetGenerationServiceTest.php
namespace Tests\Unit\SPMS;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\LoadAssignment;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use App\Services\SPMS\IPCRTargetGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRTargetGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_one_core_target_per_distinct_load_source(): void
    {
        $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
        $user = User::factory()->create();
        $fiscalPeriod = FiscalPeriod::factory()->create(['school_year_id' => $schoolYear->id]);
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $fiscalPeriod->id]);

        LoadAssignment::factory()->create([
            'user_id' => $user->id,
            'school_year_id' => $schoolYear->id,
            'load_source' => 'Teaching Load',
        ]);
        LoadAssignment::factory()->create([
            'user_id' => $user->id,
            'school_year_id' => $schoolYear->id,
            'load_source' => 'Research Load',
        ]);

        $result = (new IPCRTargetGenerationService())->generate($ipcr);

        $this->assertSame(2, $result['attached']);
        $this->assertCount(2, $ipcr->fresh()->targets);
    }

    public function test_never_clobbers_an_existing_target(): void
    {
        $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
        $user = User::factory()->create();
        $fiscalPeriod = FiscalPeriod::factory()->create(['school_year_id' => $schoolYear->id]);
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $fiscalPeriod->id]);
        $assignment = LoadAssignment::factory()->create([
            'user_id' => $user->id,
            'school_year_id' => $schoolYear->id,
            'load_source' => 'Teaching Load',
        ]);

        $service = new IPCRTargetGenerationService();
        $service->generate($ipcr);
        $ipcr->fresh()->targets->first()->update(['target' => 'manually edited target']);

        $result = $service->generate($ipcr);

        $this->assertSame(0, $result['attached']);
        $this->assertSame('manually edited target', $ipcr->fresh()->targets->first()->target);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IPCRTargetGenerationServiceTest"`
Expected: FAIL — class not found.

- [ ] **Step 4: Implement the service**

Adjust field names below to match what Step 1 revealed about `LoadAssignment` before running:

```php
<?php
// app/Services/SPMS/IPCRTargetGenerationService.php
namespace App\Services\SPMS;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\LoadAssignment;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;

class IPCRTargetGenerationService
{
    public function generate(Ipcr $ipcr): array
    {
        $schoolYearId = $ipcr->fiscalPeriod->school_year_id
            ?? SchoolYear::where('is_current', true)->value('id');

        if (!$schoolYearId) {
            return ['attached' => 0, 'personalized' => 0];
        }

        $assignments = LoadAssignment::where('user_id', $ipcr->user_id)
            ->where('school_year_id', $schoolYearId)
            ->get()
            ->groupBy('load_source');

        $existing = $ipcr->targets()
            ->where('source_type', LoadAssignment::class)
            ->pluck('source_id')
            ->all();

        $attached = 0;

        foreach ($assignments as $loadSource => $group) {
            $totalUnits = $group->sum('units') ?: 1;

            foreach ($group as $assignment) {
                if (in_array($assignment->id, $existing, true)) {
                    continue;
                }

                IpcrTarget::create([
                    'ipcr_id' => $ipcr->id,
                    'function_type' => 'core',
                    'source_type' => LoadAssignment::class,
                    'source_id' => $assignment->id,
                    'success_indicator' => $this->buildTarget($assignment, $loadSource),
                    'weight_pct' => round(($assignment->units ?? 1) / $totalUnits * 100, 2),
                ]);

                $attached++;
            }
        }

        return ['attached' => $attached, 'personalized' => 0];
    }

    private function buildTarget(LoadAssignment $assignment, string $loadSource): string
    {
        return sprintf(
            '%s: %s',
            $loadSource,
            $assignment->description ?? $assignment->subject_name ?? $loadSource
        );
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IPCRTargetGenerationServiceTest"`
Expected: PASS (2 tests) — if field names differ from the placeholders above (`units`, `description`, `subject_name`), fix them per Step 1's actual output and re-run before committing.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SPMS/IPCRTargetGenerationService.php tests/Unit/SPMS/IPCRTargetGenerationServiceTest.php
git commit -m "feat(spms): add load-driven IPCR target generation service"
```

---

### Task 10: MOV checklist base64 upload service + S3 proxy route

**Files:**
- Create: `app/Services/SPMS/MovChecklistService.php`
- Create: `app/Http/Controllers/SPMS/MovChecklistController.php`
- Modify: `routes/web.php` (add proxy route)
- Test: `tests/Feature/SPMS/MovChecklistServiceTest.php`

**Interfaces:**
- Consumes: `MovChecklistItem` (Task 6).
- Produces: `MovChecklistService::uploadEvidence(IpcrTarget $target, string $documentType, string $base64DataUri, int $submittedBy): MovChecklistItem`. Route `GET /spms/ipcr/mov/{fileId}` name `spms.ipcr.mov.show`, controller `MovChecklistController::show(string $fileId)`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SPMS/MovChecklistServiceTest.php
namespace Tests\Feature\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use App\Services\SPMS\MovChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_base64_evidence_to_s3_and_marks_submitted(): void
    {
        Storage::fake('s3');
        $target = IpcrTarget::factory()->create();
        $user = User::factory()->create();
        $tinyPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $item = (new MovChecklistService())->uploadEvidence($target, 'SIP', $tinyPngBase64, $user->id);

        $this->assertSame('submitted', $item->status);
        $this->assertNotNull($item->s3_key);
        $this->assertSame($user->id, $item->submitted_by);
        Storage::disk('s3')->assertExists($item->s3_key);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MovChecklistServiceTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the upload service**

```php
<?php
// app/Services/SPMS/MovChecklistService.php
namespace App\Services\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Support\Facades\Storage;

class MovChecklistService
{
    public function uploadEvidence(IpcrTarget $target, string $documentType, string $base64DataUri, int $submittedBy): MovChecklistItem
    {
        [$extension, $binary] = $this->decode($base64DataUri);

        $s3Key = sprintf(
            'spms/ipcr-mov/%d/%d/%s-%d.%s',
            $target->ipcr_id,
            $target->id,
            \Illuminate\Support\Str::slug($documentType),
            now()->timestamp,
            $extension
        );

        Storage::disk('s3')->put($s3Key, $binary);

        return MovChecklistItem::updateOrCreate(
            ['spms_ipcr_target_id' => $target->id, 'document_type' => $documentType],
            [
                'status' => 'submitted',
                's3_key' => $s3Key,
                'submitted_at' => now(),
                'submitted_by' => $submittedBy,
            ]
        );
    }

    public static function encodeFileId(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public static function decodeFileId(string $fileId): string
    {
        $encoded = substr($fileId, 3); // strip 's3.' prefix
        $padded = str_pad(strtr($encoded, '-_', '+/'), strlen($encoded) % 4 === 0 ? strlen($encoded) : strlen($encoded) + (4 - strlen($encoded) % 4), '=');

        return base64_decode($padded);
    }

    private function decode(string $dataUri): array
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $dataUri, $matches)) {
            $extension = $matches[1];
            $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1));
        } else {
            $extension = 'bin';
            $binary = base64_decode($dataUri);
        }

        return [$extension, $binary];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MovChecklistServiceTest"`
Expected: PASS (1 test)

- [ ] **Step 5: Implement the proxy controller**

```php
<?php
// app/Http/Controllers/SPMS/MovChecklistController.php
namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Services\SPMS\MovChecklistService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MovChecklistController extends Controller
{
    public function show(string $fileId): Response
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_.=-]+$/', $fileId), 400);
        abort_unless(str_starts_with($fileId, 's3.'), 400);

        $s3Key = MovChecklistService::decodeFileId($fileId);

        abort_unless(Storage::disk('s3')->exists($s3Key), 404);

        $contents = Storage::disk('s3')->get($s3Key);
        $extension = strtolower(pathinfo($s3Key, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return response($contents, 200)->header('Content-Type', $mime);
    }
}
```

- [ ] **Step 6: Register the route**

Modify `routes/web.php`, adding inside the existing `permission:spms.ipcr.manage` group (created alongside Task 11's routes) or, if Task 11 hasn't run yet, as a standalone authenticated route for now:

```php
Route::middleware(['auth', 'permission:spms.ipcr.manage|spms.ipcr.review'])->group(function () {
    Route::get('/spms/ipcr/mov/{fileId}', [\App\Http\Controllers\SPMS\MovChecklistController::class, 'show'])
        ->name('spms.ipcr.mov.show');
});
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/SPMS/MovChecklistService.php app/Http/Controllers/SPMS/MovChecklistController.php routes/web.php tests/Feature/SPMS/MovChecklistServiceTest.php
git commit -m "feat(spms): add MOV checklist base64 upload and S3 proxy route"
```

---

### Task 11: Employee-facing IPCR controller, routes, and Vue pages

**Files:**
- Create: `app/Http/Controllers/SPMS/EmployeeIpcrController.php`
- Modify: `routes/web.php`
- Create: `resources/js/Composables/useSpmsEmployeeIpcr.js`
- Create: `resources/js/Pages/SPMS/EmployeeIpcrIndex.vue`
- Create: `resources/js/Pages/SPMS/EmployeeIpcrShow.vue`
- Test: `tests/Feature/SPMS/EmployeeIpcrControllerTest.php`

**Interfaces:**
- Consumes: `Ipcr`/`IpcrTarget` (Tasks 4–5), `IPCRWorkflowService` (Task 8), `IPCRTargetGenerationService` (Task 9).
- Produces: `EmployeeIpcrController::index()` → `Inertia::render('SPMS/EmployeeIpcrIndex', ['ipcrs' => ...])`; `show(Ipcr $ipcr)` → `Inertia::render('SPMS/EmployeeIpcrShow', ['ipcr' => ..., 'targets' => ...])`; `generateTargets(Ipcr $ipcr)`, `submitTarget(Ipcr $ipcr)`, `submitForRating(Ipcr $ipcr)` POST actions, each `back()->with('success', ...)`.

- [ ] **Step 1: Write the failing feature test**

```php
<?php
// tests/Feature/SPMS/EmployeeIpcrControllerTest.php
namespace Tests\Feature\SPMS;

use App\Models\Role;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUserWithPermission(): User
    {
        $role = Role::create(['name' => 'Faculty']);
        $permission = \App\Models\Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_renders_own_ipcrs_only(): void
    {
        $user = $this->actingUserWithPermission();
        $period = FiscalPeriod::factory()->create();
        Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $period->id]);
        Ipcr::factory()->create(['fiscal_period_id' => $period->id]); // someone else's

        $response = $this->actingAs($user)->get('/spms/ipcr');

        $response->assertInertia(fn ($page) => $page
            ->component('SPMS/EmployeeIpcrIndex')
            ->has('ipcrs', 1)
        );
    }

    public function test_submit_target_transitions_status(): void
    {
        $user = $this->actingUserWithPermission();
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id, 'status' => Ipcr::STATUS_DRAFT_TARGET]);

        $this->actingAs($user)->post("/spms/ipcr/{$ipcr->id}/submit-target")
            ->assertRedirect();

        $this->assertSame(Ipcr::STATUS_TARGET_SUBMITTED, $ipcr->fresh()->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrControllerTest"`
Expected: FAIL — route/controller not found.

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Http/Controllers/SPMS/EmployeeIpcrController.php
namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\Ipcr;
use App\Services\SPMS\IPCRTargetGenerationService;
use App\Services\SPMS\IPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeIpcrController extends Controller
{
    public function __construct(
        private readonly IPCRWorkflowService $workflow,
        private readonly IPCRTargetGenerationService $targetGeneration,
    ) {}

    public function index(): Response
    {
        $ipcrs = Ipcr::with('fiscalPeriod')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return Inertia::render('SPMS/EmployeeIpcrIndex', ['ipcrs' => $ipcrs]);
    }

    public function show(Ipcr $ipcr): Response
    {
        $this->authorizeOwner($ipcr);

        return Inertia::render('SPMS/EmployeeIpcrShow', [
            'ipcr' => $ipcr->load(['fiscalPeriod', 'targets.movChecklistItems']),
        ]);
    }

    public function generateTargets(Ipcr $ipcr): RedirectResponse
    {
        $this->authorizeOwner($ipcr);

        $result = $this->targetGeneration->generate($ipcr);

        return back()->with('success', "Generated {$result['attached']} target(s) from your current load assignments.");
    }

    public function submitTarget(Ipcr $ipcr): RedirectResponse
    {
        $this->authorizeOwner($ipcr);

        $this->workflow->submitTarget($ipcr, Auth::user());

        return back()->with('success', 'Target submitted for approval.');
    }

    public function submitForRating(Ipcr $ipcr): RedirectResponse
    {
        $this->authorizeOwner($ipcr);

        $this->workflow->submitForRating($ipcr, Auth::user());

        return back()->with('success', 'IPCR submitted for rating.');
    }

    private function authorizeOwner(Ipcr $ipcr): void
    {
        abort_unless($ipcr->user_id === Auth::id() || Auth::user()->isSuperAdmin(), 403);
    }
}
```

- [ ] **Step 4: Register routes**

Modify `routes/web.php`:

```php
Route::middleware(['auth', 'permission:spms.ipcr.manage'])->prefix('spms/ipcr')->name('spms.ipcr.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SPMS\EmployeeIpcrController::class, 'index'])->name('index');
    Route::get('/{ipcr}', [\App\Http\Controllers\SPMS\EmployeeIpcrController::class, 'show'])->name('show');
    Route::post('/{ipcr}/generate-targets', [\App\Http\Controllers\SPMS\EmployeeIpcrController::class, 'generateTargets'])->name('generate-targets');
    Route::post('/{ipcr}/submit-target', [\App\Http\Controllers\SPMS\EmployeeIpcrController::class, 'submitTarget'])->name('submit-target');
    Route::post('/{ipcr}/submit-for-rating', [\App\Http\Controllers\SPMS\EmployeeIpcrController::class, 'submitForRating'])->name('submit-for-rating');
});
```

- [ ] **Step 5: Create the composable**

```js
// resources/js/Composables/useSpmsEmployeeIpcr.js
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useSpmsEmployeeIpcr(ipcr) {
  const generateTargets = () => {
    router.post(route('spms.ipcr.generate-targets', ipcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Done', 'Targets generated from your load assignments.', 'success'),
      onError: () => Swal.fire('Error', 'Could not generate targets.', 'error'),
    })
  }

  const submitTarget = () => {
    router.post(route('spms.ipcr.submit-target', ipcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Submitted', 'Target submitted for approval.', 'success'),
      onError: () => Swal.fire('Error', 'Could not submit target.', 'error'),
    })
  }

  const submitForRating = () => {
    router.post(route('spms.ipcr.submit-for-rating', ipcr.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Submitted', 'IPCR submitted for rating.', 'success'),
      onError: () => Swal.fire('Error', 'Could not submit for rating.', 'error'),
    })
  }

  return { generateTargets, submitTarget, submitForRating }
}
```

- [ ] **Step 6: Create the Index Vue page**

```vue
<!-- resources/js/Pages/SPMS/EmployeeIpcrIndex.vue -->
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ ipcrs: Array })

const statusBadgeColor = (status) => ({
  'Draft Target': 'bg-slate-100 text-slate-700',
  'Target Submitted': 'bg-amber-100 text-amber-700',
  'Target Approved': 'bg-blue-100 text-blue-700',
  'Submitted for Rating': 'bg-amber-100 text-amber-700',
  'Rated': 'bg-indigo-100 text-indigo-700',
  'DC Reviewed': 'bg-indigo-100 text-indigo-700',
  'PMT/HR Reviewed': 'bg-indigo-100 text-indigo-700',
  'Director Signed': 'bg-emerald-100 text-emerald-700',
  'Returned': 'bg-rose-100 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-700')
</script>

<template>
  <Head title="My IPCR (SPMS)" />
  <AdminLayout title="My IPCR (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Final Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="ipcr in ipcrs" :key="ipcr.id">
            <td class="px-4 py-3">{{ ipcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeColor(ipcr.status)">
                {{ ipcr.status }}
              </span>
            </td>
            <td class="px-4 py-3">{{ ipcr.final_rating ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.ipcr.show', ipcr.id)" class="text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 7: Create the Show Vue page**

```vue
<!-- resources/js/Pages/SPMS/EmployeeIpcrShow.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsEmployeeIpcr } from '@/Composables/useSpmsEmployeeIpcr'

const props = defineProps({ ipcr: Object })
const ipcr = computed(() => props.ipcr)
const { generateTargets, submitTarget, submitForRating } = useSpmsEmployeeIpcr(ipcr)
</script>

<template>
  <Head title="IPCR Detail (SPMS)" />
  <AdminLayout title="IPCR Detail (SPMS)">
    <div class="mb-4 flex items-center justify-between">
      <div>
        <p class="text-sm text-slate-500">{{ ipcr.fiscal_period?.label }}</p>
        <p class="text-lg font-semibold">{{ ipcr.status }}</p>
      </div>
      <div class="flex gap-2">
        <button v-if="ipcr.status === 'Draft Target'" @click="generateTargets"
          class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50">
          Generate Targets from Load
        </button>
        <button v-if="ipcr.status === 'Draft Target'" @click="submitTarget"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Submit Target
        </button>
        <button v-if="ipcr.status === 'Target Approved'" @click="submitForRating"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Submit for Rating
        </button>
      </div>
    </div>

    <div class="space-y-3">
      <div v-for="target in ipcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ target.function_type }}</p>
        <p class="mt-1">{{ target.success_indicator }}</p>
        <p class="mt-1 text-sm text-slate-500">Weight: {{ target.weight_pct }}%</p>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrControllerTest"`
Expected: PASS (2 tests) — note per this repo's known gotcha, `assertInertia` will fail with a page-not-found error if `EmployeeIpcrIndex.vue` doesn't exist yet even when the backend is correct, so Step 6 must land before this step runs.

- [ ] **Step 9: Build frontend assets**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors referencing `SPMS/EmployeeIpcrIndex` or `EmployeeIpcrShow`.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/SPMS/EmployeeIpcrController.php routes/web.php resources/js/Composables/useSpmsEmployeeIpcr.js resources/js/Pages/SPMS/EmployeeIpcrIndex.vue resources/js/Pages/SPMS/EmployeeIpcrShow.vue tests/Feature/SPMS/EmployeeIpcrControllerTest.php
git commit -m "feat(spms): add employee-facing IPCR controller, routes, and Vue pages"
```

---

### Task 12: Reviewer-facing IPCR controller (DC / PMT / HR / Director), routes, and Vue pages

**Files:**
- Create: `app/Http/Controllers/SPMS/ReviewerIpcrController.php`
- Modify: `routes/web.php`
- Create: `resources/js/Composables/useSpmsReviewerIpcr.js`
- Create: `resources/js/Pages/SPMS/ReviewerIpcrIndex.vue`
- Create: `resources/js/Pages/SPMS/ReviewerIpcrShow.vue`
- Test: `tests/Feature/SPMS/ReviewerIpcrControllerTest.php`

**Interfaces:**
- Consumes: `Ipcr` (Task 4), `IPCRWorkflowService` (Task 8).
- Produces: `ReviewerIpcrController::index()` (queue of IPCRs awaiting the current reviewer's action, filtered by status), `show(Ipcr $ipcr)`, `approveTarget(Ipcr $ipcr)`, `rate(Ipcr $ipcr, Request $request)` (accepts per-target `rating_q`/`rating_e`/`rating_t` array), `reviewAsDivisionChief(Ipcr $ipcr)`, `reviewAsPmtHr(Ipcr $ipcr)`, `finalize(Ipcr $ipcr)`, `returnToSender(Ipcr $ipcr, Request $request)`.

- [ ] **Step 1: Write the failing feature test**

```php
<?php
// tests/Feature/SPMS/ReviewerIpcrControllerTest.php
namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerIpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function reviewer(): User
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::create(['name' => 'spms.ipcr.review', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach($role->id);

        return $reviewer;
    }

    public function test_index_lists_ipcrs_pending_review(): void
    {
        $reviewer = $this->reviewer();
        Ipcr::factory()->create(['status' => Ipcr::STATUS_TARGET_SUBMITTED]);
        Ipcr::factory()->create(['status' => Ipcr::STATUS_DRAFT_TARGET]);

        $response = $this->actingAs($reviewer)->get('/spms/ipcr/review');

        $response->assertInertia(fn ($page) => $page
            ->component('SPMS/ReviewerIpcrIndex')
            ->has('ipcrs', 1)
        );
    }

    public function test_approve_target_transitions_status(): void
    {
        $reviewer = $this->reviewer();
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_TARGET_SUBMITTED]);

        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/approve-target")
            ->assertRedirect();

        $this->assertSame(Ipcr::STATUS_TARGET_APPROVED, $ipcr->fresh()->status);
    }

    public function test_rate_accepts_per_target_scores(): void
    {
        $reviewer = $this->reviewer();
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_SUBMITTED_FOR_RATING]);
        $target = IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/rate", [
            'ratings' => [$target->id => ['rating_q' => 5, 'rating_e' => 4, 'rating_t' => 5]],
        ])->assertRedirect();

        $this->assertSame(Ipcr::STATUS_RATED, $ipcr->fresh()->status);
        $this->assertNotNull($target->fresh()->rating_avg);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ReviewerIpcrControllerTest"`
Expected: FAIL — route/controller not found.

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Http/Controllers/SPMS/ReviewerIpcrController.php
namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\Ipcr;
use App\Services\SPMS\IPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReviewerIpcrController extends Controller
{
    private const PENDING_STATUSES = [
        Ipcr::STATUS_TARGET_SUBMITTED,
        Ipcr::STATUS_SUBMITTED_FOR_RATING,
        Ipcr::STATUS_RATED,
        Ipcr::STATUS_DC_REVIEWED,
        Ipcr::STATUS_PMT_HR_REVIEWED,
    ];

    public function __construct(private readonly IPCRWorkflowService $workflow) {}

    public function index(): Response
    {
        $ipcrs = Ipcr::with(['user', 'fiscalPeriod'])
            ->whereIn('status', self::PENDING_STATUSES)
            ->latest()
            ->get();

        return Inertia::render('SPMS/ReviewerIpcrIndex', ['ipcrs' => $ipcrs]);
    }

    public function show(Ipcr $ipcr): Response
    {
        return Inertia::render('SPMS/ReviewerIpcrShow', [
            'ipcr' => $ipcr->load(['user', 'fiscalPeriod', 'targets.movChecklistItems']),
        ]);
    }

    public function approveTarget(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->approveTarget($ipcr, Auth::user());

        return back()->with('success', 'Target approved.');
    }

    public function rate(Ipcr $ipcr, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ratings' => ['required', 'array'],
            'ratings.*.rating_q' => ['required', 'numeric', 'min:1', 'max:5'],
            'ratings.*.rating_e' => ['required', 'numeric', 'min:1', 'max:5'],
            'ratings.*.rating_t' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);

        foreach ($validated['ratings'] as $targetId => $scores) {
            $ipcr->targets()->whereKey($targetId)->update($scores);
        }

        $this->workflow->rate($ipcr, Auth::user());

        return back()->with('success', 'IPCR rated.');
    }

    public function reviewAsDivisionChief(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->reviewByDivisionChief($ipcr, Auth::user());

        return back()->with('success', 'Reviewed as Division Chief.');
    }

    public function reviewAsPmtHr(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->reviewByPmtHr($ipcr, Auth::user());

        return back()->with('success', 'Reviewed by PMT/HR.');
    }

    public function finalize(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->finalize($ipcr, Auth::user());

        return back()->with('success', 'IPCR signed and finalized.');
    }

    public function returnToSender(Ipcr $ipcr, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->workflow->returnToSender($ipcr, Auth::user(), $validated['reason']);

        return back()->with('success', 'Returned to employee.');
    }
}
```

- [ ] **Step 4: Register routes**

Modify `routes/web.php`:

```php
Route::middleware(['auth', 'permission:spms.ipcr.review'])->prefix('spms/ipcr/review')->name('spms.ipcr.review.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'index'])->name('index');
    Route::get('/{ipcr}', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'show'])->name('show');
    Route::post('/{ipcr}/approve-target', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'approveTarget'])->name('approve-target');
    Route::post('/{ipcr}/rate', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'rate'])->name('rate');
    Route::post('/{ipcr}/review-dc', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'reviewAsDivisionChief'])->name('review-dc');
    Route::post('/{ipcr}/review-pmt-hr', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'reviewAsPmtHr'])->name('review-pmt-hr');
    Route::post('/{ipcr}/finalize', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'finalize'])->name('finalize');
    Route::post('/{ipcr}/return', [\App\Http\Controllers\SPMS\ReviewerIpcrController::class, 'returnToSender'])->name('return');
});
```

- [ ] **Step 5: Create the composable**

```js
// resources/js/Composables/useSpmsReviewerIpcr.js
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Swal from 'sweetalert2'

export function useSpmsReviewerIpcr(ipcr) {
  const ratings = ref({})

  const approveTarget = () => {
    router.post(route('spms.ipcr.review.approve-target', ipcr.value.id), {}, { preserveScroll: true })
  }

  const submitRatings = () => {
    router.post(route('spms.ipcr.review.rate', ipcr.value.id), { ratings: ratings.value }, {
      preserveScroll: true,
      onSuccess: () => Swal.fire('Rated', 'IPCR rating saved.', 'success'),
    })
  }

  const finalizeIpcr = () => {
    router.post(route('spms.ipcr.review.finalize', ipcr.value.id), {}, { preserveScroll: true })
  }

  const returnToSender = (reason) => {
    router.post(route('spms.ipcr.review.return', ipcr.value.id), { reason }, { preserveScroll: true })
  }

  return { ratings, approveTarget, submitRatings, finalizeIpcr, returnToSender }
}
```

- [ ] **Step 6: Create the Index Vue page**

```vue
<!-- resources/js/Pages/SPMS/ReviewerIpcrIndex.vue -->
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ ipcrs: Array })
</script>

<template>
  <Head title="IPCR Review Queue (SPMS)" />
  <AdminLayout title="IPCR Review Queue (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="ipcr in ipcrs" :key="ipcr.id">
            <td class="px-4 py-3">{{ ipcr.user?.name }}</td>
            <td class="px-4 py-3">{{ ipcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">{{ ipcr.status }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.ipcr.review.show', ipcr.id)" class="text-indigo-600 hover:underline">Review</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 7: Create the Show Vue page**

```vue
<!-- resources/js/Pages/SPMS/ReviewerIpcrShow.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsReviewerIpcr } from '@/Composables/useSpmsReviewerIpcr'

const props = defineProps({ ipcr: Object })
const ipcr = computed(() => props.ipcr)
const { ratings, approveTarget, submitRatings, finalizeIpcr } = useSpmsReviewerIpcr(ipcr)
</script>

<template>
  <Head title="Review IPCR (SPMS)" />
  <AdminLayout title="Review IPCR (SPMS)">
    <p class="mb-4 text-sm text-slate-500">{{ ipcr.user?.name }} — {{ ipcr.fiscal_period?.label }} — {{ ipcr.status }}</p>

    <button v-if="ipcr.status === 'Target Submitted'" @click="approveTarget"
      class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium mb-4">
      Approve Target
    </button>

    <div v-if="ipcr.status === 'Submitted for Rating'" class="space-y-3">
      <div v-for="target in ipcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-2">{{ target.success_indicator }}</p>
        <div class="flex gap-2">
          <input v-model.number="ratings[target.id]" type="hidden" />
          <input placeholder="Q" type="number" min="1" max="5" class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="ratings[target.id] = { ...ratings[target.id], rating_q: $event.target.valueAsNumber }" />
          <input placeholder="E" type="number" min="1" max="5" class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="ratings[target.id] = { ...ratings[target.id], rating_e: $event.target.valueAsNumber }" />
          <input placeholder="T" type="number" min="1" max="5" class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="ratings[target.id] = { ...ratings[target.id], rating_t: $event.target.valueAsNumber }" />
        </div>
      </div>
      <button @click="submitRatings" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Save Ratings
      </button>
    </div>

    <button v-if="ipcr.status === 'PMT/HR Reviewed'" @click="finalizeIpcr"
      class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
      Sign as Director (Finalize)
    </button>
  </AdminLayout>
</template>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ReviewerIpcrControllerTest"`
Expected: PASS (3 tests)

- [ ] **Step 9: Build frontend assets**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/SPMS/ReviewerIpcrController.php routes/web.php resources/js/Composables/useSpmsReviewerIpcr.js resources/js/Pages/SPMS/ReviewerIpcrIndex.vue resources/js/Pages/SPMS/ReviewerIpcrShow.vue tests/Feature/SPMS/ReviewerIpcrControllerTest.php
git commit -m "feat(spms): add reviewer-facing IPCR controller, routes, and Vue pages"
```

---

### Task 13: Admin config controller (weight profiles, fiscal periods, MOV document types), routes, Vue pages

**Files:**
- Create: `app/Http/Controllers/SPMS/AdminConfigController.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/SPMS/AdminConfigIndex.vue`
- Test: `tests/Feature/SPMS/AdminConfigControllerTest.php`

**Interfaces:**
- Consumes: `WeightProfile` (Task 3), `FiscalPeriod` (Task 1).
- Produces: `AdminConfigController::index()`, `storeWeightProfile(Request $request)`, `storeFiscalPeriod(Request $request)`.

- [ ] **Step 1: Write the failing feature test**

```php
<?php
// tests/Feature/SPMS/AdminConfigControllerTest.php
namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'HR']);
        $permission = Permission::create(['name' => 'spms.admin.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_stores_a_weight_profile(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/spms/admin/weight-profiles', [
            'level' => 'ipcr',
            'division_id' => null,
            'fiscal_year' => 2026,
            'strategic_pct' => 30,
            'core_pct' => 50,
            'support_pct' => 20,
        ])->assertRedirect();

        $this->assertDatabaseHas('spms_weight_profiles', ['level' => 'ipcr', 'fiscal_year' => 2026]);
    }

    public function test_rejects_weights_that_do_not_sum_to_100(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/spms/admin/weight-profiles', [
            'level' => 'ipcr',
            'fiscal_year' => 2026,
            'strategic_pct' => 30,
            'core_pct' => 50,
            'support_pct' => 30,
        ])->assertSessionHasErrors();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AdminConfigControllerTest"`
Expected: FAIL — route/controller not found.

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Http/Controllers/SPMS/AdminConfigController.php
namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\WeightProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AdminConfigController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SPMS/AdminConfigIndex', [
            'weightProfiles' => WeightProfile::with('division')->latest()->get(),
            'fiscalPeriods' => FiscalPeriod::latest()->get(),
        ]);
    }

    public function storeWeightProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'in:opcr,dpcr,ipcr'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'fiscal_year' => ['required', 'integer', 'min:2000'],
            'strategic_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'core_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'support_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [], [], [], function (Validator $validator) use ($request) {
            //
        });

        $validator = validator($validated);
        $validator->after(function ($validator) use ($validated) {
            $sum = $validated['strategic_pct'] + $validated['core_pct'] + $validated['support_pct'];
            if (abs($sum - 100) > 0.01) {
                $validator->errors()->add('strategic_pct', 'Strategic + Core + Support must sum to 100.');
            }
        });
        $validator->validate();

        WeightProfile::create($validated);

        return back()->with('success', 'Weight profile saved.');
    }

    public function storeFiscalPeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cadence' => ['required', 'in:quarter,semester,annual'],
            'fiscal_year' => ['required', 'integer', 'min:2000'],
            'label' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'parent_period_id' => ['nullable', 'exists:spms_fiscal_periods,id'],
            'school_year_id' => ['nullable', 'exists:school_years,id'],
        ]);

        FiscalPeriod::create($validated);

        return back()->with('success', 'Fiscal period saved.');
    }
}
```

- [ ] **Step 4: Simplify the validation (fix the malformed Step 3 draft) and register routes**

Replace `storeWeightProfile()`'s validation block with the corrected version below (the closure-based `$request->validate()` overload used above accepts 3 params, not 5 — this was a drafting error to fix before running):

```php
public function storeWeightProfile(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'level' => ['required', 'in:opcr,dpcr,ipcr'],
        'division_id' => ['nullable', 'exists:divisions,id'],
        'fiscal_year' => ['required', 'integer', 'min:2000'],
        'strategic_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        'core_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        'support_pct' => ['required', 'numeric', 'min:0', 'max:100'],
    ]);

    $sum = $validated['strategic_pct'] + $validated['core_pct'] + $validated['support_pct'];
    if (abs($sum - 100) > 0.01) {
        return back()->withErrors(['strategic_pct' => 'Strategic + Core + Support must sum to 100.']);
    }

    WeightProfile::create($validated);

    return back()->with('success', 'Weight profile saved.');
}
```

Modify `routes/web.php`:

```php
Route::middleware(['auth', 'permission:spms.admin.manage'])->prefix('spms/admin')->name('spms.admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SPMS\AdminConfigController::class, 'index'])->name('index');
    Route::post('/weight-profiles', [\App\Http\Controllers\SPMS\AdminConfigController::class, 'storeWeightProfile'])->name('weight-profiles.store');
    Route::post('/fiscal-periods', [\App\Http\Controllers\SPMS\AdminConfigController::class, 'storeFiscalPeriod'])->name('fiscal-periods.store');
});
```

- [ ] **Step 5: Create the Vue page**

```vue
<!-- resources/js/Pages/SPMS/AdminConfigIndex.vue -->
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ weightProfiles: Array, fiscalPeriods: Array })

const weightForm = useForm({
  level: 'ipcr', division_id: null, fiscal_year: 2026,
  strategic_pct: 30, core_pct: 50, support_pct: 20,
})

const submitWeightProfile = () => {
  weightForm.post(route('spms.admin.weight-profiles.store'), { preserveScroll: true })
}
</script>

<template>
  <Head title="SPMS Admin Config" />
  <AdminLayout title="SPMS Admin Config">
    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-6">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">New Weight Profile</h2>
      <form @submit.prevent="submitWeightProfile" class="grid grid-cols-3 gap-3">
        <input v-model.number="weightForm.fiscal_year" type="number" placeholder="Fiscal Year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model.number="weightForm.strategic_pct" type="number" placeholder="Strategic %"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model.number="weightForm.core_pct" type="number" placeholder="Core %"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model.number="weightForm.support_pct" type="number" placeholder="Support %"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Save
        </button>
      </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Weight Profiles</h2>
      <table class="min-w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <tr v-for="profile in weightProfiles" :key="profile.id">
            <td class="px-2 py-2">{{ profile.level }}</td>
            <td class="px-2 py-2">{{ profile.fiscal_year }}</td>
            <td class="px-2 py-2">{{ profile.strategic_pct }}/{{ profile.core_pct }}/{{ profile.support_pct }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AdminConfigControllerTest"`
Expected: PASS (2 tests)

- [ ] **Step 7: Build frontend assets**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/SPMS/AdminConfigController.php routes/web.php resources/js/Pages/SPMS/AdminConfigIndex.vue tests/Feature/SPMS/AdminConfigControllerTest.php
git commit -m "feat(spms): add admin config controller for weight profiles and fiscal periods"
```

---

### Task 14: Sidebar navigation entry

**Files:**
- Modify: `resources/js/Layouts/navigation.js`

**Interfaces:**
- Consumes: existing navigation array structure (read the file first to confirm the exact existing "Performance Mngmt" section object shape before editing, since the fork's research paraphrased rather than quoted the full object).

- [ ] **Step 1: Read the existing navigation file**

Read `resources/js/Layouts/navigation.js` in full and locate the existing Performance Management section object.

- [ ] **Step 2: Add the new SPMS section**

Add a new top-level object to the array (immediately after the existing Performance Management section, matching its exact key structure — `label`/`icon`/`roles`/`children` with each child having `label`/`routeName`/`href`/`icon`/`permissions`):

```js
{
  label: 'Performance Management (SPMS)',
  icon: ChartBarIcon,
  roles: ['Administrator', 'Faculty', 'Staff', 'DivisionChief', 'HR', 'PMT'],
  children: [
    {
      label: 'My IPCR',
      routeName: 'spms.ipcr.index',
      href: route('spms.ipcr.index'),
      icon: DocumentTextIcon,
      permissions: ['spms.ipcr.manage'],
    },
    {
      label: 'IPCR Review Queue',
      routeName: 'spms.ipcr.review.index',
      href: route('spms.ipcr.review.index'),
      icon: ClipboardDocumentCheckIcon,
      permissions: ['spms.ipcr.review'],
    },
    {
      label: 'SPMS Admin Config',
      routeName: 'spms.admin.index',
      href: route('spms.admin.index'),
      icon: Cog6ToothIcon,
      permissions: ['spms.admin.manage'],
    },
  ],
},
```

Confirm `ChartBarIcon`, `DocumentTextIcon`, `ClipboardDocumentCheckIcon`, `Cog6ToothIcon` are imported from `@heroicons/vue/24/outline` at the top of the file (add the import line if any are missing).

- [ ] **Step 3: Build frontend assets and visually confirm**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`

Then start the dev stack (`docker compose up -d` from `/Users/junlou/bugsaymis-docker` if not already running) and log in as a user with `Administrator` role at `http://localhost:8080` to visually confirm the "Performance Management (SPMS)" section renders in the sidebar alongside the existing "Performance Mngmt" section, with three children linking to the pages built in Tasks 11–13.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/navigation.js
git commit -m "feat(spms): add SPMS sidebar navigation section"
```

---

### Task 15: Full-suite regression check

**Files:** none (verification only)

- [ ] **Step 1: Run the full SPMS test suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SPMS"`
Expected: all SPMS tests from Tasks 1–13 pass together (not just individually — this catches cross-task interference, e.g. factory state bleeding between tests).

- [ ] **Step 2: Run the full existing regression suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: zero new failures outside the SPMS suite — confirms zero interaction with the live v1 IPCR module (per this repo's memory: local full-suite runs can stall on OTel retries; if that happens, re-run rather than treating it as a real failure).

- [ ] **Step 3: Confirm zero shared schema with v1**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"echo \\App\\Illuminate\\Support\\Facades\\Schema::hasTable('spms_ipcrs') ? 'spms_ipcrs exists' : 'MISSING'; echo PHP_EOL; echo \\Illuminate\\Support\\Facades\\Schema::hasColumn('employee_ipcrs', 'spms_ipcr_id') ? 'LEAK: v1 table was modified' : 'clean: v1 untouched';\""`
Expected: `spms_ipcrs exists` and `clean: v1 untouched`.

- [ ] **Step 4: Manual smoke test of the full Employee → Reviewer → Director flow**

Using a dev-seeded Faculty user and an HR/DivisionChief user (via the `run` skill or manual browser session at `http://localhost:8080`):
1. As Faculty: navigate to "My IPCR" → create/view a draft SPMS IPCR → "Generate Targets from Load" → confirm target rows appear → "Submit Target".
2. As reviewer: navigate to "IPCR Review Queue" → open the submitted IPCR → "Approve Target".
3. As Faculty: "Submit for Rating".
4. As reviewer: enter Q/E/T scores per target → "Save Ratings" → confirm status becomes "Rated" → review as DC → review as PMT/HR → "Sign as Director (Finalize)" → confirm `final_rating`/`final_adjectival` are populated and status is terminal (no further action buttons render).

Document the outcome (pass/fail per step) before considering Phase 1 complete — do not report success without having actually walked this path, per this repo's verification requirements.

- [ ] **Step 5: Commit any fixes found during smoke testing**

If Steps 1–4 surface bugs, fix them with their own focused commits (not folded into earlier tasks' commits) before marking Phase 1 done.

---

## Self-Review Notes

- **Spec coverage:** All Phase 1 items from the spec (`spms_fiscal_periods`, `spms_outcomes`, `spms_performance_indicators`, `spms_weight_profiles`, `spms_ipcrs`, `spms_ipcr_targets`, `spms_ipcr_mov_checklist`, full workflow, load-driven target generation, MOV checklist, `dpcr_id` nullable) are covered by Tasks 1–13. Sidebar nav (implied by spec's "Module boundary & naming" section) is Task 14. Regression/zero-v1-interaction verification (spec's core risk) is Task 15.
- **Known unresolved detail carried into Task 9**: `LoadAssignment`'s exact field names weren't confirmed by the research fork — Task 9 Step 1 requires confirming them live before Step 4's code can be trusted verbatim; this is flagged inline rather than guessed.
- **Type consistency check**: `Ipcr::STATUS_*` constants (Task 4) are used identically in `IPCRWorkflowService::TRANSITIONS` (Task 8), `ReviewerIpcrController::PENDING_STATUSES` (Task 12), and both Vue pages' `statusBadgeColor()`/template `v-if` checks (Tasks 11–12) — verified matching strings throughout (`'Draft Target'`, `'Target Submitted'`, `'Target Approved'`, `'Submitted for Rating'`, `'Rated'`, `'DC Reviewed'`, `'PMT/HR Reviewed'`, `'Director Signed'`, `'Returned'`).
- **Fixed a drafting error in Task 13** during self-review: the initial `storeWeightProfile()` draft in Step 3 called `$request->validate()` with a malformed extra closure argument that isn't part of that method's real signature — Step 4 replaces it with a corrected version before the task is run, rather than leaving the error for the implementer to discover.
