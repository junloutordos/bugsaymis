<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CID — Competitions & Winnings.
     *
     * competitions:  one row per competition entry, logged by an employee.
     *   competition_type 'employee' → employees compete (creator usually among them)
     *   competition_type 'student'  → students compete; creator is the coach,
     *                                 other employees taggable as co-coaches
     *
     * competition_participants: one row per person on the entry.
     *   Exactly one of user_id / student_id is set (application-enforced).
     *   student_id has no FK — the legacy `students` table is MyISAM.
     *   role: participant (has an award slot) | coach | co_coach (credit only)
     */
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('competition_type', ['employee', 'student'])->default('employee');
            $table->string('organizer')->nullable();
            $table->string('venue')->nullable();
            $table->enum('level', ['campus', 'inter_campus', 'regional', 'national', 'international']);
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->boolean('represents_pshs')->default(false);
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['competition_type', 'level']);
            $table->index('school_year_id');
            $table->index('date_from');
        });

        Schema::create('competition_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->nullable()->comment('FK to legacy students.id (MyISAM, no constraint)');
            $table->enum('role', ['participant', 'coach', 'co_coach'])->default('participant');
            $table->string('award')->nullable()->comment('null = participation only');
            $table->timestamps();

            $table->index(['competition_id', 'role']);
            $table->index('user_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_participants');
        Schema::dropIfExists('competitions');
    }
};
