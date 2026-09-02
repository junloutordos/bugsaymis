<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which published Adjusted Day schedule (if any) a tap was
     * validated against, so the audit trail explains an on_time/late/no_match
     * result that looks wrong against the teacher's normal weekly schedule.
     */
    public function up(): void
    {
        Schema::table('teacher_tap_logs', function (Blueprint $table) {
            $table->foreignId('class_schedule_day_adjustment_id')
                  ->nullable()
                  ->after('class_schedule_id')
                  ->constrained('class_schedule_day_adjustments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_tap_logs', function (Blueprint $table) {
            $table->dropForeign(['class_schedule_day_adjustment_id']);
            $table->dropColumn('class_schedule_day_adjustment_id');
        });
    }
};
