<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date')->index();
            $table->enum('type', [
                'regular',           // Full pay, no work
                'special_non_working', // 30% premium if worked
                'special_working',   // Treated as regular working day
                'local',             // LGU-declared
            ])->default('regular');
            $table->boolean('is_recurring')->default(false)
                ->comment('Repeats annually on same month/day regardless of year');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['holiday_date', 'type'], 'holidays_date_type_unique');
            $table->index(['holiday_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
