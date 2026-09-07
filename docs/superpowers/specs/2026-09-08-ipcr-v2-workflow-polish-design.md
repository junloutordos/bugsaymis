# IPCR V2 — Workflow, Audit, Signature & Show/PDF Polish — Design

## Background

`docs/superpowers/specs/2026-09-07-ipcr-v2-design.md` shipped IPCR V2's data model
(Strategic/Core/Support functions, `ipcr_v2_records` + item tables) and a workflow
service (`IpcrV2WorkflowService`) that already copies v1's exact status machine
(`New Target → For Review → Targets Approved/Returned for Revision → Submitted for
Rating → Rated & For PMT Review → Submitted to HR/Submitted to PMT → Approved by
PMT/PMT Returned for Revision → Director Signed`), immutability guards
(`assertMutable`/`isFinalized`/`isPeriodClosed`), and a `finalize()` that locks the
record on Director signature.

This spec does **not** change that status set or the workflow's shape (confirmed with
the user — v1's statuses stay as-is). It closes the gaps found between what
`IpcrV2WorkflowService` already enforces and what the user asked for:

1. Every status change needs a visible timestamp + remarks + actor, not just a status
   flip recorded in the generic app-wide `audit_logs` table.
2. Signing moments need a real digital signature (PIN-verified), not just a copied
   `electronic_signature` image.
3. Locking after Director signature already exists (`isFinalized()`), but there's no
   controlled way to reopen a mis-signed record, and no notification when that happens.
4. No email or in-app notifications are wired to any IPCR V2 transition.
5. The Index pages' rating-period/target-generation flow has rough edges.
6. The Show/PDF Rating Summary is missing a "Comments and Recommendations for
   Development Purposes" section and a visible rating date.

## Goals

1. **Per-record audit timeline** — every submit/approve/return/rate/sign/reopen action
   is queryable and displayable with timestamp, actor, remarks, and (for signing
   actions) proof of PIN verification.
2. **Remarks on every return/approve action** — the same UX v1 has today (a rater or
   reviewer can explain why something was returned or approved).
3. **PIN-based digital signature** on every signing moment, reusing the existing
   `DigitalSignatureService`/`users.signature_pin` mechanism already live in Issuances.
4. **Admin-only reopen** of a Director-signed (locked) record, fully audited and
   notified, with no other role able to bypass the lock.
5. **Email + in-app notifications** on every transition, reusing the existing
   `NotificationService`/`RequestStatusNotification` pattern already used by Leave.
6. **Index page polish**: block duplicate generation clearly in the UI (the DB already
   enforces it via a unique constraint), show a status badge + latest remark per
   period row, and a real error state when an employee has no synced functions to
   generate from.
7. **Show/PDF**: add "Comments and Recommendations for Development Purposes" between
   the Rating Summary (+ Legend) and the signature block, and show the rating date on
   the summary header.

## Non-goals

- No change to `IpcrV2WorkflowService::TRANSITIONS` or any status string.
- No change to how Strategic/Core/Support items are generated
  (`IpcrV2GenerationService`, `StrategicFunctionService`, `EmployeeFunctionSyncService`)
  — target/item *generation* logic is out of scope; only the surrounding
  submit/approve/return/notify/lock UX is touched.
- No v1 code path is touched (`EmployeeIPCR`, `IPCRWorkflowService`, v1 Mail classes,
  v1 Blade/Vue pages) — this is IPCR V2-only, per the original spec's isolation
  strategy.
- No new roles or permissions beyond what's listed under Permissions below.

## Data model changes (additive only — safe per the blue-green migration rule)

### `ipcr_v2_records` — new nullable columns
- `remarks` (text) — latest remark, shown at the top of Show (mirrors v1's
  `EmployeeIPCR.remarks` UX).
- `locked_at`, `locked_by_id` (FK `users`) — set by `finalize()` alongside the existing
  `director_signed_at`; distinct column so "is this locked" doesn't require inferring
  it from `status === Director Signed`.
- `reopened_at`, `reopened_by_id` (FK `users`), `reopen_reason` (text).

