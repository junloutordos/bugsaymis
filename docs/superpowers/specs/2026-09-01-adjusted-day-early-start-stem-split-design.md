# Adjusted Day Schedule — Early-Start STEM Split + Manual Adjustment Calendar

## Context

The Adjusted Day Class Schedule module (Faculty Loading → Schedules → Day Adjustments) already supports 4 `adjustment_type` values: `flag_ceremony`, `shortened_classes`, `flag_ceremony_shortened_classes`, `shortened_classes_protect_assessments` (this last one shipped earlier the same day this spec was written, commit `2f997ab8`, not yet deployed). All 4 share one engine, `AdjustedClassScheduleService::generate()`, which builds a full campus preview for one exceptional date without touching the underlying weekly `class_schedules` rows.

CID wants a 5th type: a day where every class is moved earlier to start at **7:00 AM**, with STEM subjects kept at their normal 50-minute length and non-STEM subjects compressed to 30 minutes, no lunch break, and an optional Health Break the CID can add. CID also wants the existing "Manual Adjustment" screen (today a plain table with a type-a-time modal) upgraded to a real calendar with drag-and-drop and live conflict detection, matching the interaction model of the main Schedules calendar (`FacultyLoading/Schedules/Index.vue`) — for **all 5** adjustment types, not just the new one.

## Decisions (confirmed with CID)

1. **STEM tagging**: explicit `is_stem` boolean per Subject, tagged manually in the Subjects admin UI. Not derived from academic unit or code prefix (both have known data-quality gaps in this codebase).
2. **7 AM anchor**: this new type is used only on days that do **not** carry a Flag Ceremony. The existing Monday 7:30–8:00 Flag Ceremony bell-schedule band is untouched and unrelated to this feature — CID applies this new type on days without it.
3. **Health Break**: optional, CID types a title + picks start/end when creating the adjustment (same mechanic as the existing "Official Activity" field). Blank = no break.
4. **Manual Adjustment UI**: the calendar/drag-and-drop/conflict-detection upgrade replaces the plain-table UI for **all** adjustment types, not just the new one — one consistent editing experience.
5. **Recess**: stays in the schedule, unmodified in this feature (only Lunch is dropped, and only for this new type). Only Lunch was explicitly requested for removal.

## Part 1 — STEM Subject tagging

### Migration
`add_is_stem_to_subjects_table`: `$table->boolean('is_stem')->default(false)->after('subject_type');` — additive, nullable-safe, backward-compatible with blue-green deploy.

### Backend
- `Subject::$fillable` gains `is_stem`; `$casts` gains `'is_stem' => 'boolean'`.
- `SubjectController` store/update validation: `'is_stem' => ['boolean']`.

### Frontend
- `Subjects/Index.vue` create/edit modal: "STEM Subject" checkbox.
- Table gets a small STEM badge on tagged rows so CID can audit coverage at a glance.

### Safety net
The new adjustment type's create screen (Part 2) shows a live count, e.g. "14 of 93 active subjects tagged STEM," sourced from a simple count query in `ClassScheduleDayAdjustmentController`. This exists so a CID can't accidentally publish a campus-wide 7 AM schedule where every class silently defaulted to the 30-minute non-STEM duration because tagging was never done. Not a hard block — just a visible warning banner if the count is 0 or looks suspiciously low relative to total active subjects.

## Part 2 — New adjustment type: `early_start_stem_split`

(Working name — trivial to rename before implementation if CID prefers something else.)

### Schema
New nullable columns on `class_schedule_day_adjustments` (migration `add_early_start_stem_split_fields_to_class_schedule_day_adjustments`):
- `day_start_time` (time, default handled at controller level as `07:00`, editable per adjustment)
- `stem_class_duration_minutes` (unsigned smallint, default 50)
- `non_stem_class_duration_minutes` (unsigned smallint, default 30)
- `health_break_title` (string, nullable)
- `health_break_start_time` / `health_break_end_time` (time, nullable)

