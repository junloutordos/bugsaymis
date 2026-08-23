# Announcements Audience Extension, SOS Emergency Border, AtlasGo Announcement Cards — Design

**Date:** 2026-08-23
**Status:** Approved, pending implementation plan

## Overview

Three related but independently shippable enhancements to the Announcements and SOS/Emergency Alert stack:

1. **Announcements (web admin):** the audience picker only exposes "All Users" and "Specific Users" (employees), even though the backend (`NoticeAudienceResolver`, `NotifyAnnouncementJob`, mobile notice endpoints) already fully supports `students` and `parents` as audience groups. Close that UI gap.
2. **SOS Command Center (web):** add a blinking, red-glowing visual indicator that (a) appears site-wide on every authenticated employee page while a campus-wide emergency broadcast is active, and (b) appears on the Command Center page specifically when a raw SOS is triggered, ahead of any broadcast decision.
3. **AtlasGo (Flutter mobile):** redesign the dashboard announcement card into swipeable square cards (showing poster/artcard images, capped at 5), with a "see more" entry point into a new full announcement history list page.

Each section below can be implemented and shipped independently; there is no ordering dependency between them, though 1 and 3 share the same underlying `NoticeAudienceResolver`/announcement data model.

---

## 1. Announcements: audience picker extension

### Current state
- `Announcement.audience` is a plain `varchar(20)` (migration `2026_07_10_130000_create_announcements_tables.php`), validated server-side in `AnnouncementController::validateAnnouncement()` as `required|in:all,employees,students,parents,specific`.
- `NoticeAudienceResolver::resolve($audience)` already maps `employees|students|parents|all` to three separate collections (`User`, `Student`, `ParentContact`), and `NotifyAnnouncementJob` already fans out in-app notifications (users) and FCM push (students/parents) correctly for all four group values.
- Mobile fetch (`app/Http/Controllers/StudentAttendance/Api/NoticeController.php`) already filters via `Announcement::visibleToAudienceGroup($group)` where `$group` is derived from whether the authenticated principal is a `Student` or resolves to `parents`.
- The gap is entirely in `resources/js/Pages/Administration/Announcements/Index.vue`: the audience picker UI (around line 142-155) only renders "All Users" and "Specific Users" buttons; `employees`, `students`, `parents` are never exposed, so an admin cannot select them even though the backend would accept and correctly route them.

### Change
- Audience picker becomes a 5-option control: **All**, **Employees**, **Students**, **Parents**, **Specific (employees)**.
- "Specific" keeps its existing behavior exactly as-is (employee-only picker via the `announcement_user` pivot, searching `props.employees`). No individual student/parent targeting — confirmed out of scope per approval (whole-group only for students/parents).
- No backend changes required — validation, resolver, job, and mobile scope already handle all five values correctly. This is purely a Vue template/state change plus (optionally) clearer labels ("All Users" → "All: employees, students & parents" or similar, to avoid confusion now that "Employees" exists as its own option distinct from "All").
- Correct the stale migration comment (`// all | specific`) is not necessary — no migration is being touched, and misleading comments in old migrations are left alone per project convention (migrations are historical record, not living docs).

### Data flow
Unchanged. Admin picks group → `AnnouncementController::store/update` persists `audience` → on publish, `NotifyAnnouncementJob` resolves the group and fans out → `Announcement::visibleToAudienceGroup()` / `visibleTo()` scopes gate what each principal type sees when fetching `/notices/pending` (web) or `/api/mobile/notices/pending` (mobile).

### Testing
- Vue: manual click-through (audience picker renders 5 options, `specific` still shows the employee search, non-specific groups hide it).
- PHP feature test: create announcements with `audience=students` and `audience=parents`, assert a `Student` principal sees the former but not the latter via `/api/mobile/notices/pending`, and vice versa for a `ParentContact` principal. Check first whether `NoticeAudienceResolver`/`visibleToAudienceGroup` already have equivalent coverage before adding new tests (likely does, since the underlying logic predates this UI change).

---

## 2. SOS: site-wide + Command Center emergency border

