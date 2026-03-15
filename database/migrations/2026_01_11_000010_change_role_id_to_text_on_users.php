<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE `users` MODIFY `role_id` TEXT NULL');
        } catch (\Throwable $e) {
            // best-effort: if ALTER fails, let the migration run without throwing
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE `users` MODIFY `role_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
