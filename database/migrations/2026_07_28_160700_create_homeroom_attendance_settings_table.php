<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editable per-key settings for the Homeroom Attendance module (currently
     * just the deduction points used on the monthly Record on Attendance and
     * Punctuality). Absence of a row = fall back to the CIM 7.0 defaults
     * hardcoded in the service, so an empty table reproduces the manual's
     * values exactly. Mirrors the bell_schedule_settings key/value pattern.
     */
    public function up(): void
    {
        Schema::create('homeroom_attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->json('value');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_attendance_settings');
    }
};
