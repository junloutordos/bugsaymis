# DOST Strategic Plan Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a DOST Strategic Plan admin module under Performance Management — Pillar → Strategy → Sub-Strategy, with Strategy optionally linked to the existing `AgencyOutcome` (PSHS Program) record.

**Architecture:** Three new Eloquent models/tables (`DostPillar`, `DostStrategy`, `DostSubStrategy`) in a strict parent-child hierarchy, each with its own thin CRUD controller (matching the existing `AgencyOutcomeController`/`PerformanceIndicatorController` pattern), all routes gated by the existing `ipcr.view` permission. One additional controller (`DostStrategicPlanController@index`) renders the full nested tree as a single Inertia page with an expand/collapse UI (not `AppTable`, since the data is hierarchical, not a flat list) backed by one composable handling all three levels' modals.

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL (FK cascade deletes), Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS, SweetAlert2 for confirm/error dialogs (matching `useOutcomes.js`).

**Spec:** `docs/superpowers/specs/2026-08-17-dost-strategic-plan-design.md`

## Global Constraints

- Reuse the existing `ipcr.view` permission for every new route — do not create a new permission.
- All new routes are Inertia (no JSON API), mutations redirect with `back()->with('success', ...)`, per project convention.
- Migrations are additive only — three new tables, no changes to any existing table.
- No `fiscal_year` column on `dost_pillars`, `dost_strategies`, or `dost_sub_strategies`.
- `dost_strategies.agency_outcome_id` is nullable — `agency_org_outcomes` is empty in dev/prod today; Strategies must be creatable with no outcome selected.
- Cascade delete both directions: `DostPillar` → `DostStrategy` → `DostSubStrategy`, and `AgencyOutcome` → `DostStrategy` → `DostSubStrategy`.
- Do not touch `AgencyOutcomeController`, `PerformanceIndicatorController`, their Vue pages, or `IPCRRatingPeriodController`'s fiscal-year rollover logic.
- No seeding of real Pillar/Strategy/Sub-Strategy data — all three tables ship empty.
- Artisan commands run via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan <command>"`
- PHP tests run via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test <path>"`

---

### Task 1: DostPillar (migration, model, controller, routes, tests)

**Files:**
- Create: `database/migrations/2026_08_17_090100_create_dost_pillars_table.php`
- Create: `app/Models/DostPillar.php`
- Create: `app/Http/Controllers/DostPillarController.php`
- Modify: `routes/web.php` (import + route group)
- Test: `tests/Feature/DostPillarControllerTest.php`

