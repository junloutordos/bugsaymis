<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->dateTime('interview_date');
            $table->json('panel_members');   // array of user IDs
            $table->string('venue')->nullable();
            $table->string('format')->default('panel'); // panel, individual, demo-teaching
            $table->decimal('rating', 5, 2)->nullable(); // final averaged rating
            $table->text('remarks')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->timestamps();

            $table->index('application_id');
            $table->index('interview_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
