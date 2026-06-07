<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ris_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ris_id')->constrained('requisition_issue_slips')->cascadeOnDelete();
            $table->foreignId('supply_item_id')->constrained('supply_items');
            $table->decimal('quantity_requested', 12, 3);
            $table->decimal('quantity_issued', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ris_items');
    }
};
