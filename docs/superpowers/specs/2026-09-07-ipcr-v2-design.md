# IPCR V2 — Design

## Background

The live Performance Management / IPCR module (v1: `EmployeeIPCR`, `EmployeeIPCRPlan`,
`WorkDistributionPlan`, `AgencyOutcome`, `IPCRWorkflowService`, etc.) is stable, has
active in-flight rating periods, and directly feeds payroll/PMS-adjacent decisions. It
does not model the real CSC/DOST IPCR form (per the user-supplied template,
`IPCRV2.pdf`) cleanly: there is no Strategic Function inheritance from the campus OPCR,
Core Function rating is a flat Quality/Efficiency/Timeliness average rather than the
template's per-subject weighted rubric, and there is no per-employee mechanism for
assigning which Core/Support duties an employee even holds outside of Faculty Loading's
auto-classification.

**Two prior rebuilds in this exact problem space were fully reverted:**

- **SPMS** (2026-08-17) — a full OPCR→DPCR→IPCR cascade with a new Executive Director
  role. Reverted the day it was first clicked through ("not what I want," no further
  detail given).
- **PM V2** (2026-09-02/03) — the same Strategic(30)/Core(50)/Support(20) shape this
  spec covers, sourced from Faculty Loading/WDP. Reverted with "still not the refactor
  I asked." Root cause, confirmed directly with the user this session: **the data model
  was not the problem — the UI/UX was.** PM V2 did not follow the live v1 IPCR's actual
  screens/mechanics/process flow closely enough.

This spec is a third attempt, deliberately designed around that finding: reuse v1's
mechanism, UI/UX convention, and process flow as literally as possible, and reuse the
now-live OPCR module (which did not exist when PM V2 was built) as the real Strategic
Function source instead of inventing a parallel template.

## Goals

1. Model Strategic (30%) / Core (50%) / Support (20%) functions explicitly, per the
   `IPCRV2.pdf` template.
2. **Strategic Function** is read-only, identical for every employee, and inherited live
   from the current fiscal year's OPCR module — no per-employee authoring, no snapshot.
3. **Core Function** for teaching faculty is derived from Faculty Loading (subjects
   taught, designations-with-load, committee-chair-with-load, research load), with
   weight auto-computed from actual load units. For Plantilla Non-Teaching, Core
   Function is derived from a manually-tagged Work Distribution Plan.
4. **A new, general "Employee Functions" capability** on the Employee record lets
   HR/Admin assign Core and Support functions to any employee, tag them to a WDP, and
   (for Faculty) sync them from Faculty Loading on demand. This is not IPCR-v2-scoped —
   it is a durable Employee-record feature that IPCR V2 is simply the first consumer of.
5. Core teaching rows use the template's own weighted rubric — Positive feedback from
   students (30%), from immediate supervisor (20%), instructional-materials development
   (20%), timeliness (30%) — distinct from v1's plain Q/E/T average.
6. Reuse v1's status flow, supervisor chain, ownership guards, and screen
   layout/conventions as literally as possible (new controllers/services, but mirroring
   `IPCRWorkflowService`/`EmployeeIPCRShow.vue` etc. line-for-line in shape).
7. Zero shared mutable state with v1: no shared tables, no shared routes, no shared
   workflow state. v1's in-flight IPCRs are untouched. IPCR V2 runs in parallel and only
   replaces v1 once it is stable and mature — that migration is explicitly out of scope
   for this spec.

## Non-goals (this spec)

- No OPCR→DPCR→IPCR organizational cascade (SPMS scope — not revisited).
- No new roles. Reuses existing roles/permissions (Faculty, AUH, ACIDAA, CID Chief,
  Division Chief, PMT, HR, Administrator).
- No migration/cutover plan from v1 to v2 — that only happens after v2 is proven stable
  in production, per the user's own framing, and is a separate future decision.
- No changes to the existing Designations-module WDP tagging (Category/Designation/
  Teaching-Load tabs) that v1 IPCR generation depends on — Employee Functions is a
  fully separate, parallel tagging mechanism, confirmed explicitly with the user.
- No changes to `employee_ipcrs`/`employee_ipcrs_plan`/`WorkDistributionPlanClassifier`
  or any other v1 code path.
- The Research Advising points annex (page 5–6 of the template) is not built in this
  pass — it's a standalone points table with its own approval chain (CID
  Chief/SR Coordinator/Unit Head) that isn't part of the core Strategic/Core/Support
  cascade; flagged here so it isn't silently forgotten, not scoped into this build.

## Isolation strategy

- Models: `App\Models\IPCRV2\*` (namespace mirrors the abandoned `SPMS`/`PM2`
  namespacing convention used by the two prior attempts).
- Tables: `ipcr_v2_*` prefix.
- Routes: `ipcr-v2.*`, under `routes/ipcr-v2.php`.
- Permissions: `ipcr.v2.*` (create/update/submit/approve/view, mirroring v1's
  `ipcr.*` permission shape).
