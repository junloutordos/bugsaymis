<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opts Wellness Leave (WL) into auto-granting its full annual entitlement
 * (5 days/year, per CSC MC No. 1, s. 2026) — a plain data update on the
 * type-definition row only, mirrors the pattern used by
 * 2026_07_30_140000_fix_wellness_leave_type_config.php. Does not touch
 * leave_credits/leave_applications; the actual per-employee credit-row
 * backfill for already-affected applications is a separate, explicitly
 * reviewed data remediation, not part of this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_types')
            ->where('code', 'WL')
            ->update(['auto_grant_annual' => true]);
    }

    public function down(): void
    {
        DB::table('leave_types')
            ->where('code', 'WL')
            ->update(['auto_grant_annual' => false]);
    }
};
