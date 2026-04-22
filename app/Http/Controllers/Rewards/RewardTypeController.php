<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\RewardType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RewardTypeController extends Controller
{
    public function index()
    {
        $this->authorize('rewards.view');

        $types = RewardType::with('creator')
            ->orderBy('name')
            ->get();

        return Inertia::render('Rewards/RewardTypes/Index', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rewards.manage');

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|in:individual,team',
            'type'        => 'required|in:monetary,non_monetary,mixed',
            'criteria'    => 'nullable|string',
            'frequency'   => 'required|in:monthly,quarterly,annual,ad_hoc',
            'is_active'   => 'boolean',
        ]);

        $data['created_by'] = auth()->id();

        RewardType::create($data);

        return back()->with('success', 'Reward type created.');
    }

    public function update(Request $request, RewardType $rewardType)
    {
        $this->authorize('rewards.manage');

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|in:individual,team',
            'type'        => 'required|in:monetary,non_monetary,mixed',
            'criteria'    => 'nullable|string',
            'frequency'   => 'required|in:monthly,quarterly,annual,ad_hoc',
            'is_active'   => 'boolean',
        ]);

        $rewardType->update($data);

        return back()->with('success', 'Reward type updated.');
    }

    public function destroy(RewardType $rewardType)
    {
        $this->authorize('rewards.manage');

        $rewardType->delete();

        return back()->with('success', 'Reward type deleted.');
    }
}
