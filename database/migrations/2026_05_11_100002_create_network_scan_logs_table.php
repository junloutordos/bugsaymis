<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['snmp', 'ruijie_cloud'])->comment('Which data source produced this log');
            $table->timestamp('scanned_at');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('devices_found')->default(0);
            $table->unsignedInteger('devices_online')->default(0);
            $table->enum('status', ['success', 'failed', 'partial'])->default('success');
            $table->text('error_message')->nullable();
            $table->string('agent_version', 20)->nullable();
            $table->timestamps();

            $table->index(['source', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_scan_logs');
    }
};
