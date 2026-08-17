# SPMS Fiscal Period Admin Form + Self-Service Shell Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the already-shipped SPMS cascade (OPCR/DPCR/IPCR) actually usable in production by closing two stacked gaps: no Fiscal Period admin form exists, and nothing anywhere creates the initial `Ipcr`/`Dpcr`/`Opcr` record for a user/division/campus.

**Architecture:** Additive only — a new `is_current` toggle on the existing `storeFiscalPeriod` endpoint plus its missing Vue form, and one `store()` action per role-facing controller (`EmployeeIpcrController`, `DivisionChiefDpcrController`, `CampusDirectorOpcrController`) that creates-or-opens the caller's record for the current period of the right cadence.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, MySQL 8.0, PHPUnit (`RefreshDatabase`, real MySQL test DB).

## Global Constraints

- **Additive-only** — no schema change (the `is_current` column already exists from Phase 1, just unreachable). No workflow/rollup/permission changes; this plan only adds the missing entry point in front of the already-working, already-shipped cascade.
- **`is_current` auto-unmark decision** (confirmed with user): marking a new Fiscal Period as current automatically clears `is_current` on any other period sharing the same `cadence`, in a single transaction. Exactly one current period per cadence at all times.
- **Duplicate-safe creation**: every `store()` action is create-or-open — calling it again when a record already exists for that user/division + current period redirects to the existing record rather than erroring or duplicating.
- **Missing-period is a clear validation error, not a 500**: `back()->withErrors(['fiscal_period' => '...'])` pointing the user at Admin Config, matching this app's existing form-error conventions.
- **Out of scope**: the Weight Profile form's hardcoded `level: 'ipcr'` field (no dropdown) — separate pre-existing gap, not blocking (see spec's Non-Goals). Do not touch it in this plan.
- **Worktree**: `git worktree add .worktrees/<name> -b <name> main`, branching explicitly from local `main` (which has all three SPMS phases) — not a plain `EnterWorktree({name})` call, whose default base ref is `origin/main` and would be missing all unpushed-until-now — wait, SPMS is now pushed to origin as of this session, so `EnterWorktree({name})` is safe to use again for this plan. Confirm with `git log origin/main --oneline -1` before relying on this if picking the plan up later.
- **Docker**: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan ..."`.
- **Route constraint gotcha**: the existing `spms/ipcr`, `spms/dpcr`, `spms/opcr` route groups each carry a `->where(['<param>' => '[0-9]+'])` constraint tied to their `{id}` route parameter. Adding a parameterless `POST /` route to the same group is unaffected by that constraint (it only applies to routes using that parameter name) — no need to touch the `.where()` clause.

---

## Task 1: `storeFiscalPeriod` gains an `is_current` toggle with same-cadence auto-unmark

**Files:**
- Modify: `app/Http/Controllers/SPMS/AdminConfigController.php`
- Modify: `tests/Feature/SPMS/AdminConfigControllerTest.php`