All additive/nullable — safe under the blue-green expand rule, no contract phase needed.

### Model (`ClassScheduleDayAdjustment`)
- Add new columns to `$fillable`/`$casts`.
- `hasShortenedClasses()` gains `'early_start_stem_split'` to its `in_array` list (reuses existing shortened-family plumbing: grade-scoping, override support, print).
- New `isEarlyStartStemSplit(): bool` helper.
- New `hasHealthBreak(): bool` — true when all three health-break fields are set.

### Service (`AdjustedClassScheduleService::generate()`)

Two changes to the existing per-section loop, both extensions of mechanisms that already exist (the per-slot `'target'` duration was generalized earlier today for the Protect Assessment Periods type; `shift` already exists for Flag Ceremony transfer):

1. **Per-section shift becomes computed, not a fixed global int, for this type.** Today `$shift = $hasFlag ? (int) $adjustment->shift_minutes : 0` is one campus-wide value. For `early_start_stem_split`, compute per section: `shift = day_start_time_minutes - section's own first class period's original start_minutes`. This anchors every section's first period to the same wall-clock 7:00 AM regardless of what its normal first-period start time is (grades differ).
2. **Per-period target duration** — when building `$sectionSlots`, if `adjustment->isEarlyStartStemSplit()`, target = `$schedule->subject?->is_stem ? $adjustment->stem_class_duration_minutes : $adjustment->non_stem_class_duration_minutes` (falling back to the stored defaults 50/30). This slots directly into the existing `'target'` key `transformTime()` already reads — no change to `transformTime()` itself required beyond it already accepting a per-slot target (done today).

**Band changes:**
- LUNCH is added to the existing type-based rejection filter (`->reject(fn ($band) => in_array($band['type'], [...])`), but **only** when `isEarlyStartStemSplit()` — the other shortened types keep showing Lunch as today.
- Health Break renders as a new band (`type: 'HEALTH_BREAK'`), appended the same way `OFFICIAL_ACTIVITY` is today, only when `hasHealthBreak()`.

**Conflict-detection fix (applies to all shortened-family types, not just this one):**
Today `assertNoGeneratedConflicts()` only runs inside `if ($activityStart)` in `generate()`. Since this new type may have neither an Official Activity nor a Health Break set, that gate would silently skip conflict detection entirely for a plain early-start day. Fix: run `assertNoGeneratedConflicts()` whenever `hasShortenedClasses()` is true, independent of whether an activity/break is declared. Pure addition — no existing warning/error path is removed, this only adds coverage for a case that previously had none. This is also a prerequisite for Part 3's live conflict UI, which needs `conflict_warnings` populated on every preview regardless of activity fields.

### Controller (`ClassScheduleDayAdjustmentController`)
- `validatedData()`: add `'early_start_stem_split'` to the `adjustment_type` `in:` rule; add validation for the new optional fields (`day_start_time` required `date_format:H:i` when this type, defaulting to `07:00` if omitted; `stem_class_duration_minutes`/`non_stem_class_duration_minutes` nullable integers with sane min/max, e.g. 10–60; health break fields nullable, but if any one of title/start/end is present all three must be, and end > start).
- `hasShortenedClasses()` private helper: add the new type (mirrors the model).
- `index()`: pass a `stemSubjectCoverage` prop (`['tagged' => n, 'total' => m]`) for the safety-net banner described in Part 1.

