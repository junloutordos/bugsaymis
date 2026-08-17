# DOST Strategic Plan Module — Design

## Context

BugSayMis's Performance Management module already has an `agency_org_outcomes` table
(model `AgencyOutcome`) and `performance_indicators` table, both admin-CRUD via
`AgencyOutcomeController` / `PerformanceIndicatorController`, gated by the `ipcr.view`
permission. In dev, both tables are currently empty — this part of the module was
built but never populated or adopted.

The source of truth for how PSHS-CRC's OPCR should actually be structured is
`Downloads/Performance Management/OPCR 2026 Accomplishments Monitoring.xlsx`
(sheets `OPCR` and `2026 Targets`) plus `FAM 4.5 Strategic Performance Management
System` manual. The real hierarchy used there is:

```
DOST Pillar/Outcome  (5 fixed national pillars, e.g. "DOST Pillar 5: Governance")
  └─ Strategy         (DOST's numbered strategies, e.g. "Strategy 17: Institutionalize
                        science communication" — each Strategy always belongs to the
                        same Pillar across the document)
       └─ Sub-Strategy (PSHS's own articulation of how it executes that Strategy)
            └─ [PSHS Program]  ("A. STEM Secondary Education...", "B. STEM Promotion
                Program", "C. General Administration and Support", "D. Leadership
                Role in Secondary STEM Education")
                 └─ Performance Indicators (target, budget, division, quarterly
                     accomplishment, rating)
```

"PSHS Program" in the spreadsheet is what the system already calls "Agency Org
Outcome." This module builds the DOST Pillar → Strategy → Sub-Strategy layer and
wires Strategy to the existing `AgencyOutcome` (PSHS Program) record.

This is explicitly **Phase 1 of a larger, unstarted effort** — a future refactor of
IPCR and creation of OPCR/DPCR modules that will consume this data. That later work
is out of scope here and needs its own brainstorming/spec pass when picked up.

**Prior related work note:** an earlier SPMS v2 (OPCR/DPCR/IPCR cascade) module was
built and fully deployed on 2026-08-16/17, then reverted and deleted the same day at
the user's request ("not what I want," no further detail given). This module is
unrelated in code (nothing from that revert is being reused), but the lesson taken
from it applies here: ship this bounded piece, checkpoint with the user, and do not
proceed into the IPCR/OPCR/DPCR refactor without a fresh, explicit go-ahead.

## Decisions (confirmed with user)

1. **Keep `AgencyOutcome`/`PerformanceIndicator` as-is.** No changes to those models,
   controllers, or their Vue pages in this pass.
2. **New 3-level hierarchy**, not a flat 3-column table — chosen specifically to
   avoid retyping/mis-typing Pillar and Strategy text on every row (the source
   Excel has typos like "Whealth"/"Outome" from repeated manual entry).
3. **`dost_strategies.agency_outcome_id` is nullable.** `agency_org_outcomes` is
   currently empty; those PSHS Program rows will be added directly in production,
   and Strategies will be wired to them after that data exists. The Strategy form
   must work with no `AgencyOutcome` records present.
4. **Delete cascades**: deleting a Pillar cascades to its Strategies and their
   Sub-Strategies. Deleting an `AgencyOutcome` (PSHS Program) also cascades to any
   linked Strategies/Sub-Strategies (matching the existing cascade-on-outcome-delete
   behavior already used by `PerformanceIndicator`). The UI must warn before
   deleting a Pillar or Outcome that has children, since the blast radius is now
   larger than before this module existed.
5. **No `fiscal_year` scoping** on Pillar/Strategy/Sub-Strategy — unlike
   `AgencyOutcome`/`PerformanceIndicator`, these come from DOST's stable multi-year
   national plan, not something that changes every fiscal year. (Not used by the
   FY-rollover clone logic in `IPCRRatingPeriodController` — that logic is untouched.)
6. **Permission**: reuse `ipcr.view`, matching the existing Agency Org Outcome /
   Performance Indicators screens. No new permission.
7. **No seeding of the real 2026 Pillar/Strategy/Sub-Strategy values** from the
   Excel. All three tables ship empty; data entry happens through the new UI.

## Data Model

```
dost_pillars
  id
  name                string          e.g. "DOST Pillar 5: Governance"
  outcome_statement   text            e.g. "DOST System Governance Strengthened
                                            and Harmonized"
  timestamps

dost_strategies
  id
  dost_pillar_id      FK -> dost_pillars, cascade delete, required
  agency_outcome_id   FK -> agency_org_outcomes, cascade delete, NULLABLE
  name                string          e.g. "Strategy 17: Institutionalize
                                            science communication"
  timestamps

dost_sub_strategies
  id
  dost_strategy_id    FK -> dost_strategies, cascade delete, required
  description         text
  timestamps
```

Models: `App\Models\DostPillar`, `App\Models\DostStrategy`, `App\Models\DostSubStrategy`.

