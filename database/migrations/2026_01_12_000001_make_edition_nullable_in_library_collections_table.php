<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        try {
            if (Schema::hasTable('library_collections') && Schema::hasColumn('library_collections', 'edition')) {
                // Use raw SQL to alter column to nullable to avoid requiring doctrine/dbal
                DB::statement('ALTER TABLE `library_collections` MODIFY `edition` VARCHAR(255) NULL');
            }
        } catch (\Throwable $e) {
            // ignore errors during schema change
        }
    }

    public function down()
    {
        try {
            if (Schema::hasTable('library_collections') && Schema::hasColumn('library_collections', 'edition')) {
                DB::statement('ALTER TABLE `library_collections` MODIFY `edition` VARCHAR(255) NOT NULL');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
