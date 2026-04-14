<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            // HR Officer (Stage 1) columns — inserted before division_chief_id
            $table->unsignedBigInteger('hr_officer_id')->nullable()->after('supporting_document');
            $table->string('hr_officer_action', 20)->nullable()->after('hr_officer_id');   // certified | rejected
            $table->timestamp('hr_officer_at')->nullable()->after('hr_officer_action');
            $table->text('hr_officer_remarks')->nullable()->after('hr_officer_at');

            $table->foreign('hr_officer_id')->references('id')->on('users')->nullOnDelete();
        });

        // Extend status ENUM to include hr_verified
        DB::statement("ALTER TABLE leave_applications MODIFY COLUMN status ENUM('pending','hr_verified','forwarded','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert status ENUM (remove hr_verified)
        DB::statement("ALTER TABLE leave_applications MODIFY COLUMN status ENUM('pending','forwarded','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropForeign(['hr_officer_id']);
            $table->dropColumn(['hr_officer_id', 'hr_officer_action', 'hr_officer_at', 'hr_officer_remarks']);
        });
    }
};
