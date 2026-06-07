<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('account_code', 20)->nullable();
            $table->enum('type', ['semi_expendable', 'equipment']);
            $table->unsignedTinyInteger('useful_life_years')->default(5);
            $table->decimal('residual_rate', 5, 4)->default(0.0500);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_categories');
    }
};