### Current state
- `emergency-alerts` is a Laravel Echo private channel **already authorized for every logged-in employee** (`routes/channels.php:54-56`, deliberately — comment confirms this is not a bypass). `AdminLayout.vue` (global layout for all authenticated pages) already subscribes to it in `setupEmergencyAlertListener()` (line ~81-88), currently only to feed the global `NoticeQueueModal`.
- `sos-responders` is a separate, permission-gated channel (`sos.respond` or SuperAdmin only) subscribed only in `CommandCenter.vue`, carrying raw, pre-broadcast SOS trigger/update events (`.sos.alert.triggered`, `.sos.alert.updated`).
- `emergency-alerts` carries `.emergency.alert.broadcast` (new campus-wide broadcast) and `.emergency.alert.resolved` (broadcast resolved) — these are the promoted, campus-wide alerts, distinct from raw SOS triage.
- There is currently **no bootstrap signal** — a user who loads any page *while* an emergency broadcast is already active (rather than being on the page when the broadcast event fires) has no way to know that. The existing permission-gated `/sos/broadcast/history` endpoint can't serve this because the border must work for all employees, not just `sos.respond` holders.

### Change

**Site-wide border** (all authenticated employee pages, via `AdminLayout.vue`):
- New reactive state `hasActiveEmergency` (boolean) + `severity`, independent of the existing `NoticeQueueModal` acknowledgment state — the border reflects *campus-wide status*, not *this user's unread state*. Dismissing/acknowledging the modal must NOT clear the border.
- Set `true` on `.emergency.alert.broadcast`, `false` on `.emergency.alert.resolved` (matching by alert id).
- **New backend endpoint:** `GET /sos/emergency-status`, gated only by `auth` (no permission check — mirrors the existing openness of the `emergency-alerts` channel itself), returning `{ active: bool, severity: string|null }` for the latest unresolved `EmergencyAlert`. Called once in `AdminLayout.vue`'s `onMounted` to set initial state before any live Echo event arrives.
- New component `resources/js/Components/Sos/EmergencyBorderOverlay.vue`: `position: fixed; inset: 0; pointer-events: none; z-index` above page content, animated red pulsing/glowing border via CSS `@keyframes`, wrapped in `prefers-reduced-motion` media query to fall back to a static (non-animated) red border for users with that preference. Mounted once in `AdminLayout.vue`, driven by `hasActiveEmergency`.

**Command Center-specific border** (`CommandCenter.vue` only):
- Reuses the same `EmergencyBorderOverlay` component, but driven by a separate local boolean keyed off the existing `sos-responders` channel's `.sos.alert.triggered` event for any alert not yet in a terminal status (`resolved`/`false_alarm`). This gives responders immediate visual urgency for a raw SOS the moment it's triggered — before anyone decides whether to promote it to a campus-wide broadcast.
- Bootstrap for this one is already solved: `CommandCenter.vue`'s existing `index()` controller action already loads current alerts as page props, so initial "is there an active un-triaged alert" state comes for free from existing data — no new endpoint needed here.

### Data flow
```
Raw SOS trigger → SosAlertTriggered event → sos-responders channel
    → CommandCenter.vue: local un-triaged-alert boolean → border ON (Command Center only)

Responder promotes to broadcast → EmergencyAlertBroadcast event → emergency-alerts channel
    → AdminLayout.vue (every authenticated page): hasActiveEmergency = true → site-wide border ON
    → (also feeds existing NoticeQueueModal, unchanged)

Broadcast resolved → EmergencyAlertResolved event → emergency-alerts channel
    → AdminLayout.vue: hasActiveEmergency = false → site-wide border OFF

Page load mid-emergency → GET /sos/emergency-status → hydrates hasActiveEmergency before first Echo event
```

### Error handling
- If `GET /sos/emergency-status` fails (network blip on mount), fail closed (border off, not stuck on) and rely on the next live Echo event to correct state — an emergency that's actually active will re-broadcast or already have listeners elsewhere (Command Center, NoticeQueueModal) surfacing it; a permanently-on border from a failed fetch would be worse (alert fatigue / can't be dismissed).
- Echo disconnect/reconnect: rely on Laravel Echo's existing reconnection behavior (already relied upon elsewhere in the app, e.g. chat); no new handling needed.

### Testing
- PHP feature test for `GET /sos/emergency-status`: returns `active=true` with an unresolved `EmergencyAlert`, `active=false` with none, accessible to any authenticated employee regardless of permissions.
- Vue: manual click-through — trigger a test SOS, confirm Command Center border activates; broadcast it, confirm site-wide border activates on an unrelated page (e.g. Payroll); resolve it, confirm both clear. Confirm acknowledging the `NoticeQueueModal` popup does NOT clear the site-wide border.

