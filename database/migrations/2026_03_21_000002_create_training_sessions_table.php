<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('learning_program_id')
                  ->constrained('learning_programs')
                  ->cascadeOnDelete();

            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('venue')->nullable();          // Physical or online venue
            $table->string('mode')->default('face_to_face'); // face_to_face | online | blended
            $table->string('facilitator')->nullable();    // Name or org of facilitator

            $table->unsignedSmallInteger('max_participants')->default(30);

            $table->enum('status', [
                'scheduled',
                'ongoing',
                'completed',
                'cancelled',
            ])->default('scheduled');

            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
