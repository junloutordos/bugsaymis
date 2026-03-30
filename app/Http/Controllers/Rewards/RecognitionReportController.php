<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardNomination;
use App\Models\RewardType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RecognitionReportController extends Controller
{
    public function dashboard()
    {
        $this->authorize('rewards.view');

        $stats = [
            'total_nominations' => RewardNomination::count(),
            'pending'           => RewardNomination::where('status', 'pending')->count(),
            'screened'          => RewardNomination::where('status', 'screened')->count(),
            'evaluated'         => RewardNomination::where('status', 'evaluated')->count(),
            'approved'          => RewardNomination::where('status', 'approved')->count(),
            'rejected'          => RewardNomination::where('status', 'rejected')->count(),
            'total_awarded'     => Reward::count(),
        ];

        $byType = RewardNomination::select('reward_type_id', DB::raw('count(*) as total'))
            ->with('rewardType:id,name')
            ->groupBy('reward_type_id')
            ->get();

        $recentAwards = Reward::with(['nomination.nominee', 'nomination.rewardType'])
            ->orderByDesc('award_date')
            ->limit(10)
            ->get();

        return Inertia::render('Rewards/Dashboard', [
            'stats'        => $stats,
            'byType'       => $byType,
            'recentAwards' => $recentAwards,
        ]);
    }

    public function report(Request $request)
    {
        $this->authorize('rewards.view');

        $year   = $request->get('year', now()->year);
        $typeId = $request->get('reward_type_id');

        $query = RewardNomination::with(['rewardType', 'nominee', 'reward'])
            ->whereYear('created_at', $year);

        if ($typeId) {
            $query->where('reward_type_id', $typeId);
        }

        $nominations = $query->orderByDesc('created_at')->get();

        $awardsByIncentive = Reward::select('incentive_type', DB::raw('count(*) as count'), DB::raw('sum(incentive_value) as total_value'))
            ->whereHas('nomination', fn ($q) => $q->whereYear('created_at', $year))
            ->groupBy('incentive_type')
            ->get();

        return Inertia::render('Rewards/Reports/Index', [
            'nominations'       => $nominations,
            'awardsByIncentive' => $awardsByIncentive,
            'rewardTypes'       => RewardType::orderBy('name')->get(['id', 'name']),
            'filters'           => compact('year', 'typeId'),
        ]);
    }
}
