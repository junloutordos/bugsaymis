<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add sst_position_id to the users table so that faculty members
     * can be linked to their SST position level (SST-I through SST-V).
     *
     * This enables the Overload Pay module to automatically look up the
     * applicable salary grade and annual rate from the salary_schedules
     * table when creating an overload computation.
     *
     * The column is nullable so that non-teaching staff (admin, support)
     * are not forced to have an SST position assigned.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'sst_position_id')) {
                $table->unsignedBigInteger('sst_position_id')
                      ->nullable()
                      ->after('position')
                      ->comment('FK to sst_positions — determines salary grade for overload pay');

                // Skip FK enforcement on SQLite (test environment)
                if (config('database.default') !== 'sqlite') {
                    $table->foreign('sst_position_id')
                          ->references('id')
                          ->on('sst_positions')
                          ->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sst_position_id')) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['sst_position_id']);
                }
                $table->dropColumn('sst_position_id');
            }
        });
    }
};
