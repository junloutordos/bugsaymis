# Performance Management v2 (SPMS Cascade) — Design Spec

**Date:** 2026-08-16
**Status:** Approved (design phase) — pending implementation plan

## Summary

PSHS-CRC's actual performance management process (per CSC MC 6 s.2012 / DBM Strategic Performance Management System) cascades three levels: **OPCR** (Office/Campus Performance Commitment and Review — Campus Director, campus-wide) → **DPCR** (Division PCR — Division Chief, per division) → **IPCR** (Individual PCR — every employee). The live module only covers IPCR, bottom-up, with no OPCR/DPCR entities and no rollup between levels. This spec adds a **fully separate v2 module** (`spms.*`) implementing the full cascade, running in parallel with the existing IPCR module until confirmed stable, at which point v1 is retired. No v1 table, route, or controller is touched by this work.

## Context / Prior Art

Analysis of the reference files in `Downloads/Performance Management/` (OPCR/DPCR templates, old and newly-proposed IPCR templates, and two mid-period evidence-monitoring spreadsheets) against the existing codebase found:

- **The cascade skeleton already exists in spirit**: `AgencyOutcome` → `PerformanceIndicator` (m2m to `Division`) → `WorkDistributionPlan` → `EmployeeIPCR`/`EmployeeIPCRPlan` pivot. This taxonomy design is sound and v2 reuses its shape (as new, separate tables — see Data Strategy below).
- **`IPCRWorkflowService`** already implements a CSC-aligned state machine (`New Target → For Review → Targets Approved → Submitted for Rating → Rated → Submitted to PMT/HR → Director Signed`, terminal + immutable) with faculty-aware supervisor resolution (teacher→AUH→ACIDAA→CID Chief, else Division Chief). v2's OPCR/DPCR/IPCR workflow services follow the same audit/immutability pattern.
- **`FacultyIPCRBaselineService`** already auto-generates individual IPCR targets from `LoadAssignment` by `load_source` match — this is the mechanism v2's single flexible IPCR template extends, instead of building discrete role-specific templates.
- **Gaps confirmed**: no OPCR entity (no Campus Director commitment record, no Executive Director approval, no quarterly accomplishment tracking). No DPCR entity (`PerformanceIndicator↔Division` is an assignment link only, not a rated record with its own ratee/reviewer/approver). No rollup/aggregation logic anywhere in the cascade.
- **Weight discrepancy**: live code (`IPCRWorkflowService::FUNCTION_WEIGHTS`) uses Strategic/Core/Support = 30/55/15. Every source template (OPCR, DPCR, old IPCR, new IPCR) uses 30/50/20, with DPCR's Core bucket further subdivided dynamically by unit count (Core Duties / Student Evaluation / Supervisor Evaluation, scaled by number of teaching units). This is not corrected in v1 as part of this work — v2 defines its own weight configuration independently (see below).
- **`IPCRWeightDistribution`** (per-division strategic/core/support columns) exists in the schema but is wired into no controller — an abandoned prior attempt at configurable weights. v2's `spms_weight_profiles` is the model that actually gets wired up, replacing the intent of that orphaned table (which is left untouched, v1-owned).
- **Naming collision avoided**: `PMS`/`ict_pms_*` in this codebase is ICT Preventive Maintenance Schedule, unrelated to Performance Management. v2 uses the `spms` prefix (Strategic Performance Management System) throughout — routes, tables, permissions — to avoid any collision with `pms.*`/`ict_pms_*`.
- **Fiscal vs. school year**: OPCR/DPCR track a Jan–Dec fiscal/calendar year (per the source files, e.g. "DPCR SY 2020-21 January to December rated"), independent of `FacultyLoading\SchoolYear` (Aug–Apr academic year). IPCR v2 targets still need to pull load data from `LoadAssignment`, which is SY-scoped — `spms_fiscal_periods` carries its own calendar-year identity and maps to whichever `SchoolYear` is active at each point, the same cross-referencing problem `IPCRRatingPeriod` already solves for the current semester-based IPCR.
- **Both `OPCR 2026 Accomplishments Monitoring.xlsx` and `Proposed NEW IPCR.xlsx` are in-progress templates, not rated/closed-out examples** — their "final state" (e.g., what a fully signed, terminal-status OPCR looks like) is designed here, not copied from a completed example.

## Goals

