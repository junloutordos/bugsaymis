<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_housekeeping_checks')) {
            return;
        }

        Schema::create('rh_housekeeping_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_room_id')->index();
            $table->date('check_date');
            $table->unsignedBigInteger('inspected_by')->index();
            // JSON: {item_1: 3, item_2: 4, ... item_12: 2} — scores 1-4
            $table->json('scores')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->decimal('average_score', 4, 2)->nullable();
            $table->boolean('passed')->nullable();  // average >= 3
            $table->timestamps();

            $table->unique(['rh_room_id', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_housekeeping_checks');
    }
};
