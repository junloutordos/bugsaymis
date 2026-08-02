# Dyna Expansion — Comprehensive Analytics, Google Sign-In, Icon (Design Spec)

**Date:** 2026-08-02
**Status:** Approved for planning
**Owner:** Junlou Tordos
**Extends:** `2026-08-02-dyna-ai-assistant-design.md`

## Summary

Phase 2 of Dyna: expand the backend tool registry from 2 tools to 20 (comprehensive coverage
of HR/payroll/IPCR/requests/CSM/academics/recruitment/finance/operations, plus CID-specific
faculty and student modules), add Google Sign-In to Dyna.app alongside the existing
email/password flow, and ship an interim app icon using the Atlas mark.

## Backend — 18 new tools (20 total)

### Group A: Executive Dashboard adapters (9 tools)

Thin adapters over the existing, already-cached, already-scoped `ExecutiveDashboardService`.
Each calls `ExecutiveDashboardService::build($divisionId)` (reusing its 1-hour cache and
`rescue()` error isolation) and returns one section. `$divisionId` is resolved with the same
lens `ExecutiveDashboardController::resolveLens()` already uses: a Division Chief is locked to
their own division; OCD/Administrator get campus-wide, optionally narrowed.

| Tool | Section | Division-scoped |
|---|---|---|
| `get_performance_stats` | IPCR funnel, compliance by division, rating distribution | Yes |
| `get_requests_stats` | IT/Facility/Vehicle/Service/Work/Travel requests | Yes |
| `get_satisfaction_stats` | CSM survey (SQD dimensions, adjectival rating, by-office) | No |
| `get_academics_stats` | Enrollment (basic), faculty load, class records, gate attendance (today) | No |
| `get_recruitment_stats` | Open vacancies, pipeline, pending placements | No |
| `get_finance_stats` | Latest payroll run, net-pay trend, PR/DV status | No |
| `get_operations_stats` | Document routings, issuances, error reports, committee tasks | Yes |
| `get_attention_items` | Flagged items needing action | Yes |
| `get_division_scorecard` | Cross-division comparison rollup | No (campus-lens only) |

Existing `get_headcount` and `get_leave_trends` are unchanged — kept for their arbitrary
date-range flexibility that the fixed dashboard sections don't offer.

### Group B: Faculty & student modules (9 tools)

Grounded in real schema (verified via direct model/migration reads, not assumed):

| Tool | Source (table.column) | Metric |
|---|---|---|
| `get_faculty_load_distribution` | `faculty_loads.load_status` (underload/full_load/overload) | Distribution by status, by school year/term; TBA/unassigned count |
| `get_class_record_compliance` | `class_records.status` (draft/submitted/checked) | Submission/checking compliance rate by subject/grade level |
| `get_teacher_attendance_stats` | `teacher_tap_logs.status` (on_time/late/no_match) | On-time vs late rate trend; no-match count |
| `get_enrollment_status_breakdown` | `student_enrollments.status` (enrolled/dropped/transferred_out/on_leave/completed) | Drop/transfer/on-leave counts and rates |
| `get_gate_attendance_trend` | `student_attendance_logs` | Multi-day/weekly scan volume, late-arrival rate |
| `get_library_stats` | `borrowings.status` | Current borrowed count, overdue count, active distinct-borrower count |
| `get_competitions_stats` | `competitions.level` / `competition_participants` | Competition count by level (campus→international), award trend, by school year |
| `get_homeroom_attendance_summary` | `homeroom_monthly_report_lines` (per-student) | **Aggregate only**: avg cutting incidents, perfect-attendance count, excused-vs-unexcused ratio |
| `get_discipline_case_stats` | `discipline_cases.status` / `.threat_level` | **Aggregate only**: case counts by status/threat-level/offense |

**PII discipline (approved):**
- `get_homeroom_attendance_summary` and `get_discipline_case_stats` read from genuinely
  per-student tables. They must only ever return grouped counts/averages — never a
  `student_id`, name, or case narrative — and must suppress any breakdown cell narrow enough
  to de-anonymize (minimum group size, e.g. omit/collapse any count below 5).

### Access control — two tiers (approved)

