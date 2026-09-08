<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\EmployeeFunctionScopeResolver;
use App\Services\EmployeeFunctionSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'scopeOptions' => [
                'roles' => Role::select('id', 'name')->orderBy('name')->get(),
                'offices' => Office::select('id', 'name')->orderBy('name')->get(),
                'divisions' => Division::select('id', 'division_name as name')->orderBy('division_name')->get(),
                'positions' => User::employees()->where('status', '<>', 'inactive')
                    ->whereNotNull('position')->where('position', '<>', '')
                    ->distinct()->orderBy('position')->pluck('position'),
                'empCategories' => ['Plantilla Teaching', 'Plantilla Non-Teaching', 'COS Teaching', 'COS Non Teaching'],
                'allEmployees' => User::employees()->where('status', '<>', 'inactive')
                    ->select('id', 'name', 'position')->orderBy('name')->get(),
            ],
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

    public function previewScope(Request $request, EmployeeFunctionScopeResolver $resolver)
    {
        $data = $this->validateScope($request);

        $employees = $resolver->resolve($data['scope'], $data['user_ids'] ?? [], $data['filters'] ?? []);

        if ($data['scope'] === 'all') {
            return response()->json(['count' => $employees->count()]);
        }

        return response()->json([
            'count' => $employees->count(),
            'employees' => $employees->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'position' => $u->position,
                'office' => $u->office?->name,
                'division' => $u->division?->name,
            ])->values(),
        ]);
    }

    public function bulkStore(Request $request, EmployeeFunctionScopeResolver $resolver)
    {
        $scope = $this->validateScope($request);

        $data = $request->validate([
            'label' => 'required|string|max:255',
            'work_distribution_plan_ids' => 'nullable|array',
            'work_distribution_plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $employees = $resolver->resolve($scope['scope'], $scope['user_ids'] ?? [], $scope['filters'] ?? []);
        $planIds = $data['work_distribution_plan_ids'] ?? [];

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($employees, $data, $planIds, $request, &$created, &$skipped) {
            foreach ($employees as $employee) {
                $alreadyHasIt = EmployeeFunction::where('user_id', $employee->id)
                    ->where('function_type', EmployeeFunction::TYPE_SUPPORT)
                    ->whereRaw('LOWER(label) = ?', [mb_strtolower($data['label'])])
                    ->exists();

                if ($alreadyHasIt) {
                    $skipped++;
                    continue;
                }

                $function = EmployeeFunction::create([
                    'user_id' => $employee->id,
                    'function_type' => EmployeeFunction::TYPE_SUPPORT,
                    'source_type' => $planIds ? EmployeeFunction::SOURCE_WDP : EmployeeFunction::SOURCE_MANUAL,
                    'label' => $data['label'],
                    'created_by' => $request->user()->id,
                ]);
                $function->workDistributionPlans()->sync($planIds);
                $created++;
            }
        });

        $message = "Support function assigned to {$created} employee(s).";
        if ($skipped) {
            $message .= " Skipped {$skipped} who already had it.";
        }

        return back()->with('success', $message);
    }

    private function validateScope(Request $request): array
    {
        return $request->validate([
            'scope' => 'required|in:all,selected,filtered',
            'user_ids' => 'required_if:scope,selected|array',
            'user_ids.*' => 'exists:users,id',
            'filters' => 'nullable|array',
            'filters.position' => 'nullable|array',
            'filters.position.*' => 'string',
            'filters.role_id' => 'nullable|array',
            'filters.role_id.*' => 'exists:roles,id',
            'filters.office_id' => 'nullable|array',
            'filters.office_id.*' => 'exists:offices,id',
            'filters.division_id' => 'nullable|array',
            'filters.division_id.*' => 'exists:divisions,id',
            'filters.emp_category' => 'nullable|array',
            'filters.emp_category.*' => 'in:Plantilla Teaching,Plantilla Non-Teaching,COS Teaching,COS Non Teaching',
        ]);
    }
}
