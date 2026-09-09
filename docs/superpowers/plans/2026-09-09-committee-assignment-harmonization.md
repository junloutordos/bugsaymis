# Committee Assignment Harmonization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two separate Committee Assignment UIs (Performance Management's catalog-CRUD page and Faculty Loading's assignment-CRUD page) with one page under Performance Management, make committee membership auto-sync into Support Functions and auto-materialize into IPCR V2, and give the committee chairperson direct rating authority over those IPCR V2 Support Items.

**Architecture:** Two new focused services (`CommitteeIpcrSyncService`, `CommitteeIpcrRatingService`) carry all the new sync/rating logic and are unit-testable in isolation before anything wires into a controller. They're wired into the *existing* `FacultyLoading\CommitteeAssignmentController` and `CommitteeRosterService` first (so the new behavior ships against stable, already-working entry points), then the controller/route/page consolidation happens as a separate, mostly-mechanical relocation once the new logic is proven.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, PHPUnit (`RefreshDatabase`), MySQL 8.

**Spec:** `docs/superpowers/specs/2026-09-09-committee-assignment-harmonization-design.md`

## Global Constraints

- Never write to `employee_ipcrs_plan.sup_*` (legacy v1 pivot) for new committee ratings — this plan removes that write path entirely (spec §4.4).
- Never fabricate an `IpcrV2Record` for a member who doesn't have one for the current period — auto-materialization only runs against an *existing* current-period record (spec §4.3).
- Route names `pm-committees.*` must keep working for anything already pointing at them — the controller behind them changes, the names don't (spec §4.1).
- No new database columns/tables are needed anywhere in this plan — `EmployeeFunction.sync_source_key` (`'committee_assignment:{id}'`, already exists) is the only linkage needed between a `FacultyCommitteeAssignment` and its IPCR V2 items.
- Every committee-sourced `ipcr_v2_support_items` row must have exactly one writer for its official rating fields at a time — either the Division Chief (non-committee items) or the chairperson (committee items), never both (spec §4.4).

---

## File Structure

