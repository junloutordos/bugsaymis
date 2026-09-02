# Adjusted Day Calendar — Drag-and-Drop "Bump to Unplaced"

## Context

`AdjustedDayCalendar.vue` (the manual-adjustment editor behind the Resolve Conflicts page) already supports dragging a class entry onto a new time slot within its own section, calling `POST .../overrides` (`upsertOverride`) to record the new time. It does not currently detect when the drop target is already occupied by another entry in the same section — both entries end up visually overlapping, surfaced only as a generic conflict warning for CID to review manually before publishing.

CID wants dropping onto an occupied slot to behave like a real scheduling action: the occupant gets displaced instead of silently overlapping.

**Correction found during implementation**: `ClassSchedule::scopeClasses()` (`where('entry_type', 'class')`) is already applied to the query `generate()` uses to build `$sectionSchedule`/`entries` — non-teaching blocks never entered this calendar's `entries` to begin with, pre-dating this feature entirely. "Drop onto a non-teaching block → it's removed" therefore can't occur through the UI (there's nothing to drop onto). Confirmed with CID: drop that behavior — bump-to-unplaced applies only to real subject classes, which is the actual reported problem. The schema/service/controller below still handle a `non_teaching` row gracefully if one were ever unplaced by some other means (it's simply excluded from `unplaced_entries`), but no code path in this feature can produce that state today, and no test asserts removal-on-drop for it.

## Decisions (confirmed with CID)

