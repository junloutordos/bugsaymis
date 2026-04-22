<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recognition_logs', function (Blueprint $table) {
            $table->id();

            // The nomination this log entry is attached to
            $table->foreignId('nomination_id')
                  ->constrained('reward_nominations')
                  ->cascadeOnDelete();

            // Who performed the action
            $table->foreignId('actor_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // e.g. submitted, screened, evaluated, committee_approved,
            //      head_approved, rejected, deferred, awarded
            $table->string('action');

            $table->text('notes')->nullable();

            // Snapshot of nomination status at time of action
            $table->string('status_snapshot')->nullable();

            $table->timestamp('acted_at')->useCurrent();

            $table->index(['nomination_id', 'acted_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recognition_logs');
    }
};