New files:
- `app/Services/PerformanceManagement/CommitteeIpcrSyncService.php` — after a committee-assignment change, syncs the affected member's `EmployeeFunction` rows and, if they already have a current-period IPCR V2 record, materializes new items into it.
- `app/Services/PerformanceManagement/CommitteeIpcrRatingService.php` — resolves a `FacultyCommitteeAssignment`'s current-period IPCR V2 Support Item(s) and applies a chairperson's rating to one.
- `tests/Feature/PerformanceManagement/CommitteeIpcrSyncServiceTest.php`
- `tests/Feature/PerformanceManagement/CommitteeIpcrRatingServiceTest.php`
- `tests/Feature/PerformanceManagement/CommitteeAssignmentControllerTest.php` (the merged controller's own tests — see Task 8 for exact route names used once consolidation lands)

Modified files (backend):
- `app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php` → relocated to `app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php` (Task 8), gains catalog CRUD (Task 8), calls the two new services (Tasks 2, 6), rating action rewritten (Task 6).
- `app/Services/FacultyLoading/CommitteeRosterService.php` — calls `CommitteeIpcrSyncService` after roster changes (Task 3).
- `app/Services/FacultyLoading/CommitteeRatingService.php` — `rate()`/`saveAccomplishment()` v1-pivot logic removed (superseded by `CommitteeIpcrRatingService`; class deleted once nothing references it — Task 8).
- `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php` — `rateSupportItem()` gains the committee-sourced read-only guard (Task 7).
- `app/Http/Controllers/CommitteePerformanceController.php` — deleted (Task 8).
- `routes/faculty-loading.php` — committee-assignment routes removed (Task 8).
- `routes/web.php` — `pm-committees.*` routes repointed at the relocated controller, gains the catalog CRUD routes (Task 8).

Modified files (frontend):
- `resources/js/Pages/FacultyLoading/CommitteeAssignments/Index.vue` → relocated to `resources/js/Pages/PerformanceManagement/Committees/Index.vue`, gains a "Catalog" tab with PMS's committee CRUD modal (Task 9).
- `resources/js/Pages/FacultyLoading/CommitteeAssignments/Show.vue` → relocated to `resources/js/Pages/PerformanceManagement/Committees/Show.vue`, rating data/modal reworked to IPCR V2 support items (Task 10).
- `resources/js/Pages/PerformanceManagement/Committees/Index.vue` and `Show.vue` (old PMS versions) — deleted (Task 9/10 replace them in place).
- `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue` — committee-sourced support items rendered read-only (Task 11).
- `resources/js/Layouts/navigation.js` — Faculty Loading's "Committee Assignments" entry removed (Task 12).

---

### Task 1: `CommitteeIpcrSyncService` — sync one member's committee-sourced functions

**Files:**
- Create: `app/Services/PerformanceManagement/CommitteeIpcrSyncService.php`
- Test: `tests/Feature/PerformanceManagement/CommitteeIpcrSyncServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\EmployeeFunctionSyncService::syncFromFacultyLoading(User $user): void` (existing, unchanged), `App\Services\IPCRV2\IpcrV2GenerationService::syncNewFunctions(IpcrV2Record $record): int` (existing, unchanged), `App\Models\IPCRRatingPeriod::current()` (existing scope).
- Produces: `CommitteeIpcrSyncService::syncForUser(User $user): void` — used by Tasks 2 and 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Committee;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeIpcrSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => 'Full Term',
            'term_type' => 'full_term', 'is_current' => true,
        ]);
    }

    public function test_sync_for_user_creates_the_support_function_row(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        (new CommitteeIpcrSyncService())->syncForUser($member);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'function_type' => EmployeeFunction::TYPE_SUPPORT,
            'label' => 'Grievance Committee',
        ]);
    }

    public function test_sync_for_user_materializes_into_an_existing_current_period_record(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);

        (new CommitteeIpcrSyncService())->syncForUser($member);

        $this->assertDatabaseHas('ipcr_v2_support_items', [
            'ipcr_v2_id' => $record->id,
            'label' => 'Grievance Committee',
        ]);
    }

    public function test_sync_for_user_does_not_fabricate_a_record_when_none_exists(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        (new CommitteeIpcrSyncService())->syncForUser($member);

        $this->assertDatabaseCount('ipcr_v2_records', 0);
    }

    public function test_sync_for_committee_syncs_every_active_member(): void
    {
        $term = $this->currentTerm();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $committee = Committee::create(['name' => 'Sports Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $memberA->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        FacultyCommitteeAssignment::create([
            'user_id' => $memberB->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        FacultyCommitteeAssignment::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'inactive',
        ]);

        (new CommitteeIpcrSyncService())->syncForCommittee($committee->id);

        $this->assertDatabaseHas('employee_functions', ['user_id' => $memberA->id, 'label' => 'Sports Committee']);
        $this->assertDatabaseHas('employee_functions', ['user_id' => $memberB->id, 'label' => 'Sports Committee']);
        $this->assertDatabaseCount('employee_functions', 2); // inactive assignment's user is skipped
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeIpcrSyncServiceTest"`
Expected: FAIL — `Class "App\Services\PerformanceManagement\CommitteeIpcrSyncService" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\PerformanceManagement;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\EmployeeFunctionSyncService;
use App\Services\IPCRV2\IpcrV2GenerationService;

/**
 * Keeps a member's committee-sourced Employee Function / IPCR V2 Support
 * Item state current whenever a committee assignment changes, instead of
 * relying on HR's manual "Sync from Faculty Loading" button
 * (EmployeeFunctionSyncService itself is unchanged — this only decides
 * WHEN to call it, plus the one further step it never took: materializing
 * into an IPCR V2 record that already exists).
 */
class CommitteeIpcrSyncService
{
    public function __construct(
        private EmployeeFunctionSyncService $functions = new EmployeeFunctionSyncService(),
        private IpcrV2GenerationService $generation = new IpcrV2GenerationService(),
    ) {}

    public function syncForUser(User $user): void
    {
        $this->functions->syncFromFacultyLoading($user);

        $period = IPCRRatingPeriod::current()->first();
        if (! $period) {
            return;
        }

        $record = IpcrV2Record::where('user_id', $user->id)
            ->where('rating_period_id', $period->id)
            ->first();

        if ($record) {
            $this->generation->syncNewFunctions($record);
        }
    }

    /**
     * Syncs every currently-active member of one committee (not its
     * sub-committees — callers loop those explicitly). Takes a bare id
     * rather than a Committee instance on purpose — this method is called
     * from code paths using either of the two model classes that map to
     * the `committees` table (`App\Models\Committee` and
     * `App\Models\FacultyLoading\Committee`), and a strict type-hint on
     * either one would reject the other.
     */
    public function syncForCommittee(int $committeeId): void
    {
        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            return;
        }

        $userIds = FacultyCommitteeAssignment::where('committee_id', $committeeId)
            ->where('academic_term_id', $term->id)
            ->where('status', 'active')
            ->pluck('user_id');

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->syncForUser($user);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeIpcrSyncServiceTest"`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/PerformanceManagement/CommitteeIpcrSyncService.php tests/Feature/PerformanceManagement/CommitteeIpcrSyncServiceTest.php
git commit -m "feat(committees): add CommitteeIpcrSyncService for auto Support Function sync"
```

---

### Task 2: Wire the sync service into `FacultyLoading\CommitteeAssignmentController`

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php`
- Test: `tests/Feature/FacultyLoading/CommitteeAssignmentControllerSyncTest.php`

**Interfaces:**
- Consumes: `CommitteeIpcrSyncService::syncForUser(User $user): void` (Task 1), `CommitteeIpcrSyncService::syncForCommittee(int $committeeId): void` (Task 1).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Committee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerSyncTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => 'Full Term',
            'term_type' => 'full_term', 'is_current' => true,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->givePermission('faculty_loading.manage');

        return $admin;
    }

    public function test_store_syncs_the_assigned_members_support_function(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('faculty-loading.committee-assignments.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'label' => 'Discipline Committee',
        ]);
    }

    public function test_destroy_re_syncs_so_the_support_function_is_removed(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('faculty-loading.committee-assignments.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ]);
        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('faculty-loading.committee-assignments.destroy', $assignment->id))
            ->assertRedirect();

        $this->assertDatabaseCount('employee_functions', 0);
    }

    public function test_committee_wdp_retag_re_syncs_every_active_member(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Sports Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $assignmentId = FacultyCommitteeAssignment::where('user_id', $member->id)->value('id');

        $this->actingAs($admin)->put(route('faculty-loading.committee-assignments.update', $assignmentId), [
            'role' => 'chairperson', 'load_units' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'function_type' => EmployeeFunction::TYPE_CORE,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeAssignmentControllerSyncTest"`
Expected: FAIL — `assertDatabaseHas('employee_functions', ...)` finds nothing (sync never runs).

- [ ] **Step 3: Write minimal implementation**

Add the constructor dependency and call sites in `app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php`:

```php
// constructor:
public function __construct(
    private readonly LoadComputationService $loads,
    private readonly CommitteeRatingService $rating,
    private readonly \App\Services\PerformanceManagement\CommitteeIpcrSyncService $ipcrSync,
) {}
```

In `store()`, right after `$this->loads->syncLoad($load);` and before `return back()->with(...)`:

```php
        $this->loads->syncLoad($load);
        $this->ipcrSync->syncForUser(User::find($data['user_id']));

        return back()->with('success', 'Committee assignment added.');
```

In `update()`, after the existing `if ($load) $this->loads->syncLoad($load);` at the end of the method:

```php
        $load = FacultyLoad::where('user_id', $committeeAssignment->user_id)
            ->where('academic_term_id', $committeeAssignment->academic_term_id)
            ->first();
        if ($load) $this->loads->syncLoad($load);
        $this->ipcrSync->syncForUser($committeeAssignment->faculty);

        return back()->with('success', 'Committee assignment updated.');
```

In `destroy()`, capture the user before delete (it already captures `$userId` — reuse it) and sync after:

```php
        $userId = $committeeAssignment->user_id;
        $termId = $committeeAssignment->academic_term_id;
        $laId   = $committeeAssignment->load_assignment_id;

        $committeeAssignment->delete();

        if ($laId) LoadAssignment::destroy($laId);

        $load = FacultyLoad::where('user_id', $userId)->where('academic_term_id', $termId)->first();
        if ($load) $this->loads->syncLoad($load);
        $this->ipcrSync->syncForUser(User::find($userId));

        return back()->with('success', 'Committee assignment removed.');
```

For the committee-level WDP retag test, `update()` already runs `$committee->workDistributionPlans()->sync($data['plan_ids'] ?? []);` — add a re-sync of every active member of that committee right after it:

```php
        if ($committeeAssignment->committee_id) {
            $committee = Committee::find($committeeAssignment->committee_id);
            if ($committee) {
                $committee->workDistributionPlans()->sync($data['plan_ids'] ?? []);
                $this->ipcrSync->syncForCommittee($committee->id);
                if (in_array($data['role'], ['chairperson', 'co_chair'])) {
                    $committee->update(['head_id' => $committeeAssignment->user_id]);
                }
            }
        }
```

(The same block exists in `store()` — add the identical `$this->ipcrSync->syncForCommittee($committee->id);` line there too, right after `$committee->workDistributionPlans()->sync($data['plan_ids'] ?? []);`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeAssignmentControllerSyncTest"`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full Faculty Loading + Employee Functions suites for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=FacultyLoading && php artisan test tests/Feature/EmployeeFunctions"`
Expected: PASS, no regressions

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php tests/Feature/FacultyLoading/CommitteeAssignmentControllerSyncTest.php
git commit -m "feat(committees): auto-sync Support Functions on assignment CRUD"
```

---

### Task 3: Wire the sync service into `CommitteeRosterService`

**Files:**
- Modify: `app/Services/FacultyLoading/CommitteeRosterService.php`
- Test: `tests/Feature/FacultyLoading/CommitteeRosterServiceSyncTest.php`

**Interfaces:**
- Consumes: `CommitteeIpcrSyncService::syncForUser(User $user): void` (Task 1).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\CommitteeRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeRosterServiceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => 'Full Term',
            'term_type' => 'full_term', 'is_current' => true,
        ]);
    }

    public function test_reconcile_syncs_the_newly_added_roster_member(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'label' => 'Grievance Committee',
        ]);
    }

    public function test_reconcile_re_syncs_a_deactivated_member_so_the_row_is_removed(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id);
        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $committee->members()->detach($member->id);
        $committee->refresh();
        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseCount('employee_functions', 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeRosterServiceSyncTest"`
Expected: FAIL — no `employee_functions` row created/removed.

- [ ] **Step 3: Write minimal implementation**

Add the dependency and call it from the three places that mutate a `FacultyCommitteeAssignment`:

```php
class CommitteeRosterService
{
    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrSyncService $ipcrSync = new \App\Services\PerformanceManagement\CommitteeIpcrSyncService(),
    ) {}
```

In `createIfMissing()`, right after `$this->loads->syncLoad($load);` and before `$result['created']++;`:

```php
        $this->loads->syncLoad($load);
        $this->ipcrSync->syncForUser(\App\Models\User::findOrFail($userId));
        $result['created']++;
```

In `deactivate()`, at the end of the method after the existing `if ($load) { $this->loads->syncLoad($load); }`:

```php
        $load = FacultyLoad::where('user_id', $userId)->where('academic_term_id', $termId)->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }
        $this->ipcrSync->syncForUser(\App\Models\User::findOrFail($userId));
```

In `updateRole()`, at the end after the existing sync block:

```php
        $load = FacultyLoad::where('user_id', $assignment->user_id)
            ->where('academic_term_id', $assignment->academic_term_id)
            ->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }
        $this->ipcrSync->syncForUser(\App\Models\User::findOrFail($assignment->user_id));

        $result['role_updated']++;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeRosterServiceSyncTest"`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the committee module's full backing test suite for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=Committee"`
Expected: PASS, no regressions

- [ ] **Step 6: Commit**

```bash
git add app/Services/FacultyLoading/CommitteeRosterService.php tests/Feature/FacultyLoading/CommitteeRosterServiceSyncTest.php
git commit -m "feat(committees): auto-sync Support Functions on roster reconciliation"
```

---

### Task 4: `CommitteeIpcrRatingService` — resolve a committee assignment's IPCR V2 support items

**Files:**
- Create: `app/Services/PerformanceManagement/CommitteeIpcrRatingService.php`
- Test: `tests/Feature/PerformanceManagement/CommitteeIpcrRatingServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\EmployeeFunction` (`sync_source_key` column, existing), `App\Models\IPCRV2\IpcrV2SupportItem` (existing).
- Produces: `CommitteeIpcrRatingService::resolveSupportItems(FacultyCommitteeAssignment $assignment): \Illuminate\Support\Collection` (of `IpcrV2SupportItem`) — used by Task 5's controller and Task 6. `CommitteeIpcrRatingService::isCommitteeSourced(IpcrV2SupportItem $item): bool` — used by Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrRatingService;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeIpcrRatingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAssignmentWithRecord(): array
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $assignment = FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);

        return [$assignment, $record];
    }

    public function test_resolves_the_materialized_support_item_for_the_current_period(): void
    {
        [$assignment, $record] = $this->setUpAssignmentWithRecord();
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);

        $items = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment);

        $this->assertCount(1, $items);
        $this->assertSame('Grievance Committee', $items->first()->label);
        $this->assertSame($record->id, $items->first()->ipcr_v2_id);
    }

    public function test_resolves_nothing_when_no_current_period_record_exists(): void
    {
        [$assignment] = $this->setUpAssignmentWithRecord();
        \App\Models\IPCRV2\IpcrV2Record::query()->delete();

        $items = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment);

        $this->assertCount(0, $items);
    }

    public function test_is_committee_sourced_true_for_a_committee_materialized_item(): void
    {
        [$assignment] = $this->setUpAssignmentWithRecord();
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment)->first();

        $this->assertTrue((new CommitteeIpcrRatingService())->isCommitteeSourced($item));
    }

    public function test_is_committee_sourced_false_for_a_manually_added_support_item(): void
    {
        [, $record] = $this->setUpAssignmentWithRecord();
        $item = $record->supportItems()->create(['label' => 'Manual item']);

        $this->assertFalse((new CommitteeIpcrRatingService())->isCommitteeSourced($item));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeIpcrRatingServiceTest"`
Expected: FAIL — `Class "App\Services\PerformanceManagement\CommitteeIpcrRatingService" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\PerformanceManagement;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use Illuminate\Support\Collection;

/**
 * Bridges a term-scoped FacultyCommitteeAssignment to the IPCR V2 Support
 * Item(s) it materialized into — the same `committee_assignment:{id}`
 * sync_source_key EmployeeFunctionSyncService already writes is the only
 * linkage; no new column needed.
 */
class CommitteeIpcrRatingService
{
    public function resolveSupportItems(FacultyCommitteeAssignment $assignment): Collection
    {
        $period = IPCRRatingPeriod::current()->first();
        if (! $period) {
            return collect();
        }

        $record = IpcrV2Record::where('user_id', $assignment->user_id)
            ->where('rating_period_id', $period->id)
            ->first();
        if (! $record) {
            return collect();
        }

        $function = EmployeeFunction::where('sync_source_key', 'committee_assignment:' . $assignment->id)->first();
        if (! $function) {
            return collect();
        }

        return IpcrV2SupportItem::where('ipcr_v2_id', $record->id)
            ->where('employee_function_id', $function->id)
            ->get();
    }

    public function isCommitteeSourced(IpcrV2SupportItem $item): bool
    {
        $sourceKey = $item->employeeFunction?->sync_source_key;

        return $sourceKey !== null && str_starts_with($sourceKey, 'committee_assignment:');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeIpcrRatingServiceTest"`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/PerformanceManagement/CommitteeIpcrRatingService.php tests/Feature/PerformanceManagement/CommitteeIpcrRatingServiceTest.php
git commit -m "feat(committees): add CommitteeIpcrRatingService to resolve committee-sourced IPCR V2 items"
```

---

### Task 5: `CommitteeIpcrRatingService::rate()` — apply a chairperson's rating with status gating

**Files:**
- Modify: `app/Services/PerformanceManagement/CommitteeIpcrRatingService.php`
- Test: `tests/Feature/PerformanceManagement/CommitteeIpcrRatingServiceTest.php` (append)

**Interfaces:**
- Produces: `CommitteeIpcrRatingService::rate(IpcrV2SupportItem $item, array $data): IpcrV2SupportItem` — used by Task 6's controller. Throws `Illuminate\Validation\ValidationException` (key `status`) when the record hasn't reached Targets Approved yet, or is no longer mutable.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PerformanceManagement/CommitteeIpcrRatingServiceTest.php`:

```php
    public function test_rate_writes_quality_efficiency_timeliness_and_computes_row_average(): void
    {
        [$assignment, $record] = $this->setUpAssignmentWithRecord();
        $record->update(['status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_FOR_RATING]);
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment)->first();

        $rated = (new CommitteeIpcrRatingService())->rate($item, [
            'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3,
            'accomplishment' => 'Organized the annual meet.', 'mov_link' => 'https://example.com/photos',
        ]);

        $this->assertSame(5, $rated->quality_rating);
        $this->assertSame(4, $rated->efficiency_rating);
        $this->assertSame(3, $rated->timeliness_rating);
        $this->assertEqualsWithDelta(4.0, (float) $rated->row_average, 0.01);
        $this->assertSame('Organized the annual meet.', $rated->actual_accomplishment);
        $this->assertSame('https://example.com/photos', $rated->mov_link);
    }

    public function test_rate_rejects_when_targets_not_yet_approved(): void
    {
        [$assignment, $record] = $this->setUpAssignmentWithRecord();
        // status defaults to STATUS_NEW_TARGET
        (new CommitteeIpcrSyncService())->syncForUser($assignment->faculty);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($assignment)->first();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        (new CommitteeIpcrRatingService())->rate($item, [
            'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3,
        ]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeIpcrRatingServiceTest"`
Expected: FAIL — `Call to undefined method ...::rate()`

- [ ] **Step 3: Write minimal implementation**

Add to `CommitteeIpcrRatingService`:

```php
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Validation\ValidationException;

// ...inside the class:

    private const NOT_YET_RATABLE_STATUSES = [
        IpcrV2WorkflowService::STATUS_NEW_TARGET,
        IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        IpcrV2WorkflowService::STATUS_RETURNED,
    ];

    public function rate(IpcrV2SupportItem $item, array $data): IpcrV2SupportItem
    {
        $record = $item->ipcr()->firstOrFail();

        if (in_array($record->status, self::NOT_YET_RATABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This member\'s IPCR V2 targets are not yet approved — rating is not available until then.',
            ]);
        }

        if (! $record->isMutable()) {
            throw ValidationException::withMessages([
                'status' => 'This IPCR V2 record is finalized or its rating period is closed and can no longer be rated.',
            ]);
        }

        $rowAverage = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

        $item->update([
            'quality_rating' => $data['quality_rating'],
            'efficiency_rating' => $data['efficiency_rating'],
            'timeliness_rating' => $data['timeliness_rating'],
            'row_average' => $rowAverage,
            'actual_accomplishment' => $data['accomplishment'] ?? $item->actual_accomplishment,
            'mov_link' => $data['mov_link'] ?? $item->mov_link,
        ]);

        return $item->fresh();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeIpcrRatingServiceTest"`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/PerformanceManagement/CommitteeIpcrRatingService.php tests/Feature/PerformanceManagement/CommitteeIpcrRatingServiceTest.php
git commit -m "feat(committees): rate() applies chairperson ratings with target-approval gating"
```

---

### Task 6: Rewrite `rateAssignment()` to write IPCR V2 instead of the legacy v1 pivot

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php`
- Modify: `routes/faculty-loading.php` (route stays, no path change)
- Test: `tests/Feature/FacultyLoading/CommitteeAssignmentControllerRateTest.php`

**Interfaces:**
- Consumes: `CommitteeIpcrRatingService::resolveSupportItems()` / `::rate()` (Task 4, 5).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\PerformanceManagement\CommitteeIpcrRatingService;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerRateTest extends TestCase
{
    use RefreshDatabase;

    private function setUpChairAndMember(): array
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $chair = User::factory()->create();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $chair->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        $memberAssignment = FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_FOR_RATING]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($memberAssignment)->first();

        return [$chair, $memberAssignment, $item];
    }

    public function test_chairperson_rating_writes_the_ipcr_v2_support_item(): void
    {
        [$chair, $assignment, $item] = $this->setUpChairAndMember();

        $this->actingAs($chair)->post(route('faculty-loading.committee-assignments.rate', $assignment->id), [
            'support_item_id' => $item->id,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('ipcr_v2_support_items', [
            'id' => $item->id, 'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ]);
    }

    public function test_a_non_chair_non_admin_member_cannot_rate(): void
    {
        [, $assignment, $item] = $this->setUpChairAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('faculty-loading.committee-assignments.rate', $assignment->id), [
            'support_item_id' => $item->id,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ])->assertForbidden();
    }

    public function test_a_support_item_id_belonging_to_a_different_assignment_is_rejected(): void
    {
        [$chair, $assignment] = $this->setUpChairAndMember();
        $otherItem = IpcrV2Record::first()->supportItems()->create(['label' => 'Unrelated manual item']);

        $this->actingAs($chair)->post(route('faculty-loading.committee-assignments.rate', $assignment->id), [
            'support_item_id' => $otherItem->id,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ])->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeAssignmentControllerRateTest"`
Expected: FAIL — the current `rateAssignment()` still expects `work_distribution_plan_id`, not `support_item_id`, and still writes v1.

- [ ] **Step 3: Write minimal implementation**

Replace `rateAssignment()` in `app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php`:

```php
    public function rateAssignment(Request $request, FacultyCommitteeAssignment $committeeAssignment): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasPermission('faculty_loading.manage')) {
            $targetCommittee   = Committee::find($committeeAssignment->committee_id);
            $isSubCommittee    = $targetCommittee && $targetCommittee->parent_committee_id !== null;
            $targetIsChairRole = in_array($committeeAssignment->role, ['chairperson', 'co_chair']);

            if ($isSubCommittee && $targetIsChairRole) {
                $isChairperson = FacultyCommitteeAssignment::where('committee_id', $targetCommittee->parent_committee_id)
                    ->where('academic_term_id', $committeeAssignment->academic_term_id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', ['chairperson', 'co_chair'])
                    ->where('status', 'active')
                    ->exists();
            } else {
                $isChairperson = FacultyCommitteeAssignment::where('committee_id', $committeeAssignment->committee_id)
                    ->where('academic_term_id', $committeeAssignment->academic_term_id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', ['chairperson', 'co_chair'])
                    ->where('status', 'active')
                    ->exists();
            }

            abort_if(! $isChairperson, 403, 'Only the committee chairperson or an administrator can rate members.');
        }

        $data = $request->validate([
            'support_item_id'   => 'required|integer|exists:ipcr_v2_support_items,id',
            'accomplishment'    => 'nullable|string|max:1000',
            'mov_link'          => 'nullable|string|max:500',
            'quality_rating'    => 'required|integer|min:1|max:5',
            'efficiency_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
        ]);

        $validItemIds = $this->ipcrRating->resolveSupportItems($committeeAssignment)->pluck('id');
        abort_unless($validItemIds->contains((int) $data['support_item_id']), 404, 'This item does not belong to this committee assignment.');

        $item = \App\Models\IPCRV2\IpcrV2SupportItem::findOrFail($data['support_item_id']);
        $this->ipcrRating->rate($item, $data);

        return back()->with('success', 'Rating saved.');
    }
```

Add the new dependency to the constructor (keep `CommitteeRatingService` for now — `saveAccomplishment()` still uses it until Task 8's cleanup):

```php
    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly CommitteeRatingService $rating,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrSyncService $ipcrSync,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrRatingService $ipcrRating,
    ) {}
```

Note: a `ValidationException` thrown by `$this->ipcrRating->rate()` (Task 5's status gate) is already converted by Laravel's exception handler into a redirect-back-with-errors response, matching this controller's existing error-handling convention elsewhere — no extra try/catch needed.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeAssignmentControllerRateTest"`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full Faculty Loading suite for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=FacultyLoading"`
Expected: PASS, no regressions

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php tests/Feature/FacultyLoading/CommitteeAssignmentControllerRateTest.php
git commit -m "feat(committees): chairperson ratings now write IPCR V2 Support Items, not the legacy v1 pivot"
```

---

### Task 7: Division Chief's own IPCR V2 rating screen treats committee-sourced items as read-only

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php`
- Modify: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php` (`show()` — expose `is_committee_sourced` per support item so the Vue page can render the badge; see Task 11)
- Test: `tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerCommitteeGuardTest.php`

**Interfaces:**
- Consumes: `CommitteeIpcrRatingService::isCommitteeSourced(IpcrV2SupportItem $item): bool` (Task 4).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\Committee;
use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionChiefIpcrV2ControllerCommitteeGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_chief_cannot_rate_a_committee_sourced_support_item(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $division = Division::factory()->create();
        $dc = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $dc->id]);
        $member = User::factory()->create(['division_id' => $division->id]);
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_FOR_RATING]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $item = $record->fresh()->supportItems()->first();

        $this->actingAs($dc)->put(route('division-chief-ipcr-v2.rateSupportItem', ['id' => $record->id, 'supportItem' => $item->id]), [
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 5,
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DivisionChiefIpcrV2ControllerCommitteeGuardTest"`
Expected: FAIL — currently returns a redirect (rating succeeds), not 403.

- [ ] **Step 3: Write minimal implementation**

In `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php`, add the dependency and guard `rateSupportItem()`:

```php
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private StrategicFunctionService $strategic,
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService(),
        private PersonNameFormatter $nameFormatter = new PersonNameFormatter(),
        private \App\Services\PerformanceManagement\CommitteeIpcrRatingService $committeeRating = new \App\Services\PerformanceManagement\CommitteeIpcrRatingService(),
    ) {}

    public function rateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);
        abort_if(
            $this->committeeRating->isCommitteeSourced($supportItem),
            403,
            'This item is rated via its Committee Assignment, not here.'
        );

        $data = $request->validate([
            'quality_rating' => 'required|integer|min:1|max:5',
            'efficiency_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

        $supportItem->update($data);

        return back()->with('success', 'Rated.');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DivisionChiefIpcrV2ControllerCommitteeGuardTest"`
Expected: PASS

- [ ] **Step 5: Run the full IPCR V2 suite for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2"`
Expected: PASS, no regressions

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerCommitteeGuardTest.php
git commit -m "fix(ipcr-v2): Division Chief cannot rate a committee-sourced Support Item"
```

---

### Task 8: Consolidate controllers, routes, and permissions under Performance Management

**Files:**
- Create: `app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php` (relocated + merged content)
- Delete: `app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php`
- Delete: `app/Http/Controllers/CommitteePerformanceController.php`
- Delete: `app/Services/FacultyLoading/CommitteeRatingService.php` (no longer referenced by anything once `saveAccomplishment` moves to the merged controller — see Step 3)
- Modify: `routes/faculty-loading.php` (remove the `committee-assignments` groups)
- Modify: `routes/web.php` (`pm-committees.*` block repointed + gains catalog CRUD routes)
- Move all tests from Tasks 2, 3, 6 into `tests/Feature/PerformanceManagement/` and update their `route(...)` calls from `faculty-loading.committee-assignments.*` to `pm-committees.*`.

**Interfaces:**
- Produces: `pm-committees.index|store|update|destroy|show|compliance|rate|accomplishment|plans.sync` — the full route set both old controllers exposed, now on one controller.

- [ ] **Step 1: Move and rename the controller**

```bash
git mv app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php
```

Update its namespace to `App\Http\Controllers\PerformanceManagement`.

- [ ] **Step 2: Fold in the catalog CRUD from `CommitteePerformanceController`**

**Model-alias gotcha, verified this session by reading both files:** the relocated controller already has `use App\Models\FacultyLoading\Committee;` (bare `Committee`, used throughout every assignment-CRUD method — `index`/`store`/`update`/`destroy`/`show`/`compliance`/`rateAssignment`). `CommitteePerformanceController`'s catalog CRUD uses a *different* class, the global `App\Models\Committee` — same `committees` table, but a different PHP class with a different `$fillable` (the global model's fillable includes `fiscal_year`, which `FacultyLoading\Committee` does NOT declare fillable — mass-assigning `fiscal_year` through the wrong model silently drops it). Do not rename the existing bare `Committee` usages — instead import the global model under an alias and use that alias only in the three ported catalog methods:

```php
use App\Models\Committee as GlobalCommittee;
```

Add these methods to the relocated controller, taken from `CommitteePerformanceController::store()`/`update()`/`destroy()`/`syncMembers()` (already read this session), with every `Committee` reference inside them changed to `GlobalCommittee`. Keep the `$this->roster->reconcileCurrentTerm($committee);` calls — `CommitteeRosterService` is injected in neither controller today, so add it as a constructor dependency:

```php
    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrSyncService $ipcrSync,
        private readonly \App\Services\PerformanceManagement\CommitteeIpcrRatingService $ipcrRating,
        private readonly \App\Services\FacultyLoading\CommitteeRosterService $roster,
    ) {}

    public function storeCommittee(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'head_id'                       => 'nullable|exists:users,id',
            'description'                   => 'nullable|string',
            'fiscal_year'                   => 'nullable|integer|min:2000|max:2100',
            'has_subcommittees'             => 'boolean',
            'member_ids'                    => 'nullable|array',
            'member_ids.*'                  => 'exists:users,id',
            'member_tasks'                  => 'nullable|array',
            'plan_ids'                      => 'nullable|array',
            'plan_ids.*'                    => 'exists:work_distribution_plans,id',
            'sub_committees'                => 'nullable|array',
            'sub_committees.*.name'         => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'      => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'   => 'nullable|array',
            'sub_committees.*.member_ids.*' => 'exists:users,id',
            'sub_committees.*.member_tasks' => 'nullable|array',
        ]);

        $committee = GlobalCommittee::create([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
        ]);

        $committee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                $sub = GlobalCommittee::create([
                    'name'                => $subData['name'],
                    'head_id'             => $subData['head_id'] ?? null,
                    'parent_committee_id' => $committee->id,
                ]);
                $this->syncCatalogMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
            }
        } else {
            $this->syncCatalogMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        }

        $this->roster->reconcileCurrentTerm($committee);
        $this->ipcrSync->syncForCommittee($committee->id);

        return redirect()->back()->with('success', 'Committee created.');
    }

    public function updateCommittee(Request $request, GlobalCommittee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'head_id'                       => 'nullable|exists:users,id',
            'description'                   => 'nullable|string',
            'fiscal_year'                   => 'nullable|integer|min:2000|max:2100',
            'has_subcommittees'             => 'boolean',
            'member_ids'                    => 'nullable|array',
            'member_ids.*'                  => 'exists:users,id',
            'member_tasks'                  => 'nullable|array',
            'plan_ids'                      => 'nullable|array',
            'plan_ids.*'                    => 'exists:work_distribution_plans,id',
            'sub_committees'                => 'nullable|array',
            'sub_committees.*.id'           => 'nullable|exists:committees,id',
            'sub_committees.*.name'         => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'      => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'   => 'nullable|array',
            'sub_committees.*.member_ids.*' => 'exists:users,id',
            'sub_committees.*.member_tasks' => 'nullable|array',
        ]);

        $committee->update([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
        ]);

        $committee->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                if (!empty($subData['id'])) {
                    $sub = GlobalCommittee::find($subData['id']);
                    if ($sub && $sub->parent_committee_id === $committee->id) {
                        $sub->update(['name' => $subData['name'], 'head_id' => $subData['head_id'] ?? null]);
                        $this->syncCatalogMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
                    }
                } else {
                    $sub = GlobalCommittee::create([
                        'name'                => $subData['name'],
                        'head_id'             => $subData['head_id'] ?? null,
                        'parent_committee_id' => $committee->id,
                    ]);
                    $this->syncCatalogMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
                }
            }
        } else {
            $this->syncCatalogMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        }

        $this->roster->reconcileCurrentTerm($committee);
        $this->ipcrSync->syncForCommittee($committee->id);

        return redirect()->back()->with('success', 'Committee updated.');
    }

    public function destroyCommittee(GlobalCommittee $committee)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) abort(403);

        $committee->delete();
        return redirect()->back()->with('success', 'Committee deleted.');
    }

    private function syncCatalogMembers(GlobalCommittee $committee, array $memberIds, array $memberTasks): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $syncData[$userId] = ['task' => $memberTasks[$userId] ?? null];
        }
        $committee->members()->sync($syncData);
    }
