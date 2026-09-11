<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadence model for committee accomplishment submissions:
     *   monthly | quarterly | end_of_rating_period | annually | varies | one_time
     * Lives on CommitteeTask (different tasks in the same committee can
     * legitimately have different cadences — e.g. a secretary's monthly
     * minutes vs. a member's annual report). Committee.default_submission
     * _frequency only pre-fills new tasks created on that committee's
     * board; it does not cascade to existing tasks.
     */
    public function up(): void
    {
        Schema::table('committee_tasks', function (Blueprint $table) {
            $table->string('submission_frequency', 30)->nullable()->after('priority')
                  ->comment('monthly|quarterly|end_of_rating_period|annually|varies|one_time');
        });

        Schema::table('committees', function (Blueprint $table) {
            $table->string('default_submission_frequency', 30)->nullable()->after('member_load_units')
                  ->comment('Pre-fills new board tasks; monthly|quarterly|end_of_rating_period|annually|varies|one_time');
        });
    }

    public function down(): void
    {
        Schema::table('committee_tasks', function (Blueprint $table) {
            $table->dropColumn('submission_frequency');
        });

        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn('default_submission_frequency');
        });
    }
};
