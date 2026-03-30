<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add deferred foreign key constraints for Faculty Loading tables.
     *
     * Constraints omitted in earlier migrations because referenced
     * tables did not yet exist at that point in migration order:
     *
     *   1. class_schedules.section_id          → sections.id
     *      (sections table created in 2026_01_21_000200, before FL tables)
     *
     *   2. load_assignments.section_id         → sections.id
     *      (same; load_assignments is nullable, so nullOnDelete)
     *
     *   3. faculty_committee_assignments.committee_id → committees.id
     *      (committees table created in migration 000012)
     *
     * Skipped on SQLite (test environment) — SQLite requires PRAGMA
     * foreign_keys=ON and does not support ALTER TABLE ADD CONSTRAINT.
     * MySQL production databases get full referential integrity.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // sections.id is INT (signed) — align our columns before adding FKs
        DB::statement('ALTER TABLE class_schedules MODIFY section_id INT NOT NULL');
        DB::statement('ALTER TABLE load_assignments MODIFY section_id INT NULL');

        // 1. class_schedules.section_id → sections.id
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreign('section_id', 'fk_cs_section_id')
                  ->references('id')
                  ->on('sections')
                  ->cascadeOnDelete();
        });

        // 2. load_assignments.section_id → sections.id (nullable)
        Schema::table('load_assignments', function (Blueprint $table) {
            $table->foreign('section_id', 'fk_la_section_id')
                  ->references('id')
                  ->on('sections')
                  ->nullOnDelete();
        });

        // 3. faculty_committee_assignments.committee_id → committees.id
        Schema::table('faculty_committee_assignments', function (Blueprint $table) {
            $table->foreign('committee_id', 'fk_fca_committee_id')
                  ->references('id')
                  ->on('committees')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('faculty_committee_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_fca_committee_id');
        });

        Schema::table('load_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_la_section_id');
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_cs_section_id');
        });
    }
};
