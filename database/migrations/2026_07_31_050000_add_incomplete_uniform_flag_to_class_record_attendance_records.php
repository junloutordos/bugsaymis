<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-adds per-subject "Incomplete Uniform" (IU) tracking to Class Record
     * Attendance — but as an independent boolean FLAG alongside `status`,
     * not a status value in the Present/Absent/Tardy/Cut Class cycle. This
     * mirrors Homeroom's own `incomplete_uniform` column on
     * `homeroom_attendance_records` exactly, so a subject teacher's IU flag
     * can sync one-directionally into that existing column without touching
     * Homeroom's status vocabulary at all.
     *
     * The legacy `uniform_status` column on this table stays untouched/dead
     * — this is a new, separate column, not a revival of that one.
     *
     * Additive-only — safe for blue-green (nullable-equivalent via default).
     */
    public function up(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->boolean('incomplete_uniform')->default(false)->after('synced_from_homeroom');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->dropColumn('incomplete_uniform');
        });
    }
};
