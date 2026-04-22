<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('collection_categories') && ! Schema::hasColumn('collection_categories', 'student_borrowing_days')) {
            Schema::table('collection_categories', function (Blueprint $table) {
                $table->unsignedSmallInteger('student_borrowing_days')->nullable()->after('name');
                $table->unsignedSmallInteger('employee_borrowing_days')->nullable()->after('student_borrowing_days');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('collection_categories')) {
            Schema::table('collection_categories', function (Blueprint $table) {
                if (Schema::hasColumn('collection_categories', 'student_borrowing_days')) {
                    $table->dropColumn('student_borrowing_days');
                }
                if (Schema::hasColumn('collection_categories', 'employee_borrowing_days')) {
                    $table->dropColumn('employee_borrowing_days');
                }
            });
        }
    }
};
