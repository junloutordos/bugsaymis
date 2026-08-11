<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acting_as_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('substitution_id')
                ->constrained('substitutions')
                ->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 20)->nullable();
            // manual | expired | revoked | logout

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('substitution_id');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acting_as_sessions');
    }
};
