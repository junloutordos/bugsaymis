<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index(['lastname', 'firstname'], 'students_guard_name_idx');
            $table->index('pisaysystemID', 'students_guard_pshs_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_guard_name_idx');
            $table->dropIndex('students_guard_pshs_id_idx');
        });
    }
};
