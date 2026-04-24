<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_day_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
                  ->unique();
            $table->time('classes_start')->comment('Earliest class start time');
            $table->time('classes_end')->comment('Latest time a class can end');
            $table->json('blocked_periods')->nullable()->comment('Array of {start, end, label} blocks where no class can be placed');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_day_configs');
    }
};
