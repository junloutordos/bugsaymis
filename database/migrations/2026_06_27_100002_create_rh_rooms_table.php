<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_rooms')) {
            return;
        }

        Schema::create('rh_rooms', function (Blueprint $table) {
            $table->id();
            $table->enum('residence_hall', ['BRH', 'GRH']);
            $table->string('room_number', 20);
            $table->tinyInteger('capacity')->default(4);
            $table->tinyInteger('floor')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['residence_hall', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_rooms');
    }
};
