# Learn Module (LMS) — Phase 2c: Reusable Rubric Bank — Design

Date: 2026-08-09
Status: Approved, ready for implementation plan

## Problem

Phase 2 shipped per-assignment rubrics, deliberately deferring a reusable rubric bank as
out of scope (YAGNI for a first version). Instructors who use the same grading criteria across
multiple assignments — or across multiple courses — currently have to re-type them every time.
This spec covers Phase 2c: letting an instructor save a rubric's criteria as a named personal
template and reuse it as a starting point for future assignments.

## Program roadmap (reference only — not this spec)

| Phase | Scope | Depends on |
|---|---|---|
| 1 (shipped) | Course shell: courses, Modules, Pages, Files, syllabus, announcements | Faculty Loading, `student_enrollments` |
| 2 (shipped) | Assignments, submissions, points/rubric grading | Phase 1 |
| 2b (shipped) | Push a graded assignment's scores into Class Record | Phase 2 |
| **2c (this spec)** | Reusable/cross-course rubric bank | Phase 2 |
| 3 | Quizzes/assessment engine | Phase 1 |
| 4 | Discussions/forums | Phase 1 |

## Key decisions (resolved during brainstorming, not open questions)

1. **Copy on apply, copy on save — never share by reference.** A template has no foreign key
   relationship to any assignment's actual rubric, in either direction. Saving a template copies
   criteria *from* an assignment's just-built rubric *into* a new template row; applying a
   template copies criteria *from* the template *into* a new assignment's rubric (as a
   client-side form pre-fill, submitted through the existing `storeAssignment` flow unchanged).
   This is what makes the whole feature safe by construction: editing, deleting, or regrading an
   assignment can never reach into a template, and renaming or deleting a template can never
   reach into any assignment that was ever built from it — including ones already graded. No
   change to grading/scoring logic anywhere in this phase.
2. **Personal, not shared.** Each instructor sees and applies only templates they saved
   themselves, across any course they teach — no cross-instructor sharing, no department-level
   curation, no new permission model. Ownership is a simple `user_id` column.
3. **Saving happens inline, not on a dedicated screen.** A "Save these criteria as a template
   named ___" checkbox + name field is added to the existing rubric-building UI inside assignment
   creation (`ModuleItemController::storeAssignment`) — no standalone template-authoring screen.
4. **Templates are rename/delete only — never edit their criteria.** Once saved, a template's
   criteria are immutable. To change them, apply the template to a new assignment, tweak there,
   and save that as a new template. This keeps "editing a template never affects anything" true
   by construction rather than something that needs explaining, and avoids building a second
   criteria-editing UI separate from assignment creation.

## Data model

### `learn_rubric_templates`
- `user_id` (owner, FK `users`), `name`

### `learn_rubric_template_criteria`
- `learn_rubric_template_id`, `description`, `max_points`, `position`

Neither table has any foreign key to `learn_assignments`, `learn_rubrics`, or
`learn_rubric_criteria` — this absence is the entire safety mechanism described in Decision 1,
not an oversight.

## Workflow

- **Saving:** `ModuleItemController::storeAssignment` gains two optional request fields —
  `save_as_template` (bool) and `template_name` (required when true). After the assignment's own
  `Rubric`/`RubricCriterion` rows are created exactly as today, if `save_as_template` is set, a
  `RubricTemplate` and its `RubricTemplateCriterion` rows are created from the same submitted
  criteria data — a second, independent copy.
- **Browsing/applying:** the signed-in instructor's templates (each with its criteria) are
  included in the existing `CourseController::show()` Inertia payload as a new top-level prop.
  The "add assignment" rubric-builder form gains a "Start from template" dropdown; selecting one
  pre-fills the rubric-criteria rows client-side only — nothing is sent to the server until the
  assignment itself is submitted through the unchanged `storeAssignment` endpoint.
- **Managing:** a "My templates" list (name + rename + delete) next to the same dropdown.
  `PUT /learn/rubric-templates/{template}` renames; `DELETE /learn/rubric-templates/{template}`
  deletes. Both are ownership-checked (`$template->user_id === $user->id`) — not
  `Course::canEdit()`, since a template isn't tied to any course.

## Permissions

No new permission strings. Ownership is enforced directly (`user_id` equality), the same
lightweight pattern already used for submission ownership in Phase 2 (`$submission->student_id === $student->id`).

## Testing

- Saving: `save_as_template` creates a `RubricTemplate` with criteria matching what was
  submitted; omitting it creates no template; the assignment's rubric and the new template share
  no row/foreign-key relationship (assert independently by ID).
- Independence: editing an assignment's own rubric criteria after the fact never changes the
  template it may have been saved from or applied from (there is no code path that could, but
  assert it anyway); deleting a template never touches any assignment's own rubric.
- Applying: a template's criteria populate the client-side form correctly (covered as a
  client-side/manual check, since apply never round-trips to the server) and the resulting
  assignment, once submitted, has its own independent rubric row.
- Managing: rename and delete both succeed for the owner and 403 for a different user;
  deleting a template that was previously applied to an assignment does not affect that
  assignment's existing rubric.

## Out of scope for Phase 2c

- Editing a template's criteria after it's saved
- Cross-instructor / department-wide template sharing
- A standalone rubric-bank management screen
- Any change to grading, scoring, or the Class Record push (Phase 2b) — this phase is
  authoring-only

## Rollout

Two new, fully independent tables with no foreign keys into any existing Learn or Class Record
table. Purely additive; no blue-green expand/contract concerns.
