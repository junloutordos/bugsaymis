# ALP Clickable Dashboard Cards + Attendance Restyle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the "Active members" and "Unassigned Grades 7–10" cards on the ALP dashboard clickable, opening searchable/filterable/PDF-exportable rosters, and restyle ALP's attendance-taking UI to match Homeroom Attendance's toggle-pill/mark-all/legend pattern.

**Architecture:** New `AlpRosterService` centralizes the two list-building queries (active members across cycles, unassigned grade 7–10 students) so both the Inertia page controllers and the PDF controller share one source of truth. Two new Inertia pages render the lists; two new PDF routes reuse the existing `AlpPdfService`/mPDF/Blade pattern. The attendance restyle is a pure Vue template/script change inside the existing session-card markup — no backend changes.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, Tailwind CSS 3, mPDF 8, PHPUnit + `RefreshDatabase`.

## Global Constraints

- Never use `Storage::disk('public')`, `storage_path()` for mPDF tempDir (use `sys_get_temp_dir()`), or `FormData`/multipart uploads (N/A — no file uploads in this feature).
- All new routes stay inside the existing `permission:alp.view|alp.manage|alp.advise|alp.coordinate|alp.registrar-certify|alp.approve|alp.reports|alp.audit` group in `routes/alp.php` — no new permissions.
- Controllers stay thin; list-building logic lives in `AlpRosterService`, not duplicated inline.
- Match Philippine locale / existing Tailwind conventions (`rounded-lg border border-slate-200 ...` inputs, `AppButton`/`AppCard`/`AppTable`/`AppFilterBar` components) — follow `resources/js/Pages/CID/Competitions/Index.vue` as the reference for filter bar + local pagination.
- Stage files by name when committing (`git add <path>`), never `-A`/`.`.
- No new abstractions beyond what's specified below — do not extract a shared Vue component between Homeroom's `Daily.vue` and ALP's `Show.vue`.

---

### Task 1: `AlpRosterService::activeMembers()` + `alp.members.index` route + page controller

**Files:**
- Create: `app/Services/ALP/AlpRosterService.php`
- Modify: `app/Http/Controllers/ALP/AlpController.php` (add `membersIndex()` method, constructor param, import)
- Modify: `routes/alp.php` (add `alp.members.index` route)
- Test: `tests/Feature/ALP/AlpMembersListTest.php`

