<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map old values to new before altering the column
        DB::statement("UPDATE academic_terms SET term_type = '1st_semester' WHERE term_type = 'first'");
        DB::statement("UPDATE academic_terms SET term_type = '2nd_semester' WHERE term_type = 'second'");
        // 'summer' stays the same

        DB::statement("ALTER TABLE academic_terms MODIFY COLUMN term_type ENUM('1st_semester','2nd_semester','summer','trimester','quarter') NOT NULL DEFAULT '1st_semester'");
    }

    public function down(): void
    {
        DB::statement("UPDATE academic_terms SET term_type = 'first'  WHERE term_type = '1st_semester'");
        DB::statement("UPDATE academic_terms SET term_type = 'second' WHERE term_type = '2nd_semester'");
        DB::statement("DELETE FROM academic_terms WHERE term_type IN ('trimester','quarter')");

        DB::statement("ALTER TABLE academic_terms MODIFY COLUMN term_type ENUM('first','second','summer') NOT NULL DEFAULT 'first'");
    }
};
