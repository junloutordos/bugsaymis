<?php

namespace Tests\Unit\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Opcr;
use App\Models\User;
use App\Services\SPMS\OPCRWorkflowService;
use App\Services\SPMS\SPMSRollupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OPCRWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private OPCRWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OPCRWorkflowService(new SPMSRollupService());
    }

    private function executiveDirector(): User
    {
        $role = Role::create(['name' => 'Executive Director']);
        $approve = Permission::create(['name' => 'spms.opcr.approve', 'module' => 'SPMS']);
        $role->permissions()->attach($approve->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_submit_to_ed_transitions_draft(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_DRAFT]);

        $result = $this->service->submitToExecutiveDirector($opcr, $opcr->ratee);

        $this->assertSame(Opcr::STATUS_SUBMITTED_TO_ED, $result->status);
        $this->assertNotNull($result->submitted_to_ed_at);
        $this->assertNull($result->rolled_up_rating); // no approved DPCRs in this minimal test — computed but empty
    }

    public function test_only_ratee_can_submit_to_ed(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_DRAFT]);
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->submitToExecutiveDirector($opcr, $other);
    }

    public function test_submit_rejects_wrong_status(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_ED_APPROVED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitToExecutiveDirector($opcr, $opcr->ratee);
    }

    public function test_only_approve_permission_can_approve(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED]);
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->approve($opcr, $other);
    }

    public function test_set_override_is_allowed_for_ratee_or_approver(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED]);

        $result = $this->service->setOverride($opcr, $opcr->ratee, 4.5, 'Adjusted for verified campus-wide accomplishment');

        $this->assertEqualsWithDelta(4.5, (float) $result->override_rating, 0.001);
        $this->assertSame('Adjusted for verified campus-wide accomplishment', $result->override_reason);
    }

    public function test_approve_uses_override_when_present(): void
    {
        $opcr = Opcr::factory()->create([
            'status' => Opcr::STATUS_SUBMITTED_TO_ED,
            'rolled_up_rating' => 3.0,
            'override_rating' => 4.75,
        ]);
        $ed = $this->executiveDirector();

        $result = $this->service->approve($opcr, $ed);

        $this->assertSame(Opcr::STATUS_ED_APPROVED, $result->status);
        $this->assertEqualsWithDelta(4.75, (float) $result->final_rating, 0.001);
        $this->assertSame('Outstanding', $result->final_adjectival);
        $this->assertSame($ed->id, $result->approved_by);
    }

    public function test_approve_falls_back_to_rolled_up_rating_without_override(): void
    {
        $opcr = Opcr::factory()->create([
            'status' => Opcr::STATUS_SUBMITTED_TO_ED,
            'rolled_up_rating' => 3.6,
        ]);
        $ed = $this->executiveDirector();

        $result = $this->service->approve($opcr, $ed);

        $this->assertEqualsWithDelta(3.6, (float) $result->final_rating, 0.001);
        $this->assertSame('Very Satisfactory', $result->final_adjectival);
    }

    public function test_ed_approved_opcr_is_terminal(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED, 'rolled_up_rating' => 3.0]);
        $ed = $this->executiveDirector();
        $result = $this->service->approve($opcr, $ed);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitToExecutiveDirector($result, $result->ratee);
    }

    public function test_return_to_sender_sets_reason_and_status(): void
    {
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED]);
        $ed = $this->executiveDirector();

        $result = $this->service->returnToSender($opcr, $ed, 'Q4 actuals incomplete');

        $this->assertSame(Opcr::STATUS_RETURNED, $result->status);
        $this->assertSame('Q4 actuals incomplete', $result->return_reason);
    }
}
