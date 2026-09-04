# OPCR Module — Design

## Context

BugSayMis's Performance Management module already has the DOST Strategic Plan
hierarchy (`DostPillar` → `DostStrategy` → `DostSubStrategy`, with `DostStrategy`
optionally linked to `AgencyOutcome`) and the `performance_indicators` table
(`PerformanceIndicator`, belongs to `AgencyOutcome`, many-to-many to `Division`
via `division_performance_indicator`). Both were built 2026-08-17/18 as
deliberate prep for a future OPCR/DPCR/IPCR refactor, but that refactor never
started — the tables exist and are wired to each other, but nothing consumes
them at the campus-commitment level yet.

The source of truth for what an actual OPCR document looks like is
`Downloads/Performance Management/OPCR.xlsx` (only its first tab, "OPCR" — the
other 14 tabs in that file are unrelated APP-CSE procurement worksheets bundled
into the same workbook and are not part of this module). That tab is PSHS-CRC's
FY2026 Office Performance Commitment and Review: a Campus Director commitment
statement, then a table of indicators grouped by Pillar/Strategy/Sub-Strategy,
each showing a PSHS Program, Target, Alloted Budget, accountable Division(s),
one "Actual Accomp." value, a Q/E/T/A rating (1–5), and Remarks — closed with
Campus Director/OIC and Executive Director signature blocks.

**Prior related work note:** two full-cascade rebuilds in this same problem
space (SPMS OPCR→DPCR→IPCR, 2026-08-16/17; PM V2 IPCR rebuild, 2026-09-02/03)
were built, deployed, and fully reverted the same day/next day the user tried
them ("not what I want," no further detail given either time). This design was
produced through direct, in-session, question-by-question elicitation with the
user (scope boundary, indicator-table isolation, inline tagging, FY rollover,
PDF inclusion, permissions each confirmed individually) rather than handed to
them as a finished document to approve — see conversation history for the
specific answers this spec is built from.

## Decisions (confirmed with user)

1. **OPCR only.** No DPCR, no IPCR, no new "Executive Director" role or login.
   The Executive Director is not a user of this per-campus system — their name
   is free text on the period record, used only for the PDF signature block.
2. **New indicator table, isolated from the existing `PerformanceIndicator`.**
   `performance_indicators` is the exact table employees already select from
   when tagging their own `WorkDistributionPlan` rows. Reusing those rows
   directly for OPCR would mean a Campus Director editing an OPCR indicator's
   target/budget also changes what employees see in their own IPCR picker.
   Instead, OPCR gets its own `opcr_indicators` table with an **optional**
   `performance_indicator_id` cross-reference for when an OPCR indicator
   happens to match an existing IPCR one — informational only, no data flows
   either direction through that link.
3. **Inline tagging.** The OPCR indicator form can create new
   `DostPillar`/`DostStrategy`/`DostSubStrategy`/`AgencyOutcome` rows without
   leaving the page (reuses those existing models/tables as-is — no schema
   change to them).
4. **Quarterly actuals, not a single value.** "Actual Accomp." is tracked
   per-quarter (`opcr_indicator_actuals`, one row per `(indicator, quarter)`),
   not overwritten in place, so mid-year progress is visible.
5. **Rating is recorded, not computed.** Quality/Efficiency/Timeliness/Average
   are plain editable fields on the indicator — the campus receives these
   numbers from the Executive Director outside the system and just records
   them. Average may be pre-filled as a suggestion from Q/E/T but stays
   editable (never enforced as a strict computed value).
6. **FY-scoped with clone-forward**, matching the existing pattern in
   `IPCRRatingPeriodController::copyFramework`. One `opcr_periods` row per
   fiscal year, one `is_current`. Cloning into a new FY copies indicator
   tagging/targets/budget/divisions; actuals and ratings start empty.
7. **PDF export included in this build** — mPDF, matching the source
   document's layout (commitment statement, grouped indicator table,
   signature blocks).
8. **Permissions**: new `opcr.view` / `opcr.manage`. `OCD` (Campus
   Director/OIC role in this system) and `PMT` (Performance Management Team)
   get `opcr.manage`. `DivisionChief` gets `opcr.view`, scoped in-controller
   to indicators where their division is tagged accountable — read-only.
   `Administrator` bypasses via `isSuperAdmin()` as usual.
9. **Budget is manual entry.** No integration with Payroll/PPMP in this pass.

## Data Model

