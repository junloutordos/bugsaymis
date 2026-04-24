<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add academic_unit_id to subjects so each subject can be scoped
     * to its owning academic unit (e.g. Grade 7 Science → JHS).
     *
     * Nullable — some subjects (cross-grade electives, SST core) may
     * not be pinned to a single unit.
     * Placed after grade_level to keep classification columns grouped.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('subjects', 'academic_unit_id')) {
                $table->unsignedBigInteger('academic_unit_id')
                      ->nullable()
                      ->after('grade_level')
                      ->comment('FK to academic_units — which unit owns this subject');

                if (config('database.default') !== 'sqlite') {
                    $table->foreign('academic_unit_id')
                          ->references('id')
                          ->on('academic_units')
                          ->nullOnDelete();
                }

                $table->index(['academic_unit_id', 'grade_level']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'academic_unit_id')) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['academic_unit_id']);
                }
                $table->dropIndex(['academic_unit_id', 'grade_level']);
                $table->dropColumn('academic_unit_id');
            }
        });
    }
};
