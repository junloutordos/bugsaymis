# SPMS Fiscal Period Admin Form + Self-Service Shell Creation — Design Spec

**Date:** 2026-08-17
**Status:** Approved

## Summary

The SPMS cascade (OPCR/DPCR/IPCR, shipped across Phases 1–3) is live in production but functionally unusable: the Admin Config page has no Fiscal Period creation form at all (only Weight Profiles), and even if a Fiscal Period existed, nothing anywhere creates the initial `Ipcr`/`Dpcr`/`Opcr` "shell" record for an employee/division/campus. This spec closes both gaps: a real Fiscal Period admin form (with an `is_current` toggle) and one self-service "create my record" action per role-facing controller.

## Context

Discovered live in production after Phase 3 deployed: a user opened the SPMS pages and found them all empty. Investigation found two stacked gaps, both pre-existing since Phase 1 and never revisited:

1. **`AdminConfigController::storeFiscalPeriod`** exists and is routed, but `AdminConfigIndex.vue` never renders a form that calls it — only the Weight Profile form exists on that page. Even via direct API call, the validated fields never include `is_current`, so every created period defaults to `is_current = false` (the migration's column default) with no way to change it.
2. **No shell-creation flow exists at any cascade level.** `EmployeeIpcrController`, `DivisionChiefDpcrController`, and `CampusDirectorOpcrController` all have `index()`/`show()` and workflow-transition actions, but nothing that creates the initial record. This was a known, explicitly-documented scope boundary from Phase 1 ("Ipcr shell rows are never auto-created") that was carried through Phases 2 and 3 without being revisited — it wasn't blocking automated tests (which create rows via factories) so it went unnoticed until a human tried to use the real UI.

## Goals

- An admin can create a Fiscal Period through the UI and mark it as the current one for its cadence.
- Exactly one `is_current = true` period per cadence at all times — marking a new one current automatically un-marks the old one (same pattern as this app's existing `SchoolYear::is_current`).
- Any employee can create their own IPCR for the current semester period with one click.
- Any Division Chief can create their division's DPCR for the current semester period with one click.
- The Campus Director (OCD) can create the campus OPCR for the current annual period with one click.
- Re-clicking "Create" when a record already exists opens the existing one instead of erroring or duplicating.
- A clear, actionable error when no current period of the right cadence exists yet ("ask Admin to set one up"), rather than a silent failure or 500.

## Non-Goals

- A `level` dropdown for the Weight Profile form (still hardcoded to `ipcr`). Separate pre-existing gap; `WeightProfileResolver` already falls back to 30/50/20 defaults when no profile exists for a level, so DPCR/OPCR aren't blocked by this.
- Bulk/admin-driven shell creation (e.g., "create IPCRs for all employees at once"). Self-service one-at-a-time covers the immediate need; bulk generation is a separate future feature if requested.
- Any change to the workflow state machines, rollup formulas, or permissions — this spec only adds the missing "create" entry point in front of the already-working cascade.

## Design

### Fiscal Period admin form

`AdminConfigController::storeFiscalPeriod` validation gains `'is_current' => ['sometimes', 'boolean']`. When the validated value is truthy, wrap the create in a transaction:

```php
DB::transaction(function () use ($validated) {
    if (!empty($validated['is_current'])) {
        FiscalPeriod::where('cadence', $validated['cadence'])->update(['is_current' => false]);
    }
    FiscalPeriod::create($validated);
});
```

`AdminConfigIndex.vue` gains a second form (mirroring the existing Weight Profile form's structure) with fields for `cadence` (select: quarter/semester/annual), `fiscal_year`, `label`, `start_date`, `end_date`, `parent_period_id` (nullable select from existing periods), and an `is_current` checkbox. The existing Fiscal Periods list (already passed as a prop, currently unused in the template) gets rendered as a table showing cadence, label, fiscal year, and a "Current" badge when `is_current` is true.

### Self-service shell creation

Each controller gets a `store()` action following the same shape — resolve the current period of the right cadence, guard against missing period, guard against duplicate, create, redirect to `show`:

```php
public function store(): RedirectResponse
{
    $period = FiscalPeriod::current()->ofCadence('semester')->first(); // 'annual' for OPCR
    if (!$period) {
        return back()->withErrors(['fiscal_period' => 'No current fiscal period is configured for this cadence. Ask an SPMS Admin to set one up.']);
    }

    $existing = /* find existing record for this user/division + $period */;
    if ($existing) {
        return redirect()->route('spms.<level>.show', $existing->id);
    }

    $record = <Model>::create([/* ... */]);

    return redirect()->route('spms.<level>.show', $record->id)->with('success', '<Level> created.');
}
```

Per-level specifics:
- **IPCR**: `Ipcr::where('user_id', Auth::id())->where('fiscal_period_id', $period->id)`. Create with `user_id = Auth::id()`.
- **DPCR**: requires `Auth::user()->division_id` to be set — if null, `back()->withErrors(['division' => 'Your account has no division assigned. Contact HR.'])`. Otherwise `Dpcr::where('division_id', $divisionId)->where('fiscal_period_id', $period->id)`. Create with `division_id`, `ratee_user_id = Auth::id()`.
- **OPCR**: campus-wide, no division scoping. `Opcr::where('fiscal_period_id', $period->id)`. Create with `ratee_user_id = Auth::id()`.

New routes: `POST /spms/ipcr` → `spms.ipcr.store`, `POST /spms/dpcr` → `spms.dpcr.store`, `POST /spms/opcr` → `spms.opcr.store`, each under the existing permission-gated route groups for that controller.

### Frontend

Each Index page (`EmployeeIpcrIndex.vue`, `DivisionChiefDpcrIndex.vue`, `CampusDirectorOpcrIndex.vue`) gets a "Create My IPCR/DPCR" (or "Create OPCR" for Campus Director) button above the table, and the empty-table case gets a one-line message ("No records yet — click Create to get started.") instead of just empty `<tbody>`.

### Testing

- `storeFiscalPeriod` with `is_current: true` un-marks any other period of the same cadence (feature test).
- `storeFiscalPeriod` without `is_current` leaves existing current-period flags untouched.
- Each controller's `store()`: creates a record when none exists; redirects to the existing record on a second call (no duplicate row); returns a validation error when no current period exists for that cadence; DPCR's additionally errors when the actor has no `division_id`.
- Full HTTP walkthrough per level extending the existing `IpcrFullWorkflowTest`/`DpcrFullWorkflowTest`/`OpcrFullWorkflowTest` pattern: `store()` → full existing workflow, proving the new entry point feeds cleanly into the already-tested cascade.
