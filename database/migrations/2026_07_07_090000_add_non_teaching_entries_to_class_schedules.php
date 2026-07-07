<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
     * application layer — additive/expand-only, blue-green safe).
     */
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->string('entry_type', 20)->default('class')->after('academic_term_id');
            $table->string('title', 120)->nullable()->after('entry_type');
            $table->string('category', 30)->nullable()->after('title');

            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('subject_id')->nullable()->change();
            $table->unsignedInteger('section_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'title', 'category']);

            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreignId('subject_id')->nullable(false)->change();
            $table->unsignedInteger('section_id')->nullable(false)->change();
        });
    }
};
