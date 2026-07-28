<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_flag_attendance_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->enum('event_type', ['flag_ceremony', 'flag_retreat']);
            $table->date('date');
            $table->foreignId('taken_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'event_type', 'date'], 'hmrm_flag_att_section_event_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_flag_attendance_dates');
    }
};
