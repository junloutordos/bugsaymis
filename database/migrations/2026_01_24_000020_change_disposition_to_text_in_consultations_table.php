<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class ChangeDispositionToTextInConsultationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('consultations') || ! Schema::hasColumn('consultations', 'disposition')) {
            return;
        }

        try {
            $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = null;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE consultations MODIFY disposition TEXT NULL');
        } elseif ($driver === 'pgsql' || $driver === 'postgres') {
            DB::statement('ALTER TABLE consultations ALTER COLUMN disposition TYPE TEXT');
        } elseif ($driver === 'sqlite') {
            // SQLite cannot alter column types easily; skip since column likely already fits TEXT semantics
        } else {
            // Fallback: attempt MySQL-style ALTER
            DB::statement('ALTER TABLE consultations MODIFY disposition TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('consultations') || ! Schema::hasColumn('consultations', 'disposition')) {
            return;
        }

        try {
            $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = null;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE consultations MODIFY disposition VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql' || $driver === 'postgres') {
            DB::statement('ALTER TABLE consultations ALTER COLUMN disposition TYPE VARCHAR(255)');
        } elseif ($driver === 'sqlite') {
            // Skipping reversal on sqlite
        } else {
            DB::statement('ALTER TABLE consultations MODIFY disposition VARCHAR(255) NULL');
        }
    }
}
