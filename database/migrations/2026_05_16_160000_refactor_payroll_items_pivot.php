<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the pivot table linking batches ↔ items (many-to-many)
        Schema::create('payroll_batch_items', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('item_id');
            $table->primary(['batch_id', 'item_id']);
            $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('payroll_items')->onDelete('cascade');
        });

        // 2. Populate pivot from existing items (batch_id is still on items)
        DB::statement('INSERT IGNORE INTO payroll_batch_items (batch_id, item_id)
            SELECT batch_id, id FROM payroll_items WHERE batch_id IS NOT NULL');

        // 3. Swap unique index: (batch_id, matched_user_id) → (matched_user_id, month, year)
        // Must drop FK before dropping the unique index it backs, then re-add FK with a plain index
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropUnique('payroll_items_per_batch_user');
            $table->index('batch_id'); // plain index so FK can be re-added
            $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
        });

        // MySQL allows multiple NULLs in a unique index, so unmatched rows (NULL user) are fine
        DB::statement('ALTER TABLE payroll_items ADD UNIQUE payroll_items_per_user_month (matched_user_id, month, year)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payroll_items DROP INDEX payroll_items_per_user_month');

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
            $table->unique(['batch_id', 'matched_user_id'], 'payroll_items_per_batch_user');
            $table->foreign('batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
        });

        Schema::dropIfExists('payroll_batch_items');
    }
};
