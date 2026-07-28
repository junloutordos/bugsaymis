<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand step (blue-green safe) of trimming subject-level Class Record
     * attendance down to Present/Absent/Tardy, with Excused/Unexcused now
     * decided by the Homeroom Adviser/Registrar's Class Admission Slip
     * workflow instead of being a status value a subject teacher picks.
     *
     * The status enum is WIDENED (not narrowed) here — 'tardy' is added
     * alongside every existing value — so any request from a stale cached
     * frontend bundle mid-deploy still succeeds. All existing rows are
     * remapped into the new {present,absent,tardy} + excused_status shape
     * immediately, so historical data reads correctly under the new code
     * from this deploy on. A later, separate migration narrows the enum
     * once no old code paths remain — do not do that here.
     *
     * uniform_status is left untouched (column stays, app code just stops
     * using it this deploy) — dropping it is a separate later migration too.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE class_record_attendance_records
            MODIFY COLUMN status ENUM(
                'present', 'absent', 'late', 'tardy', 'excused',
                'cut_class', 'unexcused_absence', 'excused_absence',
                'excused_tardy', 'unexcused_tardy'
            ) NULL
        ");

        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->enum('excused_status', ['n_a', 'excused', 'unexcused'])->default('n_a')->after('status');
            $table->foreignId('admission_slip_id')->nullable()->after('excused_status')
                ->constrained('class_admission_slips')->nullOnDelete();
            $table->boolean('synced_from_homeroom')->default(false)->after('admission_slip_id');
        });

        DB::table('class_record_attendance_records')->where('status', 'late')->update(['status' => 'tardy']);
        DB::table('class_record_attendance_records')->where('status', 'cut_class')->update(['status' => 'absent']);
        DB::table('class_record_attendance_records')->where('status', 'unexcused_absence')
            ->update(['status' => 'absent', 'excused_status' => 'unexcused']);
        DB::table('class_record_attendance_records')->where('status', 'excused_absence')
            ->update(['status' => 'absent', 'excused_status' => 'excused']);
        DB::table('class_record_attendance_records')->where('status', 'excused_tardy')
            ->update(['status' => 'tardy', 'excused_status' => 'excused']);
        DB::table('class_record_attendance_records')->where('status', 'unexcused_tardy')
            ->update(['status' => 'tardy', 'excused_status' => 'unexcused']);
        DB::table('class_record_attendance_records')->where('status', 'excused')
            ->update(['status' => null, 'excused_status' => 'excused']);
    }

    public function down(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admission_slip_id');
            $table->dropColumn(['excused_status', 'synced_from_homeroom']);
        });

        DB::statement("
            UPDATE class_record_attendance_records SET status = 'late' WHERE status = 'tardy'
        ");

        DB::statement("
            ALTER TABLE class_record_attendance_records
            MODIFY COLUMN status ENUM(
                'present', 'absent', 'late', 'excused',
                'cut_class', 'unexcused_absence', 'excused_absence',
                'excused_tardy', 'unexcused_tardy'
            ) NULL
        ");
    }
};
