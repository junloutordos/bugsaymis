# IPCR V2 Index/Show/PDF Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring IPCR V2's Index page to feature parity with v1's CRUD table (view/delete, sortable columns, search, pagination), and rebuild the Show page into a real document layout matching the actual `IPCRV2.pdf` template (commitment header, Strategic/Core/Support banded table with Core's 4-criterion sub-rows, a page-4-style rating summary section), plus a server-rendered PDF export mirroring OPCR's mPDF pattern.

**Architecture:** No new tables. Two small backend additions (a `destroy()` action and an `IpcrV2SummaryService` that assembles the rating-rollup table from data that already exists), one shared prop addition (`supervisor`/`ocdUser`) threaded through all five role controllers' `show()` methods, a full Vue template rebuild of the Index page and the three shared Show components (Strategic/Core/Support tables become one continuous document table), and a new `IpcrV2PdfService` + blade view + controller mirroring `OpcrPdfService` exactly.

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, mPDF 8, PHPUnit + RefreshDatabase.

**Spec:** `docs/superpowers/specs/2026-09-07-ipcr-v2-design.md` (this plan extends it — the Index/Show/PDF conventions here were approved in-chat as a design addendum, not a separate spec doc, since no new architecture is introduced).

## Global Constraints

- mPDF: always use `sys_get_temp_dir()` for `tempDir`, never `storage_path()` (CLAUDE.md rule).
- Delete rule matches v1's **actual** behavior exactly: `EmployeeIPCRController::EDITABLE_STATUSES = [STATUS_NEW_TARGET, STATUS_RETURNED]` — not the broader `is_mutable` check (that was an imprecise paraphrase during design discussion; v1's real constant is narrower and this plan follows the real constant).
- Core Function's 4-part rubric (student feedback 30% / supervisor feedback 20% / IM development 20% / timeliness 30%) renders as 4 visual sub-rows per subject via `rowspan` on the label cell — this is a Vue template change only, no schema change (the 4 ratings already live as 4 columns on one `ipcr_v2_core_items` row).
- Support Function items stay one row each (the template gives them a single combined criterion per row, not a weighted rubric) — no sub-row change needed there.
- `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<path> && php artisan ..."` runs every artisan/test command; `npm run build` runs on the host.
- Stage files by name when committing, never `git add -A`/`.`.

---

### Task 1: `EmployeeIpcrV2Controller::destroy()` + route

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`
- Modify: `routes/ipcr-v2.php`
- Test: `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php`

**Interfaces:**
- Consumes: `IpcrV2WorkflowService::assertOwner/assertMutable` (existing).
- Produces: route `employee-ipcr-v2.destroy` (`DELETE /employee-ipcr-v2/{id}`), gated `permission:ipcr.v2.submit` (mirrors v1's `ipcr.update`-adjacent gate on the same route family).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php`:

```php
    public function test_owner_can_delete_a_new_target_record(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->delete(route('employee-ipcr-v2.destroy', $record->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('ipcr_v2_records', ['id' => $record->id]);
    }

    public function test_cannot_delete_a_record_once_submitted_for_review(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        ]);

        $response = $this->actingAs($employee)->delete(route('employee-ipcr-v2.destroy', $record->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('ipcr_v2_records', ['id' => $record->id]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php --filter=delete"`
Expected: FAIL — route `employee-ipcr-v2.destroy` not defined.

- [ ] **Step 3: Add the `destroy()` method**

In `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`, add the constant and method:

```php
    private const EDITABLE_STATUSES = [
        IpcrV2WorkflowService::STATUS_NEW_TARGET,
        IpcrV2WorkflowService::STATUS_RETURNED,
    ];

    public function destroy(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_unless(
            in_array($record->status, self::EDITABLE_STATUSES, true),
            403,
            'Only IPCR V2 records that are new or returned for revision can be deleted.'
        );

        $record->delete();

        return back()->with('success', 'IPCR V2 record deleted.');
    }
```

- [ ] **Step 4: Add the route**

In `routes/ipcr-v2.php`, inside the `permission:ipcr.v2.submit` group (alongside `submitReview`/`submitRating`):

```php
        Route::delete('/employee-ipcr-v2/{id}', [EmployeeIpcrV2Controller::class, 'destroy'])->name('employee-ipcr-v2.destroy');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php"`
