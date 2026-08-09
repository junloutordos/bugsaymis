<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_quiz_attempt_id')->constrained('learn_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('learn_quiz_question_id')->constrained('learn_quiz_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 6, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learn_quiz_attempt_id', 'learn_quiz_question_id'], 'learn_quiz_attempt_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_attempt_answers');
    }
};
