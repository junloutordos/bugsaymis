# Performance Management V2 ("PM V2") — Design

## Background

The live Performance Management / IPCR module (v1: `EmployeeIPCR`, `EmployeeIPCRPlan`,
`WorkDistributionPlan`, `AgencyOutcome`, etc.) works and has active, incomplete rating
periods. PSHS-CRC's actual IPCR document (per the user-supplied sample,
`SAMPLE IPCR.pdf`, Lyndon Bermoy / Curriculum and Instruction Division, July–December
2026) is structured around three weighted functions:

- **Strategic Function (30%)** — identical for every plantilla employee, inherited from
  the campus OPCR (Office/Organizational Performance Commitment and Review).
- **Core Function (50%)** — varies by position: Faculty Loading-derived for
  teaching/faculty, position/office-derived for non-teaching employees.
- **Support Function (20%)** — administrative/no-unit-load duties, for both faculty and
  non-teaching employees.

v1 does not model this cleanly: there is no Strategic Function inheritance mechanism, no
per-row weight, and no PDF/print export. A prior attempt at a related rebuild ("SPMS" —
a full OPCR→DPCR→IPCR cascade with a new Executive Director role) was built end-to-end
across 3 phases, deployed, and then **fully reverted** the same day the user first
clicked through it ("not what I want," no further detail given). The explicit lesson
from that incident (see memory `project_spms_module.md`): checkpoint after a single
working vertical slice before building out the rest.

This spec covers a narrower rebuild than SPMS — a single IPCR level (individual), not a
multi-level OPCR/DPCR/IPCR cascade — built as an isolated "V2" module so the still-open
v1 rating periods are never touched.

## Goals

1. Model Strategic (30%) / Core (50%) / Support (20%) functions explicitly, with
   per-row manual weight entry validated to sum to each function's allotted percentage
   (percentages themselves configurable per division via the existing, currently-unused
   `ipcr_weight_distributions` table).
2. Strategic Function content is authored once per rating period (by OCD/HR) and
   inherited read-only by every employee's IPCR.
3. Core Function generation reuses existing Faculty Loading-derived classification for
   teaching staff, and the existing manual WDP-tagging for non-teaching staff.
4. Produce a printable/PDF IPCR that closely matches the sample document's layout:
   colored section bands, per-row rating-scale rubric text, and a trailing
   Quality/Efficiency/Timeliness/Average rollup + FINAL AVERAGE RATING + signature block.
5. Zero impact on v1: no shared mutable tables, no shared routes, no shared workflow
   state. v1's in-flight IPCRs continue exactly as they are.
6. Ship incrementally: Phase 1 is a single vertical slice for one teaching-faculty
   employee, reviewed live by the user before any further phase starts.

## Non-goals (this spec)

- No OPCR→DPCR→IPCR organizational cascade (that was the reverted SPMS scope — not
  being revisited here).
- No new "Executive Director" or other new role.
- Non-teaching Core Function UI, Support Function UI polish, PMT/HR/Admin monitoring
  pages, and the Coaching & Mentoring Journal port are explicitly deferred to Phase 2+.
- **Scope is Permanent/Plantilla employees only**, per the user's framing. COS and
  other non-plantilla employee categories are not covered by V2 and continue on
  whatever process/form they currently use — this spec does not define one for them.

## Isolation strategy

Full duplication, not shared mutable tables:

- New tables: `employee_ipcrs_v2`, `employee_ipcrs_plan_v2`, `ipcr_rating_periods_v2`,
  `opcr_templates`, `opcr_template_items`.
- New models under `App\Models\PM2\*`.
- New controllers/services under `App\Http\Controllers\PM2\*` /
  `App\Services\PerformanceManagementV2\*`.
- New Vue pages under `resources/js/Pages/PerformanceManagementV2/`.
- New routes, prefixed `pm2.*`.
- New permissions, prefixed `ipcr.v2.*`.
- New sidebar entry: **"PM V2"**, separate from the existing "Performance Management"
  entry.

