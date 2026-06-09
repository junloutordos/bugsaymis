<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')->nullable()->after('nfc_uuid');
            $table->foreign('school_year_id')->references('id')->on('school_years')->restrictOnDelete();
        });

        // Backfill to current school year
        $currentSyId = DB::table('school_years')->where('is_current', true)->value('id');
        if ($currentSyId) {
            DB::table('classrooms')->whereNull('school_year_id')->update(['school_year_id' => $currentSyId]);
        }

        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')->nullable(false)->change();

            // Replace global unique on code with per-year unique
            $table->dropUnique(['code']);
            $table->unique(['code', 'school_year_id'], 'classrooms_code_school_year_id_unique');

            // Replace global unique on nfc_uuid with per-year unique so same physical tag
            // can be reused across school years (UUID is copied when cloning classrooms)
            $table->dropUnique(['nfc_uuid']);
            $table->unique(['nfc_uuid', 'school_year_id'], 'classrooms_nfc_uuid_school_year_id_unique');

            $table->index('school_year_id', 'classrooms_school_year_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex('classrooms_school_year_id_index');
            $table->dropUnique('classrooms_nfc_uuid_school_year_id_unique');
            $table->dropUnique('classrooms_code_school_year_id_unique');
            $table->unique('code');
            $table->unique('nfc_uuid');
            $table->dropForeign(['school_year_id']);
            $table->dropColumn('school_year_id');
        });
    }
};
