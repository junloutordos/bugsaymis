<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ict_pms_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ict_pms_id');
            $table->date('schedule_date');
            $table->timestamps();

            $table->foreign('ict_pms_id')
                  ->references('id')
                  ->on('ict_pms')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_pms_dates');
    }
};
