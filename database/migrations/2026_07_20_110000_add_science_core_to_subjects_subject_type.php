<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `subjects` MODIFY `subject_type` ENUM('lecture', 'laboratory', 'lecture_lab', 'research', 'elective', 'science_core') NOT NULL DEFAULT 'lecture'");
    }

    public function down(): void
    {
        DB::statement("UPDATE `subjects` SET `subject_type` = 'lecture' WHERE `subject_type` = 'science_core'");
        DB::statement("ALTER TABLE `subjects` MODIFY `subject_type` ENUM('lecture', 'laboratory', 'lecture_lab', 'research', 'elective') NOT NULL DEFAULT 'lecture'");
    }
};