**Interfaces:**
- Produces: `App\Models\DostPillar` with fillable `name`, `outcome_statement`; route names `dost-pillars.store`, `dost-pillars.update`, `dost-pillars.destroy`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DostPillarControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DostPillar;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostPillarControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithoutIpcrView(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_administrator_can_create_a_pillar(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-pillars.store'), [
            'name' => 'DOST Pillar 5: Governance',
            'outcome_statement' => 'DOST System Governance Strengthened and Harmonized',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_pillars', ['name' => 'DOST Pillar 5: Governance']);
    }

    public function test_administrator_can_update_a_pillar(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->put(route('dost-pillars.update', $pillar), [
            'name' => 'New Name',
            'outcome_statement' => 'Updated statement',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id, 'name' => 'New Name']);
    }

    public function test_administrator_can_delete_a_pillar(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'To Delete']);

        $response = $this->actingAs($admin)->delete(route('dost-pillars.destroy', $pillar));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_pillars', ['id' => $pillar->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_pillar(): void
    {
        $user = $this->userWithoutIpcrView();

        $response = $this->actingAs($user)->post(route('dost-pillars.store'), [
            'name' => 'Blocked Pillar',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('dost_pillars', ['name' => 'Blocked Pillar']);
    }

    public function test_creating_a_pillar_requires_a_name(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-pillars.store'), [
            'outcome_statement' => 'No name provided',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostPillarControllerTest.php"`
Expected: FAIL — route `dost-pillars.store` not defined (class `DostPillar` and controller don't exist yet).

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_17_090100_create_dost_pillars_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_pillars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('outcome_statement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_pillars');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_17_090100_create_dost_pillars_table.php"`

- [ ] **Step 5: Write the model**

Create `app/Models/DostPillar.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostPillar extends Model
{
    protected $fillable = ['name', 'outcome_statement'];

    public function strategies()
    {
        return $this->hasMany(DostStrategy::class);
    }
}
```

- [ ] **Step 6: Write the controller**

Create `app/Http/Controllers/DostPillarController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\DostPillar;
use Illuminate\Http\Request;

class DostPillarController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'outcome_statement' => 'nullable|string',
        ]);

        DostPillar::create($data);

        return back()->with('success', 'Pillar created.');
    }

    public function update(Request $request, DostPillar $dostPillar)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'outcome_statement' => 'nullable|string',
        ]);

        $dostPillar->update($data);

        return back()->with('success', 'Pillar updated.');
    }

    public function destroy(DostPillar $dostPillar)
    {
        $dostPillar->delete();

        return back()->with('success', 'Pillar deleted.');
    }
}
```

- [ ] **Step 7: Add routes**

In `routes/web.php`, add the import near the other Performance Management controller imports (next to `use App\Http\Controllers\AgencyOutcomeController;` around line 82):

```php
use App\Http\Controllers\DostPillarController;
```

Then add the three routes inside the existing `Route::middleware('permission:ipcr.view')->group(...)` block that currently holds the `/agency-outcomes` routes (routes/web.php:1182-1187):

```php
    // Agency Org Outcome — same reason, split out of the users.view group.
    Route::middleware('permission:ipcr.view')->group(function () {
        Route::get('/agency-outcomes', [AgencyOutcomeController::class, 'index'])->name('outcome.index');
        Route::post('agency-outcomes', [AgencyOutcomeController::class, 'store'])->name('outcome.store');
        Route::put('agency-outcomes/{id}', [AgencyOutcomeController::class, 'update'])->name('outcome.update');
        Route::delete('agency-outcomes/{id}', [AgencyOutcomeController::class, 'destroy'])->name('outcome.destroy');

        Route::post('dost-pillars', [DostPillarController::class, 'store'])->name('dost-pillars.store');
        Route::put('dost-pillars/{dostPillar}', [DostPillarController::class, 'update'])->name('dost-pillars.update');
        Route::delete('dost-pillars/{dostPillar}', [DostPillarController::class, 'destroy'])->name('dost-pillars.destroy');
    });
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostPillarControllerTest.php"`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_17_090100_create_dost_pillars_table.php app/Models/DostPillar.php app/Http/Controllers/DostPillarController.php routes/web.php tests/Feature/DostPillarControllerTest.php
git commit -m "feat(spms): add DostPillar CRUD"
```

---

### Task 2: DostStrategy (migration, model, controller, routes, tests)

**Files:**
- Create: `database/migrations/2026_08_17_090200_create_dost_strategies_table.php`
- Create: `app/Models/DostStrategy.php`
- Create: `app/Http/Controllers/DostStrategyController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DostStrategyControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\DostPillar` (Task 1), `App\Models\AgencyOutcome` (existing, `app/Models/AgencyOutcome.php`).
- Produces: `App\Models\DostStrategy` with fillable `dost_pillar_id`, `agency_outcome_id`, `name`; relations `pillar()`, `agencyOutcome()`, `subStrategies()`; route names `dost-strategies.store`, `dost-strategies.update`, `dost-strategies.destroy`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DostStrategyControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategyControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithoutIpcrView(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_administrator_can_create_a_strategy_without_an_agency_outcome(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_strategies', [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => null,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);
    }

    public function test_administrator_can_create_a_strategy_linked_to_an_agency_outcome(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $outcome = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
            'name' => 'Strategy 17: Institutionalize science communication',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_strategies', [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
        ]);
    }

    public function test_creating_a_strategy_requires_a_valid_pillar(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => 99999,
            'name' => 'Orphan Strategy',
        ]);

        $response->assertSessionHasErrors('dost_pillar_id');
        $this->assertDatabaseMissing('dost_strategies', ['name' => 'Orphan Strategy']);
    }

    public function test_creating_a_strategy_rejects_a_nonexistent_agency_outcome(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 2: Wealth Creation']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => 99999,
            'name' => 'Strategy 5: Advance research',
        ]);

        $response->assertSessionHasErrors('agency_outcome_id');
    }

    public function test_administrator_can_update_a_strategy(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Old Name']);

        $response = $this->actingAs($admin)->put(route('dost-strategies.update', $strategy), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_strategies', ['id' => $strategy->id, 'name' => 'New Name']);
    }

    public function test_administrator_can_delete_a_strategy(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'To Delete']);

        $response = $this->actingAs($admin)->delete(route('dost-strategies.destroy', $strategy));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_strategy(): void
    {
        $user = $this->userWithoutIpcrView();
        $pillar = DostPillar::create(['name' => 'Pillar A']);

        $response = $this->actingAs($user)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Blocked Strategy',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostStrategyControllerTest.php"`
Expected: FAIL — `dost-strategies.store` route not defined.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_17_090200_create_dost_strategies_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dost_pillar_id')->constrained('dost_pillars')->onDelete('cascade');
            $table->foreignId('agency_outcome_id')->nullable()->constrained('agency_org_outcomes')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_strategies');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_17_090200_create_dost_strategies_table.php"`

- [ ] **Step 5: Write the model**

Create `app/Models/DostStrategy.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostStrategy extends Model
{
    protected $fillable = ['dost_pillar_id', 'agency_outcome_id', 'name'];

    public function pillar()
    {
        return $this->belongsTo(DostPillar::class, 'dost_pillar_id');
    }

    public function agencyOutcome()
    {
        return $this->belongsTo(AgencyOutcome::class, 'agency_outcome_id');
    }

    public function subStrategies()
    {
        return $this->hasMany(DostSubStrategy::class);
    }
}
```

- [ ] **Step 6: Write the controller**

Create `app/Http/Controllers/DostStrategyController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\DostStrategy;
use Illuminate\Http\Request;

class DostStrategyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'dost_pillar_id' => 'required|exists:dost_pillars,id',
            'agency_outcome_id' => 'nullable|exists:agency_org_outcomes,id',
            'name' => 'required|string|max:255',
        ]);

        DostStrategy::create($data);

        return back()->with('success', 'Strategy created.');
    }

    public function update(Request $request, DostStrategy $dostStrategy)
    {
        $data = $request->validate([
            'dost_pillar_id' => 'required|exists:dost_pillars,id',
            'agency_outcome_id' => 'nullable|exists:agency_org_outcomes,id',
            'name' => 'required|string|max:255',
        ]);

        $dostStrategy->update($data);

        return back()->with('success', 'Strategy updated.');
    }

    public function destroy(DostStrategy $dostStrategy)
    {
        $dostStrategy->delete();

        return back()->with('success', 'Strategy deleted.');
    }
}
```

- [ ] **Step 7: Add routes**

In `routes/web.php`, add the import next to the `DostPillarController` import:

```php
use App\Http\Controllers\DostStrategyController;
```

Add to the same `permission:ipcr.view` group as Task 1, after the `dost-pillars` routes:

```php
        Route::post('dost-strategies', [DostStrategyController::class, 'store'])->name('dost-strategies.store');
        Route::put('dost-strategies/{dostStrategy}', [DostStrategyController::class, 'update'])->name('dost-strategies.update');
        Route::delete('dost-strategies/{dostStrategy}', [DostStrategyController::class, 'destroy'])->name('dost-strategies.destroy');
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostStrategyControllerTest.php"`
Expected: PASS (7 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_17_090200_create_dost_strategies_table.php app/Models/DostStrategy.php app/Http/Controllers/DostStrategyController.php routes/web.php tests/Feature/DostStrategyControllerTest.php
git commit -m "feat(spms): add DostStrategy CRUD, nullable-linked to AgencyOutcome"
```

---

### Task 3: DostSubStrategy (migration, model, controller, routes, tests)

**Files:**
- Create: `database/migrations/2026_08_17_090300_create_dost_sub_strategies_table.php`
- Create: `app/Models/DostSubStrategy.php`
- Create: `app/Http/Controllers/DostSubStrategyController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DostSubStrategyControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\DostStrategy` (Task 2).
- Produces: `App\Models\DostSubStrategy` with fillable `dost_strategy_id`, `description`; relation `strategy()`; route names `dost-sub-strategies.store`, `dost-sub-strategies.update`, `dost-sub-strategies.destroy`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DostSubStrategyControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostSubStrategyControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithoutIpcrView(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function strategy(): DostStrategy
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);

        return DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
    }

    public function test_administrator_can_create_a_sub_strategy(): void
    {
        $admin = $this->admin();
        $strategy = $this->strategy();

        $response = $this->actingAs($admin)->post(route('dost-sub-strategies.store'), [
            'dost_strategy_id' => $strategy->id,
            'description' => 'Institutionalize FORWARD Framework',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_sub_strategies', [
            'dost_strategy_id' => $strategy->id,
            'description' => 'Institutionalize FORWARD Framework',
        ]);
    }

    public function test_creating_a_sub_strategy_requires_a_valid_strategy(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-sub-strategies.store'), [
            'dost_strategy_id' => 99999,
            'description' => 'Orphan Sub-Strategy',
        ]);

        $response->assertSessionHasErrors('dost_strategy_id');
        $this->assertDatabaseMissing('dost_sub_strategies', ['description' => 'Orphan Sub-Strategy']);
    }

    public function test_administrator_can_update_a_sub_strategy(): void
    {
        $admin = $this->admin();
        $strategy = $this->strategy();
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Old description']);

        $response = $this->actingAs($admin)->put(route('dost-sub-strategies.update', $sub), [
            'dost_strategy_id' => $strategy->id,
            'description' => 'New description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_sub_strategies', ['id' => $sub->id, 'description' => 'New description']);
    }

    public function test_administrator_can_delete_a_sub_strategy(): void
    {
        $admin = $this->admin();
        $strategy = $this->strategy();
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'To delete']);

        $response = $this->actingAs($admin)->delete(route('dost-sub-strategies.destroy', $sub));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_sub_strategy(): void
    {
        $user = $this->userWithoutIpcrView();
        $strategy = $this->strategy();

        $response = $this->actingAs($user)->post(route('dost-sub-strategies.store'), [
            'dost_strategy_id' => $strategy->id,
            'description' => 'Blocked',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostSubStrategyControllerTest.php"`
Expected: FAIL — `dost-sub-strategies.store` route not defined.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_17_090300_create_dost_sub_strategies_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_sub_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dost_strategy_id')->constrained('dost_strategies')->onDelete('cascade');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_sub_strategies');
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_17_090300_create_dost_sub_strategies_table.php"`

- [ ] **Step 5: Write the model**

Create `app/Models/DostSubStrategy.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DostSubStrategy extends Model
{
    protected $fillable = ['dost_strategy_id', 'description'];

    public function strategy()
    {
        return $this->belongsTo(DostStrategy::class, 'dost_strategy_id');
    }
}
```

- [ ] **Step 6: Write the controller**

Create `app/Http/Controllers/DostSubStrategyController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\DostSubStrategy;
use Illuminate\Http\Request;

class DostSubStrategyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'dost_strategy_id' => 'required|exists:dost_strategies,id',
            'description' => 'required|string',
        ]);

        DostSubStrategy::create($data);

        return back()->with('success', 'Sub-Strategy created.');
    }

    public function update(Request $request, DostSubStrategy $dostSubStrategy)
    {
        $data = $request->validate([
            'dost_strategy_id' => 'required|exists:dost_strategies,id',
            'description' => 'required|string',
        ]);

        $dostSubStrategy->update($data);

        return back()->with('success', 'Sub-Strategy updated.');
    }

    public function destroy(DostSubStrategy $dostSubStrategy)
    {
        $dostSubStrategy->delete();

        return back()->with('success', 'Sub-Strategy deleted.');
    }
}
```

- [ ] **Step 7: Add routes**

In `routes/web.php`, add the import next to the other `Dost*Controller` imports:

```php
use App\Http\Controllers\DostSubStrategyController;
```

Add to the same `permission:ipcr.view` group, after the `dost-strategies` routes:

```php
        Route::post('dost-sub-strategies', [DostSubStrategyController::class, 'store'])->name('dost-sub-strategies.store');
        Route::put('dost-sub-strategies/{dostSubStrategy}', [DostSubStrategyController::class, 'update'])->name('dost-sub-strategies.update');
        Route::delete('dost-sub-strategies/{dostSubStrategy}', [DostSubStrategyController::class, 'destroy'])->name('dost-sub-strategies.destroy');
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostSubStrategyControllerTest.php"`
Expected: PASS (5 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_17_090300_create_dost_sub_strategies_table.php app/Models/DostSubStrategy.php app/Http/Controllers/DostSubStrategyController.php routes/web.php tests/Feature/DostSubStrategyControllerTest.php
git commit -m "feat(spms): add DostSubStrategy CRUD"
```

---

### Task 4: Cross-entity cascade delete tests

**Files:**
- Test: `tests/Feature/DostStrategicPlanCascadeDeleteTest.php`

**Interfaces:**
- Consumes: `DostPillar`, `DostStrategy`, `DostSubStrategy` (Tasks 1-3), `AgencyOutcome` (existing).
- Produces: nothing new — this task only verifies the FK `onDelete('cascade')` behavior declared in Tasks 1-3's migrations actually holds at the database level.

- [ ] **Step 1: Write the test**

Create `tests/Feature/DostStrategicPlanCascadeDeleteTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategicPlanCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_pillar_cascades_to_its_strategies_and_sub_strategies(): void
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $pillar->delete();

        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
    }

    public function test_deleting_an_agency_outcome_cascades_to_linked_strategies_and_sub_strategies(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education Program']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $strategy = DostStrategy::create([
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
            'name' => 'Strategy 19: Roll-out enabled systems',
        ]);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $outcome->delete();

        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
        // The Pillar itself is untouched — only the Strategy/AgencyOutcome edge cascades.
        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id]);
    }

    public function test_deleting_a_strategy_cascades_to_its_sub_strategies_but_not_its_pillar(): void
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 2: Wealth Creation']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 5']);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $strategy->delete();

        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostStrategicPlanCascadeDeleteTest.php"`
