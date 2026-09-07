# IPCR V2 Strategic Function DOST Columns + Q/E/T/A + Remarks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the IPCR V2 Show page's shared table (and its PDF twin) into an 11-column grid — Function (Strategy) / Output-Outcomes (Sub Strategy, Program) / Success Indicator / Target / Actual Accomplishment / Rating (Q, E, T, A) / Remarks — matching v1's column shape and using OPCR's real DOST-tagging data for Strategic rows.

**Architecture:** One backend change (extend `StrategicFunctionService`'s eager-load list so the existing `AgencyOutcome::dost_strategy_names_joined`/`dost_sub_strategy_descriptions_joined` accessors — already shipped, already correct, just never loaded here — resolve for IPCR V2). Everything else is a column-layout rebuild of the three shared Vue table components, the shared `<thead>` on all five role Show pages, and the PDF blade view, using data that already exists on `OpcrIndicator`/`IpcrV2CoreItem`/`IpcrV2SupportItem`.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>`, mPDF 8 (blade), PHPUnit + RefreshDatabase.

**Spec:** `docs/superpowers/specs/2026-09-07-ipcr-v2-design.md` (this plan is an approved-in-chat addendum, following the same "no new architecture, extend the shipped design" pattern as the two prior addenda).

## Global Constraints

- The DOST chain for a Strategic row is **`$indicator->performanceIndicator?->agencyOutcome ?? $indicator->agencyOutcome`**, never `$indicator->dost_sub_strategy_id`/`subStrategy` — that column exists on `opcr_indicators` but is vestigial from an abandoned OPCR design; the real, shipped mechanism (confirmed in `app/Services/OPCR/OpcrPdfRowGrouper.php`) tags DOST Pillar/Strategy/Sub-Strategy onto the **Agency Outcome (Program)** node, not the indicator directly.
- Program (the second Output/Outcomes sub-column) always comes from **`$indicator->agencyOutcome?->outcome`** — the indicator's own top-level Program — never from the DOST-source object above (same rule `OpcrPdfRowGrouper` enforces: `'program' => $i->agencyOutcome?->outcome` is a separate key from the DOST-derived ones).
- Core Function's single per-criterion rating goes in the **A (Average)** column only; Q/E/T stay blank for Core rows — a single criterion score is not a quality/efficiency/timeliness breakdown, and forcing one would misrepresent the data (same principle already applied when `IpcrV2SummaryService` was built).
- Support Function's existing `quality_rating`/`efficiency_rating`/`timeliness_rating` columns map directly onto the real Q/E/T columns — no data-shape change needed there, just moving three already-correct values out of one combined cell into three real columns.
- `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan ..."` runs every artisan/test command; `npm run build` runs on the host.
- Stage files by name when committing, never `git add -A`/`.`.

---

### Task 1: Extend `StrategicFunctionService`'s eager-loads for the DOST chain

**Files:**
- Modify: `app/Services/IPCRV2/StrategicFunctionService.php`
- Test: `tests/Feature/IPCRV2/StrategicFunctionServiceTest.php`

**Interfaces:**
- Produces: `StrategicFunctionService::currentIndicators()` now returns `OpcrIndicator` models whose `performanceIndicator.agencyOutcome` (and `.parent`) relations are eager-loaded deeply enough for `AgencyOutcome::dost_strategy_names_joined` / `dost_sub_strategy_descriptions_joined` (existing accessors) to resolve real text instead of `null`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/IPCRV2/StrategicFunctionServiceTest.php`:

```php
    public function test_current_indicators_resolve_dost_strategy_and_sub_strategy_text(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $pillar = \App\Models\DostPillar::create(['name' => 'Pillar 1']);
        $strategy = \App\Models\DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1: Achieve quality science education']);
        \App\Models\DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Institutionalized FORWARD program']);

        $program = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $program->dostStrategies()->attach($strategy->id);

        $performanceIndicator = \App\Models\PerformanceIndicator::create(['agency_outcome_id' => $program->id, 'description' => 'PI 1']);

        OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $program->id,
            'performance_indicator_id' => $performanceIndicator->id,
            'description' => 'Indicator 1',
        ]);

        $result = (new StrategicFunctionService())->currentIndicators();

        $source = $result->first()->performanceIndicator?->agencyOutcome ?? $result->first()->agencyOutcome;
        $this->assertSame('Strategy 1: Achieve quality science education', $source->dost_strategy_names_joined);
        $this->assertSame('Institutionalized FORWARD program', $source->dost_sub_strategy_descriptions_joined);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/StrategicFunctionServiceTest.php --filter=dost"`
