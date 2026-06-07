<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ics_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ics_id')->constrained('inventory_custodian_slips')->cascadeOnDelete();
            $table->foreignId('property_item_id')->nullable()->constrained('property_items')->nullOnDelete();
            $table->string('description');
            $table->string('unit', 50);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->date('estimated_useful_life')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('ics_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ics_items');
    }
};
