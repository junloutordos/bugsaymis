<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('e.g. Science Hall 101');
            $table->string('code', 30)->unique()->comment('Short reference code, e.g. SH-101');
            $table->enum('classroom_type', [
                'lecture',
                'laboratory',
                'science_lab',
                'physics_lab',
                'chemistry_lab',
                'biology_lab',
                'mathematics_lab',
                'ict_lab',
                'language_lab',
                'gymnasium',
                'auditorium',
                'specialized',
            ])->default('lecture');
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->string('building', 100)->nullable();
            $table->unsignedTinyInteger('floor')->nullable();

            // Optional link to existing rooms table (for facility request cross-referencing)
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();

            $table->boolean('is_available')->default(true)
                  ->comment('If false, room is under maintenance or reserved');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['classroom_type', 'is_available']);
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
