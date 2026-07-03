<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratory Reservation Form (PSHS-00-F-CID-05).
 * Flow: requester (teacher/student) -> endorsed by Subject Teacher/Unit Head
 * -> approved by SRS/SRA. Endorse & approve are PIN/digitally signed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 40)->unique();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->string('grade_level_section', 100)->nullable();
            $table->unsignedSmallInteger('number_of_students')->nullable();
            $table->string('subject', 150)->nullable();
            $table->string('teacher_in_charge', 150)->nullable();
            $table->date('date_start');
            $table->date('date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete(); // preferred lab
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name', 150)->nullable();
            $table->string('requester_type', 20)->default('teacher'); // teacher | student
            $table->json('student_group')->nullable(); // names when group
            $table->foreignId('endorsed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('endorsed_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            // pending | endorsed | approved | declined | cancelled | completed
            $table->string('status', 20)->default('pending')->index();
            $table->text('decline_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_reservations');
    }
};
