<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                  ->constrained('training_sessions')
                  ->cascadeOnDelete();

            $table->foreignId('employee_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->enum('attendance_status', [
                'registered',
                'attended',
                'absent',
                'excused',
            ])->default('registered');

            $table->enum('completion_status', [
                'pending',
                'completed',
                'not_completed',
            ])->default('pending');

            // Certificate
            $table->string('certificate_path')->nullable();
            $table->date('certificate_issued_date')->nullable();

            // Nomination/approval trail
            $table->enum('nomination_status', [
                'nominated',
                'approved',
                'disapproved',
            ])->default('nominated');

            $table->foreignId('approved_by')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Prevent duplicate registration per session
            $table->unique(['session_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};
