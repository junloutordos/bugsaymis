# Issuance Flexible Multi-Criteria Recipients (incl. Students)

## Problem

Issuance recipient targeting is currently a single exclusive choice — `issuances.recipient_type` is one of `all` / `office` / `division` / `individual`, resolved entirely against the `users` (staff) table by `IssuanceService::buildRecipients()`/`addRecipients()`. Two limitations:

1. **Not composable.** An admin can target "all staff in Office X" OR "these 3 individuals" but never both at once — no way to combine, e.g., "Office X + two specific people outside it + Division Y."
2. **Students are unreachable.** Only staff (`users` table) can be targeted. There is no way to send an issuance to students by individual, section, grade level, or the whole student body.

## Scope

Touches: `issuances`/`issuance_recipients` schema, `IssuanceService`, `IssuanceController` (`store`, `release`, `addRecipients`), the 3 recipient-notifying jobs (`ProcessIssuanceRelease`, `ResendIssuanceEmails`, `NotifyAddedIssuanceRecipients`), and the recipient-picking UI in `Create.vue`, `Show.vue` (Release Settings panel + Add Recipient modal), plus a new shared `RecipientPicker.vue` component. Also touches `Show.vue`'s Acknowledgments recipient list and `Index.vue`'s recipient-type badge label map for display.

**Out of scope:** Student Portal integration (students are notify-only via best-effort email, no in-portal acknowledgment — confirmed with user), a live resolved-recipient-count preview in the picker UI, editing/undoing a targeting selection after it's been resolved into recipient rows, any change to the existing staff email-status/resend machinery beyond adding the student branch.

## Design

### 1. Data model

**New table `issuance_recipient_criteria`** — the audit record of *what was targeted*, independent of the flattened per-person `issuance_recipients` rows it resolves to:

```
id
issuance_id     FK -> issuances, cascade delete
type            string(20): all_staff | office | division | individual_staff
                            | all_students | section | grade_level | individual_student
target_id       nullable unsignedBigInteger
                  - office        -> offices.id
                  - division      -> divisions.id
                  - individual_staff -> users.id
                  - section       -> sections.id
                  - grade_level   -> grade_levels.grade
                  - individual_student -> students.id
                  - all_staff / all_students -> null
created_at, updated_at
index (issuance_id)
```

One row per selected item — picking 3 offices produces 3 rows, each `type='office'`. Rows **accumulate**, never get deleted/replaced: the original release's selections are recorded, and every subsequent "Add Recipient" call appends its own new criteria rows on top. This gives a complete "what was this issuance sent to, and when did each wave go out" trail for free, without a separate flag.

