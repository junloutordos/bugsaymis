<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the existing `sections` table for Faculty Loading.
     *
     * The sections table was originally created in migration
     * 2026_01_21_000200 with a legacy schema (levelid, sectionname, syid).
     * This migration adds the columns required by the Faculty Loading
     * module without removing any existing columns, preserving
     * backward compatibility with other modules.
     *
     * Column mapping (legacy → Faculty Loading):
     *   levelid     → grade_level (populated from existing data)
     *   sectionname → name        (populated from existing data)
     *   syid        → school_year_id (FK added in migration 000014)
     *
     * New columns added:
     *   section_code   — short timetable code, e.g., "G7-NEWTON"
     *   strand         — STEM/ABM strand (Grade 11–12)
     *   capacity       — max students (default 45)
     *   is_active      — soft-disable flag
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            if (! Schema::hasColumn('sections', 'section_code')) {
                $table->string('section_code', 30)->nullable()
                      ->after('sectionname')
                      ->comment('Short timetable code, e.g., G7-NEWTON');
            }

            if (! Schema::hasColumn('sections', 'strand')) {
                $table->string('strand', 60)->nullable()
                      ->after('section_code')
                      ->comment('Academic strand (mainly Gr.11-12): STEM, ABM, etc.');
            }

            if (! Schema::hasColumn('sections', 'capacity')) {
                $table->unsignedTinyInteger('capacity')->default(45)
                      ->after('strand')
                      ->comment('Maximum number of students in this section');
            }

            if (! Schema::hasColumn('sections', 'is_active')) {
                $table->boolean('is_active')->default(true)
                      ->after('capacity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $cols = ['section_code', 'strand', 'capacity', 'is_active'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('sections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