Reused as-is (shared, non-duplicated infrastructure — these are catalog/config data, not
per-cycle records, so duplicating them would create drift):

- `AgencyOutcome`, `PerformanceIndicator`, `WorkDistributionPlan` (extended with new
  nullable columns — see below — additive only, v1 code paths untouched).
- `Division`, `Office`, `User`, `FacultyLoading\LoadAssignment`, the Faculty Loading
  supervisor-chain resolution logic.
- `WorkDistributionPlanClassifier` (Core/Support-by-unit-load classification).
- `IPCRWeightDistribution` — wired up for the first time (currently defined but unused
  anywhere in the codebase).

## Data model

### `WorkDistributionPlan` (additive columns, nullable)

- `weight_percent` DECIMAL(5,2) NULL — manually entered per row when attached to a V2
  IPCR; NULL until then.
- `rating_scale_quality` TEXT NULL
- `rating_scale_efficiency` TEXT NULL
- `rating_scale_timeliness` TEXT NULL

These three rubric-text fields hold the free-form 5-tier descriptive scale shown in the
sample's Rating Scale columns (e.g. "5-96-100% / 4-91-95% / ..."). v1 never reads or
writes these columns, so this is a safe additive migration under the project's
blue-green migration rules.

### `opcr_templates`

One row per rating period, **campus-wide** — not per-division. The user was explicit
that Strategic Function content must be identical for every employee ("all employees
must be the same strategic functions and targets and accomplishments"), and the
sample's Strategic rows (STEM secondary education, STEM promotion, general
administration and support, leadership role) read as institution-wide outcomes, not
division-specific ones. The sample document's division line in the header identifies
the *employee's* division context only — it does not scope the Strategic content
itself.

- `id`, `ipcr_rating_period_v2_id` FK, `is_current` boolean, timestamps.

### `opcr_template_items`

- `id`, `opcr_template_id` FK, `strategy_label` (e.g. "Strategy 1"), `output_outcome`,
  `success_indicator`, `target`, `rating_scale_quality`, `rating_scale_efficiency`,
  `rating_scale_timeliness`, `sort_order`, timestamps.

Authored via a minimal OCD/HR admin page (`/pm2/opcr-templates`). Every V2 IPCR in that
division/period references these rows read-only — never copied/mutated per-employee,
so an OCD edit propagates to every not-yet-finalized IPCR (finalized/Director-Signed
IPCRs must snapshot their own copy — see below).

### `ipcr_rating_periods_v2`

Same shape as v1's `ipcr_rating_periods` (semester/start_date/end_date/status/
is_current) — kept separate so V2 periods and v1 periods are opened/closed
independently and never collide.

### `employee_ipcrs_v2`

Same shape as v1's `employee_ipcrs` (user_id, rating_period_id → v2 periods, status,
title, remarks, workflow timestamps, final_numeric_rating, final_adjectival_rating).

### `employee_ipcrs_plan_v2`

Same rating columns as v1 (`self_quality/efficiency/timeliness/average`,
`sup_quality/efficiency/timeliness/average`, `accomplishment`, `mov_link`,
`individual_target`, `remarks`), plus:

- `function_type` ENUM('strategic','core','support') — snapshotted at attach time.
- `weight_percent` DECIMAL(5,2) — snapshotted from `WorkDistributionPlan.weight_percent`
  (or from the `opcr_template_items` row, for Strategic) at attach time, so a later edit
  to the master weight doesn't retroactively change an already-rated IPCR.
- `plan_id` nullable FK → `work_distribution_plans` (Core/Support rows).
- `opcr_template_item_id` nullable FK → `opcr_template_items` (Strategic rows).

Exactly one of `plan_id` / `opcr_template_item_id` is set per row.

## Strategic Function