Expected: PASS (all tests in the file).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php routes/ipcr-v2.php tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): add destroy() for New Target / Returned for Revision records"
```

---

### Task 2: `IpcrV2SummaryService` (rating-rollup table)

**Files:**
- Create: `app/Services/IPCRV2/IpcrV2SummaryService.php`
- Test: `tests/Feature/IPCRV2/IpcrV2SummaryServiceTest.php`

**Interfaces:**
- Consumes: `StrategicFunctionService::currentIndicators()`, `IpcrV2Record::coreItems/supportItems`, `IpcrV2RatingService::adjectivalRating()`.
- Produces: `IpcrV2SummaryService::buildRows(IpcrV2Record $record): array` — `['strategic' => [...], 'core' => [...], 'support' => [...]]`, each row shaped `{label, quality, efficiency, timeliness, average, equivalent}`. Strategic rows are grouped by Program (Agency Outcome), one row per Program, using the **OPCR's own** `rating_quality/rating_efficiency/rating_timeliness/rating_average` columns (real per-quarter-rated figures, not derived). Core rows use `null` for quality/efficiency/timeliness (the 4-part rubric doesn't map onto that 3-axis frame — never force a misleading fit) and `row_average`/its adjectival equivalent. Support rows use their own real `quality_rating/efficiency_rating/timeliness_rating` columns plus `row_average`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\OPCR\OpcrIndicator;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2SummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2SummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_strategic_core_and_support_summary_rows(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        OpcrIndicator::create([
            'fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'x',
            'rating_quality' => 5, 'rating_efficiency' => 4, 'rating_timeliness' => 5, 'rating_average' => 4.67,
        ]);

        $user = User::factory()->create();
        $period = IPCRRatingPeriod::first();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100, 'row_average' => 4.0]);
        $record->supportItems()->create([
            'label' => 'Committee', 'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3, 'row_average' => 4.0,
        ]);

        $rows = (new IpcrV2SummaryService())->buildRows($record->fresh(['coreItems', 'supportItems']));

        $this->assertSame('A. STEM Secondary Education', $rows['strategic'][0]['label']);
        $this->assertEquals(5, $rows['strategic'][0]['quality']);
        $this->assertEquals(4.67, $rows['strategic'][0]['average']);
        $this->assertSame('Very Satisfactory', $rows['strategic'][0]['equivalent']);

        $this->assertSame('Subject 1', $rows['core'][0]['label']);
        $this->assertNull($rows['core'][0]['quality']);
        $this->assertEquals(4.0, $rows['core'][0]['average']);
        $this->assertSame('Very Satisfactory', $rows['core'][0]['equivalent']);

        $this->assertSame('Committee', $rows['support'][0]['label']);
        $this->assertEquals(5, $rows['support'][0]['quality']);
        $this->assertEquals(4.0, $rows['support'][0]['average']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/IpcrV2SummaryServiceTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;

class IpcrV2SummaryService
{
    public function __construct(
        private StrategicFunctionService $strategic = new StrategicFunctionService(),
        private IpcrV2RatingService $rating = new IpcrV2RatingService()
    ) {}

    public function buildRows(IpcrV2Record $record): array
    {
        $record->loadMissing(['coreItems', 'supportItems']);

        return [
            'strategic' => $this->strategicRows(),
            'core' => $record->coreItems->map(fn ($item) => [
                'label' => $item->label,
                'quality' => null,
                'efficiency' => null,
                'timeliness' => null,
                'average' => $item->row_average,
                'equivalent' => $this->rating->adjectivalRating($item->row_average ? (float) $item->row_average : null),
            ])->all(),
            'support' => $record->supportItems->map(fn ($item) => [
                'label' => $item->label,
                'quality' => $item->quality_rating,
                'efficiency' => $item->efficiency_rating,
                'timeliness' => $item->timeliness_rating,
                'average' => $item->row_average,
                'equivalent' => $this->rating->adjectivalRating($item->row_average ? (float) $item->row_average : null),
            ])->all(),
        ];
    }

    private function strategicRows(): array
    {
        return $this->strategic->currentIndicators()
            ->groupBy(fn ($i) => $i->agencyOutcome?->outcome ?? '—')
            ->map(function ($indicators, $programLabel) {
                $average = $indicators->pluck('rating_average')->filter(fn ($v) => $v !== null)->avg();

                return [
                    'label' => $programLabel,
                    'quality' => $indicators->pluck('rating_quality')->filter(fn ($v) => $v !== null)->avg(),
                    'efficiency' => $indicators->pluck('rating_efficiency')->filter(fn ($v) => $v !== null)->avg(),
                    'timeliness' => $indicators->pluck('rating_timeliness')->filter(fn ($v) => $v !== null)->avg(),
                    'average' => $average,
                    'equivalent' => $this->rating->adjectivalRating($average ? (float) $average : null),
                ];
            })
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/IpcrV2SummaryServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2SummaryService.php tests/Feature/IPCRV2/IpcrV2SummaryServiceTest.php
git commit -m "feat(ipcr-v2): add IpcrV2SummaryService for the rating-rollup table"
```

---

### Task 3: Thread `supervisor`/`ocdUser`/`summary` props through all five `show()` methods

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`
- Modify: `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php`
- Modify: `app/Http/Controllers/IPCRV2/PMTIpcrV2Controller.php`
- Modify: `app/Http/Controllers/IPCRV2/HRIpcrV2Controller.php`
- Modify: `app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php`
- Test: `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php` (extend)

**Interfaces:**
- Consumes: `App\Services\PerformanceManagement\IPCRWorkflowService::immediateSupervisorFor(User): ?User` (existing, injected fresh into each controller), `IpcrV2SummaryService::buildRows()` (Task 2).
- Produces: every `show()` Inertia response now includes `supervisor` (`{name, position}` or `null`), `ocdUser` (`{name, position}` or `null`), `summary` (`{strategic, core, support}` array from Task 2).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php`:

```php
    public function test_show_exposes_supervisor_ocd_user_and_summary(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->get(route('employee-ipcr-v2.show', $record->id));

        $response->assertInertia(fn ($page) => $page
            ->has('ocdUser')
            ->has('summary')
            ->has('summary.strategic')
            ->has('summary.core')
            ->has('summary.support')
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php --filter=summary"`
Expected: FAIL — `summary` prop missing.

