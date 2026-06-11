<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oed_issuance_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oed_issuance_id')->constrained('oed_issuances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['oed_issuance_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oed_issuance_acknowledgments');
    }
};
