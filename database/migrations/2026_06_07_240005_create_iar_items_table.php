<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iar_id')->constrained('inspection_acceptance_reports')->cascadeOnDelete();
            $table->foreignId('supply_item_id')->nullable()->constrained('supply_items')->nullOnDelete();
            $table->string('description', 500);
            $table->string('unit', 50);
            $table->decimal('quantity_ordered', 12, 3)->default(0);
            $table->decimal('quantity_delivered', 12, 3)->default(0);
            $table->decimal('quantity_accepted', 12, 3)->default(0);
            $table->decimal('quantity_rejected', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->string('rejection_reason', 500)->nullable();
            $table->boolean('is_posted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iar_items');
    }
};
