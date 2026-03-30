<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfh_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // Time In
            $table->timestamp('time_in')->nullable();
            $table->string('time_in_photo_file_id')->nullable();   // Google Drive file ID
            $table->string('time_in_photo_link')->nullable();       // Google Drive public link

            // Time Out
            $table->timestamp('time_out')->nullable();
            $table->string('time_out_photo_file_id')->nullable();
            $table->string('time_out_photo_link')->nullable();

            // Context capture
            $table->string('ip_address', 45)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            // One record per user per day
            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_attendances');
    }
};
