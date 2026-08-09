# Learn Module (LMS) — Phase 2: Assignments + Submissions + Rubric Grading — Design

Date: 2026-08-09
Status: Approved, ready for implementation plan

## Problem

Phase 1 shipped the Learn course shell — courses, Modules, Pages, Files, syllabus, announcements,
publish/draft workflow — as the foundation for a Canvas/Blackboard-class LMS
(`docs/superpowers/specs/2026-08-08-learn-module-phase1-design.md`). It has no way for a teacher
to assign work and grade it. This spec covers Phase 2 of the roadmap: Assignments as a new
Module Item type, student submissions, and instructor grading — either by simple points or by a
per-assignment rubric.

## Program roadmap (reference only — not this spec)

| Phase | Scope | Depends on |
|---|---|---|
| 1 (shipped) | Course shell: courses, Modules, Pages, Files, syllabus, announcements | Faculty Loading, `student_enrollments` |
| **2 (this spec)** | Assignments, submissions, points/rubric grading | Phase 1 |
| 2b (later, uncommitted) | Push a graded assignment's score into Class Record | Phase 2 |
| 2c (later, uncommitted) | Reusable/cross-course rubric bank | Phase 2 |
| 3 | Quizzes/assessment engine | Phase 1 |
| 4 | Discussions/forums | Phase 1 |
| 5 (later, uncommitted) | Calendar aggregation, notifications, analytics, AtlasGo mobile surface | 2–4 |

## Key decisions (resolved during brainstorming, not open questions)

1. **Assignment is a new polymorphic Module Item type** — exactly the extension point Phase 1's
   `learn_module_items` (`itemable_type`/`itemable_id`) was built for. No schema change to
   Phase 1 tables; `LearnAssignment` just becomes a second `itemable` alongside `Page`/`File`.
   Same publish/draft gate (`ModuleItem.published_at`) as Pages/Files.
2. **One submission type per assignment, instructor-chosen at creation** — text, file, or link,
   never a mix. Matches Canvas's model; keeps the submission form and validation simple and
   unambiguous for students.
3. **Soft deadline.** `due_at` is informational — a late submission is still accepted and
   visibly flagged to the instructor, computed live (`submitted_at > due_at`), never stored.
   No hard-cutoff/lock-at-deadline logic, no override/exception mechanism needed.
4. **Resubmission replaces in place, only before grading.** `learn_submissions` has a unique
   `(assignment_id, student_id)` row — no attempt history table. Grading sets `graded_at`,
   which locks further submission; an explicit "reopen for resubmission" instructor action nulls
   `graded_at`/`score`/rubric scores to unlock it again.
5. **Rubric is optional per-assignment, never reusable, and never combined with points_possible.**
   An assignment either has a flat `points_possible` (instructor types one score when grading) or
   a rubric (`learn_rubrics` → `learn_rubric_criteria`, each with a `max_points`; grading types a
   points-earned number per criterion, 0..max — no predefined performance levels). The rubric's
   total is the score. `Assignment::maxScore()` computes which one applies live, never
   duplicating the number into a stored column. A rubric bank shared across assignments is
   explicitly deferred (Phase 2c) — YAGNI for a first version.
6. **Rubric feedback is per-criterion points + one overall comment**, not a comment box per
   criterion. Keeps the grading UI to exactly one free-text field regardless of rubric size.
7. **No Class Record push in this phase.** Deferred to Phase 2b — it touches Class Record's
   `GradingCategory`/`ClassRecordAssessment` model, which shipped major changes this month
   (WAT, PEHM co-teacher, category-split), and deserves its own scoped design rather than being
   bundled into the first assignments cycle.
8. **No separate "Assignments" tab/index.** An assignment lives wherever the instructor places
   it inside a Module, same as Pages/Files — no course-level aggregated assignment list to build.
9. **Grading view is a per-assignment roster list**, not per-student stepper navigation — built
   the same shape as `RosterService`: the course's `student_enrollments` left-joined against
   `learn_submissions`, showing not-submitted / submitted / late / graded per student.

## Data model

