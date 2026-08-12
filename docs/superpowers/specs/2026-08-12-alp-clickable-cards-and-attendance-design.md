# ALP Clickable Dashboard Cards + Attendance Restyle — Design

**Date:** 2026-08-12
**Status:** Approved, pending implementation plan

## Summary

Two independent, additive changes to the Alternative Learning Program (ALP) module:

1. The "Active Members" and "Unassigned Grades 7–10" stat cards on the ALP dashboard (`CID/ALP/Index.vue`) become clickable, opening dedicated pages with a searchable/filterable, printable/PDF-exportable roster.
2. The ALP attendance-taking UI (`CID/ALP/Show.vue`, Attendance tab) is restyled to match the Homeroom Attendance module's UX pattern (color-coded toggle pills, "Mark All Present" bulk action, status legend) while keeping ALP's existing session-based data model and 5-status set unchanged.

No database schema changes. No new permissions — both new list routes reuse the existing `permission:alp.view|alp.manage|alp.advise|alp.coordinate|alp.registrar-certify|alp.approve|alp.reports|alp.audit` group already guarding `alp.index`.

## Current state (for reference)

- `AlpController::index()` (`app/Http/Controllers/ALP/AlpController.php`) computes two aggregate counts only, no list:
  - `members` = sum of `active_members_count` (active `AlpMembership` rows) across all `AlpProgramCycle` records.
  - `unassignedRequired` = `max(0, required_count - assigned_count)`, where required = enrolled `StudentEnrollment` grade 7–10 for the current school year, and assigned = active `AlpMembership` rows whose enrollment is grade 7–10.
- `CID/ALP/Index.vue` renders these as static, non-interactive `AppCard`s (`statCards` computed, lines ~42-47).
- ALP already has an mPDF export pattern: `AlpPdfController` methods each call a builder on `AlpPdfService` and render a Blade view under `resources/views/alp/*.blade.php`.
- ALP attendance (`Show.vue` Attendance tab) is session-based: an adviser creates a "session" card (date/topic/venue), which auto-seeds `alp_attendance` rows (default `present`) for all active members. Each session has its own embedded roster and its own "Save attendance" button. Status is currently a plain `<AppSelect>` dropdown with 5 options: `present`, `absent`, `tardy`, `cutting`, `excused` (validated server-side, `AlpController::saveAttendance()`).
- Homeroom Attendance (`resources/js/Pages/HomeroomAttendance/Daily.vue`) is date-based (one table per section per day) and uses color-coded toggle pill buttons (P/A/T) with a "Mark All Present" bulk action and a live present-counter, instead of a dropdown.

## Feature 1: Active Members list + PDF

### Backend

