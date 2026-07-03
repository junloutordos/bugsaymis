<?php

namespace App\Services\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivitySpeakerEvaluation;
use App\Models\AMS\ActivityTwsEvaluation;

/**
 * Builds the evaluation summary passed to AMS/Show.vue's Evaluations tab.
 * Branches on activity_type: In-house activities keep the original
 * Objectives/Management/Physical Arrangements survey; Training/Workshop/Seminar
 * activities get the HRU-13 (content/management/overall) survey plus one
 * HRU-31 (Speaker/Topic) summary per speaker.
 *
 * This is the single source of truth for question labels — the frontend
 * receives them as props rather than duplicating the text a second time.
 */
class ActivityEvaluationSummaryService
{
    public function build(Activity $activity): array
    {
        return $activity->isTrainingWorkshopSeminar()
            ? $this->buildTws($activity)
            : $this->buildInHouse($activity);
    }

    // ── In-house (existing 13-question survey) ─────────────────────────────────

    private function buildInHouse(Activity $activity): array
    {
        $rows = ActivityEvaluation::where('activity_id', $activity->id)
            ->orderByDesc('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return ['type' => 'in_house', 'count' => 0, 'sections' => [], 'responses' => []];
        }

        $weights = ActivityEvaluation::WEIGHTS;

        $sections = [
            'A' => [
                'label'     => 'Objectives',
                'questions' => [
                    'obj_1' => 'The activity was aligned with the school\'s vision and mission.',
                    'obj_2' => 'The objectives were relevant to the needs of the participants.',
                    'obj_3' => 'The activity contributed to the academic, personal, or social development of the participants.',
                    'obj_4' => 'The objectives were achieved.',
                ],
            ],
            'B' => [
                'label'     => 'Management',
                'questions' => [
                    'mgmt_1' => 'Organizers and staff were visible and responsive.',
                    'mgmt_2' => 'Coordination and flow of the program were clear.',
                    'mgmt_3' => 'Time was managed effectively.',
                    'mgmt_4' => 'Transitions between parts of the activity were smooth.',
                    'mgmt_5' => 'The activity was well-organized.',
                    'mgmt_6' => 'Participants were actively engaged.',
                ],
            ],
            'C' => [
                'label'     => 'Physical Arrangements',
                'questions' => [
                    'phys_1' => 'Venue and materials were ready and adequate.',
                    'phys_2' => 'The sound system and equipment functioned properly.',
                    'phys_3' => 'Participants were properly guided within the venue.',
                ],
            ],
        ];

        $allOptions = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];

        $builtSections = $this->buildLikertSections($sections, $rows, $weights, $allOptions);

        $responses = $rows->map(fn ($r) => [
            'id'               => $r->id,
            'participant_type' => $r->participant_type,
            'evaluator_name'   => $r->evaluator_name ?? 'Anonymous',
            'suggestions'      => $r->suggestions,
            'other_comments'   => $r->other_comments,
            'submitted_at'     => $r->created_at->toDateTimeString(),
        ])->values()->all();

