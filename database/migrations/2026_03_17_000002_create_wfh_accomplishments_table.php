<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfh_accomplishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wfh_attendance_id')->constrained('wfh_attendances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Proof — either a photo uploaded to Google Drive or an external link
            $table->enum('proof_type', ['photo', 'link'])->nullable();
            $table->string('proof_link')->nullable();                    // if proof_type = 'link'
            $table->string('google_drive_file_id')->nullable();          // if proof_type = 'photo'
            $table->string('google_drive_link')->nullable();
            $table->string('file_name')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'wfh_attendance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_accomplishments');
    }
};