### New table: `ipcr_v2_status_logs` (append-only)
- `id`, `ipcr_v2_record_id` (FK, cascade delete)
- `from_status`, `to_status` (string, nullable `from_status` for the initial creation)
- `action_type` (string: `submitted`, `approved`, `returned`, `rated`, `signed`,
  `reopened`)
- `remarks` (text, nullable)
- `actor_id` (FK `users`), `actor_role` (string — snapshot of the role the actor acted
  under, since role membership can change later)
- `signed_via_pin` (boolean, default false)
- `signature_snapshot` (text, nullable — copy of the actor's `electronic_signature` at
  the moment of signing, so a later signature-image change never retroactively alters
  an already-signed entry)
- `created_at` only (no `updated_at` — the table is append-only by convention, enforced
  by never calling `update()`/`save()` on an existing row from application code)

This table is purpose-built for a per-record timeline UI. The existing generic
`AuditLogger::log()` call in `IpcrV2WorkflowService::transition()` stays as-is (app-wide
audit coverage); this is an addition, not a replacement.

## Workflow service changes

`IpcrV2WorkflowService::transition()` gains an `array $extra` consumer for remarks/PIN
data already flowing through it, plus a new required-when-signing `?User $actor` and
`?string $remarks` param:

```php
public function transition(
    IpcrV2Record $ipcr,
    string $to,
    array $extra = [],
    ?string $auditAction = null,
    ?User $actor = null,
    ?string $remarks = null,
    string $actionType = 'status_changed',
    bool $signedViaPin = false,
): IpcrV2Record
```

Inside the existing `DB::transaction()`, after the `AuditLogger::log()` call, insert one
`IpcrV2StatusLog` row per transition using the fresh `from`/`to` status, `$actor`,
`$remarks`, `$actionType`, `$signedViaPin`, and (when `$signedViaPin`) a
`signature_snapshot` copied from `$actor->electronic_signature`.

Controllers that currently call `transition()` without remarks (e.g.
`DivisionChiefIpcrV2Controller::returnToEmployee()` at line 68,
`PMTIpcrV2Controller::approve()`/`returnToDivisionChief()` at lines 50/58) start passing
`request()->validate(['remarks' => 'nullable|string|max:1000'])` through — return
actions make `remarks` `required`, approve actions keep it optional, matching v1's
pattern.

## Digital signature

Reuse `DigitalSignatureService::verifyPin()` exactly as `IssuanceController` does today
(check `hasPin`, require `pin` in the request, `abort`/validation-error on mismatch).
Signing moments (submit-for-review, targets-approved, submitted-for-rating, PMT
approve, Director sign) require PIN entry in the action modal; the controller verifies
the PIN before calling `transition(..., signedViaPin: true)`. Non-signing status moves
(e.g. `Submitted to HR → Submitted to PMT` housekeeping transitions) don't require a
PIN.

## Locking & reopen

`isFinalized()`/`assertMutable()` remain the mutation gate — unchanged. New:

