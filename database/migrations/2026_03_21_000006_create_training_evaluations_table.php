<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                  ->constrained('training_participants')
                  ->cascadeOnDelete();

            /*
            |------------------------------------------------------------------
            | Kirkpatrick Four-Level Evaluation Model
            |------------------------------------------------------------------
            | Level 1 — Reaction:  Did participants find it engaging/relevant?
            | Level 2 — Learning:  Did they acquire the knowledge/skills?
            | Level 3 — Behavior:  Did they apply learning on the job?
            | Level 4 — Results:   Did it impact organizational performance?
            |------------------------------------------------------------------
            */

            // Level 1 — Reaction (1–5 scale)
            $table->unsignedTinyInteger('reaction_score')->nullable();        // Overall satisfaction
            $table->unsignedTinyInteger('relevance_score')->nullable();       // Relevance to job
            $table->unsignedTinyInteger('facilitation_score')->nullable();    // Facilitator quality
            $table->unsignedTinyInteger('logistics_score')->nullable();       // Venue/materials/logistics
            $table->text('reaction_feedback')->nullable();

            // Level 2 — Learning (1–5 scale)
            $table->unsignedTinyInteger('learning_score')->nullable();        // Knowledge/skill acquired
            $table->text('learning_feedback')->nullable();

            // Level 3 — Behavior (1–5 scale, assessed ~3 months post-training)
            $table->unsignedTinyInteger('behavior_score')->nullable();
            $table->date('behavior_assessed_date')->nullable();
            $table->foreignId('behavior_assessed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('behavior_feedback')->nullable();

            // Level 4 — Results (1–5 scale, assessed by HR/management)
            $table->unsignedTinyInteger('results_score')->nullable();
            $table->date('results_assessed_date')->nullable();
            $table->foreignId('results_assessed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('results_feedback')->nullable();

            // General feedback / recommendations
            $table->text('feedback')->nullable();
            $table->text('recommendations')->nullable();

            // Computed average (stored for fast reporting)
            $table->decimal('overall_average', 4, 2)->nullable();

            $table->timestamps();

            // One evaluation per participant
            $table->unique('participant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_evaluations');
    }
};
