# Announcements Full Wiring + Emergency Alert Broadcast (Command Center)

**Date:** 2026-08-22
**Status:** Approved (design), pending implementation plan

## Problem

Two related gaps, bundled because they share the same delivery infrastructure:

1. **Announcements is half-wired.** The module (shipped 2026-07-10) publishes to a bell + a dashboard card, but nothing prompts the user to actually see it, nothing tracks whether they have, and it only reaches employees — AtlasGo (Student/Parent) users never see a campus announcement at all.
2. **Command Center (SOS module) has no way to broadcast a public alert.** It routes an individual SOS report to responders, but there's no way for DRRM/Security staff to push a campus-wide emergency notice — either escalating a reported emergency into a public alert, or issuing one proactively (lockdown, weather closure) with no triggering SOS report at all.

Goal: an announcement, once published, interrupts the user with a modal (web + AtlasGo) that they must explicitly acknowledge ("Mark as Read") before it stops reappearing, and shows on both dashboards. Command Center gains a "broadcast emergency alert" capability, reachable either from an existing SOS alert (escalate) or standalone (create), delivered with the urgency the content demands.

## Non-goals

- **Not building granular geofence/building-aware alert targeting.** Audience is `all` or a role group (`employees`/`students`/`parents`), matching how SOS Phase A already routes (by `alert_type`, not zone). Per-zone targeting is a plausible future refinement, not required here.
- **Not adding a new "acknowledge you are safe" headcount feature to emergency alerts.** That's a distinct product (mass-notification accountability/roll-call) beyond what was asked — an emergency alert here needs the same "seen it" acknowledgment as an announcement, not a safety-status check-in.
- **Not adding staff/employee accounts to AtlasGo.** AtlasGo remains Student + Parent only. Employees get announcements/alerts via Atlas web exactly as they do today (bell + this new modal); nothing about the employee delivery path changes except gaining the modal.
- **Not replacing the existing `announcement_user` pivot's role in audience *targeting*.** It still exists for the `specific` (named employees) case; this spec adds *read-tracking* as a separate concern, not folded into that pivot.
- **Not building a second live-broadcast transport for AtlasGo.** Mobile real-time delivery is FCM push — there is no persistent websocket connection to maintain on a phone, and adding one (e.g. `pusher_channels_flutter`) for this alone is unjustified complexity/battery cost. Web keeps using the existing Echo/Soketi wiring already proven in [[project_realtime_broadcasting]].
- **Not an incident-report PDF for emergency alerts.** Same call SOS Phase A made for its own event log — structured to support one later, not built now.

## Key architectural decisions

### 1. AtlasGo has no `users` table row for Students — delivery must be polymorphic across three recipient types, not just an audience enum

Confirmed by reading `StudentAttendance\Api\AuthController::login()` directly: AtlasGo issues Sanctum tokens straight against `Student` (via `student_credentials`) or `ParentContact`, and `student_credentials`' own migration comment says explicitly *"students must never get a row [in the `users` table]"*. The existing `Announcement`/`NotificationService` stack is built entirely around `User`. Extending "audience" to reach students/parents is therefore not a matter of adding enum values to an existing targeting mechanism — it requires resolving and notifying three genuinely different Eloquent models (`User`, `Student`, `ParentContact`), each with its own notification channel.

### 2. One shared, polymorphic delivery layer for both features, not two parallel builds

Announcements and Emergency Alerts need identical machinery: resolve an audience across the same three recipient types, track per-recipient read/acknowledgment, and drive the same modal component. Building this twice (once per feature) means two audience resolvers and up to six read-tracking tables. Instead:

- **`NoticeAudienceResolver`** (single service) — given `audience` (`all`/`employees`/`students`/`parents`), returns the matching `User`/`Student`/`ParentContact` collections. Both `NotifyAnnouncementJob` and the new `DispatchEmergencyAlertJob` call it — one place decides who "students" means, not two.
- **`notice_acknowledgments`** (single polymorphic table) — `notice_type`/`notice_id` morph across `Announcement`|`EmergencyAlert`, `recipient_type`/`recipient_id` morph across `User`|`Student`|`ParentContact`. Read-tracking for both features lives here. Trade-off accepted: polymorphic queries index slightly worse than a plain FK, but at this data volume (thousands of rows, not millions) that's immaterial against not building six near-identical pivot tables.

