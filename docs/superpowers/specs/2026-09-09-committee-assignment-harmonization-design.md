# Committee Assignment Harmonization — Design

## 1. Problem

Committee membership is currently managed from two separate places that
confuse users:

- **Performance Management → Committees** (`pm-committees.*`,
  `CommitteePerformanceController`) — a nearly-duplicate committee **catalog**
  CRUD (create/edit committee, sub-committee builder, head assignment) plus
  member rating against the **legacy v1 IPCR** pivot
  (`employee_ipcrs_plan.sup_quality/sup_efficiency/sup_timeliness`).
- **Faculty Loading → Committee Assignments** (`faculty-loading.committee-assignments.*`,
  `FacultyLoading\CommitteeAssignmentController`) — the real per-term
  **assignment** CRUD (role, load units, Work Distribution Plan tagging,
  compliance check against teaching load), plus its own copy of the same
  rating action.

Both already read/write the same underlying `FacultyCommitteeAssignment`
table and share `CommitteeRosterService` / `CommitteeRatingService` (unified
2026-07-15, see memory `project_committee_module.md`) — the *data layer* is
already one thing. What's not unified is the UI, the permission model, and
where a rating actually lands.

Separately, `EmployeeFunctionSyncService::syncFromFacultyLoading()` (built
2026-09-07 for IPCR V2) already knows how to turn a committee assignment into
a Core or Support `EmployeeFunction` row, using the assignment's own tagged
WDPs — but nothing calls it when a committee assignment actually changes; it
only runs when HR manually clicks "Sync from Faculty Loading" on the
Employee Functions tab, one employee at a time.

**Verified, not assumed:** `DataManagement/Committees` (the third,
oldest committee-catalog page, `CommitteeController`) has **no sidebar nav
entry at all** — grepped `resources/js/Layouts/navigation.js` for both the
"Data Management" parent group and any `committees.index` reference; it does
not exist there. The page is reachable only by a hand-typed URL. In practice,
Performance Management's "Committees" page is the only place users have ever
managed the catalog.

## 2. Goals

1. One committee-assignment UI, under Performance Management, replacing both
   existing pages.
2. Committee-assignment changes automatically keep the member's Support
   Function record current (no manual HR sync step).
3. A committee-sourced Support Function's rating is entered by the committee
   chairperson and lands in **IPCR V2** (`ipcr_v2_support_items`), not the
   legacy v1 pivot.
4. Committee-sourced Support Function items materialize into a member's
   current IPCR V2 record automatically when one already exists, instead of
   waiting on the member to click "Generate Targets."

## 3. Non-goals

- No changes to Faculty Loading's load-unit computation, compliance-check
  logic, or `LoadAssignment`/`FacultyLoad` model — committee assignments keep
  contributing load units exactly as today.
- No change to how Core Functions (teaching load, designations) sync — this
  only touches the committee-assignment path through
  `EmployeeFunctionSyncService`.
- No change to Stage 2's trigger rule for **non-committee** Employee
  Functions — teaching load / designation-backed rows still wait for the
  employee's own "Generate Targets" / "Sync from Employee Functions" click
  (2026-09-08 decision, unchanged).
- Historical v1 IPCR committee-rating data (`employee_ipcrs_plan.sup_*` rows
  already written) is left in place, not backfilled or migrated into V2.
- Deleting the orphaned `DataManagement/Committees` page/controller is
  **out of scope for the implementation plan** — flagged for a separate,
  explicit cleanup decision, not bundled into this change.

## 4. Architecture

### 4.1 Controller/route consolidation

`FacultyLoading\CommitteeAssignmentController` becomes the backbone (it
already has the more complete, correct implementation: term-scoped
`FacultyCommitteeAssignment` CRUD with role/load-units/WDP-tagging/
compliance/rating). It moves to
`App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController`
and gains the catalog-CRUD methods currently in
`CommitteePerformanceController::store/update/destroy` (committee +
sub-committee create/edit, head assignment).

