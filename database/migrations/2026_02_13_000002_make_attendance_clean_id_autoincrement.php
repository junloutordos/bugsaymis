<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('attendance_clean')) return;

        try {
            // Modify `id` column to be BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY.
            DB::statement('ALTER TABLE `attendance_clean` CHANGE `id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
        } catch (\Throwable $e) {
            // Log and continue; migration should not break deploy.
            \Log::warning('Failed to modify attendance_clean.id: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_clean')) return;

        try {
            // Revert to non auto-increment int (best-effort)
            DB::statement('ALTER TABLE `attendance_clean` CHANGE `id` `id` INT NOT NULL');
        } catch (\Throwable $e) {
            \Log::warning('Failed to revert attendance_clean.id modification: '.$e->getMessage());
        }
    }
};
