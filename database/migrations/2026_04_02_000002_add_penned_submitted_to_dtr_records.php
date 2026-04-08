<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            // WFH attendance link — set during DTR generation when WFH data was used
            $table->unsignedBigInteger('wfh_attendance_id')->nullable()->after('leave_application_id');
            $table->foreign('wfh_attendance_id')->references('id')->on('wfh_attendances')->nullOnDelete();

            // Set by the employee when they finalise their penned entries for the month.
            // Once set, the employee can no longer edit penned entries for these records.
            // Only HR / Administrator can reset this (unlockPenned action).
            $table->timestamp('penned_submitted_at')->nullable()->after('penned_at');
            $table->unsignedBigInteger('penned_submitted_by')->nullable()->after('penned_submitted_at');
            $table->foreign('penned_submitted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropForeign(['wfh_attendance_id']);
            $table->dropForeign(['penned_submitted_by']);
            $table->dropColumn(['wfh_attendance_id', 'penned_submitted_at', 'penned_submitted_by']);
        });
    }
};
