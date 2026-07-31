# Codex Memory

## 2026-07-31 - Weekly Assessment Tracker: Interactive Click-to-Schedule Calendar + Individual Faculty WAT Tracker (deployed)

- **User request:** make the WAT assessment display calendar-based like the Class Schedule calendar, with click-a-cell-to-add scheduling wired to the Class Record Setup tab (checking WAT limits on create, with an optional "apply to other sections" for the same-subject/different-section case, itself re-validated against that target's own WAT limits), plus a new Individual Faculty WAT Tracker.
- **Discovery before building:** most of the requested backend wiring already existed — `WatRuleService` (daily/weekly graded+major caps, plotting deadline, exam-window exemption, schedule-day check, Science Core/Elective section pooling), the `upsert()` endpoint's server-side cap validation, and a full `applyToSections()`/`checkWatForApply()` push-to-other-sections flow with independent per-target re-validation (grading-option match, lock status, archived status, WAT caps) — all pre-existing and already tested. A read-only month-calendar view (`SectionAssessmentCalendar.vue`) also already existed but was purely a viewer with no click-to-schedule affordance. Presented this analysis before writing code; user approved with one explicit exclusion: **do not change WAT form printing** (`wat-pdf.blade.php`, `printForm`/`renderPdf`).
- **What was actually built (the real gap was UX, not business logic):**
  - `SectionAssessmentCalendar.vue` extended with `editable`/`pendingRows`/`disabledDates` props, a click-to-schedule picker modal (pick an undated draft row, assign the clicked date), and `schedule`/`apply-to-sections` emits — read-only callers (existing "View Section Calendar" usage) are unaffected since these props are opt-in.
  - `watUtils.js` gained `WAT_LIMITS`, `weekStartOf()`, `checkWatCap()` — a client-side mirror of the daily/weekly cap math for instant visual feedback in the calendar; server-side `upsert()` remains the sole source of truth, unchanged.
  - `Show.vue` wired the calendar to the existing `assessmentDraft` state via `pendingAssessmentRows` (computed list of undated, titled rows), `calendarDateFeasibility()` (mirrors the existing schedule-day + plotting-deadline checks), and handlers (`onCalendarSchedule`, `onCalendarApplyToSections`) that write the picked date back into the draft and reuse the existing `onDateChange()` cap-check — then prompt (SweetAlert) to open the pre-existing "Apply This Setup to Other Sections…" modal, reusing `applyToSections()` unchanged.
  - New `WatRuleService::facultyWeekData(userId, schoolYearId, weekStart)` — teacher→sections aggregation direction (distinct from the pre-existing `teacherBreakdown()`, which is section→teacher), built on top of `weekData()` per distinct section so pooling/exam-window/cap logic can't drift from the section-facing tracker. Resolves a teacher's own class records via `teacher_id` OR `ClassRecordTeacher` (PEHM co-teacher pivot), matches items by `teacher_name` per the same convention `teacherBreakdown()` uses, and reports each section's shared remaining weekly graded/major room.
  - New `WeeklyAssessmentTrackerController::myTracker()` + route `class-records.wat.my-tracker` (no coordinator/ACIDAA gate — any authenticated user with ≥1 class record this SY, since it only ever surfaces their own data) + new page `Wat/MyTracker.vue` (Mon–Fri grid of the teacher's own plotted load across every section/subject, per-section remaining-room badges, Friday-noon deadline reminder for under-planned sections) + nav entry ("My WAT Tracker", Administrator/Faculty roles).
- **Self-caught bug during editing:** a `strReplace` on `WatRuleService.php` accidentally truncated `teacherBreakdown()`'s closing `return [...]; }` while inserting the new method after it — caught via `php -l` before running tests, fixed, and reverified with a second `php -l` pass plus the full test suite.
- **Tests:** new `WeeklyAssessmentTrackerMyTrackerTest.php` (8 tests, 51 assertions) — cross-section/cross-subject aggregation, excludes a different teacher's assessments, includes PEHM co-teacher records, correct *shared* (not per-teacher) remaining-room math, excludes archived records, route access control (any teacher with a record; empty-but-200 for a teacher with none), and own-data-only scoping. Full pre-existing WAT regression suite (`WeeklyAssessmentTrackerTest`, `ClassRecordAssessmentControllerTest`, `ClassRecordAssessmentSectionCalendarTest`, `ClassRecordAssessmentDeletionTest`, `ClassRecordPehmCoTeachingTest`, `QuarterExamWindowTest`) re-run clean: 75 passed, 303 assertions, zero regressions.
- **Verification:** `php -l` clean on every touched PHP file; `npm run build` (run on host — no Node in the `php` container) succeeded, confirming Vue/JS syntax across `Show.vue`, `SectionAssessmentCalendar.vue`, `MyTracker.vue`, `watUtils.js`, `navigation.js`. Confirmed via `git diff` that `WeeklyAssessmentTrackerController.php`'s diff is purely additive (only the new `myTracker` method) and `resources/views/class-record/wat-pdf.blade.php` has zero diff at all — the "don't touch WAT printing" constraint was honored exactly.
- **Commit:** `0ebca77c feat(class-records): interactive WAT calendar + individual faculty tracker` on `junlou` — staged the 9 feature files by name (`WeeklyAssessmentTrackerController.php`, `WatRuleService.php`, `navigation.js`, `Show.vue`, `SectionAssessmentCalendar.vue`, `MyTracker.vue`, `watUtils.js`, `web.php`, the new test file), excluding unrelated `.claude/*`/`.codex/memory.md`/untracked `.kiro/session-*.md` per the established convention. This commit's parent, `ead50a2b` (auto-present on date add / IU flag sync / subject submission panel — from the *previous* session, on the same `junlou` branch, left untouched/unstaged by that other session while this work was in progress on a shared dev box) — was still local-only at the time this session started.
- **Deploy — user explicitly confirmed all 3 pending commits ready (`982fe560` sex-grouping fix, `ead50a2b` attendance/IU/submission-panel feature, `0ebca77c` this WAT work):** `git checkout main && git merge junlou && git push origin main && git checkout junlou`, pushed `a2993075..210f16ef`. Confirmed the push actually triggered CI via `gh run list` (caught it `in_progress` at 17s), then `gh run watch <run-id> --exit-status` — GitHub Actions "CRCMIS Deploy" (run `30587863653`) completed successfully in 20m57s (build app+nginx images → push ECR → update ECS task def → roll worker service → blue/green wait). Confirmed live via `aws ecs describe-services`: new task definition `crcmis-prod:714` running 2/2 healthy tasks (`rolloutState: IN_PROGRESS`, within normal bake-time window), old `:713` correctly `DRAINING`. This deploy included a new migration (`2026_07_31_050000_add_incomplete_uniform_flag_to_class_record_attendance_records`, from the `ead50a2b` commit) — ran automatically as the pre-deploy one-off task, additive/blue-green-safe per the existing convention, no manual migration step needed.
- **Repository state after this session:** `junlou` is 3 commits ahead of `origin/junlou` (unpushed feature-branch history; unrelated to `main`, which is fully up to date and deployed). `.claude/*`/`.codex/memory.md`/`.kiro/session-*.md` remain the standard locally-modified/untracked files excluded from every feature commit, per convention.

## 2026-06-30 - UI/UX Refactor Deployment

- Completed shared UI/UX refactor for BugSayMis/Atlas.
- Added `AppFilterBar` and `AppIconButton`.
- Expanded `AppButton` with link rendering, block layout, and success/warning variants.
- Expanded `AppModal` with subtitle, backdrop behavior, body/panel class hooks, and Heroicons close button.
- Fixed `AppSelect` label class typo.
- Extracted `AdminLayout` UI responsibilities into `AdminTopbar`, `VersionHistoryModal`, `ReportDateRangeModal`, and `SessionExpiredOverlay`.
- Refactored `Users/Index.vue`, `ITJobRequests/Index.vue`, and `DocumentTracking/Index.vue` to use shared page header, filter, button, modal, and table primitives where practical.
- Verification passed: local `npm run build`, Docker image build frontend step, and local `/login` HTTP response.
- Browser screenshot QA was not completed because browser control was unavailable in the session.
- Commit: `b5a83ab refactor: standardize core UI components`.
- Deployed to `main` via merge commit `d049279`.
- Returned to `junlou` branch after deployment and preserved unrelated local dirty files.

## 2026-07-21 to 2026-07-23 - Attendance, Student, Scheduling, and Enrollment Improvements

### Online Time Punch

- Made AM/PM punch slots independent so employees can record a later punch even when an earlier online punch is missing (for example, when the earlier punch was made physically).
- Kept DTR generation responsible for reconciling online and physical punches.
- Changed displayed punch labels to AM/PM-first wording: `Time In AM`, `Time Out AM`, `Time In PM`, and `Time Out PM`.
- Added punch parsing/regression coverage.
- Commit: `7afe5ccc fix: allow independent online time punches`.

### Student Profile Updates

- Fixed production student updates that were not persisting reliably.
- Added a dedicated update request and `StudentProfileService` so validation, schema differences, and persistence are handled consistently.
- Added unit coverage for the student profile update path.
- Commit: `0da8c42a fix: persist student profile updates`.

### Dashboard Greeting and Nickname

- Changed the dashboard hero greeting from the user's last name to first name.
- Added an editable nickname to the employee profile; the hero greeting prefers nickname and falls back to first name.
- Added the additive nullable `users.nickname` migration and name-formatting/profile tests.
- Commit: `b1ae59cd feat: personalize dashboard greeting with nickname`.

### Computer Laboratory Scheduling

- Added computer-laboratory scheduling backed by class schedules and lab bookings.
- Added lab-aware subjects/classrooms, booking synchronization, permissions, routes, and automated coverage.
- Added drag-and-drop transfer of schedule cells between laboratories with server-side conflict/capacity validation.
- Changed laboratory schedules from simple lists to time-positioned weekday calendars, including laboratories with no plotted schedules such as Computer Lab 4.
- Primary commits: `5ef1886a feat: add computer laboratory scheduling`, `eb428a0e feat: improve scheduling and attendance workflows`, and `a774b5bc feat(computer-labs): add per-lab weekday calendars`.

### Class Schedules, Teacher Attendance, and Class Record Attendance

- Added section adviser names to class-schedule calendar cells and print sheets without the `HR Adviser` / `Academic Adviser` label, saving cell space.
- Adviser names resolve from designation assignments and work for homeroom schedule bands.
- Fixed the production Teacher Attendance 500 path by hardening attendance service/controller/export handling and added service regression coverage.
- Replaced the Class Record attendance dropdown interaction with direct click selection matching the uniform grid behavior.
- Commits: `eb428a0e feat: improve scheduling and attendance workflows` and `fab1581e fix(faculty-loading): show advisers in homeroom bands`.

### Lao, Melchizedek Y. Faculty Schedule Investigation

- Production account confirmed as user ID `184`, email `mlao@crc.pshs.edu.ph`, active Faculty role, with `faculty_loading.view_own`.
- Current term confirmed as academic term ID `6` (`Full Term, S.Y. 2026-2027`).
- Production data was valid: faculty load ID `83`, six assignments, 18 total units, and 20 schedule rows.
- Running the production controllers as this user returned all six load assignments and all 20 schedules, so no production record repair was needed.
- Improved discoverability by pinning `My Faculty Schedule` and `My Load Assignments` at the top of Faculty Loading and renaming the load page consistently.
- Added RBAC and current-term data-delivery regression tests, including tentative schedules.
- Commit: `2d886477 fix: surface faculty schedule and load assignments`.

### Enrollment Management Section Transfers

- Added a transfer-section icon beside enrolled students and a modal showing eligible same-grade targets, adviser, and capacity.
- Added a dedicated `registrar.enrollment.transfer-section` endpoint protected by `students.enrollment.manage`.
- Internal transfers keep the student `enrolled` and require a different, active, same-grade section in the same school year with available capacity.
- The transfer runs transactionally with a target-section lock and synchronizes `student_enrollments`, legacy `section_students`, and linked student-clearance section snapshots.
- Existing Class Record rosters/scores/attendance remain untouched because they may already contain academic data; future roster imports use the corrected enrollment section.
- Added five feature tests covering successful synchronization, capacity, grade/year/activity/status restrictions, and authorization (22 assertions).
- Commit: `9d52dcfb feat: add student section transfers`.

### Deployment and Repository State

- All commits above are contained in `origin/main`.
- Latest confirmed deployment: GitHub Actions `CRCMIS Deploy` run `29927554238`, successful at head `f05f0192` on 2026-07-22.
- Active local branch after the work: `junlou`.
- At memory update time, only `.claude/scheduled_tasks.lock` and `.claude/settings.local.json` remained locally modified; these were intentionally not included in feature commits.

## 2026-07-23 - CID SY 2026-2027 Research Advisory Reconciliation

- Source workbook: `Temporary (On-going) PROPOSED FACULTY LOADING SY 2026-2027 (1).xlsx`, sheet `Adjusted 18-unit Load`, dated July 21, 2026.
- Scope was restricted to research advisories for academic term ID `6` (`Full Term`, SY `2026-2027`). Teaching and every other assignment type were explicitly excluded because the CID Chief had already updated them manually.
- The approved production matrix was applied: 37 advisories unchanged, 7 updated, 2 created, and 12 obsolete advisories marked `dropped`; the final target contains 46 active advisories totaling 67 research units.
- `New Biology 3` / Grade 11 Groups 11-12 is assigned to Bayron, Janina Erika S. Production advisory ID `58` was already correct and was preserved unchanged at 2 units.
- The workbook spells Penados's first name as `Jhon`, while the active production account is user ID `114`, `Penados, John Michael`; the reconciliation command pins that verified account explicitly.
- `Fernando, Michelle B.` is pinned to production user ID `25` because another duplicate account exists.
- Production post-verification passed: 46 active advisories, 41 aggregated research load-assignment rows, zero advisory/link mismatches, and zero faculty-load research-unit drift.
- Non-research data was unchanged: 364 non-research assignment rows, including 275 teaching rows. The before/after SHA-256 checksum was identical: `10c06f262e6a9049938bb08544c1277f0ca66dcb53569fbe0f89a867fc23af1e`.
- Added a dry-run-by-default, SY-guarded, lock-aware, transactional reconciliation command and service with post-write verification and focused tests.
- Commits deployed to `main`: `d76da362 fix: reconcile CID research advisories` and `27e87d70 fix: pin Penados research adviser account`.
- GitHub Actions deployment run `29972404667` completed successfully at head `27e87d70`.

## 2026-07-24 - Faculty Print Sheet Bell-Schedule Bands + Recess Exclusion

- Diagnosed a gap where Flag Ceremony/Homeroom/Advising bands never appeared on the Individual Faculty Schedule print, even though real CID-plotted `non_teaching` schedule rows (Add Non-teaching) already printed correctly and the interactive My Faculty Schedule calendar already showed the bands.
- Root cause: `buildPrintSheet()` only built `dayConfigs.blocked` bell-schedule bands when `$gradeLevel !== null`; faculty print sheets always pass `gradeLevel = null` (one teacher spans multiple grades), so the faculty branch only ever built a Lunch band (added in the prior 2026-07-23 session) and silently dropped Flag/Homeroom/Recess/etc.
- Fix: faculty branch of `buildPrintSheet()` now derives the faculty's taught grades from `$schedules` (already fetched) and merges `FLAG`/`FLAG_RETREAT`/`HOMEROOM`/`ADVISING` bands across those grades (de-duplicated by type+start+end), alongside the existing per-faculty synced Lunch band.
- User explicitly requested Recess be excluded from both faculty views since it's a student-only break, unlike Flag Ceremony/Homeroom which faculty attend/supervise — added `RECESS` to the exclusion list in both `buildPrintSheet()` (faculty branch) and `dayConfigFor()`'s `viewBy === 'faculty'` branch in `Schedules/Index.vue` (the latter previously excluded `WHITE_SPACE`/`WELLNESS`/`CONSULT`/`LUNCH` but had missed `RECESS`, so My Faculty Schedule/By Faculty calendar was also leaking a student break onto faculty views before this fix).
- Added `test_faculty_print_includes_flag_and_homeroom_but_excludes_recess` to `FacultyLoadingHttpTest.php`.
- Verified via `php artisan test tests/Feature/FacultyLoading/FacultyLoadingHttpTest.php` (required a temporary `conf.d/zz-test-memory.ini` memory_limit bump to 1024M in the dev container — default 128M OOMs on this file's full run with OpenTelemetry exporter enabled; removed after, confirmed reverted to 128M): 44 passed including the new test; 3 pre-existing failures (`test_cid_can_create_subject`, `test_cid_can_update_subject`, `test_cid_can_create_schedule`) confirmed unrelated via `git stash` — they fail identically on the unmodified branch.
- No migration needed — presentation-layer only change.
- Commit: `a77da81b fix(faculty-loading): show Flag/Homeroom bands on faculty print, drop Recess from faculty views`. Not merged/deployed to `main` yet.

## 2026-07-24 - Computer Laboratory Scheduling: Transfer, Color-Coding, Print, and Approval Workflow

### Click-Based Lab Transfer

- Added a click-based "Transfer" action on each movable schedule cell (`LabScheduleCalendarCard.vue`), alongside the existing drag-and-drop transfer, opening a modal to pick a target laboratory.
- Added a client-side vacancy precheck (reusing the existing `overlaps()` helper against loaded bookings) that flags occupied targets and disables submission, with the server-side `ComputerLabSchedulingService::moveToRoom`/`moveConflicts` remaining the authoritative conflict guard via the existing `computer-labs.bookings.move` endpoint (no backend changes needed).
- Increased calendar `SCALE` (1.2 → 1.8 px/min) and the minimum booking block height floor (28px → 56px) so cell text (title/status/time/faculty/actions) is no longer clipped on short bookings.

### Subject Color-Coding and Formal Print Sheet

- Added `resources/js/Utils/subjectColor.js` — a deterministic hash-based 12-color palette (no DB column needed) so the same subject/title always renders the same color on screen and in print; replaced status-only coloring in `LabScheduleCalendarCard.vue` (dashed border = pending, solid = confirmed/approved).
- Added a formal print page `ITJobRequests/ComputerLabs/Print.vue` reusing the class-schedule's `report_header_landscape.jpg`/`report_footer_landscape.jpg`, per-lab title, weekly time-grid layout, and a two-column signatory block; wired via a new `ComputerLabController::printSchedule` action and `computer-labs.print` route. The "Print" button on each lab card now opens this formal sheet instead of calling `window.print()` on the interactive widget.

### Computer Laboratory Schedule Approval Workflow

- Added a term-wide (all labs in one submission) approval workflow mirroring `ClassScheduleApprovalService`: **Prepared by** is pinned to the Science Research Assistant assigned to the Computer Laboratory (San Miguel, Alexis Dave M., user id `5`, confirmed via the existing IT Job Request "Technical Assistance on Events" routing reference); **Approved by** is resolved by the `CID Chief` role.
- New `computer_lab_schedule_approvals` table/model/service/controller (`ComputerLabScheduleApproval`, `ComputerLabScheduleApprovalService`, `ComputerLabScheduleApprovalController`), reusing the existing `DigitalSignatureService` for PIN-gated signing (stages `prepared`/`approved`) and hash-drift detection between submission and approval.
- Registered a `computer_lab_schedules` tab in `ApprovalInboxService`/`ApprovalInboxController` (visible to CID Chief/Administrator), so CID Chief approves/returns from the existing `/approvals` inbox like every other module — found and fixed a pre-existing route-name mismatch (`ClassScheduleApprovalService` pointed notifications at `approvals.index`, which is actually the unrelated Rewards approvals page; the correct general inbox route is `approvals.inbox`) while wiring the new module's own notification.
- Added a "Submit for Approval" button (San Miguel-only) + status banner + the shared `DigitalSignaturePin` modal, and a "Print Schedule" button, to `ITJobRequests/ComputerLabs/Index.vue`.
- Print signatories now pull the real submitter/approver name, position, signature image, and signed-at date from the approval record's digital signatures, falling back to role lookups before any submission exists.
- Added `tests/Feature/ComputerLabScheduleApprovalTest.php` (9 tests, 19 assertions) covering preparer/approver restrictions, double-submission rejection, schedule-drift rejection, return-for-revision, inbox visibility scoping, and end-to-end submit/approve HTTP routes.
- Verification: `ComputerLabSchedulingTest` (11/11) and the new approval test (9/9) both pass (20/20, 55 assertions combined); `npm run build` clean. Confirmed via `git stash` that a `ClassScheduleApprovalTest` failure (`no such table: rooms` in its isolated in-memory SQLite schema) pre-exists on the clean baseline and is not a regression from this work.
- Commit: `403aab96 feat(computer-labs): subject colors, formal print, approval workflow` on branch `junlou` (not yet merged/deployed to `main`).

## 2026-07-24 (cont'd) - My Work Schedule Pending Submission Detail + Deploy

- User asked for the "Pending Submission" view on My Work Schedule to show full per-day detail (not just a morning/afternoon summary), including Lunch Start/End — same request extended to HR's approval list for consistency.
- Root cause: `formatDaysWithTimes()` collapses days sharing identical time-in/time-out into one summary line and never reads `lunch_start`/`lunch_end` at all. The adjacent "Current Active Schedule" card already solved this correctly with a per-day grid; the Pending Submission card and HR's Pending Schedule Submissions list did not.
- Fix (`resources/js/Pages/HR/Schedules/MySchedule.vue`): Pending Submission card now renders the same per-day grid as Current Active Schedule (`sortedDailySchedules()`, day + WFH badge + Time In-Out + Lunch start-end when present).
- Fix (`resources/js/Pages/HR/Schedules/Index.vue`): added `DAY_ORDER`/`sortedDailySchedules()` helper (mirrors MySchedule.vue) and an `expandedSubmissionId` toggle — each pending submission row gets a "View/Hide daily details" button that expands into the same per-day grid with lunch. Kept collapsed by default since this list can have many rows.
- No backend changes — `EmployeeScheduleController::index()`/`::mySchedule()` already send the full `EmployeeSchedule` model with `daily_schedules` (lunch fields intact); this was a frontend display gap only.
- Verified via `npm run build` on host (npm not available inside the `php` container) — clean build, no errors.
- Commit: `3d4d21b5 fix(hr): show full daily schedule detail incl. lunch on pending submissions`.

### Deployment (both this fix and the earlier faculty print-sheet fix)

- `junlou` had fallen 5 commits behind `origin/main` (unrelated grading/enrollment merges from other work) — merged `origin/main` → `junlou` first (clean, no conflicts), then `junlou` → `main`, pushed `cc02731b..c2314b49`.
- GitHub Actions "CRCMIS Deploy" completed successfully in 20m35s.
- Verified `https://mis.crc.pshs.edu.ph` responding (302 → login) post-deploy.
- Deployed together: `a77da81b` (faculty print Flag/Homeroom bands, Recess exclusion) and `3d4d21b5` (this fix) — both presentation-only, no migrations.
- Returned to `junlou` branch after deployment, per standard workflow.

## 2026-07-24 (cont'd) - Class Record: Reversible Archive + Grading Enhancements

Note: the computer-lab commit `403aab96` referenced above as "not yet deployed" WAS subsequently merged to `main` and deployed successfully (GitHub Actions run `30066721192`).

### Class Record Reversible Archive (soft-delete)

- Replaced hard-delete with archive: CID/Administrator (`class-records.admin`) and the record owner (`teacher_id`) can archive a class record, gated by a signature PIN verified via `DigitalSignatureService::verifyPin` (rejects wrong/absent PIN with 422). `ClassRecordController::destroy` now sets `status='archived'` + `pre_archive_status`/`archived_at`/`archived_by_id` instead of deleting — never drops the row, so the cascade to `class_record_quarters`/`student_annual_grades` is avoided.
- Added `restore` action (owner/admin, confirm-only) returning the record to its `pre_archive_status`. Route `POST /class-records/{cr}/restore`.
- Additive migration widened the `class_records.status` enum to include `archived` and added `archived_at`, `archived_by_id`, `pre_archive_status`.
- Archived records excluded from active listings (Index page + JSON index default, honoring `?status=archived`), the teaching-load `already_created` flag, and Faculty/CID dashboard analytics (new `ClassRecord::scopeActive()`).
- Frontend `ClassRecord/Index.vue`: per-row Archive (opens shared `DigitalSignaturePin` modal) + Restore actions, a status filter incl. Archived, and an amber Archived badge.
- Tests: `tests/Feature/ClassRecord/ClassRecordArchiveTest.php` (8 tests) — archive/restore, PIN correctness, authorization, listing visibility.
- Commit: `1a46724d feat(class-records): reversible archive with PIN confirmation`. Deployed to `main` (GitHub Actions run `30066721192`, success).

### Class Record Sub-categories + Per-Quarter Grading Options (CID Chief request)

- **Sub-categories**: `grading_categories` gained a self-referential nullable `parent_id`. A category is a LEAF (carries weight + max_assessments, holds assessments) or a PARENT (organizational group; stored weight = sum of children). Chose **absolute leaf weights** (all leaves total 100%) so the pure `GradeComputationService`/`gradeUtils.js` engines are unchanged — callers just pass leaves via `GradingOption::leafCategories()`. Existing flat options are unchanged (all leaves).
- **Per-quarter applicability**: `grading_options.applicable_quarters` (JSON, null = all quarters) + `appliesToQuarter()` + `GradingOptionScopeService::selectableForQuarter()`. Create/edit UI has an "Applies to: All / Specific quarters" selector.
- **Per-quarter option on class records**: `class_record_quarters.grading_option_id` (nullable, BACKFILLED from each record's option). Resolution is `quarter.grading_option_id ?? class_record.grading_option_id` (coalesce keeps old code working during blue-green). New `PUT /class-records/{cr}/quarters/{q}/grading-option` (owner/admin), guarded: current SY, not locked, option active + applicable to that quarter, and **blocked when the quarter already has assessments** (must clear first — no silent orphaning). `grades`/Excel/PDF resolve per quarter and iterate leaves; assessments restricted to leaf categories; copy-from guards option mismatch.
- Frontend: `Index.vue` nested sub-category editor + applies-to-quarters selector (payload sends `children[]` + `applicable_quarters`); `Show.vue` per-quarter option banner + "Change Q{n} Grading Option" modal, Setup tab + `ScoreGrid.vue` render leaves (sub-categories labeled "Parent · Child"); `GradingOptionDetails.vue` leaf-aware with applicable-quarters line.
- All three migrations additive/blue-green-safe.
- Tests: `tests/Feature/ClassRecord/GradingSubcategoryQuarterTest.php` (8 tests, 23 assertions). Full `tests/Feature/ClassRecord/` suite 33/33 (106 assertions); `GradeComputationServiceTest` 7/7 (30 assertions) — engine integrity intact; `npm run build` clean. Unrelated aggregate `tests/Unit` failures (PPMP, leave CTO) and a PhpSpreadsheet 128MB memory fatal are pre-existing/environmental, not from this change.
- Commit: `9660f42a feat(class-records): sub-categories, per-quarter grading options`. Deployed to `main` (`1a46724d..9660f42a`, GitHub Actions run `30068566289`).

### Repository/Deploy notes

- Standard flow each time: stage feature files by name (excluding `.claude/*`, `.codex/memory.md`, `.kiro/*`), commit on `junlou`, ff-merge `junlou`→`main`, push, return to `junlou`.
- Migrations run as the pre-deploy one-off task; all migrations this session were additive so blue/green traffic stayed safe during rollout.

## 2026-07-24 (cont'd) - Dashboard + DTR follow-up fixes

### Faculty dashboard: non-teaching leak + archived class records
- Feedback: archived class records appeared on the faculty dashboard, and non-teaching schedule blocks showed in the "Classes Today" / "Today's Classes" cards.
- Non-teaching fix (real bug): `FacultyDashboardService` added `->classes()` (ClassSchedule scope `entry_type='class'`) to both the `classes_today` count and the `todaySchedule()` list.
- Archived: the class-records panel (`classRecords`, cards, isFaculty) and CidDashboardController already excluded archived via `ClassRecord::scopeActive()` (shipped earlier), and `PersonalDashboardService` doesn't query class records — so there was no unfiltered source; the report was a stale/pre-refresh observation. Hardened `scopeActive()` from blacklist (`status <> 'archived'`) to whitelist (`whereIn('status',['draft','submitted','checked'])`) as defense against status/enum drift.
- Production diagnostic (read-only ECS exec): `class_records` status distribution `{draft:91, archived:14, submitted:12}` — 14 archived rows correctly carry `status='archived'` (dev/prod MySQL is strict mode, so blank/invalid status can't be stored). No data repair needed.
- Tests: `tests/Feature/FacultyDashboardServiceTest.php` (non-teaching excluded; archived excluded; card links to Inertia page route).
- Commit: `6280f846 fix(dashboard): exclude non-teaching from faculty today cards; harden archived scope` (deploy run 30071751685, success).

### Class record card link (JSON) + category-edit name-required regression
- Bug 1: the dashboard "My Class Records" card linked to the JSON API route `class-records.show` (returns JSON), showing raw JSON on click. Fixed `FacultyDashboardService` to use the Inertia page route `class-records.page.show`. (`class-records.show` = `/api/v1/class-records/{id}` JSON; `class-records.page.show` = `/class-records/{id}` Inertia page.)
- Bug 2 (regression from `9660f42a`): `GradingOptionController::updateCategories` reused the store payload validator which required the option `name`; the edit flow posts only `{ categories }` to `grading-options.categories.update`, so it 422'd with "The name field is required" and blocked all category edits. Split validation: `store` validates option meta + categories; `updateCategories` calls a new `validateCategoriesPayload()` (categories only, no name).
- Tests: "update categories succeeds without option name" + "class record card links to inertia page not json api".
- Commit: `c0f82a66 fix(class-records): dashboard card link + category-edit name-required regression` (deploy run 30078177086, success).

### DTR "Verified as to prescribed office hours" — COS teachers → Academic Unit Head
- Feedback: COS Teachers' DTR verifier printed as the CID Chief; it should be their Academic Unit Head.
- Root cause: `DtrRecordController::resolveSupervisor` returned the employee's division chief; teachers are in the CID whose chief is the CID Chief.
- Fix: for `emp_category === 'COS Teaching'`, resolve the AUH via `IPCRWorkflowService::academicUnitHeadFor` (made public) — explicit `users.academic_unit_id` → Data Management office link (`offices.unit_head` in CID division) → dominant current-SY teaching-subject unit; fall back to the existing division-chief/OCD chain when no unit resolves. Non-COS unchanged. Fixes both `printCsc` and `printBatch` (both call `resolveSupervisor`). Scope intentionally limited to COS Teaching; label = AUH's `position` (fallback "Academic Unit Head").
- Tests: `tests/Feature/HR/DtrSupervisorSignatoryTest.php` (3): COS→AUH, COS fallback→division chief, non-COS unchanged (36 assertions). Note the print route name is `hr.dtr.print` (group-prefixed).
- Commit: `1300a339 fix(dtr): COS teachers verified by their Academic Unit Head` (deploy run 30079884744).

### Dev environment note
- An interrupted `migrate:fresh` left the dev `bugsaymis` DB half-migrated, producing non-deterministic InnoDB errors (errno 1824 "failed to open referenced table" / 1146 table-not-found on FK creation) across different migrations per run. Root cause was an unhealthy MySQL container, NOT the migrations or feature code. Recovery: `docker compose restart mysql` then `php artisan migrate:fresh --force` completed cleanly. Production untouched. If migrate:fresh fails non-deterministically at table/FK creation, restart the mysql container first.

## 2026-07-24 (cont'd) - Weekly Assessment Tracker (WAT) Print Redesign + Designation-Based Access Fix

- User requested WAT print sheet redesign to match Class Schedule's print conventions, plus flagged that Homeroom Coordinator is NOT `Section::adviser` (legacy column) — it's a separate Designations-based role, and Homeroom Coordinators need proper WAT access/permissions.
- Root cause of the access gap: `WeeklyAssessmentTrackerController` used the legacy `Section::adviser` column and its own hand-rolled `accessibleSections()` logic (sections taught or advised via that column) — never checked Designations. Combined with the `class-records.view` permission gate (granted only to Faculty/Staff/CID Chief roles), a designation-only Homeroom Coordinator with no teaching load and none of those roles got a flat 403 on WAT.
- Fix: rewired access through `AdvisoryScheduleScopeService` (the same service Faculty Loading already uses) — `sectionIds()` resolves a coordinator's accessible sections from HR_ADV/HR_ACAD designations (section-specific or grade-range via `COORD-HRG7&8`-style codes), `adviserNamesBySection()` resolves the coordinator's name for the print signatory. Per user's explicit call: subject teachers lost WAT access entirely (coordinator/reviewer-only now, not a function of holding a teaching load).
- Print redesign (`Wat/Print.vue`): rewritten to match `SchedulePrintSheet.vue`'s conventions — landscape A4, `@page margin:0` with full-bleed header/footer images (`report_header_landscape.jpg`/`report_footer_landscape.jpg`) instead of portrait assets with page margins, compact 13pt heading, left-aligned section/week/SY meta (was centered), added a Time column, removed the weekly totals footer, renamed "Type" header to "Type of Assessment", bolded Title, and replaced the 2-column unlabeled signatories with 3 captioned columns: Consolidated by (Homeroom Coordinator) / Reviewed by (ACIDAA) / Approved by (CID Chief, newly added — reused the `User::whereHas('roles', ...'CID Chief')` pattern from `ClassScheduleController::printSharedSignatories()`).
- Time column required backend work since `class_record_assessments` has no time-of-day column — added `time_label` to `WatRuleService::weekData()` items by batch-joining `ClassSchedule` on `section_id`+`subject_id`+day-of-week (derived from `activity_date`), null/"—" when unresolvable (no `subject_id` or no matching schedule row).
- Added `tests/Feature/ClassRecord/WeeklyAssessmentTrackerTest.php` — 9 tests: designation-based access (section-specific + grade-range), subject-teacher-denied, cross-designation-denied, admin-override, coordinator-name-from-designation (not adviser column), CID Chief signatory, time_label present/null.
- **Key pattern learned:** this dev container's shared MySQL instance has flaky `RefreshDatabase` migration races (deadlocks / "table already exists") when test files are run back-to-back without letting the prior run's drop/recreate cycle fully finish (~300 migrations, takes 60-90s to settle). Confirmed via control test (ran an unrelated, unmodified, already-passing test file — got the identical flakiness) that this is pure dev-infra flakiness, not a real defect. All 9 new tests pass cleanly once MySQL is verified idle via `mysqladmin processlist` first.
- Commit: `ae36fb6d feat(class-record): redesign WAT print, fix Homeroom Coordinator access`.

### Deployment

- `junlou` was in sync with `origin/main` (0 behind) — straightforward merge + push, no migrations in this commit (access-control + presentation-layer only).
- Pushed `1300a339..ae36fb6d`. GitHub Actions "CRCMIS Deploy" completed successfully in 20m7s.
- Verified `https://mis.crc.pshs.edu.ph` responding (302 → login) post-deploy.
- **Flagged to user (not yet confirmed/resolved):** any Homeroom Coordinators who previously had WAT access purely via the legacy `Section::adviser` column (without an actual HR_ADV/HR_ACAD Designation record) will lose access until their Designation is properly set up in production. Worth checking prod data for this scenario if any coordinator reports a sudden 403.

### Deploy incident: DTR deploy reported "failed" but actually succeeded (superseded during bake)

- The DTR deploy (GitHub Actions run `30079884744`, image `1300a339`) was reported as **failed**, but it did not error. `describe-service-deployments` showed `deploymentCircuitBreaker.failureCount: 0`, `alarms.triggeredAlarmNames: []`, and the green revision reached `runningTaskCount: 2` at `productionTrafficWeight: 100.0` — it passed bake cleanly. Its `statusReason` was literally **"Replaced by a new service deployment."**
- Root cause: a concurrent push to `main` — `ae36fb6d` "feat(class-record): redesign WAT print, fix Homeroom Coordinator access" (other work) — landed during the DTR deploy's ~10-minute bake window and started a new deployment. ECS marked the in-flight DTR deployment `STOPPED` (superseded); the workflow's "Wait for blue/green deployment to finish" step treats `STOPPED` as failure and exits 1. So it's a CI false-negative, not a code/health failure.
- Confirmed the DTR fix reached production: task-def `crcmis-prod:660` = image `1300a339` (DTR) served production; task-def `:661` = `ae36fb6d` (WAT, which sits on top of the DTR commit in main so it includes the DTR fix) then baked and took over. Either revision keeps the DTR fix live. The WAT run `30080338489` subsequently completed successfully.
- **Lesson / operational note:** serialize deploys — do NOT push to `main` while another deploy is within its ~10-minute bake window. The second push supersedes the first, and CI reports the superseded (but healthy) deploy as "failed". When a deploy is reported failed, verify with `aws ecs describe-service-deployments` (check circuit breaker `failureCount`, `triggeredAlarmNames`, and whether `statusReason` is "Replaced by a new service deployment") before assuming a real failure.

## 2026-07-30 - Class Record: Scoped Assessment Deletion Approval to the Scheduled Week

- Loosened the Class Record Setup tab's plotted-assessment deletion rule: ACIDAA approval (Request Deletion workflow) is now only enforced when the deletion happens within the assessment's scheduled week (Mon–Sun of `activity_date`). Outside that window, direct deletion is allowed, same as an unplotted row. Has-scores block stays unconditional.
- Added `WatRuleService::isWithinScheduledWeek()` (backend) and a matching `resources/js/Utils/ClassRecord/watUtils.js` mirror (frontend), following the existing `gradeUtils.js` mirror-service convention.
- Updated `ClassRecordAssessmentController`'s bulk-save deletion gate and `Show.vue`'s `removeAssessmentRow()` to use the new week check.
- Updated/added tests in `ClassRecordAssessmentDeletionTest` (12/12 pass); regression-checked `WeeklyAssessmentTrackerTest` + `QuarterExamWindowTest` (29/29 pass).
- Commit: `ed60fb71 feat(class-record): only require ACIDAA approval to delete plotted assessments within their scheduled week`. Merged `junlou` → `main` (`d880a9f1..e14ea1ef`), deployed via GitHub Actions to ECS (blue/green, no migration).

## 2026-07-30 (cont'd) - Students Module: Active/Inactive Tabs, Section Column, Search Fix, Filters

- User request: Students datatable should only show currently-enrolled students (or split active/inactive into tabs), add a Section column for active students, fix the search (it wasn't actually working — see root cause below), add filter dropdowns for section/grade level/sex.
- **Root cause of the original "enrolled only" filter silently doing nothing:** `StudentController::index` guessed at a status column name (`status`/`student_status`/`enrollment_status`/etc.) directly on the legacy `students` table. None of those candidates represent real enrollment state — the actual source of truth is the `student_enrollments` table (`school_year_id`, `section_id`, `grade_level`, `status` enum: `enrolled/dropped/transferred_out/on_leave/completed`), which the controller never joined at all. So the "only show enrolled" logic was a no-op; every student showed regardless of enrollment status, and section/grade weren't queryable at all since they only exist on the enrollment row.
- **Design decision (user-approved):** "Inactive" = any student with no `enrolled` row for the current school year — this includes students who were dropped/on_leave/transferred/completed AND students who were never enrolled in any year at all (single broad catch-all bucket, not split further).
- **Fix — `StudentController::index` rewritten:**
  - LEFT JOINs `student_enrollments AS se` scoped to the current school year (`SchoolYear::where('is_current', true)`) + `sections AS sec` on `se.section_id`.
  - `tab=active|inactive` (default `active`): active = `se.status = 'enrolled'`; inactive = `se.id IS NULL OR se.status <> 'enrolled'`.
  - Search rewritten from a "guess columns via `SHOW COLUMNS` + varchar/text fallback" heuristic to an explicit, known-good column list (`SEARCHABLE_COLUMNS` const: lastname, firstname, middlename, nickname, student_email, lrn, pisaysystemID, sex, contactperson, contactno1) — the old fallback path was silently searching arbitrary varchar columns and was fragile/unpredictable.
  - New `section_id`/`grade_level`/`sex` filters, AND-combined with search and tab.
  - Returns `tab_counts` (for the tab badges), `section_options`/`grade_options` (scoped to the current SY's sections via `sections.syid`) for the filter dropdowns.
  - Page size bumped 10 → 30 (user's explicit choice).
- **Frontend (`Students/Index.vue`):** added `AppTabs` (Active/Inactive with live counts embedded in the label, matching the project's existing tab-count convention rather than a separate badge prop AppTabs doesn't support), `AppSelect` filter dropdowns for section/grade/sex, a new "Grade & Section" column shown only on the Active tab (inactive students have no current-SY section to show).
- **Search UX follow-up (separate user request, same session):** initial implementation had search auto-firing on every keystroke via a 400ms-debounced `watch(searchQuery, ...)`. User explicitly asked to disable that — search should only run on button click or Enter. Removed the debounce watcher entirely; `searchQuery` `v-model` now only updates local state, and `performSearch()` (wired to the Search button `@click` and input `@keydown.enter`) is the sole trigger. Tab/filter-dropdown changes still auto-apply immediately (unaffected — user's complaint was specifically about the text search).
- **Tests:** new `tests/Feature/StudentIndexTest.php` (5 tests / 16 assertions) — active tab excludes dropped/never-enrolled/past-SY-only students; inactive tab includes never-enrolled + all non-enrolled statuses; search matches partial lastname and exact LRN; section+grade+sex filters combine with AND logic; page size is 30. Regression-checked `StudentAttendance` (CameraKioskTest, GuardDirectoryTest, MobileParentRegistrationTest, SelfScanKioskTest) + `StudentProfileServiceTest` — 33/33 passing.
- **Verification environment note:** dev DB and the one reachable prod ECS task both had 0 rows / ECS Exec disabled respectively when checked (`InvalidParameterException: execute command was not enabled when the task was run`) — could not get an exact prod student-row-count for capacity planning. Defaulted to keeping server-side pagination/filtering (not the `DocumentTracking.vue`-style client-side-load-everything pattern) since student history across school years will grow indefinitely.
- **Commits:** `8ba68ca7` (tabs/section/search-fix/filters feature, cherry-picked from `fix/registrar-menu-role-gate` onto `main` directly since that branch also carried an already-deployed duplicate of an unrelated commit), `76f83dd8` (search-on-keystroke → search-on-submit-only fix, same cherry-pick pattern).
- **Deploy:** both commits pushed straight to `main` (not through `junlou`) via `git checkout main && git merge --ff-only <temp-branch> && git push`, since the working branch (`fix/registrar-menu-role-gate`) wasn't the normal `junlou` flow. First deploy (`8ba68ca7`, run `30515833456`) completed in 20m23s, confirmed ECS task-def `:709` PRIMARY/COMPLETED. Second deploy (`76f83dd8` folded into a later `junlou`→`main` merge commit `9f25ffe1` from a parallel session, run `30517684644`) completed in 7m6s, ECS task-def `:710`. Verified via `git log origin/main..HEAD` (empty) that no local commit was left undeployed after the parallel-session merge.

## 2026-07-30 - Weekly Assessment Tracker: Archived Class Records Leaking Into WAT

- **Problem reported by user:** archiving a class record did not remove its plotted assessments from the Weekly Assessment Tracker — the teacher creates a fresh record to replace the archived one, and the old assessments kept showing up redundantly in prod.
- **Root cause:** `ClassRecordAssessment::schoolYearScopeQuery()` — the single shared query builder joining assessments up to `class_records`, filtered only by `school_year_id` — never excluded `status = 'archived'`. Every other class-record listing in the module guards with `where('status', '<>', 'archived')` or `ClassRecord::scopeActive()`, but this one didn't.
- **Blast radius (all confirmed callers of the scope query):** `WatRuleService::weekData()` (tracker grid + PDF print), `gradeCountsOnDate()`/`gradeCountsInWeek()` (daily/weekly cap enforcement — archived assessments were wrongly consuming the section's plotting budget), `WeeklyAssessmentTrackerController::review()` (ACIDAA review tallies + teacher breakdown), `CidDashboardController` (`sectionsAtMax`), `FacultyDashboardService` (`assessmentsThisWeek`).
- **Fix:**
  - Added `->where('cr.status', '<>', 'archived')` to `schoolYearScopeQuery()` — fixes every caller above at once.
  - `WatRuleService::weekData()`'s non-graded ILA-dates query bypasses the scope query via its own `whereHas('quarter.classRecord', ...)` — added the same archived guard there so pending ILA rows from an archived record also disappear.
  - Restoring a record (`ClassRecordController::restore()` flips `status` back to `pre_archive_status`) automatically brings its assessments back — no special-casing needed.
- **Tests:** 3 new tests in `WeeklyAssessmentTrackerTest.php` — `weekData()` excludes an archived record's assessment, the daily/weekly cap ignores an archived record even when it's already at the cap, and the ACIDAA review summary count excludes it. Full suite 30/30 passed, 169 assertions, no regressions to the pre-existing sibling-homeroom/exam-window/Science-Core-pooling logic.
- **Commit:** `133f543e` on `junlou`, merged into `main` (`aa0bd822`), pushed — GitHub Actions "CRCMIS Deploy" run `30519022598` triggered, in progress at end of session (blue/green deploys take ~10+ min; verify with `gh run view 30519022598` or `aws ecs describe-services` before assuming it's live).
- **Follow-up flagged, not yet started:** user reported a new Faculty Loading → Classroom module room cannot generate its URL/print in production — analysis not yet done as of this memory update.

## 2026-07-30 (cont'd) - WAT Follow-up: section-calendar Endpoint Also Leaked Archived Records

- **Problem reported by user:** in production, Grade 10 "Electron," the CID Chief could not plot a new assessment for July 31 — got "Section already has 3 graded assessments on 2026-07-31 — pick another date" even though only 2 were actually plotted.
- **Investigation (read-only ECS exec into the nginx task, `env HOME=/tmp php /var/www/artisan tinker`):** confirmed via direct production queries that the *backend* upsert cap check (`WatRuleService::gradeCountsOnDate`/`gradeCountsInWeek`, already fixed this session) correctly saw only 1 active assessment that day and would have allowed 2 more. The "3 already" message is a **client-side-only** string (`Show.vue`'s `onDateChange()`), not the server's 422 — traced by grepping the exact wording, which only existed in the Vue file, not any controller.
- **Root cause:** `ClassRecordAssessmentController::sectionCalendar()` (`GET /class-records/{cr}/section-calendar`, feeds `Show.vue`'s pre-check) used its own hand-rolled query — never routed through `ClassRecordAssessment::schoolYearScopeQuery()` — so it predated and bypassed the archived-record fix from earlier today, AND never pooled Science Core/Elective synthetic sections the way `WatRuleService::weekData()`/the backend cap check do. Production data showed section 268 (Electron) had 2 **archived** class records' assessments plus 1 active one on July 31 — the calendar counted all 3, and the frontend's own draft-row bump pushed the client-side total to 4, tripping `counts.graded > WAT.dailyGraded` (4 > 3) and reverting the date pick.
- **Fix:** rewrote `sectionCalendar()` to go through `schoolYearScopeQuery()` (excludes archived) + `WatRuleService::poolSectionIds()` (pools SCI-/ELEC- synthetic sections), matching every other WAT query exactly. Response shape unchanged — zero Vue changes needed.
- **Tests:** new `ClassRecordAssessmentSectionCalendarTest` (3 tests: archived exclusion, Science Core pooling, sibling-homeroom non-pooling) + reran `ClassRecordAssessmentControllerTest`/`WeeklyAssessmentTrackerTest` — 44/44 passed, 220 assertions.
- **Commit:** `0f9285cc` on `junlou` → merged to `main` (`ecf01508`) → pushed, GitHub Actions "CRCMIS Deploy" run `30522553580` completed successfully.
- **Deploy confirmed live** via `aws ecs describe-services`: task definition `crcmis-prod:712`, deployment `rolloutState: COMPLETED` at 2026-07-30T15:40:47+08:00 (both today's WAT fixes — the morning's `schoolYearScopeQuery()` archived-exclusion and this afternoon's `sectionCalendar()` fix — are in this same revision, since they were two separate merge-to-main pushes, `:707`-ish then `:712`; the earlier one was already confirmed live before this one shipped).
- **Pattern reinforced:** "fix the shared query helper" does not guarantee every caller is covered — always grep for *other* hand-rolled queries joining the same tables before declaring a class-record/archived-status bug fully fixed. `sectionCalendar()` was missed in the morning's fix specifically because it never called `schoolYearScopeQuery()` in the first place; it had its own independent join.
- **Still open:** user separately reported Faculty Loading → Classroom module — a newly added room's URL/print generation fails in production — not yet investigated.

## 2026-07-30 (cont'd) - Homeroom Attendance Monthly Report: Boys/Girls Grouping Never Matched

- **User request:** group the Homeroom Attendance Monthly Report (Record on Attendance and Punctuality) by sex — Boys = Male, Girls = Female.
- **Bug found while investigating:** both `MonthlyReportPdfService::render()` and `Show.vue` grouped students with `sex === 'M'` → Boys, `sex !== 'M'` → Girls (an "everyone else" fallback bucket). But `students.sex` actually stores the full words `Male`/`Female` (confirmed via `EnrollmentApplicationController`'s `'sex' => 'required|in:Male,Female'` validation and matching test fixtures) — so `=== 'M'` never matched anything and every student was silently falling into the Girls bucket in both the on-screen table and the printed PDF.
- **Fix:** changed both call sites to explicit equality both ways — `strtoupper($sex) === 'MALE'` for Boys, `=== 'FEMALE'` for Girls — removing the "everyone else" fallback so an unexpected/blank value doesn't get silently miscategorized either.
  - `app/Services/HomeroomAttendance/MonthlyReportPdfService.php` (PDF boys/girls split).
  - `resources/js/Pages/HomeroomAttendance/MonthlyReport/Show.vue` (on-screen table boys/girls split).
- No DB/migration or Blade-template changes needed — `sex` was already exposed via `MonthlyReportController::present()` and the blade already iterated `['BOYS' => $boys, 'GIRLS' => $girls]`; only the upstream filter predicate was wrong.
- **Verification:** `php -l` clean on the PHP file; `php artisan test tests/Feature/HomeroomAttendance` (5/5 passed, `CutClassIntegrationTest`) — no dedicated test existed for the sex-grouping logic itself, so this was a silent bug fixed without disturbing existing coverage.
- **Commit:** `982fe560 fix: correct sex grouping (Male/Female) in homeroom monthly report` on `junlou`. Staged only the two fixed files by name (excluded unrelated locally-modified `.claude/*`/`.codex/memory.md` and untracked `.kiro/session-*.md`, per convention).
- **Not yet deployed as of this memory update** — merge to `main` + push was proposed and pending explicit user confirmation (production deploy is a flagged high-risk action per this repo's safety convention).

## 2026-07-30 (cont'd) - Class Record Attendance: Cut Class (CC) Status, CIM 3.6/3.6.2

- **User request:** add a "CC - Cut Class" option to Class Record Attendance and wire it to Homeroom Daily Attendance. Investigation surfaced a deliberate existing design (from the very-recent `2026_07_28_161100_expand_class_record_attendance_records_for_homeroom_sync` migration) where cutting was purely *derived* — never a status anyone picks — by cross-referencing a subject's Absent record against the same date's Homeroom Present/Tardy record. Flagged this conflict before building; user then cited CIM 3.6/3.6.2 ("cut class" = student on campus but skips/leaves a specific period without valid reason) — **only the subject teacher can witness this**, the Homeroom Adviser cannot. That reframed the design: CC needed to be a real, teacher-asserted status, not purely inferred.
- **User's three approval decisions:** (1) keep the existing mismatch-based detection as a secondary safety net alongside the new direct signal — do not retire it; (2) the Monthly Report already has a `cutting_count` column — feed both signals into it; (3) orange color for the CC badge.
- **Implementation:**
  - `cut_class` re-activated as a real, teacher-settable value in `class_record_attendance_records.status` (the enum value already existed from the prior migration but was being immediately remapped to `absent` — no-op documentation migration `2026_07_30_160000` records the re-activation). Only reachable via Class Record Attendance (`ClassRecordAttendanceController::upsert()` validation widened); **never** added to `homeroom_attendance_records.status` — the adviser's vocabulary stays present/absent/tardy only, by design.
  - `AttendanceGrid.vue`: CC added to the cycle (P→A→T→CC), orange badge (`bg-orange-100 text-orange-700`), new "Cuts" tally column, updated legend explaining CIM 3.6/3.6.2 + the kept safety net.
  - `Daily.vue` (Homeroom): **read-only** orange CC badge next to a student's name if any subject flagged them that day — `DailyAttendanceController::index()` queries `class_record_attendance_records` for `status='cut_class'` that date/section, resolving subject name via `COALESCE(cad.subject_id, cr.subject_id)` to handle both normal and shared PEHM records. Adviser cannot set/clear it.
  - `AdmissionSlipService`: added `pendingAssertedCutClass()` (direct signal, `infraction_type='cut_class'`) alongside the renamed `pendingSuspectedCuttingInstances()` (the pre-existing mismatch signal, relabeled `infraction_type='cutting_suspected'` so the Registrar can tell them apart — legacy `'cutting'` value kept in the enum only for historical rows, no code writes it anymore). `issue()` got a matching `cut_class` branch. Added `AdmissionSlip::INFRACTION_TYPE_LABELS` + `infraction_type_label` accessor, used by the PDF blade and the Registrar's queue Vue page instead of a raw `ucfirst()`.
  - `MonthlyReportService::cuttingCountsForMonth()` rewritten to union both signals (`assertedRows` + `suspectedRows`) deduplicated by the underlying `class_record_attendance_records.id` via `fromSub(...)->union(...)` + `COUNT(DISTINCT car_id)` — confirmed the two are already mutually exclusive per-row (a row's status is either `cut_class` or `absent`, never both) so double-counting isn't structurally possible, the DISTINCT is defensive.
  - Migrations: `2026_07_30_160000` (no-op documentation, `cut_class` enum value already existed) and `2026_07_30_160100` (widens `class_admission_slips.infraction_type` to add `cut_class`/`cutting_suspected`, keeps legacy `cutting`) — both additive/blue-green-safe.
- **Tests:** extended `ClassRecordAttendanceControllerTest` (+2: mark cut_class accepted, invalid status rejected) and new `tests/Feature/HomeroomAttendance/CutClassIntegrationTest.php` (5 tests covering `pending()` surfacing both signals distinctly, no double-appearance when both conditions coincide on one row, `issue()` resolving a cut_class slip, and the Monthly Report union not double-counting). `Student` model is deliberately read-only (`$guarded=['*']`) — tests insert via `DB::table('students')->insertGetId()`, not `Student::create()`.
- **Verification:** ran `tests/Feature/ClassRecord` + `tests/Feature/HomeroomAttendance` together via direct `vendor/bin/phpunit` (container's default 128M memory_limit OOMs `artisan test` on this combined scope regardless of a CLI `-d` flag, since `artisan test` shells to a subprocess that doesn't inherit it — pre-existing environment constraint, worked around with `php -d memory_limit=1024M vendor/bin/phpunit` directly) — 185 tests, 660 assertions, 1 pre-existing unrelated failure (`ClassRecordMonitorTest::cid_chief_can_view_scores_attendance_and_ila_endpoints`, a 403-vs-200 permission check in a file untouched by this work, confirmed via `git status`). Migrations applied cleanly to dev DB and verified via `SHOW COLUMNS`.
- **Commit:** `f14497a7 feat: add Cut Class status to Class Record Attendance (CIM 3.6/3.6.2)` on `junlou`, staged by file name (excluded `.claude/*`, `.codex/memory.md`, `.kiro/session-*.md` per convention).

### Deployment (this commit + 2 other pending fixes, all pushed together per explicit user instruction "deploy all commits that are not yet deployed")

- `junlou` was 3 commits ahead of `origin/junlou`: `133f543e` (WAT archived-records exclusion) and `0f9285cc` (section-calendar archived/pooling fix) — both already-committed from earlier the same day but not yet pushed — plus this session's `f14497a7` (Cut Class).
- Pushed `junlou` (`c52d5014..f14497a7`), merged `junlou`→`main` (`ecf01508..a2993075`), pushed `main` — GitHub Actions "CRCMIS Deploy" run `30528285878` completed successfully (21m13s). Returned to `junlou` branch after, per convention.
- **Note:** `982fe560` (fix: correct sex grouping in homeroom monthly report, from a separate earlier session) was sitting on `junlou` at the time of this deploy but was committed *after* the `junlou` push point being deployed — confirmed via `git log origin/main..HEAD` that it is **still local-only, not yet deployed** as of this memory update. Next deploy should include it unless explicitly told otherwise.

## 2026-07-31 - Class Record Attendance: Auto-Present on Date Add, Incomplete Uniform (IU) Flag, Subject Submission Panel

- **User request:** (1) adding a date on Class Record Attendance should immediately mark everyone Present in the DB, not just show it as a UI default; (2) bring back per-subject Uniform checking, auto-saved to DB; (3) wire it to Homeroom Daily Attendance so the Homeroom Adviser can see which subject teachers submitted attendance that day and which didn't; (4) a subject flagging a student IU should auto-check the existing Homeroom "Inc. Uniform" checkbox.
- **Scoping correction (user-approved):** initially proposed IU as a 5th status value in the Present/Absent/Tardy/Cut-Class cycle (mirroring Cut Class). User corrected this — IU must be an **independent boolean flag**, not a status, and Homeroom's own status vocabulary (present/absent/tardy) must stay untouched. Re-planned and got explicit approval before implementing.
- **(A) Add-date auto-present bug:** `ClassRecordAttendanceController::storeDates()` previously only created the `class_record_attendance_dates` row — the grid's "P" was a pure UI fallback (`effectiveStatus()` defaults to `'present'` when no DB row exists), so nothing was actually persisted until the teacher clicked "Save Attendance". Fixed by bulk-inserting explicit `present` rows into `class_record_attendance_records` for every active `ClassRecordStudent` in that quarter/subject scope at date-creation time, without overwriting any row that already exists (idempotent on re-add).
- **(B) IU flag (not a status):** new `incomplete_uniform` boolean column on `class_record_attendance_records` (separate from the legacy, still-dead `uniform_status` column) via additive migration `2026_07_31_050000`. `AttendanceGrid.vue` gets an independent per-cell checkbox (`toggleIncompleteUniform()`) alongside — not inside — the P/A/T/CC status cycle, so a student can be Present AND flagged IU the same day. New "IU" tally column. Controller (`upsert`/`index`) validates, persists, and returns the flag.
- **(C) One-directional sync to Homeroom:** new `SubjectAttendanceSyncService::syncIncompleteUniform($studentId, $date, $triggeredByUserId)` — creates the Homeroom `AttendanceDate`/`AttendanceRecord` if the adviser hasn't logged that day yet (status defaults to `present`; `taken_by` set to the triggering subject teacher since that FK column is `NOT NULL`), and only ever sets the existing `incomplete_uniform` flag to `true` — never clears a box the adviser or an earlier sync already checked. Resolves the student's current-SY section via `StudentEnrollment::active()`, no-ops if none found. `Daily.vue` shows a read-only yellow badge naming which subject(s) flagged it; the checkbox itself stays adviser-editable.
- **(D) Subject submission panel:** new `DailyAttendanceService::subjectSubmissionStatus($sectionId, $date)` — cross-references every subject actually scheduled that day-of-week (`ClassSchedule::classes()->onDay()`) against whether `class_record_attendance_records` has any saved row for that date/subject (via the same `COALESCE(cad.subject_id, cr.subject_id)` join pattern the existing Cut Class query already uses). Purely informational, no adviser action — surfaced in `Daily.vue` as a green/amber "Subject Attendance Status" panel (X / Y submitted today).
- **Design consistency kept from the 2026-07-30 Cut Class precedent:** non-clobbering sync direction, `COALESCE(cad.subject_id, cr.subject_id)` join for PEHM-shared-record compatibility, badge-not-editable-field pattern for cross-module visibility.
- **Tests:** new `tests/Feature/HomeroomAttendance/IncompleteUniformSyncTest.php` (8 tests, 22 assertions) — add-date persists explicit present rows without duplicating/overwriting existing ones; IU sync creates the Homeroom date/record when missing; IU sync never flips an already-true flag back to false and leaves the adviser's own status alone; IU sync no-ops for a student with no current enrollment; subject-submission panel correctly classifies submitted vs not-yet-submitted subjects and returns empty when nothing's scheduled that day.
- **Regression:** full `tests/Feature/ClassRecord` + `tests/Feature/HomeroomAttendance` run together — 201 tests, 733 assertions, 1 pre-existing unrelated failure (`ClassRecordMonitorTest::test_cid_chief_can_view_scores_attendance_and_ila_endpoints`, 403 vs 200) reconfirmed via `git diff --stat` showing zero changes to that test file or its controller/service — matches the same pre-existing failure already documented in the 2026-07-30 Cut Class session. `npm run build` clean.
- **Dev environment note (reinforces the existing flaky-migration pattern):** while running my own targeted migration, the shared dev MySQL instance showed a genuinely different, longer-running concurrent process — a parallel session's `artisan test` (`WeeklyAssessmentTrackerMyTrackerTest.php`) using `RefreshDatabase`, which drops/recreates the ~700-migration schema per run and caused `migrate:status` to flap between 0 and 600+ pending and `class_record_attendance_records` to intermittently "not exist" for several minutes. Confirmed via `/proc/*/cmdline` inspection inside the `php` container (no `ps` binary available) rather than assuming a stuck/broken `migrate:fresh`. Waited for a settled 0-pending window rather than restarting the MySQL container (would have killed the other session's in-progress work); my own migration and tests both verified correct once settled. Left that other session's unrelated dirty files (`WeeklyAssessmentTrackerController.php`, `WatRuleService.php`, `MyTracker.vue`, `watUtils.js`, `web.php`, `navigation.js`, `SectionAssessmentCalendar.vue`, `Show.vue`, `WeeklyAssessmentTrackerMyTrackerTest.php`) untouched and unstaged.
- **Commit:** `ead50a2b feat(class-record): auto-present on date add, IU flag synced to Homeroom, subject submission panel` on `junlou` — 9 files staged by name (`ClassRecordAttendanceController.php`, `DailyAttendanceController.php`, `ClassRecordAttendanceRecord.php`, `DailyAttendanceService.php`, `SubjectAttendanceSyncService.php`, `AttendanceGrid.vue`, `Daily.vue`, the new migration, the new test file).
- **Not yet deployed as of this memory update** — user has not yet asked to merge to `main`/push; per convention, deploy only on explicit instruction.
