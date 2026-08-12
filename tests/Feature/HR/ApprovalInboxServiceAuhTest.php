<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\ApprovalInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the AUH inbox-visibility bug: the "AUH" RBAC role
 * does not exist in this system — headship is resolved from
 * academic_units.head_user_id (see IPCRWorkflowService::leaveRecommenderFor
 * and ApprovalInboxService::holdsAcademicUnitHeadship). The inbox tab must
 * appear for the actual unit head even though they hold no "AUH" role.
 */
class ApprovalInboxServiceAuhTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_unit_head_sees_pending_leave_application_without_auh_role(): void
    {
        $cidDivision = Division::create([
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

        // Unit head holds only the "Faculty" role — no "AUH" role exists.
        $unitHead = User::factory()->create([
            'division_id'   => $cidDivision->id,
            'emp_category'  => 'Plantilla Teaching',
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

        $tabs = (new ApprovalInboxService($unitHead))->getPendingItems();

        $leaveTab = collect($tabs)->firstWhere('type', 'leave_applications');

        $this->assertNotNull($leaveTab, 'Academic Unit Head should see a Leave Applications tab.');
        $this->assertSame(1, $leaveTab['count']);
        $this->assertSame($application->id, $leaveTab['items'][0]['id']);
    }
}
