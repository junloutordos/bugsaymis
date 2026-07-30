<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds 'cut_class' and 'cutting_suspected' as their own infraction_type
     * values — a teacher-asserted cut class (CIM 3.6/3.6.2, set directly on
     * the Class Record Attendance grid) now gets its own admission slip
     * classification ('cut_class'), separate from the pre-existing
     * mismatch-derived signal (subject Absent + homeroom Present/Tardy on
     * the same date), which is relabeled 'cutting_suspected' to make clear
     * to the Registrar it's a detected safety net, not a direct teacher
     * assertion — kept per product decision, both signals are surfaced.
     *
     * The legacy 'cutting' value is left in the enum (not dropped) so
     * historical slip rows issued before this deploy keep reading correctly;
     * no code path writes 'cutting' going forward.
     *
     * Additive/widening only — safe under blue-green (old code never writes
     * the two new values here, so it's a no-op for anything still running
     * old code mid-deploy).
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE class_admission_slips
            MODIFY COLUMN infraction_type ENUM('absence', 'tardy', 'cutting', 'cut_class', 'cutting_suspected') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::table('class_admission_slips')->whereIn('infraction_type', ['cut_class', 'cutting_suspected'])->update(['infraction_type' => 'cutting']);

        DB::statement("
            ALTER TABLE class_admission_slips
            MODIFY COLUMN infraction_type ENUM('absence', 'tardy', 'cutting') NOT NULL
        ");
    }
};
