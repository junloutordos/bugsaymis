<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->date('end_date');
            $table->time('end_time')->nullable();
            $table->string('total_hours', 25)->nullable();
            $table->string('venue')->nullable();
            $table->string('resource_person')->nullable();
            $table->string('banner')->nullable();            // stored file path
            $table->string('special_order')->nullable();     // stored file path
            $table->string('activity_report')->nullable();   // stored file path
            $table->string('official_documentation')->nullable(); // stored file path
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activities');
    }
};
