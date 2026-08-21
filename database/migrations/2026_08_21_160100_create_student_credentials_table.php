<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Password-login credentials for AtlasGo's student email+password
     * registration path (StudentAttendance\Api\RegisterController::
     * registerStudent()). Kept separate from the legacy MyISAM `students`
     * table (which is bulk re-imported and treated as read-only) and from
     * the main Atlas `users` table (students must never get a row there).
     */
    public function up(): void
    {
        Schema::create('student_credentials', function (Blueprint $table) {
            $table->id();
            // References students.id (legacy MyISAM table — no FK constraint)
            $table->unsignedInteger('student_id')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 30)->default('pending_verification');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_credentials');
    }
};