```

(Named `storeCommittee`/`updateCommittee`/`destroyCommittee`/`syncCatalogMembers` rather than reusing `store`/`update`/`destroy` — those names already belong to this controller's assignment-CRUD methods, which operate on `FacultyCommitteeAssignment`, not `Committee`. Both concepts genuinely coexist in one controller now — the distinct method names are what keep them from colliding, matching this controller's existing pattern of one class covering multiple related resources.)

Also fold in `saveAccomplishment()` unchanged (member self-service accomplishment via the committee page is retired here in favor of the employee's own IPCR V2 page per the file-structure note above — **do not port this method**; delete the corresponding `faculty-loading.committee-assignments.accomplishment` route in Step 4 and drop the `CommitteeRatingService` dependency entirely).

- [ ] **Step 3: Delete now-unused files**

```bash
git rm app/Http/Controllers/CommitteePerformanceController.php
git rm app/Services/FacultyLoading/CommitteeRatingService.php
```

Remove the `use App\Services\FacultyLoading\CommitteeRatingService;` import and the `private readonly CommitteeRatingService $rating` constructor param from the merged controller (nothing calls it anymore — `rateAssignment()` was rewritten in Task 6, `saveAccomplishment()` is dropped above).

- [ ] **Step 4: Update routes**

In `routes/faculty-loading.php`, delete both `committee-assignments` route groups (lines ~82-85 and ~292-298 per the pre-refactor file).

**Verified this session** — the `pm-committees.*` lines in `routes/web.php` sit inside a larger existing `Route::middleware(['auth', 'pshs.email'])->group(function () { ... })` block that *also* contains the `committee-tasks.*` and `pm-special-assignments.*` routes right after them. Do not replace that whole outer group — only replace the 7 `pm-committees.*` lines in place (`index`/`store`/`update`/`destroy`/`show`/`member-accomplishment`/`rate-member`), leaving the outer `Route::middleware([...])->group(function () { ... })` wrapper and the `committee-tasks.*`/`pm-special-assignments.*` lines that follow untouched:

```php
    Route::prefix('performance-management/committees')->name('pm-committees.')->group(function () {
        Route::get('/',                            [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'index'])->name('index');
        Route::post('/catalog',                     [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'storeCommittee'])->name('catalog.store');
        Route::put('/catalog/{committee}',          [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'updateCommittee'])->name('catalog.update');
        Route::delete('/catalog/{committee}',       [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'destroyCommittee'])->name('catalog.destroy');
        Route::get('/compliance',                   [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'compliance'])->name('compliance');
        Route::post('/',                            [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'store'])->name('store');
        Route::put('/{committeeAssignment}',        [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'update'])->name('update');
        Route::delete('/{committeeAssignment}',     [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'destroy'])->name('destroy');
        Route::put('/{committeeAssignment}/plans',  [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'syncPlans'])->name('plans.sync');
        Route::post('/{committeeAssignment}/rate',  [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'rateAssignment'])->name('rate');
        Route::get('/{committee}',                  [\App\Http\Controllers\PerformanceManagement\CommitteeAssignmentController::class, 'show'])->name('show');
    })->middleware('permission:accomplishments.view|faculty_loading.manage');
