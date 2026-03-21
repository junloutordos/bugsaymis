<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\RewardEvaluation;
use App\Models\RewardNomination;
use App\Models\RecognitionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RewardEvaluationController extends Controller
{
    public function panel(Request $request)
    {
        $this->authorize('rewards.evaluate');

        $stage = $request->get('stage', 'screening');

        $nominations = RewardNomination::with(['rewardType', 'nominee', 'evaluations' => function ($q) use ($stage) {
                $q->where('evaluation_stage', $stage);
            }])
            ->where('status', $stage === 'screening' ? 'screened' : 'screened')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($n) use ($stage) {
                $myEval = $n->evaluations->firstWhere('evaluator_id', auth()->id());
                $n->my_evaluation  = $myEval;
                $n->average_score  = $n->evaluations->avg('score');
                $n->evaluator_count = $n->evaluations->count();
                return $n;
            });

        return Inertia::render('Rewards/Evaluations/Panel', [
            'nominations' => $nominations,
            'stage'       => $stage,
        ]);
    }

    public function store(Request $request, RewardNomination $rewardNomination)
    {
        $this->authorize('rewards.evaluate');

        $data = $request->validate([
            'score'            => 'required|integer|min:0|max:100',
            'remarks'          => 'nullable|string',
            'evaluation_stage' => 'required|in:screening,final',
            'criterion_scores' => 'nullable|array',
        ]);

        DB::transaction(function () use ($data, $rewardNomination) {
            RewardEvaluation::updateOrCreate(
                [
                    'nomination_id'    => $rewardNomination->id,
                    'evaluator_id'     => auth()->id(),
                    'evaluation_stage' => $data['evaluation_stage'],
                ],
                [
                    'score'            => $data['score'],
                    'remarks'          => $data['remarks'] ?? null,
                    'criterion_scores' => $data['criterion_scores'] ?? null,
                ]
            );

            // If all expected evaluators have submitted, advance nomination status
            $evalCount = RewardEvaluation::where('nomination_id', $rewardNomination->id)
                ->where('evaluation_stage', $data['evaluation_stage'])
                ->count();

            if ($evalCount >= 1 && $rewardNomination->status === 'screened') {
                $rewardNomination->update(['status' => 'evaluated']);

                RecognitionLog::create([
                    'nomination_id'   => $rewardNomination->id,
                    'actor_id'        => auth()->id(),
                    'action'          => 'evaluated',
                    'notes'           => "Stage: {$data['evaluation_stage']}",
                    'status_snapshot' => 'evaluated',
                ]);
            }
        });

        return back()->with('success', 'Evaluation submitted.');
    }

    public function update(Request $request, RewardEvaluation $rewardEvaluation)
    {
        $this->authorize('rewards.evaluate');

        if ($rewardEvaluation->evaluator_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'score'            => 'required|integer|min:0|max:100',
            'remarks'          => 'nullable|string',
            'criterion_scores' => 'nullable|array',
        ]);

        $rewardEvaluation->update($data);

        return back()->with('success', 'Evaluation updated.');
    }
}
