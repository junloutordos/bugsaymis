<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_speaker_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('ams_activity_speakers')->cascadeOnDelete();

            $table->enum('participant_type', ['employee', 'student']);
            $table->unsignedBigInteger('participant_id');
            $table->string('evaluator_name')->nullable();

            // Part I — Topic/Subject Matter (3 items)
            $topicScale = ['low', 'satisfactory', 'excellent'];
            $table->enum('topic_depth_of_content', $topicScale)->nullable();
            $table->enum('topic_scope_coverage', $topicScale)->nullable();
            $table->enum('topic_relevance_appropriateness', $topicScale)->nullable();

            // Part II — Resource Person (9 items, likert + N/A)
            $likertFull = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];
            $table->enum('attainment_of_objectives', $likertFull)->nullable();
            $table->enum('mastery_1_command_of_subject', $likertFull)->nullable();
            $table->enum('mastery_2_pace_timing', $likertFull)->nullable();
            $table->enum('mastery_3_theory_application_balance', $likertFull)->nullable();
            $table->enum('mastery_4_current_trends', $likertFull)->nullable();
            $table->enum('presentation_1_listened', $likertFull)->nullable();
            $table->enum('presentation_2_answered_questions', $likertFull)->nullable();
            $table->enum('presentation_3_inspired_participation', $likertFull)->nullable();
            $table->enum('presentation_4_held_interest', $likertFull)->nullable();
            $table->enum('acceptability_as_speaker', $likertFull)->nullable();

            // Part III — open-ended
            $table->text('effectiveness_reason')->nullable();
            $table->text('improvement_suggestions')->nullable();
            $table->text('other_topics_wanted')->nullable();

            $table->timestamps();

            $table->unique(['activity_id', 'speaker_id', 'participant_type', 'participant_id'], 'uq_speaker_eval_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_speaker_evaluations');
    }
};