Expected: PASS (3 tests). If any cascade assertion fails, re-check the `onDelete('cascade')` clause on the relevant foreign key in the Task 1-3 migrations — do not add manual delete-cascade code in the models to work around a migration mistake.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DostStrategicPlanCascadeDeleteTest.php
git commit -m "test(spms): verify DOST Strategic Plan cascade delete behavior"
```

---

### Task 5: DostStrategicPlanController@index + route + placeholder Vue page

**Files:**
- Create: `app/Http/Controllers/DostStrategicPlanController.php`
- Create: `resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue` (placeholder — replaced in Task 6)
- Modify: `routes/web.php`
- Test: `tests/Feature/DostStrategicPlanControllerTest.php`

**Interfaces:**
- Consumes: `DostPillar` (Task 1) with `strategies.subStrategies` and `strategies.agencyOutcome` eager-loaded; `AgencyOutcome` (existing).
- Produces: Inertia page `PerformanceManagement/DostStrategicPlan` with props `pillars` (array, each with `strategies` → `sub_strategies` and `agency_outcome`) and `agencyOutcomes` (array of `{id, outcome}`); route name `dost-strategic-plan.index`.

**Note:** the placeholder `.vue` file must exist on disk before the Inertia test runs — Inertia component resolution in this project's test setup has previously failed when the target `.vue` file didn't exist yet, even with a correct backend. Step 1 below creates it first for that reason.

- [ ] **Step 1: Create the placeholder Vue page**

Create `resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue`:

```vue
<script setup>
defineProps({
  pillars: { type: Array, default: () => [] },
  agencyOutcomes: { type: Array, default: () => [] },
});
</script>

