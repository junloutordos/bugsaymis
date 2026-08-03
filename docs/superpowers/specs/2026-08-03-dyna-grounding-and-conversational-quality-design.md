# Dyna — Grounding, Reliability, and Conversational Quality (Design Spec)

**Date:** 2026-08-03
**Status:** Approved for planning
**Owner:** Junlou Tordos
**Relates to:** `2026-08-02-dyna-ai-assistant-design.md`, `2026-08-02-dyna-expansion-design.md`,
`2026-08-03-dyna-full-profile-and-schedule.md`

## Summary

User-reported problem: Dyna gives wrong/unhelpful answers — refusals on questions it should be
able to answer, occasional plainly-wrong numbers, and vague/generic responses that don't sound
like it used real data. User also asked whether Dyna could query the database directly.

Dyna's architecture is already correct at the top level: Bedrock Converse tool-use over 25
curated, permission-gated tool functions backed by live Atlas data — not RAG, not free-form
generation. This spec addresses four concrete, code-level gaps that explain the reported
behavior, and settles the direct-DB-access question as "no, by design" rather than a gap.

## Root cause analysis

1. **Date-grounding bug (previously diagnosed 2026-08-03, never implemented).**
   `DynaOrchestratorService::SYSTEM_PROMPT` is a compile-time `const` that never includes
   today's date, and Bedrock Converse does not inject one automatically. Any relative-date
   question ("today," "this week," "this month") can't be resolved into the `from_date`/
   `to_date` params `get_leave_trends`, `get_gate_attendance_trend`, and similar tools require.
   Nova correctly refuses per its own "never invent data" instruction rather than guessing —
   which reads as "won't answer" but is the instruction working as intended against missing
   input.

2. **No error handling anywhere in the tool-execution path — the largest suspected source of
   bad answers.** `DynaToolRegistry::execute()` calls `$this->tools[$name]->execute($user,
   $input)` with no try/catch; `DynaController::chat()` has no try/catch around
   `$this->orchestrator->reply()` either. If any of the 25 tools throws for any reason —
   a permission edge case, a null relation, a transient DB error, or a bug of the same shape as
   the Carbon-object-leak already found and fixed in 2 of the 25 tools — the entire chat turn
   fails with an uncaught exception, which the user experiences as a broken/generic response,
   not a graceful "I couldn't check that." The Carbon-leak fix from earlier the same day
   (commit `08e07564`) covered exactly the 2 tools that broke in production; a systemic sweep of
   the other 23 was explicitly logged as not done.

3. **System prompt has no conversational guidance.** Current prompt is four sentences of
   strictness only ("always call a tool," "never invent," "say so plainly if no tool fits").
   Nothing instructs tone, how to synthesize several tool calls into one coherent narrative
   answer, or how to explain *why* something can't be answered instead of a flat refusal. This
   reads as curt/robotic even on turns where the underlying data is correct.

4. **Model constraint (documented, explicitly out of scope for this pass).** Dyna runs on
   Amazon Nova Pro; Anthropic/Claude models are blocked account-wide by the AWS reseller
   (Sagesoft Cloud) per prior investigation, unrelated to anything fixable in this codebase.
   Nova Pro is weaker than Claude at strict agentic tool-use and instruction-following. The
   fixes below are chosen because they hold up regardless of which model is behind Converse;
   switching models is a separate, user-owned track (contacting Sagesoft), not part of this
   spec per the user's explicit choice to work within Nova Pro for now.

## Fix 1 — Date grounding