```
opcr_periods
  id
  fiscal_year                 integer, unique
  period_label                string    e.g. "January - December 2026"
  is_current                  boolean, default false
  campus_director_name        string, nullable   (commitment statement signatory)
  oic_campus_director_name    string, nullable   (certifying signatory, if different)
  executive_director_name     string, nullable   (rating signatory)
  commitment_statement        text, nullable     (defaults to a standard sentence
                                                    if left blank at render time)
  timestamps

opcr_indicators
  id
  opcr_period_id               FK -> opcr_periods, cascade delete, required
  dost_sub_strategy_id         FK -> dost_sub_strategies, restrict delete, NULLABLE
  agency_outcome_id            FK -> agency_org_outcomes, restrict delete, NULLABLE
  performance_indicator_id     FK -> performance_indicators, nullable on delete
                                (SET NULL), NULLABLE — optional cross-reference only
  description                  text, required
  target                       string, nullable    ("0.9", "85th percentile", "Top 20")
  budget                       decimal(15,2), nullable
  remarks                      text, nullable
  rating_quality                decimal(3,2), nullable   (1.00–5.00)
  rating_efficiency             decimal(3,2), nullable
  rating_timeliness             decimal(3,2), nullable
  rating_average                decimal(3,2), nullable
  timestamps

opcr_indicator_divisions   (pivot)
  id
  opcr_indicator_id            FK -> opcr_indicators, cascade delete
  division_id                  FK -> divisions, cascade delete
  timestamps

opcr_indicator_actuals
  id
  opcr_indicator_id            FK -> opcr_indicators, cascade delete
  quarter                      tinyint (1–4)
  value                        string, nullable   ("0.8889", "634", "-", "Compliant")
  timestamps
  unique(opcr_indicator_id, quarter)
```

Restrict (not cascade) on `dost_sub_strategy_id`/`agency_outcome_id` deletes:
deleting a Sub-Strategy or Agency Outcome that's still tagged on an OPCR
indicator must fail with a clear message, not silently null out or cascade-
delete a campus commitment record. This mirrors the existing restrict-on-
delete already used for `performance_indicators.agency_outcome_id`.

Models: `App\Models\OPCR\OpcrPeriod`, `App\Models\OPCR\OpcrIndicator`,
`App\Models\OPCR\OpcrIndicatorActual` (namespaced under `OPCR`, matching the
existing `FacultyLoading`/`HR`/`Payroll` sub-namespace convention). The pivot
uses a plain `belongsToMany` — no dedicated pivot model needed, same as
`PerformanceIndicator::divisions()`.

- `OpcrPeriod::indicators()` hasMany `OpcrIndicator`
- `OpcrPeriod::scopeCurrent()` → `where('is_current', true)`
- `OpcrIndicator::period()` belongsTo `OpcrPeriod`
- `OpcrIndicator::subStrategy()` belongsTo `DostSubStrategy` (nullable)
- `OpcrIndicator::agencyOutcome()` belongsTo `AgencyOutcome` (nullable)
- `OpcrIndicator::performanceIndicator()` belongsTo `PerformanceIndicator` (nullable, cross-reference only)
- `OpcrIndicator::divisions()` belongsToMany `Division` via `opcr_indicator_divisions`
- `OpcrIndicator::actuals()` hasMany `OpcrIndicatorActual`
- `OpcrIndicatorActual::indicator()` belongsTo `OpcrIndicator`

Migration files (new, additive-only — no changes to existing tables):
- `create_opcr_periods_table`
- `create_opcr_indicators_table`
- `create_opcr_indicator_divisions_table`
- `create_opcr_indicator_actuals_table`

## Backend

Controllers under `App\Http\Controllers\OPCR`:

- `OpcrPeriodController`
  - `index()` — renders the OPCR page for the current period (or a specified
    `?fiscal_year=`), with the full indicator tree grouped by Pillar →
    Strategy → Sub-Strategy, plus a list of all periods for the FY switcher.
  - `store()` — creates a new empty period (`fiscal_year`, `period_label`
    required); does not auto-mark it current.
  - `update()` — edits period metadata (label, signatory names, commitment
    statement, `is_current` toggle — setting one period current unmarks all
    others in the same transaction, matching the `SchoolYear.is_current`
    pattern used elsewhere).
  - `cloneFrom(Request $request, OpcrPeriod $opcrPeriod)` — validates a
    `source_period_id`, refuses if the target period already has indicators
    (same guard style as `copyFramework`), then within a DB transaction
    replicates each `OpcrIndicator` (excluding actuals/ratings) with its
    `divisions()` pivot re-attached to the new indicator ids.
  - `pdf(OpcrPeriod $opcrPeriod)` — mPDF export using `sys_get_temp_dir()`,
    matching the source layout.
- `OpcrIndicatorController`
  - `store()` / `update()` — validates `opcr_period_id` required/exists,
    `dost_sub_strategy_id` nullable/exists, `agency_outcome_id` nullable/exists,
    `performance_indicator_id` nullable/exists, `description` required,
    `division_ids` array (synced via `divisions()->sync()`).
  - `destroy()`.
  - `updateActual(Request $request, OpcrIndicator $opcrIndicator)` — validates
    `quarter` (1–4) and `value`, upserts the matching `OpcrIndicatorActual` row
    (`updateOrCreate` on `[opcr_indicator_id, quarter]`).
  - `updateRating(Request $request, OpcrIndicator $opcrIndicator)` — validates
    the four rating fields (nullable, numeric, between 1 and 5), saves as-given
    (no server-side recomputation of Average).
- Inline-tagging endpoints reuse the existing `DostPillarController` /
  `DostStrategyController` / `DostSubStrategyController` / `AgencyOutcomeController`
  store actions unchanged — the OPCR indicator form calls them directly (same
  JSON/Inertia contract those controllers already expose), no new endpoints
  needed for tag creation itself.

