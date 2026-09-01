<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields for the early-start STEM-split adjustment type: the campus-wide
     * target start time every section's first class period is anchored to,
     * per-subject-type period durations, and an optional Health Break band.
     * All nullable — unused by every other adjustment type.
     */
    public function up(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->time('day_start_time')->nullable()->after('class_duration_minutes');
            $table->unsignedSmallInteger('stem_class_duration_minutes')->nullable()->after('day_start_time');
            $table->unsignedSmallInteger('non_stem_class_duration_minutes')->nullable()->after('stem_class_duration_minutes');
            $table->string('health_break_title')->nullable()->after('non_stem_class_duration_minutes');
            $table->time('health_break_start_time')->nullable()->after('health_break_title');
            $table->time('health_break_end_time')->nullable()->after('health_break_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'day_start_time',
                'stem_class_duration_minutes',
                'non_stem_class_duration_minutes',
                'health_break_title',
                'health_break_start_time',
                'health_break_end_time',
            ]);
        });
    }
};