In `DynaOrchestratorService::reply()`, build the `system` block for the Converse call by
appending the current date to `SYSTEM_PROMPT` at request time (it cannot live in the `const`
itself since that's compile-time), e.g. appending a line built from `now()->toDateString()`.
Unblocks every relative-date question across all date-range tools. Covered by a test that
asserts the date appears in the `system` payload sent to the mocked Bedrock client.

## Fix 2 — Graceful tool-failure handling

Wrap the tool dispatch in `DynaToolRegistry::execute()` in a try/catch:

- On exception: `Log::error()` with tool name, input, user id, and the exception — so failures
  are visible in CloudWatch proactively instead of only being found after a user complaint (as
  happened with the Carbon-leak bug).
- Return a structured error payload instead of throwing, e.g. `['error' => 'This data could
  not be retrieved right now.']`, so the Converse loop continues normally and the model gets a
  valid tool result to react to rather than the whole HTTP request 500ing.

`SYSTEM_PROMPT` gets one added instruction: if a tool result contains an `error` key, tell the
user plainly that this specific piece of data couldn't be retrieved right now, rather than
treating the error as "no data exists" or silently omitting it from the answer. This directly
addresses both the "wrong answers" (silent 500s becoming visible, honest responses) and "not
conversational" (a natural sentence instead of a crash) complaints.

Covered by tests: one tool forced to throw, asserting the registry catches it, logs it, and
returns an `error`-keyed array rather than propagating.

## Fix 3 — Systemic Carbon/non-scalar leak sweep

Same method that found the original bug: grep all 25 tools' return-building code (`->map()`
closures, array construction) against every referenced model's `$casts` for date/datetime
keys, and fix any raw property access on a Carbon-cast field (must go through `?->format(...)`
/ `?->toDateTimeString()`, matching the fix already applied to
`GetEmployeeFullProfileTool`/`GetStudentFullProfileTool`). Add the existing
`assertNoNonScalarLeaves` regression assertion to every Dyna tool's test suite — currently only
2 of 25 have it — so this exact bug class can't silently reappear in a tool that's never been
exercised against real Bedrock validation.

## Fix 4 — Conversational system prompt rewrite

Keep all existing strictness (always call a tool before stating a number, never invent
statistics, be plain about limits) and add:

- Natural prose over terse data dumps — explain what a number means, not just the number.
- When a question needs more than one tool call, synthesize the results into one coherent
  answer rather than listing raw tool outputs back to back.
- When something can't be answered, say why in plain terms and, where useful, what's missing
  (e.g. "I'd need a date range to check gate attendance trends — did you mean this week?")
  rather than a flat "cannot determine."

No structural change to the tool-use loop is needed for this — it's a `SYSTEM_PROMPT` content
change, verified by a test asserting the new instructions are present in the prompt sent to
Bedrock (behavioral quality itself isn't unit-testable against a mocked client, so this is a
presence check, not an output-quality check).

## Direct database access — decision: no

Confirmed with the user: Dyna will **not** get a generic "run a query" tool. Reasoning,
carried forward as the standing architectural decision:

- Every one of the 25 existing tools enforces the exact same permission/division scoping the
  web app already applies for that data — a generic query tool would bypass that per-field
  gating entirely.
- A model-constructed query (even parameterized/allow-listed) is reachable by prompt injection
  via user phrasing in a way a fixed, typed tool signature is not.
- The existing pattern — when a real gap is found (as with class-schedule/full-profile last
  session), add a new narrowly-scoped, permission-gated tool — already works and keeps the
  audit surface small and reviewable.

No code change for this item; it's documented here so the decision doesn't need re-litigating
next time the question comes up.

## Out of scope

- Switching the underlying model off Nova Pro (blocked by Sagesoft, user-owned follow-up, not
  part of this pass).
- Any new Dyna tool coverage — this spec is about correctness/reliability of the existing 25
  tools, not adding a 26th.
- Streaming responses (`converseStream`) — unrelated, previously deferred for other reasons.

## Open items for the implementation plan

- Exact wording of the new system-prompt conversational instructions — draft during
  implementation, review before merging since prompt wording is inherently iterative.
- Whether the Carbon-leak sweep (Fix 3) turns up any additional real bugs beyond the pattern
  already fixed — if so, each one gets its own fix + regression test within the same task
  rather than a follow-up spec, since it's the same bug class already scoped here.
