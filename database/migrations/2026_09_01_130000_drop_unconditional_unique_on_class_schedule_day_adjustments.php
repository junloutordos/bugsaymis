<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original (academic_term_id, effective_date) unique constraint had
     * no status filter, so a CANCELLED adjustment permanently blocked ever
     * creating a new one for that same date — cancelling was meant to be a
     * "the regular schedule applies again" no-op, not a permanent lock.
     * MySQL has no partial/filtered unique index, so the "no two ACTIVE
     * (draft/published) adjustments on the same date" rule now lives purely
     * in ClassScheduleDayAdjustmentController::validatedData(), which already
     * excludes cancelled rows from its duplicate check.
     */
    public function up(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            // MySQL was using the unique index to satisfy the academic_term_id
            // foreign key — add a plain index first so dropping the unique
            // constraint below doesn't fail with "needed in a foreign key
            // constraint".
            $table->index('academic_term_id', 'cs_day_adjustment_term_idx');
            $table->dropUnique('cs_day_adjustment_term_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->dropIndex('cs_day_adjustment_term_idx');
            $table->unique(['academic_term_id', 'effective_date'], 'cs_day_adjustment_term_date_unique');
        });
    }
};
