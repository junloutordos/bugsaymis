<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manual, time-only correction applied to one Recess or White Space
     * band within one adjusted-day preview, scoped to one section — same
     * concept as class_schedule_day_adjustment_overrides but for a bell-
     * schedule band instead of a real ClassSchedule row (bands have no id
     * of their own to hang an override off).
     */
    public function up(): void
    {
        Schema::create('class_schedule_day_adjustment_band_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')
                ->constrained('class_schedule_day_adjustments', 'id', 'cs_day_adj_band_override_adjustment_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->string('band_type');
            $table->time('override_start_time');
            $table->time('override_end_time');
            $table->timestamps();

            $table->unique(['adjustment_id', 'section_id', 'band_type'], 'cs_day_adj_band_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustment_band_overrides');
    }
};
