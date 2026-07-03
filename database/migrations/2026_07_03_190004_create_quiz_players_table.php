<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->string('nickname');

            // Public-channel identity for anonymous players (no auth) — used as the
            // private "who am I" secret handed to the joining device, not a login.
            $table->uuid('player_token')->unique();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('student_id')->nullable()->comment('Soft ref → students.id (MyISAM, no FK)');

            $table->unsignedInteger('total_score')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_players');
    }
};
