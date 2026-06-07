<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_stock_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_item_id')->unique()->constrained('supply_items')->cascadeOnDelete();
            $table->decimal('balance_quantity', 12, 3)->default(0);
            $table->decimal('average_unit_cost', 12, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_stock_cards');
    }
};
