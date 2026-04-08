<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dtr_records
            MODIFY COLUMN attendance_status
            ENUM('present','absent','half_day','on_leave','on_official_business','suspended','holiday','wfh')
            NOT NULL DEFAULT 'present'
        ");
    }

    public function down(): void
    {
        // Revert any wfh records to absent before removing the enum value
        DB::statement("UPDATE dtr_records SET attendance_status = 'absent' WHERE attendance_status = 'wfh'");

        DB::statement("
            ALTER TABLE dtr_records
            MODIFY COLUMN attendance_status
            ENUM('present','absent','half_day','on_leave','on_official_business','suspended','holiday')
            NOT NULL DEFAULT 'present'
        ");
    }
};