- Sidebar: new "IPCR V2" entry alongside (not replacing) the existing "Performance
  Management" entry.
- The one deliberate exception to full isolation is `employee_functions` (see below) —
  it is new, general, and hangs off `User`, living in the shared Employees/Users area,
  because the user explicitly confirmed this should be a durable Employee-record
  capability, not an IPCR-v2-only concept. Everything that *reads* `employee_functions`
  for IPCR V2 purposes still only ever writes to `ipcr_v2_*` tables.

## Data model

### `employee_functions` (new, general — not IPCR-namespaced)

| Column | Notes |
|---|---|
| `user_id` | FK → `users` |
| `function_type` | enum `core`\|`support` |
| `source_type` | enum `load_assignment`\|`wdp`\|`manual` |
| `load_assignment_id` | nullable FK → `load_assignments` (Faculty auto-sync only) |
| `work_distribution_plan_id` | nullable FK → `work_distribution_plans` (WDP-tagged rows) |
| `label` | denormalized display text (subject name, designation name, committee name, or free text for `manual`) |
| `weight_percent` | nullable decimal — auto-computed for Faculty Core rows, manually entered for Non-Teaching Core rows, null for Support rows (unweighted individually per the template) |
| `academic_term_id` | FK → `academic_terms` — scopes Faculty auto-sync rows so re-sync per term doesn't accumulate stale rows across terms |
| `created_by` | who added/synced this row |

**Faculty sync** (`EmployeeFunctionSyncService::syncFromFacultyLoading(User $user)`,
triggered by a "Sync from Faculty Loading" button): reads the user's current-term
`LoadAssignment`s, creates/updates one `employee_functions` row per distinct
subject/designation-with-load (mirroring the grouping already proven correct in v1's
`FacultyIPCRBaselineService` — subject-level grouping, not raw assignment-row level),
sets `weight_percent = load_units / SUM(load_units for this user this term)`. Detaches
(does not hard-delete) any prior auto-synced row for a `LoadAssignment` that's no longer
current — **unless** it has `ipcr_v2_core_items`/`ipcr_v2_support_items` already
generated against it with real accomplishment data, in which case it's left alone for
manual review (same superseded-row-preservation rule already proven necessary in v1's
WDP-tagging reconciliation).