### 3. Emergency dispatch is synchronous-broadcast-first, queued-fanout-second — same pattern SOS Phase A already established

A life-safety broadcast cannot wait on an SMS gateway or a `bulk`-queue backlog. On send: broadcast synchronously on the relevant Echo channel(s) first (already-open web sessions get the takeover instantly, no polling wait), *then* queue `DispatchEmergencyAlertJob` on a **new `emergency` queue** — deliberately separate from the `bulk` queue `NotifyAnnouncementJob` already uses, so a large announcement fan-out can never sit in front of an emergency alert's push/SMS/email delivery.

### 4. Mobile real-time = FCM push, not a second socket connection

Web gets instant in-app takeover via the existing Soketi/Echo wiring (a persistent connection already exists while the app tab is open). AtlasGo has no equivalent persistent connection and shouldn't grow one just for this — the FCM foreground-message handler shows the same takeover UI the instant the push arrives, whether the app is foregrounded, backgrounded, or closed. This is the mobile-native equivalent of "real time," not a lesser substitute.

### 5. Emergency takeover always outranks the announcement queue

If a user has both an unresolved emergency alert and unread announcements pending at the same load, the emergency takeover shows first and requires "Acknowledge" before the announcement queue (if any) follows. An emergency notice competing for attention with "no classes Friday" is not an acceptable ordering.

### 6. Escalating an SOS alert into a broadcast is a distinct, explicit staff action — never automatic

