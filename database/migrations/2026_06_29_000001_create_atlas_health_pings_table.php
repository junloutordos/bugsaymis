<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_health_pings', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 100)->index();
            $table->string('health_status', 20);
            $table->timestamp('checked_at');

            $table->index(['module_key', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_health_pings');
    }
};
