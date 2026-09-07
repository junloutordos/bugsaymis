<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_record_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action_type');
            $table->text('remarks')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->boolean('signed_via_pin')->default(false);
            $table->text('signature_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_status_logs');
    }
};
