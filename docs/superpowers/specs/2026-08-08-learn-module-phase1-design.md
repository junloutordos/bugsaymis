# Learn Module (LMS) — Phase 1: Course Shell + Content — Design

Date: 2026-08-08
Status: Approved, ready for implementation plan

## Problem

PSHS-CRC currently runs course delivery (materials, modules, syllabi) on Moodle, external to
BugSayMis. The goal is a premium, Canvas/Blackboard-class Learning Management System —
**Learn** — that lives inside BugSayMis and wires into the data that already exists here
(Faculty Loading's teaching assignments, Class Record's gradebook, SIS's roster data), rather
than duplicating enrollment/roster/scheduling data the way a bolted-on external LMS would.

Building the full LMS (content, assignments, quizzes, discussions, calendar, analytics) as one
project is not tractable — each of those is roughly its own subsystem. This document specs
**Phase 1 only**: the course shell and content delivery layer that every later phase depends
on.

## Program roadmap (reference only — not this spec)

| Phase | Scope | Depends on |
|---|---|---|
| **1 (this spec)** | Course shell: auto-created courses, Modules, Pages, Files, syllabus, course announcements, publish/draft | Faculty Loading, `student_enrollments` |
| 2 | Assignments + submissions + rubric grading + optional push to Class Record | Phase 1 |
| 3 | Quizzes/assessment engine (question bank, auto-grading, timed attempts) | Phase 1 |
| 4 | Discussions/forums | Phase 1 |
| 5 (later, uncommitted) | Calendar aggregation, notifications, analytics/at-risk dashboards, AtlasGo mobile surface | 1–4 |

Phases 2–4 each get their own design → plan → implementation cycle once Phase 1 ships.

## Key decisions (resolved during brainstorming, not open questions)

1. **Learn is a content/engagement layer only.** Class Record remains the gradebook of record.
   Phase 2 will let a teacher optionally push a Learn assignment's scores into a Class Record
   assessment column; Learn never replaces or duplicates Class Record's scoring machinery.
2. **Fresh start — no Moodle import.** Teachers rebuild/upload their current materials
   directly into Learn. A Moodle `.mbz` importer is out of scope for Phase 1 and would need
   its own design if ever pursued.
3. **Files go to S3 via the existing base64 pattern; video is link-only.** Documents, images,
   and slides upload through `Storage::disk('s3')` (private bucket, proxy-served) exactly like
   every other upload in this codebase. Video is not self-hosted — a page/item accepts a
   YouTube or Google Drive link, rendered as an embed or link-out. No transcoding/streaming
   infrastructure.
4. **Students access Learn inside the existing Student Portal** (`/student-portal`,
   Firebase-authenticated), not a new portal. One login, alongside their existing
   grades/schedule views.
5. **Enrollment is fully derived, never manual.** A course = one row per
   `(subject_id, section_id, school_year_id, academic_term_id)` sourced from Faculty Loading's
   teaching `LoadAssignment`s. Roster = live from `student_enrollments` (Registrar module,
   `status = enrolled`), the canonical current-SY enrollment source — **not** the legacy
   `section_students` mirror table. This corrects an assumption from initial brainstorming:
   `section_students` was previously documented as the section-read source of truth, but the
   Homeroom Attendance module (`RosterService::studentsForSection()`) and
   `StudentSectionResolver` have since established `student_enrollments` as canonical, since
   `section_students` is not school-year-aware and shows a stale section for students not yet
   mirrored into the new SY. Learn follows the newer, correct convention. No "enroll student in
   course" step, no separate roster table to drift out of sync.
6. **Content model is polymorphic Modules → Module Items**, matching the
   `respondable_type/id` convention CSM Feedback already uses in this codebase. Item types in
   Phase 1: `LearnPage`, `LearnFile`. Phases 2–4 add `LearnAssignment`, `LearnQuiz`,
   `LearnDiscussion` as new item types — no schema rework of Phase 1 tables required.

## Data model

### `learn_courses`
- `subject_id` (FK `faculty_loading.subjects`), `section_id` (unsigned int FK
  `faculty_loading.sections` — `sections.id` is an int PK, not bigint, same convention
  `load_assignments.section_id` already uses), `school_year_id` (FK
  `faculty_loading.school_years`), `academic_term_id` (FK `faculty_loading.academic_terms`,
  **not nullable** — `load_assignments.academic_term_id` itself is a required FK, so every
  teaching assignment, and therefore every course resolved from one, always has a term)
- Unique on `(subject_id, section_id, school_year_id, academic_term_id)`
- `status`: `draft` (default) → `published`. No `archived` value — see lifecycle below.
- `syllabus_body` (nullable rich text, Tiptap JSON/HTML — same editor convention as Issuances)
- No `class_record_id` FK — Phase 2's "push grade" action looks up the matching Class Record
  row by the same tuple at push time rather than storing a hard FK. This keeps a Learn course
  functional even in the (rare) window before a Class Record exists for that offering.

### Instructors — computed, not a synced pivot
No `learn_course_instructors` table. This codebase has no Eloquent Observer pattern anywhere,
and there is no generic "a LoadAssignment changed" hook to sync a pivot off of — building one
would be new infrastructure for this module alone. Instead, a course's instructors are
resolved live, the same way `ClassRecord::teacherIdsFor()` and `RosterService` compute their
answers on read rather than maintaining a synced copy: query teaching `LoadAssignment`s for
the course's exact tuple and pluck distinct `user_id`s. Zero drift risk, since there's nothing
to fall out of sync.

### Course lifecycle — lazy creation, computed lock (not a scheduled job)
- No sync job or observer creates course rows ahead of time. A course row is
  find-or-created on first access: when a faculty member with a teaching `LoadAssignment`
  opens "My Courses," the controller resolves their distinct teaching tuples for the
  current/future school year and `firstOrCreate()`s a `learn_courses` row per tuple (default
  `draft`). This mirrors the "compute live" convention above — there's no window where a
  background sync could be stale or fail to run.
- Read-only lock is **computed**, not a stored `archived` status: a course is read-only when
  `course.schoolYear.is_current === false`, exactly `ClassRecord::isCurrentSchoolYear()`'s
  existing pattern. No scheduled transition job, nothing to drift.

### `learn_modules`
- `learn_course_id`, `title`, `position` (int, ordering), `published_at` (nullable)

### `learn_module_items`
- `learn_module_id`, `itemable_type`, `itemable_id` (polymorphic), `position`, `published_at`
  (nullable — lets a teacher stage a module and reveal individual items later without
  publishing the whole module)

### `learn_pages`
- `title`, `body` (rich text, Tiptap), `video_url` (nullable, YouTube/Drive link only)

### `learn_files`
- `title`, `s3_key`, `mime_type`, `size_bytes`
- Uploaded via base64 JSON body → `Storage::disk('s3')`, never `disk('public')`
- Served via a private proxy route `/learn/file/{fileId}`, `fileId` encoded the same way WFH
  photos are: `'s3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=')`

### `learn_course_announcements`
- `learn_course_id`, `title`, `body`, `posted_by`, `posted_at`
- Deliberately **not** built on the existing global Announcements module — course audience
  (that course's roster + instructors) is a different targeting model than the campus-wide
  Announcements system, and forcing both onto one polymorphic audience model would complicate
  the existing module for no shared benefit.

## Permissions & access

- `learn.course.view.all` — admin/AUH oversight of all courses regardless of instructor
  assignment.
- `learn.admin` — module-wide settings (reserved for later phases; no Phase 1 admin UI needs
  it yet beyond the view-all case above).
- **No per-course permission grant.** Edit access is derived: if a user holds a teaching
  `LoadAssignment` for a course's `(subject_id, section_id, school_year_id, academic_term_id)`
  tuple, they can edit that course — mirroring `ClassRecord::canEdit()`'s existing co-teacher
  logic rather than reinventing an authorization model.
- Students are never permission-gated here. Visibility is computed: published course AND the
  student has an active `student_enrollments` row for that section/school year — same
  computation style as their existing grades/schedule views in the Student Portal.

## Publishing workflow

- New courses start `draft` — visible only to their instructors, including in the Student
  Portal course list (drafts never appear to students).
- An instructor flips a course to `published` when ready for students.
- Modules and module items each carry their own `published_at`, independent of the course's
  status — a teacher can build out a full module in draft and reveal it to students later
  without touching course-level status or other modules.
- Courses from a past school year (`schoolYear.is_current === false`) are read-only for all
  users, instructors included — computed the same way, not a stored status transition.

## Student Portal integration

- New "My Courses" area inside the existing `/student-portal` (Firebase auth, `students`
  table session — not the main app's Google OAuth).
- Lists courses where `status = published` and the student has an active `student_enrollments`
  row (`status = enrolled`) for that course's `section_id` in the course's `school_year_id`.
- Read-only content view for Phase 1: modules, pages, files, syllabus, course announcements.
  No submission UI yet (that's Phase 2).

## Testing

- Course lazy find-or-create from a teaching `LoadAssignment`, including multi-instructor
  (co-teaching) resolution correctness computed live from `LoadAssignment`.
- Instructor-derived edit access: holder of a teaching `LoadAssignment` for the tuple can
  edit; non-instructor faculty cannot.
- Publish gating: draft course invisible to students; unpublished module item invisible even
  inside a published course/module.
- SY-archive lock: course and its content become read-only once `school_year.is_current`
  flips false.
- File upload/proxy: base64 upload lands in S3 (never `disk('public')`), proxy route serves
  only to authorized viewers (instructor or enrolled+published-course student).

## Out of scope for Phase 1

- Assignments, submissions, rubric grading (Phase 2)
- Quizzes/assessments, question banks (Phase 3)
- Discussions/forums (Phase 4)
- Calendar aggregation, notifications, analytics, AtlasGo mobile surface (Phase 5, uncommitted)
- Moodle content import
- Self-hosted video
- Manual roster overrides (cross-section electives, audit access) — all enrollment is
  section-derived in Phase 1

## Rollout

All-new tables — no existing traffic depends on this schema, so this is a purely additive
rollout with no blue/green expand/contract concerns. No data migration required (fresh start,
no Moodle import).
