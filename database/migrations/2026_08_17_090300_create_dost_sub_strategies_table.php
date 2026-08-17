<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_sub_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dost_strategy_id')->constrained('dost_strategies')->onDelete('cascade');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_sub_strategies');
    }
};
