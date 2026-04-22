<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardNomination;
use App\Models\RecognitionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RewardController extends Controller
{
    public function index()
    {
        $this->authorize('rewards.view');

        $rewards = Reward::with(['nomination.rewardType', 'nomination.nominee', 'recorder'])
            ->orderByDesc('award_date')
            ->paginate(20);

        return Inertia::render('Rewards/Awards/Index', [
            'rewards' => $rewards,
        ]);
    }

    public function store(Request $request, RewardNomination $rewardNomination)
    {
        $this->authorize('rewards.manage');

        if ($rewardNomination->status !== 'approved') {
            return back()->withErrors(['nomination' => 'Only approved nominations can be awarded.']);
        }

        $data = $request->validate([
            'award_date'      => 'required|date',
            'incentive_type'  => 'required|in:cash,leave_credits,certificate,plaque,other',
            'incentive_value' => 'nullable|numeric|min:0',
            'remarks'         => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $rewardNomination) {
            $reward = Reward::create([
                'nomination_id'   => $rewardNomination->id,
                'award_date'      => $data['award_date'],
                'incentive_type'  => $data['incentive_type'],
                'incentive_value' => $data['incentive_value'] ?? null,
                'remarks'         => $data['remarks'] ?? null,
                'recorded_by'     => auth()->id(),
            ]);

            RecognitionLog::create([
                'nomination_id'   => $rewardNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => 'awarded',
                'notes'           => "Incentive: {$data['incentive_type']}",
                'status_snapshot' => 'approved',
            ]);
        });

        return back()->with('success', 'Award recorded.');
    }

    public function update(Request $request, Reward $reward)
    {
        $this->authorize('rewards.manage');

        $data = $request->validate([
            'award_date'      => 'required|date',
            'incentive_type'  => 'required|in:cash,leave_credits,certificate,plaque,other',
            'incentive_value' => 'nullable|numeric|min:0',
            'remarks'         => 'nullable|string',
        ]);

        $reward->update($data);

        return back()->with('success', 'Award updated.');
    }
}
