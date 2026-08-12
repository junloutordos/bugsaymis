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

    /**
     * Regression test for the silent-signature-failure bug: previously, a
     * wrong PIN on a signing action (certified/recommended/forwarded/
     * approved) still let the stage transition commit — the wrong PIN was
     * only reflected in a missing DigitalSignature record, discovered later
     * at print time. approve() now verifies the PIN BEFORE calling
     * processLeave(), so a bad PIN blocks the transition entirely.
     */
    public function test_wrong_signature_pin_blocks_the_stage_transition(): void
    {
        $this->approver->update(['signature_pin' => bcrypt('123456')]);
        $application = $this->makeApplication(['status' => 'forwarded']);

        $response = $this->actingAs($this->approver)
            ->post(route('hr.leave.approve', $application), [
                'stage'   => 'campus_director',
                'action'  => 'approved',
                'remarks' => '',
                'pin'     => '999999',
            ]);

        $response->assertSessionHasErrors('pin');

        $application->refresh();
        $this->assertSame('forwarded', $application->status, 'Status must not advance on a wrong PIN.');
        $this->assertNull($application->approved_by);

        $this->assertDatabaseCount('digital_signatures', 0);
    }

    /**
     * Correct PIN still signs and advances the stage exactly as before —
     * proves the new pre-flight check isn't a regression for the happy path.
     */
    public function test_correct_signature_pin_signs_and_advances_the_stage(): void
    {
        $this->approver->update(['signature_pin' => bcrypt('123456')]);
        $application = $this->makeApplication(['status' => 'forwarded']);

        $response = $this->actingAs($this->approver)
            ->post(route('hr.leave.approve', $application), [
                'stage'   => 'campus_director',
                'action'  => 'approved',
                'remarks' => '',
                'pin'     => '123456',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($this->approver->id, $application->approved_by);

        $this->assertDatabaseCount('digital_signatures', 1);
        $this->assertDatabaseHas('digital_signatures', [
            'signable_type' => LeaveApplication::class,
            'signable_id'   => $application->id,
            'signer_id'     => $this->approver->id,
        ]);
    }

    /**
     * Regression test for the "Re-sign" affordance: an application that
     * already advanced (hr_officer_id set, status moved on) but has no
     * matching DigitalSignature — the exact gap the historical bug left
     * behind — can have its missing signature filled in via resign()
     * without re-running the approval workflow.
     */
    public function test_resign_fills_in_a_missing_signature_without_rerunning_the_workflow(): void
    {
        $hrOfficer = User::factory()->create();
        $hrOfficer->roles()->attach($this->rolePermission('hr.leave.approve'));
        $hrOfficer->update(['signature_pin' => bcrypt('654321')]);

        // Simulate the historical bug: hr_officer_action recorded, status
        // advanced, but no DigitalSignature row exists for that stage.
        $application = $this->makeApplication([
            'status'            => 'hr_verified',
            'hr_officer_id'     => $hrOfficer->id,
            'hr_officer_action' => 'certified',
            'hr_officer_at'     => now(),
        ]);

        $this->assertDatabaseCount('digital_signatures', 0);

        $response = $this->actingAs($hrOfficer)
            ->post(route('hr.leave.resign', $application), [
                'stage' => 'hr_officer',
                'pin'   => '654321',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('hr_verified', $application->status, 'resign() must not touch the workflow status.');

        $this->assertDatabaseCount('digital_signatures', 1);
        $this->assertDatabaseHas('digital_signatures', [
            'signable_type' => LeaveApplication::class,
            'signable_id'   => $application->id,
            'signer_id'     => $hrOfficer->id,
        ]);
    }

    public function test_resign_rejects_someone_other_than_the_original_signer(): void
    {
        $hrOfficer  = User::factory()->create();
        $someoneElse = User::factory()->create();
        $someoneElse->roles()->attach($this->rolePermission('hr.leave.approve'));

        $application = $this->makeApplication([
            'status'            => 'hr_verified',
            'hr_officer_id'     => $hrOfficer->id,
            'hr_officer_action' => 'certified',
            'hr_officer_at'     => now(),
        ]);

        $this->actingAs($someoneElse)
            ->post(route('hr.leave.resign', $application), [
                'stage' => 'hr_officer',
                'pin'   => null,
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('digital_signatures', 0);
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