<template>
  <div>DOST Strategic Plan — placeholder, replaced in Task 6.</div>
</template>
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/DostStrategicPlanControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategicPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithoutIpcrView(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_renders_nested_pillars_strategies_and_sub_strategies(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education Program']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create([
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);
        DostSubStrategy::create([
            'dost_strategy_id' => $strategy->id,
            'description' => 'Institutionalize FORWARD Framework',
        ]);

        $response = $this->actingAs($admin)->get(route('dost-strategic-plan.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/DostStrategicPlan')
            ->has('pillars', 1)
            ->where('pillars.0.strategies.0.sub_strategies.0.description', 'Institutionalize FORWARD Framework')
            ->where('pillars.0.strategies.0.agency_outcome.outcome', 'A. STEM Secondary Education Program')
        );
    }

    public function test_index_renders_with_no_data(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('dost-strategic-plan.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/DostStrategicPlan')
            ->has('pillars', 0)
        );
    }

    public function test_user_without_ipcr_view_permission_cannot_view_the_index(): void
    {
        $user = $this->userWithoutIpcrView();

        $response = $this->actingAs($user)->get(route('dost-strategic-plan.index'));

        $response->assertForbidden();
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostStrategicPlanControllerTest.php"`
Expected: FAIL — `dost-strategic-plan.index` route not defined.

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/DostStrategicPlanController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use Inertia\Inertia;

class DostStrategicPlanController extends Controller
{
    public function index()
    {
        return Inertia::render('PerformanceManagement/DostStrategicPlan', [
            'pillars' => DostPillar::with(['strategies.subStrategies', 'strategies.agencyOutcome'])
                ->orderBy('name')
                ->get(),
            'agencyOutcomes' => AgencyOutcome::orderBy('outcome')->get(['id', 'outcome']),
        ]);
    }
}
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, add the import next to the other `Dost*Controller` imports:

```php
use App\Http\Controllers\DostStrategicPlanController;
```

Add to the same `permission:ipcr.view` group, before the `dost-pillars` routes:

```php
        Route::get('/dost-strategic-plan', [DostStrategicPlanController::class, 'index'])->name('dost-strategic-plan.index');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostStrategicPlanControllerTest.php"`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DostStrategicPlanController.php resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue routes/web.php tests/Feature/DostStrategicPlanControllerTest.php
git commit -m "feat(spms): add DOST Strategic Plan index endpoint"
```

---

### Task 6: Composable + full interactive Vue page + nav entry + manual verification

**Files:**
- Create: `resources/js/Composables/useDostStrategicPlan.js`
- Modify: `resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue` (replaces Task 5 placeholder)
- Modify: `resources/js/Layouts/navigation.js`

**Interfaces:**
- Consumes: props `pillars`, `agencyOutcomes` from `DostStrategicPlanController@index` (Task 5); routes `dost-pillars.*`, `dost-strategies.*`, `dost-sub-strategies.*` (Tasks 1-3).
- Produces: `useDostStrategicPlan(props)` composable used only by `DostStrategicPlan.vue`.

This task has no automated test — this project has no JS test runner (`package.json` has no `test` script, no vitest/jest). Verification is a Vite build check plus a manual browser walkthrough (Step 4).

- [ ] **Step 1: Write the composable**

Create `resources/js/Composables/useDostStrategicPlan.js`:

```js
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

export function useDostStrategicPlan() {
  const expandedPillars = ref(new Set());
  const expandedStrategies = ref(new Set());

  function togglePillar(id) {
    if (expandedPillars.value.has(id)) expandedPillars.value.delete(id);
    else expandedPillars.value.add(id);
  }

  function toggleStrategy(id) {
    if (expandedStrategies.value.has(id)) expandedStrategies.value.delete(id);
    else expandedStrategies.value.add(id);
  }

  function flashErrors(errors) {
    return Swal.fire("Error", Object.values(errors).flat().join(", "), "error");
  }

  // ── Pillar ─────────────────────────────────────────────────────────
  const showPillarModal = ref(false);
  const pillarModalMode = ref("create");
  const pillarForm = ref({ id: null, name: "", outcome_statement: "" });

  function openPillarModal(mode, pillar = null) {
    pillarModalMode.value = mode;
    pillarForm.value = pillar
      ? { id: pillar.id, name: pillar.name, outcome_statement: pillar.outcome_statement ?? "" }
      : { id: null, name: "", outcome_statement: "" };
    showPillarModal.value = true;
  }

  function closePillarModal() {
    showPillarModal.value = false;
  }

  function submitPillar() {
    const isCreate = pillarModalMode.value === "create";
    const url = isCreate ? "/dost-pillars" : `/dost-pillars/${pillarForm.value.id}`;
    router[isCreate ? "post" : "put"](url, pillarForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        closePillarModal();
        router.reload({ only: ["pillars"] });
      },
      onError: flashErrors,
    });
  }

  async function deletePillar(pillar) {
    const result = await Swal.fire({
      title: `Delete pillar "${pillar.name}"?`,
      text: `This will also delete ${pillar.strategies.length} strategies and everything under them. This cannot be undone.`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    });
    if (!result.isConfirmed) return;

    router.delete(`/dost-pillars/${pillar.id}`, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["pillars"] }),
    });
  }

  // ── Strategy ───────────────────────────────────────────────────────
  const showStrategyModal = ref(false);
  const strategyModalMode = ref("create");
  const strategyForm = ref({ id: null, dost_pillar_id: null, agency_outcome_id: null, name: "" });

  function openStrategyModal(mode, strategy = null, pillar = null) {
    strategyModalMode.value = mode;
    strategyForm.value = strategy
      ? {
          id: strategy.id,
          dost_pillar_id: strategy.dost_pillar_id,
          agency_outcome_id: strategy.agency_outcome_id,
          name: strategy.name,
        }
      : { id: null, dost_pillar_id: pillar?.id ?? null, agency_outcome_id: null, name: "" };
    showStrategyModal.value = true;
  }

  function closeStrategyModal() {
    showStrategyModal.value = false;
  }

  function submitStrategy() {
    const isCreate = strategyModalMode.value === "create";
    const url = isCreate ? "/dost-strategies" : `/dost-strategies/${strategyForm.value.id}`;
    router[isCreate ? "post" : "put"](url, strategyForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        closeStrategyModal();
        router.reload({ only: ["pillars"] });
      },
      onError: flashErrors,
    });
  }

  async function deleteStrategy(strategy) {
    const result = await Swal.fire({
      title: `Delete strategy "${strategy.name}"?`,
      text: `This will also delete ${strategy.sub_strategies.length} sub-strategies under it. This cannot be undone.`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    });
    if (!result.isConfirmed) return;

    router.delete(`/dost-strategies/${strategy.id}`, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["pillars"] }),
    });
  }

  // ── Sub-Strategy ───────────────────────────────────────────────────
  const showSubStrategyModal = ref(false);
  const subStrategyModalMode = ref("create");
  const subStrategyForm = ref({ id: null, dost_strategy_id: null, description: "" });

  function openSubStrategyModal(mode, sub = null, strategy = null) {
    subStrategyModalMode.value = mode;
    subStrategyForm.value = sub
      ? { id: sub.id, dost_strategy_id: sub.dost_strategy_id, description: sub.description }
      : { id: null, dost_strategy_id: strategy?.id ?? null, description: "" };
    showSubStrategyModal.value = true;
  }

  function closeSubStrategyModal() {
    showSubStrategyModal.value = false;
  }

  function submitSubStrategy() {
    const isCreate = subStrategyModalMode.value === "create";
    const url = isCreate ? "/dost-sub-strategies" : `/dost-sub-strategies/${subStrategyForm.value.id}`;
    router[isCreate ? "post" : "put"](url, subStrategyForm.value, {
      preserveScroll: true,
      onSuccess: () => {
        closeSubStrategyModal();
        router.reload({ only: ["pillars"] });
      },
      onError: flashErrors,
    });
  }

  async function deleteSubStrategy(sub) {
    const result = await Swal.fire({
      title: "Delete this sub-strategy?",
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete",
    });
    if (!result.isConfirmed) return;

    router.delete(`/dost-sub-strategies/${sub.id}`, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["pillars"] }),
    });
  }

  return {
    expandedPillars,
    expandedStrategies,
    togglePillar,
    toggleStrategy,
    showPillarModal,
    pillarModalMode,
    pillarForm,
    openPillarModal,
    closePillarModal,
    submitPillar,
    deletePillar,
    showStrategyModal,
    strategyModalMode,
    strategyForm,
    openStrategyModal,
    closeStrategyModal,
    submitStrategy,
    deleteStrategy,
    showSubStrategyModal,
    subStrategyModalMode,
    subStrategyForm,
    openSubStrategyModal,
    closeSubStrategyModal,
    submitSubStrategy,
    deleteSubStrategy,
  };
}
```

- [ ] **Step 2: Replace the placeholder Vue page**

Replace the full contents of `resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue`:

```vue
<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppIconButton from "@/Components/AppIconButton.vue";
import AppBadge from "@/Components/AppBadge.vue";
import EmptyState from "@/Components/EmptyState.vue";
import {
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  XMarkIcon,
  ChevronRightIcon,
} from "@heroicons/vue/24/outline";
import { useDostStrategicPlan } from "@/Composables/useDostStrategicPlan.js";

