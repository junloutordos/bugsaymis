<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\SchoolYear;
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
}
