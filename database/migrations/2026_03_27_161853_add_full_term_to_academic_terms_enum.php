<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE academic_terms MODIFY COLUMN term_type ENUM('1st_semester','2nd_semester','summer','trimester','quarter','full_term') NOT NULL DEFAULT '1st_semester'");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM academic_terms WHERE term_type = 'full_term'");
        DB::statement("ALTER TABLE academic_terms MODIFY COLUMN term_type ENUM('1st_semester','2nd_semester','summer','trimester','quarter') NOT NULL DEFAULT '1st_semester'");
    }
};
