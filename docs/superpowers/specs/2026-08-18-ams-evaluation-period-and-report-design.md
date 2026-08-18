# AMS Activity Evaluation — Evaluation Period + Comprehensive Report — Design

## Context

BugSayMis's AMS (Activity Management System) module (`app/Models/AMS/*`,
`app/Http/Controllers/AMS/*`, `routes/ams.php`, `resources/js/Pages/AMS/*`)
already supports two evaluation forms (in-house Likert form vs. Training/
Workshop/Seminar HRU-13/HRU-31 forms), hash-linked participant evaluation,
QR-code walk-in evaluation, and certificate generation gated on
"attended + evaluated" (`ActivityEvaluationEligibilityService`,
`CertificateController`). See `project_ams_module.md` memory for full history.

Two things are missing that this design adds:

1. **No evaluation period control.** Evaluation submission is currently gated
   only by a valid hash/QR token and a duplicate-submission check — never by
   time or an admin decision. There is no way for the evaluation committee or
   the activity's proponent to close evaluation once they've collected enough
   responses, or to reopen it.
2. **No comprehensive attendance/evaluation report**, and no per-day
   attendance data to build one from. `attended`/`hours_attended` are single
   scalar columns per participant covering the whole activity, even when the
   activity spans multiple days.

## Decisions (confirmed with user)

1. **Evaluation period is a manual boolean toggle**, not a scheduled
   open/close date. Default `evaluation_open = true` on all activities
   (including existing ones, via migration default) so nothing currently
   working changes until someone explicitly closes a period.
2. **Who can toggle:** the activity owner, any co-proponent, anyone holding
   `activities.evaluation_committee`, or an Administrator. This matches the
   existing `authorizeManage()` ownership check already used for attendance
   and certificate actions, extended with the evaluation-committee
   permission.
3. **Closing blocks new evaluations only.** No retroactive revocation of
   certificates already issued, and no re-checking of the period on
   certificate download for people who already evaluated before closing.
   Certificate gating needs **no code changes** — it already requires
   `hasEvaluated()`, and closing the period makes `hasEvaluated()`
   permanently false for anyone who hasn't submitted yet, which is exactly
   the desired "closed = no more certificates for stragglers" behavior.
4. **Per-day attendance is a real new capability**, not a cosmetic date-range
   label. New table, admin marks Present/Absent + hours per calendar day.
   Scoped to **multi-day activities only** (`end_date > start_date`).
   Single-day activities (the large majority of existing and future
   activities) keep using the existing single `attended`/`hours_attended`
   columns unchanged — no schema or UI change for them.
5. **The per-activity aggregate `attended`/`hours_attended` becomes a
   derived rollup for multi-day activities**, recomputed whenever daily
   attendance is saved (`attended = 'yes'` if present on any day; hours
   summed across days). This means `CertificateController`, the existing
   `Show.vue` participant table/certificate flow, and
   `ActivityEvaluationEligibilityService` need **zero changes** — they keep
   reading the same two columns they always have.
6. **Report has two views, both read-only, both under existing permissions**
   (`activities.manage|view_all|monitor|evaluation_committee` — no new
   permission strings):
   - On-screen `AMS/Report.vue` — KPI cards + full sortable/filterable
     participant table.
   - Printable `AMS/ReportPrint.vue` — reuses the **exact letterhead
     pattern** from `resources/js/Pages/HumanResource/WFH/PrintAccomplishments.vue`
     (`/images/report_header.jpeg` + `/images/report_footer.jpeg` as
     full-width repeating `<thead>/<tfoot>` images, `window.print()` on
     mount, table-based page-break-safe layout). This is a browser-print
     page, not an mPDF-generated file — consistent with how the WFH report
     already works, and avoids adding a second, inconsistent PDF pipeline
     to the AMS module (which currently uses mPDF only for the certificate,
     a fundamentally different fixed-layout document).
7. **Explicitly out of scope:**
   - No Excel export for this report (the existing raw-evaluation-answers
     export in `ActivityController::exportEvaluations()` is untouched and
     unrelated).
   - No scheduled/automatic period close.
   - No audit log table beyond a single last-change record (see below) —
     YAGNI; nothing in the request asks for a full history of every
     open/close toggle.

## Data Model

### Migration 1 — `add_evaluation_period_to_ams_activities_table`

Adds to `ams_activities`:

| Column | Type | Notes |
|---|---|---|
| `evaluation_open` | `boolean` default `true` | Additive, backward-compatible per this repo's blue-green migration rules. |
| `evaluation_status_changed_at` | `timestamp` nullable | Set on every toggle. |
| `evaluation_status_changed_by` | `foreignId` nullable → `users.id`, `nullOnDelete()` | Who last toggled it. |

### Migration 2 — `create_ams_activity_attendance_days_table`

```php
Schema::create('ams_activity_attendance_days', function (Blueprint $table) {
    $table->id();
    $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();
    $table->enum('participant_type', ['employee', 'student']);
    $table->unsignedBigInteger('participant_id');
    $table->date('date');
    $table->string('attended', 10)->default('no'); // 'yes'|'no', matches existing convention
    $table->decimal('hours_attended', 4, 2)->default(0);
    $table->timestamps();

    $table->unique(
        ['activity_id', 'participant_type', 'participant_id', 'date'],
        'ams_attendance_days_unique'
    );
});
```

Deliberately **not** polymorphic/FK'd to `ams_activity_participants` or
`ams_activity_student_attendance` directly — it mirrors the existing
`participant_type` + `participant_id` convention already used by the three
evaluation tables (`ams_activity_evaluations`, `ams_activity_tws_evaluations`,
etc.), which is the established pattern in this module for "which attendee is
this row about" without a real polymorphic relation.

### Models

- `Activity`: add `evaluation_open`, `evaluation_status_changed_at`,
  `evaluation_status_changed_by` to `$fillable`; cast `evaluation_open` to
  `boolean`, `evaluation_status_changed_at` to `datetime`. Add
  `statusChangedBy(): BelongsTo` (→ `User`), `isMultiDay(): bool`
  (`$this->end_date?->gt($this->start_date)`), `attendanceDays(): HasMany`.
- New `ActivityAttendanceDay` model (table
  `ams_activity_attendance_days`), `$fillable` = all columns except id/
  timestamps, cast `hours_attended` to `decimal:2`, `belongsTo(Activity::class)`.

## Backend

### Evaluation period toggle

- New route: `POST ams/activities/{activity}/evaluation-period/toggle` →
  `ActivityController::toggleEvaluationPeriod()`. Validates
  `open: required|boolean`. Sets `evaluation_open`,
  `evaluation_status_changed_at = now()`,
  `evaluation_status_changed_by = auth()->id()`.
- New private `authorizeEvaluationPeriod(Activity $activity): void` on
  `ActivityController` — allow if: `Auth::user()->isSuperAdmin()`, activity
  owner, a co-proponent (same checks `authorizeManage()` already does), or
  `Auth::user()->hasPermission('activities.evaluation_committee')`.
  `abort_unless(..., 403)` otherwise.
- `ActivityController::show()` / `mapActivity()`: include
  `evaluation_open`, `evaluation_status_changed_at`, and the changer's name
  in the payload so `Show.vue` can render the current state and a "closed by
  X on Y" note.

### Gating evaluation submission

`EvaluationController`:

- `show()`, `showTws()`: if `! $activity->evaluation_open`, render the same
  `Evaluate`/`EvaluateTWS` page with a new `evaluationClosed: true` prop
  instead of the form (page shows a "This evaluation period is closed"
  message; no 404/500 — a participant with a valid link should get a clear
  explanation, not an error).
- `showWalkin()`: same treatment.
- `store()`, `storeTws()`, `storeWalkin()`, `storeWalkinTws()`: check
  `evaluation_open` **before** the duplicate-check/validation and return
  `back()->with('error', 'This evaluation period has closed.')` if closed —
  belt-and-suspenders in case a form was already open in a stale browser
  tab when the period closed.

### Per-day attendance

Extend the two existing attendance-save endpoints rather than adding new
routes — the button the admin clicks and the permission model stay the same;
only the payload/UI shape differs for multi-day activities.

- `ActivityController::saveEmployeeAttendance()`: accept an optional
  `daily: array` (`date`, `attended`, `hours_attended` per entry), validated
  only when `$activity->isMultiDay()`. When present, upsert
  `ActivityAttendanceDay` rows in a `DB::transaction()`, then recompute and
  write the parent `attended`/`hours_attended` as the rollup described in
  Decision 5, replacing the flat `$data` the endpoint used to just save
  directly. When `daily` is absent (single-day activity), behavior is
  byte-for-byte unchanged from today.
