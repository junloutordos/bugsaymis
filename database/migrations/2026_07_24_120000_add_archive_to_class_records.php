<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add reversible soft-delete (archive) support to class_records:
 * - widen the status enum to include 'archived'
 * - track who archived and when, and the status to restore to
 *
 * Additive/backward-compatible: existing code that writes draft/submitted/
 * checked keeps working; the new columns are nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE class_records MODIFY COLUMN status ENUM('draft', 'submitted', 'checked', 'archived') NOT NULL DEFAULT 'draft'");

        Schema::table('class_records', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('checked_by_id');
            $table->foreignId('archived_by_id')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('pre_archive_status', 20)->nullable()->after('archived_by_id')
                ->comment('Status the record held before archiving, restored on recover');
        });
    }

    public function down(): void
    {
        Schema::table('class_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by_id');
            $table->dropColumn(['archived_at', 'pre_archive_status']);
        });

        // Revert any archived rows to draft before narrowing the enum so the
        // MODIFY does not fail on out-of-range values.
        DB::table('class_records')->where('status', 'archived')->update(['status' => 'draft']);
        DB::statement("ALTER TABLE class_records MODIFY COLUMN status ENUM('draft', 'submitted', 'checked') NOT NULL DEFAULT 'draft'");
    }
};