**`issuance_recipients` gets one new nullable column: `student_id`** — a recipient row is either a staff row (`user_id` set) or a student row (`student_id` set), never both. `students.id` is a plain `int` on a legacy MyISAM table (not `bigint unsigned`), and no FK constraint to it exists anywhere in this codebase today — `student_enrollments.student_id` is deliberately unconstrained for exactly this reason (confirmed in that table's own migration: `$table->unsignedInteger('student_id')`, no `->constrained()`). This column follows that same established convention, not `foreignId()`:

```php
$table->unsignedInteger('student_id')->nullable()->after('user_id');
$table->index(['issuance_id', 'student_id']);
```

**`issuances.recipient_type` is kept, repurposed as a display-only summary.** It is no longer read by resolution logic (the criteria table is authoritative going forward). On release/add-recipient, it's set to the single criterion type name if only one type was selected across all criteria for this issuance, or `'mixed'` if more than one type is present. Existing pre-feature issuances keep their original value untouched — no backfill.

Both changes are additive-only (new nullable column, new table) — safe to ship in a single deploy under the project's blue-green migration discipline.

### 2. Backend resolution (`IssuanceService`)

`buildRecipients(Issuance $issuance, array $data)` and `addRecipients(Issuance $issuance, array $data): array` both move from a `match`/`elseif` on one `recipient_type` string to accepting the full flexible payload:

```php
[
    'all_staff'     => bool,
    'office_ids'    => int[],
    'division_ids'  => int[],
    'user_ids'      => int[],   // individual staff
    'all_students'  => bool,
    'section_ids'   => int[],
    'grade_levels'  => int[],   // 7-12
    'student_ids'   => int[],   // individual students
]
```

New private helper `resolveTargetIds(array $data): array` (returns `['staff' => Collection<int>, 'students' => Collection<int>]`) does the actual union+dedupe:

- `all_staff` → `User::employees()->where('status', '<>', 'inactive')->pluck('id')`
- `office_ids` → `User::employees()->whereIn('office_id', ...)->where('status', '<>', 'inactive')->pluck('id')`
- `division_ids` → same shape, `whereIn('division_id', ...)`
- `user_ids` → `User::employees()->whereIn('id', ...)->pluck('id')` (no status filter — matches existing individual-staff behavior)
- All four staff-side results get merged and `->unique()`'d into the `staff` set.
- `all_students` → `StudentEnrollment::active()->forSchoolYear($currentSY)->pluck('student_id')`
- `section_ids` → `StudentEnrollment::active()->forSchoolYear($currentSY)->whereIn('section_id', ...)->pluck('student_id')`
- `grade_levels` → `StudentEnrollment::active()->forSchoolYear($currentSY)->whereIn('grade_level', ...)->pluck('student_id')`
- `student_ids` → `StudentEnrollment::active()->forSchoolYear($currentSY)->whereIn('student_id', ...)->pluck('student_id')` (individual picks are also constrained to currently-enrolled students, consistent with the other three)
- All four student-side results get merged and `->unique()`'d into the `students` set.

This mirrors the exact pattern `App\Services\HomeroomAttendance\RosterService` already established (`StudentEnrollment::active()->forSchoolYear()`, never the legacy `section_students` mirror). `$currentSY = SchoolYear::where('is_current', true)->first()` is resolved once; if it's null **and** any student-side criterion was selected, throw a 422 (`abort_if`) rather than silently resolving zero students.

`buildRecipients()` (draft → released, deletes-and-rebuilds like today) and `addRecipients()` (post-release, diff-and-insert-only like today) both call `resolveTargetIds()`, then:
1. Write one `issuance_recipient_criteria` row per non-empty selected item (all four staff criteria types + all four student criteria types, skipping any list that's empty/false).
2. Insert `issuance_recipients` rows for the resolved staff set (`user_id`) and student set (`student_id`) — `buildRecipients()` inserts all of them (table was just cleared); `addRecipients()` diffs against existing `user_id`/`student_id` pairs first, same as today, and returns only the newly-inserted recipient IDs.
3. Recompute and save `issuances.recipient_type` as described above.

Supplement inheritance (`IssuanceController::doRelease()` for supplements) copies parent recipient rows verbatim already — it just needs `student_id` added to the copied column list (`get(['user_id', 'office_id', 'student_id'])`). Supplements don't get their own criteria rows (they never picked their own targeting, same as today).

### 3. Controller validation (`store`, `release`, `addRecipients`)

All three replace `'recipient_type' => 'required|in:all,office,individual,division'` with the flexible payload's fields, plus a custom rule that at least one criterion is actually selected:

```php
'all_staff'      => 'nullable|boolean',
'office_ids'     => 'nullable|array',
'office_ids.*'   => 'exists:offices,id',
'division_ids'   => 'nullable|array',
'division_ids.*' => 'exists:divisions,id',
'user_ids'       => 'nullable|array',
'user_ids.*'     => 'exists:users,id',
'all_students'   => 'nullable|boolean',
'section_ids'    => 'nullable|array',
'section_ids.*'  => 'exists:sections,id',
'grade_levels'   => 'nullable|array',
'grade_levels.*' => 'exists:grade_levels,grade',
'student_ids'    => 'nullable|array',
'student_ids.*'  => 'exists:students,id',
```

plus `Validator::after()` closure: fail with "Select at least one recipient." if every one of the 8 fields is empty/false. (`release()` keeps its existing `nullable` carve-out for supplements, which don't submit any targeting fields at all.)

### 4. Student notification (email-only)

`ProcessIssuanceRelease`, `ResendIssuanceEmails`, and `NotifyAddedIssuanceRecipients` each loop over recipients and currently assume `$recipient->user` — add a branch on `$recipient->student_id`:

```php
if ($recipient->student_id) {
    $email = $recipient->student->student_email;
    $name  = $recipient->student->full_name;
    // same "skip if blank, send, mark sent/failed" logic as the staff branch —
    // no NotificationService::notifyUser() call (requires a User; students have none)
} else {
    $email = $recipient->user->email;
    $name  = $recipient->user->name;
    // existing staff branch: mail + bell/push notification
}
```

`IssuanceRecipient` model gets a `student()` belongsTo relation alongside its existing `user()`/`office()` relations. Controllers/jobs that currently `->with('user')` when loading recipients add `->with(['user', 'student'])`.

### 5. Frontend: shared `RecipientPicker.vue`

New component at `resources/js/Components/RecipientPicker.vue`, replacing the duplicated single-select tile block in `Create.vue` (step 3), `Show.vue`'s Release Settings panel, and `Show.vue`'s Add Recipient modal.

**Props:** `offices`, `divisions`, `users` (staff), `sections`, `gradeLevels`, `students` — all pre-loaded arrays, same "load once, filter client-side" pattern the existing individual-staff picker already uses. `students` must be pre-scoped server-side to currently-enrolled students for the current school year (via `StudentEnrollment::active()->forSchoolYear($currentSY)`) — the same constraint `resolveTargetIds()` applies when resolving `student_ids`. If the picker offered a wider/unfiltered student list, picking a withdrawn student would silently resolve to zero recipients with no feedback.

**v-model:** a single reactive object matching the backend payload shape exactly (`{ all_staff, office_ids, division_ids, user_ids, all_students, section_ids, grade_levels, student_ids }`), so callers just bind `v-model="targeting"` and spread it straight into their `router.post()`/`useForm()` call.

**UX:** 8 independent toggle chips (All Staff / By Office / By Division / Individual Staff / All Students / By Section / By Grade Level / Individual Students). Toggling one on reveals its own search+checklist sub-picker beneath it; multiple can be open and populated simultaneously (no mutual exclusion — resolution unions and dedupes server-side, so overlapping selections are harmless, not an error state). Each active sub-picker shows its own "N selected" count, matching the existing per-category summary style — no aggregate resolved-recipient-count preview (out of scope, see above).

Controllers pass the new `sections` (current-SY, `id`/`sectionname`/`levelid`), `gradeLevels` (from `grade_levels` table, `grade`/`label`), and `students` (`id`/`full_name`/current section+grade for display) props alongside the existing `offices`/`divisions`/`users` wherever the picker is rendered (`IssuanceController::create()`, `show()`).

### 6. Display changes

**`Show.vue` Acknowledgments recipient list:** each row currently reads `r.user?.name` / `r.user?.position`. Add a fallback to `r.student?.full_name` (no position line for students). The acknowledged-checkmark/pending-clock icon is only rendered for staff rows (`v-if="!r.student_id"`) — students have no acknowledge action, so nothing renders in that slot for them (not a permanently-pending clock, which would misleadingly imply action is expected).

**Acknowledgment progress bar/percentage:** `ackCount`/`totalCount`/`ackPercent` computeds filter to staff rows only (`recipients.filter(r => !r.student_id)`) before counting — students are structurally incapable of acknowledging, so including them would make 100% unreachable whenever any are targeted.

**`Index.vue` recipient-type badge label map** must handle both old and new values, since `recipient_type` strings now written for new issuances use the criteria-table type names (`all_staff`, `individual_staff`), not the legacy ones (`all`, `individual`) — `office` and `division` happen to be spelled the same in both. The map grows from 4 entries to 9: `all` (legacy), `individual` (legacy), `all_staff`, `individual_staff`, `office`, `division`, `all_students`, `section`, `grade_level`, `individual_student`, and `mixed`.

## Testing

- **Unit (`IssuanceServiceTest` additions):** `resolveTargetIds()` unions and dedupes correctly across combined staff+student criteria; a student picked both individually and via their section resolves once; `all_students`/student-scoped criteria abort with 422 when no current school year is set; supplement inheritance copies `student_id` correctly.
- **Feature:** `store`/`release`/`addRecipients` accept a combined payload (e.g. one office + two individuals + one section) and produce the correct de-duplicated recipient rows and criteria rows; validation rejects an all-empty payload; a student recipient with no `student_email` gets `email_status = 'skipped'`, one with a valid email gets `'sent'` (mocked mail); acknowledgment endpoints remain staff-only (a student row can never reach `acknowledge()` since students have no `users.id`-based session in this app).
- **Manual/visual:** `RecipientPicker` renders correctly with multiple simultaneous toggles active in all 3 call sites (Create step 3, Release Settings panel, Add Recipient modal); Acknowledgments card shows mixed staff+student rows with correct icon behavior; progress bar excludes students from its denominator.
