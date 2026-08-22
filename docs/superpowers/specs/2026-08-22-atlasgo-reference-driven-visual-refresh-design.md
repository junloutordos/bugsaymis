# AtlasGo Reference-Driven Visual Refresh — Radial Rings, Trend Charts, Softer Palette

**Date:** 2026-08-22
**Status:** Approved (design), pending implementation plan

## Problem

The user shared a Pinterest design reference (an e-SIM data-management app UI) as the target aesthetic for AtlasGo going forward: a contained gradient hero card (not full-bleed), a large radial progress ring as the primary stat metaphor, a soft/muted color palette (color used sparingly in icons and rings rather than saturated color-blocking), and a real trend chart with a tap tooltip.

This supersedes the "bold & vibrant" direction chosen for `2026-08-22-atlasgo-premium-redesign-phase-b2-design.md` (Phase 1a), which is already partly shipped: `HeroHeader` (full-bleed 3-stop brand gradient) is live on Home and Student Dashboard, and per-feature saturated gradients (`AppGradients.portal/attendance/grades`) were built in that phase but never yet consumed anywhere.

## Non-goals

- **Not a full re-litigation of Phase 1a.** The SOS live-status screen, its radar-pulse animation, and the confirm-gated end-SOS flow are untouched — they're a safety-critical flow with their own (already-shipped, already-verified-on-device) visual language, not part of this reference.
- **Not extending to Auth screens or the broader Portal-dashboard visual sweep.** Both were already deferred from Phase 1a and stay deferred — this refresh touches `HeroHeader`, Grades, Attendance, and the Portal-todo/clearance section specifically, not a full-app sweep.
- **Not dark mode.**
- **Not a new "school days" calendar system.** The Attendance rate's denominator reuses the existing `SchoolCalendarService::isSchoolDay()` — the same service the Homeroom Attendance module's monthly report already relies on for its own school-days count. This spec does not touch that service, only calls it.
- **Not coupling to the Homeroom Attendance module's `homeroom_attendance_dates`.** That table is populated reactively only when a homeroom teacher actually takes attendance for their section — using it as a mobile API's "school days" source would silently break the student-facing stat whenever a teacher hasn't submitted attendance yet. `SchoolCalendarService::isSchoolDay()` (weekday + holiday/suspension calendar, no teacher-compliance dependency) is the correct, already-existing building block instead.
- **Not a school-year-long chart.** The Attendance trend chart covers the last 8 weeks — long enough to show a real trend, short enough to stay legible on a phone screen and cheap to compute.

## Scope

1. **Two new reusable Flutter widgets**: `RadialProgressRing` (hand-rolled `CustomPainter`, no new dependency) and `TrendChart` (via the new `fl_chart` dependency — the first new mobile dependency since this redesign effort started).
2. **Palette redefinition**: a new contained-hero gradient (`AppGradients.hero`, navy→mint) replacing `AppGradients.authDecoration` as what `HeroHeader` uses; the three Phase-1a per-feature gradients (`portal`/`attendance`/`grades`) redefined to softer single-accent tones (safe to redefine outright since nothing consumes them yet); new soft-negative status-pill tokens (`AppColors.dangerBg`/`dangerText`) for "Expired"/overdue-style states, which don't exist in the token set today.
3. **`HeroHeader` restyled** from full-bleed edge-to-edge to a contained, fully-rounded card with margin — structure (greeting/name/date, action button, trailing-stat slot) unchanged.
4. **Three target-screen treatments**:
   - **Grades**: Student Dashboard's grade-summary card gets a `RadialProgressRing` for GWA (replacing the current text badge); the full `StudentGradesScreen` gets a `TrendChart` plotting GWA-per-quarter, computed client-side from the already-fetched `q1`–`q4` fields on each `GradeEntry` — no backend change.
   - **Portal-todo / clearance**: the flat "X of Y forms completed" / "Y of Z cleared" text becomes a `RadialProgressRing`, using the already-fetched `PortalDashboard.total/totalDone` and `ClearanceSummary.done/total` — no backend change.
   - **Attendance**: a new `RadialProgressRing` for this-month attendance rate plus a `TrendChart` of weekly attendance rate over the last 8 weeks, added to `StudentAttendanceScreen` — this **does** need one new backend endpoint (see below), since the mobile API currently only exposes single-day scan logs.

## Key architectural decisions

### 1. `RadialProgressRing` — hand-rolled, no new dependency

A `CustomPainter` draws a background track circle plus a foreground arc stroked with a `SweepGradient` (green→yellow→blue, matching the reference), sized `0..2π * (value/max)`. Center content is a slot (`Widget`) so callers render whatever text/number fits (GWA value, "3 of 10", "82%"). This is the same complexity class as the SOS radar-pulse `CustomPainter` already shipped in Phase 1a — no new library needed for a ring.

### 2. `TrendChart` — via `fl_chart` (new dependency)

