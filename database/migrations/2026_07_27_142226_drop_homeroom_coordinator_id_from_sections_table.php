<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts 2026_07_27_102008_add_homeroom_coordinator_id_to_sections_table.
 *
 * The per-section Homeroom Coordinator override was replaced the same day
 * by resolving the school's existing grade-wide COORD-* designations
 * instead — Almocera/Sanchez/Orbegoso already held those, so the override
 * was double-crediting them on top of a role they already had. See
 * HeadAdvisoryService's class doc comment and project memory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['homeroom_coordinator_id']);
            $table->dropColumn('homeroom_coordinator_id');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedBigInteger('homeroom_coordinator_id')->nullable()
                  ->after('adviser');

            $table->foreign('homeroom_coordinator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }
};