Not every SOS report warrants a campus-wide broadcast (most don't — an individual medical alert stays responder-only, as SOS Phase A already handles). Escalation is a deliberate "Broadcast Public Alert" click from the alert's existing Command Center detail view, pre-filling title/message/severity from the SOS alert's category and location but requiring the staff member to review/edit before it sends. This mirrors SOS's own principle (spec §6, prior module) that safety-relevant actions are never silently automatic.

## Data model

All new tables/columns are additive migrations, per this project's blue-green migration discipline (`->after()`, nullable, no drops/renames in the same deploy).

- **`announcements`** (existing) — `audience` enum expands from `all`/`specific` to `all`/`employees`/`students`/`parents`/`specific`. `specific` keeps its unchanged meaning (named employees via the existing `announcement_user` pivot). `employees` is a new value meaning "all employees" — previously that case was covered by `all`, since employees were the only recipient type that existed. **`all` changes meaning**: it now resolves to every recipient across all three types (employees + students + parents), not just all employees as before. This only affects *new* publishes and live dashboard/modal visibility queries going forward — `NotifyAnnouncementJob` already fired once at original publish time for existing rows (per this module's "editing a published announcement does not re-notify" behavior) and won't re-fire; existing `audience='all'` announcements will simply become newly *visible* to students/parents on their dashboard/modal once this ships, with no retroactive push.
- **`emergency_alerts`** (new) — `title`, `message`, `severity` (enum: info, warning, critical), `audience` (enum: all, employees, students, parents), `status` (enum: active, resolved), `source` (enum: manual, escalated), `sos_alert_id` (nullable FK → `sos_alerts`, only set when `source = escalated`), `created_by` (user_id), `resolved_by` (nullable user_id), `resolved_at` (nullable), timestamps.
- **`notice_acknowledgments`** (new, polymorphic, shared) — `notice_type`, `notice_id`, `recipient_type`, `recipient_id`, `acknowledged_at`. Unique on (`notice_type`, `notice_id`, `recipient_type`, `recipient_id`) — one ack per recipient per notice.
- **`student_credentials`** (existing) — `+fcm_device_token` (nullable string, `->after('email_verified_at')`), mirroring `parent_contacts.fcm_device_token`. This table, not the legacy read-only `students` table, is where student app-state belongs (per its own migration's stated intent). `Student` model gains a `hasOne(StudentCredential::class)` relation for convenience — no change to the guarded/legacy `students` table itself.

## Roles & permissions

- **Announcements**: no new permissions. `announcements.manage` (existing) already gates publish/edit; extending its audience picker to include Students/Parents doesn't change who can use it.
- **Emergency Alert broadcast**: reuses `sos.respond` (existing, already gates Command Center access — DRRM Coordinator, Security Guard, Administrator by default) for both entry points (escalate and standalone create). No new permission — the population trusted to see and triage SOS alerts in Command Center is the same population trusted to decide a campus-wide broadcast is warranted. `sos.manage` is not required for broadcasting (that permission stays scoped to tier/contact/threshold configuration, unrelated to this).

## Notification channels

- **Announcements**: employees keep the existing in-app bell (`NotificationService::notifyUser`, unchanged) plus the new modal; students/parents get an FCM push (title = announcement title, tap deep-links to the announcement) plus the same modal pattern in AtlasGo. No SMS/email for announcements — matches the original module's user-approved "bell + dashboard card only" decision; this spec only adds the modal + AtlasGo reach on top of that, not new channels.
- **Emergency Alerts**: push (FCM) + in-app takeover (Echo for web, FCM foreground-handler for mobile) always fire, for all four audience values. SMS (parents, via the existing SMS Gate provider already used for gate-scan notifications) and email fire for whichever audience was selected — an `employees`-only alert doesn't SMS parents who aren't part of it.

## UI/UX

### Announcements
- **Modal on load** — web: `GET /notices/pending` called once from `AdminLayout.vue` on mount (not baked into every Inertia response, to avoid payload bloat on every navigation). AtlasGo: `GET /api/mobile/notices/pending` called once on app open. Unread announcements render as a **queue modal** ("1 of 3"), non-dismissible via backdrop/Esc — "Mark as Read" writes a `notice_acknowledgments` row and advances to the next; empty queue closes the modal for good (won't reappear for those items on a later load).
- **Dashboard card** — existing `PersonalDashboardService::announcements()` card stays for web; the same card shape (latest N, unread badge) is added to AtlasGo's home screen.
- Poster upload/S3/base64 — unchanged, already correct per project conventions.

### Emergency Alert Broadcast (Command Center)
- **"Broadcast Public Alert"** button on an SOS alert's existing Command Center detail view — opens a form pre-filled from the SOS alert (title/message/severity/category-derived audience default), editable, on submit creates `emergency_alerts` with `source=escalated`.
- **"New Emergency Alert"** button, standalone, same Command Center — same form, blank, `source=manual`.
- **Takeover modal** — web + AtlasGo, full-screen, severity-colored (reserving red specifically for `critical`, per this project's existing color-palette convention of red only for genuine status), single "Acknowledge" action, writes a `notice_acknowledgments` row.
- **"Resolve"** action on an active alert (Command Center) — sends a follow-up "This alert has been resolved: {title}" through the same channels the original used, sets `status=resolved`; resolved alerts become read-only history in Command Center.

## Data flow summary

### Announcement
1. Staff publishes (unchanged `AnnouncementController::publish`) → `NotifyAnnouncementJob` (existing, on the `bulk` queue) now calls `NoticeAudienceResolver` instead of `User::employees()` directly.
2. Employees: existing bell notification, unchanged. Students/Parents: FCM push via `FcmService` (reusing the existing client, new call sites).
3. Next page load (web) / app open (mobile): `GET /notices/pending` returns unacknowledged published announcements addressed to this recipient (checked against `notice_acknowledgments`) → queue modal.
4. "Mark as Read" → `POST /notices/{type}/{id}/acknowledge` → `notice_acknowledgments` row → won't resurface.

### Emergency Alert
1. Staff clicks "Broadcast Public Alert" (from an SOS alert) or "New Emergency Alert" (standalone) in Command Center, submits the form.
2. Controller creates the `emergency_alerts` row, broadcasts synchronously on the audience-appropriate Echo channel(s) — already-open web sessions show the takeover immediately.
3. `DispatchEmergencyAlertJob` queued on the new `emergency` queue: `NoticeAudienceResolver` resolves recipients, FCM push fans out to Students/Parents (and to employees if a mobile channel applies — otherwise in-app only), SMS to parents via SMS Gate, email per selected audience.
4. AtlasGo foreground-handler shows the takeover the instant the push lands, independent of `notices/pending`.
5. User taps "Acknowledge" → same `notice_acknowledgments` endpoint/table as announcements.
6. Staff clicks "Resolve" in Command Center → `status=resolved`, follow-up notice dispatched through the same channels, alert becomes read-only.

## Testing

Standard TDD per this project's convention (PHPUnit, dev Docker container). Key scenarios given the shared-infra + life-safety nature of half this spec:

- `NoticeAudienceResolver` returns the correct `User`/`Student`/`ParentContact` sets for each audience value, including `all` returning the union across all three models.
- Announcement publish reaches students/parents via FCM (mock `FcmService`) in addition to the existing employee bell path — regression-test that the employee path is genuinely unchanged, not just re-routed through the resolver with different output.
- `notice_acknowledgments` is correctly polymorphic: a `User` and a `Student` acknowledging the *same* `Announcement` id don't collide or overwrite each other's row.
- `GET /notices/pending` excludes anything already acknowledged, and (mobile) correctly resolves the current `Student`/`ParentContact` from the Sanctum guard rather than assuming a `User`.
- Emergency alert broadcast fires the Echo event synchronously even if the queued job (SMS/email fan-out) later fails — a downed SMS gateway must never suppress the in-app takeover that already went out.
- Escalating from an SOS alert correctly sets `source=escalated` + `sos_alert_id`, and a standalone create leaves `sos_alert_id` null.
- Emergency takeover outranks a pending announcement queue when both exist for the same recipient in one `pending` response.
- Permission gate: both broadcast entry points require `sos.respond`; a user with only `sos.trigger` cannot reach either.
- Resolve action sends the follow-up notice and flips the alert read-only (further edits/broadcasts rejected).
- FCM push to a student succeeds once `student_credentials.fcm_device_token` is set via `PUT /api/mobile/fcm-token` — regression-test that this endpoint no longer no-ops for the `Student` guard case.

## Rollout

Two phases within one implementation plan, since the shared infra (audience resolver, polymorphic ack table, student FCM token, modal component pattern) is built once and both features depend on it:

- **Phase 1 — Announcements full wiring**: shared infra + student FCM token fix + announcement audience extension + modal (web + AtlasGo) + dashboard card (AtlasGo) + read-tracking. Ships and is verified end-to-end before Phase 2 starts, since it's the smaller, lower-risk half and proves the shared infra works before the life-safety feature builds on it.
- **Phase 2 — Emergency Alert Broadcast**: `emergency_alerts` table + Command Center's two entry points + synchronous-broadcast-then-queue dispatch + SMS/email fan-out + takeover UI (web + AtlasGo) + resolve action, built on top of Phase 1's now-proven audience resolver and ack table.

AtlasGo (Flutter) work in both phases follows the same pattern already established by SOS Phase B and the Foundation Redesign: build in `~/bugsaymis-mobile`, verify via iOS Simulator click-through, merge to local `main` — **does not ship to real devices** until a new APK/IPA is explicitly built and uploaded through the existing distribution pipeline (see `project_atlasgo_mobile`). Backend deploys independently through the normal blue-green flow the moment each phase's backend work is merged.

## Open questions for plan-time (not architectural forks — implementation detail)

- Exact SMS Gate service class/method signature to reuse for emergency-alert SMS (grep at plan time, don't re-derive here).
- Admin UI wording for the expanded audience picker (`all`/`employees`/`students`/`parents`/`specific`) — make sure `all` reads unambiguously as "everyone" now that it's semantically broader than before, not left implicit.