**Interfaces:**
- Consumes: `FiscalPeriod` (Phase 1).
- Produces: `storeFiscalPeriod` now accepts an optional `is_current` boolean; when true, wraps the write in a DB transaction that first clears `is_current` on every other `FiscalPeriod` with the same `cadence`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/SPMS/AdminConfigControllerTest.php` (existing file — keep the current two tests, add these three, and add `use App\Models\SPMS\FiscalPeriod;` to the imports):

```php
    public function test_stores_a_fiscal_period(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/spms/admin/fiscal-periods', [
            'cadence' => 'annual',
            'fiscal_year' => 2026,
            'label' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('spms_fiscal_periods', ['label' => 'FY 2026', 'is_current' => false]);
    }

    public function test_marking_a_fiscal_period_current_unmarks_others_of_same_cadence(): void
    {
        $admin = $this->admin();
        $existingCurrent = FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);

        $this->actingAs($admin)->post('/spms/admin/fiscal-periods', [
            'cadence' => 'annual',
            'fiscal_year' => 2027,
            'label' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
        ])->assertRedirect();

        $this->assertFalse((bool) $existingCurrent->fresh()->is_current);
        $this->assertDatabaseHas('spms_fiscal_periods', ['label' => 'FY 2027', 'is_current' => true]);
    }

    public function test_marking_current_does_not_affect_other_cadences(): void
    {
        $admin = $this->admin();
        $semesterCurrent = FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($admin)->post('/spms/admin/fiscal-periods', [
            'cadence' => 'annual',
            'fiscal_year' => 2027,
            'label' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
        ])->assertRedirect();

        $this->assertTrue((bool) $semesterCurrent->fresh()->is_current);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test --filter=AdminConfigControllerTest"`
Expected: the two new `is_current`-behavior tests FAIL (nothing un-marks siblings yet); `test_stores_a_fiscal_period` already PASSES today (no behavior change needed for that one — included for coverage since it didn't exist before).

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/SPMS/AdminConfigController.php`, add `use Illuminate\Support\Facades\DB;` to the imports, then replace `storeFiscalPeriod`:

```php
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
            'is_current' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            if (!empty($validated['is_current'])) {
                FiscalPeriod::where('cadence', $validated['cadence'])->update(['is_current' => false]);
            }
            FiscalPeriod::create($validated);
        });

        return back()->with('success', 'Fiscal period saved.');
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test --filter=AdminConfigControllerTest"`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SPMS/AdminConfigController.php tests/Feature/SPMS/AdminConfigControllerTest.php
git commit -m "feat(spms): add is_current toggle to fiscal period admin endpoint"
```

---

## Task 2: Fiscal Period form on the Admin Config page

**Files:**
- Modify: `resources/js/Pages/SPMS/AdminConfigIndex.vue`

**Interfaces:**
- Consumes: `spms.admin.fiscal-periods.store` route (Task 1), `fiscalPeriods` prop (already passed by `AdminConfigController::index()`, currently unused in the template).

No new backend test — `AdminConfigControllerTest` (Task 1) already covers the endpoint this form calls; this task is purely rendering it.

- [ ] **Step 1: Add the Fiscal Period form and table**

Replace the full contents of `resources/js/Pages/SPMS/AdminConfigIndex.vue`:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ weightProfiles: Array, fiscalPeriods: Array })

const periodForm = useForm({
  cadence: 'semester', fiscal_year: 2026, label: '', start_date: '', end_date: '',
  parent_period_id: null, is_current: false,
})

const submitFiscalPeriod = () => {
  periodForm.post(route('spms.admin.fiscal-periods.store'), {
    preserveScroll: true,
    onSuccess: () => periodForm.reset('label', 'start_date', 'end_date', 'is_current'),
  })
}

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
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">New Fiscal Period</h2>
      <form @submit.prevent="submitFiscalPeriod" class="grid grid-cols-3 gap-3">
        <select v-model="periodForm.cadence" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full">
          <option value="quarter">Quarter</option>
          <option value="semester">Semester</option>
          <option value="annual">Annual</option>
        </select>
        <input v-model.number="periodForm.fiscal_year" type="number" placeholder="Fiscal Year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model="periodForm.label" type="text" placeholder="Label (e.g. FY 2026)"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model="periodForm.start_date" type="date"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <input v-model="periodForm.end_date" type="date"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
        <select v-model="periodForm.parent_period_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full">
          <option :value="null">No parent period</option>
          <option v-for="period in fiscalPeriods" :key="period.id" :value="period.id">{{ period.label }}</option>
        </select>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="periodForm.is_current" type="checkbox" />
          Mark as current for this cadence
        </label>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Save
        </button>
      </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-6">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Fiscal Periods</h2>
      <table class="min-w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <tr v-for="period in fiscalPeriods" :key="period.id">
            <td class="px-2 py-2">{{ period.cadence }}</td>
            <td class="px-2 py-2">{{ period.label }}</td>
            <td class="px-2 py-2">{{ period.fiscal_year }}</td>
            <td class="px-2 py-2">
              <span v-if="period.is_current" class="rounded-full bg-emerald-100 text-emerald-700 px-2 py-1 text-xs font-medium">Current</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
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

    <div class="rounded-lg border border-slate-200 bg-white p-4 mt-6">
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

- [ ] **Step 2: Build the frontend to confirm no compile errors**

Run: `npm run build` (host-side).
Expected: build succeeds.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/SPMS/AdminConfigIndex.vue
git commit -m "feat(spms): add fiscal period form and table to admin config page"
```

