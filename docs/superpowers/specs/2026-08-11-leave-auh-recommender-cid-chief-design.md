# Leave Application: AUH's Own Leave Recommended by CID Chief (not ACIDAA)

**Date:** 2026-08-11
**Status:** Approved

## Problem

The 4-stage CID teaching-faculty leave workflow (shipped 2026-08-04, `882ecdef`) requires an Academic Unit Head (AUH) to recommend their unit's teachers' leave before the Division Chief. Since an AUH cannot recommend their own leave, `IPCRWorkflowService::leaveRecommenderFor()` currently routes an AUH's *own* leave application to whoever holds the ACIDAA (Assistant CID Chief for Academic Affairs) designation instead.

The user wants this changed: an AUH's own leave should be recommended by the **CID Chief**, not ACIDAA.

## Goal

When the applicant is an Academic Unit Head (per their current-school-year Faculty Loading designation, i.e. they head an `AcademicUnit`), `IPCRWorkflowService::leaveRecommenderFor()` must resolve the CID Chief directly — ACIDAA no longer participates in this branch.

## Non-Goals

- **ACIDAA's own leave** — already resolves to CID Chief today (`leaveRecommenderFor()`'s first branch), unrelated to this change, left untouched.
- **IPCR/SPMS `immediateSupervisorFor()`** — the performance-rating supervisor chain (AUH → ACIDAA → CID Chief) is a separate workflow governing IPCR ratings, not leave. Not in scope; the user asked specifically about the Leave Application module.
- **DTR signatory routing** (`DtrRecordController::resolveSupervisor()`) — per existing project notes this resolver never had an ACIDAA-self-recommendation special case to begin with; nothing to change.
- **Regular unit faculty, non-CID-teaching staff, everyone else** — unaffected; their branches in `leaveRecommenderFor()` are untouched.
- No new database columns, no new routes, no new UI. This is a routing-logic change inside an existing, already-shared resolver.

## Design

### The change

`IPCRWorkflowService::leaveRecommenderFor()` (`app/Services/PerformanceManagement/IPCRWorkflowService.php:198-209`) is the single source of truth reused by `ApprovalService::processLeave()`, `ApprovalInboxService`, `LeaveApplicationController`, and `PrintForm.vue`'s signatory resolution — nothing else independently encodes the AUH/ACIDAA chain. The fix is a one-line change to the AUH-self branch:

```php
public function leaveRecommenderFor(User $employee): ?User
{
    if ($this->holdsAcidaaDesignation($employee)) {
        return $this->firstNotSelf($employee, $this->cidChief());
    }

    if ($this->currentAcademicUnits()->where('head_user_id', $employee->id)->isNotEmpty()) {
        return $this->firstNotSelf($employee, $this->cidChief());   // was: $this->acidaaHolder(), $this->cidChief()
    }

    return $this->firstNotSelf($employee, $this->academicUnitHeadFor($employee));
}
```

Update the surrounding docblock (lines 189-197) to drop the "AUH → ACIDAA" line and describe the AUH-self branch as routing straight to the CID Chief.

### Confirmed downstream effect (verified with user before writing this spec)

In this deployment, the "CID Chief" RBAC role holder **is** the same real person as the CID Division's chief-of-record (`divisions.division_chief_id` for the CID division). This matters because a separate, already-shipped gate — `requiresLeaveAuhRecommendation()` — suppresses the whole "recommend" stage whenever the resolved recommender turns out to be the same person who acts at the next (Division Chief) stage anyway, to avoid making one person click "recommend" and then "forward" back-to-back on the same application. (This is exactly why ACIDAA's own leave already collapses to a 3-stage flow today.)

Consequence: once ACIDAA is removed from the AUH-self branch, **an AUH's own leave application will also collapse from 4 stages to 3**:

| Stage | Actor | Action | Status transition |
|---|---|---|---|
| 1 | HR Officer | certified | pending → hr_verified |
| 2 | CID Chief | forwarded | hr_verified → forwarded |
| 3 | Campus Director | approved | forwarded → approved |

The CID Chief signs once, at the Division Chief stage — no separate "academic_unit_head"/`recommended` action occurs for this case. This also means the CS Form 6 "RECOMMENDATION" box (7.B) shows only the CID Chief, using the same plain single-signatory layout already used for every non-AUH-qualifying applicant (`requiresAuh = false`) — no PrintForm.vue changes needed, this is a pre-existing, already-tested code path.

This resolver is written generically (not hardcoded to "collapse"), so if a future deployment ever has a different person as "CID Chief" than the CID division's chief-of-record, the AUH's own leave would correctly keep 4 stages instead (CID Chief recommends at stage 2, the actual division-chief-of-record forwards at stage 3). A test locks in this shape too, even though it doesn't reflect the current org (see Testing).

### No other file needs functional changes

Verified by reading the call sites:

- `ApprovalInboxService`'s Division Chief tab (`app/Services/ApprovalInboxService.php:212-233`) already surfaces `hr_verified` applications directly to the Division Chief whenever `requiresLeaveAuhRecommendation()` is false — this is the exact path non-CID-teaching staff already exercise today, now also taken by an AUH's own leave.
- `LeaveApplicationController::show()`/`approve()` already derive `requiresAuh` / `isAuhRecommender` generically from `requiresLeaveAuhRecommendation()` / `leaveRecommenderFor()` — no special-casing to remove.
- `PrintForm.vue` already falls back to the single-DC RECOMMENDATION box when `requiresAuh` is false.

### Comment cleanup (non-functional)

Several code comments describe the old "AUH's own leave → ACIDAA" behavior and should be corrected for future-maintainer accuracy — no logic changes:

- `app/Services/ApprovalInboxService.php` — lines ~60-61, ~85-91
- `app/Http/Controllers/HR/LeaveApplicationController.php` — lines ~183, ~193, ~208, ~225, ~497

## Testing

`tests/Feature/HR/LeaveAuhRecommendationTest.php`:

- Rewrite `test_auh_own_leave_is_recommended_by_acidaa` → new behavior: HR certifies (`pending` → `hr_verified`), then the CID Chief (who in this test's setup is also the CID division's `division_chief_id`, matching the confirmed production shape) forwards directly (`hr_verified` → `forwarded`) with no `academic_unit_head` stage in between; assert `auh_id` stays null and `requiresAuh`/`isAuhRecommender` are false for this applicant.
- Add a new test covering the other shape: CID Chief (role) is a different user than the CID division's `division_chief_id` holder. Assert the AUH's own leave still requires the `academic_unit_head` stage, that stage is resolved to the CID-Chief-role user (not ACIDAA), and the actual `division_chief_id` holder is the one who forwards afterward.
- Update the stale class-level doc comment (lines 22-26) that currently says "An AUH's own leave is recommended by ACIDAA instead."
- Existing tests unaffected: HR-then-AUH-then-DC-then-CampusDirector happy path (regular unit faculty, not the AUH's own leave), AUH rejection, wrong-person rejection, non-CID-teaching skip, AUH inbox visibility.

No new test file — extending the existing one, consistent with how the original 4-stage feature was tested.
