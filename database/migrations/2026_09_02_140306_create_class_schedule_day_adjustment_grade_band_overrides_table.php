<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manual, time-only correction applied to the ELECTIVE or SCIENCE_CORE
     * band on one adjusted-day preview, scoped to a whole grade level rather
     * than one section — these are cross-homeroom groups, so every section
     * in the grade must show the identical override. Structural sibling of
     * class_schedule_day_adjustment_band_overrides, keyed by grade_level
     * instead of section_id.
     */
    public function up(): void
    {
        Schema::create('class_schedule_day_adjustment_grade_band_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('class_schedule_day_adjustments', 'id', 'cs_day_adj_grade_band_override_adjustment_fk')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('grade_level');
            $table->string('band_type');
            $table->time('override_start_time');
            $table->time('override_end_time');
            $table->timestamps();

            $table->unique(['adjustment_id', 'grade_level', 'band_type'], 'cs_day_adj_grade_band_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustment_grade_band_overrides');
    }
};
