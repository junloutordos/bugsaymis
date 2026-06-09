<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix three schema mismatches in research_advisories (safe — 0 rows exist):
     *
     *  1. advisory_type enum('lead','co_adviser') → renamed to advisory_role
     *     (the column always held the adviser's ROLE, not the research type)
     *
     *  2. Add research_type enum — tracks the kind of research project
     *     (thesis / investigatory / science_research / feasibility)
     *
     *  3. status enum: replace 'inactive' (never in DB) with 'dropped' —
     *     aligns DB, controller, and Vue; 'dropped' is semantically correct
     *     for a student who abandons a research project
     */
    public function up(): void
    {
        // Step 1: drop the misnamed column (must be in its own Schema::table call)
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->dropColumn('advisory_type');
        });

        // Step 2: add the two correctly-named columns
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->enum('advisory_role', ['lead', 'co_adviser'])
                  ->default('lead')
                  ->after('grade_level')
                  ->comment('lead = primary adviser (1 unit default); co_adviser = supporting (0.5 unit default)');

            $table->enum('research_type', ['thesis', 'investigatory', 'science_research', 'feasibility'])
                  ->nullable()
                  ->after('advisory_role')
                  ->comment('Type of research project being advised');
        });

        // Step 3: fix status enum ('inactive' was never storable; use 'dropped')
        \DB::statement("ALTER TABLE research_advisories MODIFY COLUMN status ENUM('active','completed','dropped') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->dropColumn(['advisory_role', 'research_type']);
        });

        Schema::table('research_advisories', function (Blueprint $table) {
            $table->enum('advisory_type', ['lead', 'co_adviser'])
                  ->default('lead')
                  ->after('grade_level');
        });

        \DB::statement("ALTER TABLE research_advisories MODIFY COLUMN status ENUM('active','completed','dropped') NOT NULL DEFAULT 'active'");
    }
};
