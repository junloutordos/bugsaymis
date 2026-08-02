# Dyna — Premium UX & Full-Profile Knowledge Expansion

**Date:** 2026-08-02
**Status:** Approved, pending implementation plan

## Context

Dyna (native macOS AI assistant, Bedrock-backed) shipped Phase 1+2 earlier today: 22 tools,
Google Sign-In, an interim icon, and a genuinely functional chat loop once today's session
also fixed the Bedrock model-access blocker (switched to `openai.gpt-oss-120b-1:0` after
discovering the account's AWS reseller/channel-program restriction blocks all Anthropic
models) and two real serialization bugs (empty-array JSON schema, raw Collection/Carbon
objects in tool results).

With the chat loop actually working end-to-end, two gaps became obvious:

1. **The UI is functionally complete but visually bare** — default SwiftUI system styling,
   plain `Text` bubbles, no theming, no branding, no rich formatting for structured answers.
2. **Dyna can only answer aggregate/analytics questions.** Its two person-level lookup tools
   (`get_employee_info`, `get_student_info`) each return a small fixed set of fields. Asking
   anything outside that fixed set — "what's this teacher's leave balance", "has this student
   filed a SALN... no wait, has this employee filed a SALN, what's this student's GWA this
   quarter" — gets a "no data source for that" response even though the data exists in Atlas.

This spec covers both. They're independent subsystems (frontend polish vs. backend data
surface) but shipping together since they're both scoped to the same app in the same session.

## 1. Visual Design & Theming

Selected via visual-companion mockup comparison (three initial directions — Minimal Native,
Rich Data Cards, Warm Assistant — narrowed to Warm Assistant's layout with a palette
correction from warm-orange to royal navy blue, then confirmed in both Light and Dark).

- **Layout:** keep the existing `NavigationSplitView` (sidebar + chat detail) structure —
  it's the right native pattern, just needs restyling, not rearchitecting.
- **Tone:** avatar-led assistant messages (small circular avatar next to Dyna's replies),
  friendlier spacing and bubble treatment than the current flat gray boxes.
- **Color:** royal navy blue accent — approximately `#1e3a5f` (light mode) / `#2b4c78` (dark
  mode) for the user-message bubble, active sidebar item, and primary buttons. Replaces the
  current bare `Color.accentColor` / `Color.gray.opacity(0.15)` styling in `ChatView.swift`
  and `ConversationListView.swift`.
- **Theming:** System / Light / Dark, matching macOS convention (same pattern as Mail, Xcode).
  Defaults to following `NSApp.effectiveAppearance` / `@Environment(\.colorScheme)`; an
  explicit override is stored via `AppConfig` (already the pattern used for
  `DYNA_API_BASE_URL`) and exposed as a picker in the existing `MenuBarExtra` in
  `DynaApp.swift`.
- **Scope of restyle:** `ChatView`, `ConversationListView`, `LoginView`, the `MenuBarExtra`
  menu, and window chrome (title bar tint, sizing) — the full app, not just chat bubbles.
- **Branding:** the existing Atlas-mark icon (generated in the earlier expansion session)
  gets used as the avatar/mark inside the app itself, not just as the `.app` icon — e.g. in
  the login screen and as the assistant-message avatar.

## 2. Rich Message Rendering

Assistant messages switch from plain `Text` to Markdown rendering (bold, bullet lists, simple
tables), since full-profile answers (Section 3) will routinely return multi-fact structured
data, not one-line answers.

- **Library:** add `swift-markdown-ui` (gonzalezreal/swift-markdown-ui) via SPM — same
  dependency-management pattern already used for `GoogleSignIn-iOS`. Native
  `AttributedString(markdown:)` was considered but doesn't render tables or nested lists,
  which the full-profile answers will need.
- User's own messages stay plain `Text` — no reason to Markdown-parse what the user typed.
- This only touches `ChatView.swift` and `Message.swift` (rendering, not the network layer).

## 3. Atlas Knowledge Expansion — Full-Person Profiles

**Scope decision:** "Any question about Atlas" was narrowed, through discussion, to mean
comprehensive **person-level** Q&A (employee or student), not a conceptual FAQ layer about
what Atlas's modules are, and not a how-to/support-assistant role for non-MANCOM users. The
aggregate/analytics tools (headcount, leave trends, executive-dashboard adapters, etc.) are
unchanged — this is purely about deepening what Dyna knows about *a specific person*.

**Domain coverage:** everything at once (not phased), per explicit decision — this is a
larger scope than a typical Dyna tool addition and should be expected to surface real schema
gotchas during implementation, same as the Phase 2 expansion earlier today.

- **Employee domains:** leave (balance by type + recent application history), DTR (recent
  summary/patterns, not just latest record), PDS (education, work experience, trainings
  summary), SALN filing status, IPCR history (not just current period), faculty loading (if
  applicable — current teaching assignments/load), payroll (recent net pay/deductions
  summary), committee memberships, Digital ID status, recruitment history (if hired via the
  Recruitment module), WFH attendance summary (if applicable).
- **Student domains:** academic record (grades per subject/quarter, GWA), attendance history
  (homeroom + gate, cutting flags), discipline cases (already covered — carried over
  unchanged), library borrowing history, competitions participation, enrollment history
  across school years, section/adviser assignment, guardian contact info (standard SIS data).
- **Health/Guidance:** explicitly **included**, under the same rule as everything else — see
  Permission Model below. This was a deliberate confirmation, not an oversight; the
  alternative (blanket-excluding Health/Guidance from Dyna regardless of the asker's actual
  web permissions) was considered and rejected in favor of consistency with the existing PII
  policy.
- Exact field names/columns for each domain are **not** finalized in this spec — per this
  session's established pattern (see the Phase 2 expansion's separate "grounded backend"
  pass), real schemas get verified during implementation planning, not guessed here. What's
  fixed here is which domains are in scope, not their exact shape.

