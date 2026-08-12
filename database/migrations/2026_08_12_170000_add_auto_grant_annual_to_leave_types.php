<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 'auto_grant_annual' to leave_types — opts a leave type into having
 * its full days_per_year entitlement auto-granted (earned = days_per_year)
 * the first time a leave_credits row is created for an employee/year,
 * instead of starting at earned = 0.
 *
 * Scoped narrowly: only leave types explicitly opted in are affected. VL,
 * SL, CTO, FL, SPL (and any other type) keep the existing "earned starts at
 * 0, HR posts actual accruals" behavior unless flagged. This is for flat-
 * rate universal entitlements (currently only WL — 5 days/year for
 * permanent/casual/contractual/coterminous/substitute per CSC MC No. 1,
 * s. 2026) where nobody "earns" it incrementally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('auto_grant_annual')->default(false)->after('days_per_year');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('auto_grant_annual');
        });
    }
};
