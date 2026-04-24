<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add school_year_id FK to sections alongside the legacy syid column.
     *
     * The sections table carries a legacy integer `syid` that references
     * the old school-year table. This migration adds a proper FK column
     * `school_year_id` pointing to the `school_years` table introduced by
     * the Faculty Loading module (migration 2026_03_27_000001).
     *
     * Both columns coexist intentionally:
     *   - syid         — legacy, used by other modules; do not remove here
     *   - school_year_id — new FK used by Faculty Loading queries
     *
     * Placed after `is_active` to keep all FL-added columns together.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            if (! Schema::hasColumn('sections', 'school_year_id')) {
                $table->unsignedBigInteger('school_year_id')
                      ->nullable()
                      ->after('is_active')
                      ->comment('FK to school_years (FL module). Legacy syid column preserved separately.');

                if (config('database.default') !== 'sqlite') {
                    $table->foreign('school_year_id')
                          ->references('id')
                          ->on('school_years')
                          ->nullOnDelete();
                }

                $table->index('school_year_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            if (Schema::hasColumn('sections', 'school_year_id')) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['school_year_id']);
                }
                $table->dropIndex(['school_year_id']);
                $table->dropColumn('school_year_id');
            }
        });
    }
};
