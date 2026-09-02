# Research Advisory — Submission Requirements Design

**Date:** 2026-09-02
**Status:** Approved by user (in-chat), pending spec review before implementation planning.

## Overview

Upgrade the Research Advisory module (Faculty Loading) so a **Research Coordinator**
can define submission requirements — a deadline plus what file(s)/report(s) are due —
and target them at research groups by scope (grade level, research type, term), with
manual exceptions. Advisers get a new self-service view to upload their submissions;
the coordinator reviews each submission (accept / return for revision). Full
notification lifecycle (posted, reminder, overdue, reviewed).

This module has **zero prior file/deadline/submission infrastructure** — everything
below is new, except one small structural fix to the existing group model (Phase 0).

## Goals

- Coordinator can create a requirement once and have it apply to every matching
  active research group, with the ability to add/exclude individual groups.
- Advisers (lead or co-adviser, either one) can see and fulfill requirements for
  their group and upload evidence (multi-file + notes).
- Coordinator can accept or return-for-revision with a comment; adviser can resubmit.
- Automatic notifications: requirement posted, submission received, review decision,
  deadline reminder, overdue flag — bell + email, matching existing app conventions.
- Coordinator dashboard shows compliance at a glance (submitted/accepted/late/overdue
  counts per requirement).

## Non-Goals (explicitly out of scope)

- No PIN-signed formal approval — review is a simple status toggle + comment.
- No per-student-level submissions — everything is per research **group**.
- No changes to `load_units` / `LoadComputationService` / `faculty_loads` — this is a
  pure compliance/workflow layer, fully decoupled from unit computation.
- No rewrite of `ResearchAdvisoryReconciliationService`'s existing title-parsing bulk
  sync tool — it continues to operate exactly as today, untouched.
- No change to the existing Research Advisory CRUD page/permissions beyond adding the
  new `research_group_id` linkage described in Phase 0.

## Current State (from codebase audit)

- `ResearchAdvisory` (table `research_advisories`) — one row per **adviser** per
  **group** per **term**: `user_id`, `academic_term_id`, `grade_level`, `research_title`,
  `advisory_role` (lead/co_adviser), `research_type` (thesis/investigatory/
  science_research/feasibility), `load_units`, `status` (active/completed/dropped).
- `ResearchAdvisoryMember` (table `research_advisory_members`) — students in the group,
  child of `ResearchAdvisory`.
- **No explicit "group" entity.** A group is only implicitly identified by
  `research_title + grade_level + academic_term_id`; multiple co-advisers on the same
  group each get their own separate `ResearchAdvisory` row with no shared FK linking
  them (`ResearchAdvisoryReconciliationService::groupKeys()` does ad-hoc string
  parsing of this for its own bulk-sync purposes only).
- `faculty_loading.research_advisories` permission + "Research Coordinator" role
  already exist (`ResearchCoordinatorPermissionSeeder`) but only grant CRUD over
  `research_advisories` — nothing adviser-facing exists today. Advisers currently have
  **zero route access** to this module.
- `faculty_loading.view_own` permission already exists and is used to give faculty
  read-only access to their own schedule ("My Faculty Schedule") — the right existing
  permission to reuse for the new adviser-facing routes.
- Base64→S3 file upload is the established project-wide convention (Cloudflare WAF
  blocks multipart uploads). Files are always served through a proxy route, never a
  direct S3 URL — see the WFH photo proxy (`s3.<base64url(key)>` file-id format) as
  the closest existing pattern.

## Phase 0 — Foundational fix: explicit `research_groups` entity

**Why:** Requirements must target a *group*, and both the lead adviser and any
co-adviser on that group need shared visibility into the same requirement/submission.
Attaching directly to individual `ResearchAdvisory` rows would create duplicate,
disconnected obligations for co-advised groups. This is the one structural change in
an otherwise purely additive feature.

**Schema (additive, nullable FK — safe in a single blue-green deploy):**

```
research_groups
  id
  academic_term_id   FK -> academic_terms
  grade_level         tinyint
  title               varchar(500)
  research_type       enum(thesis, investigatory, science_research, feasibility)
  timestamps

research_advisories
  + research_group_id  FK -> research_groups, nullable
```

