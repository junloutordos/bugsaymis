# AtlasGo Premium UI/UX Redesign (Phase B2) — Full-App Visual System, Hero Card, SOS Live Status

**Date:** 2026-08-22
**Status:** Phase 1 SHIPPED (see [[project_atlasgo_premium_redesign]] — design tokens, Hero Card on Home/Student Dashboard, SOS live status, Auth screens, `RadialProgressRing`/`TrendChart`). Phase 2 + Phase 3 scope revised below and approved 2026-08-22 (later session) — combined into one implementation pass per user decision, rather than sequenced. Pending implementation plan for the combined Phase 2+3 scope.

## Problem

The 2026-08-22 Foundation Redesign (`2026-08-22-atlasgo-foundation-redesign-design.md`) established a design-token foundation (`AppElevation`/`AppSpacing`/`AppMotion` in `theme.dart`) but deliberately applied it to only four surfaces: `AppShell`/nav, Home, Profile, and the new SOS trigger flow — explicitly deferring "Phase B2: full-app rollout" as a separate spec. The other ~30 screens across Auth, Grades, Schedule, Attendance, Student dashboard, Notifications, and every Portal sub-form still use the pre-redesign ad hoc styling.

Separately, two gaps surfaced once the SOS trigger flow existed:

1. **No feedback loop after a real SOS trigger.** Today a student sees a static "Sent" confirmation and then nothing — no visibility into whether anyone has acknowledged the alert, and no way to tell responders they're safe short of a phone call. For a life-safety feature this is a real gap, not just a polish item.
2. **The greeting header (`AppHeader` on Home/Student Dashboard) reads flat** relative to the "premium" bar the rest of this pass is aiming for — a fixed white bar with plain text, not a designed moment.

## Non-goals

- **Not a new brand identity.** Stays within the existing Atlas palette (navy `#1A3557` / blue-cyan accent gradient) and Plus Jakarta Sans — this pass adds a broader, more saturated *application* of the existing brand (per-area gradients, bolder color-blocking, illustration-forward empty states), not a new logo/colorway/typeface.
- **Not dark mode.** Explicitly deferred to its own future phase (doubles the token/QA surface).
- **Not Lottie/hero-transition-level animation.** Motion stays "tasteful": native `Animated*` widgets, page transitions, staggered list entrances, press feedback, polished loading states. No new animation dependency.
- **Not a WebSocket/Pusher client for SOS live status.** The Flutter app has no realtime socket dependency today (only FCM push). Adding one means building channel-auth + reconnect handling from scratch for a safety-critical screen. This phase uses **short-interval polling** (~4s) against a new status endpoint instead. Revisit sockets as a later fast-follow if polling proves insufficient in practice.
- **Not changing the SOS escalation engine, Command Center, or existing `sos_alerts` status lifecycle.** The new student-facing endpoints are thin, permission-scoped reads/writes on top of the existing `SosAlertService` — no new states, no schema change to the state machine itself.
- **Not changing silent/duress-alert behavior.** Silent alerts continue to show **zero visible UI** — the new "Help is on the way" screen only appears for non-silent triggers. This is a safety property of the existing design, not something this pass is allowed to erode.
- **Not turning every `AppHeader` usage into a Hero Card.** Only the two screens where `AppHeader` is used as an actual personalized greeting (Home, Student Dashboard) become Hero Cards. The three screens that repurpose `AppHeader` as a plain page-title bar (Services, Notification Preferences, Children) keep a header — restyled for the bold/vibrant palette in Phase 2 of this rollout, not converted to a hero pattern.
- **Not shipping a store/direct-download build.** Matches the standing pattern (Foundation Redesign, prior mobile work): Flutter changes land on `bugsaymis-mobile` `main`, verified via Simulator click-through; bumping the distributed APK/IPA is a separate, explicit action the user triggers later.

## Scope

