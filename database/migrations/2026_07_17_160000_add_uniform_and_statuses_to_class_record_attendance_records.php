<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance detail statuses (CC/UA/EU/ET/UT) + per-date uniform check.
     * Enum widen + new nullable column — additive, blue-green safe (old code
     * keeps writing the original four values).
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE class_record_attendance_records
            MODIFY COLUMN status ENUM(
                'present', 'absent', 'late', 'excused',
                'cut_class', 'unexcused_absence', 'excused_absence',
                'excused_tardy', 'unexcused_tardy'
            ) NULL
        ");

        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->enum('uniform_status', ['complete', 'incomplete'])->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->dropColumn('uniform_status');
        });

        // Fold detail statuses back into the legacy four before shrinking the enum.
        DB::statement("
            UPDATE class_record_attendance_records SET status = CASE
                WHEN status IN ('cut_class', 'unexcused_absence') THEN 'absent'
                WHEN status = 'excused_absence' THEN 'excused'
                WHEN status IN ('excused_tardy', 'unexcused_tardy') THEN 'late'
                ELSE status
            END
        ");

        DB::statement("
            ALTER TABLE class_record_attendance_records
            MODIFY COLUMN status ENUM('present', 'absent', 'late', 'excused') NULL
        ");
    }
};
