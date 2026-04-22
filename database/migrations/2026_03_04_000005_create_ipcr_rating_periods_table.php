<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_rating_periods', function (Blueprint $table) {
            $table->id();
            $table->string('label');          // e.g. "January 1 to June 30, 2025"
            $table->unsignedSmallInteger('year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_rating_periods');
    }
};
