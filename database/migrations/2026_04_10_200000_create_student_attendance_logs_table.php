<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('raw_barcode', 50)->comment('Exact value received from scanner');
            $table->dateTime('scan_time');
            $table->enum('type', ['in', 'out']);
            $table->enum('source', ['gate_scanner', 'manual', 'api'])->default('gate_scanner');
            $table->string('gate_location', 100)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->comment('User ID if manually entered');
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — records are NEVER modified after creation

            // students table is MyISAM — FK not supported; using plain index
            $table->index('student_id');
            $table->index('scan_time');
            $table->index(['student_id', 'scan_time']);

            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_logs');
    }
};
