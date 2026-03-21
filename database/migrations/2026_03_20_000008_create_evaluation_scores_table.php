<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('criteria_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->decimal('score', 8, 4);           // raw score given by evaluator
            $table->decimal('computed_score', 8, 4);  // score * (weight/100)
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'criteria_id', 'evaluator_id']);
            $table->index(['application_id', 'criteria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