Routes, new `permission:opcr.view|opcr.manage` group (view = read-only GET,
manage = all mutations) in `routes/web.php`, placed near the existing
`ipcr.view` group:

```
GET    /opcr                                    opcr.index
POST   /opcr-periods                            opcr-periods.store
PUT    /opcr-periods/{opcrPeriod}                opcr-periods.update
POST   /opcr-periods/{opcrPeriod}/clone          opcr-periods.clone
GET    /opcr-periods/{opcrPeriod}/pdf            opcr-periods.pdf
POST   /opcr-indicators                          opcr-indicators.store
PUT    /opcr-indicators/{opcrIndicator}          opcr-indicators.update
DELETE /opcr-indicators/{opcrIndicator}          opcr-indicators.destroy
PUT    /opcr-indicators/{opcrIndicator}/actual   opcr-indicators.actual
PUT    /opcr-indicators/{opcrIndicator}/rating   opcr-indicators.rating
```

Mutation routes gated `permission:opcr.manage`; `GET /opcr` and the PDF route
gated `permission:opcr.view|opcr.manage` (either is sufficient, matching the
project's pipe = ANY convention). `DivisionChief`'s `opcr.view`-only access
means the index controller filters the indicator list to divisions the user's
`division_id` matches when the user lacks `opcr.manage` — enforced
server-side, not just hidden in the UI.

All mutations redirect back with `->with('success', ...)`, per project
convention. `updateActual`/`updateRating` are called from small inline
editors on the index page (not separate pages) — still plain Inertia
`router.put()`, not axios/JSON, since there's no file upload involved.

## Frontend

- New page: `resources/js/Pages/PerformanceManagement/Opcr.vue`
- Nav: new "OPCR" entry under Performance Management in
  `resources/js/Layouts/navigation.js`, alongside "Agency Org Outcome",
  "Performance Indicators", and "DOST Strategic Plan".
- Layout: grouped table matching the source document's shape — rows grouped
  by Pillar → Strategy → Sub-Strategy (merged-cell look via rowspan, not
  repeated text), each indicator row showing Program, Indicator, Target,
  Budget, Divisions (chips), Q1–Q4 Actual (inline-editable per quarter),
  Q/E/T/A Rating (inline-editable), Remarks.
- FY switcher at the top (dropdown of existing periods + "New FY" +
  "Clone from FY —" actions), only visible/enabled for `opcr.manage` users.
- Add/Edit indicator via modal: Pillar/Strategy/Sub-Strategy/Program
  cascading pickers, each with an inline "+ Add new" that opens a small
  nested form (reusing the same validation as the DOST Strategic Plan page)
  instead of navigating away; optional "Link to an existing IPCR indicator"
  searchable picker; Target, Budget, Division multi-select, Description,
  Remarks.
- `DivisionChief` users see the same grouped table filtered to their division,
  with all inputs rendered read-only (no modal, no inline editors).
- "Export PDF" button on the period, visible to `opcr.view` and above.
- Uses `useForm`/Inertia `post`/`put`/`delete` with `preserveScroll: true`,
  matching existing Performance Management pages.

## Testing

Feature tests (PHPUnit, following existing project conventions):

- CRUD happy path for `OpcrPeriod` and `OpcrIndicator`.
- Inline tagging: creating an indicator with a brand-new Pillar/Strategy/
  Sub-Strategy/Program in the same request persists all four correctly linked.
- Quarterly actuals: `updateActual` for quarter 1 then quarter 2 on the same
  indicator produces two rows, not one overwritten row; re-submitting the
  same quarter updates in place (no duplicate).
- Rating: `updateRating` saves exactly what's submitted, including an Average
  that doesn't mathematically match Q/E/T (never recomputed/overridden).
- Clone-forward: cloning FY2026 → FY2027 copies indicator tagging/target/
  budget/divisions; the new period's actuals and ratings are empty; cloning
  into a period that already has indicators is rejected (422).
- Delete-restrict: deleting a `DostSubStrategy`/`AgencyOutcome` still tagged
  on an `OpcrIndicator` fails, doesn't silently orphan the indicator.
- Permission gates: a user with neither `opcr.view` nor `opcr.manage` gets
  403 on every `opcr-*` route. A `DivisionChief` (view-only) gets 403 on
  every mutation route and sees only their division's indicators on `index`.
- PDF: `pdf()` renders successfully for a period with indicators and for an
  empty period (no indicators yet) — no N+1 assumption that breaks empty.

## Explicitly out of scope

- DPCR, IPCR, and any rollup/workflow logic between OPCR and those levels —
  future work, needs its own brainstorming/spec pass, not started here.
- A new "Executive Director" role, login, or in-app rating/approval workflow
  — ratings are recorded as plain data entered by whoever has `opcr.manage`.
- Any change to `performance_indicators`, `WorkDistributionPlan`, or the
  existing IPCR picker — untouched; the cross-reference from
  `opcr_indicators.performance_indicator_id` is one-directional and
  informational only.
- Budget integration with Payroll/PPMP — `budget` is a manually-typed number.
- Automated Rating computation from Actual vs. Target — ratings are supplied
  externally and simply recorded.
