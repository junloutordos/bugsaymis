<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-lot stock for consumables/reagents to support FIFO / FEFO
 * (First-In-First-Out / First-Expire-First-Out) issuance and expiry
 * segregation (CIM 4.4 §3.1.9–3.1.10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_consumable_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_consumable_id')->constrained('lab_consumables')->cascadeOnDelete();
            $table->string('lot_no', 100)->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->date('received_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_cost', 12, 4)->default(0);
            // available | depleted | expired | disposed
            $table->string('status', 20)->default('available');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['lab_consumable_id', 'expiry_date']);
            $table->index(['lab_consumable_id', 'received_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_consumable_lots');
    }
};