- [ ] **Step 3: Update `EmployeeIpcrV2Controller`**

Add the constructor dependency and update `show()`:

```php
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private IpcrV2GenerationService $generation,
        private StrategicFunctionService $strategic,
        private \App\Services\PerformanceManagement\IPCRWorkflowService $v1Chain,
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService
    ) {}
```

```php
    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);

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
        ]);
    }
```

- [ ] **Step 4: Apply the same `ocdUser`/`summary` additions to the other four controllers**

Each of `DivisionChiefIpcrV2Controller`, `PMTIpcrV2Controller`, `HRIpcrV2Controller`, `AdminIpcrV2Controller` gets:
- Constructor: add `private \App\Models\User $unused = null` — **no**, instead add `private IpcrV2SummaryService $summaryService = new IpcrV2SummaryService()` as a constructor-promoted default (these controllers don't use Laravel's DI container binding for this service anywhere else, so a `= new IpcrV2SummaryService()` default works identically to `IpcrV2WorkflowService`'s own `IpcrV2GenerationService` pattern from Task 4 of the module plan).
- In `show()`, add before the `return Inertia::render(...)`:
  ```php
  $ocdUser = \App\Models\User::havingRole('OCD')->first();
  ```
  and add to the returned props array:
  ```php
  'ocdUser' => $ocdUser?->only('name', 'position'),
  'summary' => $this->summaryService->buildRows($record),
  ```

For `DivisionChiefIpcrV2Controller` specifically, also add `'supervisor' => $record->user?->only('name', 'position')` is **not** needed — DC's own show page doesn't need a "supervisor" prop (DC *is* the supervisor viewing it); only `EmployeeIpcrV2Controller` needs the `supervisor` prop, since only the employee-facing Show page renders the commitment/signature header block (Task 5).

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php"`
Expected: PASS (all tests in the file).

- [ ] **Step 6: Run the full IPCRV2 suite to confirm no regressions from the constructor signature changes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2"`
Expected: all pass — Laravel resolves the new constructor parameters automatically via the container for every controller (no route or test change needed elsewhere, since none of the other tests assert on a fixed constructor arity).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/IPCRV2 tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): expose supervisor, OCD user, and rating summary on every show()"
```

---

### Task 4: Rebuild `EmployeeIpcrV2Index.vue` on `AppTable`

**Files:**
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue`

**Interfaces:**
- Consumes: `AppTable`, `AppFilterBar`, `AppInput`, `AppBadge`, `AppIconButton`, `PaginationControl`, `EmptyState` (existing components, same import paths as `EmployeeIPCR.vue`), routes `employee-ipcr-v2.destroy` (Task 1), `employee-ipcr-v2.generateTargets`/`.show` (existing).

- [ ] **Step 1: Rewrite the file**

```vue
<script setup>
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppTable from "@/Components/AppTable.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { EyeIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { useSubmit } from "@/Composables/useSubmit"
import Swal from "sweetalert2"

const props = defineProps({
  records: Array,
  openPeriods: Array,
})

const { isSubmitting, submit } = useSubmit()
const selectedPeriod = ref(props.openPeriods[0]?.id ?? null)
const searchQuery = ref("")
const currentPage = ref(1)
const PER_PAGE = 15

const DELETABLE_STATUSES = ["New Target", "Returned for Revision"]

const filteredRecords = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.records
  return props.records.filter(r =>
    (r.period?.label ?? "").toLowerCase().includes(q) || (r.status ?? "").toLowerCase().includes(q)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / PER_PAGE)))
const displayedRecords = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filteredRecords.value.slice(start, start + PER_PAGE)
})

function statusBadgeColor(status) {
  const map = {
    "New Target": "blue",
    "For Review": "amber",
    "Targets Approved": "green",
    "Submitted for Rating": "orange",
    "Rated & For PMT Review": "purple",
    "Submitted to PMT": "purple",
    "PMT Returned for Revision": "red",
    "Submitted to HR": "blue",
    "Approved by PMT": "green",
    "Director Signed": "green",
    "Returned for Revision": "red",
  }
  return map[status] ?? "slate"
}

function generateTargets() {
  submit((opts) => router.post(route("employee-ipcr-v2.generateTargets"), { rating_period_id: selectedPeriod.value }, opts))
}

function viewRecord(record) {
  router.get(route("employee-ipcr-v2.show", record.id))
}

function destroyRecord(record) {
  Swal.fire({
    title: "Are you sure?",
    text: "This IPCR V2 record will be permanently deleted!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc2626",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("employee-ipcr-v2.destroy", record.id), {
        onSuccess: () => Swal.fire({ icon: "success", title: "Deleted", timer: 2000, showConfirmButton: false }),
      })
    }
  })
}
</script>

<template>
  <Head title="My IPCR V2" />
  <AdminLayout title="My IPCR V2">
    <div class="p-6 space-y-5">
      <AppPageHeader title="My IPCR V2" subtitle="Strategic / Core / Support Functions">
        <template #actions>
          <template v-if="openPeriods.length">
            <AppSelect v-model="selectedPeriod" :show-blank="false" class="w-56">
              <option v-for="p in openPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
            </AppSelect>
            <AppButton :disabled="isSubmitting || !selectedPeriod" @click="generateTargets">
              <PlusIcon class="w-4 h-4" /> Generate Targets
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <AppInput v-model="searchQuery" placeholder="Search by period or status..." class="min-w-[180px] flex-1 sm:flex-none sm:w-64" />
      </AppFilterBar>

      <AppTable :is-empty="!displayedRecords.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Period</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Final Rating</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="record in displayedRecords" :key="record.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ record.period?.label }}</td>
          <td class="px-4 py-3"><AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge></td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ record.final_numeric_rating ?? "—" }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1">
              <AppIconButton label="View" @click="viewRecord(record)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="DELETABLE_STATUSES.includes(record.status)" label="Delete" variant="danger" @click="destroyRecord(record)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="record in displayedRecords" :key="'m-' + record.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 truncate">{{ record.period?.label }}</p>
                <p class="text-xs text-slate-500">{{ record.final_numeric_rating ?? "—" }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(record.status)">{{ record.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View" @click="viewRecord(record)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="DELETABLE_STATUSES.includes(record.status)" label="Delete" variant="danger" @click="destroyRecord(record)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCR V2 records found." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Build and confirm no errors**

Run: `npm run build` (from the repo root, host)
Expected: `✓ built` with no errors mentioning `EmployeeIpcrV2Index`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/IPCRV2/EmployeeIpcrV2Index.vue
git commit -m "feat(ipcr-v2): rebuild Index page on AppTable with view/delete actions"
```