New method `AlpController::membersIndex()`:
- Query: `AlpMembership::where('status', 'active')->where('school_year_id', $currentSchoolYearId)`, eager-loading `student`, `enrollment.section`, and `cycle.program` — across **all** program cycles (matching how the dashboard's `members` count is already summed across cycles).
- Returns, per row: student full name, `enrollment.grade_level`, `enrollment.section.sectionname`, `cycle.program.name` (the "ALP" column).
- Renders `Inertia::render('CID/ALP/Members', ['members' => [...]])`.

New route: `GET /cid/alp/members` → name `alp.members.index`, added to the existing `permission:...` group in `routes/alp.php`.

### Frontend

New page `resources/js/Pages/CID/ALP/Members.vue`:
- `AppTable` with columns: Name, Grade, Section, ALP.
- Search box (name) + Grade filter + Section filter, client-side filtering over the loaded set.
- Local pagination, `PER_PAGE = 15` (project convention).
- "Download PDF" button linking to the PDF route (plain `<a target="_blank">`, matching the Class Record PDF pattern — no JS PDF generation).
- "Back to ALP Dashboard" link.

### PDF

New `AlpPdfController::membersList()` → route `GET /cid/alp/members.pdf` → name `alp.members.pdf`. Calls a new builder method on `AlpPdfService`, renders new Blade view `resources/views/alp/members-list.blade.php` (school header, generated-on timestamp, full unfiltered table: Name / Grade / Section / ALP). PDF always contains the complete list regardless of on-screen filters.

## Feature 2: Unassigned Grades 7–10 list + PDF

### Backend

New method `AlpController::unassignedIndex()`:
- Reuses the exact universe/exclusion logic already used for the count:
  - Universe: `StudentEnrollment::where('school_year_id', $currentSchoolYearId)->where('status', 'enrolled')->whereBetween('grade_level', [7, 10])`.
  - Excluded: student IDs with an active `AlpMembership` for the current school year (`whereHas`/`whereNotIn` on the assigned set).
- Returns, per row: student full name, `grade_level`, `section.sectionname`. **No ALP column** (unassigned by definition).
- Renders `Inertia::render('CID/ALP/Unassigned', ['students' => [...]])`.

New route: `GET /cid/alp/unassigned` → name `alp.unassigned.index`.

### Frontend

New page `resources/js/Pages/CID/ALP/Unassigned.vue` — same table/search/filter/pagination/PDF pattern as `Members.vue`, minus the ALP column.

### PDF

New `AlpPdfController::unassignedList()` → route `GET /cid/alp/unassigned.pdf` → name `alp.unassigned.pdf`. New Blade view `resources/views/alp/unassigned-list.blade.php` (Name / Grade / Section only).

## Dashboard card wiring

In `CID/ALP/Index.vue`, the "Active members" and "Unassigned Grades 7–10" `AppCard`s become clickable (`router.visit(route('alp.members.index'))` / `router.visit(route('alp.unassigned.index'))`), with a hover state and a cursor-pointer affordance to signal interactivity. The other two cards (Programs, Accredited) are unaffected — no list exists for those and none was requested.

## Feature 3: ALP Attendance restyle (match Homeroom UX)

Scope: **restyle only** — ALP keeps its session-based structure, its 5 statuses, its per-session Save button, and its `open`/`closed` session workflow. Only the per-row status control and the addition of bulk/legend affordances change. No controller, model, or migration changes — `saveAttendance()` already validates `in:present,absent,tardy,cutting,excused`.

In `resources/js/Pages/CID/ALP/Show.vue`, within each session card's roster (currently line ~315, an `<AppSelect>` per row):

- Replace the dropdown with 5 color-coded toggle pill buttons per row, following Homeroom's visual pattern (`Daily.vue` `STATUSES` array of `{value, label, code, cls}`):
  - `present` → P → emerald
  - `absent` → A → red
  - `tardy` → T → amber
  - `cutting` → C → orange
  - `excused` → E → slate/blue
- Add a **"Mark All Present"** button per session card (mirrors `markAllPresent()` in `Daily.vue`), plus a live "X / Y marked present" counter next to the session's Save button.
- Add a small legend row (pill color → label) above or below each session's roster, since ALP's 5-status set is less self-evident than Homeroom's 3.
- Remarks input, session creation form, activity linking, and the attendance-summary table are untouched.
- This is implemented independently in `Show.vue` — not extracted into a shared component with Homeroom's `Daily.vue`, to avoid coupling two independently-evolving modules over a UI pattern match.

## Testing

- Backend (PHPUnit): new tests for `membersIndex()` (cross-cycle aggregation matches the dashboard's `members` count; current-SY scoping) and `unassignedIndex()` (exclusion logic matches the dashboard's `unassignedRequired` count) — assert the returned list's row count equals the existing stat's value for the same fixture data. Tests for both new PDF controller methods (200 response, `application/pdf` content-type, non-empty body).
- Frontend (manual, dev server): click both cards from the dashboard, confirm the list's row count matches the dashboard stat number, confirm search/filter narrows correctly, confirm "Download PDF" produces a correctly formatted file, confirm attendance pills save the same status values as before (regression pass against existing ALP attendance/session tests — no behavior change expected, only markup).

## Out of scope

- No changes to the Programs / Accredited cards.
- No change to ALP's session-based attendance data model or its 5-status set.
- No new permissions.
- No changes to Homeroom Attendance's own code.