Research found `DivisionChief` has real, granted web-app access to exactly one of these nine
modules today (`discipline.view` — a deliberate grant, not an oversight); everything else is
CID-Chief/Administrator/specialist-only, and two modules (Library, Class Record monitoring)
have no real permission gate in the web app at all today (open to any authenticated user).
Strictly mirroring each module's exact web permission would leave most Division Chiefs unable
to use most of the new tools — cutting against "comprehensive." Resolution:

- **Group A + the 7 non-sensitive Group B tools** (`get_faculty_load_distribution`,
  `get_class_record_compliance`, `get_teacher_attendance_stats`,
  `get_enrollment_status_breakdown`, `get_gate_attendance_trend`, `get_library_stats`,
  `get_competitions_stats`): gated by `atlas.dyna.access` only. These are pure institutional
  aggregates — no individual PII — so Dyna's own access gate is sufficient.
- **`get_discipline_case_stats`**: gated by `atlas.dyna.access` **and** `discipline.view`.
- **`get_homeroom_attendance_summary`**: gated by `atlas.dyna.access` **and**
  `homeroom-attendance.admin`.

### `atlas.dyna.access` grant expansion (approved)

Add the `CID Chief` role to `atlas.dyna.access` (currently Administrator, OCD, DivisionChief
only). Rationale: most of the new CID-specific tools map to permissions only `CID Chief` holds
in the web app; without this, the person who actually runs CID could never use Dyna to ask
about their own modules — only Administrator/OCD could ask on their behalf. This also means
`CID Chief` becomes the only realistic non-Administrator user of `get_homeroom_attendance_summary`
in practice (Division Chiefs lack `homeroom-attendance.admin`).

## macOS — Google Sign-In

- **Client:** Google's official `GoogleSignIn-iOS` SPM package — confirmed to support native
  macOS (opens the system default browser for consent, returns an ID token to the app). Same
  mechanism the mobile app already uses, not a new integration pattern for this codebase.
- **UI:** `LoginView` gains a "Sign in with Google" button, shown first/primary (matching the
  existing Atlas web login's layout — Google button + email/password form below it), per your
  explicit choice to keep both options.
- **Backend:** new `POST /api/dyna/login/google` on `DynaAuthController`, verifying the ID
  token via `Google\Client::verifyIdToken()` — the same verification code
  `StudentAttendance\Api\GoogleAuthController` already uses. Differs from the mobile flow in
  one deliberate way: **no auto-creation**. Look up an existing `User` by the verified email;
  if none exists, return a clear "No Atlas Account found for this Google account" error rather
  than self-registering — Dyna is an executive tool, not a public signup surface. If found but
  lacking `atlas.dyna.access`, same 403 the password flow already returns.
- **One-time setup (not automatable — requires your Apple/Google account access):** in the
  existing Google Cloud project, add an iOS-type OAuth client bound to bundle ID
  `ph.edu.pshs.crc.atlas.dyna`, pointed at the same server client ID already in
  `GOOGLE_MOBILE_CLIENT_ID`.

## macOS — Interim app icon

Source: `~/Downloads/Atlas_Mark_Only.png` (918×1178, glyph-only, no wordmark — better
resolution than the repo's `public/images/atlas-mark.png`). Treatment: replicate AtlasGo's own
shipped icon exactly (glyph centered on a padded square canvas, confirmed via its actual
960×960 `atlasgo_mark.png`) — generate the full macOS `AppIcon.appiconset` (16px through
1024px, @1x/@2x) from it. Explicitly interim, per your "for the meantime" — a real icon design
pass is a separate future task.

## Open items for the implementation plan

- Exact minimum-group-size threshold for discipline/homeroom suppression (proposing 5,
  confirm during implementation).
- Whether the 9 new Group B tools need their own migration (permission checks are code-level,
  not new `permissions` rows) or just service-level `hasPermission()` calls mirroring the
  existing tools' pattern.
- Task grouping for the implementation plan — 18 new tools is a lot of individual TDD tasks;
  will batch related tools (e.g. all 9 Group A adapters as one task-family) rather than one
  task per tool, to keep the plan reviewable.
