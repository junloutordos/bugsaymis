# SOS Command Center — Location Resolution & Operational Overhaul

Status: Approved 2026-08-25. Extends the existing SOS Emergency Alert module
(see memory `project_sos_module.md`, `project_announcements_emergency_broadcast_module.md`,
`project_announcements_sos_atlasgo_cards.md`). Scope is **Atlas web Command Center only** —
AtlasGo mobile is not touched by this pass.

## Problem

The Command Center (`resources/js/Pages/Sos/CommandCenter.vue`) currently shows only
`alert_type`, `is_silent`, `status`, `triggered_at`, and the event timeline per alert.
Raw `lat`/`lng`/`accuracy` are captured on every trigger (`SosAlertService::trigger()`)
and persisted on `sos_alerts`, but are never resolved into anything a responder can act
on, and never rendered in the UI at all. There is no way to know who's already responding
to an alert, no way to search/filter past alerts, and no response-time visibility.

## Goals

1. Resolve and display **where the reporter probably is right now**, tailored to their
   role: student → current classroom (via enrollment/section/schedule), faculty →
   current class location (via teaching load/schedule), staff → office.
2. Surface the GPS signal already being captured, as a secondary cross-check (on/off
   campus badge + map pin), not the primary answer.
3. Let responders self-claim an alert so everyone watching the Command Center can see
   who's already handling it.
4. Give responders a filterable history of past alerts and basic response-time stats.

## 1. Location resolution

### New service: `App\Services\Sos\LocationResolverService`

```php
resolve(Model $triggerable, Carbon $atTime): array
// returns: ['type' => string, 'label' => string, 'building' => ?string,
//           'room' => ?string, 'source' => string]
```

`$triggerable` is either `App\Models\User` (employee/faculty/staff) or
`App\Models\Student` (matches `SosAlert::triggerable` polymorphic relation already in
place). `$atTime` is a `Carbon` instance in the app's configured timezone.

