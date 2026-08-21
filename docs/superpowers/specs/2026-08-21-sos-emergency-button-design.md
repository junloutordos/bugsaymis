# SOS Emergency Button

**Date:** 2026-08-21
**Status:** Approved (design), pending implementation plan

## Problem

PSHS-CRC has no in-app way for a student or employee on campus to raise an emergency alert that reaches the right responders (Security, DRRM, Nurse, Administrator) in real time, with the triggering person's own emergency contact notified in parallel, and a defensible audit trail afterward. Today this depends entirely on someone finding a phone or a person.

Goal: a fully configurable SOS button — who gets notified, on what channel, in what order, with what timeout — reachable from Atlas (web) for all staff and students, with anti-prank safeguards, a silent/duress mode for security threats, and a path to external emergency contacts (BFP/PNP/hospital/LGU-DRRM) when internal response isn't enough.

## Non-goals

- **Not a live government emergency API integration.** No such API exists for PSHS-CRC to call. "External escalation" means an admin-configured contact list (name/org/phone/email) that gets notified via the existing SMS/mail channels — not a 911-equivalent dispatch integration.
- **Not adding staff login to AtlasGo (Flutter) in this phase.** AtlasGo currently authenticates only Students and Parents (Sanctum) — there is no staff auth flow to build on. Staff trigger SOS from Atlas web. Employees are not left without a phone-based option: Atlas web's SOS screen is built mobile-browser-first (large touch targets, workable when added to a phone's home screen) so a staff member's phone browser works even without a native app.
- **Not building a native AtlasGo (Flutter) trigger in this phase.** That's Phase B, in the separate `~/bugsaymis-mobile` repo. Students are not left without a v1 option, though: the existing Student Portal (`/student-portal`, Firebase/session-based auth against the `students` table — a distinct auth surface from staff `User` login, but still a Laravel/Inertia web page in this same repo) gets the same SOS trigger control as part of this phase, since it requires no Flutter work.
- **Not a kernel-level location tracker.** Location is a one-time browser Geolocation read at trigger time (reusing the existing `CampusPresenceService`/`GeofenceService` pattern from Online Punch/Teacher Attendance), not continuous tracking.
- **Not building new campus geofence zones.** Reuses `OnlinePunchGeofenceZone` as the single source of truth for "on campus" rather than a duplicate zone table.
- **Not routing by physical zone/building in v1.** Responder routing is by `alert_type` only (medical/security/fire_disaster/general), configurable per tier. Zone/building-aware routing (e.g., dorm emergencies → RH/Dorm Manager first) is a plausible future refinement, not required for v1 — the alert still carries location/zone data for responder context.
- **Phase B (AtlasGo student trigger UI) is out of scope for the implementation plan that follows this spec.** This spec defines the mobile API contract Phase B will consume, but Phase B's Flutter work happens in a separate repo (`~/bugsaymis-mobile`) and a separate implementation pass.

## Key architectural decisions

### 1. Two-track dispatch: synchronous broadcast + queued fan-out

A life-safety trigger cannot block on an external SMS gateway's latency or failure. On trigger:
1. **Synchronous**: create the `SosAlert` row, fire a `ShouldBroadcastNow` event on a permission-gated `sos-responders` private channel (same convention as the existing `attendance`/`biometric-feed` channels in `routes/channels.php`) — this is the fastest path and the one that matters most for getting a human's attention.
2. **Queued**: dispatch a job (`SosNotifyResponders`) to fan out SMS (via the existing `SmsGateService`) and email, and to notify the triggering user's own emergency contact.

### 2. Escalation timing via cron sweep, not per-alert delayed jobs

Follows the same durable pattern already proven by `AtlasSentinelAutoReleaseContainments`: a scheduled command (`sos:process-escalations`, every minute) checks unacknowledged/unverified alerts against each tier's `timeout_minutes` and advances them. This survives queue restarts and worker crashes, unlike delayed jobs on a queue driver not guaranteed to persist. Escalation never depends on a responder completing triage — if nobody acknowledges in time, the sweep escalates regardless.

### 3. Polymorphic triggering user

Both staff `User` (Atlas web) and `Student` (Student Portal in this phase, AtlasGo native app in Phase B — same backend, same `Student` triggerable) can trigger an alert. `SosAlert` uses `triggerable_type`/`triggerable_id`, mirroring the existing CSM `respondable_type`/`respondable_id` polymorphic convention already used in this codebase.

### 4. "On campus" verification blocks, rather than silently mislabels

