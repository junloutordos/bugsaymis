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
        Schema::create('ict_equipment_security_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->unique();
            $table->boolean('antivirus_enabled')->nullable();
            $table->boolean('antivirus_up_to_date')->nullable();
            $table->boolean('firewall_enabled')->nullable();
            $table->unsignedInteger('pending_updates_count')->nullable();
            $table->timestamp('last_update_installed_at')->nullable();
            $table->boolean('reboot_required')->default(false);
            $table->unsignedInteger('unauthorized_software_count')->default(0);
            $table->unsignedTinyInteger('security_score')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_security_status');
    }
};
