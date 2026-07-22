<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('gate_location', 100);
            $table->char('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('paired_by');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['gate_location', 'is_active']);
            $table->foreign('paired_by')->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_devices');
    }
};