If geofence verification fails (off-campus, or location permission denied), the SOS screen shows a clear message directing the person to call real emergency services (a configured phone number) instead of sending an alert that on-campus responders can't physically act on. This is a deliberate choice: a "we notified someone" false sense of safety is worse than an honest "this button can't help you right now, here's who can."

### 5. Two confirm flows: normal (with anti-prank friction) and silent/duress

- **Normal flow**: tap SOS → mandatory category picker (medical / security / fire_disaster / general) → hold-to-confirm (~3s press) → 5–10s cancellable countdown, then dispatch. The category step and hold gesture are the anti-prank friction — enough to stop an idle tap, not enough to slow down a real emergency by more than a few seconds.
- **Silent/duress flow**: a separate discreet trigger (long-press on the SOS icon) skips the category picker and countdown, defaults `alert_type = security`, sets `is_silent = true`, and shows no visible confirmation UI — the screen stays neutral. This exists specifically so someone hiding from a threat isn't exposed by their own device lighting up. Responders see `is_silent` on the alert and know not to call the triggering person's phone or make audible contact near them.

### 6. Triage can stop escalation early, never gates it

The first responder to acknowledge an alert can mark it `verified` (escalation continues on schedule) or `false_alarm` (requires a reason; halts further escalation and increments the triggering user's false-alarm count). If nobody triages before a tier's timeout, the cron sweep escalates anyway. Safety must never depend on a human completing an optional step in time.

## Data model

All new tables, additive migrations only (per this project's blue-green migration discipline).

- **`sos_alerts`** — `triggerable_type`, `triggerable_id`, `alert_type` (enum: medical, security, fire_disaster, general), `is_silent` (bool), `status` (enum: triggered, acknowledged, verified, false_alarm, escalated, resolved), `lat`, `lng`, `accuracy`, `geofence_zone_id` (nullable FK, informational only), `triggered_at`, `resolved_at`, `resolved_by` (nullable user_id), `resolution_notes`.
- **`sos_alert_events`** — append-only timeline: `sos_alert_id`, `type` (triggered/acknowledged/escalated/note/resolved/false_alarm), `actor_type`/`actor_id` (nullable — cron-driven escalations have no human actor), `payload` (json), `created_at`. This is the audit trail and the source for a future incident-report PDF (same mPDF pattern as the Hazard Report / Discipline Anecdotal Report — not built in this phase, but the event log is structured to support it without rework).
- **`sos_notification_logs`** — `sos_alert_id`, `channel` (in_app/sms/email/push), `recipient_type`/`recipient_id` (user, external contact, or guardian/ParentContact), `status`, `sent_at`. Proves who was actually notified and when.
- **`sos_escalation_tiers`** — `alert_type`, `order`, `role_id` (nullable — default responder set), explicit user list (pivot table `sos_escalation_tier_users`, overrides/extends the role default), `timeout_minutes`, `channels` (json). This is the "fully configurable" surface admins edit.
- **`sos_external_contacts`** — `name`, `org`, `phone`, `email`, `alert_types` (json — which types this contact applies to), `channel`, `active`. Admin-managed; not a live API.
- Repeat-offender count derives from `SELECT COUNT(*) FROM sos_alerts WHERE triggerable = ? AND status = 'false_alarm' AND triggered_at > now() - interval`. No separate tracking table — avoids a second source of truth that can drift from the alert history.

## Roles & permissions

- New role: **DRRM Coordinator**.
- Formalize **Security Guard** as a properly seeded role (it's referenced throughout the codebase — `KioskOperatorController`, `DeviceController`, `routes/channels.php` — but not currently in any seeder; this phase adds it to `RolesSeeder`).
- New permission strings: `sos.trigger` (implicitly all authenticated users, but kept as an explicit permission for consistency with the rest of the codebase's permission-string convention and to allow future restriction), `sos.respond` (view/acknowledge/triage alerts — granted to DRRM Coordinator, Security Guard, Administrator, Nurse by default), `sos.manage` (admin configuration of tiers/contacts/thresholds — Administrator only by default, following `IssuancePermissionSeeder`'s pattern of a dedicated seeder for a new module's permission set).
- Default routing (overridable per tier in admin settings): medical → Nurse + DRRM Coordinator; security → Security Guard + DRRM Coordinator + Administrator; fire_disaster → DRRM Coordinator + Administrator + Security Guard; general → Security Guard + Administrator.

## Notification channels

- **Responders**: real-time in-app (Echo `sos-responders` channel + a persistent banner/badge across the app, same pattern as `NotificationBell.vue`/`user.{id}` channel usage elsewhere) as the primary channel; SMS via `SmsGateService` as a configurable secondary channel per tier (responders aren't guaranteed to be staring at the dashboard).
- **Triggering user's own emergency contact**: staff → `HR\EmployeeProfile.emergency_contact_name/phone`; students → linked `ParentContact`, reusing its existing `notify_sms`/`notify_push`/FCM wiring already built for the gate-scan notification flow. This fires in parallel with responder notification, not after it.
- **External contacts**: notified only when an alert reaches the tier(s) an admin has configured to include them (e.g., fire_disaster escalating past internal timeout, or immediately for certain types if the admin configures it that way) — via SMS/email, using the existing mail queue and `SmsGateService`.

## UI/UX

- **Persistent floating SOS control** on every authenticated Atlas web page (staff) **and** every Student Portal page — not buried in a menu. Subtle by default (small red accent consistent with reserving red for genuine status, per existing UI convention), with the category-picker/hold-confirm/countdown modal on tap.
- **Responder Command Center** (`/sos` or similar, permission-gated on `sos.respond`, staff-side only) — real-time list of active alerts, status, location, silent-mode indicator, acknowledge/verify/false-alarm actions, event timeline per alert.
- **Admin SOS Settings** (`sos.manage`) — escalation tier editor (responder sets, timeouts, channels per alert type), external contacts CRUD, false-alarm threshold configuration.
- Mobile-browser-first for the trigger control specifically (large touch targets, works reasonably if added to a phone's home screen), since neither staff nor students (until Phase B) have a native trigger.

## Data flow summary

1. User taps/holds SOS on Atlas web → client captures geolocation, verifies on-campus via the existing geofence pattern.
2. If off-campus/unverifiable → show real-emergency-number message, no alert created.
3. If on-campus → normal flow (category + hold + countdown) or silent flow (long-press, no visible UI) → `POST` creates `SosAlert` + first `SosAlertEvent` (`triggered`).
4. Controller broadcasts `SosAlertTriggered` synchronously on `sos-responders`; queues `SosNotifyResponders` (SMS/email to tier-1 responders + triggering user's emergency contact).
5. A responder acknowledges (in-app) → `acknowledged` event; may mark `verified` or `false_alarm` (with reason) → corresponding event, notification log updated.
6. `sos:process-escalations` cron sweep runs every minute: any alert past its current tier's `timeout_minutes` without `resolved`/`false_alarm` status advances to the next tier (notifying that tier's responders/external contacts per configured channels) → `escalated` event.
7. A responder with appropriate permission resolves the alert (`resolved` event, `resolution_notes`) — this is the only way an alert leaves `escalated`/`verified` state short of `false_alarm`.
8. False-alarm count for the triggering user is derivable at any time from `sos_alerts` history; crossing the configured threshold notifies admins for review (does not block future triggering).

## Testing

Standard TDD per this project's convention (PHPUnit, dev Docker container). Key scenarios to cover explicitly given the life-safety nature of this feature:
- Trigger creates alert + broadcasts + queues notifications even if the SMS gateway is unreachable (queued job failure must not roll back the alert or block the broadcast).
- Off-campus/no-location trigger is blocked with the correct message, no alert row created.
- Silent trigger produces `is_silent = true`, skips category/countdown, and the responder view surfaces the silent flag distinctly.
- Escalation sweep advances an unacknowledged alert past timeout regardless of whether any human ever opens the app.
- `false_alarm` marking halts further escalation and is reflected in the triggering user's false-alarm count.
- Verified alert continues escalating on schedule even after triage.
- Permission gates: `sos.respond` required for the Command Center and triage actions; `sos.manage` required for settings; `sos.trigger` (effectively all authenticated users) for the button itself.
- Polymorphic `triggerable` resolves correctly for both `User` (staff, Atlas web) and `Student` (Student Portal) in Phase A — AtlasGo's native trigger in Phase B reuses the same `Student` triggerable and the same API, so the column/relation shape must not need migration when Phase B lands.

## Rollout

Ships as Phase A: backend + Atlas web trigger (staff) + Student Portal trigger (students) + Command Center + admin settings + all notification channels + escalation engine. This covers both trigger populations end-to-end on web before any Flutter work happens. Phase B (native AtlasGo trigger, replacing/complementing the Student Portal's browser-based trigger with a proper in-pocket app experience) is scoped and planned separately once Phase A's API contract is stable in production.
