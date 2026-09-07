<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\PerformanceIndicator;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\IPCRV2\IpcrV2GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IpcrV2GenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(string $successIndicator): WorkDistributionPlan
    {
        $outcome = AgencyOutcome::create(['outcome' => 'x ' . $successIndicator]);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);

        return WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => $successIndicator,
        ]);
    }

    public function test_generates_record_and_snapshots_core_and_support_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 60]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 2', 'weight_percent' => 40]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Committee w/o Load']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertCount(2, $record->coreItems);
        $this->assertCount(1, $record->supportItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
    }

    public function test_throws_when_core_weights_do_not_sum_to_100(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 60]);

        $this->expectException(ValidationException::class);
        (new IpcrV2GenerationService())->generateTargets($user, $period);
    }

    public function test_frozen_label_survives_a_later_employee_function_edit(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $function->update(['label' => 'Renamed Subject']);

        $this->assertSame('Subject 1', $record->fresh()->coreItems->first()->label);
    }

    public function test_sync_adds_functions_created_after_generation(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Placeholder Support']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $this->assertCount(0, $record->coreItems);
        $this->assertCount(1, $record->supportItems);

        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Discipline Committee']);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(2, $added);
        $record->refresh();
        $this->assertCount(1, $record->coreItems);
        $this->assertCount(2, $record->supportItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
    }

    public function test_sync_never_touches_already_snapshotted_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $record->coreItems->first()->update(['target' => 'Existing target text']);
        $function->update(['label' => 'Renamed Subject']);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(0, $added);
        $record->refresh();
        $this->assertCount(1, $record->coreItems);
        $this->assertSame('Subject 1', $record->coreItems->first()->label);
        $this->assertSame('Existing target text', $record->coreItems->first()->target);
    }

    public function test_sync_throws_when_adding_a_core_function_breaks_the_100_percent_total(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 2', 'weight_percent' => 40]);

        $this->expectException(ValidationException::class);
        (new IpcrV2GenerationService())->syncNewFunctions($record);
    }

    public function test_generate_snapshots_success_indicator_from_a_single_tagged_wdp(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $plan = $this->makePlan('Deliver IT support within SLA');
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'wdp', 'label' => 'IT Management', 'weight_percent' => 100]);
        $function->workDistributionPlans()->sync([$plan->id]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertSame('Deliver IT support within SLA', $record->coreItems->first()->success_indicator);
    }

    public function test_generate_materializes_one_support_item_per_tagged_wdp(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $planA = $this->makePlan('Plan A indicator');
        $planB = $this->makePlan('Plan B indicator');
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'wdp', 'label' => 'Administrative']);
        $function->workDistributionPlans()->sync([$planA->id, $planB->id]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertCount(2, $record->supportItems);
        $this->assertEqualsCanonicalizing(
            ['Plan A indicator', 'Plan B indicator'],
            $record->supportItems->pluck('success_indicator')->all()
        );
        $this->assertTrue($record->supportItems->every(fn ($item) => $item->label === 'Administrative'));
    }

    public function test_generate_materializes_one_core_item_per_tagged_wdp_with_weight_split_evenly(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $planA = $this->makePlan('Plan A indicator');
        $planB = $this->makePlan('Plan B indicator');
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'wdp', 'label' => 'IT Management', 'weight_percent' => 100]);
        $function->workDistributionPlans()->sync([$planA->id, $planB->id]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertCount(2, $record->coreItems);
        $this->assertEqualsCanonicalizing(['Plan A indicator', 'Plan B indicator'], $record->coreItems->pluck('success_indicator')->all());
        $this->assertTrue($record->coreItems->every(fn ($item) => (float) $item->weight_percent === 50.0));
    }

    public function test_generate_leaves_success_indicator_null_when_no_wdp_tagged(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Discipline Committee']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertNull($record->supportItems->first()->success_indicator);
    }

    public function test_sync_also_snapshots_success_indicator_for_newly_added_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Placeholder Support']);
        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $plan = $this->makePlan('Discipline Committee indicator');
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'wdp', 'label' => 'Discipline Committee']);
        $function->workDistributionPlans()->sync([$plan->id]);

        (new IpcrV2GenerationService())->syncNewFunctions($record);

        $syncedItem = $record->fresh()->supportItems->firstWhere('label', 'Discipline Committee');
        $this->assertSame('Discipline Committee indicator', $syncedItem->success_indicator);
    }

    public function test_generate_targets_rejects_an_employee_with_no_synced_functions(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);

        $this->expectException(ValidationException::class);
        (new IpcrV2GenerationService())->generateTargets($user, $period);
    }
}
