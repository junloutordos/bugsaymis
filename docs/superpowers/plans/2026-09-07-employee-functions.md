# Employee Functions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give HR/Admin a way to assign Core and Support Functions to any employee (tagged to a Work Distribution Plan, or auto-synced from Faculty Loading for teaching faculty) — a general, durable Employee-record capability that IPCR V2 will consume as its first downstream feature.

**Architecture:** One new table (`employee_functions`) + model, a sync service that reads current-term `LoadAssignment`s for faculty, a controller/routes under the existing Users/Employees area, and a new tab on the existing `Users/Index.vue` page. No changes to any existing table, model, or route.

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, PHPUnit + RefreshDatabase.

**Spec:** `docs/superpowers/specs/2026-09-07-ipcr-v2-design.md` (Data model → `employee_functions` section)

## Global Constraints

- Faculty vs. Non-Teaching detection uses the **exact same check the live v1 IPCR module already uses**: `$user->hasRole('Faculty') || (bool) $user->academic_unit_id` (see `app/Http/Controllers/EmployeeIPCRController.php:68`). Do **not** use `emp_category` for this — it exists on `User` but is not what v1 uses to distinguish Faculty from Non-Teaching, and using a different signal than v1 would silently diverge from the "reuse v1's mechanism" mandate in the spec.
- Never touch `employee_ipcrs`, `employee_ipcrs_plan`, `WorkDistributionPlanClassifier`, or the Designations-module WDP tagging tables (`designation_work_distribution_plan`, `designation_category_work_distribution_plan`) — Employee Functions is a fully separate, parallel mechanism (spec §Non-goals).
- `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan ..."` is how every artisan/test command in this plan runs — the app lives in a Docker container, not on the host.
- Never use `Auth::user()->role_id` — use `hasRole()`/`hasPermission()`.
- Stage files by name when committing (`git add <path>`), never `git add -A`/`.`.

---

### Task 1: `employee_functions` migration

**Files:**
- Create: `database/migrations/2026_09_07_090000_create_employee_functions_table.php`
- Test: `tests/Feature/EmployeeFunctions/EmployeeFunctionsMigrationTest.php`

