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

        $coreFunctions = EmployeeFunction::where('user_id', $user->id)->core()->get();
        $supportFunctions = EmployeeFunction::where('user_id', $user->id)->support()->get();

        $this->assertCoreWeightsSumTo100($coreFunctions);

        return DB::transaction(function () use ($user, $period, $coreFunctions, $supportFunctions) {
            $record = IpcrV2Record::create([
                'user_id' => $user->id,
                'rating_period_id' => $period->id,
            ]);

            foreach ($coreFunctions as $function) {
                $record->coreItems()->create([
                    'employee_function_id' => $function->id,
                    'label' => $function->label,
                    'weight_percent' => $function->weight_percent,
                ]);
            }

            foreach ($supportFunctions as $function) {
                $record->supportItems()->create([
                    'employee_function_id' => $function->id,
                    'label' => $function->label,
                ]);
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
        $record->loadMissing(['coreItems', 'supportItems']);

        $existingCoreFunctionIds = $record->coreItems->pluck('employee_function_id')->all();
        $existingSupportFunctionIds = $record->supportItems->pluck('employee_function_id')->all();

        $newCoreFunctions = EmployeeFunction::where('user_id', $record->user_id)->core()
            ->whereNotIn('id', $existingCoreFunctionIds)->get();
        $newSupportFunctions = EmployeeFunction::where('user_id', $record->user_id)->support()
            ->whereNotIn('id', $existingSupportFunctionIds)->get();

        if ($newCoreFunctions->isEmpty() && $newSupportFunctions->isEmpty()) {
            return 0;
        }

        if ($newCoreFunctions->isNotEmpty()) {
            $totalWeight = (float) $record->coreItems->sum('weight_percent') + (float) $newCoreFunctions->sum('weight_percent');
            if (abs($totalWeight - 100) > 0.5) {
                throw ValidationException::withMessages([
                    'weight_percent' => "Adding these Core Function(s) would make the weights sum to {$totalWeight}%, not 100%. Fix the weights on the Employee Functions tab before syncing.",
                ]);
            }
        }

        return DB::transaction(function () use ($record, $newCoreFunctions, $newSupportFunctions) {
            foreach ($newCoreFunctions as $function) {
                $record->coreItems()->create([
                    'employee_function_id' => $function->id,
                    'label' => $function->label,
                    'weight_percent' => $function->weight_percent,
                ]);
            }

            foreach ($newSupportFunctions as $function) {
                $record->supportItems()->create([
                    'employee_function_id' => $function->id,
                    'label' => $function->label,
                ]);
            }

            return $newCoreFunctions->count() + $newSupportFunctions->count();
        });
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