const props = defineProps({
  pillars: { type: Array, default: () => [] },
  agencyOutcomes: { type: Array, default: () => [] },
});

const {
  expandedPillars,
  expandedStrategies,
  togglePillar,
  toggleStrategy,
  showPillarModal,
  pillarModalMode,
  pillarForm,
  openPillarModal,
  closePillarModal,
  submitPillar,
  deletePillar,
  showStrategyModal,
  strategyModalMode,
  strategyForm,
  openStrategyModal,
  closeStrategyModal,
  submitStrategy,
  deleteStrategy,
  showSubStrategyModal,
  subStrategyModalMode,
  subStrategyForm,
  openSubStrategyModal,
  closeSubStrategyModal,
  submitSubStrategy,
  deleteSubStrategy,
} = useDostStrategicPlan();

const inputClass =
  "w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500";
</script>

<template>
  <Head title="DOST Strategic Plan" />
  <AdminLayout title="DOST Strategic Plan">
    <div class="space-y-5">
      <AppPageHeader
        title="DOST Strategic Plan"
        subtitle="Manage Pillar, Strategy, and Sub-Strategy alignment, linked to PSHS Programs (Agency Org Outcome)."
      >
        <template #actions>
          <AppButton @click="openPillarModal('create')">
            <PlusIcon class="w-4 h-4" /> New Pillar
          </AppButton>
        </template>
      </AppPageHeader>

      <EmptyState
        v-if="pillars.length === 0"
        title="No pillars yet"
        subtitle="Add a DOST Pillar to get started."
      />

      <div v-else class="space-y-3">
        <div
          v-for="pillar in pillars"
          :key="pillar.id"
          class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden"
        >
          <div
            class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50"
            @click="togglePillar(pillar.id)"
          >
            <div class="flex items-center gap-2 min-w-0">
              <ChevronRightIcon
                :class="[
                  'w-4 h-4 text-slate-400 transition-transform shrink-0',
                  expandedPillars.has(pillar.id) ? 'rotate-90' : '',
                ]"
              />
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ pillar.name }}</p>
                <p v-if="pillar.outcome_statement" class="text-xs text-slate-500 truncate">
                  {{ pillar.outcome_statement }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0" @click.stop>
              <AppIconButton label="New Strategy" @click="openStrategyModal('create', null, pillar)">
                <PlusIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit Pillar" variant="warning" @click="openPillarModal('edit', pillar)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete Pillar" variant="danger" @click="deletePillar(pillar)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>

          <div v-if="expandedPillars.has(pillar.id)" class="border-t border-slate-100 divide-y divide-slate-100">
            <EmptyState v-if="pillar.strategies.length === 0" title="No strategies under this pillar" />
            <div v-for="strategy in pillar.strategies" :key="strategy.id" class="pl-8 pr-4 py-3">
              <div class="flex items-center justify-between gap-3 cursor-pointer" @click="toggleStrategy(strategy.id)">
                <div class="flex items-center gap-2 min-w-0">
                  <ChevronRightIcon
                    :class="[
                      'w-3.5 h-3.5 text-slate-400 transition-transform shrink-0',
                      expandedStrategies.has(strategy.id) ? 'rotate-90' : '',
                    ]"
                  />
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-700 truncate">{{ strategy.name }}</p>
                    <AppBadge v-if="strategy.agency_outcome" color="indigo">{{ strategy.agency_outcome.outcome }}</AppBadge>
                    <AppBadge v-else color="slate">Unlinked</AppBadge>
                  </div>
                </div>
                <div class="flex items-center gap-1 shrink-0" @click.stop>
                  <AppIconButton label="New Sub-Strategy" @click="openSubStrategyModal('create', null, strategy)">
                    <PlusIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Edit Strategy" variant="warning" @click="openStrategyModal('edit', strategy, pillar)">
                    <PencilSquareIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Delete Strategy" variant="danger" @click="deleteStrategy(strategy)">
                    <TrashIcon class="w-4 h-4" />
                  </AppIconButton>
                </div>
              </div>

              <div v-if="expandedStrategies.has(strategy.id)" class="mt-2 pl-6 space-y-2">
                <EmptyState v-if="strategy.sub_strategies.length === 0" title="No sub-strategies yet" />
                <div
                  v-for="sub in strategy.sub_strategies"
                  :key="sub.id"
                  class="flex items-center justify-between gap-3 text-sm text-slate-600 bg-slate-50 rounded-lg px-3 py-2"
                >
                  <span class="truncate">{{ sub.description }}</span>
                  <div class="flex items-center gap-1 shrink-0">
                    <AppIconButton label="Edit Sub-Strategy" variant="warning" size="sm" @click="openSubStrategyModal('edit', sub, strategy)">
                      <PencilSquareIcon class="w-3.5 h-3.5" />
                    </AppIconButton>
                    <AppIconButton label="Delete Sub-Strategy" variant="danger" size="sm" @click="deleteSubStrategy(sub)">
                      <TrashIcon class="w-3.5 h-3.5" />
                    </AppIconButton>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pillar Modal -->
      <div v-if="showPillarModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ pillarModalMode === "create" ? "New Pillar" : "Edit Pillar" }}
            </h2>
            <AppIconButton label="Close" @click="closePillarModal"><XMarkIcon class="w-4 h-4" /></AppIconButton>
          </div>
          <form @submit.prevent="submitPillar">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Pillar Name</label>
                <input v-model="pillarForm.name" type="text" :class="inputClass" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Outcome Statement</label>
                <textarea v-model="pillarForm.outcome_statement" rows="3" :class="inputClass" />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closePillarModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>

      <!-- Strategy Modal -->
      <div v-if="showStrategyModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ strategyModalMode === "create" ? "New Strategy" : "Edit Strategy" }}
            </h2>
            <AppIconButton label="Close" @click="closeStrategyModal"><XMarkIcon class="w-4 h-4" /></AppIconButton>
          </div>
          <form @submit.prevent="submitStrategy">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Pillar</label>
                <select v-model="strategyForm.dost_pillar_id" :class="inputClass" required>
                  <option :value="null" disabled>Select a pillar</option>
                  <option v-for="pillar in pillars" :key="pillar.id" :value="pillar.id">{{ pillar.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">PSHS Program (Agency Org Outcome)</label>
                <select v-model="strategyForm.agency_outcome_id" :class="inputClass">
                  <option :value="null">Not yet linked</option>
                  <option v-for="outcome in agencyOutcomes" :key="outcome.id" :value="outcome.id">{{ outcome.outcome }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Strategy Name</label>
                <input v-model="strategyForm.name" type="text" :class="inputClass" required />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closeStrategyModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>

      <!-- Sub-Strategy Modal -->
      <div v-if="showSubStrategyModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ subStrategyModalMode === "create" ? "New Sub-Strategy" : "Edit Sub-Strategy" }}
            </h2>
            <AppIconButton label="Close" @click="closeSubStrategyModal"><XMarkIcon class="w-4 h-4" /></AppIconButton>
          </div>
          <form @submit.prevent="submitSubStrategy">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea v-model="subStrategyForm.description" rows="4" :class="inputClass" required />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closeSubStrategyModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Add the nav entry**

In `resources/js/Layouts/navigation.js`, add `FlagIcon` is already imported (confirm at the top `import { ..., FlagIcon, ... } from "@heroicons/vue/24/outline";` — if it isn't already in that import list, add it). Then add a new entry to the Performance Management `children` array (next to the existing `"Agency Org Outcome"` entry, ~line 656):

```js
      {
        label: "DOST Strategic Plan",
        routeName: "dost-strategic-plan.index",
        href: route("dost-strategic-plan.index"),
        icon: FlagIcon,
        permissions: ["ipcr.view"],
      },
      {
        label: "Agency Org Outcome",
        routeName: "outcome.index",
        href: route("outcome.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.view"],
      },
```

- [ ] **Step 4: Build and manually verify**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or the project's standard `build` skill) and confirm it completes with no errors.

Then, with the dev stack running (`http://localhost:8080`), log in as an Administrator and manually walk through:
1. Navigate to Performance Mngmt → DOST Strategic Plan. Confirm the empty state shows ("No pillars yet").
2. Create a Pillar (e.g. "DOST Pillar 5: Governance" / "DOST System Governance Strengthened and Harmonized"). Confirm it appears in the list.
3. Expand the Pillar, click "New Strategy", create one with no PSHS Program selected. Confirm it saves and shows an "Unlinked" badge.
4. Edit that Strategy and select a PSHS Program from the Agency Org Outcome dropdown (create one first via the existing Agency Org Outcome screen if the dropdown is empty). Confirm the badge updates to show the linked program.
5. Expand the Strategy, add a Sub-Strategy, confirm it appears and can be edited.
6. Delete the Sub-Strategy, confirm the confirmation dialog appears and it's removed.
7. Delete the Strategy, confirm the dialog mentions the sub-strategy count and it's removed.
8. Delete the Pillar, confirm the dialog mentions the strategy count and everything under it is gone.
9. Log in (or switch role) as a user without `ipcr.view` and confirm the nav item is hidden and the URL returns 403.

- [ ] **Step 5: Run the full backend test suite for this module**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/DostPillarControllerTest.php tests/Feature/DostStrategyControllerTest.php tests/Feature/DostSubStrategyControllerTest.php tests/Feature/DostStrategicPlanCascadeDeleteTest.php tests/Feature/DostStrategicPlanControllerTest.php"`
Expected: PASS (all tests across all 5 files)

- [ ] **Step 6: Commit**

```bash
git add resources/js/Composables/useDostStrategicPlan.js resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue resources/js/Layouts/navigation.js
git commit -m "feat(spms): add DOST Strategic Plan interactive UI and nav entry"
```

---

## Post-implementation

Update memory / handoff notes (outside this plan's scope to do automatically, but worth flagging to the user): the DOST Strategic Plan module is now live at `/dost-strategic-plan`, all three tables ship empty, `dost_strategies.agency_outcome_id` is nullable pending production `AgencyOutcome` data entry. The IPCR/OPCR/DPCR refactor that will consume this data is separate, unstarted future work requiring its own brainstorming/spec pass — do not begin it without an explicit go-ahead.
