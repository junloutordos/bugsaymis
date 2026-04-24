<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'salary_grade')) {
                return;
            }
            // Add after emp_category if it exists, otherwise at end
            $afterCol = Schema::hasColumn('users', 'emp_category') ? 'emp_category' : null;
            $col = $table->unsignedTinyInteger('salary_grade')->nullable();
            if ($afterCol) $col->after($afterCol);
            $table->unsignedTinyInteger('salary_step')->nullable()->default(1)->after('salary_grade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['salary_grade', 'salary_step']);
        });
    }
};
