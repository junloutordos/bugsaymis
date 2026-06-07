<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_acknowledgment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('par_number', 50)->unique();
            $table->date('issue_date');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->enum('status', ['active', 'returned', 'transferred', 'superseded'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('received_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_acknowledgment_receipts');
    }
};