```

This nested group replaces the 7 old lines but stays inside the same outer `auth`/`pshs.email` group those lines were already in — `committee-tasks.*` and `pm-special-assignments.*` keep their existing indentation/position immediately after it, unmodified. The old `member-accomplishment`/`rate-member` route names are dropped entirely (their controller methods, `saveMemberAccomplishment`/`rateMember`, no longer exist post-merge — Task 10's Show page uses `rate` only). `store`/`update`/`destroy`/`plans.sync`/`compliance` internally still call `$this->authorize('faculty_loading.manage')` exactly as the old FL controller did — the route-level permission is deliberately the union `accomplishments.view|faculty_loading.manage` so PMS-only users can still reach `index`/`show`/`rate`, while assignment-editing stays gated to `faculty_loading.manage` inside the controller, unchanged from today.

- [ ] **Step 5: Move and re-point the tests**

```bash
git mv tests/Feature/FacultyLoading/CommitteeAssignmentControllerSyncTest.php tests/Feature/PerformanceManagement/CommitteeAssignmentControllerSyncTest.php
git mv tests/Feature/FacultyLoading/CommitteeRosterServiceSyncTest.php tests/Feature/PerformanceManagement/CommitteeRosterServiceSyncTest.php
git mv tests/Feature/FacultyLoading/CommitteeAssignmentControllerRateTest.php tests/Feature/PerformanceManagement/CommitteeAssignmentControllerRateTest.php
```

In each moved test file: update the `namespace` to `Tests\Feature\PerformanceManagement`, and replace every `route('faculty-loading.committee-assignments.X', ...)` with `route('pm-committees.X', ...)`.

- [ ] **Step 6: Run everything touched**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/PerformanceManagement tests/Feature/IPCRV2 tests/Feature/EmployeeFunctions"`
Expected: PASS, no regressions

