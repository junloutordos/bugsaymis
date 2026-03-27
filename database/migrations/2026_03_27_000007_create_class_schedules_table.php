<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * class_schedules — one row per session block.
     *
     * Conflict detection uses the time-overlap rule:
     *   (startA < endB) AND (endA > startB)
     * across three conflict axes:
     *   A) faculty_id  + day_of_week (faculty double-booking)
     *   B) classroom_id + day_of_week (room double-booking)
     *   C) section_id  + day_of_week (section double-booking)
     *
     * Named class_schedules to avoid collision with the legacy `schedule`
     * biometric shift table.
     */
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();

            // Optional link back to the assignment that created this slot
            $table->foreignId('load_assignment_id')
                  ->nullable()
                  ->constrained('load_assignments')
                  ->nullOnDelete();

            // Core references
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Faculty teaching this slot');
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedInteger('section_id')->comment('FK to sections.id');
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();

            // Schedule slot
            $table->enum('day_of_week', [
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
            ]);
            $table->time('start_time');
            $table->time('end_time');

            // State
            $table->enum('status', ['active', 'tentative', 'cancelled'])->default('active');
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // ── Conflict-detection indexes (the three axes) ─────────────────────
            // A: Faculty conflict  — same faculty, same day
            $table->index(['user_id',      'day_of_week', 'start_time'], 'idx_cs_faculty_slot');
            // B: Room conflict     — same room, same day
            $table->index(['classroom_id', 'day_of_week', 'start_time'], 'idx_cs_room_slot');
            // C: Section conflict  — same section, same day
            $table->index(['section_id',   'day_of_week', 'start_time'], 'idx_cs_section_slot');
            // Scoping index
            $table->index(['school_year_id', 'academic_term_id', 'status'], 'idx_cs_term_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
