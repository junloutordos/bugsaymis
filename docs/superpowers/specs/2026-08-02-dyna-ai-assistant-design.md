# Dyna — AI Assistant for Atlas (Design Spec)

**Date:** 2026-08-02
**Status:** Approved for planning
**Owner:** Junlou Tordos

## Summary

Dyna is a native macOS app that gives the Campus Director and MANCOM (Division Chiefs) a
conversational, analytics-and-insights AI assistant grounded in live Atlas data. It answers
questions across HR, payroll, IPCR, leave, enrollment, and other modules by calling
permission-scoped Laravel tools (not by querying a vector store), backed by Claude Sonnet 5
on Amazon Bedrock.

**Scope for v1:** read-only analytics and insights. No write actions, no approvals, no data
mutation of any kind.

## Capability & data scope (approved)

- **Capability:** read-only analytics/insights only. No proactive alerts, no write-capable
  actions, in v1.
- **Data scope:** mirrors whatever the logged-in user already sees in the web app — i.e. a
  Division Chief's questions are scoped to their division the same way `/executive-dashboard`
  already scopes their view; the Campus Director sees campus-wide. Dyna introduces no new
  visibility beyond what each user's existing permissions already grant.
- **Access:** new permission `atlas.dyna.access`, seeded via `DynaPermissionSeeder` to the
  same role set that already sees `/executive-dashboard` (OCD + Division Chiefs).

## Architecture

```
Dyna.app (macOS, SwiftUI)
   │  HTTPS, streaming (SSE via ConverseStream)
   │  Sanctum token (Keychain-stored)
   ▼
Atlas\Dyna module (Laravel, existing ECS Fargate service — no new infra)
   │  DynaController -> DynaOrchestratorService
   │  IAM task role: bedrock:InvokeModel / InvokeModelWithResponseStream,
   │  scoped to the specific Sonnet 5 inference-profile ARN
   ▼
Amazon Bedrock — Converse API (streaming), Claude Sonnet 5, cross-region inference profile
   │  tool_use turns
   ▼
DynaOrchestratorService executes registered "Dyna Tools" AS THE REQUESTING USER
   │  (reuses existing hasPermission()/scoping logic — no new authz to invent)
   ▼
Existing Eloquent models / Services (LeaveApplication, Payroll, IPCR, Enrollment, ...)
```

**Turn flow:**
1. User message -> `POST /atlas/dyna/chat` (SSE), with conversation ID.
2. Orchestrator sends system prompt + tool schema (cached across calls) + history to Bedrock
   `ConverseStream`.
3. Model returns `tool_use` block(s) -> orchestrator executes the matching Dyna Tool class,
   scoped by the authenticated user's existing permissions.
4. Tool result returned to the model as `tool_result`; loop continues until the model returns
   final text, which streams to the client token-by-token.
5. Full exchange (question, tool calls, answer) persisted to `dyna_conversations` /
   `dyna_messages` for history and audit.

