# Agency Org Outcome Hierarchy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `agency_org_outcomes`' fake "one outcome, multiple sub-outcomes via duplicate rows" pattern into a real parent/child hierarchy, and fix the 5 IPCR Show pages that currently group by fragile string-matching and truncate sub-outcome text to 4 characters.

**Architecture:** One additive column (`parent_id`, self-referencing FK) on the existing `agency_org_outcomes` table — no new table, no repointing of the existing `performance_indicators.agency_outcome_id` FK. A one-time Artisan command backfills the 4 duplicate-text production rows into a real parent+children. Backend controllers gain parent/child awareness (inherit fields, delete guards, tree-shaped index responses). Frontend gets one new pure grouping function shared by all 5 IPCR Show pages (replacing 5 copies of the same buggy computed) and a tree UI on the existing outcomes admin page.

**Tech Stack:** Laravel 12 / PHP 8.4, Eloquent, PHPUnit (`RefreshDatabase`, `assertInertia`), Vue 3 `<script setup>`, Inertia.js 2, plain-function unit tests via Node's built-in test runner (`node --test`, project convention — see `tests/js/watUtils.test.mjs`).

**Spec:** `docs/superpowers/specs/2026-08-18-agency-outcome-hierarchy-design.md`

## Global Constraints

- Additive-only migrations, single deploy, no expand/contract split (spec Rollout section).
- `performance_indicators.agency_outcome_id`'s existing FK *values* never change for any of the 25 production rows — only the constraint's delete behavior changes.
- Controllers: thin, `Inertia::render`, `back()->with('success', ...)` on mutation, `$this->authorize`/route middleware for permission (`ipcr.view` group, already in place — no new permission).
- Vue: `<script setup>`, no TypeScript, Tailwind classes matching CLAUDE.md conventions (`rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500` for inputs).
- No `git add -A`/`.`; stage files by name. No `--no-verify`. Commit after each task.
- Run PHP tests via `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test <path>"` (from `/Users/junlou/bugsaymis-docker`, per CLAUDE.md Docker service name = `php`).
- Run JS tests via `npm run test:js` (root of `bugsaymis`, no Docker needed — plain Node).
- After Task 12, run `npm run build` (or the project's existing dev-server workflow) and manually click through the admin Outcomes page and one IPCR Show/print view in a browser before calling the frontend work done — per CLAUDE.md, type-checking/tests don't verify UI feature correctness.

---

### Task 1: `parent_id` column on `agency_org_outcomes` + model relations

**Files:**
- Create: `database/migrations/2026_08_18_100000_add_parent_id_to_agency_org_outcomes_table.php`
- Modify: `app/Models/AgencyOutcome.php`
- Test: `tests/Unit/AgencyOutcomeHierarchyTest.php`

**Interfaces:**
- Produces: `AgencyOutcome::parent()` (belongsTo), `AgencyOutcome::children()` (hasMany), `AgencyOutcome::scopeTopLevel()`, `AgencyOutcome::performanceIndicators()` (hasMany `PerformanceIndicator`, needed by Task 4's delete guard). `parent_id` added to `$fillable`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_outcome_belongs_to_its_parent(): void
    {
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create([
            'outcome' => 'A. STEM Secondary Education',
            'sub_outcome' => 'A.1',
            'function_type' => 'Strategic Functions',
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_top_level_scope_excludes_children(): void
    {
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create([
            'outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id,
        ]);
        $standalone = AgencyOutcome::create(['outcome' => 'B. Standalone', 'function_type' => 'Core Functions']);

        $topLevelIds = AgencyOutcome::topLevel()->pluck('id')->sort()->values()->toArray();

        $this->assertEquals([$parent->id, $standalone->id], collect($topLevelIds)->sort()->values()->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Unit/AgencyOutcomeHierarchyTest.php"` (from `/Users/junlou/bugsaymis-docker`)
Expected: FAIL — `parent_id` column / `parent()`/`children()`/`topLevel()` don't exist yet.

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
        Schema::table('agency_org_outcomes', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('fiscal_year')
                ->constrained('agency_org_outcomes')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('agency_org_outcomes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
```

- [ ] **Step 4: Run the migration in dev**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_18_100000_add_parent_id_to_agency_org_outcomes_table.php"`
Expected: `Migrated: ... add_parent_id_to_agency_org_outcomes_table`

- [ ] **Step 5: Update the model**

Edit `app/Models/AgencyOutcome.php`, replacing the whole file:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgencyOutcome extends Model
{
    use HasFactory;

    protected $table = 'agency_org_outcomes';

    protected $fillable = [
        'outcome',
        'sub_outcome',
        'function_type', // ✅ new field
        'fiscal_year',
        'parent_id',
    ];

    // NULL fiscal_year = applies to all years (legacy rows)
    public function scopeForFiscalYear($query, ?int $year)
    {
        if (! $year) {
            return $query;
        }

        return $query->where(function ($q) use ($year) {
            $q->whereNull('fiscal_year')->orWhere('fiscal_year', $year);
        });
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function performanceIndicators()
    {
        return $this->hasMany(PerformanceIndicator::class, 'agency_outcome_id');
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Unit/AgencyOutcomeHierarchyTest.php"`
Expected: PASS (2/2)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_18_100000_add_parent_id_to_agency_org_outcomes_table.php app/Models/AgencyOutcome.php tests/Unit/AgencyOutcomeHierarchyTest.php
git commit -m "feat(ipcr): add self-referencing parent_id to agency_org_outcomes"
```

---

### Task 2: Restrict-delete on `performance_indicators.agency_outcome_id`

**Files:**
- Create: `database/migrations/2026_08_18_100100_restrict_delete_on_performance_indicators_agency_outcome_id.php`
- Test: `tests/Feature/AgencyOutcomeDeleteBehaviorTest.php`

**Interfaces:**
- Consumes: `AgencyOutcome` (Task 1), `PerformanceIndicator` (existing, `app/Models/PerformanceIndicator.php`).
- Produces: DB-level guarantee that deleting an `AgencyOutcome` still referenced by a `PerformanceIndicator` throws instead of cascading — Task 4's app-level guard builds a clean error message on top of this, this task is the safety net underneath it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeDeleteBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_restricts_deleting_an_outcome_still_referenced_by_a_performance_indicator(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        PerformanceIndicator::create([
            'agency_outcome_id' => $outcome->id,
            'description' => 'Test indicator',
            'target' => '100%',
        ]);

        $this->expectException(QueryException::class);

        $outcome->delete();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/AgencyOutcomeDeleteBehaviorTest.php"`
Expected: FAIL — today's `cascade` behavior lets the delete succeed silently (no exception thrown), so `expectException` fails.

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
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropForeign(['agency_outcome_id']);
        });

        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->foreign('agency_outcome_id')
                ->references('id')->on('agency_org_outcomes')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropForeign(['agency_outcome_id']);
        });

        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->foreign('agency_outcome_id')
                ->references('id')->on('agency_org_outcomes')
                ->onDelete('cascade');
        });
    }
};
```

- [ ] **Step 4: Run the migration in dev**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_18_100100_restrict_delete_on_performance_indicators_agency_outcome_id.php"`
Expected: `Migrated: ... restrict_delete_on_performance_indicators_agency_outcome_id`

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/AgencyOutcomeDeleteBehaviorTest.php"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_18_100100_restrict_delete_on_performance_indicators_agency_outcome_id.php tests/Feature/AgencyOutcomeDeleteBehaviorTest.php
git commit -m "fix(ipcr): stop deleting an outcome from silently cascade-deleting its performance indicators"
```

---

### Task 3: One-time backfill command

**Files:**
- Create: `app/Console/Commands/BackfillAgencyOutcomeHierarchy.php`
- Test: `tests/Feature/BackfillAgencyOutcomeHierarchyTest.php`

**Interfaces:**
- Consumes: `AgencyOutcome::parent_id`, `::children()` (Task 1).
- Produces: `php artisan agency-outcomes:backfill-hierarchy` — idempotent-per-run-only (see Step 5 note), not invoked automatically by any deploy step.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillAgencyOutcomeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_groups_duplicate_outcome_text_rows_under_a_new_parent(): void
    {
        $rows = collect(['A.1', 'A.2', 'A.3', 'A.4'])->map(fn ($sub) => AgencyOutcome::create([
            'outcome' => 'A. STEM Secondary Education on Scholarship Basis Program',
            'sub_outcome' => $sub,
            'function_type' => 'Strategic Functions',
        ]));

        $this->artisan('agency-outcomes:backfill-hierarchy')->assertSuccessful();

        $parent = AgencyOutcome::whereNull('parent_id')
            ->where('outcome', 'A. STEM Secondary Education on Scholarship Basis Program')
            ->first();

        $this->assertNotNull($parent);
        $this->assertNull($parent->sub_outcome);

        foreach ($rows as $row) {
            $this->assertEquals($parent->id, $row->fresh()->parent_id);
        }
    }

    public function test_backfill_leaves_single_row_outcomes_untouched(): void
    {
        $standalone = AgencyOutcome::create([
            'outcome' => 'Core Functions',
            'sub_outcome' => 'Core Functions',
            'function_type' => 'Core Functions',
        ]);

        $this->artisan('agency-outcomes:backfill-hierarchy')->assertSuccessful();

        $this->assertNull($standalone->fresh()->parent_id);
        $this->assertEquals(1, AgencyOutcome::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/BackfillAgencyOutcomeHierarchyTest.php"`
Expected: FAIL — the `agency-outcomes:backfill-hierarchy` command doesn't exist yet.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\AgencyOutcome;
use Illuminate\Console\Command;

class BackfillAgencyOutcomeHierarchy extends Command
{
    protected $signature = 'agency-outcomes:backfill-hierarchy';

    protected $description = 'Groups agency_org_outcomes rows sharing identical outcome text into a real parent/child hierarchy via parent_id.';

    public function handle(): int
    {
        $groups = AgencyOutcome::whereNull('parent_id')->get()->groupBy('outcome');

        $groupsCreated = 0;
        $rowsLinked = 0;
        $flaggedForReview = [];

        foreach ($groups as $outcomeText => $rows) {
            if ($rows->count() < 2) {
                $row = $rows->first();
                if ($row->outcome === $row->function_type) {
                    $flaggedForReview[] = "id={$row->id} outcome=\"{$row->outcome}\"";
                }
                continue;
            }

            $first = $rows->first();
            $parent = AgencyOutcome::create([
                'outcome' => $outcomeText,
                'sub_outcome' => null,
                'function_type' => $first->function_type,
                'fiscal_year' => $first->fiscal_year,
            ]);
            $groupsCreated++;

            foreach ($rows as $row) {
                $row->parent_id = $parent->id;
                $row->save();
                $rowsLinked++;
            }
        }

        $this->info("Created {$groupsCreated} parent outcome(s), linked {$rowsLinked} existing row(s) as children.");

        if ($flaggedForReview) {
            $this->warn('Single-row outcomes that look like placeholders (outcome text equals function_type) — review manually:');
            foreach ($flaggedForReview as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
```

Note for whoever runs this in production: it is safe to run only once per un-backfilled dataset — running it a second time would treat the newly created parent rows (which have `parent_id === null`) as un-grouped candidates again, but since each parent's `outcome` text is unique (no two groups share text after the first run), a second run creates zero new groups and links zero rows. It is not destructive either way, but should still only be run once as documented in the rollout steps.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/BackfillAgencyOutcomeHierarchyTest.php"`
Expected: PASS (2/2)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillAgencyOutcomeHierarchy.php tests/Feature/BackfillAgencyOutcomeHierarchyTest.php
git commit -m "feat(ipcr): add one-time backfill command for agency outcome hierarchy"
```

---

### Task 4: `AgencyOutcomeController` — parent/child CRUD + delete guards + tree index

**Files:**
- Modify: `app/Http/Controllers/AgencyOutcomeController.php`
- Test: `tests/Feature/AgencyOutcomeControllerTest.php`

**Interfaces:**
- Consumes: `AgencyOutcome::topLevel()`, `::children()`, `::parent()`, `::performanceIndicators()` (Task 1).
- Produces: `index()` Inertia prop `outcomes` becomes an array of top-level outcomes, each with a `children` array (was previously a flat array). `store`/`update` accept an optional `parent_id`; when present, `function_type`/`fiscal_year`/`outcome` are silently overwritten server-side from the parent regardless of what's submitted. `destroy` returns a 302-with-`withErrors` (not a raw 500) when blocked.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_creating_a_sub_outcome_inherits_the_parents_function_type_and_fiscal_year(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create([
            'outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026,
        ]);

        $response = $this->actingAs($admin)->post(route('outcome.store'), [
            'parent_id' => $parent->id,
            'sub_outcome' => 'A.1',
            'outcome' => 'ignored, should be overwritten',
            'function_type' => 'Core Functions',
            'fiscal_year' => 1999,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('agency_org_outcomes', [
            'parent_id' => $parent->id,
            'sub_outcome' => 'A.1',
            'outcome' => 'A. STEM',
            'function_type' => 'Strategic Functions',
            'fiscal_year' => 2026,
        ]);
    }

    public function test_cannot_delete_an_outcome_that_still_has_children(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $parent->id));

        $response->assertSessionHasErrors('agencyOutcome');
        $this->assertDatabaseHas('agency_org_outcomes', ['id' => $parent->id]);
    }

    public function test_cannot_delete_an_outcome_referenced_by_a_performance_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Indicator', 'target' => '100%']);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $outcome->id));

        $response->assertSessionHasErrors('agencyOutcome');
        $this->assertDatabaseHas('agency_org_outcomes', ['id' => $outcome->id]);
    }

    public function test_can_delete_a_childless_unreferenced_outcome(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $outcome->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('agency_org_outcomes', ['id' => $outcome->id]);
    }

    public function test_index_returns_top_level_outcomes_with_nested_children(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->get(route('outcome.index', ['fiscal_year' => 'all']));

        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/AgencyOrgOutcome')
            ->has('outcomes', 1)
            ->where('outcomes.0.id', $parent->id)
            ->has('outcomes.0.children', 1)
            ->where('outcomes.0.children.0.sub_outcome', 'A.1')
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/AgencyOutcomeControllerTest.php"`
Expected: FAIL — controller doesn't yet support `parent_id`, doesn't guard deletes, `index()` still returns a flat list.

- [ ] **Step 3: Rewrite the controller**

Replace the full contents of `app/Http/Controllers/AgencyOutcomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgencyOutcomeController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        $outcomes = AgencyOutcome::query()
            ->topLevel()
            ->with('children')
            ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
            ->latest()
            ->get();

        return Inertia::render('PerformanceManagement/AgencyOrgOutcome', [
            'outcomes'           => $outcomes,
            'fiscalYears'        => IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'outcome' => 'required_without:parent_id|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
            'function_type' => 'required_without:parent_id|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
            'parent_id' => 'nullable|exists:agency_org_outcomes,id',
        ]);

        $data = $this->inheritFromParentIfPresent($data);

        $outcome = AgencyOutcome::create($data);

        return redirect()->back()->with('outcome', $outcome);
    }

    public function update(Request $request, AgencyOutcome $agencyOutcome)
    {
        $data = $request->validate([
            'outcome' => 'required_without:parent_id|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
            'function_type' => 'required_without:parent_id|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
            'parent_id' => 'nullable|exists:agency_org_outcomes,id',
        ]);

        $data = $this->inheritFromParentIfPresent($data);

        $agencyOutcome->update($data);

        return redirect()->back()->with('outcome', $agencyOutcome);
    }

    public function destroy(AgencyOutcome $agencyOutcome)
    {
        if ($agencyOutcome->children()->exists()) {
            return back()->withErrors(['agencyOutcome' => 'Delete its sub-outcomes first before deleting this outcome.']);
        }

        if ($agencyOutcome->performanceIndicators()->exists()) {
            return back()->withErrors(['agencyOutcome' => 'This outcome is still referenced by one or more performance indicators.']);
        }

        $agencyOutcome->delete();

        return redirect()->back();
    }

    private function inheritFromParentIfPresent(array $data): array
    {
        if (empty($data['parent_id'])) {
            return $data;
        }

        $parent = AgencyOutcome::findOrFail($data['parent_id']);
        $data['outcome'] = $parent->outcome;
        $data['function_type'] = $parent->function_type;
        $data['fiscal_year'] = $parent->fiscal_year;

        return $data;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/AgencyOutcomeControllerTest.php"`
Expected: PASS (5/5)

- [ ] **Step 5: Run the full existing test suite for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostSubStrategyControllerTest.php tests/Feature/DostStrategicPlanControllerTest.php"`
Expected: PASS — confirms the DOST module (which reads `AgencyOutcome::all()` for its linking dropdown) still works unmodified against the changed model.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AgencyOutcomeController.php tests/Feature/AgencyOutcomeControllerTest.php
git commit -m "feat(ipcr): parent/child CRUD, delete guards, and tree index for AgencyOutcomeController"
```

---

### Task 5: `PerformanceIndicatorController` — eager-load `parent`, tree-shaped `outcomes` prop

**Files:**
- Modify: `app/Http/Controllers/PerformanceIndicatorController.php`
- Test: `tests/Feature/PerformanceIndicatorControllerOutcomeTreeTest.php`

**Interfaces:**
- Consumes: `AgencyOutcome::topLevel()`, `::children()` (Task 1).
- Produces: `index()` Inertia prop `outcomes` becomes the same tree shape as Task 4's `AgencyOutcomeController@index`; `indicators[].agency_outcome.parent` is present when the indicator's outcome is a child.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorControllerOutcomeTreeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_index_sends_outcomes_as_a_tree_and_indicator_agency_outcome_includes_its_parent(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);
        PerformanceIndicator::create(['agency_outcome_id' => $child->id, 'description' => 'Indicator 1', 'target' => '100%']);

        $response = $this->actingAs($admin)->get(route('performanceindicator.index', ['fiscal_year' => 'all']));

        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/PerformanceIndicators')
            ->has('outcomes', 1)
            ->has('outcomes.0.children', 1)
            ->where('indicators.0.agency_outcome.parent.outcome', 'A. STEM')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/PerformanceIndicatorControllerOutcomeTreeTest.php"`
Expected: FAIL — `outcomes` is still flat, `agency_outcome.parent` isn't eager-loaded.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/PerformanceIndicatorController.php`, replace the `index()` method:

```php
    public function index(Request $request)
    {
        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        return Inertia::render('PerformanceManagement/PerformanceIndicators', [
            'indicators' => PerformanceIndicator::with(['agencyOutcome.parent', 'divisions'])
                ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
                ->latest()->get(),
            'outcomes' => AgencyOutcome::query()
                ->topLevel()
                ->with('children')
                ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
                ->get(),
            'divisions' => Division::all(),
            'fiscalYears'        => IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/PerformanceIndicatorControllerOutcomeTreeTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PerformanceIndicatorController.php tests/Feature/PerformanceIndicatorControllerOutcomeTreeTest.php
git commit -m "feat(ipcr): tree-shaped outcomes prop and parent eager-load in PerformanceIndicatorController"
```

---

### Task 6: `IPCRRatingPeriodController::copyFramework` — remap `parent_id` on FY rollover

**Files:**
- Modify: `app/Http/Controllers/IPCRRatingPeriodController.php:186-221`
- Test: `tests/Feature/IPCRRatingPeriodCopyFrameworkParentRemapTest.php`

**Interfaces:**
- Consumes: `AgencyOutcome::parent_id` (Task 1), the existing `$outcomeMap` (old id → new id) built in this method's first loop.
- Produces: cloned outcomes in the target fiscal year have `parent_id` pointing at the *target year's* parent clone, not the source year's parent.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRRatingPeriodCopyFrameworkParentRemapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_copying_a_fiscal_year_remaps_parent_id_to_the_new_years_clone(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026]);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026, 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->post(route('ipcr-rating-periods.copyFramework'), [
            'source_year' => 2026,
            'target_year' => 2027,
        ]);

        $response->assertRedirect();

        $newParent = AgencyOutcome::where('fiscal_year', 2027)->whereNull('parent_id')->firstOrFail();
        $newChild = AgencyOutcome::where('fiscal_year', 2027)->where('sub_outcome', 'A.1')->firstOrFail();

        $this->assertEquals($newParent->id, $newChild->parent_id);
        $this->assertNotEquals($parent->id, $newChild->parent_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRRatingPeriodCopyFrameworkParentRemapTest.php"`
Expected: FAIL — the clone's `parent_id` currently still points at the *source* year's parent id (copied verbatim by `replicate()`), so `assertNotEquals($parent->id, $newChild->parent_id)` fails.

- [ ] **Step 3: Add the remap pass**

In `app/Http/Controllers/IPCRRatingPeriodController.php`, inside the `DB::transaction` closure of `copyFramework()` (around line 186-194), add a second loop immediately after the existing outcome-cloning loop and before the `$indicators = ...` line:

```php
            $outcomes = AgencyOutcome::forFiscalYear($source)->get();
            $outcomeMap = [];   // old id => new id
            foreach ($outcomes as $outcome) {
                $clone = $outcome->replicate(['fiscal_year']);
                $clone->fiscal_year = $target;
                $clone->save();
                $outcomeMap[$outcome->id] = $clone->id;
            }

            foreach ($outcomes as $outcome) {
                if ($outcome->parent_id === null) {
                    continue;
                }

                $newId = $outcomeMap[$outcome->id];
                $newParentId = $outcomeMap[$outcome->parent_id] ?? null;
                AgencyOutcome::whereKey($newId)->update(['parent_id' => $newParentId]);
            }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRRatingPeriodCopyFrameworkParentRemapTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRRatingPeriodController.php tests/Feature/IPCRRatingPeriodCopyFrameworkParentRemapTest.php
git commit -m "fix(ipcr): remap agency_outcome parent_id to the new fiscal year during framework copy"
```

---

### Task 7: Eager-load `agencyOutcome.parent` in the 5 IPCR show controllers

**Files:**
- Modify: `app/Http/Controllers/EmployeeIPCRController.php:247`
- Modify: `app/Http/Controllers/HRIPCRController.php:57`
- Modify: `app/Http/Controllers/DivisionChiefIPCRController.php:201`
- Modify: `app/Http/Controllers/PMTIPCRController.php:53` (only the `show()` method's `with([...])` — leave `index()`'s at line 29 alone, it doesn't feed outcome grouping)
- Modify: `app/Http/Controllers/AdminIPCRController.php:58`
- Test: `tests/Feature/IPCRShowOutcomeParentEagerLoadTest.php`

**Interfaces:**
- Consumes: Task 1's `AgencyOutcome::parent()`.
- Produces: each show controller's `plans.performance_indicator.agencyOutcome` relation now includes `.parent`, which Task 9's grouping util reads via `plan.performance_indicator?.agency_outcome?.parent?.outcome`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\EmployeeIPCR;
use App\Models\IPCRRatingPeriod;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRShowOutcomeParentEagerLoadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function ipcrWithChildOutcomePlan(): EmployeeIPCR
    {
        $period = IPCRRatingPeriod::create(['label' => 'FY 2026', 'year' => 2026]);
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $child->id, 'description' => 'Indicator 1', 'target' => '100%']);
        $plan = WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id]);

        $employee = User::factory()->create();
        $ipcr = EmployeeIPCR::create([
            'user_id' => $employee->id,
            'ipcr_rating_period_id' => $period->id,
            'title' => 'Test IPCR',
            'status' => 'Submitted for Rating',
        ]);
        $ipcr->plans()->attach($plan->id);

        return $ipcr;
    }

    public function test_employee_ipcr_show_exposes_agency_outcome_parent(): void
    {
        $ipcr = $this->ipcrWithChildOutcomePlan();

        $response = $this->actingAs($ipcr->user)->get(route('employee-ipcr.show', $ipcr->id));

        $response->assertInertia(fn ($page) => $page
            ->where('ipcr.plans.0.performance_indicator.agency_outcome.parent.outcome', 'A. STEM')
        );
    }

    public function test_admin_ipcr_show_exposes_agency_outcome_parent(): void
    {
        $admin = $this->admin();
        $ipcr = $this->ipcrWithChildOutcomePlan();

        $response = $this->actingAs($admin)->get(route('admin-ipcr.show', $ipcr->id));

        $response->assertInertia(fn ($page) => $page
            ->where('ipcr.plans.0.performance_indicator.agency_outcome.parent.outcome', 'A. STEM')
        );
    }
}
```

Before writing this test, confirm the exact route names (`employee-ipcr.show`, `admin-ipcr.show`) and the `EmployeeIPCR`/`WorkDistributionPlan`/plan-attach relationship names match the codebase — run:

```
grep -n "employee-ipcr.show\|admin-ipcr.show" routes/web.php
grep -n "public function plans\|belongsToMany.*EmployeeIPCR\|belongsToMany.*WorkDistributionPlan" app/Models/EmployeeIPCR.php app/Models/WorkDistributionPlan.php
```

and adjust the test's route names / attach call to match what's actually there before running it — the plan's earlier research (`WorkDistributionPlan::employeeIpcrs()` via `employee_ipcrs_plan` pivot, `plan_id`/`ipcr_id`) suggests `$ipcr->plans()` may need to be `$ipcr->plans()->attach($plan->id)` through that same pivot from the `EmployeeIPCR` side — verify the inverse relation exists on `EmployeeIPCR` before assuming the method name.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRShowOutcomeParentEagerLoadTest.php"`
Expected: FAIL — `agency_outcome.parent` is `null` (not eager-loaded) in both responses.

- [ ] **Step 3: Update the eager loads**

In each of the 5 files, change `'plans.performance_indicator.agencyOutcome'` to `'plans.performance_indicator.agencyOutcome.parent'` (keep every other array entry in each `with([...])` call exactly as-is). For example, in `app/Http/Controllers/EmployeeIPCRController.php:247`:

```php
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
            'period',
            'plans.performance_indicator.agencyOutcome.parent'
        ])->findOrFail($id);
```

Apply the equivalent one-line change (only the `agencyOutcome` → `agencyOutcome.parent` string) in `HRIPCRController.php:57`, `DivisionChiefIPCRController.php:201`, `PMTIPCRController.php:53` (the `show()` method only), and `AdminIPCRController.php:58`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRShowOutcomeParentEagerLoadTest.php"`
Expected: PASS (2/2)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/EmployeeIPCRController.php app/Http/Controllers/HRIPCRController.php app/Http/Controllers/DivisionChiefIPCRController.php app/Http/Controllers/PMTIPCRController.php app/Http/Controllers/AdminIPCRController.php tests/Feature/IPCRShowOutcomeParentEagerLoadTest.php
git commit -m "feat(ipcr): eager-load agency_outcome.parent in all 5 IPCR show controllers"
```

---

### Task 8: `outcomeGrouping.js` — shared pure grouping function

**Files:**
- Create: `resources/js/Utils/IPCR/outcomeGrouping.js`
- Test: `tests/js/outcomeGrouping.test.mjs`

**Interfaces:**
- Produces: `normalizeFunctionType(raw: string|null): string`, `groupPlansByOutcome(plans: Array): Object` — same nested shape (`functionType → outcome → subOutcome → piDesc → plans[]`) the 5 pages' local `groupedPlansByFunction` computeds already build, but keying `outcome` by `plan.performance_indicator?.agency_outcome?.parent?.outcome ?? ...agency_outcome?.outcome` and `subOutcome` by the **full**, untruncated `agency_outcome?.sub_outcome`. Consumed by Task 9.

This lives under `Utils/`, not `Composables/`, and is a plain exported function rather than a `use...()` composable — it owns no reactive state, and the project's existing precedent for this exact kind of pure transform-and-test logic is `resources/js/Utils/ClassRecord/watUtils.js`, tested the same way this task's test file is. Each of the 5 Vue pages wraps it in their own local `computed(() => groupPlansByOutcome(props.plans))` in Task 9.

- [ ] **Step 1: Write the failing test**

```js
import assert from 'node:assert/strict'
import test from 'node:test'

import { groupPlansByOutcome, normalizeFunctionType } from '../../resources/js/Utils/IPCR/outcomeGrouping.js'

function plan({ functionType, outcome, subOutcome, piDesc, parentOutcome = null }) {
  return {
    performance_indicator: {
      description: piDesc,
      agency_outcome: {
        function_type: functionType,
        outcome,
        sub_outcome: subOutcome,
        parent: parentOutcome ? { outcome: parentOutcome } : null,
      },
    },
  }
}

test('groups by the parent outcome instead of each child row\'s own outcome text', () => {
  const plans = [
    plan({ functionType: 'Strategic Functions', outcome: 'A. STEM Secondary Education on Scholarship Basis Program', subOutcome: 'A.1', piDesc: 'Indicator 1', parentOutcome: 'A. STEM Secondary Education on Scholarship Basis Program' }),
    plan({ functionType: 'Strategic Functions', outcome: 'A. STEM Secondary Education on Scholarship Basis Program', subOutcome: 'A.2', piDesc: 'Indicator 2', parentOutcome: 'A. STEM Secondary Education on Scholarship Basis Program' }),
  ]

  const grouped = groupPlansByOutcome(plans)

  assert.deepEqual(Object.keys(grouped['Strategic Functions']), ['A. STEM Secondary Education on Scholarship Basis Program'])
  assert.deepEqual(
    Object.keys(grouped['Strategic Functions']['A. STEM Secondary Education on Scholarship Basis Program']).sort(),
    ['A.1', 'A.2']
  )
})

test('does not truncate sub_outcome text and keeps two long sub-outcomes distinct', () => {
  const plans = [
    plan({ functionType: 'Core Functions', outcome: 'B. Something', subOutcome: 'B.1 Long descriptive sub-outcome text one', piDesc: 'Indicator A' }),
    plan({ functionType: 'Core Functions', outcome: 'B. Something', subOutcome: 'B.1 Long descriptive sub-outcome text two', piDesc: 'Indicator B' }),
  ]

  const grouped = groupPlansByOutcome(plans)
  const subKeys = Object.keys(grouped['Core Functions']['B. Something']).sort()

  assert.deepEqual(subKeys, [
    'B.1 Long descriptive sub-outcome text one',
    'B.1 Long descriptive sub-outcome text two',
  ])
})

test('normalizeFunctionType maps legacy casing/spelling to canonical labels', () => {
  assert.equal(normalizeFunctionType('strategic'), 'Strategic Functions')
  assert.equal(normalizeFunctionType('Core function'), 'Core Functions')
  assert.equal(normalizeFunctionType(null), 'Uncategorized')
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js` (from `bugsaymis` root)
Expected: FAIL — `resources/js/Utils/IPCR/outcomeGrouping.js` doesn't exist yet.

- [ ] **Step 3: Write the util**

```js
export const FUNCTION_TYPE_ORDER = {
  "Strategic Functions": 1,
  "Core Functions": 2,
  "Support Functions": 3,
  "Uncategorized": 4,
};

export function normalizeFunctionType(raw) {
  if (!raw) return "Uncategorized";
  const t = String(raw).trim().toLowerCase();
  if (t === "strategic" || t === "strategic functions" || t === "strategic function") return "Strategic Functions";
  if (t === "core" || t === "core functions" || t === "core function") return "Core Functions";
  if (t === "support" || t === "support functions" || t === "support function") return "Support Functions";
  return String(raw).trim();
}

export function groupPlansByOutcome(plans) {
  const groups = {};

  (plans || []).forEach((plan) => {
    const aoo = plan.performance_indicator?.agency_outcome;
    const functionType = normalizeFunctionType(aoo?.function_type);
    const outcome = aoo?.parent?.outcome ?? aoo?.outcome ?? "Uncategorized";
    const subOutcome = aoo?.sub_outcome || "—";
    const piDesc = plan.performance_indicator?.description || "—";

    if (!groups[functionType]) groups[functionType] = {};
    if (!groups[functionType][outcome]) groups[functionType][outcome] = {};
    if (!groups[functionType][outcome][subOutcome]) groups[functionType][outcome][subOutcome] = {};
    if (!groups[functionType][outcome][subOutcome][piDesc]) groups[functionType][outcome][subOutcome][piDesc] = [];
    groups[functionType][outcome][subOutcome][piDesc].push(plan);
  });

  const sorted = {};
  Object.keys(FUNCTION_TYPE_ORDER).forEach((ft) => { if (groups[ft]) sorted[ft] = groups[ft]; });
  Object.keys(groups).filter((ft) => !FUNCTION_TYPE_ORDER[ft]).sort().forEach((ft) => (sorted[ft] = groups[ft]));

  Object.keys(sorted).forEach((ft) => {
    const sortedOutcomes = {};
    Object.keys(sorted[ft]).sort().forEach((outcome) => {
      sortedOutcomes[outcome] = {};
      Object.keys(sorted[ft][outcome]).sort().forEach((sub) => {
        const sortedPI = {};
        Object.keys(sorted[ft][outcome][sub]).sort().forEach((piDesc) => {
          sortedPI[piDesc] = sorted[ft][outcome][sub][piDesc];
        });
        sortedOutcomes[outcome][sub] = sortedPI;
      });
    });
    sorted[ft] = sortedOutcomes;
  });

  return sorted;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:js`
Expected: PASS (3/3)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Utils/IPCR/outcomeGrouping.js tests/js/outcomeGrouping.test.mjs
git commit -m "feat(ipcr): add shared outcome-grouping util replacing per-page duplication"
```

---

### Task 9: Wire `outcomeGrouping.js` into all 5 IPCR Show pages

**Files:**
- Modify: `resources/js/Pages/PerformanceManagement/EmployeeIPCRShow.vue` (computed at ~line 262-296, template loop var at ~lines 970/982)
- Modify: `resources/js/Pages/PerformanceManagement/HRIPCRShow.vue` (computed at ~line 103-136, template at ~lines 298/305)
- Modify: `resources/js/Pages/PerformanceManagement/DivisionChiefIPCRShow.vue` (computed at ~line 239-277, template at ~lines 675/683)
- Modify: `resources/js/Pages/PerformanceManagement/PMTIPCRShow.vue` (computed at ~line 106-136, template at ~lines 423/432)
- Modify: `resources/js/Pages/PerformanceManagement/AdminIPCRShow.vue` (computed at ~line 103-136, template at ~lines 298/305)

**Interfaces:**
- Consumes: `groupPlansByOutcome`, `normalizeFunctionType` from Task 8's `@/Utils/IPCR/outcomeGrouping.js`.
- Produces: no change to any other computed/prop in these files — `summaryByFunctionType` in each file keeps calling `normalizeFunctionType`, now imported instead of locally defined.

Each of the 5 files needs the same three mechanical edits. Using `EmployeeIPCRShow.vue` as the full worked example, then the same pattern (with each file's own local line numbers/formatting) for the other 4:

- [ ] **Step 1: Add the import**

In `resources/js/Pages/PerformanceManagement/EmployeeIPCRShow.vue`, near the other imports at the top of `<script setup>`, add:

```js
import { groupPlansByOutcome, normalizeFunctionType } from "@/Utils/IPCR/outcomeGrouping.js";
```

- [ ] **Step 2: Delete the local `normalizeFunctionType` definition**

Remove this block (the file's own copy, now replaced by the import):

```js
const normalizeFunctionType = (raw) => {
  if (!raw) return "Uncategorized";
  const t = String(raw).trim().toLowerCase();

  // Map possible legacy values to canonical new labels
  if (t === "strategic" || t === "strategic functions" || t === "strategic function") return "Strategic Functions";
  if (t === "core" || t === "core functions" || t === "core function") return "Core Functions";
  if (t === "support" || t === "support functions" || t === "support function") return "Support Functions";

  // If raw already is one of the canonical labels (case-insensitive)
  if (t === "strategic functions") return "Strategic Functions";
  // ...and the rest of that file's version of this function
};
```

(Read the file first to copy its exact local version before deleting — the wording/branches differ slightly file to file, as seen during research; delete whatever that file's copy actually is, in full.)

- [ ] **Step 3: Replace the `groupedPlansByFunction` computed body**

Replace:

```js
const groupedPlansByFunction = computed(() => {
  const groups = {};

  (props.plans || []).forEach(plan => {
    const aoo = plan.performance_indicator?.agency_outcome;

    const rawFT = aoo?.function_type;
    const functionType = normalizeFunctionType(rawFT);

    const outcome = aoo?.outcome || "Uncategorized";
    const subOutcome = aoo?.sub_outcome || "—";
    const subAbbrev = subOutcome !== "—" ? subOutcome.slice(0, 4) : subOutcome;

    const piDesc = plan.performance_indicator?.description || "—";

    // ... rest of the grouping/sorting logic
  });

  // ... sorting
  return sorted;
});
```

with:

```js
const groupedPlansByFunction = computed(() => groupPlansByOutcome(props.plans));
```

- [ ] **Step 4: Rename the template loop variable**

In the `<template>` section, change:

```vue
                  <template v-for="(pis, subAbbrev) in subGroups" :key="subAbbrev">
```

to:

```vue
                  <template v-for="(pis, subOutcomeLabel) in subGroups" :key="subOutcomeLabel">
```

and change the cell that displays it — `{{ subAbbrev }}` (or `{{ subAbbrev !== '—' ? subAbbrev : '' }}` in the files that guard against the em-dash) — to use `subOutcomeLabel` in place of `subAbbrev`, keeping each file's existing conditional guard exactly as it is.

- [ ] **Step 5: Repeat Steps 1-4 for the other 4 files**

Apply the same four edits to `HRIPCRShow.vue`, `DivisionChiefIPCRShow.vue`, `PMTIPCRShow.vue`, and `AdminIPCRShow.vue`. In each: keep `summaryByFunctionType` untouched except that it now calls the imported `normalizeFunctionType` instead of a locally-defined one (no code change needed inside that computed — just make sure the import from Step 1 is present and the local definition from Step 2 is gone, since both computeds in the same file shared one local definition before this change).

- [ ] **Step 6: Build and manually verify**

Run: `npm run build` (from `bugsaymis` root)
Expected: no build errors (confirms no remaining reference to the deleted local `normalizeFunctionType`/`subAbbrev` anywhere in each file).

Then, per CLAUDE.md's UI-verification rule, start the dev server and manually open each of the 5 IPCR Show pages for an IPCR that has plans against a backfilled parent/child outcome (seed one locally by running Task 3's command against dev data, or by creating one via the new admin page once Task 11 is done) and confirm: outcomes group correctly under their real parent text, sub-outcome cells show full untruncated text, and `window.print()` output (Ctrl/Cmd+P preview) still looks correct.

- [ ] **Step 7: Run the JS test suite one more time**

Run: `npm run test:js`
Expected: PASS (unchanged from Task 8 — confirms the shared util still behaves correctly after being wired into real components)

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/PerformanceManagement/EmployeeIPCRShow.vue resources/js/Pages/PerformanceManagement/HRIPCRShow.vue resources/js/Pages/PerformanceManagement/DivisionChiefIPCRShow.vue resources/js/Pages/PerformanceManagement/PMTIPCRShow.vue resources/js/Pages/PerformanceManagement/AdminIPCRShow.vue
git commit -m "refactor(ipcr): all 5 IPCR Show pages use the shared outcome-grouping util"
```

---

### Task 10: `useOutcomes.js` — parent/child form state

**Files:**
- Modify: `resources/js/Composables/useOutcomes.js`

**Interfaces:**
- Consumes: nothing new (still takes `props` with `.outcomes` — now tree-shaped per Task 4).
- Produces: `form.value.parent_id`; `openModal(mode, outcome = null, parent = null)` (new third parameter); new returned `expandedIds` (a `ref(Set)`) and `toggleExpand(id)`. `submitOutcome`/`deleteOutcome` are unchanged (they already serialize the whole `form.value`, which now includes `parent_id`).

- [ ] **Step 1: Update the `form` ref**

In `resources/js/Composables/useOutcomes.js`, change:

```js
  const form = ref({
    id: null,
    outcome: "",
    sub_outcome: "",
    function_type: "",
    fiscal_year: props.currentFiscalYear ?? null,
  });
```

to:

```js
  const form = ref({
    id: null,
    outcome: "",
    sub_outcome: "",
    function_type: "",
    fiscal_year: props.currentFiscalYear ?? null,
    parent_id: null,
  });

  const expandedIds = ref(new Set());
  const toggleExpand = (id) => {
    const next = new Set(expandedIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expandedIds.value = next;
  };
```

- [ ] **Step 2: Update `openModal` to accept a `parent` argument**

Replace the whole `openModal` function:

```js
  const openModal = (mode, outcome = null, parent = null) => {
    modalMode.value = mode;
    showModal.value = true;

    if ((mode === "edit" || mode === "view") && outcome) {
      Object.assign(form.value, {
        id: outcome.id,
        outcome: outcome.outcome,
        sub_outcome: outcome.sub_outcome ?? "",
        function_type: outcome.function_type ?? "",
        fiscal_year: outcome.fiscal_year ?? null,
        parent_id: outcome.parent_id ?? null,
      });
    } else if (mode === "create" && parent) {
      Object.assign(form.value, {
        id: null,
        outcome: parent.outcome,
        sub_outcome: "",
        function_type: parent.function_type ?? "",
        fiscal_year: parent.fiscal_year ?? null,
        parent_id: parent.id,
      });
    } else {
      Object.assign(form.value, {
        id: null,
        outcome: "",
        sub_outcome: "",
        function_type: "",
        fiscal_year: props.currentFiscalYear ?? null,
        parent_id: null,
      });
    }

    selectedOutcome.value = outcome;
  };
```

- [ ] **Step 3: Return the new state**

In the `return { ... }` block at the bottom of the file, add `expandedIds` and `toggleExpand`:

```js
  return {
    outcomesList,
    showModal,
    modalMode,
    selectedOutcome,
    searchQuery,
    currentPage,
    totalPages,
    filteredOutcomes,
    form,
    expandedIds,
    toggleExpand,
    openModal,
    closeModal,
    submitOutcome,
    deleteOutcome,
  };
```

- [ ] **Step 4: Build to catch syntax errors**

Run: `npm run build` (from `bugsaymis` root)
Expected: no build errors. (No dedicated automated test for this composable — it's Inertia-router-driven form state with no pure-logic branch worth isolating; verified end-to-end in Task 11's manual click-through, per the project's stated approach to UI verification.)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Composables/useOutcomes.js
git commit -m "feat(ipcr): add parent/child form state to useOutcomes composable"
```

---

### Task 11: `AgencyOrgOutcome.vue` — tree UI

**Files:**
- Modify: `resources/js/Pages/PerformanceManagement/AgencyOrgOutcome.vue`

**Interfaces:**
- Consumes: `expandedIds`, `toggleExpand`, updated `openModal(mode, outcome, parent)` from Task 10; `outcomes` prop is now tree-shaped (each item has `.children`) per Task 4.

- [ ] **Step 1: Add the chevron icons to the import line**

Change:

```js
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, XMarkIcon } from "@heroicons/vue/24/outline";
```

to:

```js
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, XMarkIcon, ChevronRightIcon, ChevronDownIcon } from "@heroicons/vue/24/outline";
```

- [ ] **Step 2: Destructure the new composable return values**

Change the `useOutcomes(props)` destructure to include `expandedIds` and `toggleExpand`:

```js
const {
  outcomesList,
  showModal,
  modalMode,
  selectedOutcome,
  searchQuery,
  currentPage,
  totalPages,
  filteredOutcomes,
  form,
  expandedIds,
  toggleExpand,
  openModal,
  closeModal,
  submitOutcome,
  deleteOutcome,
} = useOutcomes(props);
```

- [ ] **Step 3: Replace the desktop table rows**

Replace the single `<tr v-for="outcome in filteredOutcomes" ...>` block (the one starting right after `</template>` for `#head`, ending right before `<template #mobileCard>`) with:

```vue
        <template v-for="outcome in filteredOutcomes" :key="outcome.id">
          <tr class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.id }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">
              <button
                v-if="outcome.children?.length"
                type="button"
                class="mr-1 text-slate-400 hover:text-slate-600"
                @click="toggleExpand(outcome.id)"
              >
                <ChevronDownIcon v-if="expandedIds.has(outcome.id)" class="w-4 h-4 inline" />
                <ChevronRightIcon v-else class="w-4 h-4 inline" />
              </button>
              {{ outcome.outcome }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500">
              {{ outcome.children?.length ? `${outcome.children.length} sub-outcome(s)` : (outcome.sub_outcome ?? '—') }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-700">
              <AppBadge v-if="outcome.function_type" :color="outcomeTypeColor(outcome.function_type)">{{ outcome.function_type }}</AppBadge>
              <span v-else>—</span>
            </td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(outcome.created_at).toLocaleDateString() }}</td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-1 items-center">
                <AppIconButton label="Add Sub-Outcome" @click="openModal('create', null, outcome)">
                  <PlusIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="View" @click="openModal('view', outcome)">
                  <EyeIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Edit" variant="warning" @click="openModal('edit', outcome)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton
                  label="Delete"
                  variant="danger"
                  :disabled="outcome.children?.length > 0"
                  :title="outcome.children?.length ? 'Delete its sub-outcomes first' : 'Delete'"
                  @click="deleteOutcome(outcome)"
                >
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </td>
          </tr>

          <template v-if="expandedIds.has(outcome.id)">
            <tr v-for="child in outcome.children" :key="`child-${child.id}`" class="bg-slate-50/60 hover:bg-indigo-50/40">
              <td class="px-4 py-3 text-sm text-slate-400"></td>
              <td class="px-4 py-3 text-sm text-slate-400 pl-8">↳ {{ outcome.outcome }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ child.sub_outcome ?? '—' }}</td>
              <td class="px-4 py-3 text-sm text-slate-400">
                <AppBadge v-if="child.function_type" :color="outcomeTypeColor(child.function_type)">{{ child.function_type }}</AppBadge>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(child.created_at).toLocaleDateString() }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex justify-center gap-1 items-center">
                  <AppIconButton label="View" @click="openModal('view', child)">
                    <EyeIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Edit" variant="warning" @click="openModal('edit', child)">
                    <PencilSquareIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Delete" variant="danger" @click="deleteOutcome(child)">
                    <TrashIcon class="w-4 h-4" />
                  </AppIconButton>
                </div>
              </td>
            </tr>
          </template>
        </template>
```

- [ ] **Step 4: Replace the `#mobileCard` template**

Replace the `<template #mobileCard>...</template>` block with:

```vue
        <template #mobileCard>
          <div v-for="outcome in filteredOutcomes" :key="outcome.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ outcome.outcome }}</p>
                <p class="text-xs text-slate-500">
                  {{ outcome.children?.length ? `${outcome.children.length} sub-outcome(s)` : (outcome.sub_outcome ?? '—') }}
                </p>
              </div>
              <AppBadge v-if="outcome.function_type" :color="outcomeTypeColor(outcome.function_type)">{{ outcome.function_type }}</AppBadge>
            </div>
            <p class="text-xs text-slate-400">Created {{ new Date(outcome.created_at).toLocaleDateString() }}</p>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="Add Sub-Outcome" @click="openModal('create', null, outcome)">
                <PlusIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="View" @click="openModal('view', outcome)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" variant="warning" @click="openModal('edit', outcome)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton
                label="Delete"
                variant="danger"
                :disabled="outcome.children?.length > 0"
                @click="deleteOutcome(outcome)"
              >
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
            <div v-for="child in outcome.children" :key="child.id" class="pl-4 pt-2 border-t border-slate-100 flex items-center justify-between">
              <p class="text-xs text-slate-600">{{ child.sub_outcome }}</p>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit" variant="warning" @click="openModal('edit', child)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deleteOutcome(child)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>
```

- [ ] **Step 5: Update the modal header and form**

Change the header title expression:

```vue
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? (form.parent_id ? 'New Sub-Outcome' : 'New Outcome') : modalMode==='edit' ? 'Edit Outcome' : 'View Outcome' }}
            </h2>
```

Replace the `<!-- CREATE / EDIT FORM -->` block's field section:

```vue
          <form v-else @submit.prevent="submitOutcome">
            <div class="px-6 py-5 space-y-4">
              <div v-if="form.parent_id">
                <label class="block text-xs font-medium text-slate-600 mb-1">Outcome</label>
                <p class="text-sm text-slate-500 italic">{{ form.outcome }} (inherited from parent)</p>
              </div>
              <div v-else>
                <label class="block text-xs font-medium text-slate-600 mb-1">Outcome</label>
                <input v-model="form.outcome" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sub-Outcome</label>
                <input v-model="form.sub_outcome" type="text" placeholder="Optional" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div v-if="!form.parent_id">
                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select v-model="form.function_type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                  <option value="" disabled>Select type</option>
                  <option v-for="type in outcomeTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
              <div v-if="!form.parent_id">
                <label class="block text-xs font-medium text-slate-600 mb-1">Fiscal Year</label>
                <select v-model="form.fiscal_year" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option :value="null">All years (unscoped)</option>
                  <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
                </select>
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
```

- [ ] **Step 6: Build and manually verify**

Run: `npm run build`
Expected: no build errors.

Then run the backfill command against dev data (`docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan agency-outcomes:backfill-hierarchy"`), start the dev server, and manually: expand/collapse a parent with sub-outcomes, add a new sub-outcome (confirm it inherits type/FY and the outcome text is read-only), try deleting a parent with children (confirm the disabled button/error), delete a childless one (confirm it works).

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/PerformanceManagement/AgencyOrgOutcome.vue
git commit -m "feat(ipcr): tree UI for the Agency Org Outcome admin page"
```

---

### Task 12: `PerformanceIndicators.vue` — relabel and group the outcome picker

**Files:**
- Modify: `resources/js/Pages/PerformanceManagement/PerformanceIndicators.vue`

**Interfaces:**
- Consumes: `props.outcomes` (now tree-shaped per Task 5), `indicator.agency_outcome.parent` (now present per Task 5).

- [ ] **Step 1: Add an `outcomeLabel` helper**

In `<script setup>`, after the `usePerformanceIndicators(props)` destructure, add:

```js
const outcomeLabel = (indicator) => {
  const aoo = indicator.agency_outcome
  if (!aoo) return "—"
  const parentLabel = aoo.parent?.outcome ?? aoo.outcome
  return aoo.sub_outcome ? `${parentLabel} — ${aoo.sub_outcome}` : parentLabel
}
```

- [ ] **Step 2: Use it in the table row**

Change:

```vue
          <td class="px-4 py-3 text-sm text-slate-700">{{ indicator.agency_outcome?.sub_outcome ?? '—' }}</td>
```

to:

```vue
          <td class="px-4 py-3 text-sm text-slate-700">{{ outcomeLabel(indicator) }}</td>
```

- [ ] **Step 3: Use it in the view-mode detail panel**

Change:

```vue
          <p><span class="font-medium text-slate-700">Outcome:</span> <span class="text-slate-600">{{ selectedIndicator.agency_outcome?.sub_outcome ?? '—' }}</span></p>
```

to:

```vue
          <p><span class="font-medium text-slate-700">Outcome:</span> <span class="text-slate-600">{{ outcomeLabel(selectedIndicator) }}</span></p>
```

- [ ] **Step 4: Relabel and group the picker**

Change:

```vue
          <AppSelect v-model="form.agency_outcome_id" label="Sub-Outcome" required placeholder="-- Select Sub-Outcome --">
            <option v-for="o in props.outcomes" :key="o.id" :value="o.id">{{ o.sub_outcome }}</option>
          </AppSelect>
```

to:

```vue
          <AppSelect v-model="form.agency_outcome_id" label="Outcome" required placeholder="-- Select Outcome --">
            <optgroup v-for="parent in props.outcomes" :key="parent.id" :label="parent.outcome">
              <option
                v-for="leaf in (parent.children?.length ? parent.children : [parent])"
                :key="leaf.id"
                :value="leaf.id"
              >
                {{ leaf.sub_outcome ?? parent.outcome }}
              </option>
            </optgroup>
          </AppSelect>
```

- [ ] **Step 5: Build and manually verify**

Run: `npm run build`
Expected: no build errors.

Start the dev server, open Performance Indicators, confirm the picker now shows `<optgroup>` headers per outcome with correctly-labeled leaf options, and that the table/detail-view outcome column shows the full "Parent — Sub" text instead of just the bare sub-outcome.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/PerformanceManagement/PerformanceIndicators.vue
git commit -m "feat(ipcr): relabel and group the outcome picker in PerformanceIndicators"
```

---

## Self-Review

**Spec coverage:**
- Additive `parent_id` schema change → Task 1. ✅
- Restrict-delete fix on `performance_indicators.agency_outcome_id` → Task 2. ✅
- Auto-backfill by matching outcome text, flagging placeholder rows → Task 3. ✅
- `AgencyOutcomeController` inherit-from-parent + delete guards + tree index → Task 4. ✅
- `PerformanceIndicatorController` tree outcomes prop → Task 5. ✅
- `copyFramework()` parent_id remap → Task 6. ✅
- 5 IPCR show controllers eager-load `.parent` → Task 7. ✅
- Shared grouping logic (no more 4-char truncation, relational not string-matched) → Task 8 + 9. ✅
- Existing `AgencyOrgOutcome.vue`/`outcome.*` routes upgraded in place (no new nav entry) → Task 11. ✅
- `PerformanceIndicators.vue` picker relabeled/grouped → Task 12. ✅
- DOST hierarchy / OPCR-DPCR-IPCR-v2 / mPDF printing — confirmed untouched by every task above (no task modifies `Dost*` files, no task adds a blade/mPDF template). ✅

**Placeholder scan:** No TBD/TODO in any step; every step has runnable code or an exact command. Task 7's route-name/relation-name caveat is a verify-before-writing instruction (the actual research fork's line references were confident on controllers/views but didn't confirm `EmployeeIPCR`'s inverse pivot relation name), not a placeholder — the test's structure and assertions are fully written, only two identifiers need a one-command confirmation before the test is finalized.

**Type consistency:** `groupPlansByOutcome`/`normalizeFunctionType` (Task 8) match their usage in Task 9 exactly. `expandedIds`/`toggleExpand` (Task 10) match their consumption in Task 11 exactly. `openModal(mode, outcome, parent)`'s three-argument signature (Task 10) matches every call site added in Task 11 (`openModal('create', null, outcome)`, `openModal('view', outcome)`, `openModal('edit', child)`). `AgencyOutcome::performanceIndicators()` (Task 1) matches its use in Task 4's `destroy()` guard.