**Resolution logic (`ResearchGroupResolver` service):** given `(academic_term_id,
grade_level, title)`, normalize the title (trim + case-insensitive compare) and
`firstOrCreate` a `research_groups` row. Wire this into `ResearchAdvisoryController`
`store()`/`update()` so every create/update of a `ResearchAdvisory` resolves (and, on
a title/grade/term change, re-resolves) its `research_group_id`. This formalizes the
grouping that already existed implicitly by string — no behavior change to the
existing CRUD page.

**Backfill:** new artisan command `research-groups:backfill` (dry-run by default,
matching project convention), single transaction, groups existing `research_advisories`
rows by normalized title+grade+term and populates `research_group_id`.

## Phase 1 — Submission Requirements (coordinator side)

**Schema:**

```
research_requirements
  id
  created_by            FK -> users
  academic_term_id      FK -> academic_terms
  title                 varchar(255)
  description            text, nullable
  research_type          enum(...), nullable        -- null = all types
  grade_levels            json array of tinyint, nullable -- null = all grades
  accepted_file_types    varchar(255), nullable      -- e.g. "pdf,docx"
  max_files               tinyint, default 5
  due_at                  datetime
  allow_late_submission   boolean, default true
  status                  enum(active, archived), default active
  timestamps

research_requirement_assignments
  id
  research_requirement_id  FK -> research_requirements, cascade
  research_group_id        FK -> research_groups
  status                    enum(pending, submitted, accepted, returned), default pending
  excluded                  boolean, default false
  reminder_sent_at          datetime, nullable
  overdue_notified_at       datetime, nullable
  timestamps
  unique(research_requirement_id, research_group_id)
```

**Fan-out (`RequirementFanoutService::create()`):** on requirement creation, resolve
matching groups — active `research_groups` (i.e. having at least one non-dropped
`ResearchAdvisory` row) filtered by `academic_term_id`, `grade_level IN
grade_levels` (or all, if null), `research_type` (or all, if null) — and create a
`pending` assignment for each. An explicit **"Sync"** action (mirrors the existing
"Re-sync All Loads" button pattern) re-runs this to pick up groups created after the
requirement was posted; it never removes existing assignments, only adds newly
matching ones.

**Exceptions:** `excluded` flag on an assignment (not a hard delete) lets the
coordinator drop a specific group from a broadcast requirement while preserving any
submission history if one already exists; a separate "add group" action lets the
coordinator manually attach a group outside the computed scope.

**Routes** (prefix `faculty-loading/research-requirements`, middleware
`permission:faculty_loading.manage|faculty_loading.research_advisories` — same gate
as existing Research Advisory routes):

```
GET    /                                    index (list + summary stats)
POST   /                                    store (creates + fans out)
GET    /{requirement}                       show (assignment grid + submissions)
PUT    /{requirement}                       update (metadata only, no re-fan-out)
DELETE /{requirement}                       archive (status=archived, soft)
POST   /{requirement}/sync                  re-run fan-out
POST   /{requirement}/assignments           add exception group
PATCH  /{requirement}/assignments/{id}      exclude/re-include group
POST   /assignments/{assignment}/submissions/{submission}/review   accept | return
```

**Frontend:**
- `ResearchRequirements/Index.vue` — list, per-requirement compliance %, create/edit
  modal (scope picker: grade levels multi-select, research type dropdown incl. "All",
  due date/time, accepted file types, max files, allow-late toggle). Follows the
  existing `Index.vue` sibling page's AppModal + AppTable conventions.
- `ResearchRequirements/Show.vue` — assignment grid (group, adviser(s), status badge,
  submitted files, notes, review actions with a comment field for "Return").
- New sidebar entry under Faculty Loading, gated on the same permission as the
  existing Research Advisories link.

## Phase 2 — Adviser submission & review workflow

**Schema:**

```
research_requirement_submissions
  id
  research_requirement_assignment_id  FK -> research_requirement_assignments, cascade
  submitted_by           FK -> users
  notes                   text, nullable
  submitted_at            datetime
  is_late                 boolean
  review_status            enum(pending, accepted, returned), default pending
  review_comment           text, nullable
  reviewed_by              FK -> users, nullable
  reviewed_at               datetime, nullable
  timestamps

research_requirement_submission_files
  id
  research_requirement_submission_id  FK -> research_requirement_submissions, cascade
  original_filename       varchar(255)
  s3_key                   varchar(500)
  mime_type                varchar(100)
  size_bytes                unsignedInteger
  timestamps
```

Each submission is a new row (full history preserved across resubmissions after a
"returned" decision); `research_requirement_assignments.status` is denormalized from
the latest submission's `review_status` for fast dashboard/list queries.

