# Dyna Backend Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Grow Dyna's tool registry from 2 to 22 tools — 9 thin adapters over the existing
Executive Dashboard service, 9 grounded CID/student/faculty tools (2 with individual drill-down),
2 individual lookup tools — plus the `CID Chief` role grant and the Google Sign-In backend
endpoint the macOS plan depends on.

**Architecture:** Group A tools are a template-method abstract class over
`ExecutiveDashboardService::build()`, reusing its existing cache and lens logic verbatim. Group
B/C tools follow the established `DynaTool` pattern from the Phase 1 backend plan — each is a
small class querying one already-identified real table, permission-checked either by
`atlas.dyna.access` alone (pure aggregates) or by `atlas.dyna.access` plus the specific module
permission that already gates that data in the web app (discipline, homeroom-attendance,
employee/student profiles).

**Tech Stack:** Laravel 12 / PHP 8.4 — no new dependencies. Reuses `DynaToolRegistry`,
`DynaTool` interface, and `DynaBedrockClientFactory` from the Phase 1 plan
(`docs/superpowers/plans/2026-08-02-dyna-backend.md`).

## Global Constraints

- Every new tool implements the existing `App\Services\Atlas\Dyna\Tools\DynaTool` interface
  (`name()`, `description()`, `inputSchema()`, `execute(User $user, array $input): array`).