Expected: FAIL — `dost_strategy_names_joined` returns `null` because the required relations (`dostStrategies`, `dostStrategies.subStrategies`) aren't eager-loaded yet.

- [ ] **Step 3: Extend the eager-load list**

In `app/Services/IPCRV2/StrategicFunctionService.php`, change `currentIndicators()`'s `->with([...])` call:

```php
    public function currentIndicators(): Collection
    {
        $year = $this->currentFiscalYear();
        if (! $year) {
            return collect();
        }

        return OpcrIndicator::forFiscalYear($year)
            ->with([
                'agencyOutcome',
                'performanceIndicator.agencyOutcome.dostStrategies.pillar',
                'performanceIndicator.agencyOutcome.dostStrategies.subStrategies',
                'performanceIndicator.agencyOutcome.parent.dostStrategies.pillar',
                'performanceIndicator.agencyOutcome.parent.dostStrategies.subStrategies',
                'actuals',
            ])
            ->get()
            ->sortBy(fn ($i) => $i->agencyOutcome?->outcome ?? '')
            ->values();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2/StrategicFunctionServiceTest.php"`
Expected: PASS (both the original test and the new one).

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/StrategicFunctionService.php tests/Feature/IPCRV2/StrategicFunctionServiceTest.php
git commit -m "feat(ipcr-v2): eager-load the DOST chain so Strategic rows can show Strategy/Sub-Strategy"
```

---

### Task 2: Rebuild `IpcrV2StrategicSection.vue` for the 11-column grid

**Files:**
- Modify: `resources/js/Components/IPCRV2/IpcrV2StrategicSection.vue`

**Interfaces:**
- Consumes: `indicator.performance_indicator?.agency_outcome` / `indicator.agency_outcome` (Inertia's snake_case serialization of the Task 1 eager-loads), `indicator.rating_quality/rating_efficiency/rating_timeliness/rating_average/remarks` (existing `OpcrIndicator` columns, no backend change).
- Produces: each `<tr>` renders 11 `<td>`s in the order Function / Sub Strategy / Program / Success Indicator / Target / Actual Accomplishment / Q / E / T / A / Remarks, matching the new shared header from Task 5.

- [ ] **Step 1: Rewrite the file**

```vue
<script setup>
import { TD } from "@/Composables/useTableClasses.js"

defineProps({
  indicators: { type: Array, default: () => [] },
})

