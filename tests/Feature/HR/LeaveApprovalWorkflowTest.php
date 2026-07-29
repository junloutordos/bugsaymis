<?php

namespace Tests\Feature\HR;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approver = User::factory()->create(['email' => 'ocd@crc.pshs.edu.ph']);
        $this->approver->roles()->attach($this->rolePermission('hr.leave.approve'));

        $this->leaveType = LeaveType::create([
            'code'                        => 'FL',
            'name'                        => 'Force Leave',
            'days_per_year'               => 5,
            'is_creditable'               => true,
            'is_deductible'               => true,
            'requires_approval'           => false,
            'with_pay'                    => true,
            'applicable_employment_types' => ['permanent'],
            'is_active'                   => true,
        ]);
    }

    public function test_campus_director_cannot_approve_before_division_chief_forwards(): void
    {
        // Application is only at hr_verified — Division Chief has not acted yet.
        $application = $this->makeApplication(['status' => 'hr_verified']);

        $this->actingAs($this->approver)
            ->post(route('hr.leave.approve', $application), [
                'stage'   => 'campus_director',
                'action'  => 'approved',
                'remarks' => '',
            ])
            ->assertStatus(409);

        $application->refresh();
        $this->assertSame('hr_verified', $application->status);
        $this->assertNull($application->approved_by);
        $this->assertNull($application->division_chief_id);
    }

    public function test_applicant_cannot_approve_their_own_leave_application(): void
    {
        // Approver is also the applicant, and the application is legitimately
        // at the forwarded stage — but self-approval must still be blocked.
        $application = $this->makeApplication([
            'status'           => 'forwarded',
            'user_id'          => $this->approver->id,
            'division_chief_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($this->approver)
            ->post(route('hr.leave.approve', $application), [
                'stage'   => 'campus_director',
                'action'  => 'approved',
                'remarks' => '',
            ])
            ->assertStatus(403);

        $application->refresh();
        $this->assertSame('forwarded', $application->status);
        $this->assertNull($application->approved_by);
    }

    public function test_campus_director_can_approve_after_valid_stage_sequence(): void
    {
        $application = $this->makeApplication(['status' => 'forwarded']);

        $this->actingAs($this->approver)
            ->post(route('hr.leave.approve', $application), [
                'stage'   => 'campus_director',
                'action'  => 'approved',
                'remarks' => '',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($this->approver->id, $application->approved_by);
    }

    private function makeApplication(array $overrides = []): LeaveApplication
    {
        $applicant = $overrides['user_id'] ?? null;
        unset($overrides['user_id']);

        return LeaveApplication::create(array_merge([
            'user_id'       => $applicant ?? User::factory()->create()->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from'     => now()->addDays(10)->toDateString(),
            'date_to'       => now()->addDays(10)->toDateString(),
            'dates'         => [now()->addDays(10)->toDateString()],
            'days_applied'  => 1,
            'status'        => 'pending',
        ], $overrides));
    }

    private function rolePermission(string $permissionName): Role
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'HR', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'Leave Test '.uniqid()]);
        $role->permissions()->attach($permission);

        return $role;
    }
}
