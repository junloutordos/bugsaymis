<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ict_equipments', function (Blueprint $table) {
            $table->id();

            $table->string('category', 50);                  // Equipment Category
            $table->foreignId('owner_id')                    // FK -> users.id
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('property_no', 50)->nullable();   // Property No
            $table->string('serial_no', 50)->nullable();                 // Serial No
            $table->string('description', 100);              // Description / Model
            $table->date('date_acquired')->nullable();       // Date Acquired
            $table->decimal('amount', 10, 2)->nullable();    // Amount
            $table->string('status', 50);                    // Status
            $table->string('location', 100);                 // Location
            $table->string('remarks', 255)->nullable();      // Remarks
            $table->string('qr_code_path')->nullable();      // QR Code image path
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ict_equipments');
    }
};
