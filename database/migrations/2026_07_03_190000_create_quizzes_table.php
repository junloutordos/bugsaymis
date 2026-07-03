<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable()->comment('S3 key');

            // Optional link to the module that spawned this quiz — polymorphic soft
            // reference (no FK: source can be ams_activities or class_records).
            $table->string('source_type')->nullable()->comment('activity | class_record | null (standalone)');
            $table->unsignedBigInteger('source_id')->nullable();

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('settings')->nullable()->comment('shuffle_questions, shuffle_answers, show_leaderboard, points_mode, require_roster_join');

            $table->timestamps();

            $table->index('owner_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
