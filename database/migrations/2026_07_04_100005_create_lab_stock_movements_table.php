<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock ledger for consumables/reagents — mirrors supply_ledger_entries.
 * Every receive/issue/return/adjustment/disposal writes a row with the
 * running balance_after so stock records are auditable (CIM 4.4 §3.1.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_consumable_id')->constrained('lab_consumables')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('lab_consumable_lots')->nullOnDelete();
            $table->date('transaction_date');
            // BEGINNING | RECEIVE | ISSUE | RETURN | ADJUSTMENT | DISPOSAL
            $table->string('reference_type', 20);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 60)->nullable();
            $table->decimal('qty_received', 12, 3)->default(0);
            $table->decimal('qty_issued', 12, 3)->default(0);
            $table->decimal('qty_returned', 12, 3)->default(0);
            $table->decimal('balance_after', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lab_consumable_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_stock_movements');
    }
};
