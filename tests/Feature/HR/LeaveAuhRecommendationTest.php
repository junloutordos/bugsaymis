<?php

namespace Tests\Feature\HR;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CID teaching faculty's leave must be recommended by their Academic Unit
 * Head before the Division Chief (CID Chief). An AUH's own leave is
 * recommended by the CID Chief directly instead, since an AUH cannot
 * recommend themselves. When the CID Chief (role) is the same person as
 * the Division Chief of record, that collapses the workflow to 3 stages —
 * see test_auh_own_leave_skips_recommendation_when_cid_chief_is_division_chief.
 * Everyone else keeps the original 3-stage workflow unchanged.
 */
class LeaveAuhRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Division $cid;
    private User $cidChief;
    private User $auh;
    private AcademicUnit $unit;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2098-2099', 'start_date' => '2098-06-01', 'end_date' => '2099-03-31',
            'is_current' => true, 'status' => 'active',
        ]);

        $this->cidChief = User::factory()->create(['name' => 'Dr. CID Chief']);
        $this->cidChief->roles()->attach($this->rolePermission('hr.leave.approve'));
        $this->cid = Division::create([
            'division_name' => 'Curriculum and Instruction Division',
            'acronym' => 'CID',
            'division_chief_id' => $this->cidChief->id,
            'status' => 'active',
        ]);

        $this->auh = User::factory()->create(['name' => 'Ms. Math Unit Head']);
        $this->unit = AcademicUnit::create([
            'school_year_id' => $this->sy->id,
            'code' => 'MATH',
            'name' => 'Mathematics Unit',
            'unit_type' => 'department',
            'head_user_id' => $this->auh->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
    }

    private function teacher(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'emp_category' => 'Plantilla Teaching',
            'division_id' => $this->cid->id,
            'academic_unit_id' => $this->unit->id,
        ], $overrides));
    }

    private function application(User $applicant, array $overrides = []): LeaveApplication
    {
        return LeaveApplication::create(array_merge([
            'user_id' => $applicant->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from' => now()->addDays(10)->toDateString(),
            'date_to' => now()->addDays(10)->toDateString(),
            'dates' => [now()->addDays(10)->toDateString()],
            'days_applied' => 1,
            'status' => 'pending',
        ], $overrides));
    }

    private function rolePermission(string $permissionName): Role
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'HR', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AuhLeaveTest '.uniqid()]);
        $role->permissions()->attach($permission);

        return $role;
    }

    /** Creates a user holding the ACIDAA designation for the current school year. */
    private function makeAcidaaUser(): User
    {
        $term = AcademicTerm::firstOrCreate(
            ['school_year_id' => $this->sy->id, 'is_current' => true],
            ['name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2098-06-01', 'end_date' => '2098-10-31'],
        );
        $category = DesignationCategory::firstOrCreate(
            ['code' => 'SUPV'],
            ['name' => 'Supervisory', 'is_active' => true],
        );
        $designation = Designation::create([
            'designation_category_id' => $category->id,
            'code' => 'ACIDAA',
            'name' => 'Assistant CID Chief for Academic Affairs',
            'load_units' => 9, 'assignment_type' => 'admin', 'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Mr. ACIDAA']);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'admin', 'load_units' => 9,
            'description' => $designation->name, 'designation_id' => $designation->id,
        ]);

        return $user;
    }

    public function test_full_workflow_hr_then_auh_then_division_chief_then_campus_director(): void
    {
        $applicant = $this->teacher(['name' => 'Juan Teacher']);
        $application = $this->application($applicant);

        $hrOfficer = User::factory()->create();
        $hrOfficer->roles()->attach($this->rolePermission('hr.leave.approve'));

        $ocd = User::factory()->create(['name' => 'Campus Director']);
        Division::create([
            'division_name' => 'Office of the Campus Director', 'acronym' => 'OCD',
            'division_chief_id' => $ocd->id, 'status' => 'active',
        ]);

        // Stage 1: HR Officer certifies
        $this->actingAs($hrOfficer)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'hr_officer', 'action' => 'certified', 'remarks' => '',
            ])->assertRedirect();
        $application->refresh();
        $this->assertSame('hr_verified', $application->status);

        // Division Chief cannot act yet — AUH hasn't recommended
        $this->actingAs($this->cidChief)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'division_chief', 'action' => 'forwarded', 'remarks' => '',
            ])->assertStatus(409);

        // Stage 2: Academic Unit Head recommends
        $this->actingAs($this->auh)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'academic_unit_head', 'action' => 'recommended', 'remarks' => '',
            ])->assertRedirect();
        $application->refresh();
        $this->assertSame('auh_verified', $application->status);
        $this->assertSame($this->auh->id, $application->auh_id);
        $this->assertSame('recommended', $application->auh_action);

        // Stage 3: Division Chief forwards
        $this->actingAs($this->cidChief)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'division_chief', 'action' => 'forwarded', 'remarks' => '',
            ])->assertRedirect();
        $application->refresh();
        $this->assertSame('forwarded', $application->status);

        // Stage 4: Campus Director approves
        $campusDirectorApprover = User::factory()->create();
        $campusDirectorApprover->roles()->attach($this->rolePermission('hr.leave.approve'));
        $this->actingAs($campusDirectorApprover)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'campus_director', 'action' => 'approved', 'remarks' => '',
            ])->assertRedirect();
        $application->refresh();
        $this->assertSame('approved', $application->status);
    }

    public function test_auh_rejection_sets_status_rejected(): void
    {
        $applicant = $this->teacher();
        $application = $this->application($applicant, ['status' => 'hr_verified']);

        $this->actingAs($this->auh)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'academic_unit_head', 'action' => 'rejected', 'remarks' => 'Not enough coverage',
            ])->assertRedirect();

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('rejected', $application->auh_action);
    }

    public function test_only_the_resolved_academic_unit_head_can_recommend(): void
    {
        $applicant = $this->teacher();
        $application = $this->application($applicant, ['status' => 'hr_verified']);

        $wrongAuh = User::factory()->create();

        $this->actingAs($wrongAuh)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'academic_unit_head', 'action' => 'recommended', 'remarks' => '',
            ])->assertStatus(403);

        $application->refresh();
        $this->assertSame('hr_verified', $application->status);
        $this->assertNull($application->auh_id);
    }

    public function test_non_cid_teaching_applicant_skips_auh_stage(): void
    {
        // Plantilla Non-Teaching, still in CID — must NOT require AUH routing.
        $staff = User::factory()->create([
            'emp_category' => 'Plantilla Non-Teaching',
            'division_id' => $this->cid->id,
        ]);
        $application = $this->application($staff, ['status' => 'hr_verified']);

        // Division Chief can forward directly — no 409, no AUH stage in the way.
        $this->actingAs($this->cidChief)
            ->post(route('hr.leave.approve', $application), [
                'stage' => 'division_chief', 'action' => 'forwarded', 'remarks' => '',
            ])->assertRedirect();

        $application->refresh();
        $this->assertSame('forwarded', $application->status);
    }

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

    public function test_academic_unit_head_inbox_visibility(): void
    {
        $applicant = $this->teacher();
        $application = $this->application($applicant, ['status' => 'hr_verified']);

        $auhRole = Role::create(['name' => 'AUH']);
        $this->auh->roles()->attach($auhRole->id);

        $dcRole = Role::create(['name' => 'DivisionChief']);
        $this->cidChief->roles()->attach($dcRole->id);

        $inboxItems = (new ApprovalInboxService($this->auh->fresh()))->getPendingItems();
        $leaveTab = collect($inboxItems)->firstWhere('type', 'leave_applications');
        $this->assertNotNull($leaveTab, 'AUH should see the leave application awaiting their recommendation.');
        $this->assertTrue(collect($leaveTab['items'])->contains('id', $application->id));

        // Division Chief must NOT see it yet — AUH hasn't recommended.
        $dcInboxItems = (new ApprovalInboxService($this->cidChief->fresh()))->getPendingItems();
        $dcLeaveTab = collect($dcInboxItems)->firstWhere('type', 'leave_applications');
        if ($dcLeaveTab) {
            $this->assertFalse(collect($dcLeaveTab['items'])->contains('id', $application->id));
        } else {
            $this->assertNull($dcLeaveTab);
        }
    }
}
