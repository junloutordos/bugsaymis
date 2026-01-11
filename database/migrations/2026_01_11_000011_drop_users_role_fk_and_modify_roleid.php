<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key if exists, then change column to TEXT
        try {
            DB::statement('ALTER TABLE `users` DROP FOREIGN KEY `users_role_id_foreign`');
        } catch (\Throwable $e) {
            // ignore if it doesn't exist
        }

        try {
            DB::statement('ALTER TABLE `users` MODIFY `role_id` TEXT NULL');
        } catch (\Throwable $e) {
            // ignore errors to keep migration idempotent
        }
    }

    public function down(): void
    {
        // Attempt to revert: change to BIGINT UNSIGNED and re-add FK
        try {
            DB::statement('ALTER TABLE `users` MODIFY `role_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            DB::statement('ALTER TABLE `users` ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
