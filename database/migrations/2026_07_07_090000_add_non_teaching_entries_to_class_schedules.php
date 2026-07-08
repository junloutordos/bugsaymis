<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Non-teaching calendar entries (consultation, research blocks, advising,
     * meetings, etc.) share the class_schedules table so conflict detection
     * keeps working across one dataset:
     *
     *   entry_type = 'class'        → regular teaching session (default)
     *   entry_type = 'non_teaching' → titled block, no subject/load linkage
     *
     * A non-teaching block must reference a faculty member and/or a section,
     * so subject_id, user_id and section_id become nullable (enforced at the
     * application layer).
     *
     * Written defensively (idempotent guards + type-preserving modify):
     * the first prod run of this migration failed mid-way on section_id —
     * prod has FK fk_cs_section_id and signed-int section_id/sections.id
     * (dev: unsigned, no FK), and changing the type to unsigned broke FK
     * compatibility (MySQL error 3780). MySQL DDL is non-transactional, so
     * the earlier statements had already been applied; every step below is
     * safe to re-run, and section_id keeps whatever type the current schema
     * has so the prod-only FK stays intact.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('class_schedules', 'entry_type')) {
            Schema::table('class_schedules', function (Blueprint $table) {
                $table->string('entry_type', 20)->default('class')->after('academic_term_id');
            });
        }
        if (! Schema::hasColumn('class_schedules', 'title')) {
            Schema::table('class_schedules', function (Blueprint $table) {
                $table->string('title', 120)->nullable()->after('entry_type');
            });
        }
        if (! Schema::hasColumn('class_schedules', 'category')) {
            Schema::table('class_schedules', function (Blueprint $table) {
                $table->string('category', 30)->nullable()->after('title');
            });
        }

        $this->makeNullablePreservingType('user_id');
        $this->makeNullablePreservingType('subject_id');
        $this->makeNullablePreservingType('section_id');
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'title', 'category']);
        });

        $this->makeNullablePreservingType('user_id', nullable: false);
        $this->makeNullablePreservingType('subject_id', nullable: false);
        $this->makeNullablePreservingType('section_id', nullable: false);
    }

    /**
     * Change only the NULL-ability of a class_schedules column, keeping its
     * exact current COLUMN_TYPE — a type change would break FK compatibility
     * where the environment has FKs (e.g. prod's fk_cs_section_id).
     */
    private function makeNullablePreservingType(string $column, bool $nullable = true): void
    {
        $col = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['class_schedules', $column]
        );

        if (! $col) {
            return;
        }

        $isNullable = $col->IS_NULLABLE === 'YES';
        if ($isNullable === $nullable) {
            return; // already in the desired state (partial prior run)
        }

        $null = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `class_schedules` MODIFY `{$column}` {$col->COLUMN_TYPE} {$null}");
    }
};
