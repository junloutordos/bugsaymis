<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Two changes to load_assignments:
     *
     * 1. Add designation_id (nullable FK → designations)
     *    For assignment_type = 'admin' | 'committee', this links the row
     *    to the specific designation (e.g. "Department Head – Science").
     *    Nullable so that teaching/research rows are unaffected.
     *
     * 2. Add unique index on the teaching slot:
     *    (academic_term_id, subject_id, section_id)
     *    Prevents the same subject+section pair from being assigned to
     *    more than one faculty within the same term.
     *    Partial-unique semantics (only when subject_id + section_id are
     *    both non-null) are enforced at the application layer because
     *    MySQL does not support partial/filtered indexes natively.
     */
    public function up(): void
    {
        Schema::table('load_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('load_assignments', 'designation_id')) {
                $table->unsignedBigInteger('designation_id')
                      ->nullable()
                      ->after('description')
                      ->comment('FK to designations — set for admin/committee assignment types');

                if (config('database.default') !== 'sqlite') {
                    $table->foreign('designation_id')
                          ->references('id')
                          ->on('designations')
                          ->nullOnDelete();
                }

                $table->index('designation_id');
            }
        });

        // Teaching-slot uniqueness: one faculty per subject+section per term.
        // Only meaningful when both subject_id and section_id are non-null
        // (i.e. assignment_type = 'teaching'). Application layer must check
        // for nulls before relying on this index.
        if (config('database.default') !== 'sqlite') {
            $indexName = 'uq_la_teaching_slot';
            $exists = collect(DB::select("SHOW INDEX FROM load_assignments WHERE Key_name = '{$indexName}'"))->isNotEmpty();

            if (! $exists) {
                DB::statement(
                    'ALTER TABLE load_assignments
                     ADD UNIQUE KEY uq_la_teaching_slot (academic_term_id, subject_id, section_id)'
                );
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE load_assignments DROP INDEX IF EXISTS uq_la_teaching_slot');
        }

        Schema::table('load_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('load_assignments', 'designation_id')) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['designation_id']);
                }
                $table->dropIndex(['designation_id']);
                $table->dropColumn('designation_id');
            }
        });
    }
};
