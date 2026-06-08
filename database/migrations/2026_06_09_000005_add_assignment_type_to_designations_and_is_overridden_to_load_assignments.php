<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->enum('assignment_type', ['teaching', 'research', 'admin', 'cocurricular', 'committee'])
                  ->default('admin')
                  ->after('load_units');
        });

        Schema::table('load_assignments', function (Blueprint $table) {
            $table->boolean('is_overridden')->default(false)->after('load_units');
        });

        // RESEARCH category → research
        DB::statement("
            UPDATE designations d
            JOIN designation_categories dc ON dc.id = d.designation_category_id
            SET d.assignment_type = 'research'
            WHERE dc.code = 'RESEARCH'
        ");

        // STUDENT_ORG, ALA, SCALE → cocurricular
        DB::statement("
            UPDATE designations d
            JOIN designation_categories dc ON dc.id = d.designation_category_id
            SET d.assignment_type = 'cocurricular'
            WHERE dc.code IN ('STUDENT_ORG', 'ALA', 'SCALE')
        ");

        // All others remain 'admin' (default)
    }

    public function down(): void
    {
        Schema::table('load_assignments', function (Blueprint $table) {
            $table->dropColumn('is_overridden');
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn('assignment_type');
        });
    }
};
