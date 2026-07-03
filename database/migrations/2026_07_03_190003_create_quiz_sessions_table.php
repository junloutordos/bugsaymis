<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();

            // 6-digit PIN — uniqueness among *active* sessions only enforced in
            // QuizSessionService (old ended sessions keep their PIN for history).
            $table->string('game_pin', 6);

            $table->enum('status', ['lobby', 'question_active', 'reveal', 'leaderboard', 'ended'])->default('lobby');
            $table->unsignedInteger('current_question_index')->default(0);
            $table->boolean('require_roster_join')->default(false);

            // Server-authoritative timestamp for the active question — response
            // time is computed server-side from this, never trusted from the client.
            $table->timestamp('question_started_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['game_pin', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_sessions');
    }
};
