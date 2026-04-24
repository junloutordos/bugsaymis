<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * academic_units — organizational/academic divisions within the campus
     * (e.g. Junior High School, Senior High School, SST Program, Admin).
     *
     * unit_type values:
     *   junior_high  — Grades 7–10
     *   senior_high  — Grades 11–12
     *   sst          — Science & Technology track
     *   admin        — Administrative / non-teaching unit
     */
    public function up(): void
    {
        Schema::create('academic_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Short code, e.g. JHS, SHS, SST');
            $table->string('name', 100)->comment('Full name, e.g. Junior High School');
            $table->enum('unit_type', ['junior_high', 'senior_high', 'sst', 'admin'])
                  ->default('junior_high');
            $table->string('grade_band', 20)->nullable()
                  ->comment('Human-readable range, e.g. "Grades 7–10"');
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Unit head / principal');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('unit_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_units');
    }
};
