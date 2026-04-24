<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend the unit_type ENUM on academic_units to include 'department'.
     *
     * The 9 subject-area departments from the PSHS-CRC faculty loading
     * (CS, PISES, English, Filipino, Mathematics, BioChem, Social Science,
     * PEHM-VE, Engres) all use this type.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE academic_units MODIFY COLUMN unit_type
            ENUM('junior_high','senior_high','sst','admin','department')
            NOT NULL DEFAULT 'department'
            COMMENT 'Organisational type — use department for subject-area units'
        ");
    }

    public function down(): void
    {
        // Revert: drop 'department' entries first to avoid constraint violation
        DB::table('academic_units')->where('unit_type', 'department')->delete();

        DB::statement("ALTER TABLE academic_units MODIFY COLUMN unit_type
            ENUM('junior_high','senior_high','sst','admin')
            NOT NULL DEFAULT 'junior_high'
        ");
    }
};
