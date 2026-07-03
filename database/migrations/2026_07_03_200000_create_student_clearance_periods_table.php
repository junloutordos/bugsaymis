<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_clearance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->string('title');
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->json('target_grade_levels')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_year_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_clearance_periods');
    }
};
