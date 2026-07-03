<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('type', ['mcq', 'true_false', 'checkbox', 'poll', 'open_ended']);
            $table->text('question_text');
            $table->string('image')->nullable()->comment('S3 key');
            $table->unsignedInteger('time_limit_seconds')->default(20);
            $table->unsignedInteger('points_base')->default(1000);
            $table->timestamps();

            $table->index(['quiz_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
