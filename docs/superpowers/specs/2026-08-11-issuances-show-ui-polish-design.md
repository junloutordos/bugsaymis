# Issuances Show Page — UI/UX Polish

## Problem

`resources/js/Pages/Issuances/Show.vue` is capped at `max-w-4xl` (896px). Two concrete bugs result:

1. **Title squeeze:** the document header packs the title (`h1`, in a `flex-1` div) and an action-button cluster (up to 7 buttons depending on role/status: Acknowledge, Sign & Release, Add Related Document, PDF, View Scan, Archive, Restore, Delete) into one `flex` row. When several buttons render, they starve the title's width.
2. **Resend All overflow:** the Acknowledgments sidebar card (1/3 of a 896px page ≈ 260px) packs a heading + up to 3 buttons (Add Recipient / Resend Selected / Resend All) into one header row via `<template #header>`, which overflows at that width.

Beyond the two bugs, the page overall reads cramped for an admin-facing document view — this pass also raises it to a "premium" visual bar consistent with other wide admin pages in the app (e.g. `HR/ServiceRecords/Show.vue` already uses `max-w-7xl`).

## Scope

`resources/js/Pages/Issuances/Show.vue` only. No backend/controller/service changes, no schema changes, no shared-component changes (`AppCard`, `AppButton`, `AppBadge` stay untouched so other modules using them are unaffected).

Out of scope: any other Issuances page (`Index.vue`, `Create.vue`, `CreateSupplement.vue`, `IssuanceSettingsModal.vue`), recipient data model, student-recipients feature (separate spec).

## Design

### 1. Page width & column ratio
- `max-w-4xl` → `max-w-7xl mx-auto` (896px → 1280px), matching the existing `HR/ServiceRecords/Show.vue` convention.
- Body/sidebar grid: `lg:grid-cols-3` (2:1 split) → `lg:grid-cols-5` (3:2 split) so the sidebar column goes from ~260px to ~480px.
- Sidebar column becomes `lg:sticky lg:top-6 lg:self-start` so QR/Hash/Acknowledgments stay visible while scrolling a long document body.

### 2. Document header card
Restructure from a single `flex-row` (title | actions) to a stacked layout:
1. Badges row (control number, type, status, archived) — unchanged.
2. `<h1>` title — full width, no longer sharing a flex row with the action cluster.
3. Meta line (issued by / released date / related-to link) — unchanged.
4. **New toolbar row**: all action buttons (`Acknowledge`, `Sign & Release`, `Add Related Document`, `PDF`, `View Scan`, `Archive`, `Restore`, `Delete`), `flex flex-wrap gap-2`, right-aligned on `sm:` and above, wraps naturally on narrow screens. This is the permanent fix — title width no longer depends on how many action buttons happen to be visible for a given role/status.

### 3. Acknowledgments card
Header restructured from one packed row to two:
1. `UserGroupIcon` + "Acknowledgments" heading, own row.
2. Action row below: `Add Recipient` / `Resend Selected (N)` / `Resend All`, `flex flex-wrap gap-2`.

At the new sidebar width (~480px vs ~260px), 2 buttons fit per row before wrapping — no more overflow at any viewport.

### 4. Premium visual polish (applies across all cards on this page, local markup only)
- Card stack spacing `space-y-5` → `space-y-6`.
- Letterhead/content preview block: slightly more internal padding + subtle `bg-slate-50/50` behind it so it reads as a distinct "document" rather than a plain bordered box.
- Sidebar "metadata" cards (Verification QR, Content Hash, Archive Record) grouped with tighter spacing between each other, with clearer separation before the heavier Acknowledgments card.
- No new colors. Stays indigo-dominant; status colors (green/amber/red) remain reserved for actual status values only, per existing project color-palette convention.

**Dropped during planning:** an earlier draft of this section also called for bumping card body padding (`p-5`→`p-6`) and card header padding (`px-5 py-4`→`px-6 py-5`). The header padding is hardcoded inside `AppCard.vue`'s own template with no override slot, so it can't be changed "locally" without editing the shared component — which this spec puts out of scope (it's used across many other modules). Dropped rather than widen the blast radius; confirmed with the user.

## Testing

Visual/manual only — this is a pure Vue/Tailwind markup change with no new logic branches, computed properties, or backend calls. Verify in dev at:
- Admin view, draft issuance (Release panel action + few header buttons)
- Admin view, released issuance with many recipients (Acknowledgments card button row, sticky sidebar scroll)
- Staff view, released + unacknowledged (Acknowledge button, no admin sidebar cards)
- Narrow viewport (mobile) to confirm the header toolbar and Acknowledgments action row wrap instead of overflow
