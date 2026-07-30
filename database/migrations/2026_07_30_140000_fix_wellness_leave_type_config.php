<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Wellness Leave (WL) was seeded without an annual day allotment and
     * without creditable/deductible tracking. CSC MC No. 1, s. 2026 grants
     * 5 days of Wellness Leave per year to permanent, temporary, casual,
     * substitute, coterminous, and contractual government employees.
     *
     * This migration brings the existing leave_types row in line with that
     * circular so WL balances are tracked and deducted like SPL/FL.
     */
    public function up(): void
    {
        DB::table('leave_types')
            ->where('code', 'WL')
            ->update([
                'days_per_year'  => 5,
                'is_creditable'  => true,
                'is_deductible'  => true,
                'updated_at'     => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('leave_types')
            ->where('code', 'WL')
            ->update([
                'days_per_year'  => null,
                'is_creditable'  => false,
                'is_deductible'  => false,
                'updated_at'     => now(),
            ]);
    }
};