- [ ] **Step 7: Run PHP lint on all touched files**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && find app/Http/Controllers/PerformanceManagement app/Services/PerformanceManagement routes -name '*.php' -exec php -l {} \;"`
Expected: `No syntax errors detected` for every file

- [ ] **Step 8: Commit**

```bash
git add -A app/Http/Controllers/PerformanceManagement app/Services/PerformanceManagement routes tests/Feature/PerformanceManagement
git add app/Http/Controllers/CommitteePerformanceController.php app/Services/FacultyLoading/CommitteeRatingService.php app/Http/Controllers/FacultyLoading/CommitteeAssignmentController.php routes/faculty-loading.php routes/web.php
git commit -m "refactor(committees): consolidate PMS + Faculty Loading controllers under one Performance Management controller"
```

---

### Task 9: Merge the Index pages — assignments + catalog in one page

**Files:**
- Create: `resources/js/Pages/PerformanceManagement/Committees/Index.vue` (new content, replacing the old PMS version)
- Delete: `resources/js/Pages/FacultyLoading/CommitteeAssignments/Index.vue`
- Modify: `app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php::index()` — merge both controllers' props into one payload

**Interfaces:**
- Consumes props: everything FL's `index()` already returns (`assignments`, `terms`, `faculty`, `committees`, `plans`, `currentTerm`, `filters`) plus everything PMS's `index()` already returns (`users`, `fiscalYears`, `selectedFiscalYear`, `currentFiscalYear`, `authUser`) — `committees` from PMS's richer query (with `sub_committees`, `work_distribution_plans`, `active_assignment_count`) supersedes FL's thinner one, since the catalog tab needs those fields.

