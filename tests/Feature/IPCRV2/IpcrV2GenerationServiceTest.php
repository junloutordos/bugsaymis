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

    /**
     * Regression: deleting an EmployeeFunction nulls employee_function_id
     * on its already-materialized item (nullOnDelete — deliberately never
     * cascade-deletes, to protect anything already rated) but leaves the
     * item itself in place. EmployeeFunction's own `deleted` model event
     * (EmployeeFunction::booted()) prunes that orphan immediately on
     * delete — syncNewFunctions()'s own pruneOrphanedItems() is a second,
     * additional safety net for anything that slipped through before
     * that event existed, so by the time sync runs there's already
     * nothing left for IT to prune here.
     */
    public function test_sync_prunes_an_unrated_item_whose_function_was_deleted(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Grievance Committee']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $this->assertCount(1, $record->supportItems);

        $function->delete(); // EmployeeFunction's own deleted event prunes the orphan immediately
        $this->assertCount(0, $record->fresh()->supportItems);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(0, $added); // already pruned — nothing left for syncNewFunctions to do
        $this->assertCount(0, $record->fresh()->supportItems);
    }

    /** The same orphan is left alone (never pruned) once it has a real accomplishment logged against it. */
    public function test_sync_does_not_prune_an_orphaned_item_that_already_has_an_accomplishment(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $function = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Grievance Committee']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $record->supportItems->first()->update(['actual_accomplishment' => 'Attended sessions.']);

        $function->delete();
        (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertCount(1, $record->fresh()->supportItems);
    }

    /**
     * pruneOrphanedItems() itself is a safety net for rows already
     * orphaned BEFORE EmployeeFunction's own `deleted` event existed
     * (e.g. any left over from before this fix shipped) — simulate that
     * directly (employee_function_id already null on the row, no
     * EmployeeFunction ever deleted in this test) rather than through
     * ->delete(), which the model event now handles on its own.
     */
    public function test_sync_prunes_a_pre_existing_orphaned_item_with_a_null_employee_function_id(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $placeholder = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Placeholder']);
        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $record->supportItems()->create(['label' => 'Orphaned Row', 'employee_function_id' => null]);
        $this->assertCount(2, $record->fresh()->supportItems);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(1, $added);
        $this->assertCount(1, $record->fresh()->supportItems);
        $this->assertSame('Placeholder', $record->fresh()->supportItems->first()->label);
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

    /**
     * Regression: adding a load-bearing committee assignment re-normalizes
     * EVERY core EmployeeFunction's weight_percent against the new total
     * unit pool (EmployeeFunctionSyncService::syncFromFacultyLoading) so
     * they still sum to 100% — but syncNewFunctions() must pick up that
     * re-normalized weight for the ALREADY-MATERIALIZED core item too, not
     * just the new function, or the 100% check compares a stale pre-resync
     * snapshot against a freshly-normalized new value and false-positives
     * well past 100% (reported bug: "weights sum to 200%").
     */
    public function test_sync_does_not_false_positive_when_an_existing_functions_weight_was_re_normalized(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $teaching = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Teaching Load', 'weight_percent' => 100]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $this->assertSame(100.0, (float) $record->coreItems->first()->weight_percent);

        // Simulate what EmployeeFunctionSyncService actually does when a
        // new load-bearing committee assignment is added: it re-normalizes
        // EVERY core function's weight against the new combined unit pool,
        // not just inserts the new one — teaching load's share shrinks to
        // make room, both still sum to 100%.
        $teaching->update(['weight_percent' => 60]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Grievance Committee', 'weight_percent' => 40]);

        $added = (new IpcrV2GenerationService())->syncNewFunctions($record);

        $this->assertSame(1, $added);
        $record->refresh();
        $this->assertSame(60.0, (float) $record->coreItems->firstWhere('label', 'Teaching Load')->weight_percent);
        $this->assertSame(40.0, (float) $record->coreItems->firstWhere('label', 'Grievance Committee')->weight_percent);
    }

    /** Multi-plan existing function: the live weight must split evenly across its own already-materialized items, not overwrite each with the full new weight. */
    public function test_sync_refresh_splits_a_re_normalized_weight_evenly_across_an_existing_functions_multiple_items(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $planA = $this->makePlan('Plan A indicator');
        $planB = $this->makePlan('Plan B indicator');
        $itMgmt = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'wdp', 'label' => 'IT Management', 'weight_percent' => 100]);
        $itMgmt->workDistributionPlans()->sync([$planA->id, $planB->id]);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);
        $this->assertCount(2, $record->coreItems);
        $this->assertTrue($record->coreItems->every(fn ($item) => (float) $item->weight_percent === 50.0));

        $itMgmt->update(['weight_percent' => 60]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Grievance Committee', 'weight_percent' => 40]);

        (new IpcrV2GenerationService())->syncNewFunctions($record);

        $record->refresh();
        $itItems = $record->coreItems->where('label', 'IT Management');
        $this->assertCount(2, $itItems);
        $this->assertTrue($itItems->every(fn ($item) => (float) $item->weight_percent === 30.0));
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

    public function test_generated_items_start_with_no_self_rating(): void
    {
        $user = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Discipline Committee']);

        $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

        $this->assertNull($record->coreItems->first()->self_row_average);
        $this->assertNull($record->supportItems->first()->self_row_average);
    }
}
