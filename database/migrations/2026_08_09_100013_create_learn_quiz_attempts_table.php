<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_quiz_id')->constrained('learn_quizzes')->cascadeOnDelete();
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');
            $table->unsignedInteger('attempt_number');
            $table->json('question_order');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['learn_quiz_id', 'student_id', 'attempt_number'], 'learn_quiz_attempts_quiz_student_attempt_unique');
            $table->index(['learn_quiz_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_attempts');
    }
};