1. **Where bumped subjects go**: a small chip tray directly beneath each section's own timeline, showing that section's currently-unplaced subjects. A class can only ever return to its own section (a `ClassSchedule` row's `section_id` is fixed), so the tray is naturally per-section.
2. **Non-teaching occupants**: ~~bumping a `non_teaching` entry (e.g. an Advisory block) just removes it from the day's view entirely~~ — moot; see correction above. Non-teaching blocks are already invisible on this calendar and can never be a bump target.
3. **Publish gate**: publishing is **blocked** while any subject-bearing class is unplaced for the adjustment — CID must re-place or the underlying data would silently vanish from the official printed schedule for that day. Non-teaching removals never block publish.

## Schema

New table `class_schedule_day_adjustment_unplaced_entries`, structural mirror of the existing override tables:
- `adjustment_id` (FK → `class_schedule_day_adjustments`, cascade delete)
- `class_schedule_id` (FK → `class_schedules`, cascade delete)
- timestamps
- unique on (`adjustment_id`, `class_schedule_id`)

A row means "this class has no slot on this adjusted day." Both outcomes (bumped subject needing resolution, bumped non-teaching block just gone) share this one table — they're distinguished by the underlying `ClassSchedule.entry_type`, not a separate mechanism, avoiding two parallel concepts for what's structurally the same fact.

## Model

New `ClassScheduleDayAdjustmentUnplacedEntry` (mirrors `ClassScheduleDayAdjustmentOverride`: `belongsTo` adjustment and classSchedule). `ClassScheduleDayAdjustment` gains an `unplacedEntries(): HasMany`.

## Service (`AdjustedClassScheduleService::generate()`)

- Load the adjustment's unplaced `class_schedule_id`s once per `generate()` call (same pattern as `overridesByScheduleId`/`bandOverridesBySectionType`).
- When building a section's `$entries`, skip any `ClassSchedule` row whose id is in that set — it gets no computed position, no override applied, and does not appear in the normal `entries` array.
- For each skipped row where `entry_type === 'class'`, add a lightweight entry (id, subject, faculty, classroom, `duration_minutes` computed from the row's own `start_time`/`end_time`) to a new `section.unplaced_entries` array. `duration_minutes` is preserved so a re-placement drag sizes the entry correctly without it ever having a computed position.
- Skipped `non_teaching` rows are not added anywhere — they're simply absent from the day, matching "just removed."

## Controller

**`upsertOverride()`** (existing endpoint, extended — no new route):
1. Validate as today (`class_schedule_id`, `override_start_time`, `override_end_time`, end > start, draft-only).
2. Inside the existing transaction, run the *current* `generate()` preview for this adjustment and find **every** other entry in the **same section** as the dragged class whose (already-resolved, override-aware) time range overlaps the new range — not just the first match, since a messy prior state could have more than one. This is purely a same-section, entry-vs-entry check; it's independent of and doesn't change the existing cross-section faculty/room conflict-warning system (still computed and shown exactly as today).
3. For each collision found:
   - `entry_type === 'class'` → `updateOrCreate` an unplaced-entry row for it, and delete any pre-existing time-override row for it (a stale override is meaningless once the entry has no slot).
   - `entry_type === 'non_teaching'` → same unplaced-entry insert (the removal mechanism — nothing else needed since it's never surfaced).
4. Upsert the dragged entry's own time override as today.
5. If the dragged `class_schedule_id` itself currently has an unplaced-entry row (i.e., this drag originated from the tray), delete that row — providing an explicit time inherently means "place me here now."
6. Return the fresh `generate()` result, which now reflects: mover placed, occupant(s) unplaced/removed, `unplaced_entries` populated per affected section.

**`publish()`**: before freezing the snapshot, check `$adjustment->unplacedEntries()->whereHas('classSchedule', fn ($q) => $q->where('entry_type', 'class'))->exists()`. If true, reject with a validation error naming that this adjustment has unresolved unplaced classes (CID reviews the tray). Non-teaching-only unplaced rows never trip this check.

## Frontend (`AdjustedDayCalendar.vue`)

- Each section renders a small chip row beneath its timeline box when `section.unplaced_entries.length` — one chip per unplaced class (subject name, small STEM badge if applicable), `draggable="true"`.
- Chip drag start populates the same `dragging` ref used today (`{ kind: 'entry', target: entry, durationMinutes: entry.duration_minutes, section }`), so `onDragOver`/`onDrop` need no branching — dropping a chip is identical to dropping an on-calendar entry from the caller's point of view.
- No new visual treatment for "will bump" on hover — the existing conflict highlight (cross-section faculty/room double-booking) is untouched and unrelated; the actual bump becomes visible in the calendar and tray immediately after the drop completes.
- Publish button (added to the Resolve page in the prior session) surfaces the new validation error via the existing SweetAlert2/Inertia error flow when blocked.

## Out of scope

- No way to move a class to a *different* section via drag (unchanged — a `ClassSchedule` row's section is fixed).
- No undo for a removed non-teaching block — matches "it will just be removed."
- No new visual "will bump X" preview during drag-over — only the end result after drop.
- Band entries (Recess/White Space/Wellness/Health Break) are never bump candidates — this only applies to `section.entries` (real classes/non-teaching blocks), consistent with bands already being informational, non-overlap-checked overlays.

## Testing plan

- **Service**: a section with an unplaced class entry — assert it's absent from `entries` and present in `unplaced_entries` with correct `duration_minutes`; assert a `non_teaching` unplaced row appears in neither array.
- **Controller — bump on collision**: dragging class A onto class B's slot (same section) — assert B gets an unplaced-entry row and its old time-override (if any) is deleted, A's override is created, and the response reflects both.
- **Controller — non-teaching blocks stay uninvolved**: a `non_teaching` row at an overlapping raw time gets no unplaced-entry row from a drop — it was never a collision candidate (guards against a future regression if the upstream `->classes()` filter is ever loosened).
- **Controller — re-placement clears unplaced**: dragging an already-unplaced class onto an open slot — assert its unplaced-entry row is deleted and it now has a normal override/position.
- **Controller — publish gate**: publish rejected with a validation error while a subject-bearing unplaced entry exists; publish succeeds once it's re-placed; publish succeeds with only a non-teaching removal present.
- **Migration**: additive only, no changes to existing tables.