- [ ] **Step 1: Merge `index()`'s data**

In the merged controller, replace `index()` with a version that builds both payloads (assignments list from FL's existing query, committees list from PMS's existing richer query — copy both query blocks verbatim from what was read this session) and passes both, plus `users`/`fiscalYears`/`currentFiscalYear`/`authUser`, to one Inertia render. Note the catalog query below is written as `\App\Models\Committee::` (fully-qualified) rather than the bare `Committee` symbol — per the Step 2 model-alias gotcha, bare `Committee` in this file resolves to `App\Models\FacultyLoading\Committee` (used correctly by `$committees` further down, which needs its `active()` scope), while the catalog query needs the *other* model's `forFiscalYear()` scope:

```php
    public function index(Request $request)
    {
        $user = auth()->user();

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);
        $facultyId   = $request->input('faculty_id');

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name', 'committee:id,name,code,parent_committee_id', 'academicTerm.schoolYear'])
            ->when($termId,    fn ($q) => $q->where('academic_term_id', $termId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
            ->orderBy('user_id')
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->orderBy('name')->get(['id', 'name', 'position']);

        $currentYear = \App\Models\IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        $catalogQuery = \App\Models\Committee::with(['head', 'members', 'workDistributionPlans', 'subCommittees.head', 'subCommittees.members'])
            ->whereNull('parent_committee_id')
            ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY));

        $catalogRaw = $catalogQuery->orderBy('name')->get();
        $allCatalogIds = $catalogRaw->flatMap(fn ($c) => collect([$c->id])->merge($c->subCommittees->pluck('id')));
        $assignmentCounts = $termId
            ? FacultyCommitteeAssignment::whereIn('committee_id', $allCatalogIds)
                ->where('academic_term_id', $termId)->where('status', 'active')
                ->selectRaw('committee_id, COUNT(*) as cnt')->groupBy('committee_id')->pluck('cnt', 'committee_id')
            : collect();

        $catalog = $catalogRaw->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'fiscal_year' => $c->fiscal_year,
            'head_id' => $c->head_id, 'head' => $c->head?->only('id', 'name'), 'description' => $c->description,
            'members' => $c->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'pivot' => ['task' => $m->pivot->task]]),
            'active_assignment_count' => $assignmentCounts->get($c->id, 0),
            'work_distribution_plans' => $c->workDistributionPlans->map(fn ($p) => ['id' => $p->id])->values(),
            'sub_committees' => $c->subCommittees->map(fn ($sub) => [
                'id' => $sub->id, 'name' => $sub->name, 'head_id' => $sub->head_id, 'head' => $sub->head?->only('id', 'name'),
                'active_assignment_count' => $assignmentCounts->get($sub->id, 0),
                'members' => $sub->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'pivot' => ['task' => $m->pivot->task]]),
            ])->values(),
        ]);

        $committees = Committee::active()
            ->with(['workDistributionPlans:id', 'subCommittees:id,name,code,chairperson_load_units,member_load_units,parent_committee_id,is_active'])
            ->whereNull('parent_committee_id')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'committee_type', 'chairperson_load_units', 'member_load_units', 'parent_committee_id'])
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'committee_type' => $c->committee_type,
                'chairperson_load_units' => (float) $c->chairperson_load_units, 'member_load_units' => (float) $c->member_load_units,
                'plan_ids' => $c->workDistributionPlans->pluck('id')->toArray(),
                'sub_committees' => $c->subCommittees->where('is_active', true)->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'code' => $s->code,
                    'chairperson_load_units' => (float) $s->chairperson_load_units, 'member_load_units' => (float) $s->member_load_units,
                ])->values(),
            ]);

        $plans = WorkDistributionPlan::orderBy('success_indicator')->get(['id', 'success_indicator', 'rated_by']);

        return Inertia::render('PerformanceManagement/Committees/Index', [
            'assignments' => $assignments,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'committees'  => $committees,
            'catalog'     => $catalog,
            'plans'       => $plans,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
            'users'       => User::employees()->select('id', 'name', 'position')->orderBy('name')->get(),
            'authUser'    => $user->only('id', 'name'),
            'fiscalYears' => \App\Models\IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
        ]);
    }
```

- [ ] **Step 2: Build the merged Vue page**

Write `resources/js/Pages/PerformanceManagement/Committees/Index.vue` starting from the existing `FacultyLoading/CommitteeAssignments/Index.vue` (read in full this session — reuse its `<script setup>` and assignment-table `<template>` verbatim, only changing every `route('faculty-loading.committee-assignments.X', ...)` call to `route('pm-committees.X', ...)`), then:

1. Add `catalog: { type: Array, default: () => [] }` to `defineProps`.
2. Wrap the existing page body in `<AppTabs v-model="activeTab" :tabs="pageTabs">` with two tabs — `assignments` (existing table + Assign modal, unchanged) and `catalog` (new tab).
3. Add the catalog tab's table + "New Committee"/"Edit Committee" modal by porting the `<template>` markup and `<script setup>` state (`showModal`, `modalMode`, `memberSearch`, `emptySubCommittee`, `emptyForm`, `form`, `filteredUsers`, `filteredSubUsers`, `openModal`, `toggleMember`, `toggleSubMember`, `addSubCommittee`, `removeSubCommittee`, `submitCommittee`, `deleteCommittee`, `structureBadge`) from the old `PerformanceManagement/Committees/Index.vue` (read in full this session) unchanged, with two route-name edits: `pm-committees.store` → `pm-committees.catalog.store`, `pm-committees.update` → `pm-committees.catalog.update`, `pm-committees.destroy` → `pm-committees.catalog.destroy`. Its own table's `Link :href="route('pm-committees.show', committee.id)"` stays as-is (still the same route name).

(No new code block here — steps 1-3 above are the ported sections themselves; there is no additional logic beyond the tab wrapper and the three renamed routes.)

Add to the imports: `import AppTabs from '@/Components/AppTabs.vue'` and the icons the catalog tab's markup uses (`PencilSquareIcon` from the old PMS file, already imported nowhere else in the FL file). Add:

```js
const activeTab = ref('assignments')
const pageTabs = [
  { key: 'assignments', label: 'Assignments' },
  { key: 'catalog', label: 'Committee Catalog' },
]
```

- [ ] **Step 3: Manual verification**

Run: `npm run build`
Expected: build succeeds with no errors referencing `PerformanceManagement/Committees/Index.vue`

- [ ] **Step 4: Delete the superseded FL page**

