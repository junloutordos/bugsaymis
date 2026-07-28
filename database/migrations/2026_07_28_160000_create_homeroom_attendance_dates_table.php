<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_attendance_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('taken_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'date'], 'hmrm_att_date_section_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_attendance_dates');
    }
};
