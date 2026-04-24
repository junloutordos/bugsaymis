<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * section_fixed_slots
 *
 * Stores all pre-sealed non-class time blocks for every section × day.
 * Types include: FLAG, HOMEROOM, ADVISING, DEAD, RECESS, LUNCH,
 *                CONSULT, WELLNESS, ACTIVITY, ILA.
 *
 * Populated by FixedSlotService::sealFixedSlots() before any subject
 * placement phase runs. The scheduling engine checks this table to
 * avoid placing CLASS slots over these windows (Hard Constraints H4–H12).
 *
 * Kept separate from class_schedules because fixed slots have no
 * teacher or subject — they are structural timetable blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_fixed_slots', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('section_id')
                  ->comment('FK to sections.id (legacy INT pk)');

            $table->foreignId('school_year_id')
                  ->constrained('school_years')
                  ->cascadeOnDelete();

            $table->enum('day_of_week', [
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday',
            ]);

            $table->time('start_time');
            $table->time('end_time');

            $table->enum('slot_type', [
                'FLAG', 'HOMEROOM', 'ADVISING', 'DEAD',
                'RECESS', 'LUNCH', 'CONSULT',
                'WELLNESS', 'ACTIVITY', 'ILA',
            ]);

            $table->string('label', 80)->nullable();

            $table->timestamps();

            // ── Lookup indexes ──────────────────────────────────────────────
            // Fast overlap check: section + day
            $table->index(['section_id', 'day_of_week', 'start_time'], 'idx_sfs_section_day');
            // Bulk clear by school year
            $table->index(['school_year_id'], 'idx_sfs_sy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_fixed_slots');
    }
};
