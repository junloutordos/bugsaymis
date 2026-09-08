<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for Gantimpala Agad nominations — mirrors RecognitionLog's
     * shape/conventions but scoped to this module so kiosk-originated,
     * unauthenticated actions never collide with the internal Rewards log.
     */
    public function up(): void
    {
        Schema::create('gantimpala_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomination_id')->constrained('gantimpala_nominations')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');            // submitted, endorsed, approved, rejected, archived, pdf_generated
            $table->text('notes')->nullable();
            $table->string('status_snapshot');
            $table->timestamp('acted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gantimpala_logs');
    }
};
