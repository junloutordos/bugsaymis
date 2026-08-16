<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('cadence'); // 'quarter' | 'semester' | 'annual'
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('label'); // e.g. "Q1 2026", "1st Semester 2026", "FY 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('parent_period_id')->nullable()->constrained('spms_fiscal_periods')->nullOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_fiscal_periods');
    }
};
