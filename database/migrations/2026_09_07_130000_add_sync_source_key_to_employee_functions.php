<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_functions', function (Blueprint $table) {
            // A stable marker identifying which Faculty Loading source this
            // auto-synced row came from — e.g. "load_assignment:123",
            // "committee_assignment:45", "personnel_plan:9". Load Assignment
            // rows still populate load_assignment_id too (unchanged), but
            // committee- and personnel-plan-sourced rows have no natural FK
            // of their own, so this single marker column (mirroring the
            // AgencyOutcome.sub_outcome marker trick already used for the
            // same problem in v1) is what re-sync keys updateOrCreate on.
            $table->string('sync_source_key')->nullable()->after('load_assignment_id');
            $table->index(['user_id', 'sync_source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_functions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'sync_source_key']);
            $table->dropColumn('sync_source_key');
        });
    }
};
