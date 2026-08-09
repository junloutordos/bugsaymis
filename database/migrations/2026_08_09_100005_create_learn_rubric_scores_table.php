<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubric_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_submission_id')->constrained('learn_submissions')->cascadeOnDelete();
            $table->foreignId('learn_rubric_criterion_id')->constrained('learn_rubric_criteria')->cascadeOnDelete();
            $table->decimal('points_earned', 6, 2);
            $table->timestamps();

            $table->unique(['learn_submission_id', 'learn_rubric_criterion_id'], 'learn_rubric_scores_submission_criterion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubric_scores');
    }
};
