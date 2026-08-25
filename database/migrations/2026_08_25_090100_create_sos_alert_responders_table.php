<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alert_responders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained('sos_alerts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamp('unclaimed_at')->nullable();

            $table->index(['sos_alert_id', 'unclaimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alert_responders');
    }
};
