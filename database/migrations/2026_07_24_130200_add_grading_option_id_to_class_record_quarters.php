<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-quarter grading option. Each quarter may use a different grading option
 * (e.g. a subject whose grading is not consistent across quarters). Resolution
 * is quarter.grading_option_id ?? class_records.grading_option_id, so old code
 * that reads the record-level option keeps working during blue-green rollout.
 *
 * Backfill: seed every existing quarter with its record's current option.
 * Additive/safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_record_quarters', function (Blueprint $table) {
            $table->foreignId('grading_option_id')->nullable()->after('class_record_id')
                ->constrained('grading_options')->nullOnDelete();
        });

        DB::statement('
            UPDATE class_record_quarters crq
            JOIN class_records cr ON cr.id = crq.class_record_id
            SET crq.grading_option_id = cr.grading_option_id
            WHERE crq.grading_option_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('class_record_quarters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grading_option_id');
        });
    }
};