```bash
git rm resources/js/Pages/FacultyLoading/CommitteeAssignments/Index.vue
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php resources/js/Pages/PerformanceManagement/Committees/Index.vue
git commit -m "refactor(committees): merge assignment + catalog management into one tabbed Index page"
```

---

### Task 10: Merge the Show pages — member-centric IPCR V2 rating view

**Files:**
- Modify: `app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php::show()`
- Create: `resources/js/Pages/PerformanceManagement/Committees/Show.vue` (new content, replacing the old PMS version)
- Delete: `resources/js/Pages/FacultyLoading/CommitteeAssignments/Show.vue`

**Interfaces:**
- Consumes: `CommitteeIpcrRatingService::resolveSupportItems(FacultyCommitteeAssignment $assignment): Collection` (Task 4).

The old `planMemberData` grouped by the **committee's own** tagged Work Distribution Plans (`$committee->workDistributionPlans`) — but `EmployeeFunctionSyncService::syncCommitteeAssignment()` resolves each member's Support Function from that **member's own assignment-level** tagged plans (`$assignment->workDistributionPlans()`), which can differ from the committee-level tag. Grouping by plan is no longer correct — this task switches the Show page to a member-centric list, one row per active assignment, each showing that member's own resolved IPCR V2 support item(s).

- [ ] **Step 1: Rewrite `show()`'s data shape**

```php
    public function show(Request $request, Committee $committee): Response
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $committee->load(['head:id,name,position', 'workDistributionPlans:id,success_indicator,rated_by']);

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name,position'])
            ->where('committee_id', $committee->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get();

        $authUser  = auth()->user();
        $canManage = $authUser->hasPermission('faculty_loading.manage');

        $isChairperson = $assignments->where('user_id', $authUser->id)->whereIn('role', ['chairperson', 'co_chair'])->isNotEmpty();
        if (! $isChairperson && $committee->parent_committee_id) {
            $isChairperson = FacultyCommitteeAssignment::where('committee_id', $committee->parent_committee_id)
                ->where('academic_term_id', $termId)->where('user_id', $authUser->id)
                ->whereIn('role', ['chairperson', 'co_chair'])->where('status', 'active')->exists();
        }

        $isMember = $assignments->contains('user_id', $authUser->id)
            || $committee->head_id === $authUser->id
            || FacultyCommitteeAssignment::whereIn('committee_id', array_filter([$committee->id, $committee->parent_committee_id]))
                ->where('user_id', $authUser->id)->where('status', 'active')->exists();

        abort_unless($canManage || $isChairperson || $isMember, 403, 'You are not a member of this committee.');

        $members = $assignments->map(function ($a) {
            $items = $this->ipcrRating->resolveSupportItems($a)->map(fn ($item) => [
                'id'                     => $item->id,
                'label'                  => $item->label,
                'success_indicator'      => $item->success_indicator,
                'target'                 => $item->target,
                'actual_accomplishment'  => $item->actual_accomplishment,
                'mov_link'               => $item->mov_link,
                'quality_rating'         => $item->quality_rating,
                'efficiency_rating'      => $item->efficiency_rating,
                'timeliness_rating'      => $item->timeliness_rating,
                'row_average'            => $item->row_average,
                'ipcr_status'            => $item->ipcr->status,
            ])->values();

            return [
                'assignment_id'  => $a->id,
                'user_id'        => $a->faculty->id,
                'user_name'      => $a->faculty->name,
                'user_position'  => $a->faculty->position,
                'role'           => $a->role,
                'is_chairperson' => $a->isChairperson(),
                'items'          => $items,
            ];
        });

        return Inertia::render('PerformanceManagement/Committees/Show', [
            'committee' => [
                'id' => $committee->id, 'name' => $committee->name, 'code' => $committee->code,
                'committee_type' => $committee->committee_type, 'description' => $committee->description,
                'chairperson_title' => $committee->chairperson_title,
                'chairperson_load_units' => (float) $committee->chairperson_load_units,
                'member_load_units' => (float) $committee->member_load_units,
                'head' => $committee->head?->only('id', 'name', 'position'),
            ],
            'members'        => $members,
            'terms'          => $terms,
            'selectedTermId' => (int) $termId,
            'authUser'       => $authUser->only('id', 'name'),
            'isChairperson'  => $isChairperson,
            'canManage'      => $canManage,
            'tasks'            => \App\Models\CommitteeTask::with(['assignees:id,name', 'plan:id,success_indicator', 'period:id,label', 'updates.user:id,name'])
                                    ->withCount('updates')->where('committee_id', $committee->id)
                                    ->forPeriod(\App\Models\IPCRRatingPeriod::current()->value('id'))->orderBy('sort_order')->get(),
            'boardMembers'     => $assignments->map(fn ($a) => $a->faculty->only('id', 'name'))->unique('id')->values(),
            'canManageBoard'   => app(\App\Services\CommitteeBoardService::class)->canManageBoard($authUser, \App\Models\Committee::find($committee->id)),
        ]);
    }
```

(Drops the `rating_period_id`/`selectedPeriodId` query param entirely — a member's items are always resolved against the *current* rating period via `CommitteeIpcrRatingService`, matching how IPCR V2 itself has no concept of viewing a past period's items on a live page. The task board keeps its own period scoping unchanged.)

- [ ] **Step 2: Build the merged Vue Show page**

Write `resources/js/Pages/PerformanceManagement/Committees/Show.vue` starting from `FacultyLoading/CommitteeAssignments/Show.vue` (read in full this session):

1. Change `defineProps` to accept `members` instead of `planMemberData`, drop `ratingPeriods`/`selectedPeriodId`.
2. Replace the "Per-plan sections" template block (the `AppCard v-for="entry in planMemberData"` loop) with one table iterating `members`, and within each member row, iterating that member's own `items` (usually one row, occasionally more for a multi-tagged assignment):

```html
      <AppCard :padded="false">
        <AppTable :is-empty="!members?.length">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Member</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Role</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider max-w-xs">Accomplishment / MOV</th>
              <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-10">Q</th>
              <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-10">E</th>
              <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-10">T</th>
              <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-14">Avg</th>
              <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-24">Action</th>
            </tr>
          </template>

          <template v-for="member in members" :key="member.assignment_id">
            <tr v-if="!member.items.length" class="hover:bg-indigo-50/40">
              <td class="px-4 py-3">
                <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
              </td>
              <td class="px-4 py-3"><AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge></td>
              <td class="px-4 py-3 text-xs text-slate-400 italic" colspan="6">No IPCR V2 targets generated yet for this rating period.</td>
            </tr>
            <tr v-for="item in member.items" :key="item.id" class="hover:bg-indigo-50/40">
              <td class="px-4 py-3">
                <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
              </td>
              <td class="px-4 py-3"><AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge></td>
              <td class="px-4 py-3 max-w-xs">
                <p class="text-slate-700 text-xs truncate">{{ item.actual_accomplishment || '—' }}</p>
                <a v-if="item.mov_link" :href="item.mov_link" target="_blank" class="text-indigo-600 text-xs hover:underline">MOV Link ↗</a>
              </td>
              <td class="px-3 py-3 text-center text-sm text-slate-700">{{ item.quality_rating ?? '—' }}</td>
              <td class="px-3 py-3 text-center text-sm text-slate-700">{{ item.efficiency_rating ?? '—' }}</td>
              <td class="px-3 py-3 text-center text-sm text-slate-700">{{ item.timeliness_rating ?? '—' }}</td>
              <td class="px-3 py-3 text-center text-sm font-semibold text-slate-800">{{ item.row_average ?? '—' }}</td>
              <td class="px-3 py-3 text-center">
                <AppButton v-if="(isChairperson || canManage) && item.ipcr_status !== 'New Target' && item.ipcr_status !== 'For Review' && item.ipcr_status !== 'Returned for Revision'"
                  size="sm" @click="openRateModal(member, item)">Rate</AppButton>
                <span v-else-if="isChairperson || canManage" class="text-xs text-slate-400 italic">Targets not yet approved</span>
              </td>
            </tr>
          </template>

          <template #empty>
            <EmptyState title="No active members for this committee this term." />
          </template>
        </AppTable>
      </AppCard>
```

3. Replace `openModal`/`openRateModal`/`submitModal` with:

