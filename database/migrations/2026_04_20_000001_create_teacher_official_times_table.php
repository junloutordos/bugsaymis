<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * teacher_official_times
 *
 * Stores the official working time for each teacher on each day of the week.
 * Teachers at PSHS-CRC can be on early shift (7:30–16:30) or late shift
 * (8:00–17:00) and this can vary day-by-day.
 *
 * The scheduling engine enforces Hard Constraint H3: no class may start before
 * or end after the teacher's official time on that day.
 *
 * One row per (user_id, day_of_week) — upsert via updateOrCreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_official_times', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('The faculty member');

            $table->enum('day_of_week', [
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday',
            ]);

            $table->time('start_time')
                  ->comment('Official start: 07:30 (early) or 08:00 (late)');

            $table->time('end_time')
                  ->comment('Official end: 16:30 (early) or 17:00 (late)');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Each teacher has at most one official-time record per day
            $table->unique(['user_id', 'day_of_week'], 'uniq_teacher_day_official_time');

            $table->index('user_id', 'idx_tot_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_official_times');
    }
};
