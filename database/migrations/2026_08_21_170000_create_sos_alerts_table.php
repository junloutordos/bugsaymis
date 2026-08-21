<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('triggerable_type');
            $table->unsignedBigInteger('triggerable_id');
            $table->string('alert_type');
            $table->boolean('is_silent')->default(false);
            $table->string('status')->default('triggered');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->unsignedBigInteger('geofence_zone_id')->nullable();
            $table->unsignedInteger('current_tier_order')->default(1);
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['triggerable_type', 'triggerable_id']);
            $table->index('status');
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alerts');
    }
};
