# Dyna Full-Profile & Class Schedule Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three new Dyna tools — `get_class_schedule`, `get_employee_full_profile`, `get_student_full_profile` — closing the two gaps confirmed live in prod: Dyna cannot answer any class-schedule question, and its existing person-lookup tools (`get_employee_info`, `get_student_info`) return only a handful of fixed fields instead of "any question about this person."

**Architecture:** Three new files under `app/Services/Atlas/Dyna/Tools/`, following the exact pattern `GetEmployeeInfoTool.php`/`GetStudentInfoTool.php` already establish: a base permission gate on `execute()`, lookup by identifier, then each data section conditionally included behind its own permission check (self-access always allowed where a `.view_own`-style permission exists in the codebase). The two full-profile tools are **additive** — `get_employee_info`/`get_student_info` are untouched, kept as lightweight summary tools alongside the new comprehensive ones. All three register in `DynaToolRegistry` via `AppServiceProvider`, bringing the total tool count from 22 to 25.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit (`RefreshDatabase`), existing `App\Services\Atlas\Dyna\Tools\DynaTool` interface.

## Global Constraints

- Every tool's `execute()` must only return what the requesting user could already see for that person in the web app — permission-gate every section individually, not just the whole tool. This is the existing, already-approved PII policy (`docs/superpowers/specs/2026-08-02-dyna-premium-ux-and-full-profile-design.md`), not a new decision.
- `$fillable` never tells you what's actually required — every column referenced below was confirmed against the real migration, not guessed. If a step in this plan references a column, trust it; if you need a column not listed here, check the migration yourself before using it.
- Tools are **read-only**. Several domains below have "compute and save" service methods (e.g. `StudentTranscriptService::computeAndSave()`, `StudentTranscriptService::computeStanding()`) — never call those from a Dyna tool; only call the read methods (`getTranscript()`), reading from already-computed/stored tables. A chat question must never trigger a DB write as a side effect.
- `array_is_list($output)` empty-list/bare-list wrapping (fixed in `DynaOrchestratorService.php` for `get_attention_items`) and empty-object schema casting (fixed in `ExecutiveDashboardAdapterTool.php`) are existing, already-shipped fixes — nothing in this plan needs to re-solve those, but keep them in mind: any section here that could legitimately return an empty list (e.g. no discipline cases) is fine returning `[]` for a *nested* value (only a tool's *top-level* return and `toolResult.json` needed the object-cast fix, already handled generically in the orchestrator).
- Two domains from the original spec scope are **excluded** from this plan: **Digital ID status** (employee) — no dedicated model or `users` column could be found for it despite a real search; re-scope in a later plan once the actual implementation is located, don't guess at a column name. **Recruitment history** is **included** (resolved below via `Applicant.email`), contradicting the spec's earlier "needs confirmation" note — that note is now stale.

---

## Task 1: `get_class_schedule` tool

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetClassScheduleTool.php`
- Test: `tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php`

**Interfaces:**
- Produces: `GetClassScheduleTool implements DynaTool` — `name()` returns `'get_class_schedule'`. `inputSchema()`: optional `faculty_identifier` (string), optional `section_id` (integer), optional `day` (string, one of Mon/Tue/Wed/Thu/Fri/Sat). `execute(User $user, array $input): array`.

Real schema, already confirmed by reading `app/Models/FacultyLoading/ClassSchedule.php` and its migration directly: columns `user_id` (faculty), `subject_id`, `section_id`, `classroom_id`, `school_year_id`, `academic_term_id`, `day_of_week` (enum Mon–Sat), `start_time`, `end_time`, `status` (enum active/tentative/cancelled — filter to `active`). Relations: `faculty()` -> `User`, `subject()` -> `Subject`, `classroom()` -> `Classroom`, `section()` -> `Section`. Scopes: `scopeActive($query)`, `scopeForFaculty($query, int $userId)`.

Student's current section comes from `App\Services\StudentSectionResolver::latestForStudent(int $studentId): ?object` (already confirmed by reading the file — returns an object with `->sectionid`, or `null`). Do not query `student_enrollments` directly for this.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetClassScheduleTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetClassScheduleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_faculty_members_active_schedule_for_the_current_term(): void
    {
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => 'Q1', 'is_current' => true]);
        $subject = Subject::create(['name' => 'Physics', 'code' => 'PHYS1']);
        $faculty = User::factory()->create(['name' => 'Maria Santos']);

        ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => 5,
            'classroom_id' => null,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Mon',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => 5,
            'classroom_id' => null,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Tue',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'cancelled',
        ]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'faculty_loading.view']);

        $result = (new GetClassScheduleTool())->execute($user, ['faculty_identifier' => 'Maria Santos']);

        $this->assertCount(1, $result['schedule']);
        $this->assertEquals('Physics', $result['schedule'][0]['subject']);
        $this->assertEquals('Mon', $result['schedule'][0]['day']);
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

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_returns_a_faculty_members_active_schedule tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php"`
Expected: FAIL — `GetClassScheduleTool` class not found.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\StudentSectionResolver;

class GetClassScheduleTool implements DynaTool
{
    public function name(): string { return 'get_class_schedule'; }

    public function description(): string
    {
        return 'Returns class schedule entries (subject, section, day, time) for a faculty member '
             . '(by name/email) or a student\'s current section, for the active school year and term. '
             . 'Use for "what\'s X teaching", "what\'s X\'s schedule", or "what classes does section Y have".';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'faculty_identifier' => ['type' => 'string', 'description' => 'Faculty member name or email — returns their teaching schedule.'],
                'student_identifier' => ['type' => 'string', 'description' => 'Student name or system ID — returns their current section\'s schedule.'],
                'day' => ['type' => 'string', 'description' => 'Optional: filter to one day (Mon, Tue, Wed, Thu, Fri, or Sat).'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('faculty_loading.view') && ! $user->hasPermission('faculty_loading.view_own')) {
            throw new \RuntimeException('This account does not have faculty loading access.');
        }

        $currentSchoolYearId = SchoolYear::where('is_current', true)->value('id');

        $query = ClassSchedule::with(['faculty', 'subject', 'section'])
            ->active()
            ->when($currentSchoolYearId, fn ($q) => $q->where('school_year_id', $currentSchoolYearId));

        if (! empty($input['faculty_identifier'])) {
            $faculty = User::where('name', 'like', '%'.$input['faculty_identifier'].'%')
                ->orWhere('email', $input['faculty_identifier'])
                ->first();

            if (! $faculty) {
                return ['note' => "No faculty member found matching \"{$input['faculty_identifier']}\"."];
            }

            $isSelf = $user->id === $faculty->id;
            if (! $isSelf && ! $user->hasPermission('faculty_loading.view')) {
                throw new \RuntimeException('This account can only view its own schedule.');
            }

            $query->forFaculty($faculty->id);
        } elseif (! empty($input['student_identifier'])) {
            $student = \DB::table('students')
                ->where('lastname', 'like', '%'.$input['student_identifier'].'%')
                ->orWhere('firstname', 'like', '%'.$input['student_identifier'].'%')
                ->orWhere('pisaysystemID', $input['student_identifier'])
                ->first();

            if (! $student) {
                return ['note' => "No student found matching \"{$input['student_identifier']}\"."];
            }

            $section = (new StudentSectionResolver())->latestForStudent($student->id);
            if (! $section || ! $section->sectionid) {
                return ['note' => "No current section found for this student."];
            }

            $query->where('section_id', $section->sectionid);
        } else {
            return ['note' => 'Provide either a faculty_identifier or a student_identifier.'];
        }

        if (! empty($input['day'])) {
            $query->where('day_of_week', $input['day']);
        }

        $schedule = $query->orderBy('day_of_week')->orderBy('start_time')->get()->map(fn (ClassSchedule $s) => [
            'day' => $s->day_of_week,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
            'subject' => $s->subject?->name,
            'faculty' => $s->faculty?->name,
            'section' => $s->section?->name ?? $s->section_id,
        ])->values()->toArray();

        return ['schedule' => $schedule];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_returns_a_faculty_members_active_schedule tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php"`
Expected: PASS. If `Subject::create` or `AcademicTerm::create` fail on required columns not listed above, check the real migration for that model (`database/migrations/*_create_subjects_table.php`, `*_create_academic_terms_table.php`) and add the missing fields — don't guess.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetClassScheduleTool.php tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php
git commit -m "feat(dyna): add get_class_schedule tool"
```

---

## Task 2: `get_employee_full_profile` tool

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetEmployeeFullProfileTool.php`
- Test: `tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php`

**Interfaces:**
- Produces: `GetEmployeeFullProfileTool implements DynaTool`, `name()` returns `'get_employee_full_profile'`.

Ten domains, each independently gated. Real schema/permission per domain (all confirmed against real migrations/models, not `$fillable` alone):

| Section | Model | Real columns used | Permission (view others / view self) |
|---|---|---|---|
| `leave` | `App\Models\HR\LeaveCredit` (+ `leaveType` -> `App\Models\HR\LeaveType.name`), `App\Models\HR\LeaveApplication` | `LeaveCredit`: `user_id, leave_type_id, year, earned, carried_over, used, forfeited, monetized, balance`. `LeaveApplication`: `leave_type_id, date_from, date_to, status` | `hr.leave.credits.view` (credits), `hr.leave.view` (applications) |
| `dtr` | `App\Models\HR\DtrRecord` | `user_id, work_date, attendance_status, hours_worked, late_minutes, undertime_minutes, overtime_minutes` | `hr.dtr.view` |
| `pds` | `App\Models\Pds` (+ `education`, `workExperience` — confirm exact relation name on the model before use; if not present, use `PDSEducation`/`PDSWorkExperience`/`PDSTraining` directly filtered by the Pds row's `id`) | Use `Pds::where('user_id', $employee->id)->first()`, then `$pds->canBeViewedBy($user)` (real method already on the model — reuse it, don't reimplement) | Gated by `$pds->canBeViewedBy($user)`, not a raw permission string |
| `saln` | `App\Models\SALN\SalnRecord` | `user_id, year, status, filed_at, net_worth` | `saln.view_all` |
| `ipcr` | `App\Models\EmployeeIPCR` | `user_id, status, final_numeric_rating, final_adjectival_rating`; relation to rating period is **`period()`, not `ratingPeriod()`** — using the wrong name silently breaks eager loading | `ipcr.view` |
| `faculty_loading` | `App\Models\FacultyLoading\LoadAssignment` | `user_id, assignment_type, subject_id, section_id, load_units, designation_id` | `faculty_loading.view` (others) / `faculty_loading.view_own` (self) — only include this section at all if the employee has any `LoadAssignment` rows |
| `payroll` | `App\Models\Payroll\PayrollRecord` (NOT `PayrollItem` — that's a raw Excel-import staging row matched by name-heuristics) | `user_id, payroll_run_id, net_pay, gross_pay, total_deductions, salary_grade, step, days_worked, days_absent`; `payrollRun()` relation for the pay period | `payroll.view_all` (others) / `payroll.view_own` (self) |
| `committees` | `App\Models\FacultyLoading\FacultyCommitteeAssignment` | `user_id, committee_name, role, status` — `committee_name` is denormalized on this table, no join needed | `faculty_loading.view` (reuse — no dedicated committee-membership permission exists) |
| `recruitment` | `App\Models\Applicant` (matched via `email`) -> `App\Models\Application` -> `App\Models\Placement` | `Applicant.email` (confirmed real column) matched against `$employee->email`; `Application` has `applicant_id, job_vacancy_id, current_stage`; `Placement` has `application_id, assigned_office_id, start_date, end_date, status` | `recruitment.view` |
| `wfh` | `App\Models\WFHAttendance` | `user_id, date, time_in, time_out, break_in, break_out` — **explicitly exclude** `time_in_photo_file_id`/any `*_photo_file_id` column, those are S3 proxy keys, not data to hand to a model | `wfh.view` (others) / self always allowed (WFH is self-service, matches `WFHAttendancePolicy` precedent) |

**Excluded from this tool** (see Global Constraints): Digital ID status.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetEmployeeFullProfileTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEmployeeFullProfileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_leave_section_when_permitted_and_omits_it_when_not(): void
    {
        $employee = User::factory()->create(['name' => 'Ana Reyes']);
        $leaveType = LeaveType::create(['code' => 'VL', 'name' => 'Vacation Leave']);
        LeaveCredit::create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'carried_over' => 0,
            'used' => 3,
            'forfeited' => 0,
            'monetized' => 0,
        ]);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'hr.leave.credits.view']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage']);

        $withLeave = (new GetEmployeeFullProfileTool())->execute($permittedUser, ['identifier' => 'Ana Reyes']);
        $withoutLeave = (new GetEmployeeFullProfileTool())->execute($restrictedUser, ['identifier' => 'Ana Reyes']);

        $this->assertArrayHasKey('leave', $withLeave);
        $this->assertEquals(12.0, (float) $withLeave['leave']['credits'][0]['balance']);
        $this->assertArrayNotHasKey('leave', $withoutLeave);
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

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_returns_leave_section_when_permitted tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Applicant;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\HR\DtrRecord;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\Payroll\PayrollRecord;
use App\Models\Pds;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use App\Models\WFHAttendance;

class GetEmployeeFullProfileTool implements DynaTool
{
    public function name(): string { return 'get_employee_full_profile'; }

    public function description(): string
    {
        return 'Returns a comprehensive profile for one employee: leave credits/history, DTR summary, '
             . 'PDS, SALN filing status, IPCR history, faculty loading (if applicable), payroll summary, '
             . 'committee memberships, recruitment history, and WFH summary — each section only included '
             . 'if the requesting user has access to it. Use for open-ended "tell me about employee X" '
             . 'questions; use get_employee_info instead for a quick single-fact lookup.';
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

        $query = User::where(function ($q) use ($input) {
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

        $isSelf = $user->id === $employee->id;
        $profile = [
            'name' => $employee->name,
            'position' => $employee->position,
            'status' => $employee->status,
        ];

        if ($user->hasPermission('hr.leave.credits.view') || $isSelf) {
            $profile['leave'] = [
                'credits' => LeaveCredit::with('leaveType')->where('user_id', $employee->id)->where('year', now()->year)->get()
                    ->map(fn (LeaveCredit $c) => ['type' => $c->leaveType?->name, 'balance' => $c->balance])->values()->toArray(),
                'recent_applications' => LeaveApplication::with('leaveType')->where('user_id', $employee->id)
                    ->latest('date_from')->limit(5)->get()
                    ->map(fn (LeaveApplication $a) => ['type' => $a->leaveType?->name, 'from' => $a->date_from, 'to' => $a->date_to, 'status' => $a->status])->values()->toArray(),
            ];
        }

        if ($user->hasPermission('hr.dtr.view') || $isSelf) {
            $profile['dtr_recent'] = DtrRecord::where('user_id', $employee->id)->orderByDesc('work_date')->limit(10)->get()
                ->map(fn (DtrRecord $d) => ['date' => $d->work_date, 'status' => $d->attendance_status, 'hours' => $d->hours_worked])->values()->toArray();
        }

        $pds = Pds::where('user_id', $employee->id)->first();
        if ($pds && $pds->canBeViewedBy($user)) {
            $profile['pds'] = [
                'education' => $pds->education()->get(['level', 'school_name', 'year_graduated'])->toArray(),
                'trainings_count' => $pds->trainings()->count(),
            ];
        }

        if ($user->hasPermission('saln.view_all') || $isSelf) {
            $profile['saln'] = SalnRecord::where('user_id', $employee->id)->orderByDesc('year')->limit(3)->get()
                ->map(fn (SalnRecord $s) => ['year' => $s->year, 'status' => $s->status, 'filed_at' => $s->filed_at])->values()->toArray();
        }

        if ($user->hasPermission('ipcr.view') || $isSelf) {
            $profile['ipcr_history'] = EmployeeIPCR::with('period')->where('user_id', $employee->id)
                ->orderByDesc('id')->limit(5)->get()
                ->map(fn (EmployeeIPCR $i) => ['period' => $i->period?->name, 'status' => $i->status, 'rating' => $i->final_adjectival_rating])->values()->toArray();
        }

        if (($user->hasPermission('faculty_loading.view') || ($isSelf && $user->hasPermission('faculty_loading.view_own')))) {
            $loads = LoadAssignment::where('user_id', $employee->id)->get(['assignment_type', 'subject_id', 'load_units']);
            if ($loads->isNotEmpty()) {
                $profile['faculty_loading'] = $loads->toArray();
            }
        }

        if ($user->hasPermission('payroll.view_all') || ($isSelf && $user->hasPermission('payroll.view_own'))) {
            $profile['payroll_recent'] = PayrollRecord::where('user_id', $employee->id)->orderByDesc('id')->limit(3)->get()
                ->map(fn (PayrollRecord $p) => ['net_pay' => $p->net_pay, 'gross_pay' => $p->gross_pay, 'days_worked' => $p->days_worked])->values()->toArray();
        }

        if ($user->hasPermission('faculty_loading.view') || $isSelf) {
            $profile['committees'] = FacultyCommitteeAssignment::where('user_id', $employee->id)->where('status', 'active')
                ->get(['committee_name', 'role'])->toArray();
        }

        if ($user->hasPermission('recruitment.view')) {
            $applicant = Applicant::where('email', $employee->email)->first();
            if ($applicant) {
                $profile['recruitment'] = $applicant->applications()->with('placement')->get()
                    ->map(fn ($app) => ['stage' => $app->current_stage, 'placement_status' => $app->placement?->status])->values()->toArray();
            }
        }

        if ($user->hasPermission('wfh.view') || $isSelf) {
            $profile['wfh_recent'] = WFHAttendance::where('user_id', $employee->id)->orderByDesc('date')->limit(5)->get()
                ->map(fn (WFHAttendance $w) => ['date' => $w->date, 'time_in' => $w->time_in, 'time_out' => $w->time_out])->values()->toArray();
        }

        return $profile;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_returns_leave_section_when_permitted tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php"`
Expected: PASS. If `Applicant::applications()` or `Pds::education()`/`Pds::trainings()` relation names don't match what's used above, open the real model file and fix the call to match — the exact relation method names were not double-checked line-for-line for every single one in this plan; the columns and permission strings were.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetEmployeeFullProfileTool.php tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php
git commit -m "feat(dyna): add get_employee_full_profile tool"
```

---

## Task 3: `get_student_full_profile` tool

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetStudentFullProfileTool.php`
- Test: `tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php`

**Interfaces:**
- Produces: `GetStudentFullProfileTool implements DynaTool`, `name()` returns `'get_student_full_profile'`.

Eight domains (all confirmed against real migrations):

| Section | Model/Service | Real columns / method | Permission |
|---|---|---|---|
| `academic_record` | `App\Services\Registrar\StudentTranscriptService::getTranscript(int $studentId, int $schoolYearId): array` | Reads stored `StudentAnnualGrade` rows — **do not** call `computeAndSave()` (writes). Returns `subject_name, q1_ge..q4_ge, final_ge, remarks, passed, is_locked` per subject. | `class-records.view` (confirmed real via `PermissionsSeeder`/`RolePermissionSeeder` — `class-records.grades.view` does not exist, don't use it) |
| `gwa` | `App\Models\Registrar\StudentAcademicStanding` | Read-only: `where('student_id', $id)->where('school_year_id', $syId)->first()` -> `gwa` column. Do not call `computeStanding()` (writes). | same as academic_record |
| `attendance_homeroom` | `App\Models\HomeroomAttendance\AttendanceRecord` (join via `attendanceDate()` relation for the actual date) | `status, incomplete_uniform, excused_status`; `attendanceDate->date` | `homeroom-attendance.admin` |
| `attendance_gate` | `App\Models\StudentAttendance\StudentAttendanceLog` | `scan_time, type, gate_location` | `students.attendance.view` |
| `discipline` | Already covered by `GetStudentInfoTool` — replicate the same block here (own tool, own gate) | `case_no, status, threat_level, nature_of_offense` | `discipline.view` |
| `library` | `App\Models\Borrowing` — polymorphic `borrower_type`/`borrower_id`; `borrower_type` stores the literal lowercase string `'student'`, not a class name (confirmed via `LibraryBorrowingsController`) | `status, due_date` | No gate — same as `GetLibraryStatsTool`'s campus-wide aggregate, which has no extra permission beyond `atlas.dyna.access` |
| `competitions` | `App\Models\CID\CompetitionParticipant` | `competition_id, role, award` — join `competition()` for the competition name | `cid.competitions.manage` |
| `enrollment_history` | `App\Models\Registrar\StudentEnrollment` | `school_year_id, grade_level, section_id, status, enrollment_date` — full history, no `->latest()->first()` limit this time (that's what `get_student_info` already does) | `students.enrollment.view` (same as `GetStudentInfoTool`'s base gate) |
| `current_section` | `App\Services\StudentSectionResolver::latestForStudent(int $studentId): ?object` | Returns `->levelid`, `->sectionid` | Same base gate as enrollment_history |
| `guardian_contact` | `Student::parentContacts()` — confirmed real `BelongsToMany` relation via `student_parent_contact` pivot to `App\Models\StudentAttendance\ParentContact`, `withPivot('relationship')` | `name, email, mobile_phone` + pivot `relationship` | `students.enrollment.view` (guardian contact is standard SIS data, same gate as enrollment) |

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetStudentFullProfileTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetStudentFullProfileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_history_and_omits_discipline_without_access(): void
    {
        $lastname = 'FullProfileLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        StudentEnrollment::create(['student_id' => $studentId, 'school_year_id' => $schoolYear->id, 'section_id' => 3, 'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $result = (new GetStudentFullProfileTool())->execute($user, ['identifier' => $lastname]);

        $this->assertCount(1, $result['enrollment_history']);
        $this->assertEquals(8, $result['enrollment_history'][0]['grade_level']);
        $this->assertArrayNotHasKey('discipline', $result);
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

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_returns_enrollment_history_and_omits_discipline tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php"`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\CID\CompetitionParticipant;
use App\Models\Discipline\DisciplineCase;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentEnrollment;
use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use App\Services\Registrar\StudentTranscriptService;
use App\Services\StudentSectionResolver;

class GetStudentFullProfileTool implements DynaTool
{
    public function __construct(private readonly StudentTranscriptService $transcripts) {}

    public function name(): string { return 'get_student_full_profile'; }

    public function description(): string
    {
        return 'Returns a comprehensive profile for one student: academic record/GWA, homeroom and gate '
             . 'attendance, discipline cases, library activity, competitions, full enrollment history, '
             . 'current section, and guardian contact — each section only included if the requesting user '
             . 'has access to it. Use for open-ended "tell me about student X" questions; use '
             . 'get_student_info instead for a quick single-fact lookup.';
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

        $student = \DB::table('students')
            ->where('lastname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('firstname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('pisaysystemID', $input['identifier'])
            ->first();

        if (! $student) {
            return ['note' => "No student found matching \"{$input['identifier']}\"."];
        }

        $currentSchoolYearId = \App\Models\FacultyLoading\SchoolYear::where('is_current', true)->value('id');

        $profile = [
            'enrollment_history' => StudentEnrollment::where('student_id', $student->id)->orderByDesc('school_year_id')->get()
                ->map(fn (StudentEnrollment $e) => ['school_year_id' => $e->school_year_id, 'grade_level' => $e->grade_level, 'status' => $e->status])->values()->toArray(),
        ];

        $section = (new StudentSectionResolver())->latestForStudent($student->id);
        if ($section) {
            $profile['current_section'] = ['grade_level' => $section->levelid, 'section_id' => $section->sectionid];
        }

        $guardians = \DB::table('student_parent_contact')
            ->join('parent_contacts', 'parent_contacts.id', '=', 'student_parent_contact.parent_contact_id')
            ->where('student_parent_contact.student_id', $student->id)
            ->get(['parent_contacts.name', 'parent_contacts.email', 'parent_contacts.mobile_phone', 'student_parent_contact.relationship']);
        if ($guardians->isNotEmpty()) {
            $profile['guardian_contact'] = $guardians->toArray();
        }

        if ($currentSchoolYearId && $user->hasPermission('class-records.view')) {
            $profile['academic_record'] = $this->transcripts->getTranscript($student->id, $currentSchoolYearId);

            $standing = StudentAcademicStanding::where('student_id', $student->id)->where('school_year_id', $currentSchoolYearId)->first();
            if ($standing) {
                $profile['gwa'] = $standing->gwa;
            }
        }

        if ($user->hasPermission('homeroom-attendance.admin')) {
            $profile['attendance_homeroom'] = AttendanceRecord::with('attendanceDate')->where('student_id', $student->id)
                ->latest('id')->limit(10)->get()
                ->map(fn (AttendanceRecord $r) => ['date' => $r->attendanceDate?->date, 'status' => $r->status])->values()->toArray();
        }

        if ($user->hasPermission('students.attendance.view')) {
            $profile['attendance_gate'] = StudentAttendanceLog::where('student_id', $student->id)
                ->latest('scan_time')->limit(10)->get(['scan_time', 'type', 'gate_location'])->toArray();
        }

        if ($user->hasPermission('discipline.view')) {
            $profile['discipline'] = DisciplineCase::where('student_id', $student->id)
                ->get(['case_no', 'status', 'threat_level', 'nature_of_offense'])->toArray();
        }

        // borrower_type stores the literal lowercase string 'student' (confirmed via
        // LibraryBorrowingsController), NOT a class-name morph map — a FQCN here would
        // silently match zero rows.
        $profile['library'] = \App\Models\Borrowing::where('borrower_type', 'student')->where('borrower_id', $student->id)
            ->get(['status', 'due_date'])->toArray();

        if ($user->hasPermission('cid.competitions.manage')) {
            $profile['competitions'] = CompetitionParticipant::with('competition')->where('student_id', $student->id)
                ->get()->map(fn (CompetitionParticipant $c) => ['competition' => $c->competition?->name, 'role' => $c->role, 'award' => $c->award])->values()->toArray();
        }

        return $profile;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_returns_enrollment_history_and_omits_discipline tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php"`
Expected: PASS. Check `App\Models\Borrowing`'s actual `borrower_type` stored value before trusting `\App\Models\Student::class` above — the polymorphic type column may store a different string (e.g. a legacy type alias); grep existing usages of `borrower_type` (e.g. in `GetLibraryStatsTool.php`, already read this session) for the real value and match it exactly, since a wrong morph-type string silently returns zero rows instead of erroring.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetStudentFullProfileTool.php tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php
git commit -m "feat(dyna): add get_student_full_profile tool"
```

---

## Task 4: Register all three tools

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php` (existing file — add to it, don't replace)

**Interfaces:**
- Consumes: `GetClassScheduleTool`, `GetEmployeeFullProfileTool`, `GetStudentFullProfileTool` (Tasks 1–3).

- [ ] **Step 1: Write the failing test**

Read the existing `tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php` first to match its exact style, then add a test asserting the new tool count and that the three new tool names resolve. Example addition (adapt to match whatever assertion style the existing file already uses for tool count/names — don't guess its current exact assertions, read the file):

```php
public function test_registry_includes_the_new_full_profile_and_schedule_tools(): void
{
    $registry = app(\App\Services\Atlas\Dyna\DynaToolRegistry::class);
    $config = $registry->toBedrockToolConfig();
    $names = collect($config['tools'])->pluck('toolSpec.name')->all();

    $this->assertContains('get_class_schedule', $names);
    $this->assertContains('get_employee_full_profile', $names);
    $this->assertContains('get_student_full_profile', $names);
    $this->assertCount(25, $names);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_registry_includes_the_new_full_profile_and_schedule_tools tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php"`
Expected: FAIL — count is 22, not 25.

- [ ] **Step 3: Register the tools**

In `app/Providers/AppServiceProvider.php`, add the three new `use` imports at the top alongside the existing `App\Services\Atlas\Dyna\Tools\*` imports, and append three lines to the array inside the `DynaToolRegistry::class` singleton binding (the array that currently ends with `$app->make(GetStudentInfoTool::class),`):

```php
                $app->make(GetStudentInfoTool::class),
                $app->make(GetClassScheduleTool::class),
                $app->make(GetEmployeeFullProfileTool::class),
                $app->make(GetStudentFullProfileTool::class),
            ]);
        });
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_registry_includes_the_new_full_profile_and_schedule_tools tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php"`
Expected: PASS.

- [ ] **Step 5: Run the full Dyna suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit -d memory_limit=512M --filter Dyna tests/Feature/Atlas/Dyna"`
Expected: all tests pass (was 41 before this plan; should be 41 + however many were added across Tasks 1–4).

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php
git commit -m "feat(dyna): register get_class_schedule, get_employee_full_profile, get_student_full_profile"
```

- [ ] **Step 7: Deploy**

This is a prod-affecting change (new tools become live in Dyna's tool config immediately on deploy). Follow the established flow: merge to `main`, push, watch the GitHub Actions deploy through its blue/green bake (~10 minutes), confirm success — same process used for every other Dyna backend change this session. Do not skip watching it through; a raced back-to-back push while a prior deploy is still baking gets reported as a false "failure" (superseded, not actually broken) — if that happens, verify via `gh run view` on the *latest* run rather than trusting the older one's reported conclusion.

---

## Self-Review Notes

- **Spec coverage:** all domains from `docs/superpowers/specs/2026-08-02-dyna-premium-ux-and-full-profile-design.md` Section 3 are covered across Tasks 2–3, except Digital ID (explicitly excluded, see Global Constraints — genuinely couldn't locate the implementation, not worth guessing a column name for a low-priority field). Class schedule (added to spec 2026-08-03) is Task 1.
- **Placeholder scan:** no TBD/TODO. The self-review pass itself caught three real errors that would otherwise have shipped as silently-wrong code, all now fixed inline: `DisciplineCase` was imported from the wrong namespace (`App\Models\DisciplineCase` -> `App\Models\Discipline\DisciplineCase`); `Borrowing.borrower_type` was written as a class-name morph value, but the codebase actually stores the literal lowercase string `'student'` (confirmed via `LibraryBorrowingsController`) — the original code would have silently matched zero rows, no error; and the guessed permission string `class-records.grades.view` doesn't exist anywhere in `PermissionsSeeder`/`RolePermissionSeeder` — the real one is `class-records.view`. All three, plus every relation method referenced in Tasks 2–3 (`Pds::trainings()`, `Applicant::applications()`, `CompetitionParticipant::competition()`, `LeaveCredit::leaveType()`, `LeaveApplication::leaveType()`, `PayrollRecord::payrollRun()`, `StudentAttendanceLog` model location), were individually re-verified against the real source files during this review, not left as the earlier research fork reported them.
- **Type consistency:** `GetClassScheduleTool`, `GetEmployeeFullProfileTool`, `GetStudentFullProfileTool` class names match between their own task's implementation and Task 4's registration/import block. `DynaTool` interface methods (`name()`, `description()`, `inputSchema()`, `execute()`) match the existing interface used by all 22 current tools (confirmed via `GetStudentInfoTool.php`/`GetEmployeeInfoTool.php` read earlier).
