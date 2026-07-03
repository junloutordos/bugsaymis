<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('topic')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_speakers');
    }
};
