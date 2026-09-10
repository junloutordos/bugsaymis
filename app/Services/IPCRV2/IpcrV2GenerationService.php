<?php

namespace App\Services\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IpcrV2GenerationService
{
    public function __construct(
        private IpcrV2WorkflowService $workflow = new IpcrV2WorkflowService()
    ) {}

    public function generateTargets(User $user, IPCRRatingPeriod $period): IpcrV2Record
    {
        $this->workflow->assertPeriodAcceptsNewTargets($period);
        $this->workflow->assertNoDuplicateForPeriod($user->id, $period->id);

        $coreFunctions = EmployeeFunction::where('user_id', $user->id)->core()->with('workDistributionPlans:id,success_indicator')->get();
        $supportFunctions = EmployeeFunction::where('user_id', $user->id)->support()->with('workDistributionPlans:id,success_indicator')->get();

        if ($coreFunctions->isEmpty() && $supportFunctions->isEmpty()) {
            throw ValidationException::withMessages([
                'employee_function' => 'No functions are synced for this employee yet — sync Employee Functions first.',
            ]);
        }

        $this->assertCoreWeightsSumTo100($coreFunctions);

        return DB::transaction(function () use ($user, $period, $coreFunctions, $supportFunctions) {
            $record = IpcrV2Record::create([
                'user_id' => $user->id,
                'rating_period_id' => $period->id,
            ]);

            foreach ($coreFunctions as $function) {
                $this->createCoreItemsForFunction($record, $function);
            }

            foreach ($supportFunctions as $function) {
                $this->createSupportItemsForFunction($record, $function);
            }

            return $record->fresh(['coreItems', 'supportItems']);
        });
    }

    /**
     * Add any core/support EmployeeFunction rows created after the record was
     * generated (or last synced) as new items — never touches items that
     * already exist, so frozen labels and any target/actual_accomplishment
     * already filled in are left untouched.
     */
    public function syncNewFunctions(IpcrV2Record $record): int
    {
        $pruned = $this->pruneOrphanedItems($record);

        $record->loadMissing(['coreItems', 'supportItems']);

        $existingCoreFunctionIds = $record->coreItems->pluck('employee_function_id')->all();
        $existingSupportFunctionIds = $record->supportItems->pluck('employee_function_id')->all();

        $newCoreFunctions = EmployeeFunction::where('user_id', $record->user_id)->core()
            ->whereNotIn('id', $existingCoreFunctionIds)->with('workDistributionPlans:id,success_indicator')->get();
        $newSupportFunctions = EmployeeFunction::where('user_id', $record->user_id)->support()
            ->whereNotIn('id', $existingSupportFunctionIds)->with('workDistributionPlans:id,success_indicator')->get();

        if ($newCoreFunctions->isEmpty() && $newSupportFunctions->isEmpty()) {
            return $pruned;
        }

        // Existing core items' weight_percent is a snapshot taken at
        // generation/last-sync time — EmployeeFunctionSyncService
        // re-normalizes EVERY core EmployeeFunction's weight against the
        // user's current total unit pool whenever it runs (e.g. adding a
        // new load-bearing committee assignment shrinks teaching load's
        // share to make room), but that re-normalization never propagates
        // to already-materialized ipcr_v2_core_items rows on its own.
        // Refresh them from their live EmployeeFunction here, BEFORE
        // summing for the 100% check below — otherwise this check compares
        // stale pre-resync weights against the freshly-computed new
        // function(s) and false-positives well past 100%.
        if ($newCoreFunctions->isNotEmpty() && $record->coreItems->isNotEmpty()) {
            $liveWeightByFunctionId = EmployeeFunction::whereIn('id', $record->coreItems->pluck('employee_function_id'))
                ->pluck('weight_percent', 'id');

            // A function tagged to N plans materialized into N items with
            // the function's weight split evenly (createCoreItemsForFunction)
            // — mirror that same split here, keyed by how many of THIS
            // function's items already exist on the record, not the
            // function's raw weight_percent alone.
            foreach ($record->coreItems->groupBy('employee_function_id') as $functionId => $items) {
                $liveWeight = $liveWeightByFunctionId->get($functionId);
                if ($liveWeight === null) {
                    continue;
                }

                $splitWeight = round((float) $liveWeight / $items->count(), 2);
                foreach ($items as $item) {
                    if ((float) $splitWeight !== (float) $item->weight_percent) {
                        $item->update(['weight_percent' => $splitWeight]);
                    }
                }
            }

            $record->unsetRelation('coreItems');
            $record->load('coreItems');
        }

        if ($newCoreFunctions->isNotEmpty()) {
            $totalWeight = (float) $record->coreItems->sum('weight_percent') + (float) $newCoreFunctions->sum('weight_percent');
            if (abs($totalWeight - 100) > 0.5) {
                throw ValidationException::withMessages([
                    'weight_percent' => "Adding these Core Function(s) would make the weights sum to {$totalWeight}%, not 100%. Fix the weights on the Employee Functions tab before syncing.",
                ]);
            }
        }

        return DB::transaction(function () use ($record, $newCoreFunctions, $newSupportFunctions, $pruned) {
            foreach ($newCoreFunctions as $function) {
                $this->createCoreItemsForFunction($record, $function);
            }

            foreach ($newSupportFunctions as $function) {
                $this->createSupportItemsForFunction($record, $function);
            }

            return $pruned + $newCoreFunctions->count() + $newSupportFunctions->count();
        });
    }

