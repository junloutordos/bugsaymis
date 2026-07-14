<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * session_type distinguishes a regular full-period teaching session from
     * an Independent Learning Period (ILP) session — a subject's own weekly
     * session that only occupies the first 30 minutes of its period, freeing
     * the remainder for other bookings. Sibling to entry_type ('class' vs
     * 'non_teaching'); only meaningful when entry_type = 'class'.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('class_schedules', 'session_type')) {
            Schema::table('class_schedules', function (Blueprint $table) {
                $table->enum('session_type', ['regular', 'ilp'])->default('regular')->after('entry_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('class_schedules', 'session_type')) {
            Schema::table('class_schedules', function (Blueprint $table) {
                $table->dropColumn('session_type');
            });
        }
    }
};
