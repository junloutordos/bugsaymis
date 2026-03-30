<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\RewardApproval;
use App\Models\RewardNomination;
use App\Models\RecognitionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RewardApprovalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('rewards.approve');

        $level = $request->get('level', 'committee');

        $nominations = RewardNomination::with([
                'rewardType',
                'nominee',
                'nominator',
                'approvals' => fn ($q) => $q->where('level', $level),
            ])
            ->where('status', 'evaluated')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($n) use ($level) {
                $n->my_approval = $n->approvals->firstWhere('approver_id', auth()->id());
                return $n;
            });

        return Inertia::render('Rewards/Approvals/Index', [
            'nominations' => $nominations,
            'level'       => $level,
        ]);
    }

    public function decide(Request $request, RewardNomination $rewardNomination)
    {
        $this->authorize('rewards.approve');

        $data = $request->validate([
            'level'    => 'required|in:committee,head_of_office',
            'decision' => 'required|in:approved,rejected,deferred',
            'remarks'  => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $rewardNomination) {
            RewardApproval::updateOrCreate(
                [
                    'nomination_id' => $rewardNomination->id,
                    'level'         => $data['level'],
                ],
                [
                    'approver_id' => auth()->id(),
                    'decision'    => $data['decision'],
                    'remarks'     => $data['remarks'] ?? null,
                    'decided_at'  => now(),
                ]
            );

            // Determine new nomination status
            if ($data['decision'] === 'rejected') {
                $newStatus = 'rejected';
            } elseif ($data['decision'] === 'approved' && $data['level'] === 'head_of_office') {
                $newStatus = 'approved';
            } else {
                $newStatus = $rewardNomination->status; // stays evaluated until HoO acts
            }

            if ($newStatus !== $rewardNomination->status) {
                $rewardNomination->update(['status' => $newStatus]);
            }

            RecognitionLog::create([
                'nomination_id'   => $rewardNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => "{$data['level']}_{$data['decision']}",
                'notes'           => $data['remarks'] ?? null,
                'status_snapshot' => $newStatus,
            ]);
        });

        return back()->with('success', 'Decision recorded.');
    }
}
