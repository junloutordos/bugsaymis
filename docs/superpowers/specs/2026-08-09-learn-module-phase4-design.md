# Learn Module (LMS) — Phase 4: Discussions / Forums — Design

Date: 2026-08-09
Status: Approved, ready for implementation plan

## Problem

Learn has no discussion/forum capability. Instructors currently have no way to post a discussion
prompt and let students reply to it (or to each other) within a course — the closest existing
feature, Course Announcements, is one-way broadcast only, not a conversation. This spec covers
Phase 4: a graded-or-ungraded discussion board as a new Learn content type, with fully nested
(unlimited-depth) replies.

There is no existing discussion/forum/thread code anywhere in this codebase — no naming
collision risk, unlike Phase 3's live Quiz module.

## Program roadmap (reference only — not this spec)

| Phase | Scope | Depends on |
|---|---|---|
| 1 (shipped) | Course shell: courses, Modules, Pages, Files, syllabus, announcements | Faculty Loading, `student_enrollments` |
| 2 (shipped) | Assignments, submissions, points/rubric grading | Phase 1 |
| 2b (shipped) | Push a graded assignment's scores into Class Record | Phase 2 |
| 2c (shipped) | Reusable/cross-course rubric bank | Phase 2 |
| 3 (shipped) | Quizzes: question bank, attempts, auto+manual grading, Class Record push, item analysis | Phase 1, Phase 2b |
| **4 (this spec)** | Discussions/forums with nested replies, optional participation grading | Phase 1, Phase 2b |

## Key decisions (resolved during brainstorming, not open questions)

1. **New polymorphic `ModuleItem` type.** `Discussion` becomes a fifth `itemable` type alongside
   Page/File/Assignment/Quiz, reusing all existing module/item CRUD, publish/reorder/delete
   machinery. No changes needed to that machinery.
2. **Adjacency list for threading, not materialized path or nested set.** A single
   self-referencing `parent_post_id` on `learn_discussion_posts`. All posts for a discussion are
   fetched in one query and assembled into a nested tree server-side (PHP), then shipped to
   Inertia as a plain nested array — no recursive SQL, no path-string bookkeeping. This is the
   right tradeoff at classroom-conversation scale (dozens to low-hundreds of posts per
   discussion), where materialized-path/nested-set machinery only pays off at forum-wide scale
   this feature will never reach.
3. **Soft-delete preserves the tree.** Deleting a post sets `is_deleted = true` and records who
   deleted it; the row and everything nested under it stay in place, with the body replaced by
   "[deleted]" in the UI. This avoids one deletion destroying other students' replies as a side
   effect (the cascade-delete alternative) and avoids permanently blocking deletion once a post
   has replies (the block-on-replies alternative).
4. **Optional whole-discussion participation grading, not per-post grading.** A discussion may
   have a `points_possible`; if set, the instructor grades each student once for their overall
   participation (a separate `learn_discussion_grades` table, one row per student), not per
   individual post. `Discussion` implements the same `HasClassRecordLink` contract Assignment and
   Quiz already implement (Phase 3), so `ClassRecordPushService` needs zero changes to support it
   — same additive-widening pattern.
5. **Instructor can delete any post; students can only delete their own.** Matches the
   instructor's existing broad control over their course's content (they can already
   delete/unpublish any module item).
6. **No per-post edit lock, no per-post grading.** Since grading is participation-based (one score
   per student for the whole discussion), there is no per-post "submission" state to lock —
   authors can edit their own post's body any time, except once it's been soft-deleted.

## Data model

### `learn_discussions`
- `title`, `prompt` (rich text)
- `points_possible` (nullable decimal — ungraded if null)
- `class_record_assessment_id` (nullable FK → `class_record_assessments`, `nullOnDelete`),
  `pushed_at` (nullable timestamp) — same two columns Assignment/Quiz already carry

### `learn_discussion_posts`
- `learn_discussion_id`
- `parent_post_id` (nullable, self-referencing FK to this same table — null = top-level reply to
  the discussion prompt)
- `author_type` (enum: `student` | `faculty`), `author_id` (unsignedInteger — `student_id` or
  `user_id` depending on `author_type`; no single FK possible since students and users are
  different tables — mirrors the existing dual-identity pattern already used elsewhere, e.g.
  `CourseAnnouncement.posted_by` for faculty vs `Submission.student_id` for students)
