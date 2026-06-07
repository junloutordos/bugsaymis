<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_item_id')->constrained('supply_items');
            $table->date('transaction_date');
            $table->enum('reference_type', ['IAR', 'RIS', 'RETURN', 'ADJUSTMENT', 'BEGINNING', 'DISPOSAL']);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->decimal('qty_received', 12, 3)->default(0);
            $table->decimal('qty_issued', 12, 3)->default(0);
            $table->decimal('qty_returned', 12, 3)->default(0);
            $table->decimal('balance_after', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['supply_item_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_ledger_entries');
    }
};