A smooth line chart with a gradient fill under the curve and a tap-activated tooltip, styled through the app's existing color/type tokens rather than `fl_chart`'s defaults (custom `LineChartBarData` gradient, custom `titlesData` label builders using `AppTextStyles`). `fl_chart` is a widely-used, actively-maintained Flutter charting package — reproducing its bezier-curve interpolation, gradient-fill compositing, and touch-tooltip handling by hand would be a substantially larger and more fragile undertaking than the ring, and was the explicit tradeoff the user chose when approving this dependency.

### 3. `HeroHeader`: full-bleed → contained card

Currently `HeroHeader` is a `Container` with `SafeArea` that bleeds the gradient up under the status bar, edge-to-edge, with only the *bottom* corners rounded. It becomes a `Container` with horizontal + top margin, all four corners rounded, sitting as the first item in the scroll view like any other card — matching the reference's hero-as-card treatment. `AppGradients.hero` (navy `#1A3557` → mint/teal, 2-stop diagonal) replaces `AppGradients.authDecoration` as its background; the existing content structure (greeting/name/subtitle, `_HeroActionButton`, optional `trailing` slot) is unchanged, so Home's and Student Dashboard's call sites need no structural changes — only the container styling and gradient token change underneath them.

### 4. New attendance-summary endpoint, reusing `SchoolCalendarService`

New mobile endpoint `GET /api/mobile/student/attendance/summary` (Sanctum-guarded, same student-scoping pattern as every other mobile endpoint):

- **This-month numerator**: distinct calendar days with at least one `type = 'in'` `StudentAttendanceLog` row in the current month, for the authenticated student.
- **This-month denominator**: count of days from the 1st of the current month through today where `SchoolCalendarService::isSchoolDay($date, $student->grade_level)` is true.
- **Weekly trend (last 8 weeks)**: for each of the last 8 Mon–Sun weeks, the same numerator/denominator pair, giving a per-week rate for the `TrendChart`.

No new tables, no new columns — `StudentAttendanceLog` already holds everything needed for the numerator, and `SchoolCalendarService`/`SchoolCalendarEvent` (existing, used elsewhere) supply the denominator without any new coupling.

### 5. Palette tokens

- `AppGradients.hero`: `LinearGradient(colors: [Color(0xFF1A3557), Color(0xFF34D399)], begin: topLeft, end: bottomRight)` — navy → emerald/mint, reusing the existing brand navy as the start color for continuity rather than inventing an unrelated one.
- `AppGradients.portal/attendance/grades` (built in Phase 1a, unused anywhere yet): redefined from saturated 2-stop gradients to single soft-accent tones matching the reference's icon-chip colors (green for data/attendance-style stats, blue for informational, amber/soft-orange for grades) — a redefinition, not a migration, since no screen consumes them yet.
- `AppColors.dangerBg` / `AppColors.dangerText`: new soft-coral pair, parallel to the existing `successBg`/`successText` and `warningBg`/`warningText` pattern, for "Expired"/overdue-style status pills that don't have a token today.

## Data model

One new additive route (`GET /api/mobile/student/attendance/summary`); no new tables, no new columns, no migration.

## Roles & permissions

No change — the new endpoint uses the identical `auth:sanctum` + student-scoping pattern every other `/student/portal/*`-style mobile endpoint already uses (resolves the student from the authenticated request, not a route parameter, so there's no cross-student access surface to gate).

## UI/UX

- **HeroHeader**: contained rounded card, `AppGradients.hero` background, existing content structure.
- **Student Dashboard grade card**: `RadialProgressRing` showing GWA in place of the current text badge.
- **Grades screen**: new `TrendChart` section plotting GWA per quarter (from `q1`–`q4` already in `GradeEntry`).
- **Portal-todo / clearance**: `RadialProgressRing` replacing flat completion text.
- **Attendance screen**: new header section above the existing day-picker/timeline with a `RadialProgressRing` (this-month rate) and a `TrendChart` (8-week weekly rate), fed by the new summary endpoint.

## Testing

- **Laravel (PHPUnit)**: new summary endpoint — correct numerator (distinct present days) and denominator (school days via `SchoolCalendarService`) for a fixture with known scans and a known holiday in range; weekly-buckets shape is correct; auth/scoping matches existing mobile-endpoint conventions.
- **Flutter (widget tests)**: `RadialProgressRing` renders the correct sweep angle for a given value/max and renders arbitrary center content; `TrendChart` renders the correct number of points from fixture data and the tooltip appears on tap; `HeroHeader` (already tested in Phase 1a) gets its existing tests re-verified against the new contained-card styling, not rewritten; Grades/Attendance/Portal-todo screen tests updated to assert the new ring/chart widgets appear with correctly-derived values instead of the old text badges.

## Rollout

Single implementation plan (Flutter primitives + palette tokens + `HeroHeader` restyle + three target-screen treatments, plus the one small additive backend endpoint for Attendance) — smaller in scope than Phase 1a, no phased split needed. Same on-device Simulator verification pattern as Phase 1a before considering it done.
