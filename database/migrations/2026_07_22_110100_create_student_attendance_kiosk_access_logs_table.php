<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance_kiosk_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kiosk_device_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('event', ['unlocked', 'locked', 'expired', 'failed']);
            $table->string('reason', 100)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['kiosk_device_id', 'occurred_at'], 'attendance_access_device_time_idx');
            $table->index(['user_id', 'occurred_at'], 'attendance_access_user_time_idx');
            $table->foreign('kiosk_device_id')
                ->references('id')->on('student_attendance_devices')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_kiosk_access_logs');
    }
};