- `DostPillar::strategies()` hasMany `DostStrategy`
- `DostStrategy::pillar()` belongsTo `DostPillar`
- `DostStrategy::agencyOutcome()` belongsTo `AgencyOutcome` (nullable)
- `DostStrategy::subStrategies()` hasMany `DostSubStrategy`
- `DostSubStrategy::strategy()` belongsTo `DostStrategy`

Migration files (new, additive-only — no changes to existing tables):
- `create_dost_pillars_table`
- `create_dost_strategies_table`
- `create_dost_sub_strategies_table`

## Backend

Three lean controllers, matching the existing one-controller-per-entity convention:

- `DostPillarController` — `store`, `update`, `destroy`
- `DostStrategyController` — `store`, `update`, `destroy`
  - validates `dost_pillar_id` required/exists, `agency_outcome_id` nullable/exists,
    `name` required
- `DostSubStrategyController` — `store`, `update`, `destroy`
  - validates `dost_strategy_id` required/exists, `description` required

One `DostStrategicPlanController@index` renders the full nested tree via
`DostPillar::with('strategies.subStrategies')->get()` plus `AgencyOutcome::all()`
for the Strategy-linking dropdown, returned as an Inertia page.

Routes added inside the existing `Route::middleware('permission:ipcr.view')` group
in `routes/web.php` (same group as `/agency-outcomes`, ~line 1180-1186):

```
GET    /dost-strategic-plan                      dost-strategic-plan.index
POST   /dost-pillars                              dost-pillars.store
PUT    /dost-pillars/{dostPillar}                 dost-pillars.update
DELETE /dost-pillars/{dostPillar}                 dost-pillars.destroy
POST   /dost-strategies                           dost-strategies.store
PUT    /dost-strategies/{dostStrategy}             dost-strategies.update
DELETE /dost-strategies/{dostStrategy}             dost-strategies.destroy
POST   /dost-sub-strategies                        dost-sub-strategies.store
PUT    /dost-sub-strategies/{dostSubStrategy}      dost-sub-strategies.update
DELETE /dost-sub-strategies/{dostSubStrategy}      dost-sub-strategies.destroy
```

All mutation endpoints redirect back with `->with('success', ...)`, per project
convention. All are Inertia (no JSON API endpoints) since this is pure admin CRUD
with no file uploads.

## Frontend

- New page: `resources/js/Pages/PerformanceManagement/DostStrategicPlan.vue`
- Nav: new "DOST Strategic Plan" entry in `resources/js/Layouts/navigation.js`,
  placed alongside the existing "Agency Org Outcome" (~line 656) and "Performance
  Indicators" (~line 663) items under Performance Management.
- Layout: expandable tree — Pillar rows expand to show their Strategies; Strategy
  rows expand to show their Sub-Strategies and a badge showing the linked PSHS
  Program (`AgencyOutcome.outcome`), or an "Unlinked" badge when
  `agency_outcome_id` is null (expected until production data is wired).
- Add/Edit via modals per level, using the project's standard Tailwind input/button
  classes (CLAUDE.md conventions — `rounded-lg border border-slate-200 bg-white
  px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full`
  for inputs, indigo primary buttons).
  - Pillar form: `name`, `outcome_statement`.
  - Strategy form: Pillar dropdown (required), Agency Org Outcome dropdown
    (optional — "Not yet linked" is a valid choice), `name`.
  - Sub-Strategy form: Strategy dropdown (required; selecting one shows its Pillar
    read-only for context), `description`.
- Delete confirmations state the cascade explicitly, e.g. "Deleting this Pillar
  will also delete 3 Strategies and 7 Sub-Strategies under it."
- Uses `useForm`/Inertia POST-PUT-DELETE with `preserveScroll: true`, matching
  the existing `AgencyOrgOutcome.vue` / `PerformanceIndicators.vue` pattern.

## Testing

Feature tests (Pest/PHPUnit, following existing project test conventions):

- CRUD happy path for each of the 3 levels (create, update, delete).
- Cascade delete: deleting a Pillar removes its Strategies and their
  Sub-Strategies; deleting an `AgencyOutcome` removes linked Strategies and their
  Sub-Strategies.
- Permission gate: a user without `ipcr.view` gets 403 on all `dost-*` routes.
- Validation: Strategy `store`/`update` rejects missing `dost_pillar_id` (422);
  accepts missing `agency_outcome_id` (nullable); rejects a non-existent
  `agency_outcome_id` (422). Sub-Strategy `store`/`update` rejects missing
  `dost_strategy_id` (422).
- Index page: renders with zero data (all three tables empty is the expected
  starting state in both dev and prod) — no N+1 assumptions that break on an
  empty tree.

## Explicitly out of scope

- `PerformanceIndicator` and the existing Agency Org Outcome CRUD screen — untouched.
- IPCR/OPCR/DPCR modules and any rollup/workflow logic — future work, needs its
  own brainstorming/spec pass.
- The fiscal-year rollover clone logic in `IPCRRatingPeriodController` — untouched;
  Pillar/Strategy/Sub-Strategy are not fiscal-year-scoped and are not part of that
  clone flow.
- Seeding real Pillar/Strategy/Sub-Strategy/PSHS Program values from the 2026
  Excel — all three new tables ship empty.