**Student resolution order:**
1. Current `FacultyLoading\SchoolYear::where('is_current', true)`.
2. `Registrar\StudentEnrollment` for that student + school year → `section_id`.
3. No enrollment found → `type = 'unknown'`, `label = 'No scheduled location (not enrolled this term)'`.
4. Enrollment found → look up the section's effective schedule for `$atTime` (day of
   week + time-of-day) via the **adjusted-schedule resolver** (see "Adjusted schedule
   integration" below), not raw `ClassSchedule` queries.
5. A class is in session right now → `type = 'classroom'`,
   `label = "Room {classroom.name} — {subject.name} with {teacher.name}"`,
   `building`/`room` from the `Classroom` record, `source = 'schedule'`.
6. No class in session (gap, break, weekend, after hours) → fall back to the section's
   home classroom (`sections.classroom_id`) if present → `type = 'homeroom'`,
   `label = "Homeroom: {classroom.name}"`, `source = 'homeroom'`.
7. No homeroom either → `type = 'unknown'`, `label = 'No scheduled location'`.

**Faculty/staff resolution order** (any `App\Models\User`):
1. Look up the user's effective teaching schedule for `$atTime` via
   `FacultyLoading\ClassSchedule::forFaculty($user->id)` + the adjusted-schedule
   resolver, same as the student path.
2. A class is in session right now → `type = 'classroom'`,
   `label = "Teaching {subject.name} — Room {classroom.name}"`, `source = 'schedule'`.
3. No current class → fall back to `user.office_id` → `Office`/`Division` → `type =
   'office'`, `label = "{office.name} ({division.name})"`, `source = 'office'`.
4. No office assigned either → `type = 'unknown'`, `label = 'No scheduled location'`.

This single ordered fallback (schedule → office/homeroom → unknown) covers both
"faculty with a teaching load" and "pure staff with no load" without a separate code
path — a staff user simply never matches step 1 and falls straight to step 3.

### Adjusted schedule integration

Faculty Loading shipped `AdjustedClassScheduleService` on 2026-08-25 (commit `38eeaeaa`)
to stop drifted/adjusted-day section periods from producing false conflicts. Before
writing `LocationResolverService`, confirm and reuse whatever public method that service
(or a sibling in the `FacultyLoading` namespace) exposes for "the effective schedule
rows for section/faculty X on date Y" — do not requery `class_schedules` directly, or
location will be silently wrong on any day that's been calendar-adjusted. If no such
reusable method exists yet, add one to `AdjustedClassScheduleService` rather than
duplicating its drift-handling logic inside the resolver.

### GPS secondary signal

Reuse the existing `App\Services\CampusPresenceService` (already invoked inside
`SosAlertService::trigger()` for the on/off-campus gate) to produce a `{ on_campus:
bool, zone_label: ?string }` badge from the alert's stored `lat`/`lng`/`accuracy` and
`geofence_zone_id`. This is purely additive read-only reuse of existing logic — no
changes to `CampusPresenceService` itself.

### Snapshot vs. live

- **At trigger time**, `SosAlertService::trigger()` calls `LocationResolverService::resolve()`
  once with `triggered_at` and persists the result onto the new `resolved_location_*`
  columns (see Data Model). This is the permanent, stable record shown in history —
  "where they were expected to be when they hit the button."
- **While an alert is active** (`status` not in `resolved`/`false_alarm`), the Command
  Center's alert-serialization path (`SosAlertController::index`/`show`) additionally
  calls `LocationResolverService::resolve()` live with the current time and returns it
  as a separate `current_location` field, distinct from the persisted
  `resolved_location_*` snapshot. The UI shows both when they differ (e.g. "Reported
  near Room 204 at 10:15 AM · Currently scheduled: Room 210"). Once an alert is closed,
  only the persisted snapshot is shown — no live recompute for historical records.

## 2. Data model

Both additive, nullable, safe for a single blue-green deploy per the project's
migration discipline.

### `sos_alerts` — new nullable columns
| Column | Type | Notes |
|---|---|---|
| `resolved_location_type` | string | `classroom`\|`homeroom`\|`office`\|`unknown` |
| `resolved_location_label` | string | human-readable, as built by the resolver |
| `resolved_building` | string, nullable | from `Classroom`/`Office` if available |
| `resolved_room` | string, nullable | from `Classroom` if available |
| `resolved_source` | string | `schedule`\|`homeroom`\|`office`\|`fallback` |

### New table `sos_alert_responders`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sos_alert_id` | FK → `sos_alerts.id` | |
| `user_id` | FK → `users.id` | the responder |
| `claimed_at` | timestamp | |
| `unclaimed_at` | timestamp, nullable | null while actively claimed |
| timestamps | | |

Supports multiple simultaneous responders per alert (e.g. a guard and a nurse both
claim the same alert) and a full claim/unclaim audit trail.

## 3. Responder self-claim

- `POST /sos/{alert}/claim` — creates a `sos_alert_responders` row for the current
  user (idempotent: no-op if already actively claimed). Gated by the existing
  `sos.respond` permission — no new permission introduced.
- `POST /sos/{alert}/unclaim` — sets `unclaimed_at` on the current user's active claim
  row.
- Both actions append a `claimed`/`unclaimed` `SosAlertEvent` and broadcast it on the
  existing `sos-responders` Echo channel, so every open Command Center session updates
  live without polling.
- UI: an avatar list of currently-active responders per alert, with an "I'm
  responding" / "Stop responding" toggle button scoped to the viewing user's own claim
  state.

## 4. History and stats

### History tab
- New read path (either a `history` query mode on the existing `SosAlertController::index`
  or a dedicated method — implementation detail for the plan) returning paginated
  `sos_alerts` where `status` is `resolved` or `false_alarm`, filterable by date range,
  `alert_type`, `status`, and reporter name (join to `User`/`Student` display name).
  Each row opens the existing alert detail view, which for closed alerts shows only the
  persisted `resolved_location_*` snapshot (no live recompute) plus the full
  `sos_alert_events` timeline.

### Stats tab
Derived entirely from existing timestamps, no new tables:
- Alert volume by `alert_type` and by month.
- Avg. time-to-first-claim: first `claimed` event's `created_at` minus `triggered_at`,
  per alert, averaged.
- Avg. time-to-resolution: `resolved_at` minus `triggered_at`, averaged.

Presented as a few numeric tiles plus a simple bar/line chart (existing chart library
conventions in the app, or a lightweight inline SVG chart — implementation detail for
the plan; follow the `dataviz` skill's palette/form guidance if a new chart component
is built).

## 5. Map

Add `leaflet` (npm) + OpenStreetMap tile layer — free, no API key, no new backend
dependency. Renders a pin at the alert's `lat`/`lng` when present, labeled with the
resolved location. Empty state ("No GPS data reported") when coordinates are null.
Works identically for on-campus and off-campus alerts (e.g. a field trip incident).

## 6. Permissions

No new permissions. All new capabilities (claim/unclaim, history, stats, map) are
gated by the existing `sos.respond` permission, matching the precedent set by the
Emergency Alert Broadcast feature (also reused `sos.respond` rather than adding a new
permission string).

## 7. Testing plan

- `LocationResolverService` unit tests: student mid-class, student in a gap with a
  homeroom, student with no enrollment, faculty mid-class, faculty in a free period
  with an office, faculty with no office, staff with an office, staff with no
  office/division, and at least one case on a calendar-adjusted day to prove the
  adjusted-schedule resolver is actually being used (not raw `class_schedules`).
- Claim/unclaim feature tests: permission gate, broadcast event firing, idempotent
  double-claim, unclaim by a non-claimant is a no-op.
- History filter tests: date range, alert type, status, reporter-name search,
  pagination.
- Stats aggregate tests: seeded fixture data → expected averages/counts.
- Full existing SOS regression suite (trigger, escalation, broadcast) must stay green
  — this pass only adds fields/endpoints, it doesn't change trigger/escalation
  behavior.

## 8. Deploy

All schema changes are additive/nullable — safe within a single blue-green deploy per
CLAUDE.md's migration discipline. `leaflet` is a pure frontend dependency. No changes
to existing SOS trigger, escalation, or broadcast code paths beyond adding the
location-resolution call at the end of `SosAlertService::trigger()`.

## Out of scope (this pass)

- AtlasGo mobile Command Center parity (there is no mobile Command Center today).
- Room-level geofencing / digitized campus floor plan (the map uses real GPS
  coordinates on a street map instead).
- Formal dispatcher-assigned responder workflow (self-claim only, per approved design).
- Incident-report PDF export (already a known deferred item from the original SOS
  spec).