```js
const showModal = ref(false)
const modalEntry = ref(null) // { member, item }

const rateForm = ref({ support_item_id: null, accomplishment: '', mov_link: '', quality_rating: null, efficiency_rating: null, timeliness_rating: null })

function openRateModal(member, item) {
  modalEntry.value = { member, item }
  rateForm.value = {
    support_item_id: item.id,
    accomplishment: item.actual_accomplishment ?? '',
    mov_link: item.mov_link ?? '',
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
  }
  showModal.value = true
}

function closeModal() { showModal.value = false; modalEntry.value = null }

function submitModal() {
  const entry = modalEntry.value
  if (!entry) return

  submit.post(
    route('pm-committees.rate', entry.member.assignment_id),
    rateForm.value,
    {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false }) },
      onError: (err) => Swal.fire('Error', Object.values(err).flat().join('\n') || 'Failed to save.', 'error'),
    }
  )
}
```

4. Update the modal's `<template>` to drop the `isOwn`/self-accomplishment branch (removed per Task 8) — the rate form is now always the chairperson/admin rating form, bound to `rateForm` instead of `editForm`. Keep the "Compile done tasks" button, retargeted at `rateForm.value.accomplishment`.
5. Update every `route('faculty-loading.committee-assignments.X', ...)` call (the "Back" link, `switchTerm`) to `route('pm-committees.X', ...)`. Remove `switchPeriod`/the rating-period `<AppSelect>` (Step 1 dropped that query param).

- [ ] **Step 3: Manual verification**

Run: `npm run build`
Expected: build succeeds

- [ ] **Step 4: Delete the superseded FL page**

```bash
git rm resources/js/Pages/FacultyLoading/CommitteeAssignments/Show.vue
```

- [ ] **Step 5: Add a controller-level regression test for the new member-centric shape**

**Files:**
- Test: `tests/Feature/PerformanceManagement/CommitteeAssignmentControllerShowTest.php`

```php
<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_lists_each_members_own_resolved_support_item(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $admin = User::factory()->create();
        $admin->givePermission('faculty_loading.manage');
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);
        (new CommitteeIpcrSyncService())->syncForUser($member);

        $response = $this->actingAs($admin)->get(route('pm-committees.show', $committee->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Committees/Show')
            ->has('members', 1)
            ->where('members.0.items.0.label', 'Grievance Committee')
        );
    }
}
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CommitteeAssignmentControllerShowTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PerformanceManagement/CommitteeAssignmentController.php resources/js/Pages/PerformanceManagement/Committees/Show.vue tests/Feature/PerformanceManagement/CommitteeAssignmentControllerShowTest.php
git commit -m "refactor(committees): Show page becomes member-centric, reads IPCR V2 support items directly"
```

---

### Task 11: DC's own IPCR V2 Show page renders committee-sourced items read-only

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php::show()` (annotate each support item)
- Modify: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue` (or the shared `IpcrV2SupportItemsTable.vue` component it uses — whichever actually renders the rate button, confirm at implementation time by grepping for `rateSupportItem` in `resources/js/Pages/IPCRV2/` and `resources/js/Components/`)
- Test: extend `tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerCommitteeGuardTest.php` (Task 7) with an Inertia-props assertion.

**Interfaces:**
- Consumes: `CommitteeIpcrRatingService::isCommitteeSourced()` (Task 4).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerCommitteeGuardTest.php`:

```php
    public function test_show_flags_the_committee_sourced_item_for_the_frontend(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $division = Division::factory()->create();
        $dc = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $dc->id]);
        $member = User::factory()->create(['division_id' => $division->id]);
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);
        (new CommitteeIpcrSyncService())->syncForUser($member);

        $response = $this->actingAs($dc)->get(route('division-chief-ipcr-v2.show', $record->id));

        $response->assertInertia(fn ($page) => $page
            ->where('ipcr.supportItems.0.is_committee_sourced', true)
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DivisionChiefIpcrV2ControllerCommitteeGuardTest"`
Expected: FAIL — `is_committee_sourced` key doesn't exist on the item yet.

- [ ] **Step 3: Write minimal implementation**

In `DivisionChiefIpcrV2Controller::show()`, after `$record = IpcrV2Record::with([...])->findOrFail($id);`, annotate:

```php
        $record->supportItems->each(
            fn ($item) => $item->setAttribute('is_committee_sourced', $this->committeeRating->isCommitteeSourced($item))
        );
```

(`$this->committeeRating` is the same `CommitteeIpcrRatingService` instance added to the constructor in Task 7.)

In the Vue component that renders the support-items rate button (locate it via `grep -rn "rateSupportItem\|division-chief-ipcr-v2.rateSupportItem" resources/js/`), wrap the existing rate button/inputs with `v-if="!item.is_committee_sourced"` and add an `v-else` badge:

```html
<AppBadge v-if="item.is_committee_sourced" color="slate">Rated via Committee Assignment</AppBadge>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DivisionChiefIpcrV2ControllerCommitteeGuardTest"`
Expected: PASS (2 tests)

- [ ] **Step 5: Manual verification**

Run: `npm run build`
Expected: build succeeds

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/IPCRV2/DivisionChiefIpcrV2Controller.php resources/js/Pages/IPCRV2 resources/js/Components tests/Feature/IPCRV2/DivisionChiefIpcrV2ControllerCommitteeGuardTest.php
git commit -m "feat(ipcr-v2): show committee-sourced Support Items as read-only on the Division Chief's IPCR V2 page"
```

---

### Task 12: Navigation cleanup

**Files:**
- Modify: `resources/js/Layouts/navigation.js`

- [ ] **Step 1: Remove the Faculty Loading nav entry**

Delete this block (the one read this session, under Faculty Loading's "Load Management" section):

```js
      {
        label: "Committee Assignments",
        routeName: "faculty-loading.committee-assignments.index",
        href: route("faculty-loading.committee-assignments.index"),
        icon: QueueListIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
```

Update Performance Management's existing "Committees" entry's permission to reflect the merged route-level gate:

```js
      {
        label: "Committees",
        routeName: "pm-committees.index",
        href: route("pm-committees.index"),
        icon: UserGroupIcon,
        permissions: ["accomplishments.view", "faculty_loading.manage"],
      },
```

(`navigation.js`'s permission arrays are already OR-matched elsewhere in this file for multi-permission entries — confirm this convention at implementation time by checking how an existing multi-permission entry, e.g. the Computer Laboratories entry read this session, is actually evaluated by the sidebar-rendering component before assuming OR vs AND semantics.)

- [ ] **Step 2: Manual verification**

Run: `npm run build`
Expected: build succeeds

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/navigation.js
git commit -m "chore(nav): remove Faculty Loading's separate Committee Assignments entry"
```

---

### Task 13: Full regression pass and lint

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: PASS — compare the total pass/fail count against the pre-existing baseline noted in `project_ipcr_v2_module.md` (56 pre-existing unrelated failures as of the last full run) to confirm this work introduced zero new failures.

- [ ] **Step 2: PHP lint every file this plan touched**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && find app/Http/Controllers/PerformanceManagement app/Http/Controllers/IPCRV2 app/Services/PerformanceManagement app/Services/FacultyLoading routes -name '*.php' -exec php -l {} \;"`
Expected: `No syntax errors detected` for every file

- [ ] **Step 3: Frontend build**

Run: `npm run build`
Expected: build succeeds with no warnings referencing the deleted `FacultyLoading/CommitteeAssignments` or old `PerformanceManagement/Committees` paths

- [ ] **Step 4: Grep for dangling references to deleted routes/classes**

Run: `grep -rn "faculty-loading.committee-assignments\|CommitteePerformanceController\|CommitteeRatingService" app resources/js routes tests`
Expected: no results (confirms nothing still references the retired controller, service, or route names)

---

## Explicit deviations from the approved spec (flag to the user, don't hide)

1. **Member self-service "Edit Accomplishment" from the committee page is removed** (Task 8, Step 2). The spec said self-reported accomplishment stays untouched — it does, but it now happens exclusively on the employee's own IPCR V2 Show page (`EmployeeIpcrV2Controller::updateSupportItem`, unchanged), not from a second entry point on the committee page that would otherwise become a second writer of `actual_accomplishment` for the same field the chairperson also writes. The chairperson can still set/overwrite `actual_accomplishment` from the committee page exactly as before.
2. **The Show page drops "All periods (legacy)" / arbitrary-period viewing** (Task 10) — a member's items are always the *current* rating period's, matching how the rest of IPCR V2 has no "view a past period" mode on a live record page. Historical ratings remain visible on the employee's own past IPCR V2 records, unaffected.
