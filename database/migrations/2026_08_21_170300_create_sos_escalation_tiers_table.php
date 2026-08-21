<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_escalation_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type');
            $table->unsignedInteger('order');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedInteger('timeout_minutes')->nullable();
            $table->json('channels');
            $table->boolean('notify_external')->default(false);
            $table->timestamps();

            $table->unique(['alert_type', 'order']);
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_escalation_tiers');
    }
};