- `ActivityController::saveSectionAttendance()`: same treatment per student
  row inside the existing `foreach ($data['students'] as $row)` loop.
- Existing `shouldInvalidateCertificate()` check stays as-is — it already
  compares old vs. new `attended`/`hours_attended`, which now just happens
  to be rollup values instead of directly-entered ones.

### Report

New `App\Services\AMS\ActivityReportService` — single source of truth for
both the on-screen and print views:

- `buildReport(Activity $activity): array` returns:
  - `kpis`: invited count, present count, attendance rate, evaluated count,
    evaluation rate, certificates-issued count.
  - `days`: ordered list of calendar dates between `start_date` and
    `end_date` (empty array if not multi-day — tells the Vue pages whether
    to render per-day columns at all).
  - `rows`: one per participant (employee + individual student rows,
    sections excluded — sections are a grouping construct, not attendees),
    each with: name, type, section/division label, per-day attended map
    (multi-day only), total hours, evaluated (bool), certificate issued
    (bool).

New `ActivityReportController`:

- `show(Activity $activity)` → `Inertia::render('AMS/Report.vue', [...])`.
- `print(Activity $activity)` → `Inertia::render('AMS/ReportPrint.vue', [...])`,
  same data shape, plus activity/proponent info needed for the letterhead
  block (title, venue, dates, proponent name). Unlike WFH's report, there is
  no org-structure chain to resolve a specific evaluation-committee signee
  from — the print page always renders "Noted by:" as a **blank signature
  line** (label only, no name), for manual signing. "Prepared by:" is the
  one resolvable name — the activity's proponent (`activity.creator.name`).

### Routes (`routes/ams.php`)

Added inside the existing `ams/activities` prefix group (same middleware —
no new permission strings):

```php
Route::post('/{activity}/evaluation-period/toggle',
    [ActivityController::class, 'toggleEvaluationPeriod'])->name('evaluation-period.toggle');

Route::get('/{activity}/report',       [ActivityReportController::class, 'show'])->name('report');
Route::get('/{activity}/report/print', [ActivityReportController::class, 'print'])->name('report.print');
```

## Frontend

- `AMS/Show.vue`: in the Evaluations tab, add an "Evaluation Period" status
  badge (Open/Closed, indigo/slate per this project's minimal palette
  convention) + toggle button with a confirm dialog before closing
  ("Participants who haven't evaluated yet will no longer be able to
  evaluate or receive a certificate. Continue?"), plus a small "closed by
  {name} on {date}" note when closed. Add a "View Report" button linking to
  the new report page.
- Attendance-marking UI: when `activity.isMultiDay`, the existing
  attendance modal/section shows a day-by-day grid (same reactive
  date-range-driven grid pattern already built for meal plans in
  `Form.vue`) instead of a single Present/Absent toggle, and submits the
  `daily` array.
- `Evaluate.vue` / `EvaluateTWS.vue`: handle `evaluationClosed` prop — show
  a closed-state message in place of the form.
- New `AMS/Report.vue`: KPI cards row + participant table (dynamic per-day
  columns when applicable), sortable/filterable, indigo-dominant styling
  matching the rest of the app.
- New `AMS/ReportPrint.vue`: modeled directly on
  `HumanResource/WFH/PrintAccomplishments.vue` — same `report_header.jpeg`/
  `report_footer.jpeg` repeating table header/footer, same typography/border
  conventions, `window.print()` on mount. Content: activity info block, KPI
  summary strip, the same participant table as `Report.vue` (print-styled),
  signature block ("Prepared by: {proponent}" / "Noted by:" blank line).

## Testing

Feature tests (TDD, per project convention):

- Evaluation period: default open on new activities; toggle authorization
  (owner/co-proponent/evaluation-committee permission allowed, unrelated
  user 403); closing blocks `show`/`store` on both hash and walk-in routes
  for both in-house and TWS forms; reopening restores access.
- Certificate gating unaffected: an evaluation submitted *before* closing
  still yields a downloadable certificate after closing.
- Per-day attendance: saving `daily` rows upserts
  `ActivityAttendanceDay` correctly; parent `attended`/`hours_attended`
  rollup computed correctly (present on one of N days → `attended='yes'`,
  hours summed); single-day activities unaffected (no `daily` payload
  accepted/required).
- Report service: KPI counts and per-row data correct against a seeded
  multi-day and a seeded single-day activity fixture.
