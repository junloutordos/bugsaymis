<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number', 50)->unique();
            $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->date('rfq_date');
            $table->unsignedTinyInteger('validity_days')->default(30);
            $table->string('delivery_place', 255)->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->text('purpose')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfqs');
    }
};