    /**
     * Removes core/support items whose backing EmployeeFunction was
     * deleted (employee_function_id nulled by the FK's nullOnDelete —
     * EmployeeFunctionController::destroy() and committee-cascade cleanup
     * both leave the item itself in place, on purpose, for anything
     * already rated). Only prunes when the record is still mutable AND
     * the item has no real accomplishment data logged yet — never
     * silently drops rated/logged work, matching the same rule
     * EmployeeFunctionSyncService's own cleanup already follows. Called
     * at the start of every syncNewFunctions() run so "Sync from Employee
     * Functions" both adds new functions and removes stale orphans in one
     * action, instead of only ever adding.
     */
    private function pruneOrphanedItems(IpcrV2Record $record): int
    {
        if (! $record->isMutable()) {
            return 0;
        }

        $prunedCore = $record->coreItems()
            ->whereNull('employee_function_id')
            ->where(fn ($q) => $q->whereNull('actual_accomplishment')->orWhere('actual_accomplishment', ''))
            ->delete();

        $prunedSupport = $record->supportItems()
            ->whereNull('employee_function_id')
            ->where(fn ($q) => $q->whereNull('actual_accomplishment')->orWhere('actual_accomplishment', ''))
            ->delete();

        return $prunedCore + $prunedSupport;
    }

    /**
     * A function tagged to N Work Distribution Plans materializes into N
     * independently-ratable items — one per plan, each with that plan's own
     * success_indicator and its own Target/Actual Accomplishment/ratings —
     * rather than one item with every plan's text joined together. Weight
     * is split evenly across the N items so the sum of their weights still
     * equals the function's own weight (IpcrV2RatingService's weighted
     * average is otherwise skewed — duplicating the full weight on every
     * materialized row would overweight a multi-tagged function relative
     * to others). An untagged function still gets exactly one item, which
     * IpcrV2CoreItemsTable renders with the fixed CSC teaching rubric.
     */
    private function createCoreItemsForFunction(IpcrV2Record $record, EmployeeFunction $function): void
    {
        $plans = $function->workDistributionPlans;

        if ($plans->isEmpty()) {
            $record->coreItems()->create([
                'employee_function_id' => $function->id,
                'label' => $function->label,
                'output_outcome' => $function->output_outcome,
                'weight_percent' => $function->weight_percent,
            ]);

            return;
        }

        $splitWeight = $function->weight_percent !== null
            ? round((float) $function->weight_percent / $plans->count(), 2)
            : null;

        foreach ($plans as $plan) {
            $record->coreItems()->create([
                'employee_function_id' => $function->id,
                'label' => $function->label,
                'output_outcome' => $function->output_outcome,
                'weight_percent' => $splitWeight,
                'success_indicator' => $plan->success_indicator,
            ]);
        }
    }

    /**
     * Same one-item-per-tagged-plan materialization as core items, minus
     * weight (Support Functions carry none).
     */
    private function createSupportItemsForFunction(IpcrV2Record $record, EmployeeFunction $function): void
    {
        $plans = $function->workDistributionPlans;

        if ($plans->isEmpty()) {
            $record->supportItems()->create([
                'employee_function_id' => $function->id,
                'label' => $function->label,
                'output_outcome' => $function->output_outcome,
            ]);

            return;
        }

        foreach ($plans as $plan) {
            $record->supportItems()->create([
                'employee_function_id' => $function->id,
                'label' => $function->label,
                'output_outcome' => $function->output_outcome,
                'success_indicator' => $plan->success_indicator,
            ]);
        }
    }

    private function assertCoreWeightsSumTo100($coreFunctions): void
    {
        if ($coreFunctions->isEmpty()) {
            return;
        }

        $sum = (float) $coreFunctions->sum('weight_percent');
        if (abs($sum - 100) > 0.5) {
            throw ValidationException::withMessages([
                'weight_percent' => "This employee's Core Function weights sum to {$sum}%, not 100%. Fix the weights on the Employee Functions tab before generating targets.",
            ]);
        }
    }
}
