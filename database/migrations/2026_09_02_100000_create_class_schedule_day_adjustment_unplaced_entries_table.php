<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A class bumped off its slot for one adjusted day by a drag-and-drop
     * placement colliding with it — either a real subject class awaiting
     * manual re-placement (surfaced in the calendar's "Unplaced" tray,
     * blocks publish until resolved) or a non_teaching block that's simply
     * removed for the day (never surfaced, never blocks publish).
     * Distinguished by the underlying ClassSchedule.entry_type, not a
     * column here — both are "this class has no slot today."
     */
    public function up(): void
    {
        Schema::create('class_schedule_day_adjustment_unplaced_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('class_schedule_day_adjustments', 'id', 'cs_day_adj_unplaced_adjustment_fk')
                ->cascadeOnDelete();
            $table->foreignId('class_schedule_id')
                ->constrained('class_schedules', 'id', 'cs_day_adj_unplaced_schedule_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['adjustment_id', 'class_schedule_id'], 'cs_day_adj_unplaced_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustment_unplaced_entries');
    }
};
