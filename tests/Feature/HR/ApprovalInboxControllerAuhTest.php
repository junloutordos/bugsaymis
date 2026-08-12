<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the AUH approval-inbox visibility bug:
 * ApprovalInboxController::isApprover() must recognize Academic Unit
 * Headship (academic_units.head_user_id, current school year) as a valid
 * approver signal, the same way ApprovalInboxService and the shared
 * Inertia "isAUH" prop already do. Without this, GET /inbox 403s for a
 * unit head who holds no other approver role/permission — and, separately,
 * the sidebar "Approvals" link must also list "AUH" in navigation.js so it
 * is rendered at all for these users.
 */
class ApprovalInboxControllerAuhTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnitHead(): User
    {
        $division = Division::create([
            'division_name' => 'Curriculum Implementation Division',
            'acronym'       => 'CID',
            'status'        => 'active',
        ]);

        $schoolYear = SchoolYear::create([
            'name'       => '2025-2026',
            'start_date' => '2025-06-01',
            'end_date'   => '2026-03-31',
            'is_current' => true,
            'status'     => 'active',
        ]);

        // Holds only the "Faculty" role — no "AUH" role (none exists), no
        // other approver permission, not a division chief/OCD/etc.
        $unitHead = User::factory()->create([
            'division_id'  => $division->id,
            'emp_category' => 'Plantilla Teaching',
        ]);
        $unitHead->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'Faculty'])->id);

        AcademicUnit::create([
            'school_year_id' => $schoolYear->id,
            'code'           => 'ENG',
            'name'           => 'English',
            'unit_type'      => 'department',
            'head_user_id'   => $unitHead->id,
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        return $unitHead;
    }

    public function test_academic_unit_head_can_open_approval_inbox_without_auh_role_or_other_permission(): void
    {
        $unitHead = $this->makeUnitHead();

        $this->assertFalse($unitHead->hasRole('AUH'), 'Precondition: no AUH role assigned.');
        $this->assertFalse($unitHead->hasPermission('hr.leave.approve'), 'Precondition: no leave-approve permission.');
        $this->assertFalse($unitHead->hasAnyRole(['Administrator', 'DivisionChief', 'GSU Head', 'OCD', 'FAD Chief']));

        $response = $this->actingAs($unitHead)->get(route('approvals.inbox'));

        $response->assertOk();
    }

    public function test_non_unit_head_faculty_still_gets_403_on_approval_inbox(): void
    {
        $division = Division::create([
            'division_name' => 'Curriculum Implementation Division',
            'acronym'       => 'CID',
            'status'        => 'active',
        ]);

        $teacher = User::factory()->create([
            'division_id'  => $division->id,
            'emp_category' => 'Plantilla Teaching',
        ]);
        $teacher->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'Faculty'])->id);

        $response = $this->actingAs($teacher)->get(route('approvals.inbox'));

        $response->assertForbidden();
    }

    /**
     * Regression test for the second half of the AUH inbox bug: even after
     * the sidebar link and GET /inbox were fixed, POST /inbox/leave_applications/{id}/approve
     * still 403'd for a real unit head with no hr.leave.approve permission
     * and no DivisionChief role, because ApprovalInboxController::authoriseApprove()
     * and resolveLeaveStage() had no Academic Unit Head branch at all —
     * unlike LeaveApplicationController::approve(), which already resolves
     * the academic_unit_head stage by identity.
     */
    public function test_academic_unit_head_can_approve_a_pending_leave_application_from_the_inbox(): void
    {
        $cidChief = User::factory()->create(['emp_category' => 'Plantilla Teaching']);
        $cidChief->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'CID Chief'])->id);

        $cidDivision = Division::create([
            'division_name'     => 'Curriculum Implementation Division',
            'acronym'           => 'CID',
            'division_chief_id' => $cidChief->id,
            'status'            => 'active',
        ]);

        $schoolYear = SchoolYear::create([
            'name'       => '2025-2026',
            'start_date' => '2025-06-01',
            'end_date'   => '2026-03-31',
            'is_current' => true,
            'status'     => 'active',
        ]);

        // Unit head holds only "Faculty" — no "AUH" role, no hr.leave.approve
        // permission, not a Division Chief.
        $unitHead = User::factory()->create([
            'division_id'  => $cidDivision->id,
            'emp_category' => 'Plantilla Teaching',
        ]);
        $unitHead->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'Faculty'])->id);

        $academicUnit = AcademicUnit::create([
            'school_year_id' => $schoolYear->id,
            'code'           => 'ENG',
            'name'           => 'English',
            'unit_type'      => 'department',
            'head_user_id'   => $unitHead->id,
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        $teacher = User::factory()->create([
            'division_id'      => $cidDivision->id,
            'emp_category'     => 'Plantilla Teaching',
            'academic_unit_id' => $academicUnit->id,
        ]);

        $leaveType = LeaveType::create([
            'code'                        => 'VL',
            'name'                        => 'Vacation Leave',
            'days_per_year'               => 15,
            'is_creditable'               => true,
            'is_deductible'               => true,
            'requires_approval'           => false,
            'with_pay'                    => true,
            'applicable_employment_types' => ['permanent'],
            'is_active'                   => true,
        ]);

        $application = LeaveApplication::create([
            'user_id'       => $teacher->id,
            'leave_type_id' => $leaveType->id,
            'date_from'     => now()->addDays(5)->toDateString(),
            'date_to'       => now()->addDays(5)->toDateString(),
            'dates'         => [now()->addDays(5)->toDateString()],
            'days_applied'  => 1,
            'status'        => 'hr_verified',
        ]);

        $this->assertFalse($unitHead->hasRole('AUH'), 'Precondition: no AUH role assigned.');
        $this->assertFalse($unitHead->hasPermission('hr.leave.approve'), 'Precondition: no leave-approve permission.');

        $response = $this->actingAs($unitHead)->post(
            route('approvals.approve', ['type' => 'leave_applications', 'id' => $application->id])
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('auh_verified', $application->fresh()->status);
    }
}
