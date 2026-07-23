<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the faculty's actual lunch break (as submitted to HR and approved via
 * employee_schedules.daily_schedules) to teacher_official_times, so the
 * class-schedule print sheet and My Faculty Schedule can show the real,
 * HR-approved lunch window instead of guessing it from free gaps in the
 * class calendar.
 *
 * Nullable — a day may have no lunch recorded (not every submitted schedule
 * specifies one). Additive-only, safe for blue-green deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_official_times', function (Blueprint $table) {
            $table->time('lunch_start')->nullable()->after('end_time');
            $table->time('lunch_end')->nullable()->after('lunch_start');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_official_times', function (Blueprint $table) {
            $table->dropColumn(['lunch_start', 'lunch_end']);
        });
    }
};
