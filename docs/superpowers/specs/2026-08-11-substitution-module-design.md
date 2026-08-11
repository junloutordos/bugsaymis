# Substitution Module — Design Spec

**Date:** 2026-08-11
**Status:** Approved (design phase) — pending implementation plan

## Summary

When a faculty/staff member goes on approved Leave or Official Travel, they need someone to temporarily cover their classes, designations, approvals, and other system duties. This module lets the employee nominate a substitute, routes that nomination through the existing Division Chief/AUH approval chain, and — once approved and within the absence's date window — lets the substitute "Act As" the original user: a full session identity swap that reuses 100% of the app's existing permission and data-scoping logic, rather than trying to merge permissions across two identities.

## Context / Prior Art

No impersonation, delegation, or proxy-access mechanism exists anywhere in the codebase today (confirmed by search). Permissions are purely role/permission-based against the authenticated user's own ID (`User::hasPermission()`, `hasAnyPermission()`, `hasAnyRole()`, `isSuperAdmin()`), enforced via `CheckPermission` middleware (`permission:a|b` = any-of, `permission:a,b` = all-of).

The closest existing precedent is `app/Models/HR/UnitHead.php` (table `unit_heads`), which models temporal "Acting/OIC" appointments for organizational-unit headship specifically (`is_acting`, `effective_date`/`end_date`, auto-closes the previous holder on `creating`). This module generalizes that acting/temporal-grant pattern to **any user, any duty** — not just org-unit heads. `unit_heads` and formal "Acting Division Chief" designations remain a separate, narrower concern and are untouched by this design.

`LeaveApplicationController::approve()` implements the existing 4-stage leave approval workflow (`hr_officer` → `academic_unit_head` → `division_chief` → `campus_director`), with recommenders resolved partly by permission and partly by identity match via `IPCRWorkflowService::leaveRecommenderFor()`. This module's approval step reuses that same recommender-resolution pattern rather than inventing new routing logic.

## Architecture

### Data Model

**`substitutions` table** — the grant/nomination record:

| Column | Notes |
|---|---|
| `id` | |
| `original_user_id` | FK `users.id` — person on leave/travel |
| `substitute_user_id` | FK `users.id` — person covering |
| `absentable_type`, `absentable_id` | polymorphic FK to `HR\LeaveApplication` or `TravelRequest` (`app/Models/TravelRequest.php`, from the Travel module, `database/migrations/2026_07_03_180000_create_travel_module_tables.php`) |
| `start_date`, `end_date` | validated against, and capped by, the approved absence's own dates |
| `status` | `pending_approval` \| `approved` \| `rejected` \| `active` \| `ended` \| `revoked` |
| `nominated_by` | = `original_user_id` |
| `approved_by`, `approved_at` | nullable |
| `rejection_reason` | nullable |
| `revoked_by`, `revoked_at`, `revocation_reason` | nullable |
| `notes` | nullable, free text handoff instructions |
| timestamps | |

**`acting_as_sessions` table** — audit trail of actual impersonation usage (separate from the grant, since one approved grant may be exercised across multiple act-as sessions):

| Column | Notes |
|---|---|
| `id` | |
| `substitution_id` | FK |
| `started_at`, `ended_at` | nullable `ended_at` while live |
| `ended_reason` | `manual` \| `expired` \| `revoked` \| `logout` |
| `ip_address`, `user_agent` | |
| timestamps | |

### Session Mechanics ("Acting As")

