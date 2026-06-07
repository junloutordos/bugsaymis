<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('procurement_item_id')->nullable()->constrained('procurement_items')->nullOnDelete();
            $table->unsignedSmallInteger('item_no')->default(1);
            $table->string('unit', 50);
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->decimal('abc', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_items');
    }
};
