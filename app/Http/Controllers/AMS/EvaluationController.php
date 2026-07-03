<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivitySpeakerEvaluation;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\AMS\ActivityTwsEvaluation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($activity->isTrainingWorkshopSeminar()) {
            return $this->showTws($activity, $hash, $resolved);
        }

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

    private function showTws(Activity $activity, string $hash, array $resolved)
    {
        $alreadyEvaluated = ActivityTwsEvaluation::where('activity_id', $activity->id)
            ->where('participant_type', $resolved['type'])
            ->where('participant_id', $resolved['id'])
            ->exists();

        $speakers = $activity->speakers()->orderBy('sort_order')->get(['id', 'name', 'designation', 'topic']);

        return Inertia::render('AMS/EvaluateTWS', [
            'activity' => [
                'id'         => $activity->id,
                'title'      => $activity->title,
                'start_date' => $activity->start_date?->toDateString(),
                'end_date'   => $activity->end_date?->toDateString(),
                'venue'      => $activity->venue,
            ],
            'speakers'         => $speakers,
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

        if ($activity->isTrainingWorkshopSeminar()) {
            return $this->storeTws($request, $activity, $hash, $resolved);
        }

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

    private function storeTws(Request $request, Activity $activity, string $hash, array $resolved)
    {
        $exists = ActivityTwsEvaluation::where('activity_id', $activity->id)
            ->where('participant_type', $resolved['type'])
            ->where('participant_id', $resolved['id'])
            ->exists();

        if ($exists) {
            return back()->with('info', 'You have already submitted an evaluation for this activity.');
        }

        $agree       = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];
        $satisfaction = ['very_satisfied', 'satisfied', 'neutral', 'dissatisfied', 'very_dissatisfied', 'not_applicable'];
        $overall     = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree'];
        $topicScale  = ['low', 'satisfactory', 'excellent'];
        $speakerScale = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];

        $speakerIds = $activity->speakers()->pluck('id')->all();

        $rules = [
            'evaluator_name'     => 'nullable|string|max:255',
            'position_function'  => 'nullable|string|max:255',
            'content_1'          => 'required|in:' . implode(',', $agree),
            'content_2'          => 'required|in:' . implode(',', $agree),
            'content_3'          => 'required|in:' . implode(',', $agree),
            'content_4'          => 'required|in:' . implode(',', $agree),
            'content_5'          => 'required|in:' . implode(',', $agree),
            'mgmt_length_of_program'   => 'required|in:' . implode(',', $satisfaction),
            'mgmt_schedule'            => 'required|in:' . implode(',', $satisfaction),
            'mgmt_secretariat_support' => 'required|in:' . implode(',', $satisfaction),
            'mgmt_venue'               => 'required|in:' . implode(',', $satisfaction),
            'mgmt_accommodation'       => 'required|in:' . implode(',', $satisfaction),
            'mgmt_food_meals'          => 'required|in:' . implode(',', $satisfaction),
            'overall_1_objectives_accomplished' => 'required|in:' . implode(',', $overall),
            'overall_2_knowledge_increased'     => 'required|in:' . implode(',', $overall),
            'suggestions'    => 'nullable|string|max:2000',
            'other_comments' => 'nullable|string|max:2000',

            'speakers'                                          => 'required|array',
            'speakers.*.speaker_id'                              => 'required|integer|in:' . implode(',', $speakerIds ?: [0]),
            'speakers.*.topic_depth_of_content'                  => 'required|in:' . implode(',', $topicScale),
            'speakers.*.topic_scope_coverage'                    => 'required|in:' . implode(',', $topicScale),
            'speakers.*.topic_relevance_appropriateness'         => 'required|in:' . implode(',', $topicScale),
            'speakers.*.attainment_of_objectives'                => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.mastery_1_command_of_subject'            => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.mastery_2_pace_timing'                   => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.mastery_3_theory_application_balance'    => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.mastery_4_current_trends'                => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.presentation_1_listened'                 => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.presentation_2_answered_questions'       => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.presentation_3_inspired_participation'   => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.presentation_4_held_interest'             => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.acceptability_as_speaker'                 => 'required|in:' . implode(',', $speakerScale),
            'speakers.*.effectiveness_reason'                     => 'nullable|string|max:2000',
            'speakers.*.improvement_suggestions'                  => 'nullable|string|max:2000',
            'speakers.*.other_topics_wanted'                       => 'nullable|string|max:2000',
        ];

        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $activity, $resolved) {
            $speakerRows = $data['speakers'];
            unset($data['speakers']);

            ActivityTwsEvaluation::create(array_merge($data, [
                'activity_id'      => $activity->id,
                'participant_type' => $resolved['type'],
                'participant_id'   => $resolved['id'],
            ]));

            foreach ($speakerRows as $row) {
                $speakerId = $row['speaker_id'];
                unset($row['speaker_id']);

                ActivitySpeakerEvaluation::create(array_merge($row, [
                    'activity_id'      => $activity->id,
                    'speaker_id'       => $speakerId,
                    'participant_type' => $resolved['type'],
                    'participant_id'   => $resolved['id'],
                    'evaluator_name'   => $data['evaluator_name'] ?? null,
                ]));
            }
        });

        return redirect()->route('ams.activities.evaluate.show', [$activity->id, $hash])
            ->with('success', 'Thank you! Your evaluation has been submitted.');
    }
}
