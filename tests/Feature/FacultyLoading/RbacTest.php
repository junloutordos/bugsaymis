<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 — RBAC boundary tests.
 *
 * Verifies that each Faculty Loading permission scope correctly allows or
 * denies access to its routes. Uses the same helper pattern as
 * FacultyLoadingHttpTest (Role + Permission pivot tables).
 *
 * Scopes tested:
 *   faculty_loading.view_own  → /my-load only
 *   faculty_loading.view      → /faculty-loading index
 *   faculty_loading.manage    → assignments, schedules, sections, etc.
 *   faculty_loading.approve   → overload-computations, salary-schedules
 *   faculty_loading.reports   → reports/loads, reports/overload-pay
 *   faculty_loading.subjects  → subjects catalog
 *   faculty_loading.classrooms → classrooms catalog
 *   faculty_loading.school_year → school-years catalog
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper — create a user with specific permissions ─────────────────────

    private function userWith(array|string $permissions): User
    {
        $permissions = (array) $permissions;
        $role = Role::create(['name' => 'RbacRole_' . uniqid()]);
        foreach ($permissions as $name) {
            $perm = Permission::firstOrCreate(
                ['name' => $name],
                ['module' => 'FacultyLoading', 'description' => $name]
            );
            $role->permissions()->attach($perm->id);
        }
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function seedTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01',
            'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active',
        ]);
        return AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'start_date' => '2025-08-01',
            'end_date' => '2025-12-31', 'is_current' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // faculty_loading.view_own — Faculty role
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_view_own_allows_my_load(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.view_own');

        $this->actingAs($user)
            ->get(route('faculty-loading.my-load'))
            ->assertOk();
    }

    public function test_view_own_denies_faculty_loads_index(): void
    {
        $user = $this->userWith('faculty_loading.view_own');

        $this->actingAs($user)
            ->get(route('faculty-loading.index'))
            ->assertForbidden();
    }

    public function test_view_own_denies_manage_routes(): void
    {
        $user = $this->userWith('faculty_loading.view_own');

        $this->actingAs($user)
            ->get(route('faculty-loading.assignments.index'))
            ->assertForbidden();
    }

    public function test_view_own_denies_approve_routes(): void
    {
        $user = $this->userWith('faculty_loading.view_own');

        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertForbidden();
    }

    public function test_view_own_denies_reports(): void
    {
        $user = $this->userWith('faculty_loading.view_own');

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.loads'))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // faculty_loading.view — read-only staff
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_view_allows_faculty_loads_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.view');

        $this->actingAs($user)
            ->get(route('faculty-loading.index'))
            ->assertOk();
    }

    public function test_view_denies_manage_routes(): void
    {
        $user = $this->userWith('faculty_loading.view');

        $this->actingAs($user)
            ->get(route('faculty-loading.assignments.index'))
            ->assertForbidden();
    }

    public function test_view_denies_approve_routes(): void
    {
        $user = $this->userWith('faculty_loading.view');

        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // faculty_loading.manage — CID / AUH role
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_manage_allows_assignments_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.assignments.index'))
            ->assertOk();
    }

    public function test_manage_allows_schedules_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.schedules.index'))
            ->assertOk();
    }

    public function test_manage_allows_committee_assignments_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.committee-assignments.index'))
            ->assertOk();
    }

    public function test_manage_allows_research_advisories_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.research-advisories.index'))
            ->assertOk();
    }

    public function test_manage_allows_sections_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.sections.index'))
            ->assertOk();
    }

    public function test_manage_denies_overload_computations(): void
    {
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertForbidden();
    }

    public function test_manage_denies_salary_schedules(): void
    {
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.salary-schedules.index'))
            ->assertForbidden();
    }

    public function test_manage_denies_reports(): void
    {
        $user = $this->userWith('faculty_loading.manage');

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.loads'))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // faculty_loading.approve — Campus Director (OCD) role
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_approve_allows_overload_computations_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.approve');

        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertOk();
    }

    public function test_approve_allows_salary_schedules_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.approve');

        $this->actingAs($user)
            ->get(route('faculty-loading.salary-schedules.index'))
            ->assertOk();
    }

    public function test_approve_denies_manage_routes(): void
    {
        $user = $this->userWith('faculty_loading.approve');

        $this->actingAs($user)
            ->get(route('faculty-loading.assignments.index'))
            ->assertForbidden();
    }

    public function test_approve_denies_reports_without_reports_permission(): void
    {
        $user = $this->userWith('faculty_loading.approve');

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.loads'))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // faculty_loading.reports
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_reports_allows_loads_report(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.reports');

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.loads'))
            ->assertOk();
    }

    public function test_reports_allows_overload_pay_report(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.reports');

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.overload-pay'))
            ->assertOk();
    }

    public function test_reports_denies_manage_routes(): void
    {
        $user = $this->userWith('faculty_loading.reports');

        $this->actingAs($user)
            ->get(route('faculty-loading.assignments.index'))
            ->assertForbidden();
    }

    public function test_reports_denies_approve_routes(): void
    {
        $user = $this->userWith('faculty_loading.reports');

        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Catalog permissions
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_subjects_permission_allows_subjects_index(): void
    {
        $user = $this->userWith('faculty_loading.subjects');

        $this->actingAs($user)
            ->get(route('faculty-loading.subjects.index'))
            ->assertOk();
    }

    public function test_subjects_permission_denies_classrooms(): void
    {
        $user = $this->userWith('faculty_loading.subjects');

        $this->actingAs($user)
            ->get(route('faculty-loading.classrooms.index'))
            ->assertForbidden();
    }

    public function test_classrooms_permission_allows_classrooms_index(): void
    {
        $user = $this->userWith('faculty_loading.classrooms');

        $this->actingAs($user)
            ->get(route('faculty-loading.classrooms.index'))
            ->assertOk();
    }

    public function test_school_year_permission_allows_school_years_index(): void
    {
        $this->seedTerm();
        $user = $this->userWith('faculty_loading.school_year');

        $this->actingAs($user)
            ->get(route('faculty-loading.school-years.index'))
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Unauthenticated
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('faculty-loading.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_cannot_access_overload_computations(): void
    {
        $this->get(route('faculty-loading.overload-computations.index'))
            ->assertRedirect(route('login'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Combined roles — OCD (approve + reports + view)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_ocd_role_permissions_work_together(): void
    {
        $this->seedTerm();
        $user = $this->userWith(['faculty_loading.view', 'faculty_loading.approve', 'faculty_loading.reports']);

        $this->actingAs($user)
            ->get(route('faculty-loading.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.loads'))
            ->assertOk();
    }

    public function test_cid_role_permissions_work_together(): void
    {
        $this->seedTerm();
        $user = $this->userWith([
            'faculty_loading.view', 'faculty_loading.manage', 'faculty_loading.reports',
            'faculty_loading.subjects', 'faculty_loading.classrooms', 'faculty_loading.school_year',
        ]);

        $this->actingAs($user)
            ->get(route('faculty-loading.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('faculty-loading.assignments.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('faculty-loading.reports.loads'))
            ->assertOk();

        // CID does not approve
        $this->actingAs($user)
            ->get(route('faculty-loading.overload-computations.index'))
            ->assertForbidden();
    }
}
