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
            // Academic Unit Head recommendation (CID teaching faculty only) —
            // sits between HR Officer certification and Division Chief recommendation.
            $table->unsignedBigInteger('auh_id')->nullable()->after('hr_officer_remarks');
            $table->string('auh_action', 20)->nullable()->after('auh_id');   // recommended | rejected
            $table->timestamp('auh_at')->nullable()->after('auh_action');
            $table->text('auh_remarks')->nullable()->after('auh_at');

            $table->foreign('auh_id')->references('id')->on('users')->nullOnDelete();
        });

        // Extend status ENUM to include auh_verified, between hr_verified and forwarded
        DB::statement("ALTER TABLE leave_applications MODIFY COLUMN status ENUM('pending','hr_verified','auh_verified','forwarded','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert status ENUM (remove auh_verified)
        DB::statement("ALTER TABLE leave_applications MODIFY COLUMN status ENUM('pending','hr_verified','forwarded','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropForeign(['auh_id']);
            $table->dropColumn(['auh_id', 'auh_action', 'auh_at', 'auh_remarks']);
        });
    }
};
