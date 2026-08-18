# Agency Org Outcome Hierarchy — Design

## Context

`agency_org_outcomes` (model `AgencyOutcome`) is a flat table: `outcome`, `sub_outcome`,
`function_type`, `fiscal_year` — all plain columns, no self-reference. "An outcome with
multiple sub-outcomes" is achieved today only by inserting **duplicate rows that repeat
the same `outcome` text**, each with a different `sub_outcome`. Confirmed in production:
4 of the 9 existing rows (ids 1–4) all carry the outcome text "A. STEM Secondary
Education on Scholarship Basis Program", differing only in `sub_outcome` (A.1–A.4).

`performance_indicators.agency_outcome_id` is a required FK into this table (25 rows in
prod), which feeds `work_distribution_plans` (515 rows) — the actual data every IPCR
Show page renders. So every one of those 25 performance indicators already points at a
specific "leaf" row (whichever duplicate-text row matches its intended sub-outcome).

The 5 IPCR Show pages (`Employee`, `HR`, `DivisionChief`, `PMT`, `AdminIPCRShow.vue`)
each independently re-implement the same grouping computed
(`groupedPlansByFunction`): group plans by `function_type` → raw `outcome` string
(exact-text match across rows — fragile, since it depends on every duplicate row's
`outcome` text staying byte-identical) → `sub_outcome` **truncated to its first 4
characters** as the group key and the only thing rendered in that table cell
(`resources/js/Pages/PerformanceManagement/EmployeeIPCRShow.vue:273,982` and
equivalents). This isn't a print-layout constraint — the `<td>` just displays the
truncated string as its full visible content, so today's output already loses
information whenever `sub_outcome` text is longer than 4 characters, and two
sub-outcomes sharing a 4-character prefix would silently collapse into one printed
group. Printing itself is `window.print()` (no blade/mPDF template involved).

Two other tables reference this data and are unaffected by anything below:
`dost_strategies.agency_outcome_id` (nullable FK, 0 rows in prod — the DOST Pillar/
Strategy/Sub-Strategy hierarchy shipped 2026-08-17 is a separate, unpopulated layer)
and `IPCRRatingPeriodController::copyFramework()`, the fiscal-year rollover cloner,
which already clones `AgencyOutcome` rows and remaps `performance_indicators.
agency_outcome_id` through an `$outcomeMap` (old id → new id) — this method needs a
second remap pass added for the new `parent_id` column (see Backend section).

**Confirmed with user:** this is bounded to the outcome/sub-outcome data model and its
IPCR display consumers. It is explicitly **not** a revival of the OPCR/DPCR/IPCR-v2
cascade that was built and fully reverted on 2026-08-16/17 — same checkpoint discipline
applies: ship this bounded piece, no further IPCR refactor without a fresh go-ahead.

## Decisions (confirmed with user)

1. **Self-referencing `parent_id` on `agency_org_outcomes`**, not a second table. Chosen
   over splitting into `agency_outcomes` + `agency_sub_outcomes` because that would
   require repointing the live required `performance_indicators.agency_outcome_id` FK
   (25 rows / 515 downstream plans) through a two-deploy expand/contract, for no
   behavioral benefit at this scope — `parent_id` is purely additive and every existing
   FK value keeps pointing at exactly the row it already points at.
2. **Auto-backfill by matching `outcome` text.** Rows sharing identical `outcome` text
   get a new parent row created (outcome text only) and get `parent_id` set to it.
   Single-row outcomes (including the "Core Functions"/"Support Functions" rows, ids 8–9,
   where `outcome == sub_outcome == function_type`) are not part of any duplicate group
   and stay top-level (`parent_id` null) — no special-casing needed, they're just
   plain single outcomes with placeholder-looking text. Flagged for the user to
   optionally rename post-deploy, not because the migration can't handle them.
3. **Delete guards, not cascade.** `performance_indicators.agency_outcome_id`
   currently cascades on delete — deleting an outcome silently deletes its performance
   indicators, which cascades to work distribution plans. That's a live data-loss risk
   independent of this task; since it's directly in scope (outcome deletion), fix it here:
   change to `restrict`. Deleting a parent outcome that still has children is also
   blocked (422) rather than orphaning them.