---

### Task 5: Rebuild the Show page into a real document layout

**Files:**
- Create: `resources/js/Components/IPCRV2/IpcrV2DocumentHeader.vue`
- Create: `resources/js/Components/IPCRV2/IpcrV2SummarySection.vue`
- Modify: `resources/js/Components/IPCRV2/IpcrV2StrategicSection.vue`
- Modify: `resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue`
- Modify: `resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue`
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/HRIpcrV2Show.vue`
- Create: `resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue` (currently `AdminIpcrV2Controller::show()` reuses `HRIpcrV2Show` — give it its own file now that Show pages carry per-role header context, matching v1's `AdminIPCRShow.vue` being its own file)
- Modify: `app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php` (point at the new page)

**Interfaces:**
- Consumes: `supervisor`/`ocdUser`/`summary` props (Task 3).
- Produces: a single continuous `<table>` per Show page combining what were three separate `AppCard`s, wrapped in one `id="ipcr-v2-printable"` container (used by Task 6's PDF view for consistent structure, and available for a future client-print button if ever wanted — not required by this plan, no `window.print()` call is added per the approved design).

- [ ] **Step 1: Write `IpcrV2DocumentHeader.vue`**

```vue
<script setup>
defineProps({
  employee: Object,
  period: Object,
  supervisor: Object,
  ocdUser: Object,
})
</script>

<template>
  <div class="p-5 border-b border-slate-200">
    <p class="text-xs text-center text-slate-500">Republic of the Philippines</p>
    <p class="text-xs text-center text-slate-500 mb-3">Department of Science and Technology</p>
    <p class="text-base text-center font-semibold mb-4">
      Individual Performance Commitment and Review (IPCR)
    </p>
    <p class="text-sm text-slate-700 mb-6">
      I, <b class="uppercase">{{ employee?.name }}</b>, <b class="uppercase">{{ employee?.position }}</b>,
      of Philippine Science High School – Caraga Region Campus, commit to deliver and agree to be rated on the
      attainment of the following targets in accordance with the indicated measures for the period of
      <b class="uppercase">{{ period?.label }}</b>.
    </p>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border-collapse border border-slate-200">
        <tr class="font-semibold text-slate-700">
          <td class="border border-slate-200 px-3 py-2 text-left w-1/3">Ratee</td>
          <td class="border border-slate-200 px-3 py-2 text-left w-1/3">Reviewed by</td>
          <td class="border border-slate-200 px-3 py-2 text-left w-1/3">Approved by</td>
        </tr>
        <tr>
          <td class="border border-slate-200 px-3 py-6 text-center">
            <b class="uppercase text-slate-800">{{ employee?.name ?? "—" }}</b><br />
            <small class="text-slate-500">{{ employee?.position }}</small>
          </td>
          <td class="border border-slate-200 px-3 py-6 text-center">
            <b class="uppercase text-slate-800">{{ supervisor?.name ?? "—" }}</b><br />
            <small class="text-slate-500">{{ supervisor?.position ?? "Division Chief" }}</small>
          </td>
          <td class="border border-slate-200 px-3 py-6 text-center">
            <b class="uppercase text-slate-800">{{ ocdUser?.name ?? "—" }}</b><br />
            <small class="text-slate-500">{{ ocdUser?.position ?? "Campus Director" }}</small>
          </td>
        </tr>
      </table>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Rewrite `IpcrV2StrategicSection.vue` to render as `<tbody>` rows inside the shared document table (not its own `AppCard`)**

```vue
<script setup>
import { TD } from "@/Composables/useTableClasses.js"

defineProps({
  indicators: { type: Array, default: () => [] },
})
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="7" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Strategic Function (30%)
      </td>
    </tr>
    <tr v-for="indicator in indicators" :key="indicator.id">
      <td :class="TD" class="border border-slate-200">{{ indicator.agency_outcome?.outcome }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.description }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.target }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.displayed_accomplishment ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-400" colspan="2">—</td>
      <td :class="TD" class="border border-slate-200"></td>
    </tr>
    <tr v-if="!indicators.length">
      <td :class="TD" class="border border-slate-200" colspan="7">No OPCR indicators for the current fiscal year yet.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 3: Rewrite `IpcrV2CoreItemsTable.vue` with 4 sub-rows per subject via `rowspan`**

```vue
<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
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

