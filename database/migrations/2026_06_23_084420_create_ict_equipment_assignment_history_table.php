<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ict_equipment_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('equipment_id')->references('id')->on('ict_equipments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_assignment_history');
    }
};
