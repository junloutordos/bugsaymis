<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\RewardNomination;
use App\Models\RewardType;
use App\Models\RecognitionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RewardNominationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('rewards.view');

        $query = RewardNomination::with(['rewardType', 'nominee', 'nominator'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('reward_type_id')) {
            $query->where('reward_type_id', $request->reward_type_id);
        }
        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        $nominations = $query->paginate(20)->withQueryString();

        return Inertia::render('Rewards/Nominations/Index', [
            'nominations' => $nominations,
            'rewardTypes' => RewardType::where('is_active', true)->orderBy('name')->get(),
            'filters'     => $request->only(['status', 'reward_type_id', 'period']),
        ]);
    }

    public function show(RewardNomination $rewardNomination)
    {
        $this->authorize('rewards.view');

        $rewardNomination->load([
            'rewardType',
            'nominee',
            'nominator',
            'evaluations.evaluator',
            'approvals.approver',
            'reward',
            'logs.actor',
        ]);

        return Inertia::render('Rewards/Nominations/Show', [
            'nomination' => $rewardNomination,
        ]);
    }

    public function create()
    {
        $this->authorize('rewards.nominate');

        return Inertia::render('Rewards/Nominations/Create', [
            'rewardTypes' => RewardType::where('is_active', true)->orderBy('name')->get(),
            'employees'   => User::employees()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rewards.nominate');

        $data = $request->validate([
            'reward_type_id'       => 'required|exists:reward_types,id',
            'nominee_id'           => 'required|exists:users,id',
            'justification'        => 'required|string',
            'period'               => 'nullable|string|max:50',
            'team_name'            => 'nullable|string|max:255',
            'supporting_documents' => 'nullable|array',
            'supporting_documents.*' => 'file|mimes:pdf,doc,docx,jpg,png|max:5120',
        ]);

        DB::transaction(function () use ($data, $request) {
            $paths = [];
            if ($request->hasFile('supporting_documents')) {
                foreach ($request->file('supporting_documents') as $file) {
                    $paths[] = $file->store('rewards/documents', 'public');
                }
            }

            $nomination = RewardNomination::create([
                'reward_type_id'       => $data['reward_type_id'],
                'nominee_id'           => $data['nominee_id'],
                'nominated_by'         => auth()->id(),
                'justification'        => $data['justification'],
                'period'               => $data['period'] ?? null,
                'team_name'            => $data['team_name'] ?? null,
                'supporting_documents' => $paths ?: null,
                'status'               => 'pending',
            ]);

            RecognitionLog::create([
                'nomination_id'   => $nomination->id,
                'actor_id'        => auth()->id(),
                'action'          => 'submitted',
                'status_snapshot' => 'pending',
            ]);
        });

        return redirect()->route('rewards.nominations.index')
            ->with('success', 'Nomination submitted successfully.');
    }

    public function screen(Request $request, RewardNomination $rewardNomination)
    {
        $this->authorize('rewards.evaluate');

        $data = $request->validate([
            'decision' => 'required|in:pass,fail',
            'remarks'  => 'nullable|string',
        ]);

        $newStatus = $data['decision'] === 'pass' ? 'screened' : 'rejected';

        DB::transaction(function () use ($rewardNomination, $data, $newStatus) {
            $rewardNomination->update([
                'status'  => $newStatus,
                'remarks' => $data['remarks'] ?? null,
            ]);

            RecognitionLog::create([
                'nomination_id'   => $rewardNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => $data['decision'] === 'pass' ? 'screened' : 'rejected',
                'notes'           => $data['remarks'] ?? null,
                'status_snapshot' => $newStatus,
            ]);
        });

        return back()->with('success', 'Nomination screened.');
    }

    public function destroy(RewardNomination $rewardNomination)
    {
        $this->authorize('rewards.manage');

        $rewardNomination->delete();

        return redirect()->route('rewards.nominations.index')
            ->with('success', 'Nomination deleted.');
    }

    public function myNominations()
    {
        $user = auth()->user();

        $nominations = RewardNomination::with(['rewardType', 'nominee'])
            ->where(function ($q) use ($user) {
                $q->where('nominee_id', $user->id)
                  ->orWhere('nominated_by', $user->id);
            })
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Rewards/MyRecognitions/Index', [
            'nominations' => $nominations,
        ]);
    }
}