- Routes stay named `pm-committees.*` (URL stability for anything bookmarked
  or linked); the underlying controller changes.
- `CommitteePerformanceController` is deleted once its logic is folded in.
- `faculty-loading.committee-assignments.*` routes are deleted.
- Faculty Loading's sidebar entry ("Committee Assignments") is removed.
  Performance Management's "Committees" entry becomes the single nav path
  (permission set becomes the union of today's two: `accomplishments.view`
  for read/rate access, `faculty_loading.manage` for assignment CRUD +
  compliance check — exact permission shape is a plan-time detail, not a
  design-time one).
- Frontend: `PerformanceManagement/Committees/Index.vue` and `Show.vue`
  absorb FL's assignment-modal fields (role, load units, WDP tagging,
  compliance indicator) that today only exist in
  `FacultyLoading/CommitteeAssignments/*.vue`. The task board
  (`TaskBoard.vue`) is already shared — no change there.
- `FacultyLoading/CommitteeAssignments/*.vue` pages are deleted once parity
  is confirmed.

### 4.2 Support Function auto-sync

`EmployeeFunctionSyncService::syncFromFacultyLoading(User $user)` is called
automatically (not just from the manual HR button) at these points:

- After `FacultyCommitteeAssignment` create/update/destroy in the merged
  controller.
- After `CommitteeRosterService::createIfMissing()` / `updateRole()` /
  `deactivate()` — i.e., when a committee's catalog roster/head changes and
  reconciliation creates, promotes, or deactivates assignments.
