<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('facility_requests')) {
            // add requestor_id column
            try {
                DB::statement('ALTER TABLE `facility_requests` ADD COLUMN `requestor_id` BIGINT UNSIGNED NULL AFTER `requestor`');
            } catch (\Throwable $e) { /* ignore */ }

            // copy values from email -> requestor_id where possible
            try {
                DB::statement('UPDATE `facility_requests` fr JOIN `users` u ON fr.email = u.email SET fr.requestor_id = u.id');
            } catch (\Throwable $e) { /* ignore */ }

            // add FK
            try {
                DB::statement('ALTER TABLE `facility_requests` ADD CONSTRAINT `facility_requests_requestor_id_foreign` FOREIGN KEY (`requestor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
            } catch (\Throwable $e) { /* ignore */ }

            // drop division_chief_id FK if any and column
            try { DB::statement('ALTER TABLE `facility_requests` DROP FOREIGN KEY `facility_requests_division_chief_id_foreign`'); } catch (\Throwable $e) { /* ignore */ }
            try { DB::statement('ALTER TABLE `facility_requests` DROP COLUMN `division_chief_id`'); } catch (\Throwable $e) { /* ignore */ }

            // drop email column
            try { DB::statement('ALTER TABLE `facility_requests` DROP COLUMN `email`'); } catch (\Throwable $e) { /* ignore */ }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('facility_requests')) {
            // recreate email and division_chief_id columns (best-effort)
            try { DB::statement('ALTER TABLE `facility_requests` ADD COLUMN `email` varchar(191) NULL AFTER `requestor`'); } catch (\Throwable $e) { /* ignore */ }
            try { DB::statement('ALTER TABLE `facility_requests` ADD COLUMN `division_chief_id` bigint unsigned NULL AFTER `email`'); } catch (\Throwable $e) { /* ignore */ }

            // copy back email from users where requestor_id is present
            try { DB::statement('UPDATE `facility_requests` fr LEFT JOIN `users` u ON fr.requestor_id = u.id SET fr.email = u.email'); } catch (\Throwable $e) { /* ignore */ }

            // add FK for division_chief_id (best-effort)
            try { DB::statement('ALTER TABLE `facility_requests` ADD CONSTRAINT `facility_requests_division_chief_id_foreign` FOREIGN KEY (`division_chief_id`) REFERENCES `users`(`id`) ON DELETE SET NULL'); } catch (\Throwable $e) { /* ignore */ }

            // drop requestor_id FK & column
            try { DB::statement('ALTER TABLE `facility_requests` DROP FOREIGN KEY `facility_requests_requestor_id_foreign`'); } catch (\Throwable $e) { /* ignore */ }
            try { DB::statement('ALTER TABLE `facility_requests` DROP COLUMN `requestor_id`'); } catch (\Throwable $e) { /* ignore */ }
        }
    }
};
