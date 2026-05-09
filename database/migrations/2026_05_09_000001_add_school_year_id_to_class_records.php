<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_records', function (Blueprint $table) {
            $table->unsignedBigInteger('school_year_id')
                ->nullable()
                ->after('school_year')
                ->comment('FK → school_years.id');

            $table->foreign('school_year_id')
                ->references('id')
                ->on('school_years')
                ->nullOnDelete();
        });

        // Backfill: match existing string school_year to school_years.name
        DB::statement('
            UPDATE class_records cr
            JOIN school_years sy ON sy.name = cr.school_year
            SET cr.school_year_id = sy.id
        ');
    }

    public function down(): void
    {
        Schema::table('class_records', function (Blueprint $table) {
            $table->dropForeign(['school_year_id']);
            $table->dropColumn('school_year_id');
        });
    }
};
