<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add academic_unit_id to users so faculty/staff can be associated
     * with a specific academic unit (JHS, SHS, SST, Admin).
     *
     * Nullable — admin and support staff may not belong to any unit.
     * Placed after sst_position_id to group faculty-loading columns together.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'academic_unit_id')) {
                $table->unsignedBigInteger('academic_unit_id')
                      ->nullable()
                      ->after('sst_position_id')
                      ->comment('FK to academic_units — which unit this user belongs to');

                if (config('database.default') !== 'sqlite') {
                    $table->foreign('academic_unit_id')
                          ->references('id')
                          ->on('academic_units')
                          ->nullOnDelete();
                }

                $table->index('academic_unit_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'academic_unit_id')) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['academic_unit_id']);
                }
                $table->dropIndex(['academic_unit_id']);
                $table->dropColumn('academic_unit_id');
            }
        });
    }
};
