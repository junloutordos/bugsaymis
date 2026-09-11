<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a CommitteeTask as materialized from a committee member's
     * roster "task" text (CommitteeTaskAutoSyncService) rather than
     * created manually on the board — lets the sync find/update its own
     * generated task by identity instead of matching on title text alone,
     * and lets the board optionally badge it as roster-sourced.
     */
    public function up(): void
    {
        Schema::table('committee_tasks', function (Blueprint $table) {
            $table->boolean('auto_synced_from_roster')->default(false)->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('committee_tasks', function (Blueprint $table) {
            $table->dropColumn('auto_synced_from_roster');
        });
    }
};
