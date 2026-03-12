<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIPCR;
use App\Models\SpecialAssignment;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpecialAssignmentPerformanceController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $query = SpecialAssignment::with(['coordinator', 'members', 'workDistributionPlans']);

        // Non-admin/DC/OCD only see assignments they belong to
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) {
            $query->where(function ($q) use ($user) {
                $q->where('coordinator_id', $user->id)
                  ->orWhereHas('members', fn($mq) => $mq->where('users.id', $user->id));
            });
        }

        return Inertia::render('PerformanceManagement/SpecialAssignments/Index', [
            'assignments' => $query->orderBy('name')->get(),
            'users'       => User::select('id', 'name', 'position')->orderBy('name')->get(),
            'plans'       => WorkDistributionPlan::select('id', 'success_indicator', 'rated_by')
                                ->orderBy('success_indicator')->get(),
            'authUser'    => $user->only('id', 'name'),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) {
            abort(403);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'coordinator_id' => 'nullable|exists:users,id',
            'description'    => 'nullable|string',
            'member_ids'     => 'nullable|array',
            'member_ids.*'   => 'exists:users,id',
            'member_tasks'   => 'nullable|array',
            'plan_ids'       => 'nullable|array',
            'plan_ids.*'     => 'exists:work_distribution_plans,id',
        ]);

        $assignment = SpecialAssignment::create([
            'name'           => $validated['name'],
            'coordinator_id' => $validated['coordinator_id'] ?? null,
            'description'    => $validated['description'] ?? null,
        ]);

        $this->syncMembers($assignment, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        $assignment->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        return redirect()->back()->with('success', 'Special Assignment created.');
    }

    public function update(Request $request, SpecialAssignment $specialAssignment)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) {
            abort(403);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'coordinator_id' => 'nullable|exists:users,id',
            'description'    => 'nullable|string',
            'member_ids'     => 'nullable|array',
            'member_ids.*'   => 'exists:users,id',
            'member_tasks'   => 'nullable|array',
            'plan_ids'       => 'nullable|array',
            'plan_ids.*'     => 'exists:work_distribution_plans,id',
        ]);

        $specialAssignment->update([
            'name'           => $validated['name'],
            'coordinator_id' => $validated['coordinator_id'] ?? null,
            'description'    => $validated['description'] ?? null,
        ]);

        $this->syncMembers($specialAssignment, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        $specialAssignment->workDistributionPlans()->sync($validated['plan_ids'] ?? []);

        return redirect()->back()->with('success', 'Special Assignment updated.');
    }

    public function destroy(SpecialAssignment $specialAssignment)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR'])) {
            abort(403);
        }

        $specialAssignment->delete();
        return redirect()->back()->with('success', 'Special Assignment deleted.');
    }

    public function show(SpecialAssignment $specialAssignment)
    {
        $user = auth()->user();
        $specialAssignment->load(['coordinator', 'members', 'workDistributionPlans.performance_indicator']);

        $isCoordinator = $user->id == $specialAssignment->coordinator_id;
        $canManage     = $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD', 'HR']) || $isCoordinator;
        $isMember      = $specialAssignment->members->contains('id', $user->id);

        if (!$canManage && !$isMember) {
            abort(403, 'You are not a member of this special assignment.');
        }

        // For each plan, list members; each member has all their IPCR periods (latest first)
        $planMemberData = $specialAssignment->workDistributionPlans->map(function ($plan) use ($specialAssignment) {
            $members = $specialAssignment->members->map(function ($member) use ($plan) {
                $ipcrs = EmployeeIPCR::where('user_id', $member->id)
                    ->whereHas('plans', fn($q) => $q->where('work_distribution_plans.id', $plan->id))
                    ->with(['plans' => fn($q) => $q->where('work_distribution_plans.id', $plan->id)])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $periods = $ipcrs->map(function ($ipcr) {
                    $pivot = $ipcr->plans->first()?->pivot;
                    return [
                        'ipcr_id'        => $ipcr->id,
                        'rating_period'  => $ipcr->rating_period ?? '—',
                        'ipcr_status'    => $ipcr->status,
                        'can_rate'       => $ipcr->status === 'Submitted for Rating',
                        'accomplishment' => $pivot?->accomplishment,
                        'mov_link'       => $pivot?->mov_link,
                        'sup_quality'    => $pivot?->sup_quality,
                        'sup_efficiency' => $pivot?->sup_efficiency,
                        'sup_timeliness' => $pivot?->sup_timeliness,
                        'sup_average'    => $pivot?->sup_average,
                    ];
                })->values();

                return [
                    'user_id'       => $member->id,
                    'user_name'     => $member->name,
                    'user_position' => $member->position,
                    'task'          => $member->pivot->task,
                    'periods'       => $periods,
                ];
            });

            return [
                'plan'    => $plan,
                'members' => $members,
            ];
        });

        return Inertia::render('PerformanceManagement/SpecialAssignments/Show', [
            'assignment'     => $specialAssignment,
            'planMemberData' => $planMemberData,
            'authUser'       => $user->only('id', 'name'),
            'isCoordinator'  => $isCoordinator,
            'canManage'      => $canManage,
        ]);
    }

    /**
     * Member saves their own accomplishment for a specific IPCR + plan.
     */
    public function saveMemberAccomplishment(Request $request, SpecialAssignment $specialAssignment, User $member)
    {
        $user = auth()->user();
        if ($user->id !== $member->id) {
            abort(403, 'You can only update your own accomplishment.');
        }

        $request->validate([
            'ipcr_id'        => 'required|exists:employee_ipcrs,id',
            'plan_id'        => 'required|exists:work_distribution_plans,id',
            'accomplishment' => 'nullable|string|max:500',
            'mov_link'       => 'nullable|url|max:255',
        ]);

        $ipcr = EmployeeIPCR::where('id', $request->ipcr_id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        if (!$ipcr->plans()->where('work_distribution_plans.id', $request->plan_id)->exists()) {
            abort(404, 'This plan is not in the specified IPCR.');
        }

        $ipcr->plans()->updateExistingPivot($request->plan_id, [
            'accomplishment' => $request->accomplishment,
            'mov_link'       => $request->mov_link,
        ]);

        return redirect()->back()->with('success', 'Accomplishment saved.');
    }

    /**
     * Coordinator rates a member for a specific IPCR + plan.
     * Locked when IPCR status is not "Submitted for Rating".
     */
    public function rateMember(Request $request, SpecialAssignment $specialAssignment, User $member)
    {
        $user          = auth()->user();
        $isCoordinator = $user->id == $specialAssignment->coordinator_id;
        $canRate       = $isCoordinator || $user->hasAnyRole(['Administrator', 'DivisionChief', 'OCD']);
        if (!$canRate) {
            abort(403, 'Only the coordinator can rate members.');
        }

        $request->validate([
            'ipcr_id'        => 'required|exists:employee_ipcrs,id',
            'plan_id'        => 'required|exists:work_distribution_plans,id',
            'accomplishment' => 'nullable|string|max:500',
            'mov_link'       => 'nullable|url|max:255',
            'sup_quality'    => 'nullable|numeric|min:1|max:5',
            'sup_efficiency' => 'nullable|numeric|min:1|max:5',
            'sup_timeliness' => 'nullable|numeric|min:1|max:5',
        ]);

        $ipcr = EmployeeIPCR::where('id', $request->ipcr_id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        if ($ipcr->status !== 'Submitted for Rating') {
            abort(403, 'Ratings can only be edited while the IPCR is "Submitted for Rating".');
        }

        if (!$ipcr->plans()->where('work_distribution_plans.id', $request->plan_id)->exists()) {
            abort(404, 'This plan is not in the specified IPCR.');
        }

        $ratings = collect([
            $request->sup_quality,
            $request->sup_efficiency,
            $request->sup_timeliness,
        ])->filter(fn($v) => !is_null($v));

        $supAverage = $ratings->count() ? round($ratings->avg(), 2) : null;

        $ipcr->plans()->updateExistingPivot($request->plan_id, [
            'accomplishment' => $request->accomplishment,
            'mov_link'       => $request->mov_link,
            'sup_quality'    => $request->sup_quality,
            'sup_efficiency' => $request->sup_efficiency,
            'sup_timeliness' => $request->sup_timeliness,
            'sup_average'    => $supAverage,
        ]);

        return redirect()->back()->with('success', 'Rating saved.');
    }

    private function syncMembers(SpecialAssignment $assignment, array $memberIds, array $memberTasks): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $syncData[$userId] = ['task' => $memberTasks[$userId] ?? null];
        }
        $assignment->members()->sync($syncData);
    }
}
