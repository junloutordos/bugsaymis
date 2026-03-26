<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill daily_schedules on employee_schedules rows that were created before
 * daily_schedules was added to the model's $fillable array.  Matches rows to
 * schedule_presets by name (the assign controller copies the preset name).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE employee_schedules es
            JOIN schedule_presets sp ON es.name = sp.name
            SET es.daily_schedules = sp.daily_schedules
            WHERE es.daily_schedules IS NULL
        ');
    }

    public function down(): void
    {
        // Cannot safely reverse a data backfill
    }
};
