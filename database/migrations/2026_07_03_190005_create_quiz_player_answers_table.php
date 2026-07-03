<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_player_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('quiz_players')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();

            $table->json('selected_option_ids')->nullable();
            $table->text('answer_text')->nullable()->comment('open_ended responses');
            $table->boolean('is_correct')->nullable()->comment('null for poll/open_ended — no right answer');
            $table->unsignedInteger('points_awarded')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            $table->unique(['player_id', 'question_id']);
            $table->index(['session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_player_answers');
    }
};