**Interfaces:**
- Produces: `employee_functions` table with columns `id, user_id, function_type, source_type, load_assignment_id, work_distribution_plan_id, label, weight_percent, academic_term_id, created_by, timestamps`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmployeeFunctions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeFunctionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_functions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('employee_functions'));
        $this->assertTrue(Schema::hasColumns('employee_functions', [
            'id', 'user_id', 'function_type', 'source_type',
            'load_assignment_id', 'work_distribution_plan_id',
            'label', 'weight_percent', 'academic_term_id', 'created_by',
            'created_at', 'updated_at',
        ]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionsMigrationTest.php"`
Expected: FAIL — table `employee_functions` does not exist (`Base table or view not found`).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('function_type', ['core', 'support']);
            $table->enum('source_type', ['load_assignment', 'wdp', 'manual']);
            $table->foreignId('load_assignment_id')->nullable()
                ->constrained('load_assignments')->nullOnDelete();
            $table->foreignId('work_distribution_plan_id')->nullable()
                ->constrained('work_distribution_plans')->nullOnDelete();
            $table->string('label', 255);
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->foreignId('academic_term_id')->nullable()
                ->constrained('academic_terms')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'function_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_functions');
    }
};
```

- [ ] **Step 4: Run migration in dev**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_07_090000_create_employee_functions_table.php"`
Expected: `Migrated: 2026_09_07_090000_create_employee_functions_table`

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionsMigrationTest.php"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_07_090000_create_employee_functions_table.php tests/Feature/EmployeeFunctions/EmployeeFunctionsMigrationTest.php
git commit -m "feat(employee-functions): add employee_functions table"
```

---

### Task 2: `EmployeeFunction` model

**Files:**
- Create: `app/Models/EmployeeFunction.php`
- Test: `tests/Feature/EmployeeFunctions/EmployeeFunctionModelTest.php`

**Interfaces:**
- Consumes: `employee_functions` table (Task 1).
- Produces: `App\Models\EmployeeFunction` with `user()`, `loadAssignment()`, `workDistributionPlan()`, `academicTerm()`, `createdBy()` relations, `scopeCore($q)`, `scopeSupport($q)`, `scopeAutoSynced($q)` (source_type = load_assignment), constants `TYPE_CORE = 'core'`, `TYPE_SUPPORT = 'support'`, `SOURCE_LOAD_ASSIGNMENT = 'load_assignment'`, `SOURCE_WDP = 'wdp'`, `SOURCE_MANUAL = 'manual'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $function = EmployeeFunction::create([
            'user_id' => $user->id,
            'function_type' => EmployeeFunction::TYPE_CORE,
            'source_type' => EmployeeFunction::SOURCE_MANUAL,
            'label' => 'Test Core Function',
        ]);

        $this->assertTrue($function->user->is($user));
    }

    public function test_scope_core_and_support_filter_correctly(): void
    {
        $user = User::factory()->create();
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Core A']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Support A']);

        $this->assertSame(1, EmployeeFunction::core()->count());
        $this->assertSame(1, EmployeeFunction::support()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionModelTest.php"`
Expected: FAIL — class `App\Models\EmployeeFunction` not found.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use Illuminate\Database\Eloquent\Model;

class EmployeeFunction extends Model
{
    protected $table = 'employee_functions';

    public const TYPE_CORE = 'core';
    public const TYPE_SUPPORT = 'support';

    public const SOURCE_LOAD_ASSIGNMENT = 'load_assignment';
    public const SOURCE_WDP = 'wdp';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'user_id', 'function_type', 'source_type',
        'load_assignment_id', 'work_distribution_plan_id',
        'label', 'weight_percent', 'academic_term_id', 'created_by',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loadAssignment()
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function workDistributionPlan()
    {
        return $this->belongsTo(WorkDistributionPlan::class);
    }

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCore($query)
    {
        return $query->where('function_type', self::TYPE_CORE);
    }

    public function scopeSupport($query)
    {
        return $query->where('function_type', self::TYPE_SUPPORT);
    }

    public function scopeAutoSynced($query)
    {
        return $query->where('source_type', self::SOURCE_LOAD_ASSIGNMENT);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionModelTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/EmployeeFunction.php tests/Feature/EmployeeFunctions/EmployeeFunctionModelTest.php
git commit -m "feat(employee-functions): add EmployeeFunction model"
```

---

### Task 3: `employee_functions.manage` permission seeder

**Files:**
- Create: `database/seeders/EmployeeFunctionsPermissionSeeder.php`
- Test: `tests/Feature/EmployeeFunctions/EmployeeFunctionsPermissionSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\Permission`, `App\Models\Role` (existing).
- Produces: permission `employee_functions.manage`, granted to role `HR` (matching `OpcrPermissionSeeder`'s convention of relying on the automatic `isSuperAdmin()` bypass for Administrator rather than granting it explicitly).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\EmployeeFunctionsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionsPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permission_and_grants_it_to_hr_role(): void
    {
        $hr = Role::create(['name' => 'HR']);

        (new EmployeeFunctionsPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'employee_functions.manage']);
        $permission = Permission::where('name', 'employee_functions.manage')->first();
        $this->assertTrue($hr->fresh()->permissions->contains($permission));
    }

    public function test_seeder_is_idempotent(): void
    {
        Role::create(['name' => 'HR']);

        (new EmployeeFunctionsPermissionSeeder())->run();
        (new EmployeeFunctionsPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'employee_functions.manage')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionsPermissionSeederTest.php"`
Expected: FAIL — class `Database\Seeders\EmployeeFunctionsPermissionSeeder` not found.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Employee Functions permission.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\EmployeeFunctionsPermissionSeeder --force
 */
class EmployeeFunctionsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'employee_functions.manage' => 'Assign Core/Support Functions and WDP tags to employees',
    ];

    private const MANAGE_ROLES = ['HR'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'Employee Functions',
                'description' => $description,
            ]);
        }

        $ids = Permission::whereIn('name', array_keys(self::PERMISSIONS))->pluck('id')->all();
        foreach (self::MANAGE_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionsPermissionSeederTest.php"`
Expected: PASS

- [ ] **Step 5: Run the seeder in dev**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=Database\\\\Seeders\\\\EmployeeFunctionsPermissionSeeder"`
Expected: seeder runs without error; `employee_functions.manage` row exists in `permissions` table.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/EmployeeFunctionsPermissionSeeder.php tests/Feature/EmployeeFunctions/EmployeeFunctionsPermissionSeederTest.php
git commit -m "feat(employee-functions): seed employee_functions.manage permission"
```

---

### Task 4: `EmployeeFunctionSyncService::syncFromFacultyLoading()`

**Files:**
- Create: `app/Services/EmployeeFunctionSyncService.php`
- Test: `tests/Feature/EmployeeFunctions/EmployeeFunctionSyncServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\FacultyLoading\LoadAssignment` (existing — `user_id`, `academic_term_id`, `subject_id`, `load_units`, `assignment_type`, `designation_id`, `description`), `App\Models\EmployeeFunction` (Task 2).
- Produces: `EmployeeFunctionSyncService::syncFromFacultyLoading(User $user): void` — creates/updates one `EmployeeFunction` row per distinct subject (teaching) and per designation-with-load (admin/research/committee), all `function_type = core`, `weight_percent` auto-computed; detaches stale auto-synced rows for the user's current term that no longer match a live `LoadAssignment`.

The grouping unit is `subject_id` (not raw `LoadAssignment` row) — this mirrors the
already-proven-correct pattern in v1's `FacultyIPCRBaselineService` (see
`project_ipcr_module` memory: "Grouping unit is `subject_id`... multiple SECTIONS of
the same subject merge into one row"). Reuse that grouping decision rather than
re-deriving it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\EmployeeFunctionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id,
            'name' => 'Full Term',
            'term_type' => 'full_term',
            'is_current' => true,
        ]);
    }

    private function subject(AcademicTerm $term, string $code, string $name, float $units): Subject
    {
        return Subject::create([
            'code' => $code, 'name' => $name, 'load_units' => $units,
            'grade_level' => 7, 'school_year_id' => $term->school_year_id, 'is_active' => true,
        ]);
    }

    private function facultyLoad(User $user, AcademicTerm $term): FacultyLoad
    {
        return FacultyLoad::create([
            'user_id' => $user->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
        ]);
    }

    public function test_syncs_one_core_function_row_per_distinct_subject_with_weight_by_units(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subjectA = $this->subject($term, 'CHEM1', 'Chemistry 1', 4);
        $subjectB = $this->subject($term, 'CHEM2', 'Chemistry 2', 2);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectA->id, 'load_units' => 4,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectB->id, 'load_units' => 2,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $rows = EmployeeFunction::where('user_id', $teacher->id)->core()->autoSynced()->get();
        $this->assertCount(2, $rows);

        $chemA = $rows->firstWhere('label', 'Chemistry 1');
        $this->assertNotNull($chemA);
        $this->assertEqualsWithDelta(66.67, (float) $chemA->weight_percent, 0.01); // 4 / 6 * 100
    }

    public function test_re_sync_detaches_a_row_no_longer_backed_by_a_current_load_assignment(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'PHYS1', 'Physics 1', 4);

        $assignment = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);
        $this->assertCount(1, EmployeeFunction::where('user_id', $teacher->id)->get());

        $assignment->delete();
        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $this->assertCount(0, EmployeeFunction::where('user_id', $teacher->id)->get());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionSyncServiceTest.php"`
Expected: FAIL — class `App\Services\EmployeeFunctionSyncService` not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeFunctionSyncService
{
    /**
     * Sync this faculty member's Core Function rows from their current-term
     * Load Assignments. Grouping unit is subject_id (teaching) or
     * designation_id (admin/research/committee-with-load), mirroring the
     * subject-level grouping already proven correct in v1's
     * FacultyIPCRBaselineService.
     */
    public function syncFromFacultyLoading(User $user): void
    {
        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            return;
        }

        DB::transaction(function () use ($user, $term) {
            $assignments = LoadAssignment::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->get();

            $totalUnits = (float) $assignments->sum('load_units');
            $groups = $this->groupAssignments($assignments);

            $keptIds = [];

            foreach ($groups as $group) {
                $weight = $totalUnits > 0 ? round(($group['units'] / $totalUnits) * 100, 2) : 0;

                $row = EmployeeFunction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'academic_term_id' => $term->id,
                        'source_type' => EmployeeFunction::SOURCE_LOAD_ASSIGNMENT,
                        'load_assignment_id' => $group['representative_assignment_id'],
                    ],
                    [
                        'function_type' => EmployeeFunction::TYPE_CORE,
                        'label' => $group['label'],
                        'weight_percent' => $weight,
                        'created_by' => $user->id,
                    ]
                );

                $keptIds[] = $row->id;
            }

            // Detach (hard-delete — no ipcr_v2 data exists yet to protect;
            // see EmployeeFunctionsIpcrV2Guard task in the IPCR V2 plan for
            // the follow-up guard once ipcr_v2_core_items exists) any prior
            // auto-synced row for this user/term no longer represented.
            EmployeeFunction::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->autoSynced()
                ->whereNotIn('id', $keptIds)
                ->delete();
        });
    }

    /**
     * @return array<int, array{label: string, units: float, representative_assignment_id: int}>
     *         keyed by a stable group key (subject_id or designation_id) so
     *         re-syncing finds the same group across runs.
     */
    private function groupAssignments($assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            if ($assignment->assignment_type === 'teaching' && $assignment->subject_id) {
                $key = 'subject_' . $assignment->subject_id;
                $groups[$key]['label'] ??= $assignment->subject?->name ?? 'Teaching Load';
                $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
                $groups[$key]['representative_assignment_id'] ??= $assignment->id;

                continue;
            }

            if ($assignment->designation_id && (float) $assignment->load_units > 0) {
                $key = 'designation_' . $assignment->designation_id;
                $groups[$key]['label'] ??= $assignment->description ?? ($assignment->designation?->name ?? 'Designation with Load');
                $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
                $groups[$key]['representative_assignment_id'] ??= $assignment->id;
            }
        }

        return $groups;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionSyncServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/EmployeeFunctionSyncService.php tests/Feature/EmployeeFunctions/EmployeeFunctionSyncServiceTest.php
