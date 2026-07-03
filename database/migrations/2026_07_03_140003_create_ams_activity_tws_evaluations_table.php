<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_tws_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();

            // Who evaluated
            $table->enum('participant_type', ['employee', 'student']);
            $table->unsignedBigInteger('participant_id');
            $table->string('evaluator_name')->nullable();
            $table->string('position_function')->nullable()->comment('Function/position for content relevance item');

            $likertFull = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];
            $satisfactionScale = ['very_dissatisfied', 'dissatisfied', 'neutral', 'satisfied', 'very_satisfied', 'not_applicable'];
            $likertNoNa = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree'];

            // Section A — Training/Workshop/Activity Content (5 items)
            $table->enum('content_1', $likertFull)->nullable()->comment('Objectives clearly stated');
            $table->enum('content_2', $likertFull)->nullable()->comment('Information clear and well organized');
            $table->enum('content_3', $likertFull)->nullable()->comment('Examples/exercises reinforced objectives');
            $table->enum('content_4', $likertFull)->nullable()->comment('Audio/Visuals reinforced program content');
            $table->enum('content_5', $likertFull)->nullable()->comment('Content relevant to needs/functions');

            // Section B — Management (6 items, satisfaction scale)
            $table->enum('mgmt_length_of_program', $satisfactionScale)->nullable();
            $table->enum('mgmt_schedule', $satisfactionScale)->nullable();
            $table->enum('mgmt_secretariat_support', $satisfactionScale)->nullable();
            $table->enum('mgmt_venue', $satisfactionScale)->nullable();
            $table->enum('mgmt_accommodation', $satisfactionScale)->nullable();
            $table->enum('mgmt_food_meals', $satisfactionScale)->nullable();

            // Section C — Overall Evaluation (2 items, no N/A)
            $table->enum('overall_1_objectives_accomplished', $likertNoNa)->nullable();
            $table->enum('overall_2_knowledge_increased', $likertNoNa)->nullable();

            // Open-ended
            $table->text('suggestions')->nullable();
            $table->text('other_comments')->nullable();

            $table->timestamps();

            $table->unique(['activity_id', 'participant_type', 'participant_id'], 'uq_tws_eval_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_tws_evaluations');
    }
};
