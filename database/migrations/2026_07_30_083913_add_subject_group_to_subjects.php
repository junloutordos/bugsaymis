<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups subjects that share one class record across multiple teachers —
 * currently only 'PEHM' (Physical Education, Health, Music at the same
 * grade level). Null (default) means the subject behaves as before: its own
 * standalone class record, one teacher.
 *
 * The Class Record creation wizard uses this to detect when a teacher is
 * setting up PE/Health/Music for a section and offers to join an existing
 * shared record (created by one of the other two teachers) instead of
 * creating a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('subject_group', 30)->nullable()->after('subject_type');
            $table->index('subject_group');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex(['subject_group']);
            $table->dropColumn('subject_group');
        });
    }
};