function dostSource(indicator) {
  return indicator.performance_indicator?.agency_outcome ?? indicator.agency_outcome
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Strategic Function (30%)
      </td>
    </tr>
    <tr v-for="indicator in indicators" :key="indicator.id">
      <td :class="TD" class="border border-slate-200">{{ dostSource(indicator)?.dost_strategy_names_joined ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ dostSource(indicator)?.dost_sub_strategy_descriptions_joined ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.agency_outcome?.outcome ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.description }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.target }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.displayed_accomplishment ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ indicator.rating_quality ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ indicator.rating_efficiency ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ indicator.rating_timeliness ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">{{ indicator.rating_average ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.remarks ?? "—" }}</td>
    </tr>
    <tr v-if="!indicators.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No OPCR indicators for the current fiscal year yet.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2StrategicSection.vue
git commit -m "feat(ipcr-v2): show Strategy/Sub-Strategy/Program and real Q/E/T/A on Strategic rows"
```

---

### Task 3: Rebuild `IpcrV2CoreItemsTable.vue` for the 11-column grid

**Files:**
- Modify: `resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue`

**Interfaces:**
- Consumes: `item.remarks` (existing `ipcr_v2_core_items.remarks` column, already accepted by `DivisionChiefIpcrV2Controller::rateCoreItem()`'s validated payload — this task is the first to expose it in the UI).
- Produces: each subject's 5-row group (4 criteria + 1 average row) fills the same 11 columns as Task 2 — Sub Strategy/Program show "—" (not applicable to Core), Q/E/T stay blank per the approved design, the single criterion rating goes in the A column, and a Remarks input appears on the average row when `canRate`.

- [ ] **Step 1: Rewrite the file**

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
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Core Function (50%)
      </td>
    </tr>
    <template v-for="item in items" :key="item.id">
      <tr v-for="(criterion, idx) in CRITERIA" :key="item.id + '-' + criterion.key">
        <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top font-medium">
          {{ item.label }}<br />
          <small class="text-slate-400">Weight: {{ item.weight_percent ?? "—" }}%</small>
        </td>
        <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td :class="TD" class="border border-slate-200">{{ criterion.label }}</td>
        <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="item.target" @blur="saveEmployeeFields(item)" />
          <span v-else>{{ item.target ?? "—" }}</span>
        </td>
        <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
          <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="item[criterion.key]" class="border rounded text-xs px-1">
            <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
          </select>
          <span v-else>{{ item[criterion.key] ?? "—" }}</span>
        </td>
        <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top">
          <input v-if="canRate" v-model="item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
          <span v-else>{{ item.remarks ?? "—" }}</span>
        </td>
      </tr>
      <tr>
        <td :class="TD" class="border border-slate-200 font-semibold" colspan="3">Row Average</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
          {{ item.row_average ?? rowAverage(item) }}
          <button v-if="canRate" type="button" class="ml-2 text-xs text-indigo-600" @click="rate(item)">Save Ratings</button>
        </td>
      </tr>
    </template>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No Core Function rows yet — generate targets from Employee Functions.</td>
    </tr>
  </tbody>
</template>
```

Note the column count per row: row 1 of each subject has 3 rowspan-5 cells (Function, Sub Strategy dash, Program dash) + 1 criterion label + 2 rowspan-4 cells (Target, Actual) + 4 rating cells (—, —, —, value) + 1 rowspan-5 Remarks cell = 11 logical columns, matching the shared header. Rows 2-4 render only the criterion label + 4 rating cells (8 columns) since the other 3 are covered by rowspan from row 1 — 8 + the 3 rowspanned = 11. The average row renders a colspan-3 label + 3 dash cells + 1 average cell = 7 + the 4 rowspanned (Function/SubStrategy/Program/Remarks all still spanning from row 1) = 11.

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue
git commit -m "feat(ipcr-v2): fit Core Function rows into the 11-column grid, add Remarks input"
```

---

### Task 4: Rebuild `IpcrV2SupportItemsTable.vue` for the 11-column grid

**Files:**
- Modify: `resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue`

**Interfaces:**
- Consumes: `item.quality_rating/efficiency_rating/timeliness_rating/row_average/remarks` (all existing `ipcr_v2_support_items` columns).
- Produces: real Q/E/T columns (previously squeezed into one cell with the rate button), a dedicated Average column, and a Remarks input when `canRate`.

- [ ] **Step 1: Rewrite the file**

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
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Support Function (20%)
      </td>
    </tr>
    <tr v-for="item in items" :key="item.id">
      <td :class="TD" class="border border-slate-200 font-medium">{{ item.label }}</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-300">—</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-300">—</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-400">100% delivered</td>
      <td class="border border-slate-200 px-4 py-3 text-sm text-slate-300">—</td>
      <td :class="TD" class="border border-slate-200">
        <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
        <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
        <input v-if="isOwner && isMutable" v-model="item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(item)" />
        <small v-else-if="item.mov_link" class="block text-slate-400 mt-1">MOV: {{ item.mov_link }}</small>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ item.quality_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ item.efficiency_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ item.timeliness_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        {{ item.row_average ?? "—" }}
        <button v-if="canRate" type="button" class="block mt-1 text-xs text-indigo-600" @click="rate(item)">Save</button>
      </td>
      <td :class="TD" class="border border-slate-200">
        <input v-if="canRate" v-model="item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
        <span v-else>{{ item.remarks ?? "—" }}</span>
      </td>
    </tr>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No Support Function rows yet.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue
git commit -m "feat(ipcr-v2): split Support Function's Q/E/T into real columns, add Average and Remarks"
```

---

### Task 5: Update the shared `<thead>` on all five Show pages

**Files:**
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/HRIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue`

**Interfaces:**
- Produces: a 2-row `<thead>` with 11 physical columns per row, matching the `<tbody>` column counts from Tasks 2-4.

- [ ] **Step 1: Replace the `<thead>` block in every one of the five files**

In each file, replace:

```vue
          <thead class="bg-slate-50/80">
            <tr>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Function</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Success Indicator</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Actual Accomplishment</th>
              <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
            </tr>
          </thead>
```

with:

```vue
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
```

- [ ] **Step 2: Build and confirm no errors**

Run: `npm run build`
Expected: `✓ built` with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue resources/js/Pages/IPCRV2/HRIpcrV2Show.vue resources/js/Pages/IPCRV2/AdminIpcrV2Show.vue
git commit -m "feat(ipcr-v2): update shared table header to the 11-column grid on every role's Show page"
```

---

### Task 6: Update the PDF blade view to match

**Files:**
- Modify: `resources/views/ipcr-v2/pdf.blade.php`

**Interfaces:**
- Consumes: the same eager-loaded `performanceIndicator.agencyOutcome`/`agencyOutcome` chain from Task 1 (the PDF service already loads `strategicIndicators` via `StrategicFunctionService`, so no service change is needed here — just reading the now-populated accessors).

- [ ] **Step 1: Replace the main table's `<thead>` and Strategic/Core/Support `<tbody>` rows**

Replace the existing single main `<table>` block in `resources/views/ipcr-v2/pdf.blade.php` (the one with `<th>Function</th><th>Success Indicator</th>...`) with:

```blade
    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th rowspan="2">Function</th>
                <th colspan="2" class="center">Output/Outcomes</th>
                <th rowspan="2">Success Indicator</th>
                <th rowspan="2">Target</th>
                <th rowspan="2">Actual Accomplishment</th>
                <th colspan="4" class="center">Rating</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th class="center">Sub Strategy</th>
                <th class="center">Program</th>
                <th class="center">Q</th>
                <th class="center">E</th>
                <th class="center">T</th>
                <th class="center">A</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="11" class="band">Strategic Function (30%)</td></tr>
            @foreach ($strategicIndicators as $indicator)
                @php($source = $indicator->performanceIndicator?->agencyOutcome ?? $indicator->agencyOutcome)
                <tr>
                    <td>{{ $source?->dost_strategy_names_joined ?? '&mdash;' }}</td>
                    <td>{{ $source?->dost_sub_strategy_descriptions_joined ?? '&mdash;' }}</td>
                    <td>{{ $indicator->agencyOutcome?->outcome ?? '&mdash;' }}</td>
                    <td>{{ $indicator->description }}</td>
                    <td>{{ $indicator->target }}</td>
                    <td>{{ $indicator->displayed_accomplishment ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_quality ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_efficiency ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_timeliness ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_average ?? '&mdash;' }}</td>
                    <td>{{ $indicator->remarks ?? '&mdash;' }}</td>
                </tr>
            @endforeach

            <tr><td colspan="11" class="band">Core Function (50%)</td></tr>
            @foreach ($ipcr->coreItems as $item)
                <tr>
                    <td rowspan="5">{{ $item->label }}<br><small>Weight: {{ $item->weight_percent ?? '&mdash;' }}%</small></td>
                    <td rowspan="5">&mdash;</td>
                    <td rowspan="5">&mdash;</td>
                    <td>Positive feedback from students (30%)</td>
                    <td rowspan="4">{{ $item->target }}</td>
                    <td rowspan="4">{{ $item->actual_accomplishment }}</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">{{ $item->student_feedback_rating ?? '&mdash;' }}</td>
                    <td rowspan="5">{{ $item->remarks ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td>Positive feedback from immediate supervisor (20%)</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">{{ $item->supervisor_feedback_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td>Instructional materials development (20%)</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">{{ $item->im_development_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td>Timely submission of forms and documents (30%)</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">{{ $item->timeliness_rating ?? '&mdash;' }}</td>
                </tr>
                <tr>
                    <td colspan="3"><strong>Row Average</strong></td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center">&mdash;</td>
                    <td class="center"><strong>{{ $item->row_average ?? '&mdash;' }}</strong></td>
                </tr>
            @endforeach

            <tr><td colspan="11" class="band">Support Function (20%)</td></tr>
            @foreach ($ipcr->supportItems as $item)
                <tr>
                    <td>{{ $item->label }}</td>
                    <td>&mdash;</td>
                    <td>&mdash;</td>
                    <td>100% delivered</td>
                    <td>&mdash;</td>
                    <td>{{ $item->actual_accomplishment }} @if($item->mov_link) <br><small>MOV: {{ $item->mov_link }}</small> @endif</td>
                    <td class="center">{{ $item->quality_rating ?? '&mdash;' }}</td>
                    <td class="center">{{ $item->efficiency_rating ?? '&mdash;' }}</td>
                    <td class="center">{{ $item->timeliness_rating ?? '&mdash;' }}</td>
                    <td class="center">{{ $item->row_average ?? '&mdash;' }}</td>
                    <td>{{ $item->remarks ?? '&mdash;' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/ipcr-v2/pdf.blade.php
git commit -m "feat(ipcr-v2): match the PDF export to the new 11-column table"
```

---

### Task 7: Full regression run

**Files:** none (verification-only task).

- [ ] **Step 1: Run the IPCRV2/EmployeeFunctions suite**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis/<worktree-path> && php artisan test tests/Feature/IPCRV2 tests/Feature/EmployeeFunctions"`
Expected: all pass — this task changes no backend contracts beyond Task 1's eager-load extension, which the existing `IpcrV2PdfTest`/`EmployeeIpcrV2ControllerTest` exercise indirectly (they call `show()`/PDF routes, which now eager-load more relations but return the same shape).

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 3: Stream a PDF once by hand to sanity-check the mPDF rendering of the new 2-row header and rowspan-heavy Core block**

Run (after seeding/creating a real IPCR V2 record with at least one tagged, DOST-linked OPCR indicator and one Core item in dev): visit `/ipcr-v2/{id}/pdf` in a browser and confirm the header renders as two rows and the Core rowspans don't visually collapse — mPDF's rowspan/colspan support is narrower than a browser's, so this is the one thing the passing `assertHeader('content-type', 'application/pdf')` test cannot catch.

---

## Self-Review Notes

- **Spec coverage**: Strategy/Sub-Strategy/Program columns ✓ Task 1-2, Q/E/T/A whole-table ✓ Tasks 2-4, Remarks column ✓ Tasks 2-4, header restructuring ✓ Task 5, PDF parity ✓ Task 6.
- **Column-count consistency**: every `<tbody>` fragment (Tasks 2-4) and the `<thead>` (Task 5) total 11 physical columns — verified row-by-row in Task 3's note, since Core's rowspan mixing is the one place a miscount would silently misalign the table.
- **No backend schema change**: `OpcrIndicator.remarks`, `IpcrV2CoreItem.remarks`, `IpcrV2SupportItem.remarks`, and `OpcrIndicator.rating_quality/efficiency/timeliness/rating_average` all already exist — this plan only surfaces already-stored data and extends one eager-load list.