        return [
            'type'      => 'in_house',
            'count'     => $rows->count(),
            'sections'  => $builtSections,
            'responses' => $responses,
        ];
    }

    // ── Training/Workshop/Seminar (HRU-13 + HRU-31) ─────────────────────────────

    private function buildTws(Activity $activity): array
    {
        $rows = ActivityTwsEvaluation::where('activity_id', $activity->id)
            ->orderByDesc('created_at')
            ->get();

        $agreeOptions       = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];
        $satisfactionOptions = ['very_satisfied', 'satisfied', 'neutral', 'dissatisfied', 'very_dissatisfied', 'not_applicable'];
        $overallOptions     = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree'];

        $sections = [
            'A' => [
                'label'     => 'Training/Workshop/Activity Content',
                'options'   => $agreeOptions,
                'weights'   => ActivityTwsEvaluation::AGREE_WEIGHTS,
                'questions' => [
                    'content_1' => 'The training/workshop/activity objectives were clearly stated.',
                    'content_2' => 'Information was clear and well organized.',
                    'content_3' => 'Examples/exercises reinforced training/workshop/activity objectives.',
                    'content_4' => 'Audio/Visuals reinforced program content.',
                    'content_5' => 'Content was relevant to my needs/functions.',
                ],
            ],
            'B' => [
                'label'     => 'Management of Training/Workshop/Activity',
                'options'   => $satisfactionOptions,
                'weights'   => ActivityTwsEvaluation::SATISFACTION_WEIGHTS,
                'questions' => [
                    'mgmt_length_of_program'   => 'Length of the program.',
                    'mgmt_schedule'            => 'Schedule of activities.',
                    'mgmt_secretariat_support' => 'Secretariat & support staff, if any.',
                    'mgmt_venue'               => 'Venue of training/workshop/activity (sound system, etc).',
                    'mgmt_accommodation'       => 'Hotel accommodation/lodging.',
                    'mgmt_food_meals'          => 'Food/meals served.',
                ],
            ],
            'C' => [
                'label'     => 'Overall Evaluation',
                'options'   => $overallOptions,
                'weights'   => ActivityTwsEvaluation::AGREE_WEIGHTS,
                'questions' => [
                    'overall_1_objectives_accomplished' => 'Training/workshop/activity objectives were accomplished.',
                    'overall_2_knowledge_increased'     => 'This training/workshop/activity has increased my knowledge and capabilities.',
                ],
            ],
        ];

        $twsSections = $this->buildWeightedSections($sections, $rows);

        $responses = $rows->map(fn ($r) => [
            'id'                 => $r->id,
            'participant_type'   => $r->participant_type,
            'evaluator_name'     => $r->evaluator_name ?? 'Anonymous',
            'position_function'  => $r->position_function,
            'suggestions'        => $r->suggestions,
            'other_comments'     => $r->other_comments,
            'submitted_at'       => $r->created_at->toDateTimeString(),
        ])->values()->all();

        $speakerSummaries = $activity->speakers->map(
            fn ($speaker) => $this->buildSpeakerSummary($speaker)
        )->values()->all();

        return [
            'type'      => 'training_workshop_seminar',
            'count'     => $rows->count(),
            'sections'  => $twsSections,
            'responses' => $responses,
            'speakers'  => $speakerSummaries,
        ];
    }

    private function buildSpeakerSummary($speaker): array
    {
        $rows = ActivitySpeakerEvaluation::where('speaker_id', $speaker->id)
            ->orderByDesc('created_at')
            ->get();

        $topicOptions  = ['excellent', 'satisfactory', 'low'];
        $speakerOptions = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];

        $sections = [
            'I' => [
                'label'     => 'Topic/Subject Matter',
                'options'   => $topicOptions,
                'weights'   => ActivitySpeakerEvaluation::TOPIC_WEIGHTS,
                'questions' => [
                    'topic_depth_of_content'          => 'Depth of Content',
                    'topic_scope_coverage'            => 'Scope/Coverage',
                    'topic_relevance_appropriateness' => 'Relevance/Appropriateness',
                ],
            ],
            'II' => [
                'label'     => 'Resource Person',
                'options'   => $speakerOptions,
                'weights'   => ActivitySpeakerEvaluation::SPEAKER_WEIGHTS,
                'questions' => [
                    'attainment_of_objectives'              => 'Attainment of Topic Objectives',
                    'mastery_1_command_of_subject'          => 'Demonstrated command of subject matter',
                    'mastery_2_pace_timing'                 => 'Maintained comfortable pace and timing',
                    'mastery_3_theory_application_balance'  => 'Balanced the theories/principles with applications',
                    'mastery_4_current_trends'              => 'Presented current trends/developments related to the topic',
                    'presentation_1_listened'               => 'Listened to what I and other attendees had to say',
                    'presentation_2_answered_questions'     => 'Answered questions effectively',
                    'presentation_3_inspired_participation' => 'Inspired participation of attendees',
                    'presentation_4_held_interest'          => 'Held my interest in the topic',
                    'acceptability_as_speaker'               => 'Acceptability of the Speaker as Resource Person',
                ],
            ],
        ];

        $builtSections = $this->buildWeightedSections($sections, $rows);

        $responses = $rows->map(fn ($r) => [
            'id'                       => $r->id,
            'participant_type'         => $r->participant_type,
            'evaluator_name'           => $r->evaluator_name ?? 'Anonymous',
            'effectiveness_reason'     => $r->effectiveness_reason,
            'improvement_suggestions' => $r->improvement_suggestions,
            'other_topics_wanted'      => $r->other_topics_wanted,
            'submitted_at'             => $r->created_at->toDateTimeString(),
        ])->values()->all();

        return [
            'speaker'   => ['id' => $speaker->id, 'name' => $speaker->name, 'topic' => $speaker->topic, 'designation' => $speaker->designation],
            'count'     => $rows->count(),
            'sections'  => $builtSections,
            'responses' => $responses,
        ];
    }

    /**
     * Shared builder for weighted-scale sections (TWS + Speaker summaries),
     * where each section may use its own option set/weight map.
     */
    private function buildWeightedSections(array $sections, $rows): array
    {
        $built = [];

        foreach ($sections as $key => $section) {
            $questions     = [];
            $sectionScores = [];
            $weights       = $section['weights'];

            foreach ($section['questions'] as $field => $label) {
                $dist   = array_fill_keys($section['options'], 0);
                $scores = [];

                foreach ($rows as $row) {
                    $val = $row->$field;
                    if ($val) {
                        $dist[$val] = ($dist[$val] ?? 0) + 1;
                    }
                    $w = $weights[$val] ?? null;
                    if ($w !== null) {
                        $scores[] = $w;
                    }
                }

                $questions[] = [
                    'field' => $field,
                    'label' => $label,
                    'avg'   => count($scores) ? round(array_sum($scores) / count($scores), 2) : null,
                    'dist'  => $dist,
                    'count' => count($scores),
                ];

                array_push($sectionScores, ...$scores);
            }

            $built[] = [
                'key'       => $key,
                'label'     => $section['label'],
                'avg'       => count($sectionScores) ? round(array_sum($sectionScores) / count($sectionScores), 2) : null,
                'options'   => $section['options'],
                'questions' => $questions,
            ];
        }

        return $built;
    }

    /**
     * In-house sections all share the same options/weight map — thin wrapper
     * over buildWeightedSections() to keep buildInHouse() readable.
     */
    private function buildLikertSections(array $sections, $rows, array $weights, array $options): array
    {
        foreach ($sections as $key => &$section) {
            $section['weights'] = $weights;
            $section['options'] = $options;
        }
        unset($section);

        return $this->buildWeightedSections($sections, $rows);
    }
}
