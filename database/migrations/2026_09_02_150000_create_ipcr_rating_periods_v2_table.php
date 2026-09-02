<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_rating_periods_v2', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->index(['year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_rating_periods_v2');
    }
};
