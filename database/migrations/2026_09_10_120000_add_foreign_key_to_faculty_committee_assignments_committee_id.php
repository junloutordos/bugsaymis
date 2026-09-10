<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * faculty_committee_assignments.committee_id was created as a plain
 * nullable unsignedBigInteger with no FK — deleting a Committee row left
 * every assignment referencing it silently dangling, and by extension its
 * synced EmployeeFunction row (and anything already materialized into
 * IPCR V2 from it) stuck forever, since nothing else ever re-checks
 * whether committee_id still resolves to a real committee.
 *
 * The application-level fix (CommitteeAssignmentController::destroyCommittee())
 * now cleans these up explicitly before deleting a committee. This FK is a
 * DB-level safety net for any other/future code path that deletes a
 * Committee row directly. nullOnDelete() (not cascadeOnDelete()) — a
 * committee's own deletion must not silently wipe FacultyLoad/IPCR history
 * by cascading through several more tables; a null committee_id on an
 * otherwise-orphaned assignment is what the app-level cleanup already
 * expects to find and finish reconciling on its next sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Null out any rows already dangling (pointing at a committee that
        // no longer exists) before adding the constraint — MySQL rejects
        // adding a FK when existing data would violate it.
        DB::table('faculty_committee_assignments')
            ->whereNotNull('committee_id')
            ->whereNotIn('committee_id', function ($query) {
                $query->select('id')->from('committees');
            })
            ->update(['committee_id' => null]);

        Schema::table('faculty_committee_assignments', function (Blueprint $table) {
            $table->foreign('committee_id')
                ->references('id')->on('committees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faculty_committee_assignments', function (Blueprint $table) {
            $table->dropForeign(['committee_id']);
        });
    }
};
