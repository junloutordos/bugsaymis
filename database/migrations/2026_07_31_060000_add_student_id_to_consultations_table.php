<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give student clinic consultations an unambiguous student link.
     *
     * `requestor_id` is polymorphic in the legacy Health Services module: it
     * can point either to students or users depending on `requestor_type`.
     * A dedicated nullable student_id keeps the Class Record integration
     * simple, indexable, and safe without changing any legacy columns.
     *
     * Additive-only — safe for blue-green deployment.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->integer('student_id')->nullable()->after('requestor')->index();
        });

        if (
            Schema::hasColumn('consultations', 'requestor_id')
            && Schema::hasColumn('consultations', 'requestor_type')
        ) {
            DB::table('consultations')
                ->where('requestor_type', 'student')
                ->whereNotNull('requestor_id')
                ->update(['student_id' => DB::raw('requestor_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
