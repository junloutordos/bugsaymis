<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_items', function (Blueprint $table) {
            $table->id();
            $table->string('property_number', 50)->unique();
            $table->string('description');
            $table->foreignId('category_id')->constrained('property_categories')->restrictOnDelete();
            $table->string('unit', 50)->default('unit');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 2)->storedAs('ROUND(quantity * unit_cost, 2)');
            $table->date('acquisition_date');
            $table->enum('acquisition_mode', ['purchase', 'donation', 'transfer', 'fabricated'])->default('purchase');
            $table->string('supplier_name')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('iar_item_id')->nullable()->constrained('iar_items')->nullOnDelete();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->foreignId('current_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['serviceable', 'unserviceable', 'disposed', 'transferred', 'lost'])->default('serviceable');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index('current_officer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_items');
    }
};
