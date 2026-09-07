<?php

namespace App\Http\Controllers;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\EmployeeFunctionSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeFunctionController extends Controller
{
    public function index(User $user)
    {
        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');

        return Inertia::render('Users/EmployeeFunctions', [
            'employee' => $user->only('id', 'name', 'position'),
            'functions' => $user->employeeFunctions()->with('workDistributionPlans:id,success_indicator')->get(),
            'isFaculty' => $user->hasRole('Faculty') || (bool) $user->academic_unit_id,
            'workDistributionPlans' => WorkDistributionPlan::forFiscalYear($currentYear)
                ->select('id', 'success_indicator')
                ->orderBy('success_indicator')
                ->get(),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'function_type' => 'required|in:core,support',
            'work_distribution_plan_ids' => 'nullable|array',
            'work_distribution_plan_ids.*' => 'exists:work_distribution_plans,id',
            'label' => 'required|string|max:255',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $planIds = $data['work_distribution_plan_ids'] ?? [];

        $function = EmployeeFunction::create([
            'user_id' => $user->id,
            'function_type' => $data['function_type'],
            'source_type' => $planIds ? EmployeeFunction::SOURCE_WDP : EmployeeFunction::SOURCE_MANUAL,
            'label' => $data['label'],
            'weight_percent' => $data['weight_percent'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $function->workDistributionPlans()->sync($planIds);

        return back()->with('success', 'Function added.');
    }

    public function update(Request $request, User $user, EmployeeFunction $employeeFunction)
    {
        abort_if($employeeFunction->user_id !== $user->id, 404);

        $data = $request->validate([
            'label' => 'required|string|max:255',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
            'work_distribution_plan_ids' => 'nullable|array',
            'work_distribution_plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $planIds = $data['work_distribution_plan_ids'] ?? [];

        $employeeFunction->update([
            'label' => $data['label'],
            'weight_percent' => $data['weight_percent'] ?? null,
            'source_type' => $planIds ? EmployeeFunction::SOURCE_WDP : (
                $employeeFunction->source_type === EmployeeFunction::SOURCE_LOAD_ASSIGNMENT
                    ? EmployeeFunction::SOURCE_LOAD_ASSIGNMENT
                    : EmployeeFunction::SOURCE_MANUAL
            ),
        ]);

        $employeeFunction->workDistributionPlans()->sync($planIds);

        return back()->with('success', 'Function updated.');
    }

    public function destroy(User $user, EmployeeFunction $employeeFunction)
    {
        abort_if($employeeFunction->user_id !== $user->id, 404);

        $employeeFunction->delete();

        return back()->with('success', 'Function removed.');
    }

    public function sync(User $user, EmployeeFunctionSyncService $service)
    {
        abort_unless($user->hasRole('Faculty') || (bool) $user->academic_unit_id, 422, 'Sync from Faculty Loading only applies to faculty employees.');

        $service->syncFromFacultyLoading($user);

        return back()->with('success', 'Synced from Faculty Loading.');
    }
}
