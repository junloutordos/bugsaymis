<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_units', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')->nullable()->after('is_active');
            $table->foreign('school_year_id')->references('id')->on('school_years')->restrictOnDelete();
        });

        // Backfill to current school year
        $currentSyId = DB::table('school_years')->where('is_current', true)->value('id');
        if ($currentSyId) {
            DB::table('academic_units')->whereNull('school_year_id')->update(['school_year_id' => $currentSyId]);
        }

        Schema::table('academic_units', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')->nullable(false)->change();

            // Replace global unique on code with per-year unique
            $table->dropUnique(['code']);
            $table->unique(['code', 'school_year_id'], 'academic_units_code_school_year_id_unique');
            $table->index('school_year_id', 'academic_units_school_year_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('academic_units', function (Blueprint $table) {
            $table->dropIndex('academic_units_school_year_id_index');
            $table->dropUnique('academic_units_code_school_year_id_unique');
            $table->unique('code');
            $table->dropForeign(['school_year_id']);
            $table->dropColumn('school_year_id');
        });
    }
};