**Non-Teaching manual tagging**: HR/Admin adds Core or Support rows directly via a
"+ Add Function" action on the Employee Functions tab, picking a `WorkDistributionPlan`
(their job's actual duties) or free-typing a `manual` label for one-off items. Core row
weights must sum to 100% within Core for that employee (validated the same way v1's
existing-but-previously-unused `IPCRWeightDistribution` validation pattern was designed
for).

**New UI**: an "Employee Functions" tab on the existing Users/Employees page (the
single shared page/controller per `project_users_employees_module` — this adds a tab,
it does not touch the existing tabs/actions there). Gated by a new
`employee_functions.manage` permission.

### `ipcr_v2_records` (the IPCR V2 record itself)

| Column | Notes |
|---|---|
| `user_id` | FK → `users` |
| `rating_period_id` | FK → the **existing** `ipcr_rating_periods` table — same FY/semester infra v1 and OPCR already use, no new period concept |
| `status` | same string constants as `IPCRWorkflowService` (`New Target`, `For Review`, `Targets Approved`, `Submitted for Rating`, `Rated & For PMT Review`, `Submitted to PMT`, `PMT Returned for Revision`, `Approved by PMT`, `Submitted to HR`, `Director Signed`, `Returned for Revision`) |
| `submitted_for_review_at` / `target_approved_at` / `submitted_for_rating_at` / `submitted_rating_at` / `submitted_for_pmtreview_at` / `submitted_to_hr_at` / `director_signed_at` | mirrors `employee_ipcrs` timestamp columns exactly |
| `director_signature` | mirrors `employee_ipcrs` |
| `final_numeric_rating` / `final_adjectival_rating` | mirrors `employee_ipcrs`, computed per "Final rating" below |

### `ipcr_v2_core_items` (snapshotted from `employee_functions` at "Generate Targets" time)

| Column | Notes |
|---|---|
| `ipcr_v2_id` | FK |
| `employee_function_id` | nullable FK — traceability back to the source row, never re-read live after generation |
| `label` | copied at generation time (frozen even if the source `employee_functions.label` later changes) |
| `weight_percent` | copied at generation time |
| `target` / `actual_accomplishment` | free text, same looseness as v1's `mov_link`/`accomplishment` (Philippine MIS forms are rarely strictly-typed data — see `feedback_laravel_gotchas` on `mov_link` validation) |
| `student_feedback_rating` / `supervisor_feedback_rating` / `im_development_rating` / `timeliness_rating` | each 1–5 int, weights 30/20/20/30 are fixed constants (not user-editable), matching the template exactly |
| `row_average` | computed: `0.30×student + 0.20×supervisor + 0.20×im + 0.30×timeliness` |
| `remarks` | free text |

### `ipcr_v2_support_items` (same snapshot pattern; unweighted individually per template)

| Column | Notes |
|---|---|
| `ipcr_v2_id` | FK |
| `employee_function_id` | nullable FK |
| `label` | frozen at generation time |
| `actual_accomplishment` / `mov_link` | free text |
| `quality_rating` / `efficiency_rating` / `timeliness_rating` | plain 1–5 average (Support does **not** use the 4-part rubric — that's specific to teaching Core rows per the template) |
| `row_average` | computed average of the three |
| `remarks` | free text |

### Strategic Function — no new tables

Rendered directly from the live `OpcrIndicator` rows for the current fiscal year
(`OpcrIndicator::forFiscalYear($currentYear)`, grouped by Program A–D exactly like the
OPCR Show page), plus the OPCR's own actual/rating figures. Identical for every
employee; never snapshotted, never individually edited from the IPCR V2 side — if the
OPCR changes mid-period, every employee's Strategic section reflects that live, by
design (this section is explicitly "true to all employees," not a per-employee
commitment).

## Final rating computation

Reuses the **existing, currently-dormant** `ipcr_weight_distributions` table
(`division_id`, `strategic`, `core`, `support`) instead of hardcoding 30/50/20 — a
division can already configure its own split via this table; IPCR V2 is simply the
first thing that actually reads it. Falls back to 30/50/20 if the employee's division
has no configured row.

```
final_numeric_rating =
    (strategic_weight × current OPCR's actual rating)
  + (core_weight × weighted-avg of ipcr_v2_core_items.row_average, weighted by weight_percent)
  + (support_weight × avg of ipcr_v2_support_items.row_average)
```

Adjectival banding reuses v1's existing thresholds (4.51/3.51/2.51/1.51 →
Outstanding/Very Satisfactory/Satisfactory/Unsatisfactory/Poor).

## Workflow & roles — literal reuse

Same status flow, same supervisor chain (teacher→AUH→ACIDAA→CID Chief; CID Chief
ministerial-only on faculty IPCRs; Division Chief's own IPCR → OCD), same
`canManage`/`canEndorse`/`canRatePlan` authorization split, same `isOwner` guard
pattern, same Coaching & Mentoring Journal port (new `ipcr_v2_coaching_sessions` table,
same shape as `ipcr_coaching_sessions`). Controllers/services are new
(`IPCRV2WorkflowService`, `EmployeeIPCRV2Controller`, `DivisionChiefIPCRV2Controller`,
`PMTIPCRV2Controller`, `HRIPCRV2Controller`, `AdminIPCRV2Controller`) but mirror the v1
originals line-for-line in shape and are built by literally referencing the v1 Vue
components for layout/status-pill/modal conventions — the direct response to the PM V2
finding that the UI/UX, not the data model, was what didn't land last time.

## Permissions

New `ipcr.v2.*` permission set (view/create/update/submit/approve/admin), mirroring
v1's `ipcr.*` shape exactly. New `employee_functions.manage` permission for the
Employee Functions tab (HR/Admin).

## Rollout / build order

Full 3-part shape for both Faculty and Non-Teaching is being built in one pass (per the
user's explicit choice, not phase-gated behind a separate approval per slice) — but
implementation still proceeds in this dependency order so there's always a coherent,
inspectable slice as it goes:

1. `employee_functions` + Employee Functions tab (Faculty sync + Non-Teaching manual
   tagging) — the prerequisite everything else reads from.
2. Strategic Function (read-only OPCR mirror) — cheapest, no new mutable state.
3. Core Function (`ipcr_v2_core_items`, 4-part rubric, weight roll-up).
4. Support Function (`ipcr_v2_support_items`).
5. Workflow/roles/status flow, screens for every role (Employee, DC, AUH/ACIDAA, PMT,
   HR, Admin monitor), Coaching Journal port.
6. Final rating computation + adjectival banding + PDF export matching the template
   layout.

## Open risks / assumptions carried into implementation

- **Rating-period scope**: this spec assumes IPCR V2 reuses the *same*
  `ipcr_rating_periods` records v1 and OPCR already use (not a separate "v2 period"
  concept). If a v2-only period concept turns out to be needed, that's a scope change
  to raise before building the workflow layer.
- **`emp_category`** on `User` is assumed to be the field that distinguishes Faculty
  from Plantilla Non-Teaching for routing to the correct Core-generation path (Faculty
  Loading sync vs. WDP manual tagging) — needs a direct check against its actual seeded
  values during implementation, not assumed from memory alone.
- The Research Advising points annex (template pages 5–6) is explicitly deferred (see
  Non-goals) — if the user expects it in this same build, that's a scope addition to
  flag before implementation starts.
