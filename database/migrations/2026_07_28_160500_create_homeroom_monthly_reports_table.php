<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->unsignedSmallInteger('school_days_count')->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('coordinator_remarks')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'year', 'month'], 'hmrm_report_section_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_monthly_reports');
    }
};
