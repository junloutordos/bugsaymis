<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the existing date-specific overlay so it can also describe a
     * compressed class day for an official activity. Existing flag-transfer
     * rows remain valid and unchanged. Nullable activity fields keep this
     * migration backward-compatible with the currently deployed reader.
     */
    public function up(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->date('postponed_from_date')->nullable()->change();
            $table->string('activity_title')->nullable()->after('shift_minutes');
            $table->time('activity_start_time')->nullable()->after('activity_title');
            $table->time('activity_end_time')->nullable()->after('activity_start_time');
            $table->unsignedSmallInteger('class_duration_minutes')->nullable()->after('activity_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'activity_title',
                'activity_start_time',
                'activity_end_time',
                'class_duration_minutes',
            ]);
            $table->date('postponed_from_date')->nullable(false)->change();
        });
    }
};