### `learn_assignments`
- `title`, `instructions` (rich text, Tiptap — same `RichTextEditor.vue` convention as Page body)
- `submission_type`: enum `text` / `file` / `link`
- `points_possible` (nullable — ignored when a rubric is attached to this assignment)
- `due_at` (nullable timestamp)
- Attached to a course the same way Pages/Files are: via a `learn_module_items` row whose
  `itemable_type` is `App\Models\Learn\Assignment`.

### `learn_rubrics`
- `learn_assignment_id` (unique — one rubric per assignment, nullable relation from the
  assignment's side; an assignment with no rubric row uses `points_possible` instead)

### `learn_rubric_criteria`
- `learn_rubric_id`, `description`, `max_points`, `position`

### `learn_submissions`
- `learn_assignment_id`, `student_id`, unique on `(learn_assignment_id, student_id)`
- `text_body` (rich text, nullable), `file_id` (nullable FK `learn_files`), `link_url` (nullable
  string) — only the column matching the assignment's `submission_type` is ever populated
- `submitted_at`
- `score` (nullable — null means ungraded), `feedback_comment` (nullable), `graded_at`
  (nullable — the lock signal), `graded_by` (nullable FK `users`)
- `isLate()` computed live from `submitted_at` vs. `assignment.due_at` — never a stored column,
  matching Phase 1's `isReadOnly()`/`instructorIds()` compute-on-read convention.

### `learn_rubric_scores`
- Only populated when the assignment has a rubric: `learn_submission_id`,
  `learn_rubric_criterion_id`, `points_earned`

## Permissions & access

No new permission strings. Authoring and grading reuse `Course::canEdit()` exactly as Phase 1's
Module/Page/File controllers already do. Students can only view and submit their own submission
row, gated by the same `Course::isVisibleToStudent()` enrollment check Phase 1 built for course
visibility — a student can never see or query another student's submission or grade.

## Workflow

- **Authoring:** create an Assignment inside a Module the same way a Page or File is created
  (`ModuleItemController` gains a `storeAssignment` action alongside `storePage`/`storeFile`);
  publish/draft uses the existing `ModuleItem.published_at` toggle unchanged.
- **Submitting:** the student's course view renders a submission form matching the assignment's
  fixed `submission_type` — rich text (sanitized with DOMPurify at render, carrying forward
  Phase 1's stored-XSS fix), file (reuses `CourseFileService::upload()`/allowlist unchanged), or
  link (`http(s)`-only scheme validation, same pattern Phase 1 used for `video_url`).
  Resubmitting overwrites the same row until graded.
- **Grading:** opening an assignment as an instructor shows the roster-style submission list;
  clicking a student opens either a single points field (flat-points assignments) or one number
  input per rubric criterion (auto-summed) plus one overall feedback comment field. Saving sets
  `graded_at`/`graded_by` and locks the submission. An explicit "reopen for resubmission" button
  unlocks it.

## Testing

Same coverage shape as Phase 1: assignment CRUD + publish gating (item-type-specific), submission
create/resubmit/lock-on-grade/reopen, live late-flag computation, both scoring paths (flat points
and rubric-sum), roster-list correctness (not-submitted/submitted/late/graded), and permission
boundaries — a student cannot view or grade another student's submission, and a non-instructor
cannot grade at all.

## Out of scope for Phase 2

- Class Record push (Phase 2b)
- Reusable/cross-course rubric bank (Phase 2c)
- Per-criterion feedback comments
- Hard deadlines / submission lockout at due date
- Submission attempt history (only the current, latest submission is kept)
- A dedicated course-level Assignments tab/index separate from Module placement
- Quizzes, discussions (Phases 3–4, unrelated to this spec)

## Rollout

All-new tables (`learn_assignments`, `learn_rubrics`, `learn_rubric_criteria`,
`learn_submissions`, `learn_rubric_scores`) plus one new polymorphic `itemable` type value on the
existing `learn_module_items` table — no changes to any Phase 1 column, no changes to any other
module's schema. Purely additive; no blue-green expand/contract concerns.
