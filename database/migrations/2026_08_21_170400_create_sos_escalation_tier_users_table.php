<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_escalation_tier_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_escalation_tier_id')->constrained('sos_escalation_tiers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sos_escalation_tier_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_escalation_tier_users');
    }
};
