<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            // employment_type is redundant — recruitment_type_id already defines the engagement type
            $table->dropColumn('employment_type');

            // Salary detail fields
            $table->unsignedTinyInteger('salary_step')->nullable()->default(1)->after('salary_grade');
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('salary_step');

            // Qualifications / standards (from job ad)
            $table->text('qualification_standards')->nullable()->after('monthly_salary');
            $table->text('duties_responsibilities')->nullable()->after('qualification_standards');
        });
    }

    public function down(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->string('employment_type')->nullable()->after('salary_grade');
            $table->dropColumn(['salary_step', 'monthly_salary', 'qualification_standards', 'duties_responsibilities']);
        });
    }
};
