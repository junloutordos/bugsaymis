# Adjusted Day Schedule — Lunch Gap Collapse + Draggable Recess/White Space Bands

## Context

Earlier the same day, the Adjusted Day Schedule module gained a 5th type, `early_start_stem_split` (7 AM start, STEM subjects 50 min, non-STEM 30 min, Lunch hidden, optional Health Break), plus a drag-and-drop Manual Adjustment calendar (`AdjustedDayCalendar.vue`) replacing the old plain table, for all 5 adjustment types.

Two follow-up items surfaced after using it:

1. **Bug**: "remove the Lunch break" only ever hid the Lunch *band label* from the calendar. It never collapsed the *time* Lunch occupied — a section's periods after where Lunch used to be still land at their original (uncompressed-for-that-gap) time, leaving a large silent, unlabeled hole in the schedule.
2. **New capability**: CID wants to be able to drag (move) and resize Recess and White Space bands directly in the Manual Adjustment calendar, the same way class entries can already be dragged — across all 5 adjustment types, not just the new one.

## Decisions (confirmed with CID)

1. Recess/White Space dragging ships for **all 5 adjustment types**, not just `early_start_stem_split` — consistent with those bands already rendering identically across every type today.
2. Dragging supports **both move and resize** (edge-drag handles to change duration), not move-only.
3. The Lunch gap-collapse fix applies **only to `early_start_stem_split`** — the other shortened-class types intentionally keep Lunch as a normal, uncollapsed break.

## Part 1 — Fix: collapse the Lunch gap, not just its label

### Root cause (verified by hand-tracing the compression math)

`AdjustedClassScheduleService::transformTime()` only ever reduces the duration of *actual class periods* present in `$sectionSlots` — each slot's `'target'` says how short that one period should become. It has no concept of the *idle time between* two periods. Hiding the LUNCH band from the `bands` array (already shipped) only removes its visual label; a section's periods that originally sat on either side of the campus Lunch window still transform to times that preserve the *full original width* of that gap. Traced example: with periods immediately before and after an hour-long Lunch window, the period after Lunch still starts ~90 minutes after the period before it ends, even though both individually compressed correctly — the gap itself was never touched.

### Fix

For `early_start_stem_split` only, when building a section's `$sectionSlots`, additionally inject a synthetic **collapsible gap slot** (`'target' => 0`) for the portion of the campus Lunch window that genuinely falls between two of that section's own real, consecutive class periods:

1. Resolve the campus Lunch window for this grade/day/section via the existing `SchedulingConstants::getEffectiveLunch($gradeLevel, $day, $section->lunchOverrideFor($day))` (already used elsewhere in this service — no new resolution logic).
2. Walk the section's own sorted class periods in pairs (period `i` and period `i+1`, both already sourced from real `ClassSchedule` rows, never the idealized canonical grid — same principle as the Aug 25 fix in this same file).
3. For each pair, compute `gap = [period[i].end, period[i+1].start]`. Intersect `gap` with the resolved Lunch window. If the intersection has positive width, add `['start' => intersectionStart, 'end' => intersectionEnd, 'target' => 0]` to `$sectionSlots`.
4. This slot participates in `transformTime()`'s existing reduction loop exactly like a real period slot — anything after `intersectionEnd` loses the full intersection width as savings.

**Why intersection, not the raw canonical window**: a section whose real schedule doesn't have a gap at the canonical Lunch time at all (e.g., a heavier-loaded section that runs through it) gets no synthetic slot — nothing is fabricated. A section whose real gap only partially overlaps the canonical window (drifted schedule) only loses the overlapping portion. This mirrors the exact reasoning behind the Aug 25 correction in this file (deriving slots from real data, not the idealized grid) and avoids reintroducing that bug class.

**Not touched**: Recess, White Space, Wellness, and any other band — this fix is Lunch-specific and `early_start_stem_split`-specific. The LUNCH band-rejection filter (already shipped, hides the label) stays as-is; this fix makes the *time* match what the hidden label implied.

## Part 2 — Draggable + resizable Recess / White Space bands

### Schema