- Every new tool must be added to the `DynaToolRegistry` binding in
  `app/Providers/AppServiceProvider.php::register()` — the container cannot auto-wire this
  array (this bit Phase 1, Task 10 — see that plan's note).
- **Individual-level data is allowed** (per the approved expansion spec, revised from an
  earlier aggregate-only draft) — Dyna is restricted to `atlas.dyna.access` holders only, not
  a broad audience. What's NOT relaxed: every tool still enforces exactly the permission
  scoping the corresponding web module already enforces — a tool never grants access beyond
  what that user already has in the web app today.
- **Employee-lookup permission decision:** use `hr.employees.manage` (what's actually enforced
  on `GET /hr/employees` today, `routes/web.php:1238`), not `hr.employees.view` (defined in
  `PermissionsSeeder.php:29` but wired to zero routes — using it would grant Dyna access nobody
  currently has anywhere in the web app, breaking the mirror-existing-access principle).
- **Student-lookup permission:** `students.enrollment.view` (`routes/registrar.php:63,67,71,75`)
  — currently granted only to `Student Discipline Officer`. This looks like a real gap
  elsewhere in the app (Registrar lacking view access to its own module) — out of scope to fix
  here; Dyna just mirrors it as-is.
- Discipline/homeroom-attendance individual lookups resolve the student via the existing
  `student()` `BelongsTo` relation already defined on `DisciplineCase`/`MonthlyReportLine`
  (`App\Models\Student`, unconstrained legacy-table FK, `full_name` accessor) — reuse it, don't
  hand-roll a new join.
- Tests: PHPUnit Feature tests, `RefreshDatabase`, mirror `userWithPermissions()`/`userWithRole()`
  helper patterns already established in `tests/Feature/Atlas/Dyna/*` from Phase 1.

---

## File structure

```
database/migrations/
  2026_08_03_100000_add_cid_chief_to_dyna_permission.php

app/Services/Atlas/Dyna/Tools/
  Concerns/ResolvesDivisionLens.php               (shared trait, Group A)
  ExecutiveDashboardAdapterTool.php                (abstract base, Group A)
  GetPerformanceStatsTool.php
  GetRequestsStatsTool.php
  GetSatisfactionStatsTool.php
  GetAcademicsStatsTool.php
  GetRecruitmentStatsTool.php
  GetFinanceStatsTool.php
  GetOperationsStatsTool.php
  GetAttentionItemsTool.php
  GetDivisionScorecardTool.php
  GetFacultyLoadDistributionTool.php
  GetClassRecordComplianceTool.php
  GetTeacherAttendanceStatsTool.php
  GetEnrollmentStatusBreakdownTool.php
  GetGateAttendanceTrendTool.php
  GetLibraryStatsTool.php
  GetCompetitionsStatsTool.php
  GetDisciplineCaseStatsTool.php
  GetHomeroomAttendanceSummaryTool.php
  GetEmployeeInfoTool.php
  GetStudentInfoTool.php

app/Services/Atlas/Dyna/
  DynaGoogleClientFactory.php

app/Http/Controllers/Api/
  DynaAuthController.php                           (modify — add loginWithGoogle)

routes/api.php                                      (modify — add /login/google route)

app/Providers/AppServiceProvider.php                (modify — register all 20 new tools)

tests/Feature/Atlas/Dyna/
  DynaCidChiefPermissionTest.php
  ExecutiveDashboardAdapterToolsTest.php
  GetFacultyLoadDistributionToolTest.php
  GetClassRecordComplianceToolTest.php
  GetTeacherAttendanceStatsToolTest.php
  GetEnrollmentStatusBreakdownToolTest.php
  GetGateAttendanceTrendToolTest.php
  GetLibraryStatsToolTest.php
  GetCompetitionsStatsToolTest.php
  GetDisciplineCaseStatsToolTest.php
  GetHomeroomAttendanceSummaryToolTest.php
  GetEmployeeInfoToolTest.php
  GetStudentInfoToolTest.php
  DynaGoogleLoginTest.php
```

---

### Task 1: `CID Chief` added to `atlas.dyna.access`

**Files:**
- Create: `database/migrations/2026_08_03_100000_add_cid_chief_to_dyna_permission.php`
- Test: `tests/Feature/Atlas/Dyna/DynaCidChiefPermissionTest.php`

**Interfaces:**
- Consumes: `atlas.dyna.access` permission row (Phase 1, Task 2).
- Produces: `permission_role` grant for `CID Chief` → `atlas.dyna.access`. No new interface for
  later tasks — this only affects who can reach the tools built in this plan.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynaCidChiefPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cid_chief_role_is_granted_atlas_dyna_access(): void
    {
        Role::firstOrCreate(['name' => 'CID Chief']);

        $migration = require database_path('migrations/2026_08_03_100000_add_cid_chief_to_dyna_permission.php');
        $migration->up();

        $roleId = DB::table('roles')->where('name', 'CID Chief')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        $this->assertDatabaseHas('permission_role', ['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaCidChiefPermissionTest"`
Expected: FAIL — `require` fatals, migration file doesn't exist.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'CID Chief')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'CID Chief')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }
    }
};
```

- [ ] **Step 4: Run migration and test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_03_100000_add_cid_chief_to_dyna_permission.php"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaCidChiefPermissionTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_03_100000_add_cid_chief_to_dyna_permission.php tests/Feature/Atlas/Dyna/DynaCidChiefPermissionTest.php
git commit -m "feat(dyna): grant CID Chief role atlas.dyna.access"
```

---

### Task 2: Group A — 9 Executive Dashboard adapter tools

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/Concerns/ResolvesDivisionLens.php`
- Create: `app/Services/Atlas/Dyna/Tools/ExecutiveDashboardAdapterTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetPerformanceStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetRequestsStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetSatisfactionStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetAcademicsStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetRecruitmentStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetFinanceStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetOperationsStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetAttentionItemsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetDivisionScorecardTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/ExecutiveDashboardAdapterToolsTest.php`

**Interfaces:**
- Consumes: `App\Services\ExecutiveDashboardService::build(?int $divisionId): array` (existing,
  confirmed public, returns keys `workforce`/`performance`/`requests`/`satisfaction`/`academics`/
  `recruitment`/`finance`/`operations`/`attention`/`scorecard`/`generatedAt` — the 10 section
  methods themselves are `private`, so adapters MUST go through `build()`, never call a section
  method directly), `App\Models\Division` (existing, `division_chief_id` column).
- Produces: `ExecutiveDashboardAdapterTool` (abstract) with template method
  `abstract protected function sectionKey(): string` and `abstract protected function exposesDivisionFilter(): bool`;
  9 concrete subclasses, each `name()` matching the table in the spec exactly
  (`get_performance_stats`, `get_requests_stats`, `get_satisfaction_stats`,
  `get_academics_stats`, `get_recruitment_stats`, `get_finance_stats`,
  `get_operations_stats`, `get_attention_items`, `get_division_scorecard`).

This is one task covering all 9 tools — they are mechanically identical (same base class,
differ only in `sectionKey()`/`inputSchema()`/description text), so a reviewer would approve or
reject them as one unit; splitting them into 9 separate tasks would be pure ceremony.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetAttentionItemsTool;
use App\Services\Atlas\Dyna\Tools\GetDivisionScorecardTool;
use App\Services\Atlas\Dyna\Tools\GetPerformanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetSatisfactionStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardAdapterToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_performance_stats_returns_only_the_performance_section(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetPerformanceStatsTool(app(\App\Services\ExecutiveDashboardService::class)))
            ->execute($administrator, []);

        // ExecutiveDashboardService::performance() always returns these keys regardless of data volume.
        $this->assertArrayHasKey('funnel', $result);
        $this->assertArrayHasKey('complianceByDivision', $result);
    }

    public function test_division_chief_is_locked_to_their_own_division_for_scoped_sections(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $divisionA->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        // Attempt to request divisionB's data — must be ignored, locked to own division.
        $tool = new GetAttentionItemsTool(app(\App\Services\ExecutiveDashboardService::class));
        $resultOwn = $tool->execute($chief, []);
        $resultAttemptOther = $tool->execute($chief, ['division_id' => $divisionB->id]);

        $this->assertEquals($resultOwn, $resultAttemptOther);
    }

    public function test_campus_wide_sections_ignore_division_id_and_never_error(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetSatisfactionStatsTool(app(\App\Services\ExecutiveDashboardService::class)))
            ->execute($administrator, []);

        $this->assertIsArray($result);
    }

    public function test_scorecard_is_empty_for_a_division_locked_user_matching_dashboard_behavior(): void
    {
        $division = Division::factory()->create();
        $chief = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetDivisionScorecardTool(app(\App\Services\ExecutiveDashboardService::class)))
            ->execute($chief, []);

        // ExecutiveDashboardService::build() sets 'scorecard' => null whenever $divisionId is non-null.
        // ExecutiveDashboardService::build() sets 'scorecard' => null whenever $divisionId is
        // non-null; the tool interface requires an array return, so this becomes a note instead
        // (real bug found during execution — see the execute() note below).
        $this->assertArrayHasKey('note', $result);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ExecutiveDashboardAdapterToolsTest"`
Expected: FAIL — classes don't exist.

- [ ] **Step 3: Write the shared trait, abstract base, and 9 concrete tools**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools\Concerns;

use App\Models\Division;
use App\Models\User;

trait ResolvesDivisionLens
{
    /**
     * Mirrors ExecutiveDashboardController::resolveLens() exactly — a Division Chief is
     * locked to their own division regardless of what $requestedDivisionId asks for;
     * OCD/Administrator get campus-wide, optionally narrowed by $requestedDivisionId.
     */
    private function resolveDivisionId(User $user, ?int $requestedDivisionId): ?int
    {
        $ownDivisionId = Division::where('division_chief_id', $user->id)->value('id');
        $isCampusLens = $user->isSuperAdmin() || $user->hasRole('OCD') || ! $ownDivisionId;

        return $isCampusLens ? $requestedDivisionId : $ownDivisionId;
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\User;
use App\Services\Atlas\Dyna\Tools\Concerns\ResolvesDivisionLens;
use App\Services\ExecutiveDashboardService;

abstract class ExecutiveDashboardAdapterTool implements DynaTool
{
    use ResolvesDivisionLens;

    public function __construct(private readonly ExecutiveDashboardService $dashboard) {}

    abstract protected function sectionKey(): string;

    /** Whether this section's data actually varies by division (controls inputSchema only). */
    abstract protected function exposesDivisionFilter(): bool;

    public function inputSchema(): array
    {
        if (! $this->exposesDivisionFilter()) {
            return ['type' => 'object', 'properties' => []];
        }

        return [
            'type' => 'object',
            'properties' => [
                'division_id' => [
                    'type' => 'integer',
                    'description' => 'Optional division ID to filter to (Administrator/OCD only — a Division Chief is always locked to their own division).',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $divisionId = $this->resolveDivisionId($user, $input['division_id'] ?? null);

        $section = $this->dashboard->build($divisionId)[$this->sectionKey()];

        // Real bug found during execution: only `scorecard` can legitimately be null
        // (campus-lens only, per ExecutiveDashboardService::build()) — the DynaTool interface
        // requires an array return, so a plain `?? []` here silently hides *why* it's empty
        // and also fails the type contract's intent (execute() must return array, not null).
        // Every other section key is never null, so this fallback is unreachable for them.
        return $section ?? ['note' => 'Not available for a division-locked view — this section is campus-wide only.'];
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetPerformanceStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_performance_stats'; }
    public function description(): string { return 'Returns IPCR performance data: submission funnel, compliance rate by division, and rating distribution for the current rating period.'; }
    protected function sectionKey(): string { return 'performance'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetRequestsStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_requests_stats'; }
    public function description(): string { return 'Returns IT/Facility/Vehicle/Service/Work/Travel request stats: totals, this month, completion rate, and how many are open past 7 days.'; }
    protected function sectionKey(): string { return 'requests'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetSatisfactionStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_satisfaction_stats'; }
    public function description(): string { return 'Returns campus-wide CSM (client satisfaction) survey results: per-dimension averages, overall adjectival rating, and top offices by response volume.'; }
    protected function sectionKey(): string { return 'satisfaction'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetAcademicsStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_academics_stats'; }
    public function description(): string { return 'Returns campus-wide academic snapshot: enrollment by grade level, faculty load status distribution, class record status, and today\'s gate scan volume.'; }
    protected function sectionKey(): string { return 'academics'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetRecruitmentStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_recruitment_stats'; }
    public function description(): string { return 'Returns campus-wide recruitment stats: open vacancies, applicant pipeline by stage, applications this month, and pending placements.'; }
    protected function sectionKey(): string { return 'recruitment'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetFinanceStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_finance_stats'; }
    public function description(): string { return 'Returns campus-wide finance snapshot: latest payroll run summary, 6-month net-pay trend, and purchase request / disbursement voucher status counts.'; }
    protected function sectionKey(): string { return 'finance'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetOperationsStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_operations_stats'; }
    public function description(): string { return 'Returns operations stats: open/overdue document routings, issuances this month, open error reports, and committee task progress.'; }
    protected function sectionKey(): string { return 'operations'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetAttentionItemsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_attention_items'; }
    public function description(): string { return 'Returns a flagged list of items needing action: overdue routings, stuck committee tasks, open error reports, requests open past 7 days, leave pending past 5 days, and employees missing an IPCR for the current period.'; }
    protected function sectionKey(): string { return 'attention'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetDivisionScorecardTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_division_scorecard'; }
    public function description(): string { return 'Returns a per-division rollup comparison (headcount, leave days/employee, IPCR submission rate, request completion rate). Only populated for a campus-wide view (Administrator/OCD) — empty for a Division Chief, since this is inherently a cross-division comparison.'; }
    protected function sectionKey(): string { return 'scorecard'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
```

Register all 9 in `app/Providers/AppServiceProvider.php` — modify the existing
`DynaToolRegistry` binding added in Phase 1 (Task 10):

```php
use App\Services\Atlas\Dyna\Tools\GetPerformanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetRequestsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetSatisfactionStatsTool;
use App\Services\Atlas\Dyna\Tools\GetAcademicsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetRecruitmentStatsTool;
use App\Services\Atlas\Dyna\Tools\GetFinanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetOperationsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetAttentionItemsTool;
use App\Services\Atlas\Dyna\Tools\GetDivisionScorecardTool;
```

```php
$this->app->singleton(DynaToolRegistry::class, function ($app) {
    return new DynaToolRegistry([
        $app->make(GetHeadcountTool::class),
        $app->make(GetLeaveTrendsTool::class),
        $app->make(GetPerformanceStatsTool::class),
        $app->make(GetRequestsStatsTool::class),
        $app->make(GetSatisfactionStatsTool::class),
        $app->make(GetAcademicsStatsTool::class),
        $app->make(GetRecruitmentStatsTool::class),
        $app->make(GetFinanceStatsTool::class),
        $app->make(GetOperationsStatsTool::class),
        $app->make(GetAttentionItemsTool::class),
        $app->make(GetDivisionScorecardTool::class),
        // Tasks 3-8 append their tools to this same array.
    ]);
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ExecutiveDashboardAdapterToolsTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/Concerns/ResolvesDivisionLens.php app/Services/Atlas/Dyna/Tools/ExecutiveDashboardAdapterTool.php app/Services/Atlas/Dyna/Tools/Get*StatsTool.php app/Services/Atlas/Dyna/Tools/GetAttentionItemsTool.php app/Services/Atlas/Dyna/Tools/GetDivisionScorecardTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/ExecutiveDashboardAdapterToolsTest.php
git commit -m "feat(dyna): add 9 Executive Dashboard adapter tools"
```

---

### Task 3: Faculty tools — load distribution, class record compliance, teacher attendance

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetFacultyLoadDistributionTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetClassRecordComplianceTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetTeacherAttendanceStatsTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/GetFacultyLoadDistributionToolTest.php`
- Test: `tests/Feature/Atlas/Dyna/GetClassRecordComplianceToolTest.php`
- Test: `tests/Feature/Atlas/Dyna/GetTeacherAttendanceStatsToolTest.php`

**Interfaces:**
- Consumes: `App\Models\FacultyLoading\FacultyLoad` (`user_id`, `school_year_id`,
  `load_status` enum underload/full_load/overload), `App\Models\ClassRecord\ClassRecord`
  (`teacher_id`, `school_year_id`, `status` enum draft/submitted/checked), `App\Models\FacultyLoading\TeacherTapLog`
  (`user_id`, `status` enum on_time/late/no_match, `is_late`, `late_minutes`).
- Produces: `GetFacultyLoadDistributionTool` (`get_faculty_load_distribution`),
  `GetClassRecordComplianceTool` (`get_class_record_compliance`),
  `GetTeacherAttendanceStatsTool` (`get_teacher_attendance_stats`) — all `atlas.dyna.access`
  only (no additional module permission — pure aggregates, per the spec's access-control
  decision).

All three are division-scoped via `whereHas('faculty', ...)` / `whereHas('teacher', ...)` on
`users.division_id`, following the exact pattern `GetLeaveTrendsTool` already established in
Phase 1 — reuse that pattern, don't invent a new one.

**Real relation names found during execution:** `FacultyLoad`'s relation to the faculty member
is `faculty()`, not `user()` (confirmed via `app/Models/FacultyLoading/FacultyLoad.php:50`) —
despite the FK column itself being named `user_id`. `ClassRecord::teacher()` and
`TeacherTapLog::teacher()` are both correctly named `teacher()` as assumed. Use `faculty()` for
the `FacultyLoad` scoping call, not `user()`.

**Real required-fixture chain found during execution (needed to construct valid test rows,
none of it obvious from `$fillable` alone):**
- `faculty_loads` requires `school_year_id` and `academic_term_id` (both FKs, no default) —
  confirmed via `database/migrations/*_create_faculty_loads_table.php`.
- `school_years` requires `start_date` and `end_date` in addition to `name`/`is_current` — the
  `SchoolYear::where('is_current', true)->first()` convention from `CLAUDE.md` doesn't cover
  what's required to *create* one.
- `academic_terms` requires `school_year_id` (FK) and `name`.
- `class_records` requires `grading_option_id` (FK to `grading_options`) and `school_year`
  (string) — `school_year_id` is a separate, later-added column and IS nullable, unlike
  `school_year` itself.
- `teacher_tap_logs` requires `classroom_id` (FK to `classrooms`).
- `classrooms` requires `school_year_id` — nullable in the original create-table migration,
  made `nullable(false)` by a later migration
  (`database/migrations/2026_06_09_000012_add_school_year_id_to_classrooms.php`), which also
  changed `code`'s uniqueness from global to per-`school_year_id`.

- [ ] **Step 1: Write the three failing tests**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetFacultyLoadDistributionTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetFacultyLoadDistributionToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_load_status_distribution_scoped_to_division_chiefs_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $facultyA1 = User::factory()->create(['division_id' => $divisionA->id]);
        $facultyA2 = User::factory()->create(['division_id' => $divisionA->id]);
        $facultyB1 = User::factory()->create(['division_id' => $divisionB->id]);

        // faculty_loads.school_year_id and .academic_term_id are required FKs (no default) —
        // confirmed via database/migrations/*_create_faculty_loads_table.php. school_years
        // also requires start_date/end_date (no default) — confirmed via
        // database/migrations/*_create_school_years_table.php.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => '1st Semester']);

        FacultyLoad::create(['user_id' => $facultyA1->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'overload', 'total_units' => 20]);
        FacultyLoad::create(['user_id' => $facultyA2->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'full_load', 'total_units' => 18]);
        FacultyLoad::create(['user_id' => $facultyB1->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'underload', 'total_units' => 10]);

        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $divisionA->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetFacultyLoadDistributionTool())->execute($chief, []);

        $this->assertEquals(['overload' => 1, 'full_load' => 1], $result);
    }
}
```

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetClassRecordComplianceTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetClassRecordComplianceToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_campus_wide_status_breakdown(): void
    {
        $teacher = User::factory()->create();
        // class_records.grading_option_id and .school_year are required (no default) —
        // confirmed via database/migrations/*_create_class_records_table.php. school_year_id
        // (a later-added FK) is nullable, unlike school_year itself.
        $gradingOption = GradingOption::create(['name' => 'Standard']);
        ClassRecord::create(['teacher_id' => $teacher->id, 'grading_option_id' => $gradingOption->id, 'school_year' => '2026-2027', 'status' => 'checked', 'subject_name' => 'Math', 'year_level_section' => 'G7-A']);
        ClassRecord::create(['teacher_id' => $teacher->id, 'grading_option_id' => $gradingOption->id, 'school_year' => '2026-2027', 'status' => 'draft', 'subject_name' => 'Science', 'year_level_section' => 'G7-A']);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetClassRecordComplianceTool())->execute($administrator, []);

        $this->assertEquals(['checked' => 1, 'draft' => 1], $result);
    }
}
```

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetTeacherAttendanceStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetTeacherAttendanceStatsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_campus_wide_tap_status_breakdown(): void
    {
        $teacher = User::factory()->create();
        // teacher_tap_logs.classroom_id is a required FK (no default) — confirmed via
        // database/migrations/*_create_teacher_tap_logs_table.php. classrooms.school_year_id
        // is also required (made non-nullable by a later migration) — confirmed via
        // database/migrations/*_add_school_year_id_to_classrooms.php.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $classroom = Classroom::create(['name' => 'Science Hall 101', 'code' => 'SH-101-'.uniqid(), 'school_year_id' => $schoolYear->id]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'on_time', 'tapped_at' => now(), 'is_late' => false]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'late', 'tapped_at' => now()->addMinute(), 'is_late' => true, 'late_minutes' => 12]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'no_match', 'tapped_at' => now()->addMinutes(2), 'is_late' => false]);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetTeacherAttendanceStatsTool())->execute($administrator, []);

        $this->assertEquals(['on_time' => 1, 'late' => 1, 'no_match' => 1], $result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='GetFacultyLoadDistributionToolTest|GetClassRecordComplianceToolTest|GetTeacherAttendanceStatsToolTest'"`
Expected: FAIL — classes don't exist.

- [ ] **Step 3: Write the three tools**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\FacultyLoading\FacultyLoad;
use App\Models\User;

class GetFacultyLoadDistributionTool implements DynaTool
{
    public function name(): string { return 'get_faculty_load_distribution'; }

    public function description(): string
    {
        return 'Returns faculty teaching-load status distribution (underload/full_load/overload counts). '
             . 'Use for questions about faculty workload, overload counts, or load balancing.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $query = FacultyLoad::query();

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('faculty', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('load_status, count(*) as total')
            ->groupBy('load_status')
            ->pluck('total', 'load_status')
            ->toArray();
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\ClassRecord\ClassRecord;
use App\Models\User;

class GetClassRecordComplianceTool implements DynaTool
{
    public function name(): string { return 'get_class_record_compliance'; }

    public function description(): string
    {
        return 'Returns class record status distribution (draft/submitted/checked counts). '
             . 'Use for questions about grade submission compliance or class record completion.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $query = ClassRecord::query();

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('teacher', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;

class GetTeacherAttendanceStatsTool implements DynaTool
{
    public function name(): string { return 'get_teacher_attendance_stats'; }

    public function description(): string
    {
        return 'Returns teacher NFC tap-attendance status distribution (on_time/late/no_match counts). '
             . 'Use for questions about teacher punctuality or attendance tracking data quality.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $query = TeacherTapLog::query();

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('teacher', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
```

Note: `TeacherTapLog`'s relation to the tapping teacher is named `teacher()`, not `user()`
(confirmed via `app/Models/FacultyLoading/TeacherTapLog.php:39`) — `ClassRecord::teacher()` is
also `teacher()` (confirmed via `app/Models/ClassRecord/ClassRecord.php:97`), already matching
the code above. Both relations are pre-existing; no new relation needs to be added.

Append the three tools to the `DynaToolRegistry` array in `AppServiceProvider`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='GetFacultyLoadDistributionToolTest|GetClassRecordComplianceToolTest|GetTeacherAttendanceStatsToolTest'"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetFacultyLoadDistributionTool.php app/Services/Atlas/Dyna/Tools/GetClassRecordComplianceTool.php app/Services/Atlas/Dyna/Tools/GetTeacherAttendanceStatsTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/GetFacultyLoadDistributionToolTest.php tests/Feature/Atlas/Dyna/GetClassRecordComplianceToolTest.php tests/Feature/Atlas/Dyna/GetTeacherAttendanceStatsToolTest.php
git commit -m "feat(dyna): add faculty load, class record, and teacher attendance tools"
```

---

### Task 4: Student/operational tools — enrollment, gate attendance, library, competitions

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetEnrollmentStatusBreakdownTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetGateAttendanceTrendTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetLibraryStatsTool.php`
- Create: `app/Services/Atlas/Dyna/Tools/GetCompetitionsStatsTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/GetEnrollmentStatusBreakdownToolTest.php`
- Test: `tests/Feature/Atlas/Dyna/GetGateAttendanceTrendToolTest.php`
- Test: `tests/Feature/Atlas/Dyna/GetLibraryStatsToolTest.php`
- Test: `tests/Feature/Atlas/Dyna/GetCompetitionsStatsToolTest.php`

**Interfaces:**
- Consumes: `App\Models\Registrar\StudentEnrollment` (`status` enum
  enrolled/dropped/transferred_out/on_leave/completed), `App\Models\StudentAttendance\StudentAttendanceLog`
  (`scan_time`, `type`), `App\Models\Borrowing` (`status`), `App\Models\CID\Competition`
  (`level`, `date_from`) + `App\Models\CID\CompetitionParticipant` (`award`).
- Produces: 4 tools, all `atlas.dyna.access`-only (campus-wide, no division scoping — these
  modules don't have a division concept the way faculty/employee data does).

- [ ] **Step 1: Write the four failing tests**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetEnrollmentStatusBreakdownTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEnrollmentStatusBreakdownToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_status_counts(): void
    {
        // student_enrollments requires school_year_id, section_id, grade_level (tinyint,
        // not a label string), and enrollment_date — confirmed via
        // database/migrations/*_create_student_enrollments_table.php. Unique on
        // [student_id, school_year_id], so distinct student_id per row below.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);

        StudentEnrollment::create(['student_id' => 1, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 7, 'status' => 'enrolled', 'enrollment_date' => now()]);
        StudentEnrollment::create(['student_id' => 2, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 7, 'status' => 'enrolled', 'enrollment_date' => now()]);
        StudentEnrollment::create(['student_id' => 3, 'school_year_id' => $schoolYear->id, 'section_id' => 2, 'grade_level' => 8, 'status' => 'transferred_out', 'enrollment_date' => now()]);

        $user = User::factory()->create();

        $result = (new GetEnrollmentStatusBreakdownTool())->execute($user, []);

        $this->assertEquals(['enrolled' => 2, 'transferred_out' => 1], $result);
    }
}
```

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetGateAttendanceTrendTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetGateAttendanceTrendToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_scan_counts_by_day_within_the_given_range(): void
    {
        // raw_barcode is required (no default) — confirmed via
        // database/migrations/*_create_student_attendance_logs_table.php.
        StudentAttendanceLog::create(['student_id' => 1, 'raw_barcode' => 'BC1', 'type' => 'in', 'scan_time' => '2026-07-27 07:00:00']);
        StudentAttendanceLog::create(['student_id' => 2, 'raw_barcode' => 'BC2', 'type' => 'in', 'scan_time' => '2026-07-27 07:05:00']);
        StudentAttendanceLog::create(['student_id' => 1, 'raw_barcode' => 'BC1', 'type' => 'in', 'scan_time' => '2026-07-28 07:00:00']);

        $user = User::factory()->create();

        $result = (new GetGateAttendanceTrendTool())->execute($user, [
            'from_date' => '2026-07-27', 'to_date' => '2026-07-28',
        ]);

        $this->assertEquals(['2026-07-27' => 2, '2026-07-28' => 1], $result);
    }
}
```

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Borrowing;
use App\Models\LibraryCollection;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetLibraryStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetLibraryStatsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_current_borrowed_overdue_and_distinct_active_borrower_counts(): void
    {
        $book = LibraryCollection::create(['title' => 'Test Book']);

        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 1, 'borrow_date' => now()->subDays(5), 'due_date' => now()->addDays(3), 'status' => 'Borrowed']);
        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 2, 'borrow_date' => now()->subDays(10), 'due_date' => now()->subDays(2), 'status' => 'Borrowed']);
        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 1, 'borrow_date' => now()->subDays(20), 'due_date' => now()->subDays(10), 'status' => 'Returned']);

        $user = User::factory()->create();

        $result = (new GetLibraryStatsTool())->execute($user, []);

        $this->assertEquals(2, $result['currently_borrowed']);
        $this->assertEquals(1, $result['overdue']);
        $this->assertEquals(2, $result['active_borrowers']);
    }
}
```

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\CID\Competition;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetCompetitionsStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCompetitionsStatsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_competition_counts_by_level(): void
    {
        // created_by is a required FK (no default) — confirmed via
        // database/migrations/*_create_competitions_table.php.
        $creator = User::factory()->create();

        Competition::create(['title' => 'Math Olympiad', 'level' => 'regional', 'date_from' => '2026-07-01', 'created_by' => $creator->id]);
        Competition::create(['title' => 'Science Fair', 'level' => 'regional', 'date_from' => '2026-07-15', 'created_by' => $creator->id]);
        Competition::create(['title' => 'Robotics Cup', 'level' => 'national', 'date_from' => '2026-06-01', 'created_by' => $creator->id]);

        $user = User::factory()->create();

        $result = (new GetCompetitionsStatsTool())->execute($user, []);

        $this->assertEquals(['regional' => 2, 'national' => 1], $result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='GetEnrollmentStatusBreakdownToolTest|GetGateAttendanceTrendToolTest|GetLibraryStatsToolTest|GetCompetitionsStatsToolTest'"`
Expected: FAIL — classes don't exist.

**Schema note (confirmed via `database/migrations/2026_01_11_000042_create_borrowings_table.php`):**
`Borrowing.borrower` is polymorphic (`borrower_type` + `borrower_id`, via `morphTo()` on
`app/Models/Borrowing.php:18`), not a plain FK to `User`. Counting "active borrowers" as
`distinct('borrower_id')` alone would be wrong — the same numeric ID could belong to different
borrower types. The implementation below counts distinct `(borrower_type, borrower_id)` pairs
instead.

- [ ] **Step 3: Write the four tools**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Registrar\StudentEnrollment;
use App\Models\User;

class GetEnrollmentStatusBreakdownTool implements DynaTool
{
    public function name(): string { return 'get_enrollment_status_breakdown'; }

    public function description(): string
    {
        return 'Returns student enrollment status counts (enrolled/dropped/transferred_out/on_leave/completed). '
             . 'Use for questions about enrollment numbers, drop rate, or transfer rate — deeper than the basic enrolled-only count in academics stats.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'school_year_id' => ['type' => 'integer', 'description' => 'Optional school year ID to filter to. Omit for the current school year across all records.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = StudentEnrollment::query();

        if (! empty($input['school_year_id'])) {
            $query->where('school_year_id', $input['school_year_id']);
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetGateAttendanceTrendTool implements DynaTool
{
    public function name(): string { return 'get_gate_attendance_trend'; }

    public function description(): string
    {
        return 'Returns daily gate-scan counts for a date range. '
             . 'Use for questions about student attendance trends over multiple days — for today only, use academics stats instead.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['from_date', 'to_date'],
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'Start date, format YYYY-MM-DD.'],
                'to_date' => ['type' => 'string', 'description' => 'End date, format YYYY-MM-DD.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        return StudentAttendanceLog::query()
            ->whereBetween('scan_time', [
                Carbon::parse($input['from_date'])->startOfDay(),
                Carbon::parse($input['to_date'])->endOfDay(),
            ])
            ->selectRaw('DATE(scan_time) as scan_date, count(*) as total')
            ->groupBy('scan_date')
            ->orderBy('scan_date')
            ->pluck('total', 'scan_date')
            ->toArray();
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Borrowing;
use App\Models\User;

class GetLibraryStatsTool implements DynaTool
{
    public function name(): string { return 'get_library_stats'; }

    public function description(): string
    {
        return 'Returns current library circulation stats: how many books are currently borrowed, how many are overdue, and how many distinct active borrowers there are.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $borrowed = Borrowing::where('status', 'Borrowed');

        return [
            'currently_borrowed' => (clone $borrowed)->count(),
            'overdue' => (clone $borrowed)->where('due_date', '<', now())->count(),
            // borrower is polymorphic (borrower_type + borrower_id) — a plain
            // distinct('borrower_id') would undercount by conflating IDs across types.
            'active_borrowers' => (clone $borrowed)->select('borrower_type', 'borrower_id')->distinct()->get()->count(),
        ];
    }
}
```

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\CID\Competition;
use App\Models\User;

class GetCompetitionsStatsTool implements DynaTool
{
    public function name(): string { return 'get_competitions_stats'; }

    public function description(): string
    {
        return 'Returns competition counts by level (campus/inter_campus/regional/national/international). '
             . 'Use for questions about competition participation or how many competitions the school entered.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'school_year_id' => ['type' => 'integer', 'description' => 'Optional school year ID to filter to.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = Competition::query();

        if (! empty($input['school_year_id'])) {
            $query->where('school_year_id', $input['school_year_id']);
        }

        return $query->selectRaw('level, count(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();
    }
}
```

Append all four to the `DynaToolRegistry` array.

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter='GetEnrollmentStatusBreakdownToolTest|GetGateAttendanceTrendToolTest|GetLibraryStatsToolTest|GetCompetitionsStatsToolTest'"`
Expected: PASS. Fix `Borrowing` column assumptions per the Step 2 note if they don't match.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetEnrollmentStatusBreakdownTool.php app/Services/Atlas/Dyna/Tools/GetGateAttendanceTrendTool.php app/Services/Atlas/Dyna/Tools/GetLibraryStatsTool.php app/Services/Atlas/Dyna/Tools/GetCompetitionsStatsTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/GetEnrollmentStatusBreakdownToolTest.php tests/Feature/Atlas/Dyna/GetGateAttendanceTrendToolTest.php tests/Feature/Atlas/Dyna/GetLibraryStatsToolTest.php tests/Feature/Atlas/Dyna/GetCompetitionsStatsToolTest.php
git commit -m "feat(dyna): add enrollment, gate attendance, library, and competitions tools"
```

---

### Task 5: `get_discipline_case_stats` (aggregate + individual, `discipline.view` gated)

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetDisciplineCaseStatsTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/GetDisciplineCaseStatsToolTest.php`

**Interfaces:**
- Consumes: `App\Models\Discipline\DisciplineCase` (`status`, `threat_level`, `student_id` via
  existing `student(): BelongsTo` relation to `App\Models\Student`, which exposes
  `full_name`/`getFullNameAttribute()`).
- Produces: `GetDisciplineCaseStatsTool` (`get_discipline_case_stats`) — gated by
  `atlas.dyna.access` **and** `discipline.view`. Two modes: no `student_identifier` → aggregate
  counts by status/threat_level; with `student_identifier` → that student's case list.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Discipline\DisciplineCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetDisciplineCaseStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetDisciplineCaseStatsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_mode_returns_counts_by_status(): void
    {
        DisciplineCase::create(['student_id' => 1, 'status' => 'resolved', 'threat_level' => 'low', 'nature_of_offense' => 'Tardiness', 'incident_date' => '2026-07-01', 'school_year_id' => 1]);
        DisciplineCase::create(['student_id' => 2, 'status' => 'under_review', 'threat_level' => 'medium', 'nature_of_offense' => 'Bullying', 'incident_date' => '2026-07-05', 'school_year_id' => 1]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'discipline.view']);

        $result = (new GetDisciplineCaseStatsTool())->execute($user, []);

        $this->assertEquals(['resolved' => 1, 'under_review' => 1], $result['byStatus']);
    }

    public function test_individual_mode_returns_that_students_cases(): void
    {
        // students is a legacy MyISAM table -- RefreshDatabase can't roll it back between
        // tests (per the pattern in tests/Feature/Atlas/WorkspaceSyncTest.php), so use a
        // name unique to this test.
        $lastname = 'DisciplineLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId([
            'lastname' => $lastname, 'firstname' => 'Test',
        ]);

        DisciplineCase::create(['student_id' => $studentId, 'status' => 'resolved', 'threat_level' => 'low', 'nature_of_offense' => 'Tardiness', 'incident_date' => '2026-07-01', 'school_year_id' => 1]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'discipline.view']);

        $result = (new GetDisciplineCaseStatsTool())->execute($user, ['student_identifier' => $lastname]);

        $this->assertCount(1, $result['cases']);
        $this->assertEquals('Tardiness', $result['cases'][0]['nature_of_offense']);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetDisciplineCaseStatsToolTest"`
Expected: FAIL — class doesn't exist. (`App\Models\Student` declares no `$connection`
override, so `students` lives on the app's default connection — `DB::table('students')`
above needs no explicit connection name.)

- [ ] **Step 3: Write the tool**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Discipline\DisciplineCase;
use App\Models\Student;
use App\Models\User;

class GetDisciplineCaseStatsTool implements DynaTool
{
    public function name(): string { return 'get_discipline_case_stats'; }

    public function description(): string
    {
        return 'Returns discipline case data. Without student_identifier: aggregate counts by status and threat level. '
             . 'With student_identifier (name or ID): that specific student\'s case list. Requires discipline access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_identifier' => [
                    'type' => 'string',
                    'description' => 'Optional — a student name or ID to look up that specific student\'s discipline cases instead of aggregate counts.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('discipline.view')) {
            throw new \RuntimeException('This account does not have discipline access.');
        }

        if (! empty($input['student_identifier'])) {
            return $this->individualCases($input['student_identifier']);
        }

        return [
            'byStatus' => DisciplineCase::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->toArray(),
            'byThreatLevel' => DisciplineCase::query()->selectRaw('threat_level, count(*) as total')->groupBy('threat_level')->pluck('total', 'threat_level')->toArray(),
        ];
    }

    private function individualCases(string $identifier): array
    {
        $student = Student::where('lastname', 'like', "%{$identifier}%")
            ->orWhere('firstname', 'like', "%{$identifier}%")
            ->orWhere('pisaysystemID', $identifier)
            ->first();

        if (! $student) {
            return ['cases' => [], 'note' => "No student found matching \"{$identifier}\"."];
        }

        $cases = DisciplineCase::where('student_id', $student->id)->get([
            'case_no', 'status', 'threat_level', 'nature_of_offense', 'incident_date', 'resolution', 'sanction',
        ]);

        return [
            'student' => $student->full_name,
            'cases' => $cases->toArray(),
        ];
    }
}
```

Append to the `DynaToolRegistry` array.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetDisciplineCaseStatsToolTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetDisciplineCaseStatsTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/GetDisciplineCaseStatsToolTest.php
git commit -m "feat(dyna): add get_discipline_case_stats (aggregate + individual)"
```

---

### Task 6: `get_homeroom_attendance_summary` (aggregate + individual, `homeroom-attendance.admin` gated)

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetHomeroomAttendanceSummaryTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/GetHomeroomAttendanceSummaryToolTest.php`

**Interfaces:**
- Consumes: `App\Models\HomeroomAttendance\MonthlyReportLine` (`student_id`, `days_present`,
  `excused_absences`, `unexcused_absences`, `cutting_count`, `tardy_count`,
  `is_perfect_attendance`).
- Produces: `GetHomeroomAttendanceSummaryTool` (`get_homeroom_attendance_summary`) — gated by
  `atlas.dyna.access` **and** `homeroom-attendance.admin`. Same two-mode shape as Task 5's
  discipline tool.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\HomeroomAttendance\MonthlyReport;
use App\Models\HomeroomAttendance\MonthlyReportLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetHomeroomAttendanceSummaryTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetHomeroomAttendanceSummaryToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_mode_returns_campus_wide_averages(): void
    {
        // section_id is an unconstrained legacy-table FK (any int works); school_year_id
        // is a real constrained FK to school_years, per
        // database/migrations/2026_07_28_160500_create_homeroom_monthly_reports_table.php.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);
        $report = MonthlyReport::create(['section_id' => 1, 'school_year_id' => $schoolYear->id, 'month' => 7, 'year' => 2026]);
        MonthlyReportLine::create(['homeroom_monthly_report_id' => $report->id, 'student_id' => 1, 'cutting_count' => 2, 'is_perfect_attendance' => false, 'excused_absences' => 1, 'unexcused_absences' => 1]);
        MonthlyReportLine::create(['homeroom_monthly_report_id' => $report->id, 'student_id' => 2, 'cutting_count' => 0, 'is_perfect_attendance' => true, 'excused_absences' => 0, 'unexcused_absences' => 0]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'homeroom-attendance.admin']);

        $result = (new GetHomeroomAttendanceSummaryTool())->execute($user, []);

        $this->assertEquals(1, $result['perfect_attendance_count']);
        $this->assertEquals(1.0, $result['average_cutting_count']);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetHomeroomAttendanceSummaryToolTest"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write the tool**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\HomeroomAttendance\MonthlyReportLine;
use App\Models\Student;
use App\Models\User;

class GetHomeroomAttendanceSummaryTool implements DynaTool
{
    public function name(): string { return 'get_homeroom_attendance_summary'; }

    public function description(): string
    {
        return 'Returns homeroom attendance data. Without student_identifier: campus-wide averages (cutting incidents, '
             . 'perfect-attendance count, excused-vs-unexcused ratio). With student_identifier: that specific student\'s monthly record. '
             . 'Requires homeroom attendance admin access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_identifier' => [
                    'type' => 'string',
                    'description' => 'Optional — a student name or ID to look up that specific student\'s attendance record instead of the campus-wide summary.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('homeroom-attendance.admin')) {
            throw new \RuntimeException('This account does not have homeroom attendance admin access.');
        }

        if (! empty($input['student_identifier'])) {
            return $this->individualRecord($input['student_identifier']);
        }

        $lines = MonthlyReportLine::all();

        return [
            'perfect_attendance_count' => $lines->where('is_perfect_attendance', true)->count(),
            'average_cutting_count' => round($lines->avg('cutting_count'), 2),
            'total_excused_absences' => $lines->sum('excused_absences'),
            'total_unexcused_absences' => $lines->sum('unexcused_absences'),
        ];
    }

    private function individualRecord(string $identifier): array
    {
        $student = Student::where('lastname', 'like', "%{$identifier}%")
            ->orWhere('firstname', 'like', "%{$identifier}%")
            ->orWhere('pisaysystemID', $identifier)
            ->first();

        if (! $student) {
            return ['records' => [], 'note' => "No student found matching \"{$identifier}\"."];
        }

        $lines = MonthlyReportLine::where('student_id', $student->id)->get([
            'days_present', 'excused_absences', 'unexcused_absences', 'cutting_count', 'tardy_count', 'is_perfect_attendance',
        ]);

        return [
            'student' => $student->full_name,
            'records' => $lines->toArray(),
        ];
    }
}
```

Append to the `DynaToolRegistry` array.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetHomeroomAttendanceSummaryToolTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetHomeroomAttendanceSummaryTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/GetHomeroomAttendanceSummaryToolTest.php
git commit -m "feat(dyna): add get_homeroom_attendance_summary (aggregate + individual)"
```

---

### Task 7: `get_employee_info` (`hr.employees.manage` gated)

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetEmployeeInfoTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/GetEmployeeInfoToolTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (`position`, `division_id`, `office_id`, `status`,
  `salary_grade`, `salary_step`), `App\Models\HR\DtrRecord` (`user_id`, `work_date`,
  `attendance_status`), `App\Models\EmployeeIPCR` (`user_id`, `status`, `rating_period_id`,
  `final_numeric_rating`, `final_adjectival_rating`).
- Produces: `GetEmployeeInfoTool` (`get_employee_info`) — gated by `atlas.dyna.access` **and**
  `hr.employees.manage`, plus the same division lock non-Administrator/OCD users already get
  elsewhere (a Division Chief can only look up someone in their own division).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetEmployeeInfoTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEmployeeInfoToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_employee_profile_for_a_user_in_the_requesters_division(): void
    {
        $division = Division::factory()->create();
        $employee = User::factory()->create([
            'name' => 'Jane Employee', 'division_id' => $division->id, 'position' => 'Teacher III',
            'salary_grade' => 15, 'salary_step' => 3, 'status' => 'active',
        ]);

        $chief = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'hr.employees.manage'], ['module' => 'HR', 'description' => 'x'])
        );
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'atlas.dyna.access'], ['module' => 'Atlas', 'description' => 'x'])
        );

        $result = (new GetEmployeeInfoTool())->execute($chief, ['identifier' => 'Jane Employee']);

        $this->assertEquals('Jane Employee', $result['name']);
        $this->assertEquals('Teacher III', $result['position']);
        $this->assertEquals(15, $result['salary_grade']);
    }

    public function test_returns_not_found_for_an_employee_outside_the_requesters_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        User::factory()->create(['name' => 'Other Division Employee', 'division_id' => $divisionB->id]);

        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $divisionA->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'hr.employees.manage'], ['module' => 'HR', 'description' => 'x'])
        );

        $result = (new GetEmployeeInfoTool())->execute($chief, ['identifier' => 'Other Division Employee']);

        $this->assertArrayHasKey('note', $result);
        $this->assertEmpty($result['employee'] ?? null);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetEmployeeInfoToolTest"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write the tool**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\EmployeeIPCR;
use App\Models\HR\DtrRecord;
use App\Models\User;

class GetEmployeeInfoTool implements DynaTool
{
    public function name(): string { return 'get_employee_info'; }

    public function description(): string
    {
        return 'Returns one employee\'s profile: position, division/office, status, salary grade/step, '
             . 'latest DTR attendance status, and current-period IPCR status. Requires HR employee access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier'],
            'properties' => [
                'identifier' => ['type' => 'string', 'description' => 'Employee name or email.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('hr.employees.manage')) {
            throw new \RuntimeException('This account does not have HR employee access.');
        }

        $query = User::with(['division', 'office'])
            ->where(function ($q) use ($input) {
                $q->where('name', 'like', '%'.$input['identifier'].'%')
                    ->orWhere('email', $input['identifier']);
            });

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->where('division_id', $user->division_id);
        }

        $employee = $query->first();

        if (! $employee) {
            return ['note' => "No employee found matching \"{$input['identifier']}\" in your accessible scope."];
        }

        $latestDtr = DtrRecord::where('user_id', $employee->id)->orderByDesc('work_date')->first();
        $currentIpcr = EmployeeIPCR::where('user_id', $employee->id)->orderByDesc('rating_period_id')->first();

        return [
            'name' => $employee->name,
            'position' => $employee->position,
            'division' => $employee->division?->division_name,
            'status' => $employee->status,
            'salary_grade' => $employee->salary_grade,
            'salary_step' => $employee->salary_step,
            'latest_dtr_status' => $latestDtr?->attendance_status,
            'latest_dtr_date' => $latestDtr?->work_date,
            'current_ipcr_status' => $currentIpcr?->status,
            'current_ipcr_rating' => $currentIpcr?->final_adjectival_rating,
        ];
    }
}
```

Append to the `DynaToolRegistry` array.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetEmployeeInfoToolTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetEmployeeInfoTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/GetEmployeeInfoToolTest.php
git commit -m "feat(dyna): add get_employee_info"
```

---

### Task 8: `get_student_info` (`students.enrollment.view` gated)

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetStudentInfoTool.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/GetStudentInfoToolTest.php`

**Interfaces:**
- Consumes: `App\Models\Student` (`lastname`/`firstname`/`middlename`/`pisaysystemID`,
  `full_name` accessor), `App\Models\Registrar\StudentEnrollment` (`student_id`, `status`,
  `grade_level`, `section_id`), `App\Models\HomeroomAttendance\MonthlyReportLine` (via the
  aggregate helper written in Task 6 — reuse its query shape, don't duplicate), `App\Models\Borrowing`.
- Produces: `GetStudentInfoTool` (`get_student_info`) — gated by `atlas.dyna.access` **and**
  `students.enrollment.view`. Discipline history included only when the requester additionally
  has `discipline.view` (checked separately, does not block the rest of the response).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetStudentInfoTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetStudentInfoToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_and_attendance_summary_without_discipline_access(): void
    {
        // students is a legacy table on the app's default connection (App\Models\Student
        // declares no $connection override) -- RefreshDatabase can't roll it back reliably
        // between tests (MyISAM), so use a name unique to this test.
        $lastname = 'StudentInfoLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        StudentEnrollment::create(['student_id' => $studentId, 'status' => 'enrolled', 'grade_level' => 'Grade 9']);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $result = (new GetStudentInfoTool())->execute($user, ['identifier' => $lastname]);

        $this->assertEquals('enrolled', $result['enrollment_status']);
        $this->assertEquals('Grade 9', $result['grade_level']);
        $this->assertArrayNotHasKey('discipline_cases', $result);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetStudentInfoToolTest"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write the tool**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Discipline\DisciplineCase;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;

class GetStudentInfoTool implements DynaTool
{
    public function name(): string { return 'get_student_info'; }

    public function description(): string
    {
        return 'Returns one student\'s profile: enrollment status, grade/section, and attendance summary. '
             . 'Discipline history is included only if the requester also has discipline access. Requires student enrollment view access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier'],
            'properties' => [
                'identifier' => ['type' => 'string', 'description' => 'Student name or system ID.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('students.enrollment.view')) {
            throw new \RuntimeException('This account does not have student enrollment access.');
        }

        $student = Student::where('lastname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('firstname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('pisaysystemID', $input['identifier'])
            ->first();

        if (! $student) {
            return ['note' => "No student found matching \"{$input['identifier']}\"."];
        }

        $enrollment = StudentEnrollment::where('student_id', $student->id)->latest('id')->first();

        $result = [
            'name' => $student->full_name,
            'enrollment_status' => $enrollment?->status,
            'grade_level' => $enrollment?->grade_level,
        ];

        if ($user->hasPermission('discipline.view')) {
            $result['discipline_cases'] = DisciplineCase::where('student_id', $student->id)
                ->get(['case_no', 'status', 'threat_level', 'nature_of_offense'])
                ->toArray();
        }

        return $result;
    }
}
```

Append to the `DynaToolRegistry` array.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetStudentInfoToolTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetStudentInfoTool.php app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/GetStudentInfoToolTest.php
git commit -m "feat(dyna): add get_student_info"
```

---

### Task 9: `POST /api/dyna/login/google` — Google Sign-In backend endpoint

**Files:**
- Create: `app/Services/Atlas/Dyna/DynaGoogleClientFactory.php`
- Modify: `app/Http/Controllers/Api/DynaAuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Atlas/Dyna/DynaGoogleLoginTest.php`

**Real pattern confirmed (`app/Http/Controllers/StudentAttendance/Api/GoogleAuthController.php:143-153`):**
`verifyIdToken()` there does `new \Google\Client(['client_id' => config('services.google.mobile_client_id')])`
then `$client->verifyIdToken($idToken)` wrapped in try/catch, returning the payload array (with
keys including `email`, `email_verified`, `name`) or `null`. **This is constructed inline with
`new`, not resolved from the container** — a straight port of that pattern would be untestable
without hitting Google's servers or using PHP's `overload:` Mockery hack. Instead, mirror this
plan's own `DynaBedrockClientFactory` pattern (Phase 1, Task 7): wrap client construction in a
small factory class, inject it, mock the factory (not `Google\Client` itself) in tests.

Unlike the mobile flow, this endpoint does **not** enforce the `@crc.pshs.edu.ph` domain / `email_verified`
check — that check exists there because the mobile flow is a public self-registration surface;
Dyna's Google Sign-In only ever matches against an *existing* `atlas.dyna.access` account, which
is already a much narrower gate.

**Interfaces:**
- Produces: `DynaGoogleClientFactory::make(): \Google\Client` (reads
  `config('services.google.mobile_client_id')` — the same, already-configured client ID the
  mobile app uses, since Dyna's new OAuth client entry shares that same server client ID per
  the design spec). `POST /api/dyna/login/google` accepting `{id_token, device_name}`, returns
  the same `{token, user}` shape as the password login. **No auto-creation** — an unmatched
  email returns 404. The macOS plan's Google Sign-In task depends on this endpoint existing
  with this exact contract.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaGoogleClientFactory;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynaGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_in_an_existing_user_with_dyna_access_via_verified_google_token(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $this->mockGoogleClient(['email' => $user->email]);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'fake-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['name', 'email']]);
    }

    public function test_returns_404_when_no_atlas_account_matches_the_google_email(): void
    {
        $this->mockGoogleClient(['email' => 'nobody@example.com']);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'fake-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(404);
    }

    public function test_returns_403_when_the_matched_user_lacks_dyna_access(): void
    {
        $user = User::factory()->create();
        $this->mockGoogleClient(['email' => $user->email]);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'fake-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(403);
    }

    public function test_returns_401_when_the_token_fails_verification(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')->once()->andReturn(false);
        $factory = Mockery::mock(DynaGoogleClientFactory::class);
        $factory->shouldReceive('make')->andReturn($googleClient);
        $this->app->instance(DynaGoogleClientFactory::class, $factory);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'bad-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(401);
    }

    private function mockGoogleClient(array $payload): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')->once()->andReturn($payload);
        $factory = Mockery::mock(DynaGoogleClientFactory::class);
        $factory->shouldReceive('make')->andReturn($googleClient);
        $this->app->instance(DynaGoogleClientFactory::class, $factory);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaGoogleLoginTest"`
Expected: FAIL — `DynaGoogleClientFactory` doesn't exist yet.

- [ ] **Step 3: Write the factory and `loginWithGoogle`**

```php
<?php

namespace App\Services\Atlas\Dyna;

use Google\Client;

class DynaGoogleClientFactory
{
    public function make(): Client
    {
        return new Client(['client_id' => config('services.google.mobile_client_id')]);
    }
}
```

Add to `DynaAuthController`:

```php
    public function __construct(private readonly \App\Services\Atlas\Dyna\DynaGoogleClientFactory $googleClientFactory) {}

    public function loginWithGoogle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $payload = $this->googleClientFactory->make()->verifyIdToken($validated['id_token']);
        } catch (\Throwable) {
            $payload = false;
        }

        if (! $payload || empty($payload['email'])) {
            return response()->json(['message' => 'Google sign-in could not be verified.'], 401);
        }

        $user = User::where('email', $payload['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'No Atlas Account found for this Google account.'], 404);
        }

        if (! $user->hasPermission('atlas.dyna.access')) {
            return response()->json(['message' => 'This account does not have Dyna access.'], 403);
        }

        $user->tokens()->where('name', $validated['device_name'])->delete();
        $token = $user->createToken($validated['device_name'], ['dyna'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }
```

`DynaAuthController::login()` (the password flow, Phase 1) has no constructor today — adding
one here means checking whether that method still works unchanged (it doesn't touch
`$this->googleClientFactory`, so it's unaffected, but confirm the class doesn't already have a
constructor to merge into rather than clobber).

Add to `routes/api.php`, inside the existing `dyna` prefix group next to the password
`/login` route:

```php
Route::post('/login/google', [DynaAuthController::class, 'loginWithGoogle'])->name('login.google')->middleware('throttle:10,1');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaGoogleLoginTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/DynaGoogleClientFactory.php app/Http/Controllers/Api/DynaAuthController.php routes/api.php tests/Feature/Atlas/Dyna/DynaGoogleLoginTest.php
git commit -m "feat(dyna): add POST /api/dyna/login/google"
```

---

### Task 10: Full suite verification + lint

**Files:** none (verification-only task).

- [ ] **Step 1: Run the full Dyna test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=Dyna"`
Expected: PASS — every test from this plan plus all of Phase 1's, no interaction failures.

- [ ] **Step 2: PHP syntax-check every modified/created file**

Run the project's `lint` skill (`php -l` sweep) over every file touched in this plan.

- [ ] **Step 3: Confirm every new tool is actually registered**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"echo count(app(App\\\\Services\\\\Atlas\\\\Dyna\\\\DynaToolRegistry::class)->toBedrockToolConfig()['tools']);\""`
Expected: `22` (2 from Phase 1 + 20 from this plan). If it's lower, a tool was written but never
appended to the `AppServiceProvider` array — go back and fix it.

- [ ] **Step 4: Commit (if Steps 1-3 required fixes)**

```bash
git add -u
git commit -m "fix(dyna): address issues found in expansion full-suite verification"
```