4. **Upgrade the existing `AgencyOrgOutcome.vue` / `outcome.*` routes in place** rather
   than add a new nav entry. That page is already the outcome management screen (flat
   table + modal CRUD); it becomes a tree view (outcome rows expand to their
   sub-outcomes) using the same route names and the same `AgencyOutcomeController`,
   extended — not a new controller, since outcome and sub-outcome are the same table/
   entity, unlike the DOST module's genuinely separate Pillar/Strategy/Sub-Strategy
   tables.
5. **`PerformanceIndicators.vue`'s picker** (currently labeled "Sub-Outcome" but backed
   by the full `agency_outcome_id` FK) is relabeled and grouped (`<optgroup>` per parent
   outcome) to reflect the real hierarchy, and can only select a leaf (a child row if
   the outcome has children, otherwise the top-level row itself) — matching what
   `performance_indicators.agency_outcome_id` already points at today.
6. **All 5 IPCR Show pages consolidate onto one shared composable** (grouping logic is
   identical across them today, just copy-pasted) instead of being patched
   independently — removes the update-5-places-identically risk and the 4-character
   truncation bug in one place.
7. **No fiscal-year semantics change.** `parent_id` is not fiscal-year-scoped itself
   (it points at another `agency_org_outcomes` row, which already carries its own
   `fiscal_year`); `copyFramework()`'s per-year clone naturally produces a fresh
   parent/child pair scoped to the target year once its remap pass is added (below).
8. **DOST hierarchy untouched.** `dost_strategies.agency_outcome_id`'s target semantics
   don't change — it can point at a parent or a child row exactly as before; that table
   is unpopulated in prod so there's no real-data risk either way.

## Data Model

```
agency_org_outcomes                          (existing table, additive change only)
  id
  outcome              string
  sub_outcome          string    nullable
  function_type        string    nullable
  fiscal_year           smallint  nullable, indexed
  parent_id             FK -> agency_org_outcomes.id, nullable, indexed, RESTRICT on delete   -- NEW
  timestamps
```

`AgencyOutcome` model additions:
- `parent()` — belongsTo self (`parent_id`)
- `children()` — hasMany self (`parent_id`)
- `scopeTopLevel($query)` — `whereNull('parent_id')`
- keep existing `scopeForFiscalYear`

`performance_indicators.agency_outcome_id`: FK behavior changes from `cascade` to
`restrict` on delete (migration alters the existing foreign key constraint — drop +
recreate, no column/type change, no data loss, safe to run alongside old code since old
code never intentionally deletes an outcome that still has indicators).

Migration files (new):
- `add_parent_id_to_agency_org_outcomes_table` — adds nullable self-FK + index.
- `restrict_delete_on_performance_indicators_agency_outcome_id` — drops and recreates
  that FK with `onDelete('restrict')`.
- One-time backfill (Artisan command run manually post-deploy, e.g.
  `php artisan agency-outcomes:backfill-hierarchy`, not baked into the schema migration
  itself — keeps the reversible/idempotent data step separate from the structural one):
  groups existing rows by identical `outcome` text, creates a parent row per group with
  >1 member, sets `parent_id` on the group's members. Logs a summary (groups created,
  rows linked, and the ids/text of any single-row outcomes whose text looks like a
  placeholder, e.g. `outcome === function_type`) for manual review.

## Backend

`AgencyOutcomeController` (extended, not replaced):
- `index()` — returns `AgencyOutcome::topLevel()->with('children')->...` (FY-scoped as
  today) instead of a flat list, so the Vue page can render outcome rows with their
  sub-outcomes nested.