New table `class_schedule_day_adjustment_band_overrides`, structural mirror of the existing `class_schedule_day_adjustment_overrides` table:
- `adjustment_id` (FK → `class_schedule_day_adjustments`, cascade delete)
- `section_id` (FK → `sections`, cascade delete)
- `band_type` (string — validated to `RECESS` or `WHITE_SPACE` only at the application layer, plain string column matching this module's existing convention of not using DB enums for type discriminators)
- `override_start_time` / `override_end_time` (time)
- unique on (`adjustment_id`, `section_id`, `band_type`)

### Model

New `ClassScheduleDayAdjustmentBandOverride` (mirrors `ClassScheduleDayAdjustmentOverride` exactly: `belongsTo` adjustment and section). `ClassScheduleDayAdjustment` gains a `bandOverrides(): HasMany`.

### Service (`AdjustedClassScheduleService::generate()`)

When building each section's `bands` array, before finalizing a `RECESS` or `WHITE_SPACE` band, look up a matching override (keyed `"{$section->id}:{$band['type']}"`, loaded once per `generate()` call the same way entry overrides already are). If present, use the override's start/end **verbatim** (skip the `transformTime()` recomputation for that one band — identical precedent to how an entry override skips recompression) and flag the band `manually_adjusted: true` so the frontend can style it like a manually-adjusted entry.

### Controller

Two new endpoints, structural mirrors of the existing `upsertOverride`/`removeOverride`:
- `POST /schedules/day-adjustments/{adjustment}/band-overrides` → validates `section_id` (must belong to the adjustment's term), `band_type` (`in:RECESS,WHITE_SPACE`), `override_start_time`/`override_end_time` (`H:i`, end > start); draft-only guard (same as entry overrides); `updateOrCreate` keyed on `(section_id, band_type)`; returns a fresh `generate()` result.
- `DELETE /schedules/day-adjustments/{adjustment}/band-overrides/{sectionId}/{bandType}` → draft-only guard; delete; return fresh `generate()`.

Both routes added to the existing `faculty_loading.manage`-only route group alongside the entry-override routes.

### Frontend (`AdjustedDayCalendar.vue`)

- Recess and White Space band `<div>`s become `draggable="true"` (Lunch, Consult, Activity, Health Break, Flag Retreat, Elective, Science Core bands stay static, non-interactive, exactly as today).
- **Move**: reuses the same native HTML5 drag mechanics already built for entries — `dragstart` captures the band's section+type+duration, `dragover` computes a live snapped drop position with the same client-side conflict pre-check styling entries already have (informational only, not blocking), `drop` calls the new band-override endpoint.
- **Resize**: a second, independent interaction — small drag-handle strips at the top and bottom edge of a draggable band, using `mousedown`/`mousemove`/`mouseup` (not HTML5 DnD, which has no edge-resize concept). Dragging the top handle adjusts only `override_start_time` (end fixed); the bottom handle adjusts only `override_end_time` (start fixed); both snap to 5 minutes and enforce a minimum 5-minute duration (mirrors the existing `override_end_time must be after start_time` validation, plus a floor to prevent degenerate zero/negative-length bands). Ends the drag by calling the same band-override upsert endpoint with whichever single edge changed.
- **Click-to-edit**: the existing precision-edit modal (currently entry-only) is generalized to also open for a band, typing exact start/end times — same modal, same save/remove flow, dispatched to the band-override endpoints instead of the entry-override endpoints when the edit target is a band.
- No new conflict/overlap validation between a repositioned band and class entries — matches this calendar's existing behavior where bands are informational overlays, not validated against entries; CID reviews the visual result before publishing, same as today.

### Out of scope

- Cannot create a Recess/White Space band where none is configured for that grade/day (White Space in particular is genuinely absent on some grade/day combinations) — this feature only repositions/resizes an existing band.
- No change to how Lunch, Consult, Activity, Health Break, or any other band type renders or behaves.
- No change to the entry drag/resize behavior already shipped.

## Testing plan

- **Part 1**: unit test on `AdjustedClassScheduleService` — a section with two real periods straddling the campus Lunch window on an `early_start_stem_split` day: assert the second period's start now lands immediately after the first period's end (gap fully collapsed), not at its previously-uncollapsed position. A second test: a section whose real schedule has no gap at the canonical Lunch time at all — assert nothing is altered (no fabricated collapse). A third: a section's gap only partially overlaps the canonical window — assert only the overlapping portion collapses.
- **Part 2**: feature tests on the two new controller endpoints (create, update via re-upsert, remove, draft-only guard, validation of `band_type` and time ordering) mirroring the existing entry-override test coverage shape. A `generate()`-level test confirming a band override's time is used verbatim and flagged `manually_adjusted`.
- Frontend: same limitation as this morning's Part 3 — no automated coverage for the drag/resize interactions themselves; verify by hand in a real browser once this lands somewhere the dev server actually serves it (main, not an isolated worktree).

## Risks / open items carried into implementation

- The gap-intersection algorithm (Part 1) is the one new piece of real logic; it deserves the most test coverage, same as the anchor-shift math did this morning.
- Two independent drag mechanisms now coexist in `AdjustedDayCalendar.vue` (HTML5 DnD for move, mouse-event based for resize) — needs care so they don't fight over the same pointer events on a band's edges vs. its body.