- Full OPCR → DPCR → IPCR cascade with real rated entities at every level, each with its own ratee/reviewer/approver chain matching CSC form conventions.
- Auto-computed rating rollup up the cascade, with rater override + required reason at each level.
- Configurable Strategic/Core/Support weights per level and per division, defaulting to 30/50/20.
- One flexible IPCR template (not N role-specific templates), driven by existing `LoadAssignment` data.
- Quarterly accomplishment tracking at OPCR and DPCR level; semestral rating cadence at DPCR/IPCR, annual at OPCR.
- MOV/evidence checklist per IPCR Core Function target, replacing the standalone monitoring spreadsheets.
- Zero risk to the live v1 IPCR module — fully separate tables, routes, and controllers.

## Non-Goals (this spec)

- Migrating or backfilling any v1 IPCR data into v2.
- Cross-cascade reporting dashboards (future work, after all three levels ship and stabilize).
- Deprecating/removing v1 — happens only after user-confirmed stability, as a separate later effort.

## Data Strategy

Fully separate v2 tables (`spms_*` prefix) — no shared tables with the live v1 IPCR module. This means v2's outcome/indicator taxonomy is re-entered independently of v1's `AgencyOutcome`/`PerformanceIndicator`, which is a one-time cost accepted in exchange for zero migration-discipline risk against the currently-deployed, blue-green-constrained v1 code (per this repo's migration rules: shared tables would require every v2 schema change to stay backward-compatible with live v1 controllers reading the same rows — this design avoids that constraint entirely).

## Architecture

### Data Model

**`spms_fiscal_periods`** — replaces `IPCRRatingPeriod` for v2, cadence-aware:

| Column | Notes |
|---|---|
| `id` | |
| `cadence` | `quarter` \| `semester` \| `annual` |
| `fiscal_year` | e.g. `2026` |
| `label` | e.g. "Q1 2026", "1st Semester 2026", "FY 2026" |
| `start_date`, `end_date` | |
| `parent_period_id` | nullable FK self — quarters link to their semester, semesters to their annual period |
| `school_year_id` | nullable FK `school_years.id` — the SY active for the majority of this period, for `LoadAssignment` cross-referencing |
| `is_current` | boolean |

**`spms_outcomes`** (forked shape from `AgencyOutcome`): `outcome`, `sub_outcome`, `function_type` (`strategic`\|`core`\|`support`), `fiscal_year`.

**`spms_performance_indicators`** (forked shape from `PerformanceIndicator`): `spms_outcome_id`, `description`, `target`, `budget`, `fiscal_year`, m2m to `divisions` via `spms_division_performance_indicator`.

**`spms_weight_profiles`** — the wiring `IPCRWeightDistribution` never got:

| Column | Notes |
|---|---|
| `id` | |
| `level` | `opcr` \| `dpcr` \| `ipcr` |
| `division_id` | nullable FK — null = system default for that level |
| `fiscal_year` | |
| `strategic_pct`, `core_pct`, `support_pct` | must sum to 100, default 30/50/20 |
| `core_subweights` | JSON, DPCR-only: `{core_duties_pct, student_eval_pct, supervisor_eval_pct}`, scaled at runtime by unit count |

**`spms_opcrs`** — campus-wide commitment/review record:

| Column | Notes |
|---|---|
| `id` | |
| `fiscal_period_id` | FK, `cadence = annual` |
| `ratee_user_id` | Campus Director |
| `approver_user_id` | Executive Director |
| `status` | `draft` → `submitted_to_ed` → `ed_approved` (terminal) \| `returned` |
| `weight_profile_id` | FK |
| `final_rating`, `final_adjectival` | nullable until computed |
| `override_rating`, `override_reason` | nullable |
| `approved_at`, `approved_by` | |
| timestamps | |

**`spms_opcr_targets`** — `opcr_id`, `spms_performance_indicator_id`, `q1_actual`..`q4_actual`, `q1_rating`..`q4_rating` (Q/E/T/Avg each), `remarks`.

**`spms_dpcrs`** — per-division commitment/review record:

| Column | Notes |
|---|---|
| `id` | |
| `division_id` | FK |
| `fiscal_period_id` | FK, `cadence = semester` |
| `ratee_user_id` | Division Chief |
| `reviewer_user_id` | immediate supervisor |
| `approver_user_id` | Head of Office (Campus Director, as the single head of office for a one-campus deployment) |
| `status` | `draft` → `submitted_to_reviewer` → `reviewed` → `submitted_to_approver` → `approved` (terminal) \| `returned` |
| `weight_profile_id`, `unit_count` | drives `core_subweights` scaling |
| `rolled_up_rating` | auto-computed from division's `spms_ipcrs` |
| `override_rating`, `override_reason` | nullable |
| `final_rating`, `final_adjectival` | |
| timestamps | |

**`spms_dpcr_targets`** — `dpcr_id`, `spms_performance_indicator_id`, quarterly actual/rating columns (mirrors OPCR targets — DPCR tracks quarterly per your cadence decision), `remarks`.

**`spms_ipcrs`** — per-employee record:

| Column | Notes |
|---|---|
| `id` | |
| `user_id` | |
| `fiscal_period_id` | FK, `cadence = semester` |
| `dpcr_id` | nullable FK — null in Phase 1, populated once Phase 2 ships |
| `status` | mirrors current `IPCRWorkflowService` chain: `draft_target` → `target_submitted` → `target_approved` → `submitted_for_rating` → `rated` → `dc_reviewed` → `pmt_hr_reviewed` → `director_signed` (terminal) \| `returned` (at any review step) |
| `weight_profile_id` | |
| `final_rating`, `final_adjectival` | |
| timestamps + submit/approve timestamp columns | mirrors `employee_ipcrs` conventions |

**`spms_ipcr_targets`** — one row per Core/Strategic/Support Function line item:

| Column | Notes |
|---|---|
| `id` | |
| `ipcr_id` | |
| `function_type` | `strategic` \| `core` \| `support` |
| `source_type`, `source_id` | polymorphic — `LoadAssignment`, `Committee`, `SpecialAssignment`, or null for manually-added rows |
| `success_indicator`, `target` | |
| `rubric_text` | embedded rating-scale legend, e.g. "5: 96–100%, 4: 91–95%…" — new vs. v1 |
| `weight_pct` | load-proportional, computed at generation time, editable |
| `actual_q`, `actual_e`, `actual_t` | |
| `rating_q`, `rating_e`, `rating_t`, `rating_avg` | |
| `remarks` | |

**`spms_ipcr_mov_checklist`** — evidence tracking, new in v2:

| Column | Notes |
|---|---|
| `id` | |
| `spms_ipcr_target_id` | FK |
| `document_type` | configurable list, seeded from source files: SIP, OCM/CFFS, Grading Sheets, APR, etc. |
| `status` | `pending` \| `submitted` \| `not_applicable` |
| `s3_key` | nullable — proxy-served per this repo's S3 conventions, never a direct URL |
| `submitted_at`, `submitted_by` | |

### Workflow

```
IPCR (semestral)
  Draft Target → Target Approved → [MOV checklist ongoing, non-gating]
    → Submitted for Rating → Rated
    → (auto-rolls into parent DPCR, once dpcr_id is populated — Phase 2+)
    → DC Reviewed → PMT/HR Reviewed → Director Signed (terminal)

DPCR (quarterly accomplishment + semestral rating)
  Draft
    → Q1..Q4 accomplishment entries (non-gating, ongoing)
    → at semester close: rolled_up_rating computed from division's rated IPCRs
    → Submitted to Reviewer (immediate supervisor) → Reviewed
    → Submitted to Approver (Head of Office / Campus Director) → Approved (terminal)
    (Head of Office = Campus Director in this single-campus deployment)

OPCR (quarterly accomplishment + annual rating)
  Draft
    → Q1..Q4 accomplishment entries (non-gating, ongoing)
    → at fiscal year close: rating computed from campus's approved DPCRs
    → Submitted to Executive Director → ED Approved (terminal)
```

Each level's service (`OPCRWorkflowService`, `DPCRWorkflowService`, `IPCRWorkflowServiceV2`) follows the same terminal-state immutability and audit-trail pattern as the existing `IPCRWorkflowService` — no new state-machine idiom introduced.

### Rollup Engine

`SPMSRollupService` — triggered on rating-period close (semester end for IPCR→DPCR, fiscal year end for DPCR→OPCR) and on-demand via a "Recalculate" action prior to submission:

- **IPCR→DPCR**: load-weighted average of the division's rated `spms_ipcrs`' Core Function ratings against the division's shared `spms_performance_indicators`, written to `spms_dpcrs.rolled_up_rating`.
- **DPCR→OPCR**: average of campus divisions' approved `spms_dpcrs.final_rating`, weighted by division size or a configurable weight, written into the OPCR's computed rating prior to ED submission.
- **Override**: at either level, the ratee/reviewer can set `override_rating` + `override_reason` (required, free text) before submission. The computed value is retained alongside the override for audit purposes — never silently discarded.

### Permissions

- `spms.ipcr.manage` — self-service target/rating flow (all employees)
- `spms.ipcr.review` — DC/PMT/HR review steps
- `spms.dpcr.manage` — Division Chief (own division)
- `spms.dpcr.review` — reviewer step (immediate supervisor)
- `spms.dpcr.approve` — approver step (Head of Office / Campus Director)
- `spms.opcr.manage` — Campus Director
- `spms.opcr.approve` — Executive Director (new role)
- `spms.admin.manage` — HR/PMT config: weight profiles, fiscal periods, MOV document types

### New Role

**Executive Director** — in-system role/permission, real login for the PSHS System ED to review/approve OPCR directly (per your decision — full digital signature chain, consistent with how Campus Director already signs IPCRs today).

## Components (indicative — finalized in the implementation plan)

| Component | Purpose |
|---|---|
| `app/Models/SPMS/{FiscalPeriod,Outcome,PerformanceIndicator,WeightProfile,Opcr,OpcrTarget,Dpcr,DpcrTarget,Ipcr,IpcrTarget,IpcrMovChecklistItem}.php` | v2 models, `SPMS\` namespace to stay clearly distinct from `App\Models\EmployeeIPCR` et al. |
| `app/Services/SPMS/{OPCRWorkflowService,DPCRWorkflowService,IPCRWorkflowService,SPMSRollupService,IPCRTargetGenerationService}.php` | `IPCRTargetGenerationService` extends `FacultyIPCRBaselineService`'s load-driven generation pattern for the new template shape |
| `app/Http/Controllers/SPMS/*Controller.php` | one per role-facing surface (Employee, DivisionChief, CampusDirector, ExecutiveDirector, HR/Admin config) |
| `resources/js/Pages/SPMS/*.vue` | mirrors existing `PerformanceManagement/*.vue` structure, new directory |
| `database/migrations/*_create_spms_*_table.php` | additive-only, no interaction with any `ipcr*`/`employee_ipcrs*` table |

## Testing Plan

- Weight profile resolution: division override vs. system default, `core_subweights` scaling by unit count
- IPCR target generation from `LoadAssignment`: teaching load, research load, committee, special designation rows all produce correctly load-weighted `weight_pct`
- Rollup correctness: IPCR→DPCR load-weighted average matches hand-computed expectation; DPCR→OPCR average; override persists alongside computed value, never lost
- Workflow state transitions + terminal immutability at all three levels, mirroring existing `IPCRWorkflowService` test patterns
- MOV checklist: status transitions, S3 proxy serving (never a direct S3 URL, per repo convention)
- Permission gates: each new `spms.*` permission string on its corresponding controller action
- Regression: confirm zero interaction with any v1 IPCR table/route/permission (no shared foreign keys, no shared migrations)

## Phasing

1. **Phase 1 — IPCR v2**: `spms_fiscal_periods`, `spms_outcomes`, `spms_performance_indicators`, `spms_weight_profiles`, `spms_ipcrs`, `spms_ipcr_targets`, `spms_ipcr_mov_checklist`. Full workflow, load-driven target generation, MOV checklist. `dpcr_id` nullable throughout. Ships and is used for at least one full rating period before Phase 2.
2. **Phase 2 — DPCR**: `spms_dpcrs`, `spms_dpcr_targets`, IPCR→DPCR rollup, quarterly DPCR accomplishment tracking, reviewer/approver workflow. Backfills `dpcr_id` on Phase 1's `spms_ipcrs` going forward.
3. **Phase 3 — OPCR**: `spms_opcrs`, `spms_opcr_targets`, Executive Director role, DPCR→OPCR rollup, quarterly OPCR accomplishment tracking, annual ED approval workflow.

## Out of Scope (this build)

- Cross-cascade reporting/analytics dashboards
- v1 IPCR deprecation or data migration
- Resolving the v1 weight discrepancy (30/55/15 vs. 30/50/20) in the live module — v2 is independent and correct from its own default; v1 is untouched
- Multi-year historical trend views across fiscal periods
