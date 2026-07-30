<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags a LEAF grading category as belonging to one specific subject — used
 * by the PEHM shared class record, where one grading option has, e.g., a
 * "Summative Assessments" parent category with PE / Health / Music leaves
 * underneath it, each scored only by that subject's teacher.
 *
 * Null (the default, and every pre-existing row) means the category is not
 * subject-split — normal single-teacher class records are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_categories', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('parent_id')
                ->constrained('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grading_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
