<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('attendance_clean')) return;

        if (! Schema::hasColumn('attendance_clean', 'id')) {
            // Use raw statement to add an auto-increment primary key as the first column.
            DB::statement('ALTER TABLE `attendance_clean` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_clean')) return;

        if (Schema::hasColumn('attendance_clean', 'id')) {
            // Drop the id column
            DB::statement('ALTER TABLE `attendance_clean` DROP COLUMN `id`');
        }
    }
};
