<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dedicated ID-card number, separate from `employee_no` (used by
            // payroll matching/parsing and payslips). Format: E13-YYYY-MM-XXX.
            // Never used as a lookup key — verification is via id_verification_token.
            $table->string('employee_idno_new', 30)->nullable()->unique()->after('employee_no');

            // Captured once via the mandatory post-login prompt; also used to
            // build employee_idno_new and kept for HR audit/edit purposes.
            $table->unsignedSmallInteger('hired_year')->nullable()->after('employee_idno_new');
            $table->unsignedTinyInteger('hired_month')->nullable()->after('hired_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_idno_new', 'hired_year', 'hired_month']);
        });
    }
};
