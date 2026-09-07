# IPCR V2 — Strategic Function Year-Lag & Core/Support Self-Rating — Design

## Background

The IPCR V2 module (`docs/superpowers/specs/2026-09-07-ipcr-v2-design.md`, polished further in
`docs/superpowers/specs/2026-09-08-ipcr-v2-workflow-polish-design.md`) currently:

- Sources Strategic Function ratings live from `OpcrIndicator::forFiscalYear($year)` where `$year`
  is the **same** fiscal year as the IPCR's own rating period
  (`StrategicFunctionService::currentFiscalYear()` → `IPCRRatingPeriod::current()->value('year')`).
- Rates Core and Support Function items with a single column group
  (`quality_rating`/`efficiency_rating`/`timeliness_rating`/`row_average`, or the CSC 4-criteria
  teaching rubric for untagged Core items), settable **only** by the Division Chief via
  `DivisionChiefIpcrV2Controller::rateCoreItem()`/`rateSupportItem()`. The employee has no rating
  input at all — only `target`/`actual_accomplishment`/`mov_link`.

Two problems, both confirmed directly with the user:

1. **A fiscal year's own OPCR rating isn't knowable until the year is essentially over** (it's
   computed from that year's actual accomplishment data). An IPCR for FY2026 asking for FY2026's
   OPCR rating is asking for a number that doesn't exist yet — it needs the **last completed**
   year's rating instead (FY2026 IPCR → FY2025 OPCR).
2. **IPCR V1 already has an employee self-rating step that IPCR V2 is missing.** V1's
   `employee_ipcrs_plan` table carries `self_quality/self_efficiency/self_timeliness/self_average`
   (filled by the employee, informational) alongside `sup_quality/sup_efficiency/sup_timeliness/
   sup_average` (filled by the Division Chief — confirmed via grep as the only columns ever read by
   `AdminIPCRController`/`HRIPCRController`/`DivisionChiefIPCRController`/`MyUnitIPCRController`'s
   aggregate queries and `IPCRWorkflowService`'s final-rating computation). IPCR V2 has no
   equivalent — the employee self-rates nothing.

## Goals

1. Strategic Function ratings come from the **prior** fiscal year's OPCR, not the current one.
2. Core and Support Function items each carry a parallel, employee-filled **self-rating** column
   group alongside the existing Division-Chief-filled one, mirroring V1's `self_*`/`sup_*` split
   exactly.
3. Self-rating is **required** before the employee can submit for rating — `submitForRating()`
   rejects if any Core/Support item is missing a self-rating.
4. Self-ratings are informational only: they never feed `IpcrV2RatingService::computeFinalRating()`
   or the Rating Summary — the Division Chief's rating remains the sole official value everywhere,
   exactly like V1.

## Non-goals

- No change to `computeFinalRating()`, `IpcrV2SummaryService::buildRows()`, or any Rating Summary
  number — self-ratings are additive display/input only.
- No change to Strategic Function's read-only, campus-wide, non-per-employee nature — only which
  fiscal year's OPCR data it reads.
- No fallback walk-further-back-in-time logic for the OPCR year lookup — if the prior year has no
  indicators, the section renders empty, identical to today's existing empty-state behavior.
- No new routes for self-rating — it's folded into the employee's existing
  `updateCoreItem()`/`updateSupportItem()` endpoints, matching V1's single-combined-save UX.
- No change to the IPCR V2 status set, `TRANSITIONS` map, or any workflow-polish work already
  shipped (audit timeline, PIN signing, notifications, admin reopen) — this spec is scoped purely
  to the rating data model and the two controllers/services/Vue tables it touches.

## 1. Strategic Function — prior-year OPCR lookup

`StrategicFunctionService::currentFiscalYear()` is called only internally (confirmed via
repo-wide grep — zero external callers, no test references it directly). Rename to
`ratingFiscalYear()` and change its body:

```php
public function ratingFiscalYear(): ?int
{
    $currentYear = IPCRRatingPeriod::current()->value('year');

    return $currentYear ? $currentYear - 1 : null;
}
```

`currentIndicators()` updates its one call site accordingly. No other change to that method — the
empty-collection early return when `$year` is null already covers "no current rating period," and
naturally also now covers "current period's year minus one has no `OpcrIndicator` rows" once
`OpcrIndicator::forFiscalYear($year)` returns nothing for that year — no new branch needed.

## 2. Self-rating columns

Additive migration, two tables:

**`ipcr_v2_core_items`** — new nullable columns, mirroring the existing ones column-for-column:
- `self_quality_rating` (unsignedTinyInteger) — WDP-tagged Q/E/T shape
- `self_efficiency_rating` (unsignedTinyInteger) — WDP-tagged Q/E/T shape
- `self_student_feedback_rating` (unsignedTinyInteger) — untagged teaching shape
- `self_supervisor_feedback_rating` (unsignedTinyInteger) — untagged teaching shape
- `self_im_development_rating` (unsignedTinyInteger) — untagged teaching shape
- `self_timeliness_rating` (unsignedTinyInteger) — **shared** between both rubrics, mirroring the
  existing shared `timeliness_rating` column
- `self_row_average` (decimal 4,2) — shared between both rubrics, mirroring `row_average`

**`ipcr_v2_support_items`** — new nullable columns (Q/E/T shape only, no teaching rubric exists
here):
- `self_quality_rating`, `self_efficiency_rating`, `self_timeliness_rating` (unsignedTinyInteger)
- `self_row_average` (decimal 4,2)

No `self_remarks` — V1's `employee_ipcrs_plan` has a single shared `remarks` column, not a
self/sup split, and IPCR V2's existing `remarks` column on both item tables stays as-is (still
Division-Chief-facing, unchanged).

## 3. Employee self-rating action

No new routes. Extend `EmployeeIpcrV2Controller::updateCoreItem()` and `updateSupportItem()`
(currently `target`/`actual_accomplishment`/`mov_link` only) to also accept and persist the
matching `self_*` fields, computing `self_row_average` server-side with the exact same weighting
already used by `DivisionChiefIpcrV2Controller::rateCoreItem()`:

- WDP-tagged Core items and all Support items: straight average of the 3 self-criteria.
- Untagged (teaching-load) Core items: `self_student_feedback_rating * 0.30 +
  self_supervisor_feedback_rating * 0.20 + self_im_development_rating * 0.20 +
  self_timeliness_rating * 0.30`.

`updateCoreItem()` branches on `$coreItem->success_indicator !== null` to pick the right validation
rule set — identical branch condition already used by `rateCoreItem()`, just applied to the `self_*`
fields instead. `updateSupportItem()` needs only the flat 3-criteria rule. All `self_*` fields are
`nullable` at this per-save validation level (`saveEmployeeFields()` in the Vue layer fires on every
field blur with whatever the item currently holds, not just complete rating sets) — `self_row_average`
is computed only when all of that shape's self-criteria are present, left `null` otherwise.
Completeness is enforced once, at submission time (§4), not on every intermediate save.

One combined PUT request saves target/accomplishment/MOV-link and self-rating together — matches
V1's `EmployeeIPCRController` pattern (a single save endpoint, no separate "submit self-rating"
step) and requires no new workflow status.

