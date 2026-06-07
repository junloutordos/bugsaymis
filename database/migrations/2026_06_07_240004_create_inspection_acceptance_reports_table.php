<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('iar_number', 30)->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('supplier_name', 255);
            $table->string('supplier_address', 500)->nullable();
            $table->string('supplier_tin', 30)->nullable();
            $table->date('delivery_date');
            $table->date('inspection_date')->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('property_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'inspected', 'accepted', 'partial'])->default('draft');
            $table->text('remarks')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_acceptance_reports');
    }
};
