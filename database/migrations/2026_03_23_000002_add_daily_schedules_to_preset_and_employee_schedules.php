<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // schedule_presets: replace time_in/time_out with per-day JSON
        Schema::table('schedule_presets', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out', 'work_days']);
            // {"Mon":{"time_in":"08:00","time_out":"17:00"}, "Tue":{...}, ...}
            $table->json('daily_schedules')->after('schedule_type')
                ->comment('Per-day time_in/time_out keyed by Mon-Sun');
        });

        // employee_schedules: add daily_schedules, make time_in/time_out nullable
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->json('daily_schedules')->nullable()->after('work_days')
                ->comment('Per-day overrides; if set, overrides time_in/time_out');
            $table->time('time_in')->nullable()->change();
            $table->time('time_out')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_presets', function (Blueprint $table) {
            $table->dropColumn('daily_schedules');
            $table->json('work_days')->nullable()->after('schedule_type');
            $table->time('time_in')->after('work_days');
            $table->time('time_out')->after('time_in');
        });

        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropColumn('daily_schedules');
        });
    }
};
