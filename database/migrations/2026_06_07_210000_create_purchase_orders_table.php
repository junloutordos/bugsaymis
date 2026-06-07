<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('po_number', 30)->nullable()->unique()
                  ->comment('Auto-numbered PO-YYYY-MM-XXXX on issue');
            $table->date('po_date')->nullable();

            // Link to the approved PR this PO was created from (optional)
            $table->foreignId('procurement_id')
                  ->nullable()
                  ->constrained('procurements')
                  ->nullOnDelete();

            // Supplier details
            $table->string('supplier_name', 200);
            $table->text('supplier_address')->nullable();
            $table->string('supplier_tin', 30)->nullable();

            // Delivery & payment terms
            $table->string('delivery_place', 200)->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('payment_terms', 100)->nullable()->default('30 days');
            $table->string('delivery_terms', 100)->nullable()->default('FOB Destination');

            $table->decimal('total_amount', 12, 2)->default(0)
                  ->comment('Recomputed from items on each change');

            $table->enum('status', [
                'draft',
                'pending_procurement',
                'pending_ocd',
                'issued',
                'cancelled',
            ])->default('draft');

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Procurement Officer review
            $table->foreignId('procurement_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('procurement_officer_at')->nullable();
            $table->text('procurement_officer_remarks')->nullable();

            // OCD (Campus Director) signature
            $table->foreignId('ocd_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ocd_at')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['procurement_id', 'status']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
