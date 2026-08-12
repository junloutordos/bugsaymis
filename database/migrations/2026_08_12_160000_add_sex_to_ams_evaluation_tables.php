<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable 'sex' column to the two AMS evaluation tables so walk-in
 * respondents (participant_type='walkin', no linked User/Student row) can
 * report their sex at birth directly on the evaluation form. Registered
 * participants (employee/student) still resolve sex from their linked
 * User/Student record at export time — this column is only populated for
 * walk-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ams_activity_evaluations', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('evaluator_name');
        });

        Schema::table('ams_activity_tws_evaluations', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('evaluator_name');
        });
    }

    public function down(): void
    {
        Schema::table('ams_activity_evaluations', function (Blueprint $table) {
            $table->dropColumn('sex');
        });

        Schema::table('ams_activity_tws_evaluations', function (Blueprint $table) {
            $table->dropColumn('sex');
        });
    }
};