**Why tool-use instead of RAG:** analytics questions ("how many," "what's the trend," "compare
X to Y") need precise, live aggregation — vector similarity search is the wrong tool for that
and would leave answers a sync cycle stale. The standard RAG backing store (OpenSearch
Serverless) also carries a fixed ~$700+/month minimum, wildly disproportionate at this scale.
Tool-use keeps numbers grounded in real queries, inherits the existing permission system for
free, and is the cheapest option to run and to extend (one new tool per question category).
RAG (via a much cheaper S3-Vectors-backed Knowledge Base) is deferred to a future phase, only
if unstructured policy/handbook document Q&A becomes a real need.

**Initial Dyna Tools (v1, ~5-6):** leave trends, payroll summary, IPCR completion rate,
enrollment stats, headcount. Additional tools added incrementally based on real usage.

## Security & compliance

- **Least privilege on AWS:** ECS task role scoped to `bedrock:InvokeModel(WithResponseStream)`
  on the specific inference-profile ARN only — not `bedrock:*`. No long-lived Bedrock API keys
  anywhere in app or config.
- **Read-only by construction:** no write-capable tool exists in the registry, so even a
  confused model turn cannot mutate data.
- **Guardrails (recommended):** Bedrock Guardrails applied to input+output as defense in depth
  — keeps Dyna on-topic (Atlas data only, not general-purpose chat) and adds a PII-handling
  check behind the permission-scoped tools.
- **`maxTokens` always explicit** on every Bedrock call (standard Bedrock best practice —
  avoids silent over-reservation of quota and throttling).
- **Audit trail:** `dyna_messages` records every question, tool call, and answer per user
  (same spirit as the existing `ApprovalSnapshot` audit pattern). CloudTrail logs every
  Bedrock API call independently.
- **Data residency (explicitly approved by user):** no Bedrock region exists in the
  Philippines. Using a `us.`-prefixed cross-region inference profile means data referenced in
  a Dyna answer transits to US AWS regions for inference only — Bedrock does not store inputs
  or train on them, and RDS/S3 stay in `ap-southeast-1` as today. This is a deliberate,
  approved deviation from the current all-`ap-southeast-1` posture, scoped to Dyna only.

## macOS app (Dyna.app)

- **Stack:** native Swift/SwiftUI, macOS Sonoma+.
- **Distribution:** signed & notarized `.dmg`, direct download — same pattern as AtlasGo, not
  the Mac App Store. Reuses the **existing Apple Developer Program membership already used for
  AtlasGo** — no new Apple cost.
- **UX:** menu bar presence + full chat window, sign-in with the user's existing **Atlas
  Account** (not a separate identity system), streamed markdown-rendered responses (tables for
  breakdowns), conversation history sidebar, dark mode following system appearance, a
  keyboard-shortcut summon (Spotlight-style), and seeded example prompts on first launch since
  MANCOM users won't know Dyna's scope out of the box.
- **v1 explicitly excludes:** write actions, file uploads, general-purpose chat outside Atlas
  data.

## Model & cost

- **Model:** Claude Sonnet 5 on Bedrock — selected over Haiku 4.5 deliberately. At this volume
  (~8 users, ~1,000-1,500 questions/month) the price gap to Haiku is only ~$10-20/month, while
  Dyna's job (deciding which tool(s) to call across a multi-step question, then synthesizing an
  actual insight for an executive) needs stronger reasoning than Haiku is tuned for. Revisit
  only if usage scales 10-20x and cost becomes a real line item — a hybrid (Haiku for
  single-fact lookups, Sonnet for multi-tool/insight questions) would be the next step then,
  not now.
- **Pricing basis (checked live, not from training memory):** Sonnet 5 on Bedrock is
  $2/$10 per million input/output tokens (promotional through 2026-08-31, stepping to $3/$15
  after), with prompt-cache reads ~$0.20/M and writes ~$2.50/M. Prompt caching is used on the
  repeated system prompt + tool schema block to cut cost.

| Item | Estimate |
|---|---|
| Bedrock inference (Sonnet 5, cached) | ~$15-35/mo |
| Bedrock Guardrails (optional, recommended) | ~$5-10/mo |
| New AWS infra (ECS/RDS/S3) | $0 — rides existing Fargate service |
| Apple Developer Program | $0 incremental — reuses AtlasGo's existing membership |
| **Total AWS run-rate** | **~$20-45/month**, room to roughly triple in usage before $100/mo |

This sharpens the $25-100/mo range already sanity-checked for a Bedrock chatbot in this system
on 2026-07-29.

## Build phases

1. **Foundation** — Bedrock model access request, IAM role + scoped policy, `atlas.dyna.access`
   permission + seeder, `dyna_conversations`/`dyna_messages` migrations.
2. **Backend brain** — `DynaOrchestratorService`, initial ~5-6 tool registry, streaming chat
   endpoint, conversation persistence. Testable standalone (temporary web/CLI harness) before
   the native client exists — de-risks the Bedrock integration first.
3. **Dyna.app (macOS)** — SwiftUI client: sign-in, streaming chat UI, history, menu bar
   presence, signing/notarization, direct-download `.dmg` distribution.
4. **Hardening** — Guardrails wiring, audit-trail review UI (who asked Dyna what), expand tool
   registry based on real usage.
5. **Future, not now** — proactive alerts, policy-document Knowledge Base (S3-Vectors-backed),
   write-capable actions if ever wanted.

## Open items for the implementation plan

- Exact list and JSON schemas of the initial ~5-6 Dyna Tools.
- Conversation history trimming/summarization strategy for long threads (context-window and
  cache-efficiency management).
- Whether Guardrails ships in v1 or is deferred to the Hardening phase.