**File handling:** base64-in-JSON upload, decoded server-side, `Storage::disk('s3')`
— exact pattern already used by `DocumentTrackingController`. Files served via a new
proxy route (ownership or coordinator-permission checked), never a direct S3 URL.
10MB per-file cap and a MIME whitelist (matching the Chat module's hardening:
pdf/doc/docx/xls/xlsx/ppt/pptx/jpg/png/zip, excluding svg/executables), further
restricted by the requirement's own `accepted_file_types` when set. `max_files` cap
enforced server-side.

**Authorization:** a user may submit for an assignment only if they hold a
non-`dropped` `ResearchAdvisory` row on that assignment's `research_group_id` (lead
or co-adviser, either qualifies). Review actions require
`faculty_loading.research_advisories`/`faculty_loading.manage` — an adviser can never
review their own submission.

**Late submission rule:** blocked with a 422 only when `allow_late_submission=false`
AND the assignment's current status is `pending` (never yet submitted) AND
`now() > due_at`. A `returned`-for-revision assignment can **always** be resubmitted
regardless of the deadline — the coordinator initiated that exception.

**Routes** (prefix `faculty-loading/my-research-requirements`, middleware
`permission:faculty_loading.view_own`):

```
GET  /                                     my requirements (pending/submitted/returned/accepted)
POST /{assignment}/submissions             submit (files_base64[], notes)
GET  /files/{fileId}                       S3 proxy (ownership or coordinator-checked)
```

**Frontend:**
- `MyResearchRequirements.vue` — list grouped by status, deadline countdown/overdue
  badge, submit modal (drag-drop → base64, notes textarea, shows `accepted_file_types`/
  `max_files` constraints inline). New sidebar entry visible to any user holding
  `faculty_loading.view_own` who has at least one active research advisory.

## Phase 3 — Notifications

Reuses the existing bell + email notification plumbing (same shape as Issuances'
per-recipient job pattern).

| Event | Trigger | Recipient(s) | Job |
|---|---|---|---|
| Requirement posted | fan-out creates assignment | every adviser on the group | `NotifyResearchRequirementCreated` (queued) |
| Submission received | adviser submits | requirement's `created_by` | `NotifyResearchSubmissionReceived` (queued) |
| Reviewed | coordinator accepts/returns | submitting adviser | `NotifyResearchSubmissionReviewed` (queued) |
| Deadline reminder | scheduled, due_at within 3 days, status in (pending, returned), `reminder_sent_at` null | every adviser on the group | scheduled command |
| Overdue | scheduled, due_at passed, status in (pending, returned), `overdue_notified_at` null | adviser + requirement's `created_by` | scheduled command |

New scheduled artisan command `research:requirement-reminders` (daily, registered in
the existing Kernel schedule), guarded by the `reminder_sent_at`/`overdue_notified_at`
timestamp columns so each notification fires exactly once per assignment.

## Phase 4 — Coordinator dashboard / reporting

`ResearchRequirements/Index.vue` shows, per requirement: total assignments, submitted
count, accepted count, returned count, overdue count, and a compliance percentage.
`Show.vue`'s assignment grid is filterable by status. No export in this phase (flagged
as a natural follow-up, not built now — YAGNI until asked for).

## Testing Strategy

- Feature tests: requirement creation + fan-out (incl. grade/type scoping), exception
  add/exclude, adviser submission (incl. late-block, file validation, max-files cap),
  review transitions (accept/return/resubmit), authorization boundaries (adviser
  cannot review, non-group-member cannot submit).
- Unit tests: `ResearchGroupResolver` (create/reuse/re-resolve on rename),
  `RequirementFanoutService` (scope matching, idempotent sync, exceptions respected).
- Notification tests via `Notification::fake()`/`Queue::fake()` — assert dispatch, not
  real sends; assert guard columns prevent duplicate reminder/overdue notifications on
  a second scheduled run.
- Backfill command tested against a seeded set of co-advised groups to confirm correct
  grouping and no duplicate `research_groups` rows.

## Open Items Flagged to User (both accepted in review)

1. The `research_groups` refactor (Phase 0) is the largest structural change here —
   confirmed as the right call given the explicit "refactor" ask and the co-adviser
   visibility problem.
2. Submissions are group-level only, not per-student — confirmed this matches actual
   PSHS research submission practice.
