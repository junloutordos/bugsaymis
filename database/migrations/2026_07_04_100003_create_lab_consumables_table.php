<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratory consumables & reagents master (CIM 4.4 §3.1.8–3.1.11).
 * Chemicals carry an SDS file. Stock is tracked in lots (FIFO/FEFO) with a
 * running ledger in lab_stock_movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_consumables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('consumable'); // consumable | reagent | chemical
            $table->string('unit_of_measure', 30)->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('unit', 100)->nullable(); // owning academic unit
            $table->boolean('is_chemical')->default(false);
            $table->string('sds_path')->nullable(); // Safety Data Sheet, S3 key
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->boolean('tracks_expiry')->default(false);
            $table->string('status', 20)->default('active'); // active | inactive
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_consumables');
    }
};
