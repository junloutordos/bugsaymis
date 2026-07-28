<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holiday / class-suspension calendar, managed by CID Chief/Administrator.
     * A null grade_level means campus-wide; a set grade_level scopes the
     * event to just that grade (e.g. a grade-specific activity day). Consumed
     * by SchoolCalendarService::isSchoolDay() — used to stop the
     * schedule-driven subject-attendance sync from auto-creating attendance
     * dates on days classes didn't actually happen, and to keep the monthly
     * Record on Attendance and Punctuality's school-days count honest.
     */
    public function up(): void
    {
        Schema::create('school_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('event_type', ['holiday', 'suspension']);
            $table->unsignedTinyInteger('grade_level')->nullable()->comment('NULL = campus-wide');
            $table->string('title');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['date', 'grade_level'], 'school_cal_date_grade_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_calendar_events');
    }
};