---

## 3. AtlasGo: swipeable square announcement cards + full history page

### Current state
- `lib/src/features/notices/announcements_card.dart`: compact widget, plain-text titles only, `data.announcements.take(3)`, no images, no tap-through, no pagination.
- `noticesProvider` (`lib/src/features/notices/notices_provider.dart`) fetches `GET /notices/pending` — unacknowledged items only, no pagination params.
- `NoticeItem` model already carries `posterPath` end-to-end from the backend (`json['poster_path']`) but nothing renders it today.
- No "view all announcements" page exists anywhere in the mobile app.
- Poster images are stored in private S3 and served on web via `GET /media/{path}` → `StorageProxyController::serve`, gated by session (`auth`) middleware only — not reachable by mobile's Sanctum token auth.

### Change

**Card widget redesign** (`announcements_card.dart`):
- Horizontal swipeable `PageView` (or similar) of **square** cards, poster image as the card background (falls back to a solid-color/icon placeholder card when `posterPath` is null), title + short snippet overlaid, capped at **5 items** (`data.announcements.take(5)`).
- "See more" affordance (icon/button) at the end of the swipe sequence or as a persistent trailing control, navigating to the new list page.
- Uses existing design tokens from `lib/src/core/theme.dart` (`AppColors`, `AppRadius.card`, `AppSpacing`, `AppMotion`) for visual consistency with the rest of the app's premium redesign — no new design system introduced.

**New list page** (net-new, e.g. `lib/src/features/notices/announcement_list_screen.dart`):
- Tile/grid layout of all published announcements visible to the user's audience group (student or parent), each showing poster thumbnail (if present), title, date, and a read/unread visual marker.
- Paginated (infinite scroll or page-based — implementer's call at plan time, whichever fits existing AtlasGo list patterns elsewhere in the app).
- Tapping an item can open a detail view — reuse whatever the existing `NoticeQueueModal`-equivalent detail rendering pattern is on mobile (`notice_queue_dialog.dart`) if adequate, rather than building a new detail screen from scratch.

**Backend additions:**
1. New paginated endpoint for full history (approved: full history including read items, not unread-only). Likely `GET /api/mobile/notices/history` on the existing `StudentAttendance\Api\NoticeController`, reusing the same `visibleToAudienceGroup()` scope but without the "unacknowledged only" filter, adding pagination and an `is_read` flag per item derived from the existing acknowledgment relation.
2. Mobile poster access: add `GET /api/mobile/media/{path}` under `auth:sanctum` in `routes/api.php`, reusing `StorageProxyController::serve` as-is (it's already generic/path-based) — just a new route entry pointing at the same controller method, so the private-S3-via-proxy pattern stays consistent with the rest of the app rather than introducing a second image-serving mechanism.

### Data flow
```
Dashboard load → noticesProvider (unchanged, /notices/pending) → AnnouncementsCard
    → renders top 5 as swipeable square cards, poster via GET /api/mobile/media/{path}

"See more" tap → AnnouncementListScreen → new history provider
    → GET /api/mobile/notices/history?page=N → paginated full list, is_read flag per item
    → poster thumbnails via same /api/mobile/media/{path} proxy
```

### Error handling
- Missing/failed poster load: card falls back to a placeholder (solid color + icon), never a broken-image glyph — matches existing AtlasGo patterns for optional images elsewhere.
- Empty state (no announcements at all): existing card already has some empty/zero-count handling per current code; list page needs an equivalent "no announcements yet" empty state.

### Testing
- Flutter widget tests: card renders correctly with 0, 1, and >5 announcements (cap enforced), poster vs. placeholder rendering, swipe gesture triggers page change.
- Flutter widget test: list page pagination (loads next page on scroll/tap), read/unread marker reflects `is_read`.
- PHP feature test: `GET /api/mobile/notices/history` returns announcements for the caller's audience group including previously-acknowledged ones, correctly paginated, and `GET /api/mobile/media/{path}` serves a poster to a Sanctum-authenticated Student/Parent.

---

## Out of scope (explicitly deferred)

- Individual student/parent targeting in the "Specific" announcement picker (group-level only, per approval).
- Severity-based visual differentiation on the emergency border (v1 is one consistent red treatment).
- A new detail/reader screen for AtlasGo announcements beyond what `notice_queue_dialog.dart`'s pattern already provides, unless found inadequate at implementation time.