### Frontend (`DayAdjustments.vue`)
- New adjustment-type option in the create/edit form's type selector.
- Conditional fields shown only for this type: Day Start Time (default 07:00), STEM/non-STEM duration overrides (collapsed under "Advanced," default 50/30 pre-filled), Health Break title/start/end (optional, collapsed under an "Add a break" toggle).
- STEM tagging coverage banner (Part 1's safety net) shown when this type is selected.

## Part 3 — Manual Adjustment: calendar + drag-and-drop + conflict detection (all 5 types)

### Scope and reuse boundary
The backend contract for "move one entry's displayed time" already exists in full: `upsertOverride`/`removeOverride` (write a `ClassScheduleDayAdjustmentOverride` row keyed by `class_schedule_id`) and `preview`/`resolve` (return a freshly-generated snapshot including `conflict_warnings`). **Part 3 is a frontend-only change** — no new routes, no new backend concepts.

`Schedules/Index.vue` (4,079 lines) already has a proven native-HTML5 drag-and-drop calendar with live conflict pre-checking, lane-packing for concurrent events, and snap-to-5-minutes — but it's built for a **weekly, multi-day, cross-section** view. Extracting a shared abstraction from that file is explicitly out of scope for this feature: that file is heavily battle-tested production code, and a single adjusted-day's layout (one date, no cross-day drag, overrides instead of real schedule writes) is materially simpler. Building a new, focused component that borrows Index.vue's *patterns* (not its code) is lower risk.

### New component: `AdjustedDayCalendar.vue`
Replaces the table body of `ResolveConflicts.vue` (the warnings banner at the top stays as-is).

- One calendar block per grade (matches today's per-grade grouping), sections as parallel lanes/columns within that grade's block for the single effective date — reuse the same lane-packing approach already proven in `Index.vue`'s "By Year Level" view for concurrent same-time entries, adapted to a single day.
- Time axis and background bands (Recess, Lunch-if-still-present, Health Break, Official Activity, etc.) rendered as translucent bands exactly as `bands` already returns from `generate()` today — no backend change needed here, this data already exists.
- Each class entry is an absolutely-positioned block. Two ways to adjust its time, mirroring Index.vue's own dual interaction model:
  - **Drag** the block vertically along its section's timeline → snap to 5 minutes → live client-side overlap pre-check (red/green highlight) against the other entries already in `currentPreview.value`, same-day only (no cross-day matching needed, simpler than Index.vue's version) → on drop, call the existing `upsertOverride` endpoint.
  - **Click** the block → same fine-tune-by-typed-time popover that exists today (kept, not removed — precision editing still matters for exact minute adjustments).
- `upsertOverride`'s response (a fresh `generate()` result with `conflict_warnings`) drives the warnings banner exactly as it does today — no change needed there.
- "Remove override" stays available per manually-adjusted entry, same as today.

### Out of scope
- No change to `Schedules/Index.vue` itself.
- No cross-day dragging (an adjustment is always for one specific date).
- No change to how overrides are stored or how publishing/printing consumes them.

## Testing plan

- **Part 1**: feature test for Subject store/update accepting `is_stem`; no behavior change to existing subjects (defaults false).
- **Part 2**: unit tests on `AdjustedClassScheduleService` mirroring the existing `shortened_classes_protect_assessments` test file's shape — per-section anchor-shift correctness (different grades' original first-period times all land on the same target), STEM vs non-STEM duration split, Lunch band absence, Health Break band presence/absence, and the conflict-detection-runs-without-activity-declared fix (regression test: a plain early-start day with no Official Activity and no Health Break still surfaces a genuine same-grade room conflict).
- **Part 3**: no new backend surface to unit-test (Part 3 doesn't touch the backend). Frontend verified via Chrome MCP click-through in dev: create a draft `early_start_stem_split` adjustment with an intentional conflict, open Manual Adjustment, drag an entry to resolve it, confirm the warning clears and the override persists on reload.

## Risks / open items carried into implementation

- The per-section anchor-shift math (item 1 under Part 2's service changes) is the one genuinely new algorithm in this spec — everything else extends an existing mechanism. It deserves the most test coverage.
- STEM tagging is a manual, ongoing data-entry responsibility for CID going forward — the coverage banner mitigates but doesn't eliminate the risk of an under-tagged catalogue producing a wrong-looking schedule.
