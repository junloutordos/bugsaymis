<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop matched_user_id FK first (backed by payroll_items_per_user_month unique)
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign('payroll_items_matched_user_id_foreign');
        });

        // 2. Drop the per-user-month unique (now FK is gone so index is free to drop)
        $indexes = collect(DB::select("SHOW INDEX FROM payroll_items"))->pluck('Key_name')->unique();
        if ($indexes->contains('payroll_items_per_user_month')) {
            DB::statement('ALTER TABLE payroll_items DROP INDEX payroll_items_per_user_month');
        }

        // 3. Drop any standalone matched_user_id index left over from prior migrations (name varies per env)
        $indexes = collect(DB::select("SHOW INDEX FROM payroll_items"))->pluck('Key_name')->unique();
        foreach (['payroll_items_matched_user_id_index', 'payroll_items_matched_user_id_foreign'] as $ix) {
            if ($indexes->contains($ix)) {
                DB::statement("ALTER TABLE payroll_items DROP INDEX `{$ix}`");
            }
        }

        // 4. Swap batch_id index: plain → unique (batch_id, matched_user_id); restore both FKs
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']); // payroll_items_batch_id_index
            $table->unique(['batch_id', 'matched_user_id'], 'payroll_items_per_batch_user');
            $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
            $table->index('matched_user_id', 'payroll_items_matched_user_id_index');
            $table->foreign('matched_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 5. Clean pivot: remove cross-batch rows created by the now-abandoned merge design
        //    (rows where the pivot batch_id ≠ the item's own batch_id FK)
        DB::statement('DELETE pbi FROM payroll_batch_items pbi
            JOIN payroll_items pi ON pi.id = pbi.item_id
            WHERE pbi.batch_id != pi.batch_id');
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign('payroll_items_matched_user_id_foreign');
            $table->dropIndex('payroll_items_matched_user_id_index');
            $table->dropForeign(['batch_id']);
            $table->dropUnique('payroll_items_per_batch_user');
            $table->index('batch_id', 'payroll_items_batch_id_index');
            $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE payroll_items ADD UNIQUE payroll_items_per_user_month (matched_user_id, month, year)');

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->foreign('matched_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
