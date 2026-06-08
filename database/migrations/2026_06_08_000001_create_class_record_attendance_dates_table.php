<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_attendance_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_quarter_id')
                ->constrained('class_record_quarters')
                ->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['class_record_quarter_id', 'date'], 'attendance_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_attendance_dates');
    }
};
