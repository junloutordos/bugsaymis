<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendance_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('kiosk_device_id')->nullable()->after('recorded_by');
            $table->uuid('scan_uuid')->nullable()->after('kiosk_device_id');
            $table->dateTime('device_scan_time')->nullable()->after('scan_uuid');
            $table->string('capture_method', 30)->nullable()->after('device_scan_time');

            $table->unique('scan_uuid');
            $table->index(['kiosk_device_id', 'scan_time']);
            $table->foreign('kiosk_device_id')
                ->references('id')
                ->on('student_attendance_devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['kiosk_device_id']);
            $table->dropUnique(['scan_uuid']);
            $table->dropIndex(['kiosk_device_id', 'scan_time']);
            $table->dropColumn(['kiosk_device_id', 'scan_uuid', 'device_scan_time', 'capture_method']);
        });
    }
};