- **Entry:** `POST /substitutions/{id}/act-as` — validates the grant is `approved`/`active` and within `[start_date, end_date]`, stores `session('true_user_id')` (substitute's real ID) and `session('acting_substitution_id')`, then `Auth::login($originalUser)`. From this point every existing `hasPermission()` call, `Auth::id()`-scoped query, dashboard, and controller works unmodified — no code elsewhere in the app needs to change.
- **Guard middleware** (`EnsureActingAsWindowValid`, applied globally once acting-as is active): on every request, re-checks the grant is still `active`/`approved` and `now()` is within the date window. If not, force-reverts to the substitute's real account, closes the open `acting_as_sessions` row (`ended_reason = 'expired'` or `'revoked'`), and redirects with a flash notice.
- **Persistent banner:** shared Inertia prop on every layout — *"Acting as {OriginalUser} — you are {SubstituteUser}. [Return to my account]"* — non-dismissible, amber/warning-toned to visually distinguish from a normal session.
- **Exit:** `POST /substitutions/act-as/exit` — reverses the session swap, closes the `acting_as_sessions` row (`ended_reason = 'manual'`).
- **One active identity at a time** — starting a new act-as session auto-exits any other live one first. No nested/chained impersonation: while acting-as, the user cannot start another act-as session, and cannot access their own account's data without exiting first.
- **SuperAdmin kill switch:** an admin screen listing all live `acting_as_sessions` with a force-end action, mirroring the existing Atlas Sentinel containment kill-switch pattern.

### Nomination & Approval Lifecycle

```
Leave/Travel approved
        │
        ▼
Employee nominates substitute (from Leave/Travel Show page)
        │  status: pending_approval
        ▼
Notification → Division Chief / AUH
(recommender resolved via the same pattern as
 IPCRWorkflowService::leaveRecommenderFor())
        │
   ┌────┴────┐
   ▼         ▼
Rejected   Approved → status: approved
(notify        │       Notification → substitute
 employee)     │
               ▼
   start_date reached → status: active
   (substitute can act-as immediately if
    start_date has already begun)
               │
               ▼
   end_date passed / manually ended / revoked
   → status: ended / revoked
   (middleware force-exits any live act-as session)
```

- **Early revocation** allowed by: the original user, the approving Division Chief/AUH, or an Administrator/HR. Immediately flips status to `revoked`; middleware boots any live session on next request.
- **Cascade from Leave/Travel changes:** if the underlying absence is cancelled, the substitution auto-revokes; if its dates are shortened, `end_date` auto-adjusts down to match (never extends beyond the approved absence).
- **Notifications:** nomination submitted → approver; approved/rejected → employee; approved → substitute; act-as started/ended → original user (so they always know when someone was driving their account, and when).

### Validation Rules (edge cases)

- **SuperAdmin block:** nomination is rejected outright if `original_user->isSuperAdmin()` — Administrators bypass all permission checks, so a substitution grant on that account would hand the substitute unrestricted system access via a routine leave flow. Administrators arrange backup coverage out-of-band, not through this module.
- **No self-substitution:** `original_user_id !== substitute_user_id`.
- **Substitute availability:** nomination blocked if the proposed substitute has their own approved Leave/Travel overlapping the proposed window.
- **No overlapping grants:** only one active/approved substitution per `original_user_id` per date range.
- **Approver-is-applicant escalation:** if the resolved approver is the applicant themselves (e.g. an AUH nominating their own substitute), escalate to the next tier up, reusing the existing recommender-escalation pattern.

## Components

| Component | Purpose |
|---|---|
| `app/Models/HR/Substitution.php` | Grant record, scopes (`current()`, `active()`, `effectiveOn($date)`) mirroring `UnitHead` conventions |
| `app/Models/HR/ActingAsSession.php` | Session audit record |
| `app/Services/HR/SubstitutionService.php` | Nomination, approval/rejection, revocation, cascade-from-leave logic, validation rules |
| `app/Services/HR/ActingAsService.php` | Session swap in/out, window checks |
| `app/Http/Middleware/EnsureActingAsWindowValid.php` | Per-request re-validation, global once active |
| `app/Http/Controllers/HR/SubstitutionController.php` | Inertia CRUD + approve/reject/revoke actions |
| `app/Http/Controllers/HR/ActingAsController.php` | `act-as` / `act-as/exit` endpoints |
| `resources/js/Pages/HR/Substitutions/*.vue` | Index (My Nominations / For My Approval / My Substitutions tabs), nomination form panel on Leave/Travel Show |
| Shared Inertia prop + banner in `AdminLayout.vue` | Acting-as indicator |

## Permissions

- `hr.substitution.manage` — nominate/view own nominations (all employees)
- `hr.substitution.approve` — approve/reject nominations (Division Chief, AUH, HR, Administrator)
- `hr.substitution.revoke` — early revocation (original user always allowed on their own grant; approver/HR/Administrator otherwise)

## Testing Plan

- Nomination validation: SuperAdmin block, self-substitution block, substitute-availability overlap, grant-overlap block
- Approval routing: standard case, applicant-is-approver escalation, rejection notifies employee
- Cascade: leave cancellation auto-revokes; leave shortening auto-adjusts `end_date`
- Act-as lifecycle: entry validates window, exit closes session cleanly, middleware force-reverts on expiry/revocation mid-session, one-active-identity-at-a-time auto-exit
- Regression: confirm existing permission/data-scoping code paths behave identically when `Auth::user()` is the original user via act-as vs. a normal login (spot-check leave approval, class record grading, designation-scoped dashboards)

## Out of Scope (v1)

- Action-level audit trail of *what* the substitute did while acting-as (only session start/end is logged; individual mutations are attributed to the original user's ID in existing tables, as they should be for business-record correctness — recoverable by cross-referencing `acting_as_sessions` for that time window, not built as a separate diff log)
- Concurrent acting-as of multiple people simultaneously
- Explicit substitute accept/decline step (supervisor approval of the nomination is the control point instead)
