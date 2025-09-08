<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ict_equipments', function (Blueprint $table) {
            $table->id();
            $table->string('property_no', 50)->nullable();
            $table->unsignedInteger('device_type')->nullable();
            $table->string('description', 50)->nullable();
            $table->string('location', 50)->nullable();
            $table->date('date_entry')->nullable();
            $table->unsignedInteger('unit')->nullable();
            $table->unsignedInteger('owner')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('remarks', 100)->nullable();
            $table->string('encodedby', 100)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->date('date_acquired')->nullable();
            $table->string('serialno', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('qr_code_path')->nullable(); // new field for QR code image
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ict_equipments');
    }
};