- `body` (rich text)
- `is_deleted` (bool, default false) — when true, `body` is not shown, the row and its children
  remain intact
- `deleted_by_type` / `deleted_by_id` (nullable — who deleted it, same shape as
  `author_type`/`author_id`)
- `created_at`, `updated_at` (an `updated_at` meaningfully later than `created_at` means the post
  was edited — no separate "edited" flag needed)

### `learn_discussion_grades`
- `learn_discussion_id`, `student_id`, `points_earned`, `feedback_comment`, `graded_at`,
  `graded_by` — one row per student (participation grade for the whole discussion), unique on
  (`learn_discussion_id`, `student_id`)

Neither `learn_discussion_posts` nor `learn_discussion_grades` has any foreign key relationship
to a question bank or template concept — Phase 4 has no reusable-content feature, unlike Phase
2c's rubric bank or Phase 3's question bank.

## Workflow

- **Authoring:** instructor creates a discussion (title, prompt, optional `points_possible`) in
  one POST — same one-shot creation pattern as `storeAssignment`/`storeQuiz`.
- **Posting/replying:** any student visible to the course (`Course::isVisibleToStudent()`) or any
  instructor with course access (`Course::canView()` — not `canEdit()`, so a co-instructor without
  edit rights can still participate) can post a top-level reply or reply to any existing post
  (setting `parent_post_id`). The full post tree for a discussion is fetched in one query, grouped
  by `parent_post_id` in PHP, and shipped to the Vue page as a nested array.
- **Editing:** the author can edit their own post's body at any time; editing a soft-deleted post
  is blocked.
- **Deleting:** the author can delete their own post; anyone with `canEdit()` on the course
  (the instructor) can delete any post. Both soft-delete — `is_deleted = true` plus
  `deleted_by_type`/`deleted_by_id` — never a hard delete, never touches children.
- **Grading:** when `points_possible` is set, the instructor sees a roster (student name →
  points_earned/feedback_comment), one row per student, backed by `learn_discussion_grades`. Class
  Record push/link works exactly like Assignment/Quiz via the shared `HasClassRecordLink`
  contract from Phase 3.

## Permissions

No new permission strings. Authoring the discussion itself, editing its own settings, and grading
reuse `Course::canEdit()`. Posting/replying reuses `Course::canView()` for instructors and
`Course::isVisibleToStudent()` for students — the same checks already gating Assignment/Quiz
participation. Deleting your own post needs only an author-match check; instructor
delete-any-post reuses `canEdit()`.

## Testing

- Discussion CRUD (create with/without `points_possible`).
- Posting a top-level reply and a nested reply (3+ levels deep); the tree-building logic groups
  posts by `parent_post_id` correctly regardless of database return order.
- Soft-delete preserves children in the tree and replaces the body with "[deleted]"; a
  soft-deleted post cannot be edited.
- Editing updates `body`/`updated_at`; editing someone else's post is rejected; editing a deleted
  post is rejected.
- Author can delete their own post; a stranger cannot; the instructor can delete any post in
  their course.
- Grading roster reflects `learn_discussion_grades`; Class Record push/link reused via
  `HasClassRecordLink`, including the WAT invariant test (linking/pushing never touches the
  target `ClassRecordAssessment`'s `plotted_at`/`activity_date`) — same test shape as Phase 2b/3.

## Out of scope for Phase 4

- Per-post grading (only whole-discussion participation grades).
- Requiring a student to post before seeing others' replies ("post-first" discussions).
- Real-time updates via Echo/Pusher broadcasting — a reply is a standard Inertia reload, same as
  Assignments/Quizzes.
- Post attachments/files — text-only posts, matching Announcements' existing text-only body.
- @mentions or notifications.

## Rollout

Three new tables, purely additive — no destructive migrations, no blue-green expand/contract
concerns. `Discussion` implementing `HasClassRecordLink` requires zero changes to the
already-shipped `Assignment`/`Quiz` implementations or to `ClassRecordPushService` itself — the
same additive-widening pattern Phase 3 established when it introduced the contract.
