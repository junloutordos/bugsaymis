<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_item_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_supplier_id')->constrained('rfq_suppliers')->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->cascadeOnDelete();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->boolean('is_awarded')->default(false);
            $table->unique(['rfq_supplier_id', 'rfq_item_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_item_quotations');
    }
};
