<?php

namespace Tests\Feature;

use App\Models\IPCRWeightDistribution;
use App\Models\PM2\EmployeeIpcrPlanV2;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\User;
use App\Services\PerformanceManagementV2\IpcrWorkflowServiceV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IpcrWorkflowServiceV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_transition_rejects_a_skipped_status(): void
    {
        $service = app(IpcrWorkflowServiceV2::class);
        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => User::factory()->create()->id,
            'rating_period_id' => $period->id,
            'title' => 'Test',
            'status' => IpcrWorkflowServiceV2::STATUS_NEW_TARGET,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->transition($ipcr, IpcrWorkflowServiceV2::STATUS_RATED);
    }

    public function test_weight_targets_default_to_30_50_20_without_a_division_row(): void
    {
        $service = app(IpcrWorkflowServiceV2::class);

        $this->assertEquals(
            ['strategic' => 30.0, 'core' => 50.0, 'support' => 20.0],
            $service->weightTargets(999999)
        );
    }

    public function test_weight_targets_use_the_division_row_when_present(): void
    {
        $service = app(IpcrWorkflowServiceV2::class);
        $division = \App\Models\Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        IPCRWeightDistribution::create(['division_id' => $division->id, 'strategic' => 25, 'core' => 55, 'support' => 20]);

        $this->assertEquals(
            ['strategic' => 25.0, 'core' => 55.0, 'support' => 20.0],
            $service->weightTargets($division->id)
        );
    }

    public function test_assert_weights_valid_throws_when_a_group_does_not_sum_to_target(): void
    {
        $service = app(IpcrWorkflowServiceV2::class);
        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $employee = User::factory()->create();
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $employee->id,
            'rating_period_id' => $period->id,
            'title' => 'Test',
            'status' => IpcrWorkflowServiceV2::STATUS_NEW_TARGET,
        ]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'weight_percent' => 40]);

        $this->expectException(ValidationException::class);
        $service->assertWeightsValid($ipcr);
    }
}