- **Permission**: `ipcr.v2.admin.reopen` (Administrator only).
- **Action**: `AdminIpcrV2Controller::reopen()` — requires `reason` (string, required),
  requires the Administrator's PIN, clears `locked_at`/`director_signed_at`, reverts
  `status` to `Submitted to PMT` (the last stage before Director signature — the record
  re-enters PMT's queue rather than skipping back further), writes an
  `ipcr_v2_status_logs` row with `action_type = 'reopened'` and the reason as `remarks`.
  `Director Signed → Submitted to PMT` is not a member of `TRANSITIONS` (Director
  Signed's allowed-list is empty by design, since nothing should normally leave that
  state) — `reopen()` therefore does **not** call `IpcrV2WorkflowService::transition()`.
  It performs its own guarded update (permission + PIN + `isFinalized()` precondition
  check, then a direct status/lock-column write inside a `DB::transaction()`), and
  writes the `ipcr_v2_status_logs` row itself. This keeps the normal transition
  allow-list strict for every other role while giving Admin one explicit, fully-audited
  escape hatch.
- **Notification**: fires to the employee, their Division Chief, and PMT — "This IPCR
  was reopened by an Administrator. Reason: …".

No other role gets an unlock path — matches the approved answer (admin-only unlock with
audit trail).

## Notifications

Reuse `NotificationService` (in-app) and the `RequestStatusNotification` shape already
used by Leave — one call from `IpcrV2WorkflowService::transition()` (or a thin listener
on it) after each successful transition, addressed to whoever is next in the chain
(mirrors v1's per-stage recipient logic) plus the employee themself on
approve/return/sign.

For email, this spec recommends **one parametrized Mailable**
(`App\Mail\IpcrV2StatusMail`) with a status-driven Blade partial, rather than v1's
twelve dedicated classes — same information per stage, less class sprawl. (Flagged as a
deliberate deviation from v1's exact pattern; v1's status set is unchanged, but this is
new code with no existing callers, so YAGNI applies. If you'd rather match v1's
one-class-per-stage convention for maintenance consistency, say so before the plan is
written — it's a small change either way.)

## Index page polish

Scope: `EmployeeIpcrV2Index.vue` (target generation) plus the four reviewer Index pages
(DivisionChief/PMT/HR/Admin), which already exist as thin role-filtered wrappers over
the same `ipcr_v2_records` list.

- **Duplicate generation**: the DB already enforces one record per
  `(user_id, rating_period_id)` via a unique constraint
  (`2026_09_07_100000_create_ipcr_v2_records_table.php:28`) and
  `assertNoDuplicateForPeriod()` already exists in the workflow service — this is
  UI-only polish: disable "Generate" for a period that already has a record and link to
  the existing one instead of surfacing a raw validation error.
- **Status visibility**: each period row shows a status badge and (if present) the
  latest `remarks` truncated inline, consistent with the new `remarks` column.
- **Empty-generation error**: `IpcrV2GenerationService`, when it finds zero
  Strategic/Core/Support items to attach (employee has no synced functions), currently
  either produces an empty record or a generic failure — this spec requires a specific,
  actionable validation message ("No functions are synced for this employee yet — sync
  Employee Functions first.") instead.
- Exact component-level diff is finalized at plan time against the live files, per the
  scope note already given in chat — the behavior above is the contract; the DOM/props
  shape is an implementation detail for the plan.

## Show/PDF additions

In `IpcrV2SummarySection.vue` and `resources/views/ipcr-v2/pdf.blade.php` (currently
rendering the Rating Summary table + Legend at `pdf.blade.php:163-167`, immediately
followed by the `Discussed with / Assessed by / Final Rating by` signature block):

1. New "Comments and Recommendations for Development Purposes" section, inserted
   between the Legend and the signature block. Free-text, persisted as a new nullable
   `comments_recommendations` column on `ipcr_v2_records`, captured at the same stage
   v1 captures its equivalent narrative (PMT review / final rating stage) and editable
   only while the record is mutable.
2. A visible rating date on the Rating Summary header — the Director's
   `director_signed_at` when present, otherwise the latest rating action's timestamp
   from `ipcr_v2_status_logs`.

## Permissions summary

- `ipcr.v2.admin.reopen` — new, Administrator only.
- No other new permissions; existing `ipcr.v2.*` permission set already scopes
  submit/approve/return/view per role.

## Testing

- Feature tests per transition path: remarks required-on-return, PIN required-on-sign
  and rejected-on-mismatch, `ipcr_v2_status_logs` row written with correct
  `action_type`/`actor`/`signature_snapshot`.
- Locking: mutation attempts on a `Director Signed` record still 403 (regression guard
  on existing `assertMutable`).
- Reopen: only `ipcr.v2.admin.reopen` can call it, reason required, status reverts to
  `Submitted to PMT`, log row + notifications fire.
- Notification tests: correct recipient(s) per transition, mirroring v1's existing
  notification test coverage where one exists.
- Index: duplicate-generation is blocked/redirected, empty-sync error message renders.
- Show/PDF: Comments section renders in both Vue and PDF output; omitted cleanly when
  empty.
