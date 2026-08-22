# AtlasGo Foundation Redesign — SOS Phase B, Nav Restructure, Design System, Self-Service Profile

**Date:** 2026-08-22
**Status:** Approved (design), pending implementation plan

## Problem

Three things converge into one AtlasGo (Flutter) release:

1. **SOS Phase B** — the SOS Emergency Button (see `2026-08-21-sos-emergency-button-design.md`, Phase A shipped 2026-08-22) explicitly deferred a native AtlasGo trigger. Students today can only trigger SOS via the browser-based Student Portal; AtlasGo has no trigger at all.
2. **AtlasGo's visual design is due for a "premium" pass**, and the user wants the SOS trigger to take over the nav bar's most prominent slot (the raised center button, currently Digital ID), pushing Digital ID into Profile.
3. **Students currently cannot self-update their own record** in the legacy `students` table (contact info, address, parent/guardian details, etc.) — any correction requires a registrar to edit it via the web `/students` admin screen.

Because a full "every screen" premium redesign is a much larger effort than the other two, this spec covers only the **foundation pass**: the nav shell, dashboard/home, Profile, and the new SOS flow — establishing the design system the later full-app rollout (a separate spec/plan) will reuse.

## Non-goals

- **Not the full-app redesign.** Screens untouched by SOS/nav/profile changes (Grades, Attendance, Schedule, portal forms, Lost & Found, RH, clearance, etc.) are explicitly out of scope here — they get a mechanical follow-up pass once this foundation is approved and shipped. They will incidentally look somewhat better where they already reuse now-upgraded shared widgets (`AppCard`, `AppHeader`, buttons), but are not individually redesigned in this pass.
- **Not a new visual identity.** Stays within the existing Atlas brand (navy `#1A3557` / blue accent gradient, Plus Jakarta Sans, arrow mark) — this pass elevates craft (depth, spacing, motion, empty/loading states), not palette or typography family.
- **Not a lock-screen or home-screen-widget SOS trigger.** The native trigger is in-app only (parity with the Student Portal's browser trigger), matching the user's explicit choice over the OS-widget alternative.
- **Not adding staff login to AtlasGo.** Unchanged from the Phase A spec — SOS on AtlasGo is student-only, same as Student Portal.
- **Not changing the SOS backend data model, escalation engine, or Command Center.** Phase B adds one new mobile API endpoint that calls the existing `SosAlertService`; nothing in `app/Services/Sos/` or the admin/responder side changes.
- **Not a live status view of an in-flight SOS alert** (e.g., polling for responder acknowledgment). The one addition beyond web parity is a static "Sent" confirmation screen after successful dispatch — not a live-updating status.
- **Not opening self-service editing to identity or academic fields.** `lastname`/`firstname`/`middlename`/`birthday`/`sex`/`lrn`/`batch`/`status`/`img`/`signature`/`date_encoded`/`encodedby`/`pisaysystemID`/`pisaysystemID2`/`dateofgraduation`/`average`/`honors` and other registrar/academic-encoding columns remain registrar-only, edited only via the existing `/students` admin screen. See the field list below.
- **Not a direct, unreviewed write to `students`.** Every self-service edit is a pending change request; the `students` row itself is only touched when a registrar (permission `manage-students`) approves it.
- **Not touching `student_parent_contact`, `egcu_*`, or any of the other ~24 student-related tables.** Confirmed by inspection that none of them duplicate the personal-info columns in scope here — this feature reads/writes `students` directly (via the new change-request table) and nothing else.

## Scope

Four coupled pieces, one implementation plan:

1. **AtlasGo native SOS trigger** — new mobile API endpoint + Flutter trigger flow.
2. **Nav restructure** — SOS takes the center button slot; Digital ID moves to Profile.
3. **Design system foundation** — token + shared-widget upgrade, applied to `AppShell`/nav, Home, Profile, and the new SOS flow.
4. **Self-service profile update** — student-initiated edit of a defined set of `students` columns, gated behind registrar approval.

## Key architectural decisions

### 1. SOS mobile endpoint reuses the existing service, no new backend logic

`POST /api/mobile/student/sos/trigger` (new route in the existing `routes/api.php` mobile group, `auth:sanctum`) resolves the student via the same `StudentMobileLink` pattern every other mobile endpoint already uses, then calls `SosAlertService::trigger()` — the identical call the web `StudentPortal\SosAlertController::trigger()` makes. Same validation rules (`alert_type`, `is_silent`, `lat`/`lng`/`accuracy`), same response contract (`blocked` + message/hotline, or `201` + `alert_id`). This is exactly the API shape the Phase A spec said Phase B would consume — no migration, no service change.

### 2. Flutter trigger flow mirrors the web state machine, plus one native-only "Sent" state

`lib/src/features/sos/` implements the same sequence as `SosFloatingButton.vue`: category picker (medical/security/fire_disaster/general) → hold-to-confirm (~3s, config-driven like web via `sosConfig`) → cancellable countdown → dispatch. Off-campus/blocked response shows the same message + hotline the web version shows. Silent/duress = long-press on the center nav button itself (parity with web's long-press-on-icon), skips picker/countdown, `alert_type=security`, `is_silent=true`, no visible UI change — just a haptic pulse (native affordance, cheap, doesn't compromise discretion).

**Beyond web parity:** a "Sent" confirmation screen after successful dispatch ("Help has been notified"), since a native life-safety flow shouldn't leave the user wondering whether it worked with no feedback at all. This is one additional state in the same flow, not a live status view (see non-goals).

Needs `geolocator` added to `pubspec.yaml` (not present today) for the one-time location capture, same semantics as the web's `navigator.geolocation.getCurrentPosition`.

### 3. Nav restructure: swap the center button, relocate Digital ID's entry point only

`AppShell`'s `_IdCenterButton` (student-only slot, currently opens `/student/id`) is replaced by an `_SosCenterButton` — same raised-circle treatment (58px, white ring, drop shadow) but red instead of the blue button gradient, reserving red for genuine alert affordances per this codebase's existing UI convention. Tap opens the category picker; long-press triggers silent mode.

`ProfileScreen` (already exists, reached today via avatar tap on Home → `/profile`, unaffected by this change) gets a new "Digital Student ID" entry card that pushes the existing `/student/id` route. **The ID screen itself (`student_id_screen.dart`) is untouched** — only its entry point moves from the nav bar into Profile. Parent-side nav (no center button today, 4 flat tabs) is unaffected by this change.

### 4. Design system: token + shared-widget upgrade, not per-screen bespoke work

Extend `lib/src/core/theme.dart`'s token set — a graduated elevation/shadow scale (currently single flat `kNavShadow`-style shadows), a consistent spacing scale, and named motion curves/durations (currently ad hoc `Duration(milliseconds: 200)` literals scattered per-widget) — then upgrade the shared primitives once: `AppCard`, `AppHeader`, `AppNavBar`/`_NavItem`, buttons, empty-state and shimmer/loading widgets. Every screen using these primitives (including parent-side Attendance/Grades/Schedule, not otherwise touched this phase) inherits the improvement automatically. Screens built fresh against the upgraded primitives in this phase: `AppShell`/nav bar, Home/dashboard, Profile (+ new ID entry + new self-service section), and the SOS flow. This is also the system the later full-app rollout phase reuses rather than re-deriving.

### 5. Self-service profile edits: change-request table, registrar approval, reuse existing `manage-students` permission

New table `student_profile_change_requests`: `student_id` (FK to `students.id`), `requested_changes` (json — `{column: new_value}` diff, only from the approved editable-field allowlist below), `status` (enum: `pending`, `approved`, `rejected`), `reviewed_by` (nullable FK `users.id`), `reviewed_at`, `review_notes` (nullable), timestamps. A student can have at most one `pending` request at a time (enforced in the service, not a DB constraint, to keep the migration purely additive) — submitting a new one while a request is pending is rejected client-side with a clear message, avoiding conflicting simultaneous diffs.

Approval writes the diff to the `students` row and updates the `date_updated` column (already exists on `students`, currently used elsewhere for last-modified tracking) — reuses `manage-students`, the same permission that already gates every write to this table via `StudentController`, rather than inventing a parallel permission string for the same resource.

Rejection requires `review_notes` (mirrors the SOS false-alarm-reason pattern from Phase A — a reviewer decision that blocks/denies something must record why).

**Editable-field allowlist** (personal/contact info only — excludes identity, academic, and encoding columns per the non-goals list above):
`studentcontact`, `contactno1`, `contactno2`, `contactperson`, `contactperson2`, `relation1`, `relation2`, `contact_address1`, `contact_address2`, `contact_ofc_address1`, `contact_ofc_address2`, `contact_ofc_telno1`, `contact_ofc_telno2`, `bloodtype`, `religion`, `ethnic`, `nationality`, `student_email`, `houseno`, `barangay`, `municipal`, `district`, `province`, `zipcode`, `homeaddresstype`, `mcpno`, `fcpno`, `memailaddress`, `femailaddress`, `moccupation`, `foccupation`.

The service validates every key in `requested_changes` against this allowlist server-side (not just client-side UI restriction) — a request containing any key outside the allowlist is rejected outright, since this is the actual integrity boundary, not the Flutter form.

## Data model

Only one new table (additive migration, per this project's blue-green migration discipline):

- **`student_profile_change_requests`** — see decision #5 above.

No changes to `sos_alerts`, `sos_alert_events`, `sos_notification_logs`, `sos_escalation_tiers`, `sos_external_contacts`, or any other existing table.

## Roles & permissions

- SOS trigger: no new permission — the mobile endpoint authenticates via `auth:sanctum` + `StudentMobileLink` resolution, identical gating to every other student mobile endpoint; `sos.trigger` (Phase A's "implicitly all authenticated" permission) already covers this at the service layer.
- Profile change-request review: reuses **`manage-students`** (existing permission, already required for every `students` table write via `StudentController::update`).

## UI/UX

### SOS (AtlasGo)
- Center nav button (student-only): tap → category picker bottom sheet → hold-to-confirm → countdown → dispatch → **Sent confirmation screen** (or blocked message with hotline, if off-campus/no location).
- Long-press on the same button → silent trigger, no visible UI, haptic pulse only.

### Nav / Profile
- `AppShell` center slot: SOS (red), replacing Digital ID (blue).
- `ProfileScreen`: new "Digital Student ID" card (pushes `/student/id`, unchanged screen) + new "Update My Information" section.

### Self-service profile update
- "Update My Information" in Profile: form pre-filled with current values for the allowlisted fields, grouped (Contact Info / Address / Parent-Guardian Info / Personal Details).
- If a request is already `pending`, the section shows a status banner ("Your update is awaiting registrar review") instead of the editable form, with a read-only view of what was submitted.
- On `approved`/`rejected`, the student sees the outcome (and `review_notes` if rejected) the next time they open the section; the banner then clears back to the editable form.

### Design system
- Elevation scale (e.g., flat / raised / floating tiers) replaces ad hoc shadow values.
- Spacing scale replaces literal `EdgeInsets` magic numbers in touched screens.
- Named motion tokens (duration/curve) replace scattered per-widget animation literals.
- Upgraded `AppCard`, `AppHeader`, `AppNavBar`, buttons, empty/loading states — applied to `AppShell`, Home, Profile, SOS.

## Data flow summary

**SOS trigger:** Student taps/holds center button → Flutter captures geolocation via `geolocator` → `POST /api/mobile/student/sos/trigger` → same `SosAlertService::trigger()` as web → same broadcast (`sos-responders` channel) + queued notification fan-out (SMS/email + the student's linked `ParentContact`) already built in Phase A → Flutter shows Sent confirmation or blocked message.

**Self-service profile update:** Student edits allowlisted fields in Profile → submit creates a `pending` `student_profile_change_requests` row (rejected client- and server-side if one is already pending) → registrar reviews via a new panel on the existing `/students` admin area (permission `manage-students`) → approve writes the diff to `students` + updates `date_updated`, or reject requires `review_notes` → student sees the outcome next time they open the section.

## Testing

- **Laravel (PHPUnit):** new mobile SOS endpoint — auth required, blocked/success responses match the web controller's contract, polymorphic `triggerable` resolves to the same `Student` row via `StudentMobileLink`. Change-request service — allowlist enforcement (a request with a disallowed key is rejected), one-pending-at-a-time enforcement, approval writes the diff correctly and updates `date_updated`, rejection requires `review_notes`, permission gate (`manage-students`) on the review actions.
- **Flutter (widget tests):** SOS sheet state transitions (category → hold → countdown → dispatch → Sent/blocked) and the silent long-press path, mirroring the Phase A PHPUnit scenarios. Profile's self-service section: pending-banner vs editable-form branching, allowlist-only fields rendered.

## Rollout

Ships as the **Foundation pass** (Phase B1 of the AtlasGo redesign initiative). The **full-app rollout** (applying the design system to every remaining screen) is Phase B2 — a separate spec/plan, scoped only after this foundation ships and the user has seen it in practice.