- After a committee's `workDistributionPlans()->sync()` call, for every
  currently-active member of that committee (a WDP re-tag changes what any
  member's committee-sourced function resolves to).

No changes to `syncFromFacultyLoading()`'s internals are needed — the
committee-assignment resolution logic already exists and is already tested
(`syncCommitteeAssignment()`, `defaultPlanForCommitteeAssignment()`). This is
purely "call it at the right times."

### 4.3 IPCR V2 auto-materialization for committee rows

When `syncFromFacultyLoading()` creates or updates a committee-sourced
`EmployeeFunction` row, and the member already has an `IpcrV2Record` for the
current `IPCRRatingPeriod`, immediately call
`IpcrV2GenerationService::syncNewFunctions($record)` for that record so the
new/changed committee Support Item appears without the member needing to
click "Generate Targets" themselves.

- If the member has **no** current-period `IpcrV2Record` yet, nothing is
  auto-created — the design explicitly rejects fabricating a person's entire
  IPCR V2 document as a side effect of a committee assignment. The
  committee page shows "This member hasn't generated their IPCR V2 targets
  yet" until they do; once they do, all their committee-sourced functions
  materialize in the same `generateTargets()` pass as today.
- Auto-materialized items still carry a `target` (pre-filled from the tagged
  WDP's `success_indicator`, same propagation already built for manual
  generation) and still require Division-Chief **Target Approval**
  (`STATUS_TARGETS_APPROVED`) before anyone — chairperson included — can
  enter an accomplishment or rating. Auto-materialization removes the wait
  for a button click, not the approval step.

### 4.4 Chairperson rating authority on IPCR V2 Support Items

This is the one genuinely new authorization channel this design introduces:
a non-Division-Chief rater writing to `ipcr_v2_support_items`.

- **Resolution**: given a `FacultyCommitteeAssignment`, resolve its
  committee-sourced `EmployeeFunction`
  (`sync_source_key = 'committee_assignment:{assignment id}'`), then that
  function's `ipcrV2SupportItems()` row(s) on the member's current
  `IpcrV2Record`.
- **Gate**: identical hierarchical rule already implemented in
  `CommitteeAssignmentController::rateAssignment()` today — main-committee
  chairperson/co-chair rates their own committee's members and cascades into
  sub-committees; a sub-committee's own chairperson/co-chair rates only that
  sub's members; `faculty_loading.manage` (or the merged permission)
  bypasses. This logic is reused, not reinvented.
- **Status gate**: rating is only possible once the item's record is at
  `STATUS_TARGETS_APPROVED` or later (mirroring
  `EmployeeIpcrV2Controller::EDITABLE_STATUSES` phase logic) — before that,
  the rate form is replaced with a "Targets not yet approved" message.
- **Write**: sets `quality_rating` / `efficiency_rating` / `timeliness_rating`
  (`row_average` computed the same way `DivisionChiefIpcrV2Controller::rateSupportItem()`
  does: `round((q+e+t)/3, 2)`), and may also set `actual_accomplishment` /
  `mov_link` (parity with today's chairperson capability — chairs already
  overwrite these fields via `CommitteeRatingService::rate()`). Employee
  self-rating (`self_quality_rating` etc.) and self-reported
  `actual_accomplishment` are untouched — the employee's own IPCR V2 Show
  page keeps working exactly as it does today.
- **Single-writer guarantee**: `DivisionChiefIpcrV2Controller::rateSupportItem()`
  is guarded — if the target item's `employee_function_id` traces to a
  committee-sourced `EmployeeFunction`, the DC's own IPCR V2 review screen
  renders that row **read-only** ("Rated via Committee Assignment by
  `<chairperson name>`") instead of an editable form, and the endpoint itself
  rejects a write attempt on such a row. This prevents two independent
  writers racing on the same field.
- **Legacy pivot retirement**: `CommitteeRatingService::rate()`'s write to
  the v1 `employee_ipcrs_plan.sup_*` columns is removed for new ratings.
  Existing historical rows are left as-is (read-only history, not migrated).

## 5. Data flow (end to end)

```
Committee CRUD (merged controller / CommitteeRosterService)
        │  create / update / destroy FacultyCommitteeAssignment
        ▼
EmployeeFunctionSyncService::syncFromFacultyLoading(member)
        │  upserts committee-sourced EmployeeFunction (Core if load_units>0, else Support)
        ▼
   [member has a current-period IpcrV2Record?] ──no──► stops here; queued for their next Generate Targets
        │ yes
        ▼
IpcrV2GenerationService::syncNewFunctions(record)
        │  materializes/updates ipcr_v2_support_items row, target pre-filled
        ▼
   [DC approves target → STATUS_TARGETS_APPROVED]
        ▼
Chairperson rates via merged Committee page
        │  writes quality/efficiency/timeliness + accomplishment/mov_link
        ▼
ipcr_v2_support_items (official rating) ── read-only mirror ──► DC's own IPCR V2 Show page
```

## 6. Testing approach

- `EmployeeFunctionSyncService` — existing tests continue to pass unchanged
  (no internal logic change); new tests cover the *trigger* points (creating/
  editing/removing a `FacultyCommitteeAssignment` results in a sync call;
  WDP re-tag on a committee re-syncs all active members).
- New `IpcrV2GenerationService::syncNewFunctions()` call site — test that a
  committee assignment change against a member with an existing current-
  period record materializes/updates the item, and that a member with no
  current-period record is left alone (no record fabricated).
- New chairperson-rating endpoint/branch — test the reused hierarchical gate
  (main chair, sub-chair, non-chair member rejected, admin bypass), the
  status gate (blocked before Targets Approved), and the DC read-only guard
  on the same row (DC write attempt on a committee-sourced item is
  rejected).
- Controller/route consolidation — full existing `tests/Feature` coverage
  for both old controllers must be ported to the merged controller and pass
  before either old controller is deleted.
- No dev-DB click-through is expected to be possible (Google-OAuth-only login,
  same limitation noted across every recent IPCR V2 memory) — ship on green
  tests + `npm run build`, same as the rest of this module's history.

## 7. Rollout

Additive from a data standpoint (no destructive migration — no columns
dropped, `employee_ipcrs_plan` untouched). Safe as a single normal deploy,
not an expand/contract split. Route/controller/page deletions happen in the
same deploy as their replacements, once feature parity is verified by tests
— there is no external caller of `faculty-loading.committee-assignments.*`
or `pm-committees.*`'s old catalog-CRUD shape to keep working during a
blue/green window beyond the app itself.
