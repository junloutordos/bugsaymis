<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->string('supplier_name', 255);
            $table->string('supplier_address', 500)->nullable();
            $table->string('supplier_tin', 50)->nullable();
            $table->string('supplier_contact', 100)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quotation_received_at')->nullable();
            $table->string('quotation_file_s3_key', 500)->nullable();
            $table->string('quotation_filename', 255)->nullable();
            $table->decimal('quotation_total', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_suppliers');
    }
};