---

## Task 3: `EmployeeIpcrController::store()`

**Files:**
- Modify: `app/Http/Controllers/SPMS/EmployeeIpcrController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/SPMS/EmployeeIpcrControllerTest.php`

**Interfaces:**
- Consumes: `FiscalPeriod::current()`/`::ofCadence()` scopes (Phase 1).
- Produces: `POST /spms/ipcr` → `spms.ipcr.store`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/SPMS/EmployeeIpcrControllerTest.php` (existing file — keep current tests, add these):

```php
    public function test_store_creates_ipcr_for_current_semester_period(): void
    {
        $user = $this->actingUserWithPermission();
        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($user)->post('/spms/ipcr')->assertRedirect();

        $this->assertDatabaseHas('spms_ipcrs', ['user_id' => $user->id]);
    }

    public function test_store_redirects_to_existing_ipcr_instead_of_duplicating(): void
    {
        $user = $this->actingUserWithPermission();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);
        $existing = Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $period->id]);

        $this->actingAs($user)->post('/spms/ipcr')->assertRedirect(route('spms.ipcr.show', $existing->id));

        $this->assertSame(1, Ipcr::where('user_id', $user->id)->count());
    }

    public function test_store_errors_when_no_current_semester_period_exists(): void
    {
        $user = $this->actingUserWithPermission();

        $this->actingAs($user)->post('/spms/ipcr')->assertSessionHasErrors('fiscal_period');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test --filter=EmployeeIpcrControllerTest"`
Expected: FAIL — route `spms.ipcr.store` not defined / 404s or 405s.

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/SPMS/EmployeeIpcrController.php`, add after `index()`:

```php
    public function store(): RedirectResponse
    {
        $period = FiscalPeriod::current()->ofCadence('semester')->first();
        if (!$period) {
            return back()->withErrors(['fiscal_period' => 'No current semester fiscal period is configured. Ask an SPMS Admin to set one up.']);
        }

        $existing = Ipcr::where('user_id', Auth::id())->where('fiscal_period_id', $period->id)->first();
        if ($existing) {
            return redirect()->route('spms.ipcr.show', $existing->id);
        }

        $ipcr = Ipcr::create(['user_id' => Auth::id(), 'fiscal_period_id' => $period->id]);

        return redirect()->route('spms.ipcr.show', $ipcr->id)->with('success', 'IPCR created.');
    }
```

Add `use App\Models\SPMS\FiscalPeriod;` to the imports.

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the existing `spms.ipcr.` group (right after `Route::get('/', ...)->name('index');`):

```php
    Route::post('/', [\App\Http\Controllers\SPMS\EmployeeIpcrController::class, 'store'])->name('store');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan route:clear && php artisan test --filter=EmployeeIpcrControllerTest"`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SPMS/EmployeeIpcrController.php routes/web.php tests/Feature/SPMS/EmployeeIpcrControllerTest.php
git commit -m "feat(spms): add self-service IPCR creation"
```

---

## Task 4: `DivisionChiefDpcrController::store()`

**Files:**
- Modify: `app/Http/Controllers/SPMS/DivisionChiefDpcrController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/SPMS/DivisionChiefDpcrControllerTest.php`

**Interfaces:**
- Consumes: `FiscalPeriod::current()`/`::ofCadence()`, `Auth::user()->division_id`.
- Produces: `POST /spms/dpcr` → `spms.dpcr.store`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/SPMS/DivisionChiefDpcrControllerTest.php` (existing file — keep current tests, add `use App\Models\Division;` and `use App\Models\SPMS\FiscalPeriod;` to imports if not already present, then add):

```php
    public function test_store_creates_dpcr_for_own_division_and_current_semester_period(): void
    {
        $ratee = $this->ratee();
        $division = Division::factory()->create();
        $ratee->update(['division_id' => $division->id]);
        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertRedirect();

        $this->assertDatabaseHas('spms_dpcrs', ['division_id' => $division->id, 'ratee_user_id' => $ratee->id]);
    }

    public function test_store_redirects_to_existing_dpcr_instead_of_duplicating(): void
    {
        $ratee = $this->ratee();
        $division = Division::factory()->create();
        $ratee->update(['division_id' => $division->id]);
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);
        $existing = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertRedirect(route('spms.dpcr.show', $existing->id));

        $this->assertSame(1, Dpcr::where('division_id', $division->id)->count());
    }

    public function test_store_errors_when_actor_has_no_division(): void
    {
        $ratee = $this->ratee();
        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertSessionHasErrors('division');
    }

    public function test_store_errors_when_no_current_semester_period_exists(): void
    {
        $ratee = $this->ratee();
        $division = Division::factory()->create();
        $ratee->update(['division_id' => $division->id]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertSessionHasErrors('fiscal_period');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test --filter=DivisionChiefDpcrControllerTest"`
Expected: FAIL — route `spms.dpcr.store` not defined / 404s or 405s.

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/SPMS/DivisionChiefDpcrController.php`, add after `index()`:

```php
    public function store(): RedirectResponse
    {
        $divisionId = Auth::user()->division_id;
        if (!$divisionId) {
            return back()->withErrors(['division' => 'Your account has no division assigned. Contact HR.']);
        }

        $period = FiscalPeriod::current()->ofCadence('semester')->first();
        if (!$period) {
            return back()->withErrors(['fiscal_period' => 'No current semester fiscal period is configured. Ask an SPMS Admin to set one up.']);
        }

        $existing = Dpcr::where('division_id', $divisionId)->where('fiscal_period_id', $period->id)->first();
        if ($existing) {
            return redirect()->route('spms.dpcr.show', $existing->id);
        }

        $dpcr = Dpcr::create([
            'division_id' => $divisionId,
            'fiscal_period_id' => $period->id,
            'ratee_user_id' => Auth::id(),
        ]);

        return redirect()->route('spms.dpcr.show', $dpcr->id)->with('success', 'DPCR created.');
    }
```

Add `use App\Models\SPMS\FiscalPeriod;` to the imports.

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the existing `spms.dpcr.` group (right after `Route::get('/', ...)->name('index');`):

```php
    Route::post('/', [\App\Http\Controllers\SPMS\DivisionChiefDpcrController::class, 'store'])->name('store');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan route:clear && php artisan test --filter=DivisionChiefDpcrControllerTest"`
Expected: PASS (9 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SPMS/DivisionChiefDpcrController.php routes/web.php tests/Feature/SPMS/DivisionChiefDpcrControllerTest.php
git commit -m "feat(spms): add self-service DPCR creation"
```

---

## Task 5: `CampusDirectorOpcrController::store()`

**Files:**
- Modify: `app/Http/Controllers/SPMS/CampusDirectorOpcrController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/SPMS/CampusDirectorOpcrControllerTest.php`

**Interfaces:**
- Consumes: `FiscalPeriod::current()`/`::ofCadence()`.
- Produces: `POST /spms/opcr` → `spms.opcr.store`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/SPMS/CampusDirectorOpcrControllerTest.php` (existing file — keep current tests, add `use App\Models\SPMS\FiscalPeriod;` to imports if not already present, then add):

```php
    public function test_store_creates_opcr_for_current_annual_period(): void
    {
        $ratee = $this->ratee();
        FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);

        $this->actingAs($ratee)->post('/spms/opcr')->assertRedirect();

        $this->assertDatabaseHas('spms_opcrs', ['ratee_user_id' => $ratee->id]);
    }

    public function test_store_redirects_to_existing_opcr_instead_of_duplicating(): void
    {
        $ratee = $this->ratee();
        $period = FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);
        $existing = Opcr::factory()->create(['fiscal_period_id' => $period->id]);

        $this->actingAs($ratee)->post('/spms/opcr')->assertRedirect(route('spms.opcr.show', $existing->id));

        $this->assertSame(1, Opcr::where('fiscal_period_id', $period->id)->count());
    }

    public function test_store_errors_when_no_current_annual_period_exists(): void
    {
        $ratee = $this->ratee();

        $this->actingAs($ratee)->post('/spms/opcr')->assertSessionHasErrors('fiscal_period');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test --filter=CampusDirectorOpcrControllerTest"`
Expected: FAIL — route `spms.opcr.store` not defined / 404s or 405s.

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/SPMS/CampusDirectorOpcrController.php`, add after `index()`:

```php
    public function store(): RedirectResponse
    {
        $period = FiscalPeriod::current()->ofCadence('annual')->first();
        if (!$period) {
            return back()->withErrors(['fiscal_period' => 'No current annual fiscal period is configured. Ask an SPMS Admin to set one up.']);
        }

        $existing = Opcr::where('fiscal_period_id', $period->id)->first();
        if ($existing) {
            return redirect()->route('spms.opcr.show', $existing->id);
        }

        $opcr = Opcr::create(['fiscal_period_id' => $period->id, 'ratee_user_id' => Auth::id()]);

        return redirect()->route('spms.opcr.show', $opcr->id)->with('success', 'OPCR created.');
    }
```

Add `use App\Models\SPMS\FiscalPeriod;` to the imports.

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the existing `spms.opcr.` group (right after `Route::get('/', ...)->name('index');`):

```php
    Route::post('/', [\App\Http\Controllers\SPMS\CampusDirectorOpcrController::class, 'store'])->name('store');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan route:clear && php artisan test --filter=CampusDirectorOpcrControllerTest"`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SPMS/CampusDirectorOpcrController.php routes/web.php tests/Feature/SPMS/CampusDirectorOpcrControllerTest.php
git commit -m "feat(spms): add self-service OPCR creation"
```

---

## Task 6: "Create" buttons and empty states on the three Index pages

**Files:**
- Modify: `resources/js/Pages/SPMS/EmployeeIpcrIndex.vue`
- Modify: `resources/js/Pages/SPMS/DivisionChiefDpcrIndex.vue`
- Modify: `resources/js/Pages/SPMS/CampusDirectorOpcrIndex.vue`

**Interfaces:**
- Consumes: `spms.ipcr.store`, `spms.dpcr.store`, `spms.opcr.store` routes (Tasks 3–5).

No new backend test — the `store()` actions are already covered; this is a pure frontend wiring task, verified by `npm run build`.

- [ ] **Step 1: Add the button and empty state to `EmployeeIpcrIndex.vue`**

Replace the full contents of `resources/js/Pages/SPMS/EmployeeIpcrIndex.vue`:

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
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

const createIpcr = () => {
  router.post(route('spms.ipcr.store'))
}
</script>

<template>
  <Head title="My IPCR (SPMS)" />
  <AdminLayout title="My IPCR (SPMS)">
    <div class="mb-4 flex justify-end">
      <button @click="createIpcr" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Create My IPCR
      </button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
      <p v-if="!ipcrs.length" class="p-6 text-sm text-slate-500">No records yet — click Create to get started.</p>
      <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
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

- [ ] **Step 2: Add the button and empty state to `DivisionChiefDpcrIndex.vue`**

Replace the full contents of `resources/js/Pages/SPMS/DivisionChiefDpcrIndex.vue`:

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ dpcrs: Array })

const statusBadgeColor = (status) => ({
  'Draft': 'bg-slate-100 text-slate-700',
  'Submitted to Reviewer': 'bg-amber-100 text-amber-700',
  'Reviewed': 'bg-blue-100 text-blue-700',
  'Submitted to Approver': 'bg-amber-100 text-amber-700',
  'Approved': 'bg-emerald-100 text-emerald-700',
  'Returned': 'bg-rose-100 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-700')

const createDpcr = () => {
  router.post(route('spms.dpcr.store'))
}
</script>

<template>
  <Head title="My DPCR (SPMS)" />
  <AdminLayout title="My DPCR (SPMS)">
    <div class="mb-4 flex justify-end">
      <button @click="createDpcr" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Create My DPCR
      </button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
      <p v-if="!dpcrs.length" class="p-6 text-sm text-slate-500">No records yet — click Create to get started.</p>
      <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Division</th>
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Final Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="dpcr in dpcrs" :key="dpcr.id">
            <td class="px-4 py-3">{{ dpcr.division?.division_name }}</td>
            <td class="px-4 py-3">{{ dpcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeColor(dpcr.status)">
                {{ dpcr.status }}
              </span>
            </td>
            <td class="px-4 py-3">{{ dpcr.final_rating ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.dpcr.show', dpcr.id)" class="text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Add the button and empty state to `CampusDirectorOpcrIndex.vue`**

Replace the full contents of `resources/js/Pages/SPMS/CampusDirectorOpcrIndex.vue`:

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ opcrs: Array })

const statusBadgeColor = (status) => ({
  'Draft': 'bg-slate-100 text-slate-700',
  'Submitted to Executive Director': 'bg-amber-100 text-amber-700',
  'ED Approved': 'bg-emerald-100 text-emerald-700',
  'Returned': 'bg-rose-100 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-700')

const createOpcr = () => {
  router.post(route('spms.opcr.store'))
}
</script>

<template>
  <Head title="My OPCR (SPMS)" />
  <AdminLayout title="My OPCR (SPMS)">
    <div class="mb-4 flex justify-end">
      <button @click="createOpcr" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Create OPCR
      </button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
      <p v-if="!opcrs.length" class="p-6 text-sm text-slate-500">No records yet — click Create to get started.</p>
      <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Fiscal Year</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Final Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="opcr in opcrs" :key="opcr.id">
            <td class="px-4 py-3">{{ opcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeColor(opcr.status)">
                {{ opcr.status }}
              </span>
            </td>
            <td class="px-4 py-3">{{ opcr.final_rating ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.opcr.show', opcr.id)" class="text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 4: Build the frontend to confirm no compile errors**

Run: `npm run build` (host-side).
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/SPMS/EmployeeIpcrIndex.vue resources/js/Pages/SPMS/DivisionChiefDpcrIndex.vue resources/js/Pages/SPMS/CampusDirectorOpcrIndex.vue
git commit -m "feat(spms): add self-service create buttons and empty states to index pages"
```

---

## Task 7: End-to-end tests proving `store()` feeds into the existing cascade

**Files:**
- Modify: `tests/Feature/SPMS/IpcrFullWorkflowTest.php`
- Modify: `tests/Feature/SPMS/DpcrFullWorkflowTest.php`
- Modify: `tests/Feature/SPMS/OpcrFullWorkflowTest.php`

**Interfaces:**
- Consumes: `store()` actions from Tasks 3–5, the already-tested workflow chains from Phases 1–3.

- [ ] **Step 1: Add a store-first variant to `IpcrFullWorkflowTest`**

Add this test method to the existing `IpcrFullWorkflowTest` class:

```php
    public function test_self_service_store_then_full_lifecycle(): void
    {
        $facultyRole = Role::create(['name' => 'Faculty']);
        $reviewerRole = Role::create(['name' => 'DivisionChief']);
        $managePermission = Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $reviewPermission = Permission::create(['name' => 'spms.ipcr.review', 'module' => 'SPMS']);
        $facultyRole->permissions()->attach($managePermission->id);
        $reviewerRole->permissions()->attach($reviewPermission->id);

        $employee = User::factory()->create();
        $employee->roles()->attach($facultyRole->id);

        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($employee)->post('/spms/ipcr')->assertRedirect();
        $ipcr = Ipcr::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(Ipcr::STATUS_DRAFT_TARGET, $ipcr->status);

        // Re-clicking Create opens the same record, no duplicate
        $this->actingAs($employee)->post('/spms/ipcr')->assertRedirect(route('spms.ipcr.show', $ipcr->id));
        $this->assertSame(1, Ipcr::where('user_id', $employee->id)->count());
    }
```

Add `use App\Models\SPMS\FiscalPeriod;` to the imports if not already present.

- [ ] **Step 2: Add a store-first variant to `DpcrFullWorkflowTest`**

Add this test method to the existing `DpcrFullWorkflowTest` class:

```php
    public function test_self_service_store_then_full_lifecycle(): void
    {
        $dcRole = Role::create(['name' => 'DivisionChief']);
        $dcManage = Permission::create(['name' => 'spms.dpcr.manage', 'module' => 'SPMS']);
        $dcRole->permissions()->attach($dcManage->id);

        $division = Division::factory()->create();
        $divisionChief = User::factory()->create(['division_id' => $division->id]);
        $divisionChief->roles()->attach($dcRole->id);

        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($divisionChief)->post('/spms/dpcr')->assertRedirect();
        $dpcr = Dpcr::where('division_id', $division->id)->firstOrFail();
        $this->assertSame(Dpcr::STATUS_DRAFT, $dpcr->status);
        $this->assertSame($divisionChief->id, $dpcr->ratee_user_id);

        $this->actingAs($divisionChief)->post('/spms/dpcr')->assertRedirect(route('spms.dpcr.show', $dpcr->id));
        $this->assertSame(1, Dpcr::where('division_id', $division->id)->count());
    }
```

- [ ] **Step 3: Add a store-first variant to `OpcrFullWorkflowTest`**

Add this test method to the existing `OpcrFullWorkflowTest` class:

```php
    public function test_self_service_store_then_full_lifecycle(): void
    {
        $ocdRole = Role::create(['name' => 'OCD']);
        $opcrManage = Permission::create(['name' => 'spms.opcr.manage', 'module' => 'SPMS']);
        $ocdRole->permissions()->attach($opcrManage->id);

        $campusDirector = User::factory()->create();
        $campusDirector->roles()->attach($ocdRole->id);

        FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);

        $this->actingAs($campusDirector)->post('/spms/opcr')->assertRedirect();
        $opcr = Opcr::where('ratee_user_id', $campusDirector->id)->firstOrFail();
        $this->assertSame(Opcr::STATUS_DRAFT, $opcr->status);

        $this->actingAs($campusDirector)->post('/spms/opcr')->assertRedirect(route('spms.opcr.show', $opcr->id));
        $this->assertSame(1, Opcr::where('ratee_user_id', $campusDirector->id)->count());
    }
```

- [ ] **Step 4: Run all three tests to verify they pass**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test --filter=IpcrFullWorkflowTest && php artisan test --filter=DpcrFullWorkflowTest && php artisan test --filter=OpcrFullWorkflowTest"`
Expected: PASS (2 tests each — the original full-lifecycle test plus the new store-first one)

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/SPMS/IpcrFullWorkflowTest.php tests/Feature/SPMS/DpcrFullWorkflowTest.php tests/Feature/SPMS/OpcrFullWorkflowTest.php
git commit -m "test(spms): add self-service-store-then-full-lifecycle coverage at all three levels"
```

---

## Task 8: Full SPMS regression + route/schema sanity check

**Files:** none created — verification only.

- [ ] **Step 1: Run the full SPMS test suite**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/SPMS tests/Unit/SPMS"`
Expected: all Phase 1–3 SPMS tests plus this plan's new tests pass — 98 (post-Phase-3 baseline) + 3 (Task 1) + 3 (Task 3) + 4 (Task 4) + 3 (Task 5) + 3 (Task 7) = 114, zero regressions.

- [ ] **Step 2: Confirm route cache compiles with no conflicts**

Run: `docker compose --project-directory /Users/junlou/bugsaymis-docker exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan route:cache && php artisan route:clear"`
Expected: no errors.

- [ ] **Step 3: Lint all modified PHP files**

Use the `lint` skill (`php -l` syntax check) against every file touched in Tasks 1–7.

- [ ] **Step 4: Manual smoke check of the new admin form (optional but recommended given this fixes a live-in-prod gap)**

Since the app's Google-OAuth-only login blocks agent browser self-testing, this step is for the human partner: after merging/deploying, visit `/spms/admin`, create a `semester` and an `annual` Fiscal Period both marked "current", then visit `/spms/ipcr`, `/spms/dpcr`, `/spms/opcr` and click each "Create" button to confirm records actually appear.

No commit for this task — verification only. If anything fails, fix it under the task that introduced the regression and re-commit there.
