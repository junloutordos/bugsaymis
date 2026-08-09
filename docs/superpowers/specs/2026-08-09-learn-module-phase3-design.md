# Learn Module (LMS) — Phase 3: Quizzes / Assessment Engine — Design

Date: 2026-08-09
Status: Approved, ready for implementation plan

## Problem

Learn currently has no quiz capability — instructors either use Assignments (text/file/link
submissions, manually graded) or the existing live Quiz module elsewhere in this codebase.
That existing module (`App\Models\Quiz`, `App\Services\Quiz\QuizSessionService`, real-time
leaderboards via broadcasting) is a **completely separate, unrelated system**: a live,
Kahoot-style shared session where players join and answer in real time. This spec is for
something different — an async, LMS-style graded quiz that lives inside a Learn course,
students complete on their own time, and the instructor grades (auto- where possible, manually
where not) — the Canvas/Moodle/Blackboard model. No file, table, model, or route in this spec
touches the existing live Quiz module in any way; the two systems share nothing but a name.

## Program roadmap (reference only — not this spec)

| Phase | Scope | Depends on |
|---|---|---|
| 1 (shipped) | Course shell: courses, Modules, Pages, Files, syllabus, announcements | Faculty Loading, `student_enrollments` |
| 2 (shipped) | Assignments, submissions, points/rubric grading | Phase 1 |
| 2b (shipped) | Push a graded assignment's scores into Class Record | Phase 2 |
| 2c (shipped) | Reusable/cross-course rubric bank | Phase 2 |
| **3 (this spec)** | Quizzes: question bank, attempts, auto+manual grading, Class Record push, item analysis | Phase 1, Phase 2b (shared push service) |
| 4 | Discussions/forums | Phase 1 |

## Key decisions (resolved during brainstorming, not open questions)

1. **New polymorphic `ModuleItem` type.** `Quiz` becomes a fourth `itemable` type alongside
   Page/File/Assignment, reusing all of the module/item CRUD, publish/reorder/delete machinery
   Phase 1/2 already built. No changes needed to that machinery.
2. **Lock quiz structure once it has real attempt data.** `learn_quizzes.is_locked` flips to
   `true` on the first *submitted* attempt against it. While locked, existing questions/options
   cannot be edited or deleted (adding brand-new questions is still allowed, since they can't
   corrupt scores nobody has answered yet). This prevents an instructor from silently corrupting
   historical grades by editing a question's correct answer or point value after students have
   already been scored against it — mirrors `ClassRecordQuarter.is_locked` and WAT's
   plotting-deadline pattern already established in this codebase.
3. **Question bank: copy-on-save, copy-on-apply — never a live reference.** Same safety
   reasoning as Phase 2c's rubric templates, for the same failure mode: editing a bank question
   must never reach into a quiz a student has already been graded on. A bank item has no foreign
   key to any `learn_quiz_questions` row in either direction.
4. **Multiple attempts, highest score counts.** Instructor sets an optional `max_attempts`;
   the student's course-facing/gradebook score is `max(score)` across their finalized attempts,
   computed live — never stored redundantly, consistent with `Course::isReadOnly()` and friends.
5. **Time limit is server-authoritative via lazy finalization, not a cron job.** A client-side
   countdown drives the normal auto-submit call, but since a dropped connection can't guarantee
   that call fires, an unsubmitted attempt past its deadline is finalized on its *next touch*
   (student reopening it, instructor viewing the roster) — `submitted_at` is backfilled to
   `started_at + time_limit_minutes`, `auto_submitted = true`, and it's graded on whatever was
   autosaved. This matches the "compute live" convention already used throughout Learn instead
   of introducing a scheduled job.
6. **Autosave per answer, not one final payload.** Each answer is saved to the server as the
   student answers it. A lost connection loses at most one unsaved answer, not the whole attempt.
7. **Class Record push reuses Phase 2b's service via a shared contract.** `Assignment` and
   `Quiz` both implement a small `HasClassRecordLink` interface (assignment/quiz id, `maxScore()`,
   `classRecordAssessment()` relation, `pushed_at`); `ClassRecordPushService`'s `link()`/`push()`
   methods are widened to accept either, with zero change to Phase 2b's existing WAT-safety
   invariant — Learn still never creates or dates a `ClassRecordAssessment` itself.
8. **Analytics are computed live, not stored.** Item analysis and the course trend view read
   directly from `learn_quiz_attempts`/`learn_quiz_attempt_answers` on each request — no
   materialized/cached aggregate tables, consistent with the rest of Learn.

## Architecture & item integration

`Quiz` belongs to a `ModuleItem` exactly like `Assignment` does — appears in the course's module
list, publishable/reorderable/deletable through the existing generic item endpoints with no
changes there. `ModuleItemController` gains a `storeQuiz()`/quiz-question sibling to
`storeAssignment()`, following the same shape.

## Data model

