<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 50)->unique();
            $table->string('description', 500);
            $table->string('unit', 50);
            $table->foreignId('category_id')->constrained('supply_categories');
            $table->decimal('estimated_unit_cost', 12, 4)->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->text('specifications')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_items');
    }
};