const CRITERIA = [
  { key: "student_feedback_rating", label: "Positive feedback from students (30%)" },
  { key: "supervisor_feedback_rating", label: "Positive feedback from immediate supervisor (20%)" },
  { key: "im_development_rating", label: "Instructional materials development (20%)" },
  { key: "timeliness_rating", label: "Timely submission of forms and documents (30%)" },
]

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateCoreItem", [props.ipcrId, item.id]), {
    student_feedback_rating: item.student_feedback_rating,
    supervisor_feedback_rating: item.supervisor_feedback_rating,
    im_development_rating: item.im_development_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rowAverage(item) {
  const parts = [item.student_feedback_rating, item.supervisor_feedback_rating, item.im_development_rating, item.timeliness_rating]
  if (parts.some(v => v === null || v === undefined)) return "—"
  return (parts[0] * 0.3 + parts[1] * 0.2 + parts[2] * 0.2 + parts[3] * 0.3).toFixed(2)
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td :colspan="canRate ? 7 : 6" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Core Function (50%)
      </td>
    </tr>
    <template v-for="item in items" :key="item.id">
      <tr v-for="(criterion, idx) in CRITERIA" :key="item.id + '-' + criterion.key">
        <td v-if="idx === 0" :rowspan="CRITERIA.length + 1" :class="TD" class="border border-slate-200 align-top font-medium">
          {{ item.label }}<br />
          <small class="text-slate-400">Weight: {{ item.weight_percent ?? "—" }}%</small>
        </td>
        <td :class="TD" class="border border-slate-200">{{ criterion.label }}</td>
        <td :class="TD" class="border border-slate-200">
          <AppTextarea v-if="idx === 0 && isOwner && isMutable" v-model="item.target" @blur="saveEmployeeFields(item)" />
          <span v-else-if="idx === 0">{{ item.target ?? "—" }}</span>
        </td>
        <td :class="TD" class="border border-slate-200">
          <AppTextarea v-if="idx === 0 && isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
          <span v-else-if="idx === 0">{{ item.actual_accomplishment ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="item[criterion.key]" class="border rounded text-xs px-1">
            <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
          </select>
          <span v-else>{{ item[criterion.key] ?? "—" }}</span>
        </td>
      </tr>
      <tr>
        <td :class="TD" class="border border-slate-200 font-semibold" colspan="3">Row Average</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
          {{ item.row_average ?? rowAverage(item) }}
          <button v-if="canRate" type="button" class="ml-2 text-xs text-indigo-600" @click="rate(item)">Save Ratings</button>
        </td>
      </tr>
    </template>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" :colspan="canRate ? 7 : 6">No Core Function rows yet — generate targets from Employee Functions.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 4: Rewrite `IpcrV2SupportItemsTable.vue` as `<tbody>` rows (single row per item, unchanged mechanics, just restyled to match the document table)**

```vue
<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
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
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    actual_accomplishment: item.actual_accomplishment,
    mov_link: item.mov_link,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateSupportItem", [props.ipcrId, item.id]), {
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td :colspan="canRate ? 5 : 4" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Support Function (20%)
      </td>
    </tr>
    <tr v-for="item in items" :key="item.id">
      <td :class="TD" class="border border-slate-200 font-medium">{{ item.label }}</td>
      <td :class="TD" class="border border-slate-200">
        <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
        <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
      </td>
      <td :class="TD" class="border border-slate-200">
        <input v-if="isOwner && isMutable" v-model="item.mov_link" class="border rounded px-2 py-1 text-xs w-full" @blur="saveEmployeeFields(item)" />
        <span v-else>{{ item.mov_link ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ item.row_average ?? "—" }}</td>
      <td v-if="canRate" class="border border-slate-200 px-4 py-3 text-center text-sm">
        <div class="flex gap-1 items-center justify-center">
          <select v-model.number="item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <select v-model.number="item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <select v-model.number="item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <button type="button" class="text-xs text-indigo-600" @click="rate(item)">Save</button>
        </div>
      </td>
    </tr>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" :colspan="canRate ? 5 : 4">No Support Function rows yet.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 5: Write `IpcrV2SummarySection.vue`**

```vue
<script setup>
import AppCard from "@/Components/AppCard.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"

defineProps({
  summary: { type: Object, default: () => ({ strategic: [], core: [], support: [] }) },
})

function fmt(v) {
  return v === null || v === undefined ? "—" : (typeof v === "number" ? v.toFixed(2) : v)
}
</script>

<template>
  <AppCard class="mt-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-4">Rating Summary</h3>
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

    <div class="mt-4 text-xs text-slate-500 italic">
      Legend: 5 - Outstanding &nbsp; 4 - Very Satisfactory &nbsp; 3 - Satisfactory &nbsp; 2 - Unsatisfactory &nbsp; 1 - Poor
    </div>

    <table class="w-full border-collapse border border-slate-200 text-sm mt-6">
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
  </AppCard>
</template>
```

- [ ] **Step 6: Rewrite `EmployeeIpcrV2Show.vue` to assemble the single document table**

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
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  supervisor: Object,
  ocdUser: Object,
  summary: Object,
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
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Function</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Success Indicator</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Actual Accomplishment</th>
              <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="isOwner" :is-mutable="isMutable" />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="isOwner" :is-mutable="isMutable" />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" />

    <div v-if="isOwner && isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'New Target'" :disabled="isSubmitting" @click="submitForReview">Submit for Review</AppButton>
      <AppButton v-if="ipcr.status === 'Targets Approved'" :disabled="isSubmitting" @click="submitForRating">Submit for Rating</AppButton>
    </div>
  </AdminLayout>
</template>
```

Note: the Strategic/Core/Support components now render `<tbody>` fragments and are placed directly inside one `<table>` — the `IpcrV2StrategicSection`/`IpcrV2CoreItemsTable`/`IpcrV2SupportItemsTable` components must each render exactly one root `<tbody>` element (Vue 3 supports multi-root templates, and multiple `<tbody>` elements inside one `<table>` is valid HTML).

- [ ] **Step 7: Apply the equivalent restructuring to `DivisionChiefIpcrV2Show.vue`, `PMTIpcrV2Show.vue`, `HRIpcrV2Show.vue`**

Each becomes the same `<table>` assembly as Step 6 (header block, three `<tbody>` sections, summary section below), differing only in:
- `DivisionChiefIpcrV2Show.vue`: keeps its `approveTargets`/`disapproveTargets`/`submitToPMT` action buttons at the bottom; passes `can-rate` to `IpcrV2CoreItemsTable`/`IpcrV2SupportItemsTable`; passes `:supervisor="null"` and `:ocd-user="ocdUser"` to `IpcrV2DocumentHeader` (DC's own show doesn't need a "supervisor" row distinct from itself — pass the DC's own `ipcr.user` and leave `ocdUser` populated); no "Print PDF" button is required here to keep this task additive-only (Task 6 wires PDF access from the Employee page; extending the button to every role page is a one-line copy the user can request if wanted, not scoped here since the plan's approved design named PDF access as a Show-page capability without specifying every role must surface the button — keep this task's diff minimal).
- `PMTIpcrV2Show.vue` / `HRIpcrV2Show.vue`: `:is-owner="false"`, `:is-mutable="false"` (or `isMutable` prop for PMT's action buttons), no `can-rate`.

Concretely, for `DivisionChiefIpcrV2Show.vue`, replace the three separate component calls with:

```vue
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="null" :ocd-user="ocdUser" />
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-slate-200 text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Function</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Success Indicator</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Actual Accomplishment</th>
              <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="isMutable" can-rate />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="isMutable" can-rate />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" />
```

adding the matching `import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"`, `import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"`, and `summary: Object, ocdUser: Object` to `defineProps`. Apply the same pattern (without `can-rate`, `:is-owner="false"`, `:is-mutable="false"` since PMT/HR never mutate items) to `PMTIpcrV2Show.vue` and `HRIpcrV2Show.vue`.

- [ ] **Step 8: Create `AdminIpcrV2Show.vue`**

Copy `HRIpcrV2Show.vue` verbatim to `resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue` (same read-only shape — matches v1's `AdminIPCRShow.vue` being adapted from `HRIPCRShow.vue`, per existing project convention).

- [ ] **Step 9: Point `AdminIpcrV2Controller::show()` at the new page**

In `app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php`, change:

```php
return Inertia::render('IPCRV2/HRIpcrV2Show', ['ipcr' => $record]);
```

to:

```php
return Inertia::render('IPCRV2/AdminIpcrV2Show', [
    'ipcr' => $record,
    'ocdUser' => \App\Models\User::havingRole('OCD')->first()?->only('name', 'position'),
    'summary' => app(\App\Services\IPCRV2\IpcrV2SummaryService::class)->buildRows($record),
]);
```

- [ ] **Step 10: Build and verify**

Run: `npm run build`
Expected: `✓ built` with no errors.

- [ ] **Step 11: Run the full IPCRV2 backend suite (Vue changes don't have their own test runner in this project — the backend props/data contract is what's tested)**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2 tests/Feature/EmployeeFunctions"`
Expected: all pass, same count as before this task (no backend contract change in this task beyond what Task 3 already covered).

- [ ] **Step 12: Commit**

```bash
git add resources/js/Components/IPCRV2 resources/js/Pages/IPCRV2 app/Http/Controllers/IPCRV2/AdminIpcrV2Controller.php
git commit -m "feat(ipcr-v2): rebuild Show page as a single document table matching the CSC template"
```

---

### Task 6: mPDF export

**Files:**
- Create: `app/Services/IPCRV2/IpcrV2PdfService.php`
- Create: `app/Http/Controllers/IPCRV2/IpcrV2PdfController.php`
- Create: `resources/views/ipcr-v2/pdf.blade.php`
- Modify: `routes/ipcr-v2.php`
- Test: `tests/Feature/IPCRV2/IpcrV2PdfTest.php`

**Interfaces:**
- Consumes: `IpcrV2SummaryService::buildRows()` (Task 2), `IPCRWorkflowService::immediateSupervisorFor()`.
- Produces: route `ipcr-v2-pdf.show` (`GET /ipcr-v2/{id}/pdf`), streaming a PDF byte response (same `StreamedResponse` pattern as `OpcrPdfService`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2PdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_stream_their_own_ipcr_v2_pdf(): void
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $employee = User::factory()->create();
        $employee->roles()->attach($role->id);

        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->get(route('ipcr-v2-pdf.show', $record->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_unrelated_faculty_cannot_stream_another_employees_pdf(): void
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($role->id);

        $other = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $other->id, 'rating_period_id' => $period->id]);

        $this->actingAs($viewer)->get(route('ipcr-v2-pdf.show', $record->id))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/IpcrV2PdfTest.php"`
Expected: FAIL — route `ipcr-v2-pdf.show` not defined.

- [ ] **Step 3: Write `IpcrV2PdfService`**

```php
<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IpcrV2PdfService
{
    public function __construct(
        private StrategicFunctionService $strategic = new StrategicFunctionService(),
        private IpcrV2SummaryService $summary = new IpcrV2SummaryService(),
        private IPCRWorkflowService $chain = new IPCRWorkflowService()
    ) {}

    public function stream(IpcrV2Record $record): StreamedResponse
    {
        $record->loadMissing(['user', 'coreItems', 'supportItems', 'period']);

        $supervisor = $this->chain->immediateSupervisorFor($record->user)
            ?? ($record->user->hasRole('DivisionChief') ? User::havingRole('OCD')->first() : null);
        $ocdUser = User::havingRole('OCD')->first();

        $html = view('ipcr-v2.pdf', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'summary' => $this->summary->buildRows($record),
            'supervisor' => $supervisor,
            'ocdUser' => $ocdUser,
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

        $mpdf->SetTitle('IPCR V2 — ' . $record->user->name . ' — ' . $record->period->label);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'IPCRV2_' . str_replace(' ', '_', $record->user->name) . '.pdf';

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

- [ ] **Step 4: Write `resources/views/ipcr-v2/pdf.blade.php`**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        .center { text-align: center; }
        .band { background: #d1d5db; font-weight: bold; text-transform: uppercase; padding: 4px 6px; }
        .title { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 8px; }
        .preamble { text-align: center; font-size: 9px; margin-bottom: 2px; }
    </style>
</head>
<body>
    <p class="preamble">Republic of the Philippines</p>
    <p class="preamble">Department of Science and Technology</p>
    <p class="title">Individual Performance Commitment and Review (IPCR)</p>
    <p>
        I, <strong>{{ strtoupper($ipcr->user->name) }}</strong>, <strong>{{ strtoupper($ipcr->user->position ?? '') }}</strong>,
        of Philippine Science High School &ndash; Caraga Region Campus, commit to deliver and agree to be rated on the
        attainment of the following targets in accordance with the indicated measures for the period of
        <strong>{{ strtoupper($ipcr->period->label) }}</strong>.
    </p>

    <table>
        <tr>
            <td class="center"><strong>{{ strtoupper($ipcr->user->name) }}</strong><br>{{ $ipcr->user->position }}</td>
            <td class="center"><strong>{{ $supervisor ? strtoupper($supervisor->name) : '&mdash;' }}</strong><br>{{ $supervisor->position ?? 'Division Chief' }}</td>
            <td class="center"><strong>{{ $ocdUser ? strtoupper($ocdUser->name) : '&mdash;' }}</strong><br>{{ $ocdUser->position ?? 'Campus Director' }}</td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th>Function</th>
                <th>Success Indicator</th>
                <th>Target</th>
                <th>Actual Accomplishment</th>
                <th class="center">Rating</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="5" class="band">Strategic Function (30%)</td></tr>
            @foreach ($strategicIndicators as $indicator)
                <tr>
                    <td>{{ $indicator->agencyOutcome?->outcome }}</td>
                    <td>{{ $indicator->description }}</td>
                    <td>{{ $indicator->target }}</td>
                    <td>{{ $indicator->displayed_accomplishment ?? '&mdash;' }}</td>
                    <td class="center">&mdash;</td>
                </tr>
            @endforeach

            <tr><td colspan="5" class="band">Core Function (50%)</td></tr>
            @foreach ($ipcr->coreItems as $item)
                <tr>
                    <td rowspan="5">{{ $item->label }}<br><small>Weight: {{ $item->weight_percent ?? '&mdash;' }}%</small></td>
                    <td>Positive feedback from students (30%)</td>
                    <td rowspan="4">{{ $item->target }}</td>
                    <td rowspan="4">{{ $item->actual_accomplishment }}</td>
                    <td class="center">{{ $item->student_feedback_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td>Positive feedback from immediate supervisor (20%)</td>
                    <td class="center">{{ $item->supervisor_feedback_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td>Instructional materials development (20%)</td>
                    <td class="center">{{ $item->im_development_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td>Timely submission of forms and documents (30%)</td>
                    <td class="center">{{ $item->timeliness_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Row Average</strong></td>
                    <td class="center"><strong>{{ $item->row_average ?? '&mdash;' }}</strong></td>
                </tr>
            @endforeach

            <tr><td colspan="5" class="band">Support Function (20%)</td></tr>
            @foreach ($ipcr->supportItems as $item)
                <tr>
                    <td>{{ $item->label }}</td>
                    <td colspan="2">{{ $item->actual_accomplishment }}</td>
                    <td>{{ $item->mov_link }}</td>
                    <td class="center">{{ $item->row_average ?? '&mdash;' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 10px;"><strong>Rating Summary</strong></p>
    <table>
        <thead>
            <tr>
                <th>Agency Organizational Outcome</th>
                <th class="center">Quality</th>
                <th class="center">Efficiency</th>
                <th class="center">Timeliness</th>
                <th class="center">Average</th>
                <th>Equivalent</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="6" class="band">Strategic Functions (30%)</td></tr>
            @foreach ($summary['strategic'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="center">{{ $row['quality'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['efficiency'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['timeliness'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['average'] ?? '&mdash;' }}</td>
                    <td>{{ $row['equivalent'] ?? '&mdash;' }}</td>
                </tr>
            @endforeach
            <tr><td colspan="6" class="band">Core Functions (50%)</td></tr>
            @foreach ($summary['core'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="center">{{ $row['quality'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['efficiency'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['timeliness'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['average'] ?? '&mdash;' }}</td>
                    <td>{{ $row['equivalent'] ?? '&mdash;' }}</td>
                </tr>
            @endforeach
            <tr><td colspan="6" class="band">Support Functions (20%)</td></tr>
            @foreach ($summary['support'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="center">{{ $row['quality'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['efficiency'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['timeliness'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['average'] ?? '&mdash;' }}</td>
                    <td>{{ $row['equivalent'] ?? '&mdash;' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 6px; font-size: 8px; font-style: italic;">
        Legend: 5 - Outstanding &nbsp; 4 - Very Satisfactory &nbsp; 3 - Satisfactory &nbsp; 2 - Unsatisfactory &nbsp; 1 - Poor
    </p>
</body>
</html>
```

- [ ] **Step 5: Write `IpcrV2PdfController`**

```php
<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2PdfService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;

class IpcrV2PdfController extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

    public function show(Request $request, int $id, IpcrV2PdfService $pdf)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);
        $user = $request->user();

        $isOwner = $record->user_id === $user->id;
        $isBroadlyPrivileged = $user->hasAnyRole(['OCD', 'PMT', 'HR', 'Administrator']);
        $isDivisionChiefInScope = $user->hasRole('DivisionChief') && $record->user?->division_id === $user->division_id;

        abort_unless(
            $isOwner || $isBroadlyPrivileged || $isDivisionChiefInScope || $this->workflow->canManage($user, $record),
            403,
            'You are not authorized to view this IPCR V2.'
        );

        return $pdf->stream($record);
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/ipcr-v2.php`, inside the outer `['web', 'auth', 'pshs.email']` group but gated only by the base view permission (any of the granted roles can reach it; the controller does the real per-record check above):

```php
    Route::middleware('permission:ipcr.v2.view')->group(function () {
        Route::get('/ipcr-v2/{id}/pdf', [\App\Http\Controllers\IPCRV2\IpcrV2PdfController::class, 'show'])->name('ipcr-v2-pdf.show');
    });
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/IpcrV2PdfTest.php"`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/IPCRV2/IpcrV2PdfService.php app/Http/Controllers/IPCRV2/IpcrV2PdfController.php resources/views/ipcr-v2/pdf.blade.php routes/ipcr-v2.php tests/Feature/IPCRV2/IpcrV2PdfTest.php
git commit -m "feat(ipcr-v2): add mPDF export mirroring OpcrPdfService"
```

---

### Task 7: Full regression run

**Files:** none (verification-only task).

- [ ] **Step 1: Run the full IPCRV2/EmployeeFunctions suite**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2 tests/Feature/EmployeeFunctions"`
Expected: all pass.

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 3: Run the full untargeted backend suite (with `-e OTEL_SDK_DISABLED=true` to avoid the known OpenTelemetry memory-exhaustion issue on full runs)**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec -e OTEL_SDK_DISABLED=true php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test"`
Expected: same pre-existing failure count/names as the last known baseline (56 failures, none touching IPCR V2/EmployeeFunctions/IPCRWeightDistribution) plus this plan's new passing tests — confirm no new failures introduced.

---

## Self-Review Notes

- **Spec coverage**: Index view/delete parity with v1 ✓ Task 1/4, Show page document layout with commitment header/signature blocks ✓ Task 5, Core Function 4-criterion sub-rows ✓ Task 5 Step 3, page-4-style rating summary ✓ Task 2/5, mPDF export mirroring `OpcrPdfService` ✓ Task 6.
- **Delete rule correction**: the in-chat design approval described delete as "same as v1 — any mutable record," but v1's actual `EmployeeIPCRController` constant (`EDITABLE_STATUSES`) is narrower (New Target / Returned for Revision only, not the full `is_mutable` range). This plan implements v1's real behavior, since "same as v1" is the controlling intent and the broader description was this plan's own imprecise paraphrase during the design conversation, not a deliberate choice to diverge from v1.
- **Type consistency**: `IpcrV2SummaryService::buildRows()`'s row shape (`label/quality/efficiency/timeliness/average/equivalent`) is used identically in `IpcrV2SummarySection.vue` and `ipcr-v2/pdf.blade.php`.
- **No new permissions/tables**: this plan is purely additive UI/PDF work over the existing IPCR V2 schema and permission set from the module plan — no migration, no seeder changes.
