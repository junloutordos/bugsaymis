# Leave Application: AUH's Own Leave Recommended by CID Chief Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When an Academic Unit Head applies for their own leave, route the "recommend before Division Chief" stage to the CID Chief instead of ACIDAA.

**Architecture:** `IPCRWorkflowService::leaveRecommenderFor()` is the single shared resolver every leave-approval consumer (`ApprovalService`, `ApprovalInboxService`, `LeaveApplicationController`, `PrintForm.vue`'s signatory data) calls through — the fix is one branch inside that method. A separate, already-shipped gate (`requiresLeaveAuhRecommendation()`) automatically collapses the workflow from 4 stages to 3 whenever the resolved recommender is the same person who acts at the Division Chief stage, which is confirmed to be true for CID Chief in this deployment — so no other file needs new branching logic.

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit (`RefreshDatabase`), Docker (`php` service).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-11-leave-auh-recommender-cid-chief-design.md` (Approved).
- Confirmed with user: the "CID Chief" RBAC role holder is the same real person as the CID Division's `division_chief_id`. The test covering this shape must attach the "CID Chief" role to that user explicitly, locally within the test — not in the shared `setUp()`, since a second test needs the opposite shape (a distinct "CID Chief" role holder) and `IPCRWorkflowService::cidChief()`'s `User::havingRole('CID Chief')->first()` becomes ambiguous if two users hold that role in the same test.
- Out of scope, do not touch: `IPCRWorkflowService::immediateSupervisorFor()` (IPCR/SPMS chain), `DtrRecordController::resolveSupervisor()`, ACIDAA's own-leave branch (`holdsAcidaaDesignation()` branch, first `if` in `leaveRecommenderFor()`), any non-AUH applicant branch.
- Run tests via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/LeaveAuhRecommendationTest.php"` (swap the path for the full suite where noted).
- Stage by filename in git, never `git add -A`/`.` — per project convention.

---

### Task 1: `leaveRecommenderFor()` routes an AUH's own leave to CID Chief, not ACIDAA

**Files:**
- Modify: `app/Services/PerformanceManagement/IPCRWorkflowService.php:189-209`
- Modify: `tests/Feature/HR/LeaveAuhRecommendationTest.php`

**Interfaces:**
- Consumes: nothing new — `IPCRWorkflowService::leaveRecommenderFor(User $employee): ?User`, `requiresLeaveAuhRecommendation(User $applicant): bool`, and `holdsAcidaaDesignation()`/`acidaaHolder()`/`cidChief()`/`academicUnitHeadFor()`/`firstNotSelf()` already exist unchanged (private helpers on the same class).
- Produces: `leaveRecommenderFor()` keeps its exact signature and return type (`?User`) — every caller (`ApprovalService`, `ApprovalInboxService`, `LeaveApplicationController`) needs no changes.

- [ ] **Step 1: Leave `setUp()` as-is — do not add "CID Chief" to the shared `$this->cidChief` fixture globally**

`IPCRWorkflowService::cidChief()` resolves via `User::havingRole('CID Chief')->first()`, which is ambiguous if more than one user holds that role. Task 1 Step 3 needs two *different* test scenarios — one where the "CID Chief" role holder IS `$this->cidChief` (the division-chief-of-record), and one where it's a distinct user — so the role attachment must happen locally inside each test method, not in the shared `setUp()`. (This is a deliberate correction from an earlier draft of this plan that attached the role in `setUp()`, which would have made `cidChief()` non-deterministic once a second "CID Chief"-role user existed in the same test.) No edit needed for this step — proceed to Step 2.

- [ ] **Step 2: Replace the class-doc comment**

Find:

```php
/**
 * CID teaching faculty's leave must be recommended by their Academic Unit
 * Head before the Division Chief (CID Chief). An AUH's own leave is
 * recommended by ACIDAA instead, since an AUH cannot recommend themselves.
 * Everyone else keeps the original 3-stage workflow unchanged.
 */
```

Replace with:

```php
/**
 * CID teaching faculty's leave must be recommended by their Academic Unit
 * Head before the Division Chief (CID Chief). An AUH's own leave is
 * recommended by the CID Chief directly instead, since an AUH cannot
 * recommend themselves. When the CID Chief (role) is the same person as
 * the Division Chief of record, that collapses the workflow to 3 stages —
 * see test_auh_own_leave_skips_recommendation_when_cid_chief_is_division_chief.
 * Everyone else keeps the original 3-stage workflow unchanged.
 */
```

- [ ] **Step 3: Replace `test_auh_own_leave_is_recommended_by_acidaa` with the new expected behavior**

Find the entire existing method (from its doc comment through its closing brace):

```php
    public function test_auh_own_leave_is_recommended_by_acidaa(): void
    {
        // The AUH applies for their own leave — they cannot recommend
        // themselves, so ACIDAA (not the AUH's own academic-unit lookup)
        // must be the resolved recommender.
        $auhApplicant = User::factory()->create([
            'name' => 'Ms. Math Unit Head',
            'emp_category' => 'Plantilla Teaching',
            'division_id' => $this->cid->id,
        ]);
        // Re-point the unit's head to this exact user (they head their own unit).
        $this->unit->update(['head_user_id' => $auhApplicant->id]);

        $acidaa = $this->makeAcidaaUser();
        $application = $this->application($auhApplicant, ['status' => 'hr_verified']);

        // ACIDAA has no dedicated RBAC role/permission for this — the
        // identity-match check alone must authorize the action.
        $this->actingAs($acidaa)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'academic_unit_head', 'action' => 'recommended', 'remarks' => '',
            ])->assertRedirect();

        $application->refresh();
        $this->assertSame('auh_verified', $application->status);
        $this->assertSame($acidaa->id, $application->auh_id);
    }
```

Replace with two methods:

```php
    public function test_auh_own_leave_skips_recommendation_when_cid_chief_is_division_chief(): void
    {
        // An ACIDAA designee exists in the system, but must NOT be routed
        // to for the AUH's own leave — the CID Chief handles it directly.
        // Give $this->cidChief the "CID Chief" RBAC role so it's also who
        // IPCRWorkflowService::cidChief() resolves — matching production,
        // where the CID Chief role holder and the CID division's
        // division_chief_id are the same person. That equality is what
        // collapses the recommendation stage away entirely — the CID
        // Chief forwards straight from hr_verified.
        $this->cidChief->roles()->attach(Role::firstOrCreate(['name' => 'CID Chief'])->id);
        $this->makeAcidaaUser();

        $auhApplicant = User::factory()->create([
            'name' => 'Ms. Math Unit Head',
            'emp_category' => 'Plantilla Teaching',
            'division_id' => $this->cid->id,
        ]);
        // Re-point the unit's head to this exact user (they head their own unit).
        $this->unit->update(['head_user_id' => $auhApplicant->id]);

        $application = $this->application($auhApplicant, ['status' => 'hr_verified']);

        $this->actingAs($this->cidChief)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'division_chief', 'action' => 'forwarded', 'remarks' => '',
            ])->assertRedirect();

        $application->refresh();
        $this->assertSame('forwarded', $application->status);
        $this->assertNull($application->auh_id);
    }

    public function test_auh_own_leave_requires_recommendation_when_cid_chief_role_differs_from_division_chief_record(): void
    {
        // Here "CID Chief" (the RBAC role leaveRecommenderFor() resolves
        // via cidChief()) is a different physical person than
        // $this->cidChief (the CID division's division_chief_id holder).
        // Deliberately do NOT attach the "CID Chief" role to $this->cidChief
        // in this test, so cidChief() resolves unambiguously to the user
        // created below. The AUH's own leave must still route through a
        // distinct recommendation stage in that case, performed by the CID
        // Chief role holder — not collapsed, and not ACIDAA either.
        $cidChiefRoleHolder = User::factory()->create(['name' => 'Dr. Other CID Chief']);
        $cidChiefRoleHolder->roles()->attach(Role::firstOrCreate(['name' => 'CID Chief'])->id);

        $auhApplicant = User::factory()->create([
            'name' => 'Ms. Math Unit Head',
            'emp_category' => 'Plantilla Teaching',
            'division_id' => $this->cid->id,
        ]);
        $this->unit->update(['head_user_id' => $auhApplicant->id]);

        $application = $this->application($auhApplicant, ['status' => 'hr_verified']);

        // The division_chief_id holder cannot forward yet — no recommendation on file.
        $this->actingAs($this->cidChief)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'division_chief', 'action' => 'forwarded', 'remarks' => '',
            ])->assertStatus(409);

        // The CID Chief role holder recommends.
        $this->actingAs($cidChiefRoleHolder)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'academic_unit_head', 'action' => 'recommended', 'remarks' => '',
            ])->assertRedirect();

        $application->refresh();
        $this->assertSame('auh_verified', $application->status);
        $this->assertSame($cidChiefRoleHolder->id, $application->auh_id);

        // Now the actual division_chief_id holder forwards.
        $this->actingAs($this->cidChief)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'division_chief', 'action' => 'forwarded', 'remarks' => '',
            ])->assertRedirect();

        $application->refresh();
        $this->assertSame('forwarded', $application->status);
    }
```

- [ ] **Step 4: Run the test file to confirm the expected failures**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/LeaveAuhRecommendationTest.php"
```

Expected: `test_auh_own_leave_skips_recommendation_when_cid_chief_is_division_chief` **FAILS** — with the current (unfixed) resolver, `acidaaHolder()` still wins over `cidChief()` in the AUH-self branch, so the application stays at `hr_verified` and the `division_chief` stage post returns 409 instead of a redirect. `test_auh_own_leave_requires_recommendation_when_cid_chief_role_differs_from_division_chief_record` is expected to **PASS already** (it doesn't exercise the ACIDAA branch — no ACIDAA user exists in that test — so old and new resolver code behave the same for it; it's regression coverage, not a red test). All other existing tests in the file should still pass unchanged.

- [ ] **Step 5: Fix `leaveRecommenderFor()`**

In `app/Services/PerformanceManagement/IPCRWorkflowService.php`, find:

```php
    /**
     * The "recommend before Division Chief" resolver for CID teaching
     * faculty leave applications. Mirrors immediateSupervisorFor()'s
     * AUH/ACIDAA chain (without the DivisionChief-self branch, which
     * doesn't apply to leave — non-teaching DCs never reach this gate):
     *   AUH (heads a unit)  → ACIDAA — an AUH cannot recommend their own leave
     *   ACIDAA holder       → CID Chief
     *   Regular unit faculty→ their Academic Unit Head
     */
    public function leaveRecommenderFor(User $employee): ?User
    {
        if ($this->holdsAcidaaDesignation($employee)) {
            return $this->firstNotSelf($employee, $this->cidChief());
        }

        if ($this->currentAcademicUnits()->where('head_user_id', $employee->id)->isNotEmpty()) {
            return $this->firstNotSelf($employee, $this->acidaaHolder(), $this->cidChief());
        }

        return $this->firstNotSelf($employee, $this->academicUnitHeadFor($employee));
    }
```

Replace with:

```php
    /**
     * The "recommend before Division Chief" resolver for CID teaching
     * faculty leave applications. Mirrors immediateSupervisorFor()'s
     * AUH chain (without the DivisionChief-self branch, which doesn't
     * apply to leave — non-teaching DCs never reach this gate), but
     * unlike immediateSupervisorFor() an AUH's own leave never routes
     * through ACIDAA — it goes straight to the CID Chief:
     *   AUH (heads a unit)  → CID Chief — an AUH cannot recommend their own leave
     *   ACIDAA holder       → CID Chief
     *   Regular unit faculty→ their Academic Unit Head
     */
    public function leaveRecommenderFor(User $employee): ?User
    {
        if ($this->holdsAcidaaDesignation($employee)) {
            return $this->firstNotSelf($employee, $this->cidChief());
        }

        if ($this->currentAcademicUnits()->where('head_user_id', $employee->id)->isNotEmpty()) {
            return $this->firstNotSelf($employee, $this->cidChief());
        }

        return $this->firstNotSelf($employee, $this->academicUnitHeadFor($employee));
    }
```

- [ ] **Step 6: Run the test file again to confirm all tests pass**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/LeaveAuhRecommendationTest.php"
```

Expected: all tests in the file **PASS**, including both new/rewritten tests and the five untouched existing tests (`test_full_workflow_hr_then_auh_then_division_chief_then_campus_director`, `test_auh_rejection_sets_status_rejected`, `test_only_the_resolved_academic_unit_head_can_recommend`, `test_non_cid_teaching_applicant_skips_auh_stage`, `test_academic_unit_head_inbox_visibility`).

- [ ] **Step 7: Run the full HR leave test suite to check for regressions**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/LeaveApprovalWorkflowTest.php tests/Feature/HR/LeaveAuhRecommendationTest.php"
```

Expected: all PASS — `LeaveApprovalWorkflowTest.php` covers the 3-stage baseline (non-CID applicants), which never touches the AUH-self branch and must be unaffected.

- [ ] **Step 8: Commit**

```bash
git add app/Services/PerformanceManagement/IPCRWorkflowService.php tests/Feature/HR/LeaveAuhRecommendationTest.php
git commit -m "fix(hr): AUH's own leave routes to CID Chief instead of ACIDAA"
```

---

### Task 2: Clean up stale ACIDAA comments left over from the old routing

**Files:**
- Modify: `app/Services/ApprovalInboxService.php:59-62,85-91`
- Modify: `app/Http/Controllers/HR/LeaveApplicationController.php:181-197,206-210,225-226,496-497`

**Interfaces:**
- Consumes: nothing — comment/dead-code cleanup only, no behavior depends on this task.
- Produces: nothing new — no signatures change.

- [ ] **Step 1: `ApprovalInboxService.php` — drop the now-dead `$isAcidaa` contribution to `$isAuh` and fix the surrounding comments**

Find:

```php
        $isAcidaa = $this->holdsAcidaaDesignation($user);
        // ACIDAA holders can also be the leave recommender for an AUH's own
        // leave application (see IPCRWorkflowService::leaveRecommenderFor()).
        $isAuh = $user->hasRole('AUH') || $isAcidaa;
```

Replace with:

```php
        $isAcidaa = $this->holdsAcidaaDesignation($user);
        $isAuh = $user->hasRole('AUH');
```

(`$isAcidaa` is still used for the unrelated Assessment Deletion Requests tab further down — keep the variable, only its use inside `$isAuh` is dead now that `leaveRecommenderFor()` never resolves an ACIDAA holder for an AUH's own leave.)

Find:

```php
        // ── Academic Unit Head (and ACIDAA) ──────────────────────────────────
        // CID teaching faculty's leave must be recommended before it reaches
        // the Division Chief (CID Chief). Recommender is normally the AUH,
        // but an AUH's own leave is recommended by ACIDAA instead — see
        // IPCRWorkflowService::leaveRecommenderFor(). No divisionIds scoping —
        // each application is matched against the applicant's *specific*
        // resolved recommender, not just "anyone holding the AUH role".
```

Replace with:

```php
        // ── Academic Unit Head ────────────────────────────────────────────────
        // CID teaching faculty's leave must be recommended before it reaches
        // the Division Chief (CID Chief). Recommender is normally the AUH,
        // but an AUH's own leave is recommended by the CID Chief directly
        // instead — see IPCRWorkflowService::leaveRecommenderFor(). No
        // divisionIds scoping — each application is matched against the
        // applicant's *specific* resolved recommender, not just "anyone
        // holding the AUH role".
```

- [ ] **Step 2: `LeaveApplicationController.php` — fix the four stale ACIDAA comments**

Find:

```php
            // Lets the "Review Application" button show for the specific
            // resolved recommender even when they hold no RBAC permission
            // for it (e.g. an ACIDAA holder reviewing an AUH's own leave).
```

Replace with:

```php
            // Lets the "Review Application" button show for the specific
            // resolved recommender even when they hold no RBAC permission
            // for it (e.g. ACIDAA recommending their own leave).
```

Find:

```php
    /**
     * Leave approval workflow (4 stages for CID teaching faculty, 3 for everyone else):
     *   Stage 1 — hr_officer        : HR Officer certifies leave credits    (pending       → hr_verified)
     *   Stage 2 — academic_unit_head: AUH (or ACIDAA for an AUH's own leave)
     *                                 recommends — CID teaching faculty only (hr_verified   → auh_verified)
     *   Stage 3 — division_chief    : Division Chief recommends              (hr_verified/auh_verified → forwarded)
     *   Stage 4 — campus_director   : Campus Director final approval         (forwarded     → approved/rejected)
     */
```

Replace with:

```php
    /**
     * Leave approval workflow (4 stages for CID teaching faculty, 3 for everyone else):
     *   Stage 1 — hr_officer        : HR Officer certifies leave credits    (pending       → hr_verified)
     *   Stage 2 — academic_unit_head: AUH (or CID Chief for an AUH's own leave)
     *                                 recommends — CID teaching faculty only (hr_verified   → auh_verified)
     *   Stage 3 — division_chief    : Division Chief recommends              (hr_verified/auh_verified → forwarded)
     *   Stage 4 — campus_director   : Campus Director final approval         (forwarded     → approved/rejected)
     */
```

Find:

```php
        // The academic_unit_head stage is authorized purely by identity (must
        // be the applicant's specific resolved recommender, checked below) —
        // an ACIDAA holder reviewing an AUH's own leave has no dedicated RBAC
        // role/permission, mirroring how ApprovalInboxController already
        // treats ACIDAA designation membership as sufficient on its own.
```

Replace with:

```php
        // The academic_unit_head stage is authorized purely by identity (must
        // be the applicant's specific resolved recommender, checked below) —
        // an ACIDAA holder recommending their own leave has no dedicated RBAC
        // role/permission, mirroring how ApprovalInboxController already
        // treats ACIDAA designation membership as sufficient on its own.
```

Find:

```php
        // Academic Unit Head (or ACIDAA, for an AUH's own leave) may only act
        // on the specific applicant they are the resolved recommender for.
```

Replace with:

```php
        // Academic Unit Head (or CID Chief, for an AUH's own leave) may only
        // act on the specific applicant they are the resolved recommender for.
```

Find:

```php
        // Fallback: live Academic Unit Head recommender (CID teaching faculty
        // only — AUH for regular faculty, ACIDAA for an AUH's own leave).
```

Replace with:

```php
        // Fallback: live Academic Unit Head recommender (CID teaching faculty
        // only — AUH for regular faculty, CID Chief for an AUH's own leave).
```

- [ ] **Step 3: PHP syntax-check both modified files**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -l app/Services/ApprovalInboxService.php && php -l app/Http/Controllers/HR/LeaveApplicationController.php"
```

Expected: `No syntax errors detected` for both files.

- [ ] **Step 4: Run the full HR leave + inbox test suites to confirm zero behavior change**

Run:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/LeaveApprovalWorkflowTest.php tests/Feature/HR/LeaveAuhRecommendationTest.php --filter=ApprovalInbox"
```

Then also run any existing ApprovalInboxService-focused test file if one exists:

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && find tests -iname '*ApprovalInbox*'"
```

If that find turns up a test file, run it explicitly with `php artisan test <path>` and confirm all PASS. If it turns up nothing, the `test_academic_unit_head_inbox_visibility` case inside `LeaveAuhRecommendationTest.php` (already run in Task 1 Step 7) is the only inbox coverage that touches this code path, and it already passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ApprovalInboxService.php app/Http/Controllers/HR/LeaveApplicationController.php
git commit -m "docs: correct stale ACIDAA comments after AUH leave routing fix"
```
