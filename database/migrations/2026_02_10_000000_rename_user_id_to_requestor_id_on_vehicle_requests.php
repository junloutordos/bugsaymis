<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('vehicle_requests') && Schema::hasColumn('vehicle_requests', 'user_id')) {
            // Use raw SQL statements to avoid requiring Doctrine DBAL at runtime for renames
            try {
                DB::statement('ALTER TABLE `vehicle_requests` ADD COLUMN `requestor_id` BIGINT UNSIGNED NULL AFTER `user_id`');
            } catch (\Throwable $e) { /* ignore if column exists */ }

            try {
                DB::statement('UPDATE `vehicle_requests` SET `requestor_id` = `user_id`');
            } catch (\Throwable $e) { /* ignore */ }

            try {
                DB::statement('ALTER TABLE `vehicle_requests` ADD CONSTRAINT `vehicle_requests_requestor_id_foreign` FOREIGN KEY (`requestor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE');
            } catch (\Throwable $e) { /* ignore if constraint exists or cannot be added */ }

            // Attempt to drop old foreign key and column
            try {
                DB::statement('ALTER TABLE `vehicle_requests` DROP FOREIGN KEY `vehicle_requests_user_id_foreign`');
            } catch (\Throwable $e) { /* ignore if FK not found */ }

            try {
                DB::statement('ALTER TABLE `vehicle_requests` DROP COLUMN `user_id`');
            } catch (\Throwable $e) { /* ignore if column cannot be dropped */ }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vehicle_requests') && Schema::hasColumn('vehicle_requests', 'requestor_id')) {
            // Reverse: recreate `user_id`, copy values back, recreate FK, drop `requestor_id`
            try {
                DB::statement('ALTER TABLE `vehicle_requests` ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `requestor_id`');
            } catch (\Throwable $e) { /* ignore */ }

            try {
                DB::statement('UPDATE `vehicle_requests` SET `user_id` = `requestor_id`');
            } catch (\Throwable $e) { /* ignore */ }

            try {
                DB::statement('ALTER TABLE `vehicle_requests` ADD CONSTRAINT `vehicle_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE');
            } catch (\Throwable $e) { /* ignore */ }

            try {
                DB::statement('ALTER TABLE `vehicle_requests` DROP FOREIGN KEY `vehicle_requests_requestor_id_foreign`');
            } catch (\Throwable $e) { /* ignore */ }

            try {
                DB::statement('ALTER TABLE `vehicle_requests` DROP COLUMN `requestor_id`');
            } catch (\Throwable $e) { /* ignore */ }
        }
    }
};
