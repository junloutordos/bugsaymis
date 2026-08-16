<?php

namespace Tests\Unit\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use App\Services\SPMS\IPCRWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private IPCRWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IPCRWorkflowService();
    }

    public function test_submit_target_transitions_draft_to_submitted(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_DRAFT_TARGET]);

        $result = $this->service->submitTarget($ipcr, $ipcr->user);

        $this->assertSame(Ipcr::STATUS_TARGET_SUBMITTED, $result->status);
        $this->assertNotNull($result->target_submitted_at);
    }

    public function test_submit_target_rejects_wrong_status(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_RATED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitTarget($ipcr, $ipcr->user);
    }

    public function test_only_owner_can_submit_target(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_DRAFT_TARGET]);
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->submitTarget($ipcr, $other);
    }

    public function test_compute_weighted_average_applies_default_30_50_20(): void
    {
        $ipcr = Ipcr::factory()->create();
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'strategic', 'rating_avg' => 5.0]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'support', 'rating_avg' => 3.0]);

        // 5*0.30 + 4*0.50 + 3*0.20 = 1.5 + 2.0 + 0.6 = 4.1
        $this->assertEqualsWithDelta(4.1, $this->service->computeWeightedAverage($ipcr->fresh()), 0.001);
    }

    public function test_adjectival_rating_bands(): void
    {
        $this->assertSame('Outstanding', $this->service->adjectivalRating(4.51));
        $this->assertSame('Very Satisfactory', $this->service->adjectivalRating(3.51));
        $this->assertSame('Satisfactory', $this->service->adjectivalRating(2.51));
        $this->assertSame('Unsatisfactory', $this->service->adjectivalRating(1.51));
        $this->assertSame('Poor', $this->service->adjectivalRating(1.0));
    }

    public function test_finalize_is_terminal_and_immutable(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_PMT_HR_REVIEWED]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0]);

        $role = Role::create(['name' => 'DirectorTest']);
        $permission = Permission::create(['name' => 'spms.ipcr.review', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $director = User::factory()->create();
        $director->roles()->attach($role->id);

        $result = $this->service->finalize($ipcr, $director);

        $this->assertSame(Ipcr::STATUS_DIRECTOR_SIGNED, $result->status);
        $this->assertNotNull($result->final_rating);
        $this->assertNotNull($result->final_adjectival);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitTarget($result, $result->user);
    }
}