Three phases. Phase 1 shipped as its own set of sessions (see Status above — the work was actually done incrementally as "Phase 1a", "reference-driven visual refresh", and "auth + hero refresh", together covering everything described as Phase 1 here). Phases 2 and 3 were re-scoped and approved together in a later 2026-08-22 session, per the user's explicit choice to build them as one combined pass rather than sequenced:

1. **Phase 1 — System + hero screens. SHIPPED.** Token extensions (bold/vibrant gradients, color-blocking), new animation primitives (`Pressable`, `StaggeredList` — built but not yet wired up outside Phase 1's own screens), Hero Card on Home + Student Dashboard, `RadialProgressRing`/`TrendChart` data-viz, and the SOS live-status + end-SOS feature (backend + Flutter). Applied to: Auth (Login/Register), Home, Student Dashboard, Student Attendance, Student Grades, Portal dashboard, SOS.
2. **Phase 2 — Subject-dashboard + settings screens.** Same tokens/primitives applied to: parent-side `Attendance`, parent-side `Grades`, `Schedule` + `Student Schedule`, `Children`, `Profile` (all get `HeroHeader` where there's a real subject+stat — `Schedule`/`Children` carry identity/count only, no invented chart), plus `Notification Preferences` and `Student ID` (settings-tier — header/card/spacing/motion tokens only, no invented visualization; `Student ID` was missed by the original Phase 2/3 split and is added here).
3. **Phase 3 — Portal action/form + auth-utility screens.** `Services` hub, `Clearance`, `Leave Passes`, `RH Application`, `Forms Overview`, `Lost & Found`, `Profile Section Form`, `Medical Section Form` (header/card motion + `Pressable` on rows + `StaggeredList` on lists; `HeroHeader` only where a real subject/progress stat exists, e.g. Clearance). Also: `Verify Email`, `Student Link`, `Link Child` — missed by the original spec, added here; these match the Login/Register auth-arc pattern (`AppGradients.hero` + `kFormShadow`), not `HeroHeader`, since they're pre-nav-shell screens.

Also in this pass: the two small Phase-1 loose ends found while planning Phase 2/3 — `register_screen.dart`'s top arc still uses the older `AppGradients.authDecoration` instead of `AppGradients.hero` (so Register visually breaks from Login), and Login has no footer. Since Verify Email/Student Link/Link Child are being brought onto the same auth-arc pattern in this same pass, all five auth-family screens (Login, Register, Verify Email, Student Link, Link Child) get `AppGradients.hero` uniformly, and Login gets a small centered footer caption: "AtlasGo is the Mobile app of Philippine Science High School – Caraga Region Campus in Butuan City" (`AppTextStyles.caption`).

This document covers the full token/primitive system (used by all three phases) and the Phase 1 feature work in detail. Phase 2 and Phase 3 are mechanical reapplications of the same system to more screens, built together as one implementation pass — each screen still verified individually via Simulator click-through, batched by archetype (4 checkpoints: subject-dashboards, settings, portal-forms, auth-utility) rather than one signoff per screen.

## Key architectural decisions

### 1. Design tokens: bold & vibrant, additive to the existing `theme.dart` system

Extend (not replace) the Foundation Redesign's token classes:

- **`AppGradients` gains per-feature-area gradients** — small, fixed palette of saturated 2-stop gradients (e.g. violet/indigo for Portal, teal/emerald for Attendance, amber/orange for Grades), used on section headers, stat-card backgrounds, and icon chips. The existing `AppGradients.button` (blue/cyan) stays as the one universal action-gradient; feature gradients are for *identity*, not buttons.
- **Bolder color-blocking on `AppCard`-based content**: colored icon chips (icon on a tinted-background circle) replace plain gray icons; stat cards get a saturated tinted background instead of all-white; `AppColors.accent`/`accentMid` get used as fills in more places, not just borders/text.
- **Illustration-forward empty/error states**: flat SVG illustrations (new `flutter_svg` dependency, hand-built assets, no stock-art package) replace the current icon+text empty states in `EmptyState`/`_EmptyState` widgets.
- **Typography**: no new sizes/weights — lean harder on the existing `screenTitle` (`w800`, `-0.3` letter-spacing) for section headers too, for more visual confidence.

### 2. New animation primitives in `lib/src/shared/widgets/`

- **`AppPageTransition`** — shared `go_router` `pageBuilder` transition (slide+fade, using `AppMotion.base`/`standard`) replacing the default platform transition, wired once at the router level so it applies app-wide with no per-route change.
- **`StaggeredList`** — wraps a list of children so each fades/slides in with a small per-item delay (used on dashboard lists, grades lists, notification lists). Built on native `AnimatedList`/`TweenSequence`, no new dependency.
- **`Pressable`** — extracts `AppCard`'s existing press-scale (`AnimatedScale` + `_pressed` state) into a standalone wrapper, so chips, list rows, and nav items get the same tactile feedback without duplicating the state-management boilerplate `AppCard` already has.
- Polished pull-to-refresh (custom `RefreshIndicator` styling per the new palette) and a slightly richer `ShimmerCard`/`ShimmerList` shape per content type (list row vs. stat-grid vs. form) — extends the existing `shimmer_card.dart`, doesn't replace it.

### 3. Hero Card replaces the greeting `AppHeader` on Home and Student Dashboard only

Both screens currently render a fixed white `AppHeader` (greeting/name/subtitle + actions) pinned above an `Expanded` scrollable body. That header goes away; the `Scaffold` body becomes a single scrollable column, and the greeting becomes its **first scrolling item**: a tall rounded card with a brand gradient background (bleeding up under the status bar, no separate app bar), bold white greeting/name/date text, the existing profile-avatar action, and one embedded glanceable stat per screen — the student's `StatusBadge` (attendance) on Student Dashboard, linked-student count on parent Home — so it reads as a dashboard hero, not a repainted label. `RefreshIndicator` wraps the whole scroll view so pull-to-refresh visibly pulls the hero along with the rest of the content.

The three screens using `AppHeader` as a plain title bar (Services, Notification Preferences, Children) are unaffected by this decision — they keep `AppHeader`, restyled in Phase 2.

### 4. SOS live status: new student-scoped read/write endpoints, no changes to the state machine

Two new mobile endpoints under the existing `/api/mobile/student/portal/sos/*` group (`StudentSosController`), alongside today's `config`/`trigger`:

- **`GET /api/mobile/student/portal/sos/{alert}`** — returns the same shape as the Command Center's `serialize()` (status, timestamps, event timeline), scoped so the request only succeeds when `$alert->triggerable_type/id` matches the authenticated student (403 otherwise — this is the actual security boundary, not a client-side assumption).
- **`POST /api/mobile/student/portal/sos/{alert}/end`** — new `SosAlertService::endByReporter(SosAlert $alert, Model $reporter)` method: validates the reporter matches the alert's `triggerable`, rejects if already `resolved`/`false_alarm`, then sets `status = resolved`, `resolved_at = now()`, `resolved_by = null` (the FK targets `users.id` and a `Student` may not have a `users` row — attribution instead lives in the event), `resolution_notes = 'Ended by reporting student.'`, writes a `SosAlertEvent` (`type: resolved`, `actor_type` = the student's morph class, `payload: ['ended_by' => 'reporter']`), and fires the existing `SosAlertUpdated` broadcast — reusing the `resolved` status means the Command Center dashboard needs no new status handling, while the event timeline still shows a student-initiated close distinctly from a staff one.

No new `sos_alerts` columns, no new statuses, no migration.

**Flutter side:** after a successful non-silent trigger, the trigger sheet is replaced by a full-screen "Help is on the way" state:
- A calm pulsing radar/rings animation (`AnimationController` + `CustomPainter`, no new dependency) around a status icon.
- A live stepper — Triggered → Acknowledged → Verified/Escalated → Resolved — driven by polling the new GET endpoint every ~4s while the screen is mounted (paused on `dispose`).
- An **"End SOS — I'm safe"** action behind a confirm dialog (not the trigger's full hold-to-confirm, but a real confirmation — ending a live emergency broadcast is not a one-tap action) that calls the new end endpoint.
- Once status reaches `resolved`/`false_alarm` (by either path — student-ended or command-center-resolved), the screen transitions to a calm resolved end state and polling stops.
- **Silent/duress triggers skip this screen entirely** — they keep today's zero-visible-UI behavior (haptic pulse only). This branch is enforced in the same place the trigger flow already branches on `is_silent`.

## Data model

No new tables, no new columns, no destructive changes. Two new routes (additive) on the existing mobile SOS route group.

## Roles & permissions

- New SOS status/end endpoints: no new permission string — gated by `auth:sanctum` + an explicit `triggerable` ownership check in the controller (a student can only read/end their own alert), the same security model the rest of the mobile SOS surface already uses.
- Everything else in this phase is presentation-layer only; no permission changes.

## UI/UX

### Hero Card (Home, Student Dashboard)
- Replaces the pinned `AppHeader` with a scrolling gradient hero card: greeting, name, date, profile avatar action, one embedded stat.
- `RefreshIndicator` wraps the full scroll view.

### SOS live status
- Non-silent trigger success → full-screen "Help is on the way": pulsing radar animation, live status stepper (polled every ~4s), "End SOS — I'm safe" behind a confirm dialog.
- Resolved (either path) → calm resolved end state, polling stops.
- Silent/duress trigger → unchanged: no visible UI, haptic pulse only.

### Design system (all three phases)
- Per-feature gradients, bolder color-blocked cards/chips, SVG illustration empty states.
- `AppPageTransition` (app-wide), `StaggeredList`, `Pressable`, refreshed pull-to-refresh + shimmer shapes.
- **Phase 1 (SHIPPED):** Auth (Login/Register), Home, Student Dashboard, Student Attendance, Student Grades, Portal dashboard, SOS.
- **Phase 2:** parent-side Attendance, parent-side Grades, Schedule, Student Schedule, Children, Profile, Notification Preferences, Student ID.
- **Phase 3:** Services, Clearance, Leave Passes, RH Application, Forms Overview, Lost & Found, Profile Section Form, Medical Section Form, Verify Email, Student Link, Link Child. Plus the Phase-1 gradient/footer cleanup described in Scope (Register → `AppGradients.hero`, Login footer).

## Data flow summary

**SOS live status:** Non-silent trigger dispatches (unchanged from Foundation Redesign) → Flutter navigates to the new status screen → polls `GET .../sos/{alert}` every ~4s → renders the stepper from `status` + `events` → student may call `POST .../sos/{alert}/end` (confirm-gated) → next poll (or the end-call's own response) reflects `resolved` → screen settles to the resolved end state.

## Testing

- **Laravel (PHPUnit):** new GET endpoint — 200 for the owning student, 403/404 for a different student's alert, correct status/event-timeline shape. New end endpoint — succeeds only for the owning student on an active alert, 409/422-equivalent on an already-terminal alert, writes the `resolved` status + event with `actor_type` = student, fires `SosAlertUpdated`.
- **Flutter (widget tests):** status screen renders the correct stepper state per polled status; end-SOS confirm dialog gates the call; silent-trigger path never navigates to the status screen; polling stops once status is terminal.
- **Simulator click-through** (per phase, matching the Foundation Redesign pattern): trigger a real non-silent alert, verify the live screen updates as status changes (drive the transition via a command-center action or direct DB update in dev), confirm end-SOS works, confirm the silent path still shows zero UI.

## Rollout

Phase 1 (system + hero screens + SOS live status) shipped first, Simulator-verified. Phase 2 and Phase 3 are built together as one implementation pass (user's explicit choice over further sequencing), still verified in 4 archetype-batched Simulator checkpoints rather than per-screen, before merging. Distribution (APK/IPA rebuild) is explicitly out of scope — same standing pattern as every prior phase; Flutter changes land on `bugsaymis-mobile` `main` only.
