<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Period-scope committee ratings: one accomplishment/rating row per
     * (assignment, plan, rating period). Legacy rows keep NULL period.
     * Blue-green safe: old code's updateOrCreate on (assignment, plan)
     * simply keeps writing the NULL-period row.
     */
    public function up(): void
    {
        Schema::table('faculty_committee_accomplishments', function (Blueprint $table) {
            $table->foreignId('rating_period_id')
                ->nullable()
                ->after('work_distribution_plan_id')
                ->constrained('ipcr_rating_periods')
                ->nullOnDelete();

            // Add the wider unique BEFORE dropping the old one — the
            // fca_assignment_fk FK needs an index on its column at all times.
            $table->unique(
                ['faculty_committee_assignment_id', 'work_distribution_plan_id', 'rating_period_id'],
                'fca_assignment_plan_period_unique'
            );
            $table->dropUnique('fca_assignment_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_committee_accomplishments', function (Blueprint $table) {
            $table->unique(
                ['faculty_committee_assignment_id', 'work_distribution_plan_id'],
                'fca_assignment_plan_unique'
            );
            $table->dropUnique('fca_assignment_plan_period_unique');
            $table->dropConstrainedForeignId('rating_period_id');
        });
    }
};