- `store()`/`update()` — validation gains `parent_id => 'nullable|exists:agency_org_outcomes,id'`.
  When `parent_id` is present, `function_type` and `fiscal_year` are inherited from the
  parent server-side (a sub-outcome doesn't get its own independent function type/year)
  rather than re-validated as required on the child form.
- `destroy()` — before deleting, check `children()->exists()` → 422 "Delete its N
  sub-outcomes first" and check `performanceIndicators()->exists()` → 422 naming which
  indicators still reference it (the FK `restrict` would already stop this at the DB
  level; the explicit check just gives a clean validation message instead of a raw SQL
  exception bubbling up).

`PerformanceIndicatorController::index()` — `outcomes` prop changes from a flat
`AgencyOutcome::query()->get()` to the same `topLevel()->with('children')` shape, for
the relabeled/grouped picker.

`IPCRRatingPeriodController::copyFramework()` (line ~186–221) — after the existing
outcome-cloning loop builds `$outcomeMap` (old id → new id), add a second pass over the
newly created clones: for each clone whose *original* had a non-null `parent_id`, set
`$clone->parent_id = $outcomeMap[$original->parent_id] ?? null` and save. Must run after
the full `$outcomeMap` is built (a child can be cloned before or after its parent in
iteration order), so this is a distinct loop, not inlined into the first one.

## Frontend

- `AgencyOrgOutcome.vue` (existing page, same route `outcome.index`): table becomes
  expandable — each top-level outcome row expands to list its sub-outcomes underneath.
  "Add Sub-Outcome" action on an outcome row opens the existing modal pre-filled with
  `parent_id`, hides the `function_type`/`fiscal_year` fields (inherited from parent,
  shown read-only instead). Delete button on an outcome with children is disabled with
  a tooltip explaining why, matching the backend guard.
- `PerformanceIndicators.vue` — the `agency_outcome_id` select relabeled from
  "Sub-Outcome" to "Outcome", grouped via `<optgroup :label="parent.outcome">` per
  parent, options are the leaf rows (a parent's children if it has any, otherwise the
  parent itself).
- New composable `resources/js/Composables/useIpcrOutcomeGrouping.js`: takes `plans`,
  returns the same nested structure (`functionType → outcome → subOutcome → piDesc →
  plans[]`) the 5 pages already build, but keyed by the real `parent.outcome ??
  outcome` and the **full** `sub_outcome` text (no `.slice(0, 4)`). All 5 IPCR Show
  pages replace their local `groupedPlansByFunction` computed with a call to this
  composable; the print markup (`resources/js/Pages/PerformanceManagement/
  EmployeeIPCRShow.vue:970-983` and its 4 counterparts) is otherwise unchanged — the
  `<td>` already renders whatever the key is, so no template restructuring needed
  there, just visually longer cell content after the fix.

## Testing

Feature tests (Pest/PHPUnit, project convention):
- Backfill command: given the 4 duplicate-outcome-text rows, produces exactly 1 new
  parent row and links all 4 as children by id (existing ids/FKs unchanged); single-row
  outcomes untouched (`parent_id` stays null).
- Delete guards: deleting a parent with children → 422; deleting an outcome still
  referenced by a performance indicator → 422; deleting a childless, unreferenced
  outcome → succeeds.
- `AgencyOutcomeController::store` — creating a child inherits parent's `function_type`/
  `fiscal_year` regardless of what's submitted for those fields.
- `copyFramework()` — cloning a fiscal year that includes a parent+children group
  produces a new parent+children group in the target year with `parent_id` pointing at
  the *new* year's parent, not the source year's.
- `useIpcrOutcomeGrouping` — unit test (Vitest, if the project has JS unit tests
  configured; otherwise a manual click-through) confirming full `sub_outcome` text
  appears (not truncated) and two sub-outcomes sharing a 4-character prefix no longer
  collapse into one group.
- Manual click-through of all 5 IPCR Show pages against the real backfilled prod-shaped
  data (dev DB seeded from the backfill) to confirm grouping and `window.print()` output
  are correct before deploy.

## Rollout

Single deploy, additive only — no expand/contract split needed:
1. Migrations: add `parent_id` (nullable), alter FK to `restrict`. Old code never reads
   `parent_id` and doesn't intentionally delete outcomes-with-indicators, so it keeps
   working unmodified against the new schema during the blue/green window.
2. Run the backfill Artisan command once, post-migration (manual step, not automatic on
   boot — consistent with `migrate --force` being intentionally excluded from
   `docker-entrypoint.sh`).
3. Ship backend + frontend changes together (single Inertia asset version, no partial
   rollout risk there).
4. Manually review the flagged placeholder rows (Core Functions / Support Functions)
   post-deploy.

## Explicitly out of scope

- DOST Pillar/Strategy/Sub-Strategy hierarchy — untouched.
- Any OPCR/DPCR/IPCR-v2 cascade or rollup work — not revived.
- `dost_strategies.agency_outcome_id` target semantics — unchanged.
- Server-side PDF generation for IPCR — printing stays `window.print()`; no blade/mPDF
  template introduced.