Read-only inheritance: when a V2 IPCR is created (or regenerated), every
`opcr_template_item` for the employee's division + current V2 period is attached as a
`function_type = 'strategic'` row, snapshotting its content and `weight_percent`
(entered on the template item itself, validated to sum to the division's Strategic %).
Employees cannot add, edit, or remove Strategic rows — only self-rate and record
accomplishments/MOV against them, same as any other row.

## Core Function

- **Teaching/Faculty** (Phase 1 scope): generated the same way v1's
  `FacultyIPCRBaselineService` / `WorkDistributionPlanClassifier` already do — one row
  per distinct subject/load pulled from Faculty Loading, function type resolved via
  `hasUnitLoad()`. The rater manually enters each row's `weight_percent`; the UI blocks
  submission-for-review until Core's attached rows sum to the division's Core % (default
  50, from `ipcr_weight_distributions`).
- **Non-Teaching** (Phase 2): unchanged mechanism from v1 — WDPs manually
  created/tagged per Office, attached to the employee, weight entered the same way.

## Support Function

Same source as today (no-unit-load assignments, admin/committee duties) via
`WorkDistributionPlanClassifier`; weight entered manually, validated to sum to the
division's Support % (default 20). Full UI polish deferred to Phase 2 — Phase 1 only
needs this to function, not to be polished.

## Workflow

Reuse v1's status machine shape via a new `IPCRWorkflowServiceV2` operating on the v2
tables: New Target → For Review → Targets Approved → Submitted for Rating → Rated →
(PMT/HR/Director stages — Phase 2+). Phase 1 only needs New Target → Targets Approved →
Submitted for Rating → Rated (self-rate + one supervisor rate), enough to exercise the
full weight-validation and print path end-to-end for one employee. Supervisor-chain
resolution (AUH→ACIDAA→CID Chief / Division Chief) is reused unchanged, re-pointed at
v2 models.

## Print / PDF

New mPDF export (`sys_get_temp_dir()` for tempDir, per project convention) matching the
sample closely:

- Header block: Republic of the Philippines / DOST / PSHS-CRC / division name /
  "INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)" / "AGENCY ORGANIZATIONAL
  OUTCOME" / employee name / division / rating period, Reviewed-by / Approved-by row
  with dates.
- Main grid: Function | Outputs/Outcomes | Success Indicator | Target | Actual
  Accomplishment | Rating Scale (Q/E/T) | Rating (Q/E/T/Avg) | Remarks, with colored
  section bands for Strategic (30%) / Core (50%) / Support (20%), each row's
  weight-percent shown in the left margin the way the sample shows "1. DMT Elective (10
  Units) 21.74%".
- Trailing rollup table: one row per function group (and per Core/Support sub-item),
  Quality/Efficiency/Timeliness/Average columns, weighted contribution, a highlighted
  FINAL AVERAGE RATING row, Comments and Recommendations, and the three-signature block
  (Employee / Supervisor / Head of Office, each with a role caption and date).

## Phasing

**Phase 1** (this implementation plan): OPCR template admin (minimal — create/edit
items for one division/period), Strategic inheritance, Core generation from Faculty
Loading with manual weight entry + sum validation, target → self-rate → one
supervisor-rate flow, PDF export matching the sample, for **one teaching-faculty
employee end-to-end**. User clicks through it live in dev before Phase 2 starts.

**Phase 2+** (separate spec/plan, not started until Phase 1 is approved in use):
non-teaching Core, Support Function UI polish, PMT/HR/Admin monitoring pages, Coaching
& Mentoring Journal port, sidebar rollout beyond the pilot employee/division.

## Migration discipline

All new tables are pure additions (new tables, or new nullable columns on
`work_distribution_plans`) — no existing column is dropped, renamed, or retyped, and no
v1 code path reads the new columns. This is a same-deploy-safe additive change per the
project's blue-green migration rules; no expand/contract split is needed.
