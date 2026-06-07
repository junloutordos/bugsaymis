<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_tap_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('Faculty who tapped in');

            $table->foreignId('classroom_id')
                  ->constrained('classrooms')
                  ->cascadeOnDelete();

            // Nullable: set when a matching class_schedule is found at tap time
            $table->foreignId('class_schedule_id')
                  ->nullable()
                  ->constrained('class_schedules')
                  ->nullOnDelete();

            $table->timestamp('tapped_at');

            $table->enum('status', ['on_time', 'late', 'no_match'])
                  ->default('on_time')
                  ->comment('on_time: within window; late: past grace period; no_match: no schedule found');

            $table->boolean('is_late')->default(false);
            $table->unsignedSmallInteger('late_minutes')->default(0);

            $table->timestamps();

            // One tap per class schedule slot per teacher per day (idempotent)
            $table->unique(['user_id', 'class_schedule_id', 'tapped_at'], 'idx_tap_unique_slot');

            $table->index(['user_id', 'tapped_at']);
            $table->index(['classroom_id', 'tapped_at']);
            $table->index(['class_schedule_id', 'tapped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_tap_logs');
    }
};