git commit -m "feat(employee-functions): sync Core Function rows from Faculty Loading"
```

---

### Task 5: `EmployeeFunctionController` + routes

**Files:**
- Create: `app/Http/Controllers/EmployeeFunctionController.php`
- Modify: `routes/web.php` (add a new route group near the existing `users.*` block, `routes/web.php:1210-1220`)
- Test: `tests/Feature/EmployeeFunctions/EmployeeFunctionControllerTest.php`

**Interfaces:**
- Consumes: `EmployeeFunction` (Task 2), `EmployeeFunctionSyncService` (Task 4).
- Produces: routes `employee-functions.index` (GET `/users/{user}/functions`), `employee-functions.store` (POST `/users/{user}/functions`), `employee-functions.update` (PUT `/users/{user}/functions/{employeeFunction}`), `employee-functions.destroy` (DELETE `/users/{user}/functions/{employeeFunction}`), `employee-functions.sync` (POST `/users/{user}/functions/sync`) — all gated `permission:employee_functions.manage`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'HR']);
        $permission = Permission::firstOrCreate(['name' => 'employee_functions.manage'], ['module' => 'Employee Functions', 'description' => 'x']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_creates_a_manual_support_function_tagged_to_a_wdp(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $outcome = \App\Models\AgencyOutcome::create(['outcome' => 'Core Functions']);
        $indicator = \App\Models\PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);
        $plan = WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => 'x',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);

        $response = $this->actingAs($manager)->post(route('employee-functions.store', $employee), [
            'function_type' => 'support',
            'work_distribution_plan_id' => $plan->id,
            'label' => 'Member, Discipline Committee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $employee->id,
            'function_type' => 'support',
            'source_type' => 'wdp',
            'work_distribution_plan_id' => $plan->id,
            'label' => 'Member, Discipline Committee',
        ]);
    }

    public function test_destroy_removes_a_function_row(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $function = EmployeeFunction::create([
            'user_id' => $employee->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'x',
        ]);

        $response = $this->actingAs($manager)->delete(route('employee-functions.destroy', [$employee, $function]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('employee_functions', ['id' => $function->id]);
    }

    public function test_non_manager_cannot_access(): void
    {
        $employee = User::factory()->create();
        $someUser = User::factory()->create();

        $this->actingAs($someUser)->get(route('employee-functions.index', $employee))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionControllerTest.php"`
