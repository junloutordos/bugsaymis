<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomination_id')
                  ->constrained('reward_nominations')
                  ->cascadeOnDelete();

            $table->foreignId('evaluator_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Score out of 100
            $table->unsignedTinyInteger('score')->nullable();

            $table->text('remarks')->nullable();

            $table->enum('evaluation_stage', ['screening', 'final'])->default('screening');

            // Structured per-criterion scores (optional JSON breakdown)
            $table->json('criterion_scores')->nullable();

            $table->timestamps();

            // One evaluator submits one evaluation per nomination per stage
            $table->unique(['nomination_id', 'evaluator_id', 'evaluation_stage'], 'reward_evals_unique');

            $table->index('evaluation_stage');
            $table->index('evaluator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_evaluations');
    }
};
