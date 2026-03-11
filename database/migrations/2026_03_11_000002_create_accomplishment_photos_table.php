<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accomplishment_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accomplishment_id')->constrained()->cascadeOnDelete();
            $table->string('google_drive_file_id')->nullable();
            $table->string('google_drive_link')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accomplishment_photos');
    }
};
