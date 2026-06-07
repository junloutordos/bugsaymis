<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('par_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('par_id')->constrained('property_acknowledgment_receipts')->cascadeOnDelete();
            $table->foreignId('property_item_id')->nullable()->constrained('property_items')->nullOnDelete();
            $table->string('description');
            $table->string('unit', 50);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('par_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('par_items');
    }
};
