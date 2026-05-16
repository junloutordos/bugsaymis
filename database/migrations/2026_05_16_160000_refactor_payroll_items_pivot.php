<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

        // MySQL allows multiple NULLs in a unique index, so unmatched rows (NULL user) are fine
        if (!$indexes->contains('payroll_items_per_user_month')) {
            DB::statement('ALTER TABLE payroll_items ADD UNIQUE payroll_items_per_user_month (matched_user_id, month, year)');
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
