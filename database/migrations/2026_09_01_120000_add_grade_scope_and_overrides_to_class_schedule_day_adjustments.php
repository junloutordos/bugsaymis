<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds grade-level publish scope to adjusted-day schedules (nullable =
     * "all grades", preserving existing rows' behavior) and a table for
     * manual time-only overrides used to resolve a flagged conflict on a
     * specific class entry before publishing.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('class_schedule_day_adjustments', 'grade_levels')) {
            Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
                // Null/empty = every grade (7–12), matching pre-existing behavior.
                // A non-null array restricts generation/publishing to those grades
                // only; unselected grades keep their regular weekly schedule.
                $table->json('grade_levels')->nullable()->after('adjustment_type');
            });
        }

        Schema::create('class_schedule_day_adjustment_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('class_schedule_day_adjustments', 'id', 'cs_day_adjustment_override_adjustment_fk')
                ->cascadeOnDelete();
            $table->foreignId('class_schedule_id')
                ->constrained('class_schedules', 'id', 'cs_day_adjustment_override_schedule_fk')
                ->cascadeOnDelete();
            $table->time('override_start_time');
            $table->time('override_end_time');
            $table->timestamps();

            $table->unique(['adjustment_id', 'class_schedule_id'], 'cs_day_adjustment_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustment_overrides');

        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->dropColumn('grade_levels');
        });
    }
};
