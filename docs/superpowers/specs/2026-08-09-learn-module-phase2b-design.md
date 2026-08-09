# Learn Module (LMS) — Phase 2b: Push Grades to Class Record — Design

Date: 2026-08-09
Status: Approved, ready for implementation plan

## Problem

Phase 2 shipped Learn assignments, submissions, and grading (points or rubric) as a
self-contained gradebook inside Learn — deliberately disconnected from Class Record, the
existing gradebook of record. Instructors now need a way to get a graded Learn assignment's
scores into Class Record without re-typing them by hand. This spec covers Phase 2b of the
roadmap: a manual, one-way "push" from a Learn assignment into a specific, pre-existing Class
Record assessment.

## Program roadmap (reference only — not this spec)

| Phase | Scope | Depends on |
|---|---|---|
| 1 (shipped) | Course shell: courses, Modules, Pages, Files, syllabus, announcements | Faculty Loading, `student_enrollments` |
| 2 (shipped) | Assignments, submissions, points/rubric grading | Phase 1 |
| **2b (this spec)** | Push a graded assignment's scores into Class Record | Phase 2 |
| 2c | Reusable/cross-course rubric bank | Phase 2 |
| 3 | Quizzes/assessment engine | Phase 1 |
| 4 | Discussions/forums | Phase 1 |

## Key decisions (resolved during brainstorming, not open questions)

1. **Learn never creates Class Record assessments.** The instructor creates the target
   `ClassRecordAssessment` in Class Record themselves, the normal way, then links a Learn
   assignment to it. This keeps Learn from writing into Class Record's category/weighting
   structure it doesn't own, and avoids Learn needing to understand grading-category weight
   rules at all.
2. **Linking is instructor-picked from a dropdown, never title-matched.** The link screen
   resolves candidate Class Records by `(subject_id, section_id, school_year_id)` — handling
   sibling records like a STEM Research "Ongoing"/"Completed" split — and lists each one's
   quarters → assessments for the instructor to pick from directly. No fuzzy string matching.
3. **Max scores must match exactly before a link is allowed.** The Learn assignment's
   `maxScore()` (flat `points_possible` or rubric total) and the target assessment's
   `max_score` are set independently by different people at different times; requiring an
   exact match keeps a push a straight 1:1 score copy with no scaling math and no ambiguity
   about what the pushed number means. If they don't match, linking is rejected (422) until
   one side is edited to match.
4. **Push is a manual, all-graded-submissions, overwrite action — never automatic or partial.**
   One "Push to Class Record" action copies every currently-*graded* submission's score into
   the linked assessment, overwriting whatever score (if any) is already there. There is no
   live sync: a later regrade in Learn requires an explicit re-push to propagate. This was
   chosen deliberately — Learn becomes the authoritative source for that one linked assessment
   once linked, and "just re-push" is simpler than a change-tracking sync mechanism.
5. **Students missing from the target quarter's roster are skipped, not blocking.** A Learn
   course's roster (`student_enrollments`) and a Class Record quarter's roster
   (`ClassRecordStudent`, snapshotted at quarter setup) can drift apart in edge cases (late
   transfers, etc.). Push skips any submission whose student has no matching
   `ClassRecordStudent` row on the target quarter and reports exactly who was skipped — it
   never fails the whole push over one student.
6. **No new permission strings.** Linking and pushing reuse `Course::canEdit()` (Learn side)
   and `GradingCategory::canEditOn()` (Class Record side) exactly as they already exist —
   including PEHM's existing subject-scoped co-teacher restriction, so a Learn instructor
   without Class Record write access to that specific category/subject still can't push into
   it even if they can edit the Learn assignment itself.

## Data model

Two new nullable columns on the existing `learn_assignments` table (Phase 2) — no new tables:

- `class_record_assessment_id` — nullable FK to `class_record_assessments`. Null means
  "not linked yet." Set via the link action; can be changed later to re-link to a different
  assessment (the previously-linked assessment's already-pushed scores are left untouched).
- `pushed_at` — nullable timestamp, set on every successful push (regardless of whether any
  students were skipped), surfaced to the instructor as "Last pushed: …".

## Linking

- `GET /learn/assignments/{assignment}/class-record-options` — resolves every `ClassRecord`
  matching `(subject_id, section_id, school_year_id)` from the assignment's Learn course, then
  for each one lists its quarters → `ClassRecordAssessment`s (via
  `ClassRecordAssessment::whereHas('quarter.classRecord', ...)`, eager-loading `gradingCategory`
  and `quarter` for grouped display: Class Record → Quarter → Grading Category → Assessment).
- `PUT /learn/assignments/{assignment}/link` — body: `class_record_assessment_id`.
  - Authorization: `$assignment->canEdit($user)` **and**
    `$assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user)`.
  - Validation: `$assessment->max_score` must exactly equal `$assignment->maxScore()` — 422 with
    a clear message otherwise (not a generic validation error).
  - On success: sets `class_record_assessment_id`; does not touch `pushed_at`.

## Pushing

- `POST /learn/assignments/{assignment}/push` — requires `class_record_assessment_id` to
  already be set (422 otherwise, "Link a Class Record assessment first").
  - Authorization: same two-sided check as linking (re-checked at push time, not just cached
    from link time — permissions can change between linking and pushing).
  - For every `Submission` where `learn_assignment_id` matches and `graded_at` is not null:
    - Resolve `ClassRecordStudent::where('class_record_quarter_id', $quarter->id)->where('student_id', $submission->student_id)->first()`.
    - Found → `ClassRecordScore::updateOrCreate(['class_record_student_id' => ..., 'class_record_assessment_id' => ...], ['score' => $submission->score])`.
    - Not found → add to a `skipped` list (student name/id), continue with the rest.
  - Sets `pushed_at = now()`.
  - Response includes the count pushed and the `skipped` list, rendered back to the instructor
    (not silently swallowed).

## Permissions

No new permission strings anywhere in this phase. Every authorization check is a direct reuse
of `Course::canEdit()` (Phase 1) and `GradingCategory::canEditOn()` (existing Class Record
code, already correctly PEHM-subject-scoped) — Learn adds no new authorization model.

## Testing

- Linking: rejects a max-score mismatch (422, no state change); succeeds and persists
  `class_record_assessment_id` on an exact match; resolves the correct candidate list across
  sibling Class Records (same subject+section+SY, different category_label); 403s a Learn
  instructor who lacks Class Record write access to the specific target category (PEHM
  subject-scoping case); 403s a non-Learn-instructor entirely.
- Pushing: copies graded scores correctly via `updateOrCreate` (overwrite, not duplicate) —
  including a second push after a regrade updating the same score row; skips and reports a
  student with no matching `ClassRecordStudent` on the target quarter without failing the rest
  of the push; ungraded submissions are never pushed; 422 when pushing before linking; 403 when
  either side's permission is missing at push time.

## Out of scope for Phase 2b

- Auto-creating Class Record assessments from Learn
- Live/automatic sync (push is always an explicit, manual action)
- Proportional score scaling when max scores differ
- Un-pushing / clearing previously-pushed scores when unlinking or deleting a Learn assignment

## Rollout

Two new nullable columns on an existing Phase 2 table — no changes to Class Record's schema at
all (Learn only reads `ClassRecordAssessment`/`ClassRecordStudent`/`GradingCategory` and writes
`ClassRecordScore` through its own existing model, using its own existing authorization method).
Purely additive; no blue-green expand/contract concerns.
