<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendance_devices', function (Blueprint $table) {
            $table->string('device_mode', 30)->default('guard_camera')->after('gate_location');
            $table->index(['device_mode', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('student_attendance_devices', function (Blueprint $table) {
            $table->dropIndex(['device_mode', 'is_active']);
            $table->dropColumn('device_mode');
        });
    }
};
