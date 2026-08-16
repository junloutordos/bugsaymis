<?php

namespace Tests\Unit\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Dpcr;
use App\Models\User;
use App\Services\SPMS\DPCRWorkflowService;
use App\Services\SPMS\SPMSRollupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DPCRWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private DPCRWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DPCRWorkflowService(new SPMSRollupService());
    }

    private function reviewerAndApprover(): User
    {
        $role = Role::create(['name' => 'OCDTest']);
        $review = Permission::create(['name' => 'spms.dpcr.review', 'module' => 'SPMS']);
        $approve = Permission::create(['name' => 'spms.dpcr.approve', 'module' => 'SPMS']);
        $role->permissions()->attach([$review->id, $approve->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_submit_to_reviewer_transitions_draft(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_DRAFT]);

        $result = $this->service->submitToReviewer($dpcr, $dpcr->ratee);

        $this->assertSame(Dpcr::STATUS_SUBMITTED_TO_REVIEWER, $result->status);
        $this->assertNotNull($result->submitted_to_reviewer_at);
    }

    public function test_only_ratee_can_submit_to_reviewer(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_DRAFT]);
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->submitToReviewer($dpcr, $other);
    }

    public function test_submit_target_rejects_wrong_status(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_APPROVED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitToReviewer($dpcr, $dpcr->ratee);
    }

    public function test_review_computes_rolled_up_rating_and_transitions(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_REVIEWER]);
        $reviewer = $this->reviewerAndApprover();

        $result = $this->service->review($dpcr, $reviewer);

        $this->assertSame(Dpcr::STATUS_REVIEWED, $result->status);
        $this->assertNotNull($result->reviewed_at);
        $this->assertNull($result->rolled_up_rating); // no rated IPCRs in this division/period yet
    }

    public function test_only_reviewer_permission_can_review(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_REVIEWER]);
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->review($dpcr, $other);
    }

    public function test_set_override_is_allowed_for_ratee_or_reviewer(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_REVIEWED]);

        $result = $this->service->setOverride($dpcr, $dpcr->ratee, 4.5, 'Manual adjustment for verified extra load');

        $this->assertEqualsWithDelta(4.5, (float) $result->override_rating, 0.001);
        $this->assertSame('Manual adjustment for verified extra load', $result->override_reason);
    }

    public function test_approve_uses_override_when_present(): void
    {
        $dpcr = Dpcr::factory()->create([
            'status' => Dpcr::STATUS_SUBMITTED_TO_APPROVER,
            'rolled_up_rating' => 3.0,
            'override_rating' => 4.75,
        ]);
        $approver = $this->reviewerAndApprover();

        $result = $this->service->approve($dpcr, $approver);

        $this->assertSame(Dpcr::STATUS_APPROVED, $result->status);
        $this->assertEqualsWithDelta(4.75, (float) $result->final_rating, 0.001);
        $this->assertSame('Outstanding', $result->final_adjectival);
    }

    public function test_approve_falls_back_to_rolled_up_rating_without_override(): void
    {
        $dpcr = Dpcr::factory()->create([
            'status' => Dpcr::STATUS_SUBMITTED_TO_APPROVER,
            'rolled_up_rating' => 3.6,
        ]);
        $approver = $this->reviewerAndApprover();

        $result = $this->service->approve($dpcr, $approver);

        $this->assertEqualsWithDelta(3.6, (float) $result->final_rating, 0.001);
        $this->assertSame('Very Satisfactory', $result->final_adjectival);
    }

    public function test_approved_dpcr_is_terminal(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_APPROVER, 'rolled_up_rating' => 3.0]);
        $approver = $this->reviewerAndApprover();
        $result = $this->service->approve($dpcr, $approver);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitToReviewer($result, $result->ratee);
    }

    public function test_return_to_sender_sets_reason_and_status(): void
    {
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_REVIEWER]);
        $reviewer = $this->reviewerAndApprover();

        $result = $this->service->returnToSender($dpcr, $reviewer, 'Missing Q2 actuals');

        $this->assertSame(Dpcr::STATUS_RETURNED, $result->status);
        $this->assertSame('Missing Q2 actuals', $result->return_reason);
    }
}
