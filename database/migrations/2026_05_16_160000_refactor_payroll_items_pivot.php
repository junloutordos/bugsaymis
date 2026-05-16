<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NUMERIC_COLS = [
        'basic_salary', 'salary_increase', 'additional_compensation',
        'pera', 'sala', 'hazard_pay', 'longevity_pay', 'others_bonuses', 'gross_earnings',
        'lawop', 'pvp_overpayment', 'gsis_contribution', 'bir_tax',
        'wht_hazard', 'wht_cna', 'longevity_tax',
        'philhealth_contribution', 'philhealth_differential',
        'hdmf_contribution', 'hdmf_additional',
        'gsis_salary_loan', 'gsis_policy_loan', 'gsis_consolidated_loan',
        'gsis_emergency_loan', 'gsis_mpl', 'gsis_gfal', 'gsis_cpl', 'gsis_mpl_lite',
        'hdmf_salary_loan', 'hdmf_calamity', 'hdmf_housing', 'hdmf_multipurpose', 'hdmf_mp2',
        'landbank_loan', 'credit_union',
        'total_deductions', 'net_pay', 'first_half_amount', 'second_half_amount',
    ];

    public function up(): void
    {
        // 1. Create the pivot table (idempotent — prod may already have it from a prior partial run)
        if (!Schema::hasTable('payroll_batch_items')) {
            Schema::create('payroll_batch_items', function (Blueprint $table) {
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('item_id');
                $table->primary(['batch_id', 'item_id']);
                $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
                $table->foreign('item_id')->references('id')->on('payroll_items')->onDelete('cascade');
            });
        }

        // 2. Populate pivot from existing items (batch_id is still on items)
        DB::statement('INSERT IGNORE INTO payroll_batch_items (batch_id, item_id)
            SELECT batch_id, id FROM payroll_items WHERE batch_id IS NOT NULL');

        // 3. Swap unique index: (batch_id, matched_user_id) → (matched_user_id, month, year)
        // Guard each step — prod may be in any partial state from a prior failed run
        $indexes = collect(DB::select("SHOW INDEX FROM payroll_items"))->pluck('Key_name');

        if ($indexes->contains('payroll_items_per_batch_user')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->dropForeign(['batch_id']);
                $table->dropUnique('payroll_items_per_batch_user');
                $table->index('batch_id');
                $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
            });
        }

        // 4. Deduplicate: prod may have multiple items per (matched_user_id, month, year) from before
        //    the merge-ADD rule was introduced. Merge them so the unique constraint can be added.
        if (!$indexes->contains('payroll_items_per_user_month')) {
            $this->deduplicateItems();
            DB::statement('ALTER TABLE payroll_items ADD UNIQUE payroll_items_per_user_month (matched_user_id, month, year)');
        }
    }

    private function deduplicateItems(): void
    {
        $groups = DB::table('payroll_items')
            ->selectRaw('matched_user_id, month, year, MIN(id) as keep_id')
            ->whereNotNull('matched_user_id')
            ->groupBy('matched_user_id', 'month', 'year')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $dupIds = DB::table('payroll_items')
                ->where('matched_user_id', $group->matched_user_id)
                ->where('month', $group->month)
                ->where('year', $group->year)
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            // Add numeric columns from duplicates into the keeper
            $sumSelect = implode(', ', array_map(fn($c) => "SUM(`$c`) as `$c`", self::NUMERIC_COLS));
            $sums = DB::table('payroll_items')->whereIn('id', $dupIds)->selectRaw($sumSelect)->first();

            $updates = [];
            foreach (self::NUMERIC_COLS as $col) {
                $updates[$col] = DB::raw("`$col` + " . round((float) ($sums->$col ?? 0), 2));
            }
            DB::table('payroll_items')->where('id', $group->keep_id)->update($updates);

            // Point pivot rows for duplicates to the keeper (INSERT IGNORE to skip collisions)
            foreach ($dupIds as $dupId) {
                $batchIds = DB::table('payroll_batch_items')->where('item_id', $dupId)->pluck('batch_id');
                foreach ($batchIds as $batchId) {
                    DB::table('payroll_batch_items')->insertOrIgnore(['batch_id' => $batchId, 'item_id' => $group->keep_id]);
                }
                DB::table('payroll_batch_items')->where('item_id', $dupId)->delete();
            }

            DB::table('payroll_items')->whereIn('id', $dupIds)->delete();
        }
    }

    public function down(): void
    {
        // payroll_items_per_user_month has matched_user_id as leading column — it backs the FK to users.
        // Drop that FK first so MySQL lets us freely drop/swap indexes on matched_user_id.
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign('payroll_items_matched_user_id_foreign');
        });

        DB::statement('ALTER TABLE payroll_items DROP INDEX payroll_items_per_user_month');

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
            $table->unique(['batch_id', 'matched_user_id'], 'payroll_items_per_batch_user');
            $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
            // per_batch_user has batch_id as leading, so matched_user_id needs its own index for the FK
            $table->index('matched_user_id', 'payroll_items_matched_user_id_index');
            $table->foreign('matched_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::dropIfExists('payroll_batch_items');
    }
};
