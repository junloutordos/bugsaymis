<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_appliances')) {
            return;
        }

        Schema::create('rh_appliances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_intern_id')->index();
            $table->string('device_type', 100);  // e.g. electric_fan, laptop, cellphone
            $table->string('device_name', 150)->nullable();
            $table->tinyInteger('unit_count')->default(1);
            $table->smallInteger('wattage')->nullable();
            $table->decimal('fee_amount', 8, 2)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_appliances');
    }
};