## 4. Required before submission

`EmployeeIpcrV2Controller::submitForRating()` gains a precondition check before calling
`transition()`: every row in `$record->coreItems` and `$record->supportItems` must have
`self_row_average` set. If any are missing, throw a `ValidationException` (key `self_rating`)
naming the count of unrated items, rather than a generic message — e.g. "3 Core/Support item(s)
still need a self-rating before you can submit for rating." This mirrors the existing pattern of
`IpcrV2GenerationService`'s validation messages (specific, actionable, not generic).

Strategic Functions are excluded from this check — they have no item rows to rate (read-only,
inherited from OPCR), so there's nothing for the employee to self-rate there.

## 5. UI changes

`IpcrV2CoreItemsTable.vue` and `IpcrV2SupportItemsTable.vue` each gain a second Q/E/T/A column
group for self-rating, positioned before the existing Division-Chief column group (self-assessment
naturally precedes supervisor assessment, matching the real-world flow and V1's on-screen column
order). Editable when `isOwner && isMutable` (the employee); read-only elsewhere, exactly the same
gating already used for `target`/`actual_accomplishment`. The existing Division-Chief column group
keeps its current `canRate` gating, completely unchanged.

This adds columns to a table whose `<thead>` is currently duplicated verbatim across
`EmployeeIpcrV2Show.vue`, `DivisionChiefIpcrV2Show.vue`, and `PMTIpcrV2Show.vue` (confirmed — all
three carry an identical hard-coded header block). All three need their header `colspan`s and
column labels updated together so the column count stays consistent with the tbody every table
renders into that shared header. This is the largest part of the diff by file count, even though
each individual change is mechanical.

## 6. Non-goal, explicit

`IpcrV2RatingService::computeFinalRating()`, `IpcrV2SummaryService::buildRows()`, the Rating
Summary table, and the PDF all continue reading only `quality_rating`/`efficiency_rating`/
`timeliness_rating`/`student_feedback_rating`/`supervisor_feedback_rating`/`im_development_rating`/
`row_average` (the Division-Chief/official set). None of them are touched by this spec — self-rating
columns are write-once-by-employee, read-only-display-everywhere-else, and never enter any
aggregate computation, exactly like V1.

## Testing

- `StrategicFunctionServiceTest`: `ratingFiscalYear()` returns current-period-year minus one;
  `currentIndicators()` returns empty when the prior year has no `OpcrIndicator` rows even though
  the current year does (regression guard against accidentally reading the wrong year).
- `IpcrV2GenerationServiceTest` / new test file: self-rating columns start null on generation
  (no behavior change to generation itself — confirms this spec doesn't touch item creation).
- `EmployeeIpcrV2ControllerTest`: self-rating saved via `updateCoreItem()`/`updateSupportItem()`
  for both Core rubrics and Support items, with correct `self_row_average` computation per
  weighting formula; `submitForRating()` rejected when any item lacks a self-rating; succeeds once
  all items have one.
- `DivisionChiefIpcrV2ControllerTest`: existing `rateCoreItem()`/`rateSupportItem()` tests continue
  passing unchanged (regression guard — self-rating columns must not interfere with the official
  rating path).
- `IpcrV2SummaryServiceTest` / `IpcrV2RatingServiceTest`: existing tests continue passing unchanged
  (regression guard — self-rating columns must not appear in any summary/final-rating output).