Expected: FAIL — route `employee-functions.store` not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\EmployeeFunction;
use App\Models\User;
use App\Services\EmployeeFunctionSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeFunctionController extends Controller
{
    public function index(User $user)
    {
        return Inertia::render('Users/EmployeeFunctions', [
            'employee' => $user->only('id', 'name', 'position'),
            'functions' => $user->employeeFunctions()->with('workDistributionPlan')->get(),
            'isFaculty' => $user->hasRole('Faculty') || (bool) $user->academic_unit_id,
        ]);
    }

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'function_type' => 'required|in:core,support',
            'work_distribution_plan_id' => 'nullable|exists:work_distribution_plans,id',
            'label' => 'required|string|max:255',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        EmployeeFunction::create([
            'user_id' => $user->id,
            'function_type' => $data['function_type'],
            'source_type' => $data['work_distribution_plan_id'] ? EmployeeFunction::SOURCE_WDP : EmployeeFunction::SOURCE_MANUAL,
            'work_distribution_plan_id' => $data['work_distribution_plan_id'] ?? null,
            'label' => $data['label'],
            'weight_percent' => $data['weight_percent'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Function added.');
    }

    public function update(Request $request, User $user, EmployeeFunction $employeeFunction)
    {
        abort_if($employeeFunction->user_id !== $user->id, 404);

        $data = $request->validate([
            'label' => 'required|string|max:255',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $employeeFunction->update($data);

        return back()->with('success', 'Function updated.');
    }

    public function destroy(User $user, EmployeeFunction $employeeFunction)
    {
        abort_if($employeeFunction->user_id !== $user->id, 404);

        $employeeFunction->delete();

        return back()->with('success', 'Function removed.');
    }

    public function sync(User $user, EmployeeFunctionSyncService $service)
    {
        abort_unless($user->hasRole('Faculty') || (bool) $user->academic_unit_id, 422, 'Sync from Faculty Loading only applies to faculty employees.');

        $service->syncFromFacultyLoading($user);

        return back()->with('success', 'Synced from Faculty Loading.');
    }
}
```

- [ ] **Step 4: Add the `employeeFunctions()` relation to `User`**

In `app/Models/User.php`, add near the other `hasMany` relations (e.g. next to `loadAssignments()`):

```php
    public function employeeFunctions()
    {
        return $this->hasMany(\App\Models\EmployeeFunction::class);
    }
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, immediately after the existing `users.*` group (after the line ending `Route::post('/users/{id}/activate', ...)` around line 1218), add:

```php
        Route::middleware('permission:employee_functions.manage')->group(function () {
            Route::get('/users/{user}/functions', [EmployeeFunctionController::class, 'index'])->name('employee-functions.index');
            Route::post('/users/{user}/functions', [EmployeeFunctionController::class, 'store'])->name('employee-functions.store');
            Route::put('/users/{user}/functions/{employeeFunction}', [EmployeeFunctionController::class, 'update'])->name('employee-functions.update');
            Route::delete('/users/{user}/functions/{employeeFunction}', [EmployeeFunctionController::class, 'destroy'])->name('employee-functions.destroy');
            Route::post('/users/{user}/functions/sync', [EmployeeFunctionController::class, 'sync'])->name('employee-functions.sync');
        });
```

Add the `use App\Http\Controllers\EmployeeFunctionController;` import at the top of `routes/web.php` alongside the other controller imports.

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/EmployeeFunctions/EmployeeFunctionControllerTest.php"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EmployeeFunctionController.php app/Models/User.php routes/web.php tests/Feature/EmployeeFunctions/EmployeeFunctionControllerTest.php
git commit -m "feat(employee-functions): add controller, routes, and User relation"
```

---

### Task 6: "Employee Functions" tab — Vue page

**Files:**
- Create: `resources/js/Pages/Users/EmployeeFunctions.vue`
- Modify: `resources/js/Pages/Users/Index.vue` (add a "Functions" icon-button action per row, linking to the new page — same pattern as the existing Edit/Delete `AppIconButton` row actions)

**Interfaces:**
- Consumes: props `employee { id, name, position }`, `functions: Array` (each `{ id, function_type, source_type, label, weight_percent, work_distribution_plan }`), `isFaculty: Boolean` — from `EmployeeFunctionController::index()` (Task 5).
- Uses routes: `employee-functions.store`, `employee-functions.update`, `employee-functions.destroy`, `employee-functions.sync` (Task 5).

- [ ] **Step 1: Write the Vue page**

```vue
<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import { ArrowPathIcon, PlusIcon, TrashIcon } from "@heroicons/vue/24/outline"
import { computed, ref } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { TH, TD, TR, TD_END } from "@/Composables/useTableClasses.js"

const props = defineProps({
  employee:  Object,
  functions: Array,
  isFaculty: Boolean,
})

const { isSubmitting, submit } = useSubmit()

const coreFunctions = computed(() => props.functions.filter(f => f.function_type === "core"))
const supportFunctions = computed(() => props.functions.filter(f => f.function_type === "support"))

function syncFromFacultyLoading() {
  submit((opts) => router.post(route("employee-functions.sync", props.employee.id), {}, opts))
}

function removeFunction(fn) {
  submit((opts) => router.delete(route("employee-functions.destroy", [props.employee.id, fn.id]), opts))
}

const showAddModal = ref(false)
const addForm = ref({ function_type: "core", label: "", weight_percent: null })

function openAddModal(type) {
  addForm.value = { function_type: type, label: "", weight_percent: null }
  showAddModal.value = true
}

function saveAddForm() {
  submit(
    (opts) => router.post(route("employee-functions.store", props.employee.id), addForm.value, opts),
    { onSuccess: () => { showAddModal.value = false } }
  )
}
</script>

<template>
  <Head :title="`${employee.name} — Functions`" />
  <AdminLayout :title="`${employee.name} — Employee Functions`">
    <AppPageHeader :title="employee.name" :subtitle="employee.position" />

    <AppCard class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-700">Core Functions</h3>
        <div class="flex gap-2">
          <AppButton v-if="isFaculty" variant="secondary" :disabled="isSubmitting" @click="syncFromFacultyLoading">
            <ArrowPathIcon class="w-4 h-4 mr-1" /> Sync from Faculty Loading
          </AppButton>
          <AppButton @click="openAddModal('core')"><PlusIcon class="w-4 h-4 mr-1" /> Add Core Function</AppButton>
        </div>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th :class="TH">Label</th>
            <th :class="TH">Source</th>
            <th :class="TH">Weight %</th>
            <th :class="TH"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fn in coreFunctions" :key="fn.id" :class="TR">
            <td :class="TD">{{ fn.label }}</td>
            <td :class="TD"><AppBadge>{{ fn.source_type }}</AppBadge></td>
            <td :class="TD">{{ fn.weight_percent ?? "—" }}</td>
            <td :class="TD_END">
              <AppIconButton label="Remove" variant="danger" @click="removeFunction(fn)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </td>
          </tr>
          <tr v-if="!coreFunctions.length">
            <td :class="TD" colspan="4">No Core Functions assigned yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <AppCard>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-700">Support Functions</h3>
        <AppButton @click="openAddModal('support')"><PlusIcon class="w-4 h-4 mr-1" /> Add Support Function</AppButton>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th :class="TH">Label</th>
            <th :class="TH">Source</th>
            <th :class="TH"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fn in supportFunctions" :key="fn.id" :class="TR">
            <td :class="TD">{{ fn.label }}</td>
            <td :class="TD"><AppBadge>{{ fn.source_type }}</AppBadge></td>
            <td :class="TD_END">
              <AppIconButton label="Remove" variant="danger" @click="removeFunction(fn)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </td>
          </tr>
          <tr v-if="!supportFunctions.length">
            <td :class="TD" colspan="3">No Support Functions assigned yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <AppModal :show="showAddModal" title="Add Function" @close="showAddModal = false">
      <div class="space-y-4">
        <AppInput v-model="addForm.label" label="Label" placeholder="e.g. Chairperson, Discipline Committee" />
        <AppInput v-if="addForm.function_type === 'core'" v-model="addForm.weight_percent" type="number" label="Weight %" />
        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
          <AppButton :disabled="isSubmitting" @click="saveAddForm">Save</AppButton>
        </div>
      </div>
    </AppModal>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Add a "Functions" row action on `Users/Index.vue`**

In `resources/js/Pages/Users/Index.vue`, find the existing row-actions cell (near the `EyeIcon`/`PencilSquareIcon`/`TrashIcon` `AppIconButton`s), add an `IdentificationIcon` button (already imported at the top of the file per the existing import list) linking to the new page:

```vue
<AppIconButton label="Employee Functions" @click="router.get(route('employee-functions.index', user.id))"><IdentificationIcon class="w-4 h-4" /></AppIconButton>
```

- [ ] **Step 3: Build and manually verify**

Run: `npm run build`
Then start the dev app (`docker compose -f /Users/junlou/bugsaymis-docker/docker-compose.yml up -d`, visit `http://localhost:8080/users`), log in as an HR user, click the new Functions icon on any employee row, add a Core and a Support function, and (for a Faculty employee) click "Sync from Faculty Loading" and confirm rows appear with computed weights.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Users/EmployeeFunctions.vue resources/js/Pages/Users/Index.vue
git commit -m "feat(employee-functions): add Employee Functions tab UI"
```

---

## Self-Review Notes

- **Spec coverage**: `employee_functions` table ✓ (Task 1-2), Faculty sync + weight auto-compute ✓ (Task 4), Non-Teaching manual WDP tagging ✓ (Task 5 `store` accepts `work_distribution_plan_id`), new Employee Functions tab ✓ (Task 6), permission ✓ (Task 3). Superseded-row preservation (never delete a row with real IPCR V2 accomplishment data) is explicitly deferred to the IPCR V2 plan, which adds the ipcr_v2-aware guard once `ipcr_v2_core_items` exists — noted inline in Task 4's step 3 comment so it isn't silently forgotten.
- **Type consistency**: `EmployeeFunction::TYPE_CORE`/`TYPE_SUPPORT`/`SOURCE_*` constants (Task 2) are used identically in the sync service (Task 4) and controller (Task 5).