**Interfaces:**
- Produces: `AlpRosterService::activeMembers(int $schoolYearId, \App\Models\User $user): \Illuminate\Support\Collection` — each element is `['name' => ?string, 'grade_level' => ?int, 'section' => ?string, 'alp' => ?string]`, sorted by name.
- Produces: `AlpController::membersIndex(Request $request)` — renders `Inertia::render('CID/ALP/Members', ['members' => Collection, 'schoolYears' => Collection, 'selectedSchoolYearId' => int])`.
- Produces: route name `alp.members.index` → `GET /cid/alp/members`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/ALP/AlpMembersListTest.php`:

```php
<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpMembersListTest extends TestCase
{
    use RefreshDatabase;

    // AlpRosterService::activeMembers() scopes non-elevated users to cycles
    // they advise/coordinate — grant 'alp.manage' (an elevated permission per
    // AlpRosterService's own $elevated check) so this test can see active
    // members aggregated across BOTH cycles below, not just one adviser's.
    private function alpUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.manage'], ['module' => 'ALP', 'description' => 'alp.manage']);
        $role = Role::create(['name' => 'AlpManagerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function currentSchoolYear(): SchoolYear
    {
        return SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
    }

    private function makeStudent(string $lastname, string $firstname): int
    {
        return (int) DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => $firstname]);
    }

    private function enroll(int $studentId, SchoolYear $sy, ?int $sectionId, int $gradeLevel): StudentEnrollment
    {
        return StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $sectionId,
            'grade_level' => $gradeLevel, 'enrollment_type' => 'returning', 'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);
    }

    private function makeCycle(SchoolYear $sy, string $programName): AlpProgramCycle
    {
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => $programName, 'status' => 'active']);

        return AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
    }

    public function test_lists_active_members_across_all_cycles_with_name_grade_section_and_alp(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Newton', 'syid' => 2026,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);

        $cycleA = $this->makeCycle($sy, 'Reading Recovery Program');
        $cycleB = $this->makeCycle($sy, 'Numeracy Bridge Program');

        $studentA = $this->makeStudent('Cruz', 'Ana');
        $enrollmentA = $this->enroll($studentA, $sy, $section->id, 8);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycleA->id, 'school_year_id' => $sy->id, 'student_id' => $studentA,
            'student_enrollment_id' => $enrollmentA->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $studentB = $this->makeStudent('Reyes', 'Ben');
        $enrollmentB = $this->enroll($studentB, $sy, null, 9);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycleB->id, 'school_year_id' => $sy->id, 'student_id' => $studentB,
            'student_enrollment_id' => $enrollmentB->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        // Inactive membership must be excluded.
        $studentC = $this->makeStudent('Santos', 'Cara');
        $enrollmentC = $this->enroll($studentC, $sy, null, 7);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycleA->id, 'school_year_id' => $sy->id, 'student_id' => $studentC,
            'student_enrollment_id' => $enrollmentC->id, 'status' => 'inactive', 'joined_at' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.members.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('CID/ALP/Members')
            ->has('members', 2)
            ->where('members.0.name', 'Cruz, Ana')
            ->where('members.0.grade_level', 8)
            ->where('members.0.section', 'Newton')
            ->where('members.0.alp', 'Reading Recovery Program')
            ->where('members.1.name', 'Reyes, Ben')
            ->where('members.1.alp', 'Numeracy Bridge Program'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpMembersListTest.php"`
Expected: FAIL — route `alp.members.index` not defined / class not found.

- [ ] **Step 3: Create `AlpRosterService`**

Create `app/Services/ALP/AlpRosterService.php`:

```php
<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

class AlpRosterService
{
    public function activeMembers(int $schoolYearId, User $user): Collection
    {
        $elevated = $user->isSuperAdmin() || $user->hasAnyPermission(['alp.manage', 'alp.coordinate', 'alp.registrar-certify', 'alp.approve', 'alp.reports', 'alp.audit']);

        return AlpMembership::query()
            ->where('status', 'active')
            ->where('school_year_id', $schoolYearId)
            ->whereHas('cycle', fn ($q) => $q->when(! $elevated, fn ($scope) => $scope->where(
                fn ($s) => $s->where('adviser_id', $user->id)->orWhere('coordinator_id', $user->id)
            )))
            ->with(['student:id,firstname,lastname,middlename', 'enrollment.section:id,sectionname', 'cycle.program:id,name'])
            ->get()
            ->map(fn ($membership) => [
                'name' => $membership->student?->full_name,
                'grade_level' => $membership->enrollment?->grade_level,
                'section' => $membership->enrollment?->section?->sectionname,
                'alp' => $membership->cycle?->program?->name,
            ])
            ->sortBy('name')
            ->values();
    }

    public function unassignedGrades7To10(int $schoolYearId): Collection
    {
        $assignedStudentIds = AlpMembership::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'active')
            ->whereHas('enrollment', fn ($q) => $q->whereBetween('grade_level', [7, 10]))
            ->pluck('student_id');

        return StudentEnrollment::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->whereBetween('grade_level', [7, 10])
            ->whereNotIn('student_id', $assignedStudentIds)
            ->with(['student:id,firstname,lastname,middlename', 'section:id,sectionname'])
            ->get()
            ->map(fn ($enrollment) => [
                'name' => $enrollment->student?->full_name,
                'grade_level' => $enrollment->grade_level,
                'section' => $enrollment->section?->sectionname,
            ])
            ->sortBy('name')
            ->values();
    }
}
```

- [ ] **Step 4: Wire `AlpController::membersIndex()`**

In `app/Http/Controllers/ALP/AlpController.php`:

Add the import near the other `App\Services\ALP\*` imports (after `use App\Services\ALP\AlpProgramSyncService;`):

```php
use App\Services\ALP\AlpRosterService;
```

Add the constructor param (after `private AlpFileService $files,`):

```php
    public function __construct(
        private AlpAccessService $access,
        private AlpComplianceService $compliance,
        private AlpProgramSyncService $sync,
        private AlpWorkflowService $workflow,
        private AlpAmsIntegrationService $ams,
        private AlpFileService $files,
        private AlpRosterService $roster,
        private SnapshotService $snapshots,
    ) {}
```

Add the new method right after `index()` (before `public function sync(Request $request)`):

```php
    public function membersIndex(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;

        return Inertia::render('CID/ALP/Members', [
            'members' => $this->roster->activeMembers($schoolYearId, $request->user()),
            'schoolYears' => $schoolYears,
            'selectedSchoolYearId' => $schoolYearId,
        ]);
    }
```

- [ ] **Step 5: Add the route**

In `routes/alp.php`, add right after `Route::get('/', [AlpController::class, 'index'])->name('index');`:

```php
        Route::get('/members', [AlpController::class, 'membersIndex'])->name('members.index');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpMembersListTest.php"`
Expected: PASS (the test will fail on the missing `CID/ALP/Members` Inertia page component at the frontend build level only if strict rendering is checked — `assertInertia` does not require the Vue file to exist, only the backend props, so this passes before Task 5 creates the page).

- [ ] **Step 7: Commit**

```bash
git add app/Services/ALP/AlpRosterService.php app/Http/Controllers/ALP/AlpController.php routes/alp.php tests/Feature/ALP/AlpMembersListTest.php
git commit -m "feat(alp): add active members roster list endpoint"
```

---

### Task 2: `AlpRosterService::unassignedGrades7To10()` + `alp.unassigned.index` route + page controller

**Files:**
- Modify: `app/Http/Controllers/ALP/AlpController.php` (add `unassignedIndex()` method)
- Modify: `routes/alp.php` (add `alp.unassigned.index` route)
- Test: `tests/Feature/ALP/AlpUnassignedListTest.php`

**Interfaces:**
- Consumes: `AlpRosterService::unassignedGrades7To10(int $schoolYearId): Collection` (already implemented in Task 1).
- Produces: `AlpController::unassignedIndex(Request $request)` — renders `Inertia::render('CID/ALP/Unassigned', ['students' => Collection, 'schoolYears' => Collection, 'selectedSchoolYearId' => int])`.
- Produces: route name `alp.unassigned.index` → `GET /cid/alp/unassigned`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/ALP/AlpUnassignedListTest.php`:

```php
<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpUnassignedListTest extends TestCase
{
    use RefreshDatabase;

    private function alpUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.view'], ['module' => 'ALP', 'description' => 'alp.view']);
        $role = Role::create(['name' => 'AlpViewerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function currentSchoolYear(): SchoolYear
    {
        return SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
    }

    private function makeStudent(string $lastname, string $firstname): int
    {
        return (int) DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => $firstname]);
    }

    private function enroll(int $studentId, SchoolYear $sy, ?int $sectionId, int $gradeLevel): StudentEnrollment
    {
        return StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $sectionId,
            'grade_level' => $gradeLevel, 'enrollment_type' => 'returning', 'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);
    }

    public function test_lists_enrolled_grade_7_to_10_students_without_an_active_alp_membership(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'levelid' => 7, 'sectionname' => 'Curie', 'syid' => 2026,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);

        // Unassigned, in range — must appear.
        $unassigned = $this->makeStudent('Dela Cruz', 'Diego');
        $this->enroll($unassigned, $sy, $section->id, 7);

        // Assigned via an active membership with a grade 7-10 enrollment — must NOT appear.
        $assigned = $this->makeStudent('Enriquez', 'Elena');
        $assignedEnrollment = $this->enroll($assigned, $sy, null, 9);
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => 'Numeracy Bridge Program', 'status' => 'active']);
        $cycle = AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $assigned,
            'student_enrollment_id' => $assignedEnrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        // Enrolled but out of the grade 7-10 range — must NOT appear.
        $outOfRange = $this->makeStudent('Fajardo', 'Fina');
        $this->enroll($outOfRange, $sy, null, 6);

        $response = $this->actingAs($user)->get(route('alp.unassigned.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('CID/ALP/Unassigned')
            ->has('students', 1)
            ->where('students.0.name', 'Dela Cruz, Diego')
            ->where('students.0.grade_level', 7)
            ->where('students.0.section', 'Curie'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpUnassignedListTest.php"`
Expected: FAIL — route `alp.unassigned.index` not defined.

- [ ] **Step 3: Wire `AlpController::unassignedIndex()`**

In `app/Http/Controllers/ALP/AlpController.php`, add right after `membersIndex()`:

```php
    public function unassignedIndex(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;

        return Inertia::render('CID/ALP/Unassigned', [
            'students' => $this->roster->unassignedGrades7To10($schoolYearId),
            'schoolYears' => $schoolYears,
            'selectedSchoolYearId' => $schoolYearId,
        ]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/alp.php`, add right after the `alp.members.index` route added in Task 1:

```php
        Route::get('/unassigned', [AlpController::class, 'unassignedIndex'])->name('unassigned.index');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpUnassignedListTest.php"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ALP/AlpController.php routes/alp.php tests/Feature/ALP/AlpUnassignedListTest.php
git commit -m "feat(alp): add unassigned grades 7-10 roster list endpoint"
```

---

### Task 3: Active Members PDF export

**Files:**
- Modify: `app/Services/ALP/AlpPdfService.php` (add `membersList()`)
- Modify: `app/Http/Controllers/ALP/AlpPdfController.php` (add `membersList()`, constructor param, imports)
- Create: `resources/views/alp/members-list.blade.php`
- Modify: `routes/alp.php` (add `alp.members.pdf` route)
- Test: `tests/Feature/ALP/AlpMembersPdfTest.php`

**Interfaces:**
- Consumes: `AlpRosterService::activeMembers()` (Task 1).
- Produces: `AlpPdfService::membersList(array $members, string $schoolYearName): string` (raw PDF bytes).
- Produces: route name `alp.members.pdf` → `GET /cid/alp/members.pdf`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/ALP/AlpMembersPdfTest.php`:

```php
<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpMembersPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_a_pdf_of_active_members(): void
    {
        // 'alp.manage' is elevated per AlpRosterService::activeMembers()'s own
        // $elevated check, so this user sees the cycle's members regardless
        // of who its adviser/coordinator is — matches AlpMembersListTest.
        $permission = Permission::firstOrCreate(['name' => 'alp.manage'], ['module' => 'ALP', 'description' => 'alp.manage']);
        $role = Role::create(['name' => 'AlpManagerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $studentId = (int) DB::table('students')->insertGetId(['lastname' => 'Cruz', 'firstname' => 'Ana']);
        $enrollment = StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 8, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => 'Reading Recovery Program', 'status' => 'active']);
        $cycle = AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentId,
            'student_enrollment_id' => $enrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.members.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpMembersPdfTest.php"`
Expected: FAIL — route `alp.members.pdf` not defined.

- [ ] **Step 3: Add `AlpPdfService::membersList()`**

In `app/Services/ALP/AlpPdfService.php`, add right after the `document()` method:

```php
    public function membersList(array $members, string $schoolYearName): string
    {
        return $this->render(view('alp.members-list', compact('members', 'schoolYearName'))->render());
    }
```

- [ ] **Step 4: Create the Blade template**

Create `resources/views/alp/members-list.blade.php`:

```blade
<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9pt}h1{text-align:center;font-size:14pt;margin-bottom:3px}.meta{text-align:center;color:#475569;margin-bottom:18px}table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}th{background:#eef2ff}.footer{margin-top:20px;border-top:1px solid #111;font-size:7pt}
</style></head><body>
<h1>ALP ACTIVE MEMBERS</h1>
<div class="meta">S.Y. {{ $schoolYearName }} | {{ count($members) }} member(s) | Generated {{ now()->format('F j, Y') }}</div>
<table><thead><tr><th>#</th><th>Name</th><th>Grade</th><th>Section</th><th>ALP</th></tr></thead><tbody>
@foreach($members as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['name'] }}</td><td>{{ $row['grade_level'] }}</td><td>{{ $row['section'] }}</td><td>{{ $row['alp'] }}</td></tr>@endforeach
</tbody></table>
<div class="footer">Generated {{ now()->format('m/d/Y') }} by BugSayMis CRCMIS</div>
</body></html>
```

- [ ] **Step 5: Wire `AlpPdfController::membersList()`**

In `app/Http/Controllers/ALP/AlpPdfController.php`, add imports (after `use App\Models\ALP\AlpReport;`):

```php
use App\Models\ALP\AlpSession;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\ALP\AlpRosterService;
```

Add the constructor param (after `private AlpFileService $files,`):

```php
    public function __construct(private AlpAccessService $access, private AlpPdfService $pdf, private AlpFileService $files, private AlpWorkflowService $workflow, private AlpRosterService $roster) {}
```

Add the method right after `document()`:

```php
    public function membersList(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;
        $schoolYear = $schoolYears->firstWhere('id', $schoolYearId);
        abort_unless($schoolYear, 404);
        $members = $this->roster->activeMembers($schoolYearId, $request->user());

        return $this->response($this->pdf->membersList($members->all(), $schoolYear->name), 'ALP-Active-Members-'.$schoolYear->name.'.pdf');
    }
```

- [ ] **Step 6: Add the route**

In `routes/alp.php`, add right after the `alp.unassigned.index` route added in Task 2:

```php
        Route::get('/members.pdf', [AlpPdfController::class, 'membersList'])->name('members.pdf');
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpMembersPdfTest.php"`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/ALP/AlpPdfService.php app/Http/Controllers/ALP/AlpPdfController.php resources/views/alp/members-list.blade.php routes/alp.php tests/Feature/ALP/AlpMembersPdfTest.php
git commit -m "feat(alp): add active members PDF export"
```

---

### Task 4: Unassigned Grades 7–10 PDF export

**Files:**
- Modify: `app/Services/ALP/AlpPdfService.php` (add `unassignedList()`)
- Modify: `app/Http/Controllers/ALP/AlpPdfController.php` (add `unassignedList()`)
- Create: `resources/views/alp/unassigned-list.blade.php`
- Modify: `routes/alp.php` (add `alp.unassigned.pdf` route)
- Test: `tests/Feature/ALP/AlpUnassignedPdfTest.php`

**Interfaces:**
- Consumes: `AlpRosterService::unassignedGrades7To10()` (Task 1/2).
- Produces: `AlpPdfService::unassignedList(array $students, string $schoolYearName): string`.
- Produces: route name `alp.unassigned.pdf` → `GET /cid/alp/unassigned.pdf`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/ALP/AlpUnassignedPdfTest.php`:

```php
<?php

namespace Tests\Feature\ALP;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpUnassignedPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_a_pdf_of_unassigned_grade_7_to_10_students(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.view'], ['module' => 'ALP', 'description' => 'alp.view']);
        $role = Role::create(['name' => 'AlpViewerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $studentId = (int) DB::table('students')->insertGetId(['lastname' => 'Dela Cruz', 'firstname' => 'Diego']);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 7, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.unassigned.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpUnassignedPdfTest.php"`
Expected: FAIL — route `alp.unassigned.pdf` not defined.

- [ ] **Step 3: Add `AlpPdfService::unassignedList()`**

In `app/Services/ALP/AlpPdfService.php`, add right after `membersList()`:

```php
    public function unassignedList(array $students, string $schoolYearName): string
    {
        return $this->render(view('alp.unassigned-list', compact('students', 'schoolYearName'))->render());
    }
```

- [ ] **Step 4: Create the Blade template**

Create `resources/views/alp/unassigned-list.blade.php`:

```blade
<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9pt}h1{text-align:center;font-size:14pt;margin-bottom:3px}.meta{text-align:center;color:#475569;margin-bottom:18px}table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}th{background:#eef2ff}.footer{margin-top:20px;border-top:1px solid #111;font-size:7pt}
</style></head><body>
<h1>ALP UNASSIGNED — GRADES 7 TO 10</h1>
<div class="meta">S.Y. {{ $schoolYearName }} | {{ count($students) }} student(s) | Generated {{ now()->format('F j, Y') }}</div>
<table><thead><tr><th>#</th><th>Name</th><th>Grade</th><th>Section</th></tr></thead><tbody>
@foreach($students as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['name'] }}</td><td>{{ $row['grade_level'] }}</td><td>{{ $row['section'] }}</td></tr>@endforeach
</tbody></table>
<div class="footer">Generated {{ now()->format('m/d/Y') }} by BugSayMis CRCMIS</div>
</body></html>
```

- [ ] **Step 5: Wire `AlpPdfController::unassignedList()`**

In `app/Http/Controllers/ALP/AlpPdfController.php`, add right after `membersList()`:

```php
    public function unassignedList(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;
        $schoolYear = $schoolYears->firstWhere('id', $schoolYearId);
        abort_unless($schoolYear, 404);
        $students = $this->roster->unassignedGrades7To10($schoolYearId);

        return $this->response($this->pdf->unassignedList($students->all(), $schoolYear->name), 'ALP-Unassigned-7-10-'.$schoolYear->name.'.pdf');
    }
```

- [ ] **Step 6: Add the route**

In `routes/alp.php`, add right after the `alp.members.pdf` route added in Task 3:

```php
        Route::get('/unassigned.pdf', [AlpPdfController::class, 'unassignedList'])->name('unassigned.pdf');
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP/AlpUnassignedPdfTest.php"`
Expected: PASS

- [ ] **Step 8: Run the full ALP test suite to check for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/ALP tests/Unit/ALP"`
Expected: All PASS (Tasks 1–4 tests plus the pre-existing `AlpFileServiceTest`).

- [ ] **Step 9: Commit**

```bash
git add app/Services/ALP/AlpPdfService.php app/Http/Controllers/ALP/AlpPdfController.php resources/views/alp/unassigned-list.blade.php routes/alp.php tests/Feature/ALP/AlpUnassignedPdfTest.php
git commit -m "feat(alp): add unassigned grades 7-10 PDF export"
```

---

### Task 5: `CID/ALP/Members.vue` page

**Files:**
- Create: `resources/js/Pages/CID/ALP/Members.vue`

**Interfaces:**
- Consumes: props `members: Array<{name, grade_level, section, alp}>`, `schoolYears: Array<{id, name, is_current}>`, `selectedSchoolYearId: Number|String` (from Task 1's `AlpController::membersIndex`).
- Consumes: routes `alp.index`, `alp.members.index`, `alp.members.pdf` (Tasks 1 and 3).

- [ ] **Step 1: Create the page**

Create `resources/js/Pages/CID/ALP/Members.vue`:

```vue
<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { DocumentArrowDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  members: { type: Array, default: () => [] },
  schoolYears: { type: Array, default: () => [] },
  selectedSchoolYearId: [Number, String],
})

const selectYear = (schoolYearId) => router.get(route('alp.members.index'), { school_year_id: schoolYearId }, { preserveState: true })

const search = ref('')
const gradeFilter = ref('')
const sectionFilter = ref('')

const gradeOptions = computed(() => [...new Set(props.members.map(m => m.grade_level).filter(Boolean))].sort((a, b) => a - b))
const sectionOptions = computed(() => [...new Set(props.members.map(m => m.section).filter(Boolean))].sort())

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.members.filter(m => {
    if (gradeFilter.value && String(m.grade_level) !== String(gradeFilter.value)) return false
    if (sectionFilter.value && m.section !== sectionFilter.value) return false
    if (!q) return true
    return `${m.name ?? ''} ${m.alp ?? ''}`.toLowerCase().includes(q)
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

const TH = 'px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
const TD = 'px-4 py-3 text-sm text-slate-600'
</script>

<template>
  <Head title="ALP Active Members" />
  <AdminLayout title="ALP Active Members">
    <div class="space-y-5">
      <AppPageHeader
        title="Active Members"
        subtitle="All active ALP members across every program cycle."
        :breadcrumb="[{ label: 'Alternative Learning Program', href: route('alp.index') }, { label: 'Active Members' }]"
      >
        <template #actions>
          <AppSelect :model-value="selectedSchoolYearId" :show-blank="false" aria-label="School year" class="min-w-40" @update:model-value="selectYear">
            <option v-for="year in schoolYears" :key="year.id" :value="year.id">{{ year.name }}</option>
          </AppSelect>
          <AppButton as="a" :href="route('alp.members.pdf', { school_year_id: selectedSchoolYearId })" target="_blank" variant="secondary">
            <DocumentArrowDownIcon class="h-4 w-4" />
            Download PDF
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search name or ALP..."
            class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-64" />
        </div>
        <select v-model="gradeFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Grades</option>
          <option v-for="g in gradeOptions" :key="g" :value="g">Grade {{ g }}</option>
        </select>
        <select v-model="sectionFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Sections</option>
          <option v-for="s in sectionOptions" :key="s" :value="s">{{ s }}</option>
        </select>
      </AppFilterBar>

      <AppCard :padded="false">
        <AppTable :is-empty="displayed.length === 0" :skeleton-cols="4" :card="false">
          <template #head>
            <tr>
              <th :class="TH">Name</th>
              <th :class="TH">Grade</th>
              <th :class="TH">Section</th>
              <th :class="TH">ALP</th>
            </tr>
          </template>

          <tr v-for="(m, idx) in displayed" :key="idx" class="hover:bg-indigo-50/40">
            <td :class="TD" class="font-medium text-slate-800">{{ m.name }}</td>
            <td :class="TD">{{ m.grade_level }}</td>
            <td :class="TD">{{ m.section || '—' }}</td>
            <td :class="TD">{{ m.alp }}</td>
          </tr>

          <template #empty>
            <EmptyState title="No active members found" />
          </template>
        </AppTable>

        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
          <p class="text-xs text-slate-500">{{ filtered.length }} member(s) — page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-1">
            <AppButton variant="secondary" size="sm" :disabled="currentPage === 1" @click="currentPage--">Prev</AppButton>
            <AppButton variant="secondary" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">Next</AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Build assets and manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or use the dev server if already running)

Then in a browser: visit `/cid/alp`, click the "Active members" card once Task 7 wires it up (temporarily, you can also visit `/cid/alp/members` directly by URL to verify this page in isolation before Task 7 lands) — confirm the table renders, search/grade/section filters narrow results, and "Download PDF" opens a PDF in a new tab.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/CID/ALP/Members.vue
git commit -m "feat(alp): add Active Members roster page"
```

---

### Task 6: `CID/ALP/Unassigned.vue` page

**Files:**
- Create: `resources/js/Pages/CID/ALP/Unassigned.vue`

**Interfaces:**
- Consumes: props `students: Array<{name, grade_level, section}>`, `schoolYears`, `selectedSchoolYearId` (from Task 2's `AlpController::unassignedIndex`).
- Consumes: routes `alp.index`, `alp.unassigned.index`, `alp.unassigned.pdf` (Tasks 2 and 4).

- [ ] **Step 1: Create the page**

Create `resources/js/Pages/CID/ALP/Unassigned.vue` — same structure as `Members.vue` from Task 5, minus the ALP column and its filter-search field:

```vue
<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { DocumentArrowDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  students: { type: Array, default: () => [] },
  schoolYears: { type: Array, default: () => [] },
  selectedSchoolYearId: [Number, String],
})

const selectYear = (schoolYearId) => router.get(route('alp.unassigned.index'), { school_year_id: schoolYearId }, { preserveState: true })

const search = ref('')
const gradeFilter = ref('')
const sectionFilter = ref('')

const gradeOptions = computed(() => [...new Set(props.students.map(s => s.grade_level).filter(Boolean))].sort((a, b) => a - b))
const sectionOptions = computed(() => [...new Set(props.students.map(s => s.section).filter(Boolean))].sort())

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.students.filter(s => {
    if (gradeFilter.value && String(s.grade_level) !== String(gradeFilter.value)) return false
    if (sectionFilter.value && s.section !== sectionFilter.value) return false
    if (!q) return true
    return String(s.name ?? '').toLowerCase().includes(q)
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

const TH = 'px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
const TD = 'px-4 py-3 text-sm text-slate-600'
</script>

<template>
  <Head title="ALP Unassigned Grades 7-10" />
  <AdminLayout title="ALP Unassigned Grades 7-10">
    <div class="space-y-5">
      <AppPageHeader
        title="Unassigned Grades 7–10"
        subtitle="Enrolled Grade 7-10 scholars with no active ALP membership this school year."
        :breadcrumb="[{ label: 'Alternative Learning Program', href: route('alp.index') }, { label: 'Unassigned Grades 7–10' }]"
      >
        <template #actions>
          <AppSelect :model-value="selectedSchoolYearId" :show-blank="false" aria-label="School year" class="min-w-40" @update:model-value="selectYear">
            <option v-for="year in schoolYears" :key="year.id" :value="year.id">{{ year.name }}</option>
          </AppSelect>
          <AppButton as="a" :href="route('alp.unassigned.pdf', { school_year_id: selectedSchoolYearId })" target="_blank" variant="secondary">
            <DocumentArrowDownIcon class="h-4 w-4" />
            Download PDF
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search name..."
            class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-64" />
        </div>
        <select v-model="gradeFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Grades</option>
          <option v-for="g in gradeOptions" :key="g" :value="g">Grade {{ g }}</option>
        </select>
        <select v-model="sectionFilter" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Sections</option>
          <option v-for="s in sectionOptions" :key="s" :value="s">{{ s }}</option>
        </select>
      </AppFilterBar>

      <AppCard :padded="false">
        <AppTable :is-empty="displayed.length === 0" :skeleton-cols="3" :card="false">
          <template #head>
            <tr>
              <th :class="TH">Name</th>
              <th :class="TH">Grade</th>
              <th :class="TH">Section</th>
            </tr>
          </template>

          <tr v-for="(s, idx) in displayed" :key="idx" class="hover:bg-indigo-50/40">
            <td :class="TD" class="font-medium text-slate-800">{{ s.name }}</td>
            <td :class="TD">{{ s.grade_level }}</td>
            <td :class="TD">{{ s.section || '—' }}</td>
          </tr>

          <template #empty>
            <EmptyState title="No unassigned scholars found" subtitle="Every enrolled Grade 7-10 scholar currently has an active ALP membership." />
          </template>
        </AppTable>

        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
          <p class="text-xs text-slate-500">{{ filtered.length }} student(s) — page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-1">
            <AppButton variant="secondary" size="sm" :disabled="currentPage === 1" @click="currentPage--">Prev</AppButton>
            <AppButton variant="secondary" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">Next</AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Manually verify**

Visit `/cid/alp/unassigned` directly by URL in the dev server and confirm the table, filters, and PDF download work.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/CID/ALP/Unassigned.vue
git commit -m "feat(alp): add Unassigned Grades 7-10 roster page"
```

---

### Task 7: Wire the dashboard cards to be clickable

**Files:**
- Modify: `resources/js/Pages/CID/ALP/Index.vue`

**Interfaces:**
- Consumes: routes `alp.members.index` (Task 1), `alp.unassigned.index` (Task 2).

- [ ] **Step 1: Add `href` to the two relevant stat cards**

In `resources/js/Pages/CID/ALP/Index.vue`, replace the `statCards` computed (currently lines 42-47):

```js
const statCards = computed(() => [
  { label: 'Programs', value: props.stats.programs || 0, icon: AcademicCapIcon, tone: 'bg-indigo-50 text-indigo-600' },
  { label: 'Accredited', value: props.stats.accredited || 0, icon: CheckBadgeIcon, tone: 'bg-success-50 text-success-600' },
  { label: 'Active members', value: props.stats.members || 0, icon: UserGroupIcon, tone: 'bg-blue-50 text-blue-600', href: route('alp.members.index') },
  { label: 'Unassigned Grades 7–10', value: props.stats.unassignedRequired || 0, icon: UserMinusIcon, tone: 'bg-warning-50 text-warning-600', href: route('alp.unassigned.index') },
])
```

- [ ] **Step 2: Make the card clickable in the template**

Replace the stat-cards `<section>` block (currently lines 79-91):

```html
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="ALP summary">
        <AppCard
          v-for="item in statCards"
          :key="item.label"
          :tabindex="item.href ? 0 : undefined"
          :class="item.href ? 'cursor-pointer transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-indigo-500' : ''"
          @click="item.href && router.visit(item.href)"
          @keydown.enter="item.href && router.visit(item.href)"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-medium text-slate-500">{{ item.label }}</p>
              <p class="mt-2 font-heading text-2xl font-semibold text-slate-900">{{ item.value }}</p>
            </div>
            <span :class="['flex h-10 w-10 items-center justify-center rounded-xl', item.tone]">
              <component :is="item.icon" class="h-5 w-5" />
            </span>
          </div>
        </AppCard>
      </section>
```

- [ ] **Step 3: Manually verify**

Visit `/cid/alp` in the dev server. Confirm: "Programs" and "Accredited" cards are not clickable (no pointer cursor, no navigation on click). "Active members" and "Unassigned Grades 7–10" cards show a pointer cursor, a hover shadow, and clicking (or focusing + Enter) navigates to `/cid/alp/members` and `/cid/alp/unassigned` respectively, each showing a row count matching the dashboard's stat number.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/CID/ALP/Index.vue
git commit -m "feat(alp): make Active Members and Unassigned dashboard cards clickable"
```

---

### Task 8: Restyle ALP attendance to match Homeroom's toggle-pill pattern

**Files:**
- Modify: `resources/js/Pages/CID/ALP/Show.vue`

**Interfaces:**
- No backend changes — `saveAttendance()` (`app/Http/Controllers/ALP/AlpController.php:414-434`) already accepts `records.*.status` in `present,absent,tardy,cutting,excused`; this task only changes how the value is captured client-side, not the value set itself.

- [ ] **Step 1: Add the status pill definitions and a mark-all-present helper**

In `resources/js/Pages/CID/ALP/Show.vue`, add right after the `attendanceDrafts` declaration (currently line 83, before `saveAttendance`):

```js
const ATTENDANCE_STATUSES = [
  { value: 'present', label: 'Present', code: 'P', cls: 'bg-emerald-100 text-emerald-700' },
  { value: 'absent',  label: 'Absent',  code: 'A', cls: 'bg-red-100 text-red-700' },
  { value: 'tardy',   label: 'Tardy',   code: 'T', cls: 'bg-amber-100 text-amber-700' },
  { value: 'cutting', label: 'Cutting', code: 'C', cls: 'bg-orange-100 text-orange-700' },
  { value: 'excused', label: 'Excused', code: 'E', cls: 'bg-slate-200 text-slate-700' },
]
const setAttendanceStatus = (sessionId, memberId, status) => { attendanceDrafts.value[sessionId][memberId].status = status }
const presentCount = (session) => Object.values(attendanceDrafts.value[session.id] || {}).filter(r => r.status === 'present').length
const markAllPresent = (session) => { Object.values(attendanceDrafts.value[session.id] || {}).forEach(r => { r.status = 'present' }) }
```

- [ ] **Step 2: Replace the per-row dropdown with toggle pills, add the mark-all button, counter, and legend**

Replace the session-card block (currently lines 313-316):

```html
        <AppCard v-for="session in cycle.sessions" :key="session.id">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="font-heading text-sm font-semibold text-slate-800">{{ session.topic || 'ALP Session' }}</h2>
              <p class="text-sm text-slate-500">{{ date(session.session_date) }} · {{ session.venue || 'No venue' }}</p>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="statusColor(session.status)">{{ label(session.status) }}</AppBadge>
              <span class="text-xs text-slate-500">{{ presentCount(session) }} / {{ activeMembers.length }} present</span>
            </div>
          </div>

          <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
            <span v-for="s in ATTENDANCE_STATUSES" :key="s.value" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold" :class="s.cls">
              {{ s.code }} = {{ s.label }}
            </span>
          </div>

          <div class="mt-4 max-h-80 overflow-auto rounded-xl ring-1 ring-slate-200/70">
            <div v-for="member in activeMembers" :key="member.id" class="grid grid-cols-1 items-center gap-2 border-b border-slate-100 p-3 text-sm last:border-0 sm:grid-cols-[1fr_170px_1fr]">
              <span class="font-medium text-slate-700">{{ member.student?.full_name }}</span>
              <div v-if="attendanceDrafts[session.id]?.[member.id]" class="flex gap-1">
                <button
                  v-for="s in ATTENDANCE_STATUSES" :key="s.value"
                  type="button"
                  :title="s.label"
                  :class="['min-w-[30px] h-7 px-1.5 rounded-md text-[11px] font-bold transition-colors', attendanceDrafts[session.id][member.id].status === s.value ? s.cls : 'bg-slate-50 text-slate-300 hover:bg-slate-100']"
                  @click="setAttendanceStatus(session.id, member.id, s.value)"
                >
                  {{ s.code }}
                </button>
              </div>
              <AppInput v-if="attendanceDrafts[session.id]?.[member.id]" v-model="attendanceDrafts[session.id][member.id].remarks" placeholder="Remarks" />
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton v-if="abilities.manage" variant="secondary" size="sm" @click="markAllPresent(session)">Mark All Present</AppButton>
            <AppButton v-if="abilities.manage" size="sm" @click="saveAttendance(session)">Save attendance</AppButton>
            <AppButton as="a" :href="route('alp.attendance.pdf', [cycle.id, session.id])" target="_blank" size="sm" variant="secondary">Form 33 PDF</AppButton>
          </div>
        </AppCard>
```

- [ ] **Step 3: Manually verify**

Start the dev server, open an ALP cycle's Attendance tab (`/cid/alp/cycles/{id}`, Attendance tab). Confirm: each session shows 5 color-coded pill buttons per row (P/A/T/C/E) instead of a dropdown, clicking a pill highlights it and un-highlights the others for that row, the legend row explains each code, "Mark All Present" sets every row in that session to Present and updates the "X / Y present" counter, and "Save attendance" still posts successfully (check the network tab or the success flash message) with the same status values as before.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/CID/ALP/Show.vue
git commit -m "feat(alp): restyle attendance status picker to match Homeroom's toggle-pill pattern"
```

---

### Task 9: Full regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full PHP test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: All tests pass (no regressions from Tasks 1–4; Tasks 5–8 are frontend-only).

- [ ] **Step 2: PHP lint the changed files**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -l app/Services/ALP/AlpRosterService.php && php -l app/Http/Controllers/ALP/AlpController.php && php -l app/Http/Controllers/ALP/AlpPdfController.php && php -l app/Services/ALP/AlpPdfService.php"`
Expected: "No syntax errors detected" for each file.

- [ ] **Step 3: Build frontend assets**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: Build succeeds with no errors referencing `CID/ALP/Members.vue`, `CID/ALP/Unassigned.vue`, `CID/ALP/Index.vue`, or `CID/ALP/Show.vue`.

- [ ] **Step 4: Manual end-to-end walkthrough in the browser**

Using the dev server (`http://localhost:8080`), as a user with an `alp.*` permission:
1. Go to `/cid/alp`. Confirm "Active members" and "Unassigned Grades 7–10" cards are visually clickable (cursor, hover) and the other two are not.
2. Click "Active members" → confirm the roster page loads, row count matches the dashboard number, search/grade/section filters work, "Download PDF" produces a correctly formatted PDF.
3. Go back, click "Unassigned Grades 7–10" → same checks, minus the ALP column.
4. Open an ALP cycle → Attendance tab → confirm the pill-button restyle, legend, "Mark All Present", and that saving attendance still works end-to-end (reload the page and confirm the saved statuses persisted).

- [ ] **Step 5: Report completion**

No commit needed for this task — it's verification only. Summarize results to the user (test counts, any issues found and how they were resolved).
