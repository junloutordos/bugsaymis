<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EvaluationController extends Controller
{
    // ── Resolve participant from hash ─────────────────────────────────────────

    /**
     * Returns ['type' => 'employee'|'student', 'id' => int, 'name' => string]
     * or null if the hash doesn't match any participant of this activity.
     */
    private function resolveParticipant(Activity $activity, string $hash): ?array
    {
        // Check employee participants
        $participant = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')
            ->get()
            ->first(fn($p) => md5($p->participant_id . '-' . $activity->id) === $hash);

        if ($participant) {
            $user = User::find($participant->participant_id);
            return [
                'type' => 'employee',
                'id'   => $participant->participant_id,
                'name' => $user?->name ?? "Participant #{$participant->participant_id}",
            ];
        }

        // Check student participants
        $studentRow = ActivityStudentAttendance::where('activity_id', $activity->id)
            ->get()
            ->first(fn($r) => md5($r->participant_id . '-' . $activity->id) === $hash);

        if ($studentRow) {
            $student = Student::find($studentRow->participant_id);
            return [
                'type' => 'student',
                'id'   => $studentRow->participant_id,
                'name' => $student?->full_name ?? "Student #{$studentRow->participant_id}",
            ];
        }

        return null;
    }

    // ── Show evaluation form ──────────────────────────────────────────────────

    public function show(Activity $activity, string $hash)
    {
        $resolved = $this->resolveParticipant($activity, $hash);
        if (!$resolved) abort(404, 'Evaluation link is invalid or has expired.');

        $alreadyEvaluated = ActivityEvaluation::where('activity_id', $activity->id)
            ->where('participant_type', $resolved['type'])
            ->where('participant_id', $resolved['id'])
            ->exists();

        return Inertia::render('AMS/Evaluate', [
            'activity' => [
                'id'         => $activity->id,
                'title'      => $activity->title,
                'start_date' => $activity->start_date?->toDateString(),
                'end_date'   => $activity->end_date?->toDateString(),
                'venue'      => $activity->venue,
            ],
            'participant'      => $resolved,
            'hash'             => $hash,
            'alreadyEvaluated' => $alreadyEvaluated,
        ]);
    }

    // ── Store evaluation ──────────────────────────────────────────────────────

    public function store(Request $request, Activity $activity, string $hash)
    {
        $resolved = $this->resolveParticipant($activity, $hash);
        if (!$resolved) abort(404, 'Evaluation link is invalid or has expired.');

        // Prevent duplicate submissions
        $exists = ActivityEvaluation::where('activity_id', $activity->id)
            ->where('participant_type', $resolved['type'])
            ->where('participant_id', $resolved['id'])
            ->exists();

        if ($exists) {
            return back()->with('info', 'You have already submitted an evaluation for this activity.');
        }

        $likertFull = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];
        $likertBase = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree'];

        $data = $request->validate([
            'evaluator_name' => 'nullable|string|max:255',
            // Section A
            'obj_1'  => 'required|in:' . implode(',', $likertFull),
            'obj_2'  => 'required|in:' . implode(',', $likertFull),
            'obj_3'  => 'required|in:' . implode(',', $likertFull),
            'obj_4'  => 'required|in:' . implode(',', $likertFull),
            // Section B
            'mgmt_1' => 'required|in:' . implode(',', $likertFull),
            'mgmt_2' => 'required|in:' . implode(',', $likertFull),
            'mgmt_3' => 'required|in:' . implode(',', $likertFull),
            'mgmt_4' => 'required|in:' . implode(',', $likertFull),
            'mgmt_5' => 'required|in:' . implode(',', $likertFull),
            'mgmt_6' => 'required|in:' . implode(',', $likertFull),
            // Section C
            'phys_1' => 'required|in:' . implode(',', $likertBase),
            'phys_2' => 'required|in:' . implode(',', $likertBase),
            'phys_3' => 'required|in:' . implode(',', $likertBase),
            // Open-ended
            'suggestions'    => 'nullable|string|max:2000',
            'other_comments' => 'nullable|string|max:2000',
        ]);

        ActivityEvaluation::create(array_merge($data, [
            'activity_id'      => $activity->id,
            'participant_type' => $resolved['type'],
            'participant_id'   => $resolved['id'],
        ]));

        return redirect()->route('ams.activities.evaluate.show', [$activity->id, $hash])
            ->with('success', 'Thank you! Your evaluation has been submitted.');
    }
}
