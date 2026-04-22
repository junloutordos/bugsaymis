<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the existing `committees` table for Faculty Loading.
     *
     * The committees table was created in migration 2026_03_06_000002
     * with a minimal schema (name, head_id, description).
     * This migration adds the columns required by the Faculty Loading
     * module without removing any existing columns.
     *
     * New columns added:
     *   code                   — acronym, e.g., ACAD, RDEV
     *   committee_type         — classification for filtering/reporting
     *   max_members            — soft membership cap
     *   chairperson_title      — title for the committee head
     *   chairperson_load_units — load credit for chair/co-chair role
     *   member_load_units      — load credit for regular member
     *   is_active              — soft-disable flag
     */
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            if (! Schema::hasColumn('committees', 'code')) {
                $table->string('code', 30)->nullable()->after('name')
                      ->comment('Acronym, e.g., ACAD, RDEV, DISC');
            }

            if (! Schema::hasColumn('committees', 'committee_type')) {
                $table->string('committee_type', 40)->default('other')->after('code')
                      ->comment('academic|research|student_affairs|administrative|co_curricular|discipline|finance|health_and_safety|other');
            }

            if (! Schema::hasColumn('committees', 'max_members')) {
                $table->unsignedTinyInteger('max_members')->nullable()->after('description')
                      ->comment('Soft cap on membership (null = unlimited)');
            }

            if (! Schema::hasColumn('committees', 'chairperson_title')) {
                $table->string('chairperson_title', 80)->default('Chairperson')->after('max_members');
            }

            if (! Schema::hasColumn('committees', 'chairperson_load_units')) {
                $table->decimal('chairperson_load_units', 4, 2)->default(1.00)->after('chairperson_title')
                      ->comment('Load units credited for chairperson/co-chair role');
            }

            if (! Schema::hasColumn('committees', 'member_load_units')) {
                $table->decimal('member_load_units', 4, 2)->default(0.50)->after('chairperson_load_units')
                      ->comment('Load units credited for regular member role');
            }

            if (! Schema::hasColumn('committees', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('member_load_units');
            }
        });
    }

    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $cols = [
                'code', 'committee_type', 'max_members',
                'chairperson_title', 'chairperson_load_units',
                'member_load_units', 'is_active',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('committees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
