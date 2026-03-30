<?php

namespace App\Http\Controllers\LnD;

use App\Http\Controllers\Controller;
use App\Models\TrainingEvaluation;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingEvaluationController extends Controller
{
    // ── Submit Level 1 (Reaction) — by participant ────────────────────────────

    public function storeReaction(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.evaluate');

        $this->ensureOwnerOrHR($trainingParticipant);

        $validated = $request->validate([
            'reaction_score'     => ['required', 'integer', 'min:1', 'max:5'],
            'relevance_score'    => ['required', 'integer', 'min:1', 'max:5'],
            'facilitation_score' => ['required', 'integer', 'min:1', 'max:5'],
            'logistics_score'    => ['required', 'integer', 'min:1', 'max:5'],
            'reaction_feedback'  => ['nullable', 'string'],
        ]);

        $evaluation = $this->findOrCreate($trainingParticipant);
        $evaluation->update($validated);
        $evaluation->recalculateAverage();

        return back()->with('success', 'Level 1 (Reaction) evaluation saved.');
    }

    // ── Submit Level 2 (Learning) — by participant ────────────────────────────

    public function storeLearning(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.evaluate');

        $this->ensureOwnerOrHR($trainingParticipant);

        $validated = $request->validate([
            'learning_score'    => ['required', 'integer', 'min:1', 'max:5'],
            'learning_feedback' => ['nullable', 'string'],
        ]);

        $evaluation = $this->findOrCreate($trainingParticipant);
        $evaluation->update($validated);
        $evaluation->recalculateAverage();

        return back()->with('success', 'Level 2 (Learning) evaluation saved.');
    }

    // ── Submit Level 3 (Behavior) — by supervisor / HR ────────────────────────

    public function storeBehavior(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.evaluate');

        $validated = $request->validate([
            'behavior_score'         => ['required', 'integer', 'min:1', 'max:5'],
            'behavior_assessed_date' => ['required', 'date'],
            'behavior_feedback'      => ['nullable', 'string'],
        ]);

        $validated['behavior_assessed_by'] = Auth::id();

        $evaluation = $this->findOrCreate($trainingParticipant);
        $evaluation->update($validated);
        $evaluation->recalculateAverage();

        return back()->with('success', 'Level 3 (Behavior) evaluation saved.');
    }

    // ── Submit Level 4 (Results) — by HR / management ─────────────────────────

    public function storeResults(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.evaluate');

        $validated = $request->validate([
            'results_score'         => ['required', 'integer', 'min:1', 'max:5'],
            'results_assessed_date' => ['required', 'date'],
            'results_feedback'      => ['nullable', 'string'],
        ]);

        $validated['results_assessed_by'] = Auth::id();

        $evaluation = $this->findOrCreate($trainingParticipant);
        $evaluation->update($validated);
        $evaluation->recalculateAverage();

        return back()->with('success', 'Level 4 (Results) evaluation saved.');
    }

    // ── General feedback / recommendations ────────────────────────────────────

    public function updateFeedback(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.evaluate');

        $this->ensureOwnerOrHR($trainingParticipant);

        $validated = $request->validate([
            'feedback'        => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $evaluation = $this->findOrCreate($trainingParticipant);
        $evaluation->update($validated);

        return back()->with('success', 'Feedback saved.');
    }

    // ── Show evaluation for a participant ─────────────────────────────────────

    public function show(TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.view');

        $evaluation = $trainingParticipant->evaluation()->with([
            'behaviorAssessor:id,name',
            'resultsAssessor:id,name',
        ])->firstOrNew(['participant_id' => $trainingParticipant->id]);

        $trainingParticipant->load([
            'employee:id,name',
            'session.program:id,title,type',
        ]);

        return Inertia::render('LnD/Evaluations/Show', [
            'participant' => $trainingParticipant,
            'evaluation'  => $evaluation,
        ]);
    }

    // ── Session-level summary (all participants) ───────────────────────────────

    public function sessionSummary(TrainingSession $trainingSession)
    {
        $this->authorize('lnd.view');

        $evaluations = $trainingSession->evaluations()
            ->with(['participant.employee:id,name'])
            ->get();

        $summary = [
            'total'             => $evaluations->count(),
            'avg_reaction'      => round($evaluations->avg('reaction_score'), 2),
            'avg_relevance'     => round($evaluations->avg('relevance_score'), 2),
            'avg_facilitation'  => round($evaluations->avg('facilitation_score'), 2),
            'avg_logistics'     => round($evaluations->avg('logistics_score'), 2),
            'avg_learning'      => round($evaluations->avg('learning_score'), 2),
            'avg_behavior'      => round($evaluations->avg('behavior_score'), 2),
            'avg_results'       => round($evaluations->avg('results_score'), 2),
            'avg_overall'       => round($evaluations->avg('overall_average'), 2),
        ];

        return Inertia::render('LnD/Evaluations/SessionSummary', [
            'session'     => $trainingSession->load('program:id,title'),
            'evaluations' => $evaluations,
            'summary'     => $summary,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function findOrCreate(TrainingParticipant $participant): TrainingEvaluation
    {
        return TrainingEvaluation::firstOrCreate(
            ['participant_id' => $participant->id]
        );
    }

    private function ensureOwnerOrHR(TrainingParticipant $participant): void
    {
        $user = Auth::user();
        $isOwner = $participant->employee_id === $user->id;
        $isHR    = $user->hasPermissionTo('lnd.approve');

        if (!$isOwner && !$isHR) {
            abort(403, 'You can only submit evaluations for your own training records.');
        }
    }
}
