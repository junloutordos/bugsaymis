<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('from_officer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_officer_id')->constrained('users')->restrictOnDelete();
            $table->date('transfer_date');
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('property_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('property_transfers')->cascadeOnDelete();
            $table->foreignId('property_item_id')->constrained('property_items')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['transfer_id', 'property_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_transfer_items');
        Schema::dropIfExists('property_transfers');
    }
};
