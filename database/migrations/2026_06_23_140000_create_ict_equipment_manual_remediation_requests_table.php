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
        if (Schema::hasTable('ict_equipment_manual_remediation_requests')) {
            return;
        }

        Schema::create('ict_equipment_manual_remediation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->string('action');
            $table->string('target')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->enum('status', ['pending', 'delivered', 'completed', 'failed'])->default('pending');
            $table->string('result')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_manual_remediation_requests');
    }
};
