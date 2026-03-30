<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ict_pms', function (Blueprint $table) {
            $table->id();

            // Title for the PMS schedule
            $table->string('title')->nullable();

            // Foreign Key to Users (who performed the PMS)
            $table->unsignedBigInteger('performed_by')->nullable();

            $table->date('pms_date');
            $table->enum('status', ['Pending', 'Ongoing', 'Completed'])->default('Pending');
            $table->string('remarks')->nullable();
            $table->timestamps();

            // FK: performed_by
            $table->foreign('performed_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_pms');
    }
};