### `learn_quizzes`
- `title`, `instructions`
- `time_limit_minutes` (nullable — untimed if null)
- `max_attempts` (nullable — unlimited if null)
- `questions_to_draw` (nullable int — draws a random N-subset per attempt; null = use every
  authored question)
- `shuffle_questions` (bool), `shuffle_options` (bool)
- `due_at` (nullable timestamp — parity with Assignment; also the trend chart's x-axis)
- `is_locked` (bool, default false)
- `class_record_assessment_id` (nullable FK, `nullOnDelete`), `pushed_at` (nullable timestamp) —
  same two columns Phase 2b added to `learn_assignments`

### `learn_quiz_questions`
- `learn_quiz_id`, `question_type` (`multiple_choice` | `true_false` | `multiple_select` |
  `short_answer` | `essay`)
- `prompt` (rich text, sanitized + KaTeX-rendered at display time), `points`, `position`
- `difficulty` (nullable enum: `easy` | `medium` | `hard`)

### `learn_quiz_question_options`
(for `multiple_choice` / `true_false` / `multiple_select`)
- `learn_quiz_question_id`, `option_text`, `is_correct`, `position`

### `learn_quiz_question_accepted_answers`
(for `short_answer` — instructor can list several accepted phrasings)
- `learn_quiz_question_id`, `answer_text`

### `learn_quiz_attempts`
- `learn_quiz_id`, `student_id` (unsignedInteger, no FK — same convention as every other
  student-referencing column in this codebase, since `students` is legacy MyISAM), `attempt_number`
- `question_order` (JSON array of question IDs — the shuffled/sampled presentation order for
  this attempt)
- `started_at`, `submitted_at` (null while in progress), `auto_submitted` (bool)
- `score` (nullable — null until every essay question on the attempt has been graded)

### `learn_quiz_attempt_answers`
- `learn_quiz_attempt_id`, `learn_quiz_question_id`
- `answer_text` (short_answer/essay only)
- `is_correct` (nullable — null for essay until graded; for `multiple_select`, true only on full
  credit, partial credit lives in `points_earned` instead)
- `points_earned` (nullable), `graded_at`, `graded_by` (essay only)

### `learn_quiz_attempt_selected_options`
(pivot — used uniformly for `multiple_choice`/`true_false`/`multiple_select`; single-select
types simply have exactly one row)
- `learn_quiz_attempt_answer_id`, `learn_quiz_question_option_id`

### Question bank (no FK to any table above)
- `learn_quiz_question_bank_items`: `user_id` (owner), `question_type`, `prompt`, `points`,
  `difficulty`
- `learn_quiz_question_bank_options`: `learn_quiz_question_bank_item_id`, `option_text`,
  `is_correct`, `position`

## Question bank workflow

- **Saving:** while authoring a question, a "Save to bank" checkbox + name field copies that
  question's full content (including options) into a new bank item — independent rows, same
  pattern as Phase 2c.
- **Applying:** a "Start from bank" dropdown in the question builder pre-fills a new question's
  fields client-side from a bank item; nothing is sent to the server until the question itself is
  submitted through the normal question-creation endpoint.
- **Managing:** rename/delete only, ownership-checked (`user_id === $user->id`) — identical
  shape to `RubricTemplateController` from Phase 2c, just for question-bank items instead of
  rubric templates.

## Attempt lifecycle

1. **Starting:** student clicks "Start attempt" — blocked if `max_attempts` reached. Creates a
   `learn_quiz_attempts` row with `started_at = now()`. `question_order` is populated by:
   sampling `questions_to_draw` questions at random (or all of them if null), then shuffling that
   set if `shuffle_questions` is true. Each `multiple_choice`/`multiple_select` question's
   presented option order is a deterministic shuffle seeded by `attempt_id + question_id` when
   `shuffle_options` is true — no extra storage, reproducible on reload/grading review.
2. **Answering:** each answer autosaves via a per-question PUT as the student answers it.
3. **Time limit / lazy finalization:** client countdown auto-submits at zero; any subsequent
   load of an unsubmitted attempt past `started_at + time_limit_minutes` finalizes it server-side
   on the spot (see Decision 5).
4. **Auto-grading on submit:**
   - `multiple_choice`/`true_false`: correct iff the single selected option's `is_correct` is true.
   - `multiple_select`: `points_earned = points × max(0, correct_selected − incorrect_selected) /
     total_correct_options`; `is_correct = true` only when that equals `points` exactly.
   - `short_answer`: case-insensitive, trimmed match against any row in
     `learn_quiz_question_accepted_answers`.
   - `essay`: `is_correct`/`points_earned` left null, pending manual grading.
   - The attempt's overall `score` is set once every essay question on it has a non-null
     `points_earned`; until then it stays null (mirrors how a rubric-graded Assignment submission
     isn't "graded" until every criterion is scored).
5. **Grade visibility:** auto-graded results shown immediately; essay feedback/points appear
   once the instructor grades that item.
6. **Multi-attempt scoring:** the gradebook-facing score is `max(score)` across the student's
   finalized (non-null-score) attempts, computed live.

## Instructor authoring & grading

- **Authoring:** "Add quiz" form (title, instructions, time limit, max attempts,
  `questions_to_draw`, shuffle toggles, due date) plus a question builder — add/remove/reorder
  questions, each with a type-specific sub-form (options + correct flag(s) for
  multiple_choice/true_false/multiple_select, accepted-answer list for short_answer, just
  prompt+points for essay), a difficulty picker, and the bank save/apply controls above.
- **Grading queue:** per-quiz view listing every attempt across the roster (student, attempt #,
  status, auto-graded subtotal, essay items still pending). Selecting an attempt shows each
  question with the student's answer; essay items get a score input + feedback comment, same
  widget style as Assignment's flat-points grading.
- **Reopening:** scoped to one attempt (clears its grading fields, unlocks it for re-editing) —
  same pattern as Assignment's `reopen`, just per-attempt instead of per-submission.
- **Editing after `is_locked`:** existing questions become read-only; new questions can still be
  added.

## Class Record push & permissions

- `ClassRecordPushService::link()`/`push()` (Phase 2b) are widened to accept anything
  implementing `HasClassRecordLink` (both `Assignment` and `Quiz`). Linking still requires a
  pre-existing, instructor-created `ClassRecordAssessment` picked from a dropdown — Learn never
  creates or dates one itself, preserving the WAT-safety invariant verified in Phase 2b. Pushing
  copies each student's live `max(score)` across attempts into `ClassRecordScore`.
- No new permission strings: quiz authoring/grading reuses `Course::canEdit($user)`; taking a
  quiz reuses `Course::isVisibleToStudent()`; question-bank rename/delete reuses the same
  `user_id` ownership check as Phase 2c's rubric templates.

## Analytics

- **Item analysis** (per quiz): for each question, `avg(points_earned / points_possible)` across
  attempts that answered it — one uniform score-percentage metric across every question type
  (correct/incorrect/partial all fold into it) — shown alongside its difficulty tag; plus the
  attempt-level score distribution (min/max/avg/median) for that quiz. Both computed live.
- **Course trend view**: average score % per quiz in the course, ordered by `due_at` (falling
  back to `created_at`), plus average score-percentage pooled by difficulty tag across those
  quizzes. Scoped to a single course — not cross-course.

## LaTeX rendering

KaTeX added as a new npm dependency (client-side only, same integration pattern as DOMPurify
from Phase 1). Prompts stay rich text, sanitized with DOMPurify as always; after sanitization, a
KaTeX auto-render pass converts `$...$` (inline) / `$$...$$` (block) segments into rendered math
wherever a prompt is displayed — authoring preview, student attempt view, grading view, item
analysis.

## Testing

- Quiz/question CRUD, including the lock-on-first-submitted-attempt rule (existing
  questions/options reject edits/deletes once locked; new questions still allowed).
- Each auto-grading path independently: multiple_choice, true_false, multiple_select (proportional
  partial credit — full, partial, zero, and the negative-selection floor-at-zero case),
  short_answer (case-insensitive/trimmed match, including a near-miss that correctly fails).
- Essay manual grading: attempt `score` stays null until every essay item is graded, then
  computes correctly.
- `max_attempts` enforcement; highest-score-wins across multiple finalized attempts.
- Lazy expiry finalization: an attempt loaded past its deadline auto-submits with only the
  previously-autosaved answers, `auto_submitted = true`.
- Shuffle determinism: the same attempt reloaded twice yields the same question/option order;
  `questions_to_draw` sampling respects the configured count and never exceeds total questions.
- Question bank save/apply independence: same assertion style as Phase 2c — editing/deleting
  either side never touches the other.
- Class Record push/link against a `Quiz`, reusing Phase 2b's test shape, including the explicit
  WAT invariant test (`plotted_at`/`activity_date` byte-for-byte unchanged after quiz link+push).
- Item analysis / course trend view: correct aggregation math against known fixture data.

## Out of scope for Phase 3

- Any change to the existing live Quiz module (`App\Models\Quiz`) — fully separate system.
- Cross-course analytics (trend view is single-course only).
- Per-student item-analysis drill-down (e.g., "which students missed question 4") — the item
  analysis view is aggregate-only in v1.
- Rich media (images/video) embedded directly in question prompts beyond what the existing rich
  text editor already supports.

## Rollout

All new tables, purely additive — no destructive migrations, no blue-green expand/contract
concerns. The only change to already-shipped Phase 2b code is extracting `Assignment`'s existing
Class-Record-link behavior into a small shared `HasClassRecordLink` interface that `Quiz` also
implements; `Assignment`'s own behavior is unchanged.
