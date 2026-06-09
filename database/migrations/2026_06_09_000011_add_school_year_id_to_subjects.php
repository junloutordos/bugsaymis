<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')->nullable()->after('is_active');
            $table->foreign('school_year_id')->references('id')->on('school_years')->restrictOnDelete();
        });

        // Backfill to current school year
        $currentSyId = DB::table('school_years')->where('is_current', true)->value('id');
        if ($currentSyId) {
            DB::table('subjects')->whereNull('school_year_id')->update(['school_year_id' => $currentSyId]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')->nullable(false)->change();

            // Replace global unique on code with per-year unique
            $table->dropUnique(['code']);
            $table->unique(['code', 'school_year_id'], 'subjects_code_school_year_id_unique');
            $table->index('school_year_id', 'subjects_school_year_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex('subjects_school_year_id_index');
            $table->dropUnique('subjects_code_school_year_id_unique');
            $table->unique('code');
            $table->dropForeign(['school_year_id']);
            $table->dropColumn('school_year_id');
        });
    }
};
