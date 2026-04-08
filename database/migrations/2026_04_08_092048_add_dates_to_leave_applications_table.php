<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change leave_details from ENUM to VARCHAR so new SPL detail values
        // (graduation, enrollment, wedding, burial, accident) are accepted.
        DB::statement("ALTER TABLE leave_applications MODIFY leave_details VARCHAR(100) NULL");

        Schema::table('leave_applications', function (Blueprint $table) {
            // JSON array of individually selected dates (ISO "YYYY-MM-DD").
            // Enables non-consecutive multi-date leave applications.
            // date_from / date_to remain as the min/max of this array.
            $table->json('dates')->nullable()->after('date_to')
                ->comment('Array of selected leave dates; may be non-consecutive');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('dates');
        });

        DB::statement("ALTER TABLE leave_applications MODIFY leave_details ENUM(
            'within_philippines','abroad',
            'in_hospital','out_patient',
            'master_degree','bar_board_review',
            'other'
        ) NULL");
    }
};