### Architecture: two comprehensive tools, not fifteen granular ones

Mirroring the existing one-tool-per-stat pattern for this expansion would mean 15-20+ new
tools, pushing Dyna's total tool count from 22 to 40+. Rejected, for two reasons specific to
Dyna's current state (not a general objection to granular tools):

1. `DynaToolRegistry::toBedrockToolConfig()` sends every registered tool's full spec on
   **every single turn** — 40+ tool definitions is real token overhead on every message, not
   just when relevant to the question asked.
2. Dyna currently runs on `openai.gpt-oss-120b-1:0` (open-weight, not a frontier model) —
   forced by today's discovery that the AWS account's reseller/channel-program status blocks
   all Anthropic models on Bedrock. A smaller model reliably picking the right tool out of
   40+ options is a real risk, not a theoretical one, given this constraint.

**Decision:** two new tools — `get_employee_full_profile(identifier)` and
`get_student_full_profile(identifier)` — each internally aggregating every in-scope domain
for that person into one structured response in a single tool call, gated section-by-section
by whatever the requesting user could already see for that person in the web app (extending
the exact pattern `GetStudentInfoTool` already uses to conditionally include
`discipline_cases` based on the `discipline.view` permission). The existing
`get_employee_info` / `get_student_info` tools either get absorbed into these two or kept as
thin summary-only aliases — an implementation detail to resolve during planning, not a
product decision.

### Permission model (extends existing policy, no new decision)

Unchanged from the Phase 2 PII policy already in place: every section of a full-profile
response only includes what the requesting user could already see for that person in the web
app. A Division Chief asking about a student outside their division, or an employee outside
their scope, gets the same division-locking/module-permission behavior the web app already
enforces — `get_employee_full_profile`/`get_student_full_profile` are a new *shape* of
lookup, not a new *scope* of access.

## Non-Goals (explicitly out of scope for this spec)

- Conceptual "what is Atlas / how does module X work" FAQ capability — not what was meant by
  "any question about Atlas" once clarified; may be worth a future spec, not this one.
- How-to/support-assistant behavior for non-MANCOM users (faculty/staff asking "how do I file
  leave") — would require rethinking Dyna's audience/permission model entirely; out of scope.
- Any change to the aggregate/analytics tools (`get_headcount`, `get_leave_trends`, the 9
  executive-dashboard adapters, etc.) — untouched by this spec.
- Real-time/streaming responses — Dyna still uses synchronous `converse()`, unchanged.
- iOS/iPadOS companion app — macOS only, unchanged.

## Testing

- SwiftUI view changes: existing `DynaTests` target pattern (Swift 6 strict concurrency,
  `@MainActor` view models) — add snapshot/rendering coverage for the Markdown renderer at
  minimum, since that's genuinely new behavior, not just restyling.
- New backend tools: TDD per existing convention (factory-backed feature tests, migration
  discipline where new columns are needed) — same pattern as all 22 existing Dyna tools.
- Given the "everything at once" scope, expect a real schema-grounding pass (Explore-heavy,
  not implementation-heavy) before the implementation plan is written, matching how the
  Phase 2 expansion's backend plan was grounded against verified schemas before execution.
